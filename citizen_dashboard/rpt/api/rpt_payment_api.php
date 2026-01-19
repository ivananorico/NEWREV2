<?php
// revenue2/citizen_dashboard/rpt/api/rpt_payment_api.php - WITH TAX CLEARANCE

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type
header('Content-Type: application/json');

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// If JSON input is empty, try form data
if (empty($data)) {
    $data = $_POST;
}

// Extract data
$reference_id = $data['reference_id'] ?? null;
$receipt_number = $data['receipt_number'] ?? null;
$amount = $data['amount'] ?? null;
$purpose = $data['purpose'] ?? null;
$paid_at = $data['paid_at'] ?? date('Y-m-d H:i:s');
$client_system = $data['client_system'] ?? null;
$payment_id = $data['payment_id'] ?? null;
$phone = $data['phone'] ?? null;

// Validate required fields
if (!$reference_id || !$receipt_number) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Missing required fields: reference_id and receipt_number are required',
        'received_data' => $data
    ]);
    exit();
}

// Check if this is actually for RPT system
if ($client_system !== 'rpt') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'This API is only for RPT system. Received callback for: ' . $client_system
    ]);
    exit();
}

// Check if we can include the database file
$rpt_db_path = __DIR__ . '/../../../db/RPT/rpt_db.php';

if (!file_exists($rpt_db_path)) {
    // Try alternative paths
    $alt_paths = [
        __DIR__ . '/../../../../db/RPT/rpt_db.php',
        'C:/xampp/htdocs/revenue2/db/RPT/rpt_db.php',
        '../../../db/RPT/rpt_db.php'
    ];
    
    foreach ($alt_paths as $path) {
        if (file_exists($path)) {
            $rpt_db_path = $path;
            break;
        }
    }
    
    if (!file_exists($rpt_db_path)) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Database configuration not found'
        ]);
        exit();
    }
}

// Include the database file
include_once $rpt_db_path;

