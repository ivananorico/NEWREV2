<?php
//revenue2/Business/BusinessValidation/update_permit_status.php

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

// Get database connection
$pdo = getDatabaseConnection();

if (!$pdo) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to connect to database'
    ]);
    exit();
}

// Function to get tax rate based on business type and taxable amount
function getBusinessTaxRate($business_nature, $taxable_amount, $tax_calculation_type, $pdo) {
    try {
        if ($tax_calculation_type == 'capital_investment') {
            // Get capital investment tax rate based on amount range
            $rate_stmt = $pdo->prepare("
                SELECT tax_percent 
                FROM capital_investment_tax_config 
                WHERE ? BETWEEN min_amount AND max_amount
                ORDER BY min_amount DESC
                LIMIT 1
            ");
            $rate_stmt->execute([$taxable_amount]);
            $rate = $rate_stmt->fetch(PDO::FETCH_ASSOC);
            return $rate['tax_percent'] ?? 25.00; // Default 25%
        } else {
            // For gross sales, use default or try to match business nature
            return 2.00; // Default 2% for gross sales
        }
    } catch(PDOException $e) {
        error_log("Error getting tax rate: " . $e->getMessage());
        return $tax_calculation_type == 'capital_investment' ? 25.00 : 2.00;
    }
}

// Function to generate reference number
function generateBusinessReferenceNumber($prefix = 'APAY-BUS') {
    $date = date('Ymd');
    $random = mt_rand(1000, 9999);
    return $prefix . '-' . $date . '-' . $random;
}

// Function to get business discount percentage
function getBusinessDiscountPercent($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT discount_percent 
            FROM business_discount_config 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? floatval($result['discount_percent']) : 0.00;
    } catch (Exception $e) {
        error_log("Error getting business discount: " . $e->getMessage());
        return 0.00;
    }
}

// Function to generate quarterly taxes for business
function createQuarterlyTaxes($pdo, $permitId, $annualTax, $applicantId, $taxableAmount, $businessNature, $taxCalculationType) {
    try {
        // Check if quarterly taxes already exist for this permit
        $checkQuery = "SELECT id FROM business_quarterly_taxes WHERE business_permit_id = ? LIMIT 1";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$permitId]);
        $exists = $checkStmt->fetch();
        
        if ($exists) {
            return false;
        }
        
        // Get tax rate
        $taxRate = getBusinessTaxRate($businessNature, $taxableAmount, $taxCalculationType, $pdo);
        
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
        
        // Insert quarterly taxes
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
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error creating quarterly taxes: " . $e->getMessage());
        return false;
    }
}

// Function to create annual payment
function createAnnualPayment($pdo, $business_permit_id, $user_id, $annual_tax) {
    try {
        $current_year = date('Y');
        $discount_percent = getBusinessDiscountPercent($pdo);
        
        // Calculate discount (apply only in January)
        $discount_amount = 0;
        if (date('n') == 1) { // January only
            $discount_amount = $annual_tax * ($discount_percent / 100);
        }
        
        $final_amount = $annual_tax - $discount_amount;
        $reference_number = generateBusinessReferenceNumber();
        $receipt_number = 'RCPT-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
        
        // Check if annual payment already exists
        $check_stmt = $pdo->prepare("
            SELECT id FROM annual_payments 
            WHERE business_permit_id = ? AND payment_year = ? AND status = 'active'
        ");
        $check_stmt->execute([$business_permit_id, $current_year]);
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            return [
                'success' => false, 
                'message' => 'Annual payment already exists for this year'
            ];
        }
        
        // Insert new annual payment
        $insert_query = "
            INSERT INTO annual_payments 
            (business_permit_id, user_id, payment_year, reference_number, total_amount,
             discount_percent, discount_amount, final_amount, payment_status,
             receipt_number, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'active', NOW())
        ";
        
        $insert_stmt = $pdo->prepare($insert_query);
        
        $insert_stmt->execute([
            $business_permit_id,
            $user_id,
            $current_year,
            $reference_number,
            $annual_tax,
            $discount_percent,
            $discount_amount,
            $final_amount,
            $receipt_number
        ]);
        
        $annual_payment_id = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'annual_payment_id' => $annual_payment_id,
            'reference_number' => $reference_number,
            'receipt_number' => $receipt_number,
            'total_amount' => $annual_tax,
            'discount_percent' => $discount_percent,
            'discount_amount' => $discount_amount,
            'final_amount' => $final_amount,
            'payment_year' => $current_year
        ];
        
    } catch (Exception $e) {
        error_log("Error creating annual payment: " . $e->getMessage());
        return [
            'success' => false, 
            'message' => 'Failed to create annual payment: ' . $e->getMessage()
        ];
    }
}

