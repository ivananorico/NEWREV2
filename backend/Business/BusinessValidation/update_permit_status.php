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
    $checkQuery = "SELECT id, business_permit_id, taxable_amount, tax_amount FROM business_permits WHERE id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$id]);
    $permit = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permit) {
        throw new Exception("Business permit not found with ID: " . $id);
    }
    
    // Update the permit with new status
    // Note: tax_calculated and tax_approved columns are no longer used
    // Tax calculation is inferred from taxable_amount and tax_amount > 0
    // Tax approval is inferred from status being 'Approved' or 'Active'
    $query = "UPDATE business_permits SET 
              status = :status,
              tax_amount = :tax_amount,
              regulatory_fees = :regulatory_fees,
              total_tax = :total_tax,
              approved_date = :approved_date,
              updated_at = NOW()
              WHERE id = :id";
    
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        ':status' => $status,
        ':tax_amount' => $tax_amount,
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
        $response['regulatory_fees'] = $regulatory_fees;
        $response['approved_date'] = $approved_date;
        
        // If status is being approved and tax has been calculated, create quarterly taxes
        if (($status === 'Approved' || $status === 'Active') && $tax_amount > 0) {
            createQuarterlyTaxes($pdo, $id, $total_tax, $permit['business_permit_id']);
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
function createQuarterlyTaxes($pdo, $permitId, $annualTax, $businessPermitId) {
    try {
        // Check if quarterly taxes already exist for this permit
        $checkQuery = "SELECT id FROM business_quarterly_taxes WHERE business_permit_id = ? LIMIT 1";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$permitId]);
        
        if ($checkStmt->fetch()) {
            // Quarterly taxes already exist, skip creation
            return false;
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
        
        $insertQuery = "INSERT INTO business_quarterly_taxes 
                        (business_permit_id, quarter, year, due_date, total_quarterly_tax, payment_status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
        
        $insertStmt = $pdo->prepare($insertQuery);
        
        foreach ($quarters as $quarter => $dueDate) {
            $insertStmt->execute([
                $permitId,
                $quarter,
                $currentYear,
                $dueDate,
                $quarterlyTaxAmount
            ]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error creating quarterly taxes: " . $e->getMessage());
        return false;
    }
}
?>