try {
    // Get database connection
    $rpt_pdo = getDatabaseConnection();
    
    if (!$rpt_pdo) {
        throw new Exception('Failed to connect to RPT database.');
    }
    
    // Test the connection
    $rpt_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction for atomic operations
    $rpt_pdo->beginTransaction();
    
    // Check if this is an annual payment (starts with ANNUAL-)
    if (strpos($reference_id, 'ANNUAL-') === 0) {
        // Extract annual reference number
        $annual_ref = substr($reference_id, 7); // Remove "ANNUAL-" prefix
        
        // Check if annual payment exists
        $check_annual_query = "
            SELECT ap.*, pt.id as property_total_id
            FROM annual_payments ap
            JOIN property_totals pt ON ap.property_total_id = pt.id
            WHERE ap.reference_number = :annual_ref
        ";
        
        $check_annual_stmt = $rpt_pdo->prepare($check_annual_query);
        $check_annual_stmt->execute([':annual_ref' => $annual_ref]);
        $annual_payment = $check_annual_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$annual_payment) {
            throw new Exception("Annual payment not found: $annual_ref");
        }
        
        // Check if already paid
        if ($annual_payment['payment_status'] == 'paid') {
            echo json_encode([
                'status' => 'warning',
                'message' => 'This annual payment is already marked as paid',
                'reference_id' => $reference_id,
                'annual_ref' => $annual_ref,
                'current_status' => 'paid'
            ]);
            exit();
        }
        
        // Update annual_payments
        $update_annual_query = "
            UPDATE annual_payments 
            SET payment_status = 'paid',
                payment_date = :paid_at,
                receipt_number = :receipt_number,
                transaction_id = :payment_id
            WHERE reference_number = :annual_ref
        ";
        
        $annual_stmt = $rpt_pdo->prepare($update_annual_query);
        $annual_stmt->execute([
            ':paid_at' => $paid_at,
            ':receipt_number' => $receipt_number,
            ':payment_id' => $payment_id,
            ':annual_ref' => $annual_ref
        ]);
        
        $annual_rows_updated = $annual_stmt->rowCount();
        
        if ($annual_rows_updated === 0) {
            throw new Exception("No rows updated in annual_payments.");
        }
        
        // AUTOMATICALLY MARK ALL QUARTERLY TAXES AS PAID FOR THIS YEAR
        $property_total_id = $annual_payment['property_total_id'];
        $payment_year = $annual_payment['payment_year'];
        $discount_percent = $annual_payment['discount_percent'];
        
        // Get all quarterly taxes for this property and year
        $quarterly_tax_query = "
            SELECT id, total_quarterly_tax 
            FROM quarterly_taxes 
            WHERE property_total_id = :property_total_id 
            AND year = :payment_year
        ";
        
        $quarterly_tax_stmt = $rpt_pdo->prepare($quarterly_tax_query);
        $quarterly_tax_stmt->execute([
            ':property_total_id' => $property_total_id,
            ':payment_year' => $payment_year
        ]);
        $quarterly_taxes = $quarterly_tax_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update each quarterly tax with the SAME receipt and date
        foreach ($quarterly_taxes as $quarterly) {
            // Calculate discount for this quarter
            $quarter_discount_amount = $discount_percent > 0 
                ? ($quarterly['total_quarterly_tax'] * ($discount_percent / 100))
                : 0;
            
            $update_quarterly_query = "
                UPDATE quarterly_taxes 
                SET payment_status = 'paid',
                    payment_date = :paid_at,
                    receipt_number = :receipt_number,
                    discount_applied = :discount_applied,
                    discount_percent_used = :discount_percent,
                    discount_amount = :discount_amount
                WHERE id = :quarterly_id
            ";
            
            $update_quarterly_stmt = $rpt_pdo->prepare($update_quarterly_query);
            $update_quarterly_stmt->execute([
                ':paid_at' => $paid_at,
                ':receipt_number' => $receipt_number,
                ':discount_applied' => $discount_percent > 0 ? 1 : 0,
                ':discount_percent' => $discount_percent,
                ':discount_amount' => $quarter_discount_amount,
                ':quarterly_id' => $quarterly['id']
            ]);
        }
        
        $quarterly_rows_updated = count($quarterly_taxes);
        
        // =============== TAX CLEARANCE AUTOMATION FOR ANNUAL PAYMENT ===============
        $clearance_generated = false;
        $certificate_number = null;
        
        try {
            // Since we just marked all quarters as paid, generate tax clearance
            error_log("ANNUAL PAYMENT: All quarters marked as paid. Generating tax clearance...");
            
            // Check if clearance already exists
            $check_clearance_query = "
                SELECT id FROM tax_clearances 
                WHERE property_total_id = :property_total_id 
                AND clearance_year = :year
            ";
            $clearance_stmt = $rpt_pdo->prepare($check_clearance_query);
            $clearance_stmt->execute([
                ':property_total_id' => $property_total_id,
                ':year' => $payment_year
            ]);
            
            if ($clearance_stmt->rowCount() == 0) {
                // Generate certificate number
                $certificate_number = 'RPT-CLR-' . date('Y') . '-' . str_pad($property_total_id, 6, '0', STR_PAD_LEFT) . '-' . $payment_year;
                
                // Insert into tax_clearances table
                $insert_query = "
                    INSERT INTO tax_clearances 
                    (certificate_number, property_total_id, clearance_year, issue_date, generated_by)
                    VALUES (:cert_number, :property_id, :year, CURDATE(), 1)
                ";
                
                $insert_stmt = $rpt_pdo->prepare($insert_query);
                $insert_stmt->execute([
                    ':cert_number' => $certificate_number,
                    ':property_id' => $property_total_id,
                    ':year' => $payment_year
                ]);
                
                $clearance_id = $rpt_pdo->lastInsertId();
                $clearance_generated = true;
                
                error_log("Tax clearance generated! ID: $clearance_id, Certificate: $certificate_number");
                
                // Also update property_totals to show clearance is available
                $update_property_query = "
                    UPDATE property_totals 
                    SET tax_clearance_available = 1,
                        last_clearance_generated = CURDATE()
                    WHERE id = :property_total_id
                ";
                $update_property_stmt = $rpt_pdo->prepare($update_property_query);
                $update_property_stmt->execute([':property_total_id' => $property_total_id]);
                
                error_log("Property totals updated with tax clearance flag");
            } else {
                error_log("Tax clearance already exists for property_total_id: $property_total_id, year: $payment_year");
            }
        } catch (Exception $clearance_error) {
            // Don't fail the whole payment if clearance generation fails
            error_log("Tax clearance generation error (non-critical): " . $clearance_error->getMessage());
            $clearance_generated = false;
        }
        // =============== END TAX CLEARANCE AUTOMATION ===============
        
        $rpt_pdo->commit();
        
        $response = [
            'status' => 'success',
            'message' => 'Annual payment processed successfully. All quarterly taxes marked as paid with same receipt.',
            'reference_id' => $reference_id,
            'annual_ref' => $annual_ref,
            'annual_payment_updated' => true,
            'quarterly_taxes_updated' => $quarterly_rows_updated,
            'receipt_number' => $receipt_number,
            'payment_date' => $paid_at,
            'discount_applied' => $discount_percent,
            'type' => 'annual'
        ];
        
        // Add clearance info if generated
        if ($clearance_generated) {
            $response['tax_clearance'] = [
                'generated' => true,
                'certificate_number' => $certificate_number,
                'year' => $payment_year
            ];
        }
        
        echo json_encode($response);
        
    } else {
        // This is a quarterly payment
        $quarterly_id = $reference_id;
        
        // Check if the quarterly tax exists
        $check_query = "
            SELECT id, payment_status, receipt_number, property_total_id, year 
            FROM quarterly_taxes 
            WHERE id = :quarterly_id
        ";
        
        $check_stmt = $rpt_pdo->prepare($check_query);
        $check_stmt->execute([':quarterly_id' => $quarterly_id]);
        $quarterly_tax = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quarterly_tax) {
            throw new Exception("Quarterly tax not found: $quarterly_id");
        }
        
        // Check if already paid
        if ($quarterly_tax['payment_status'] == 'paid') {
            echo json_encode([
                'status' => 'warning',
                'message' => 'This tax is already marked as paid',
                'reference_id' => $reference_id,
                'quarterly_id' => $quarterly_id,
                'current_status' => 'paid'
            ]);
            exit();
        }
        
        // Check if annual payment exists for this property and year
        $check_annual_exists_query = "
            SELECT id, reference_number, payment_status 
            FROM annual_payments 
            WHERE property_total_id = :property_total_id 
            AND payment_year = :year
            AND status = 'active'
        ";
        
        $annual_exists_stmt = $rpt_pdo->prepare($check_annual_exists_query);
        $annual_exists_stmt->execute([
            ':property_total_id' => $quarterly_tax['property_total_id'],
            ':year' => $quarterly_tax['year']
        ]);
        $existing_annual = $annual_exists_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_annual && $existing_annual['payment_status'] == 'pending') {
            // Mark annual payment as cancelled if it exists and is pending
            $cancel_annual_query = "
                UPDATE annual_payments 
                SET payment_status = 'cancelled',
                    status = 'inactive'
                WHERE id = :annual_id
            ";
            
            $cancel_annual_stmt = $rpt_pdo->prepare($cancel_annual_query);
            $cancel_annual_stmt->execute([':annual_id' => $existing_annual['id']]);
        }
        
        // Update quarterly_taxes
        $update_query = "
            UPDATE quarterly_taxes 
            SET payment_status = 'paid',
                payment_date = :paid_at,
                receipt_number = :receipt_number
            WHERE id = :quarterly_id
        ";
        
        $stmt = $rpt_pdo->prepare($update_query);
        $stmt->execute([
            ':paid_at' => $paid_at,
            ':receipt_number' => $receipt_number,
            ':quarterly_id' => $quarterly_id
        ]);
        
        $rows_updated = $stmt->rowCount();
        
        if ($rows_updated === 0) {
            throw new Exception("No rows updated.");
        }
        
        // =============== TAX CLEARANCE AUTOMATION FOR QUARTERLY PAYMENT ===============
        $clearance_generated = false;
        $certificate_number = null;
        
        try {
            // Check if all quarters for this year are now paid
            $check_all_paid_query = "
                SELECT 
                    COUNT(*) as total_quarters,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_quarters
                FROM quarterly_taxes 
                WHERE property_total_id = :property_total_id 
                AND year = :year
            ";
            
            $check_stmt = $rpt_pdo->prepare($check_all_paid_query);
            $check_stmt->execute([
                ':property_total_id' => $quarterly_tax['property_total_id'],
                ':year' => $quarterly_tax['year']
            ]);
            $status = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Quarterly payment: Checking tax status for year {$quarterly_tax['year']}: {$status['paid_quarters']}/{$status['total_quarters']} quarters paid");
            
            // If all 4 quarters are paid
            if ($status['total_quarters'] == 4 && $status['paid_quarters'] == 4) {
                error_log("ALL QUARTERS PAID! Generating tax clearance for year {$quarterly_tax['year']}");
                
                // Check if clearance already exists
                $check_clearance_query = "
                    SELECT id FROM tax_clearances 
                    WHERE property_total_id = :property_total_id 
                    AND clearance_year = :year
                ";
                $clearance_stmt = $rpt_pdo->prepare($check_clearance_query);
                $clearance_stmt->execute([
                    ':property_total_id' => $quarterly_tax['property_total_id'],
                    ':year' => $quarterly_tax['year']
                ]);
                
                if ($clearance_stmt->rowCount() == 0) {
                    // Generate certificate number
                    $certificate_number = 'RPT-CLR-' . date('Y') . '-' . str_pad($quarterly_tax['property_total_id'], 6, '0', STR_PAD_LEFT) . '-' . $quarterly_tax['year'];
                    
                    // Insert into tax_clearances table
                    $insert_query = "
                        INSERT INTO tax_clearances 
                        (certificate_number, property_total_id, clearance_year, issue_date, generated_by)
                        VALUES (:cert_number, :property_id, :year, CURDATE(), 1)
                    ";
                    
                    $insert_stmt = $rpt_pdo->prepare($insert_query);
                    $insert_stmt->execute([
                        ':cert_number' => $certificate_number,
                        ':property_id' => $quarterly_tax['property_total_id'],
                        ':year' => $quarterly_tax['year']
                    ]);
                    
                    $clearance_id = $rpt_pdo->lastInsertId();
                    $clearance_generated = true;
                    
                    error_log("Tax clearance generated! ID: $clearance_id, Certificate: $certificate_number");
                    
                    // Also update property_totals to show clearance is available
                    $update_property_query = "
                        UPDATE property_totals 
                        SET tax_clearance_available = 1,
                            last_clearance_generated = CURDATE()
                        WHERE id = :property_total_id
                    ";
                    $update_property_stmt = $rpt_pdo->prepare($update_property_query);
                    $update_property_stmt->execute([':property_total_id' => $quarterly_tax['property_total_id']]);
                }
            }
        } catch (Exception $clearance_error) {
            // Don't fail the whole payment if clearance generation fails
            error_log("Tax clearance generation error (non-critical): " . $clearance_error->getMessage());
        }
        // =============== END TAX CLEARANCE AUTOMATION ===============
        
        $rpt_pdo->commit();
        
        // Return success response
        $response = [
            'status' => 'success',
            'message' => 'Payment updated successfully in RPT database',
            'rows_updated' => $rows_updated,
            'reference_id' => $reference_id,
            'quarterly_id' => $quarterly_id,
            'receipt_number' => $receipt_number,
            'payment_date' => $paid_at,
            'type' => 'quarterly',
            'annual_cancelled' => isset($existing_annual) ? true : false
        ];
        
        // Add clearance info if generated
        if ($clearance_generated) {
            $response['tax_clearance'] = [
                'generated' => true,
                'certificate_number' => $certificate_number,
                'year' => $quarterly_tax['year']
            ];
        }
        
        echo json_encode($response);
    }
    
} catch (PDOException $e) {
    if (isset($rpt_pdo)) {
        $rpt_pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'reference_id' => $reference_id
    ]);
} catch (Exception $e) {
    if (isset($rpt_pdo)) {
        $rpt_pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'API error: ' . e->getMessage(),
        'reference_id' => $reference_id
    ]);
}
?>