// Main function to approve permit
function approvePermit($pdo) {
    // Get input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }

    // Validate required fields
    if (!isset($data['id']) || empty(trim($data['id']))) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required field: id"]);
        return;
    }

    // Also validate tax fields
    if (!isset($data['total_tax']) || $data['total_tax'] <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid tax calculation. Please calculate tax first"]);
        return;
    }

    $permit_id = intval($data['id']);
    
    try {
        // Get business permit details - UPDATED COLUMN NAMES
        $permit_stmt = $pdo->prepare("
            SELECT id, applicant_id, taxable_amount, capital_investment, business_nature, 
                   tax_calculation_type, user_id, permit_status, tax_status
            FROM business_permits 
            WHERE id = ?
        ");
        $permit_stmt->execute([$permit_id]);
        $permit = $permit_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$permit) {
            http_response_code(404);
            echo json_encode(["error" => "Permit not found"]);
            return;
        }

        // Check if permit is already approved
        if ($permit['permit_status'] === 'APPROVED' || $permit['permit_status'] === 'ACTIVE') {
            http_response_code(400);
            echo json_encode(["error" => "Permit is already approved"]);
            return;
        }

        // Get tax values from request data
        $tax_amount = floatval($data['tax_amount'] ?? 0);
        $tax_rate = floatval($data['tax_rate'] ?? 0);
        $regulatory_fees = floatval($data['regulatory_fees'] ?? 0);
        $total_tax = floatval($data['total_tax'] ?? 0);

        // Start transaction
        $pdo->beginTransaction();

        try {
            // 1. Update permit status and tax values - UPDATED COLUMN NAMES
            $update_stmt = $pdo->prepare("
                UPDATE business_permits 
                SET permit_status = 'APPROVED', 
                    tax_status = 'Approved',
                    tax_amount = :tax_amount,
                    tax_rate = :tax_rate,
                    total_tax = :total_tax,
                    approved_date = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $update_stmt->execute([
                ':tax_amount' => $tax_amount,
                ':tax_rate' => $tax_rate,
                ':total_tax' => $total_tax,
                ':id' => $permit_id
            ]);

            // 2. GENERATE QUARTERLY TAXES
            $taxable_amount = floatval($permit['taxable_amount']) ?: floatval($permit['capital_investment']);
            $quarterly_created = createQuarterlyTaxes(
                $pdo, 
                $permit_id, 
                $total_tax,
                $permit['applicant_id'], // Changed from business_permit_id
                $taxable_amount,
                $permit['business_nature'], // Changed from business_type
                $permit['tax_calculation_type']
            );

            // 3. GENERATE ANNUAL PAYMENT RECORD (Optional)
            $annual_payment_result = null;
            $annual_payment_id = null;
            
            // Check if annual_payments table exists
            try {
                $table_check = $pdo->query("SHOW TABLES LIKE 'annual_payments'");
                $table_exists = $table_check->rowCount() > 0;
                
                if ($table_exists && isset($permit['user_id'])) {
                    // Create annual payment
                    $annual_payment_result = createAnnualPayment(
                        $pdo, 
                        $permit_id, 
                        $permit['user_id'] ?? 1, // Default user ID if not set
                        $total_tax
                    );
                }
            } catch (Exception $e) {
                // Skip annual payment if there's an error
                error_log("Annual payment skipped: " . $e->getMessage());
            }

            $pdo->commit();

            // Prepare response data
            $response_data = [
                'permit_updated' => true,
                'permit_id' => $permit_id,
                'applicant_id' => $permit['applicant_id'], // Changed from business_permit_id
                'new_status' => 'APPROVED',
                'approved_date' => date('Y-m-d H:i:s'),
                'totals' => [
                    'taxable_amount' => $taxable_amount,
                    'tax_amount' => $tax_amount,
                    'tax_rate' => $tax_rate,
                    'regulatory_fees' => $regulatory_fees,
                    'total_tax' => $total_tax
                ],
                'quarterly_taxes_created' => $quarterly_created
            ];

            // Add annual payment info if created
            if ($annual_payment_result && $annual_payment_result['success']) {
                $response_data['annual_payment'] = [
                    'id' => $annual_payment_result['annual_payment_id'],
                    'reference_number' => $annual_payment_result['reference_number'],
                    'receipt_number' => $annual_payment_result['receipt_number'],
                    'total_amount' => $annual_payment_result['total_amount'],
                    'discount_percent' => $annual_payment_result['discount_percent'],
                    'discount_amount' => $annual_payment_result['discount_amount'],
                    'final_amount' => $annual_payment_result['final_amount'],
                    'payment_year' => $annual_payment_result['payment_year']
                ];
            }

            echo json_encode([
                "status" => "success",
                "message" => "Business permit approved successfully.",
                "data" => $response_data
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Transaction failed: " . $e->getMessage()
            ]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $e->getMessage()
        ]);
    }
}

// Determine HTTP method and route
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        approvePermit($pdo);
        break;
    case 'OPTIONS':
        http_response_code(200);
        exit();
    default:
        http_response_code(405);
        echo json_encode([
            "status" => "error",
            "message" => "Method not allowed"
        ]);
        break;
}
?>