<?php
// Your common CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, expires, Cache-Control, Pragma");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Use your existing database connection
require_once '../../../db/Business/business_db.php';

$response = ['status' => 'error', 'message' => ''];

// Function to get tax rate based on business type and taxable amount
function getBusinessTaxRate($business_type, $taxable_amount, $tax_calculation_type, $pdo) {
    try {
        if ($tax_calculation_type == 'capital_investment') {
            // Get capital investment tax rate based on amount range
            $rate_stmt = $pdo->prepare("
                SELECT tax_percent 
                FROM capital_investment_tax_config 
                WHERE :amount BETWEEN min_amount AND max_amount
                AND (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
                ORDER BY min_amount DESC
                LIMIT 1
            ");
            $rate_stmt->execute(['amount' => $taxable_amount]);
            $rate = $rate_stmt->fetch(PDO::FETCH_ASSOC);
            return $rate['tax_percent'] ?? 25.00; // Default 25%
        } else {
            // Get gross sales tax rate based on business type
            $rate_stmt = $pdo->prepare("
                SELECT tax_percent 
                FROM gross_sales_tax_config 
                WHERE business_type = :business_type
                AND (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
                LIMIT 1
            ");
            $rate_stmt->execute(['business_type' => $business_type]);
            $rate = $rate_stmt->fetch(PDO::FETCH_ASSOC);
            return $rate['tax_percent'] ?? 2.00; // Default 2%
        }
    } catch(PDOException $e) {
        error_log("Error getting tax rate: " . $e->getMessage());
        // Return default rates based on calculation type
        return $tax_calculation_type == 'capital_investment' ? 25.00 : 2.00;
    }
}

try {
    // Get JSON data from request
    $json_data = file_get_contents("php://input");
    
    if (empty($json_data)) {
        throw new Exception("No data received");
    }
    
    $data = json_decode($json_data);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON data: " . json_last_error_msg());
    }
    
    if (!isset($data->id)) {
        throw new Exception("Permit ID is required");
    }
    
    $id = intval($data->id);
    $status = $data->status ?? 'Approved';
    $action_by = $data->action_by ?? 'system';
    $remarks = $data->remarks ?? '';
    $tax_amount = $data->tax_amount ?? 0;
    $regulatory_fees = $data->regulatory_fees ?? 0;
    $total_tax = $data->total_tax ?? 0;
    $approved_date = $data->approved_date ?? date('Y-m-d H:i:s');
    
    // Check if permit exists and get current data
    $checkQuery = "SELECT id, business_permit_id, taxable_amount, tax_amount, 
                          tax_rate, business_type, tax_calculation_type 
                   FROM business_permits WHERE id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$id]);
    $permit = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permit) {
        throw new Exception("Business permit not found with ID: " . $id);
    }
    
    // Calculate tax rate if not provided
    $tax_rate = $permit['tax_rate'] ?? 0;
    if ($tax_rate == 0 && $permit['taxable_amount'] > 0 && $tax_amount > 0) {
        // Calculate tax rate from taxable amount and tax amount
        $tax_rate = ($tax_amount / $permit['taxable_amount']) * 100;
    }
    
    // Update the permit with new status
    $query = "UPDATE business_permits SET 
              status = :status,
              tax_amount = :tax_amount,
              tax_rate = :tax_rate,
              regulatory_fees = :regulatory_fees,
              total_tax = :total_tax,
              approved_date = :approved_date,
              updated_at = NOW()
              WHERE id = :id";
    
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        ':status' => $status,
        ':tax_amount' => $tax_amount,
        ':tax_rate' => $tax_rate,
        ':regulatory_fees' => $regulatory_fees,
        ':total_tax' => $total_tax,
        ':approved_date' => $approved_date,
        ':id' => $id
    ]);
    
    if ($result) {
        $response['status'] = 'success';
        $response['message'] = 'Permit status updated successfully';
        $response['permit_id'] = $id;
        $response['business_permit_id'] = $permit['business_permit_id'];
        $response['new_status'] = $status;
        $response['total_tax'] = $total_tax;
        $response['tax_amount'] = $tax_amount;
        $response['tax_rate'] = $tax_rate;
        $response['regulatory_fees'] = $regulatory_fees;
        $response['approved_date'] = $approved_date;
        
        // If status is being approved and tax has been calculated, create quarterly taxes
        if (($status === 'Approved' || $status === 'Active') && $tax_amount > 0) {
            createQuarterlyTaxes($pdo, $id, $total_tax, $permit['business_permit_id'], 
                                $permit['taxable_amount'], $permit['business_type'], 
                                $permit['tax_calculation_type'], $tax_rate);
            $response['quarterly_taxes_created'] = true;
        }
    } else {
        throw new Exception("Failed to update permit status. Please try again.");
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Error updating permit status: " . $e->getMessage());
}

echo json_encode($response);

// ==================== CREATE QUARTERLY TAXES FUNCTION ====================
function createQuarterlyTaxes($pdo, $permitId, $annualTax, $businessPermitId, $taxableAmount, $businessType, $taxCalculationType, $taxRate) {
    try {
        // Check if quarterly taxes already exist for this permit
        $checkQuery = "SELECT id FROM business_quarterly_taxes WHERE business_permit_id = ? LIMIT 1";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$permitId]);
        
        if ($checkStmt->fetch()) {
            // Quarterly taxes already exist, skip creation
            return false;
        }
        
        // Get tax rate from config if not provided
        if ($taxRate == 0) {
            $taxRate = getBusinessTaxRate($businessType, $taxableAmount, $taxCalculationType, $pdo);
        }
        
        // Calculate quarterly tax amount (annual tax divided by 4)
        $quarterlyTaxAmount = $annualTax / 4;
        
        // Create quarterly tax records for the current year
        $currentYear = date('Y');
        $quarters = [
            'Q1' => $currentYear . '-03-31',
            'Q2' => $currentYear . '-06-30', 
            'Q3' => $currentYear . '-09-30',
            'Q4' => $currentYear . '-12-31'
        ];
        
        // Check if tax_rate_used column exists
        $columnCheck = $pdo->query("SHOW COLUMNS FROM business_quarterly_taxes LIKE 'tax_rate_used'");
        $columnExists = $columnCheck->fetch();
        
        if ($columnExists) {
            // Column exists, use it
            $insertQuery = "INSERT INTO business_quarterly_taxes 
                            (business_permit_id, quarter, year, due_date, total_quarterly_tax, 
                             tax_type_used, tax_rate_used, payment_status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $insertStmt = $pdo->prepare($insertQuery);
            
            foreach ($quarters as $quarter => $dueDate) {
                $insertStmt->execute([
                    $permitId,
                    $quarter,
                    $currentYear,
                    $dueDate,
                    $quarterlyTaxAmount,
                    $taxCalculationType,
                    $taxRate
                ]);
            }
        } else {
            // Column doesn't exist, use simpler query
            $insertQuery = "INSERT INTO business_quarterly_taxes 
                            (business_permit_id, quarter, year, due_date, total_quarterly_tax, 
                             tax_type_used, payment_status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $insertStmt = $pdo->prepare($insertQuery);
            
            foreach ($quarters as $quarter => $dueDate) {
                $insertStmt->execute([
                    $permitId,
                    $quarter,
                    $currentYear,
                    $dueDate,
                    $quarterlyTaxAmount,
                    $taxCalculationType
                ]);
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error creating quarterly taxes: " . $e->getMessage());
        return false;
    }
}
?>