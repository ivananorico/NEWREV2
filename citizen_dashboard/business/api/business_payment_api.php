<?php
// revenue2/citizen_dashboard/business/api/business_payment_api.php

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

// Extract UNIVERSAL data
$reference_id = $data['reference_id'] ?? null;
$receipt_number = $data['receipt_number'] ?? null;
$paid_at = $data['paid_at'] ?? date('Y-m-d H:i:s');
$amount = $data['amount'] ?? null;
$purpose = $data['purpose'] ?? null;
$client_system = $data['client_system'] ?? null;
$payment_id = $data['payment_id'] ?? null;

// Log for debugging
error_log("=== BUSINESS PAYMENT API CALLED ===");
error_log("Client System: $client_system");
error_log("Reference ID: $reference_id");
error_log("Receipt: $receipt_number");
error_log("Payment ID: $payment_id");

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

// Verify this is for business system
if ($client_system !== 'business') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'This API is for business system only. Received: ' . $client_system
    ]);
    exit();
}

// Include the business database connection
$business_db_path = __DIR__ . '/../../../db/Business/business_db.php';

if (!file_exists($business_db_path)) {
    error_log("Database file NOT found at: " . $business_db_path);
    
    // Try alternative paths
    $alt_paths = [
        __DIR__ . '/../../../../db/Business/business_db.php',
        'C:/xampp/htdocs/revenue2/db/Business/business_db.php'
    ];
    
    foreach ($alt_paths as $path) {
        error_log("Checking alternative: " . $path);
        if (file_exists($path)) {
            $business_db_path = $path;
            error_log("Found database file at: " . $path);
            break;
        }
    }
    
    if (!file_exists($business_db_path)) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Business database configuration not found'
        ]);
        exit();
    }
}

include_once $business_db_path;

try {
    // Get database connection
    $business_pdo = getDatabaseConnection();
    
    if (!$business_pdo) {
        throw new Exception('Failed to connect to Business database.');
    }
    
    // Test the connection
    $business_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction for atomic operations
    $business_pdo->beginTransaction();
    
    // Check if this is an annual payment (starts with ANNUAL-)
    if (strpos($reference_id, 'ANNUAL-') === 0) {
        // Extract annual reference number
        $annual_ref = substr($reference_id, 7); // Remove "ANNUAL-" prefix
        
        error_log("Processing ANNUAL payment with reference: $annual_ref");
        
        // Check if annual payment exists
        $check_annual_query = "
            SELECT ap.*, bp.id as business_permit_id, bp.business_name, bp.business_permit_id as permit_number
            FROM annual_payments ap
            JOIN business_permits bp ON ap.business_permit_id = bp.id
            WHERE ap.reference_number = :annual_ref
        ";
        
        $check_annual_stmt = $business_pdo->prepare($check_annual_query);
        $check_annual_stmt->execute([':annual_ref' => $annual_ref]);
        $annual_payment = $check_annual_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$annual_payment) {
            throw new Exception("Annual payment not found: $annual_ref");
        }
        
        error_log("Found annual payment: ID={$annual_payment['id']}, Status={$annual_payment['payment_status']}, Year={$annual_payment['payment_year']}");
        
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
        
        $annual_stmt = $business_pdo->prepare($update_annual_query);
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
        $business_permit_id = $annual_payment['business_permit_id'];
        $payment_year = $annual_payment['payment_year'];
        $discount_percent = $annual_payment['discount_percent'];
        
        // Get all quarterly taxes for this business and year
        $quarterly_tax_query = "
            SELECT id, total_quarterly_tax 
            FROM business_quarterly_taxes 
            WHERE business_permit_id = :business_permit_id 
            AND year = :payment_year
        ";
        
        $quarterly_tax_stmt = $business_pdo->prepare($quarterly_tax_query);
        $quarterly_tax_stmt->execute([
            ':business_permit_id' => $business_permit_id,
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
                UPDATE business_quarterly_taxes 
                SET payment_status = 'paid',
                    payment_date = :paid_at,
                    receipt_number = :receipt_number,
                    discount_applied = :discount_applied,
                    discount_percent_used = :discount_percent,
                    discount_amount = :discount_amount
                WHERE id = :quarterly_id
            ";
            
            $update_quarterly_stmt = $business_pdo->prepare($update_quarterly_query);
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
        
        // =============== BUSINESS TAX CLEARANCE AUTOMATION FOR ANNUAL PAYMENT ===============
        $clearance_generated = false;
        $certificate_number = null;
        
        try {
            // Since we just marked all quarters as paid, generate tax clearance
            error_log("BUSINESS ANNUAL PAYMENT: All quarters marked as paid. Generating tax clearance...");
            
            // Check if clearance already exists
            $check_clearance_query = "
                SELECT id FROM business_tax_clearances 
                WHERE business_permit_id = :business_permit_id 
                AND clearance_year = :year
            ";
            $clearance_stmt = $business_pdo->prepare($check_clearance_query);
            $clearance_stmt->execute([
                ':business_permit_id' => $business_permit_id,
                ':year' => $payment_year
            ]);
            
            if ($clearance_stmt->rowCount() == 0) {
                // Generate certificate number
                $certificate_number = 'BUS-CLR-' . date('Y') . '-' . str_pad($business_permit_id, 6, '0', STR_PAD_LEFT) . '-' . $payment_year;
                
                // Insert into business_tax_clearances table
                $insert_query = "
                    INSERT INTO business_tax_clearances 
                    (certificate_number, business_permit_id, clearance_year, issue_date, generated_by)
                    VALUES (:cert_number, :business_permit_id, :year, CURDATE(), 1)
                ";
                
                $insert_stmt = $business_pdo->prepare($insert_query);
                $insert_stmt->execute([
                    ':cert_number' => $certificate_number,
                    ':business_permit_id' => $business_permit_id,
                    ':year' => $payment_year
                ]);
                
                $clearance_id = $business_pdo->lastInsertId();
                
                error_log("Business tax clearance generated! ID: $clearance_id, Certificate: $certificate_number");
                
                // Get business info for notification
                $business_query = "
                    SELECT bp.business_name, bp.full_name, bp.personal_email, bp.business_permit_id
                    FROM business_permits bp
                    WHERE bp.id = :business_permit_id
                ";
                $business_stmt = $business_pdo->prepare($business_query);
                $business_stmt->execute([':business_permit_id' => $business_permit_id]);
                $business_info = $business_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($business_info) {
                    // Log notification
                    $notification_query = "
                        INSERT INTO notifications 
                        (user_email, subject, message, sent_at) 
                        VALUES (:email, :subject, :message, NOW())
                    ";
                    
                    $notification_stmt = $business_pdo->prepare($notification_query);
                    $notification_stmt->execute([
                        ':email' => $business_info['personal_email'],
                        ':subject' => 'Business Tax Clearance Certificate Generated - ' . $certificate_number,
                        ':message' => "Dear " . $business_info['full_name'] . ",\n\nYour business tax clearance certificate has been generated for " . $business_info['business_name'] . " (Permit: " . $business_info['business_permit_id'] . ") for year " . $payment_year . ".\n\nCertificate Number: " . $certificate_number . "\n\nYou can download it from your dashboard.\n\nThank you!"
                    ]);
                    
                    error_log("Notification sent to: " . $business_info['personal_email']);
                }
                
                $clearance_generated = true;
            } else {
                error_log("Business tax clearance already exists for this permit and year");
            }
        } catch (Exception $clearance_error) {
            // Don't fail the whole payment if clearance generation fails
            error_log("Business tax clearance generation error (non-critical): " . $clearance_error->getMessage());
        }
        // =============== END BUSINESS TAX CLEARANCE AUTOMATION ===============
        
        $business_pdo->commit();
        
        $response = [
            'status' => 'success',
            'message' => 'Annual payment processed successfully. All quarterly taxes marked as paid with same receipt.',
            'reference_id' => $reference_id,
            'annual_ref' => $annual_ref,
            'business_permit_id' => $annual_payment['permit_number'],
            'business_name' => $annual_payment['business_name'],
            'annual_payment_updated' => true,
            'quarterly_taxes_updated' => $quarterly_rows_updated,
            'receipt_number' => $receipt_number,
            'payment_date' => $paid_at,
            'discount_applied' => $discount_percent,
            'payment_year' => $payment_year,
            'type' => 'annual'
        ];
        
        // Add clearance info if generated
        if ($clearance_generated) {
            $response['business_tax_clearance'] = [
                'generated' => true,
                'certificate_number' => $certificate_number,
                'year' => $payment_year,
                'message' => 'Tax clearance certificate has been generated for your business'
            ];
        }
        
        echo json_encode($response);
        
    } else {
        // This is a quarterly payment
        $quarterly_id = $reference_id;
        
        error_log("Processing QUARTERLY payment with ID: $quarterly_id");
        
        // Check if the quarterly tax exists
        $check_query = "
            SELECT 
                bqt.id, 
                bqt.payment_status, 
                bqt.receipt_number,
                bqt.business_permit_id,
                bqt.year,
                bp.business_permit_id as permit_number,
                bp.business_name
            FROM business_quarterly_taxes bqt
            INNER JOIN business_permits bp ON bqt.business_permit_id = bp.id
            WHERE bqt.id = :quarterly_id
        ";
        
        $check_stmt = $business_pdo->prepare($check_query);
        $check_stmt->execute([':quarterly_id' => $quarterly_id]);
        $quarterly_tax = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quarterly_tax) {
            error_log("Business quarterly tax NOT found with ID: $quarterly_id");
            throw new Exception("Business quarterly tax with ID $quarterly_id not found in database");
        }
        
        error_log("Found business quarterly tax: ID={$quarterly_tax['id']}, Status={$quarterly_tax['payment_status']}, Year={$quarterly_tax['year']}");
        
        // Check if already paid
        if ($quarterly_tax['payment_status'] == 'paid') {
            error_log("Business quarterly tax $quarterly_id is already paid");
            echo json_encode([
                'status' => 'warning',
                'message' => 'This quarterly business tax is already marked as paid',
                'reference_id' => $reference_id,
                'quarterly_id' => $quarterly_id,
                'current_status' => 'paid',
                'current_receipt' => $quarterly_tax['receipt_number']
            ]);
            exit();
        }
        
        // Check if annual payment exists for this business and year
        $check_annual_exists_query = "
            SELECT id, reference_number, payment_status 
            FROM annual_payments 
            WHERE business_permit_id = :business_permit_id 
            AND payment_year = :year
            AND status = 'active'
        ";
        
        $annual_exists_stmt = $business_pdo->prepare($check_annual_exists_query);
        $annual_exists_stmt->execute([
            ':business_permit_id' => $quarterly_tax['business_permit_id'],
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
            
            $cancel_annual_stmt = $business_pdo->prepare($cancel_annual_query);
            $cancel_annual_stmt->execute([':annual_id' => $existing_annual['id']]);
            
            error_log("Cancelled annual payment ID: {$existing_annual['id']} because quarterly was paid");
        }
        
        // Update business_quarterly_taxes
        $update_query = "
            UPDATE business_quarterly_taxes 
            SET payment_status = 'paid',
                payment_date = :paid_at,
                receipt_number = :receipt_number
            WHERE id = :quarterly_id
        ";
        
        $stmt = $business_pdo->prepare($update_query);
        $stmt->execute([
            ':paid_at' => $paid_at,
            ':receipt_number' => $receipt_number,
            ':quarterly_id' => $quarterly_id
        ]);
        
        $rows_updated = $stmt->rowCount();
        
        if ($rows_updated === 0) {
            throw new Exception("No rows updated. Business quarterly tax ID $quarterly_id may not exist or is already updated.");
        }
        
        // Verify the update
        $verify_stmt = $business_pdo->prepare("SELECT id, payment_status, receipt_number FROM business_quarterly_taxes WHERE id = :quarterly_id");
        $verify_stmt->execute([':quarterly_id' => $quarterly_id]);
        $updated_record = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$updated_record) {
            throw new Exception("Failed to verify update. Record not found after update.");
        }
        
        if ($updated_record['payment_status'] !== 'paid') {
            throw new Exception("Update verification failed. Status is still: " . $updated_record['payment_status']);
        }
        
        error_log("Successfully updated business tax $reference_id. New status: paid, Receipt: $receipt_number");
        
        // =============== BUSINESS TAX CLEARANCE AUTOMATION ===============
        $clearance_generated = false;
        $certificate_number = null;
        
        try {
            $business_permit_id = $quarterly_tax['business_permit_id'];
            $year = $quarterly_tax['year'];
            
            error_log("Checking business tax clearance eligibility for permit ID: $business_permit_id, year: $year");
            
            // Check if all quarters for this year are now paid
            $check_all_paid_query = "
                SELECT 
                    COUNT(*) as total_quarters,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_quarters
                FROM business_quarterly_taxes 
                WHERE business_permit_id = :business_permit_id 
                AND year = :year
            ";
            
            $check_stmt = $business_pdo->prepare($check_all_paid_query);
            $check_stmt->execute([
                ':business_permit_id' => $business_permit_id,
                ':year' => $year
            ]);
            $status = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Business tax status for year $year: {$status['paid_quarters']}/{$status['total_quarters']} quarters paid");
            
            // If all 4 quarters are paid
            if ($status['total_quarters'] == 4 && $status['paid_quarters'] == 4) {
                error_log("ALL BUSINESS QUARTERS PAID! Generating tax clearance for year $year");
                
                // Check if clearance already exists
                $check_clearance_query = "
                    SELECT id FROM business_tax_clearances 
                    WHERE business_permit_id = :business_permit_id 
                    AND clearance_year = :year
                ";
                $clearance_stmt = $business_pdo->prepare($check_clearance_query);
                $clearance_stmt->execute([
                    ':business_permit_id' => $business_permit_id,
                    ':year' => $year
                ]);
                
                if ($clearance_stmt->rowCount() == 0) {
                    // Generate certificate number
                    $certificate_number = 'BUS-CLR-' . date('Y') . '-' . str_pad($business_permit_id, 6, '0', STR_PAD_LEFT) . '-' . $year;
                    
                    // Insert into business_tax_clearances table
                    $insert_query = "
                        INSERT INTO business_tax_clearances 
                        (certificate_number, business_permit_id, clearance_year, issue_date, generated_by)
                        VALUES (:cert_number, :business_permit_id, :year, CURDATE(), 1)
                    ";
                    
                    $insert_stmt = $business_pdo->prepare($insert_query);
                    $insert_stmt->execute([
                        ':cert_number' => $certificate_number,
                        ':business_permit_id' => $business_permit_id,
                        ':year' => $year
                    ]);
                    
                    $clearance_id = $business_pdo->lastInsertId();
                    
                    error_log("Business tax clearance generated! ID: $clearance_id, Certificate: $certificate_number");
                    
                    // Get business info for notification
                    $business_query = "
                        SELECT bp.business_name, bp.full_name, bp.personal_email, bp.business_permit_id
                        FROM business_permits bp
                        WHERE bp.id = :business_permit_id
                    ";
                    $business_stmt = $business_pdo->prepare($business_query);
                    $business_stmt->execute([':business_permit_id' => $business_permit_id]);
                    $business_info = $business_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($business_info) {
                        // Log notification
                        $notification_query = "
                            INSERT INTO notifications 
                            (user_email, subject, message, sent_at) 
                            VALUES (:email, :subject, :message, NOW())
                        ";
                        
                        $notification_stmt = $business_pdo->prepare($notification_query);
                        $notification_stmt->execute([
                            ':email' => $business_info['personal_email'],
                            ':subject' => 'Business Tax Clearance Certificate Generated - ' . $certificate_number,
                            ':message' => "Dear " . $business_info['full_name'] . ",\n\nYour business tax clearance certificate has been generated for " . $business_info['business_name'] . " (Permit: " . $business_info['business_permit_id'] . ") for year " . $year . ".\n\nCertificate Number: " . $certificate_number . "\n\nYou can download it from your dashboard.\n\nThank you!"
                        ]);
                        
                        error_log("Notification sent to: " . $business_info['personal_email']);
                    }
                    
                    $clearance_generated = true;
                } else {
                    error_log("Business tax clearance already exists for this permit and year");
                }
            } else {
                error_log("Not all business quarters are paid yet. No clearance generated.");
            }
        } catch (Exception $clearance_error) {
            // Don't fail the whole payment if clearance generation fails
            error_log("Business tax clearance generation error (non-critical): " . $clearance_error->getMessage());
        }
        // =============== END BUSINESS TAX CLEARANCE AUTOMATION ===============
        
        $business_pdo->commit();
        
        // Return success response
        $response = [
            'status' => 'success',
            'message' => 'Payment updated successfully in Business database',
            'rows_updated' => $rows_updated,
            'reference_id' => $reference_id,
            'quarterly_id' => $quarterly_id,
            'business_permit_id' => $quarterly_tax['permit_number'],
            'business_name' => $quarterly_tax['business_name'],
            'receipt_number' => $receipt_number,
            'payment_date' => $paid_at,
            'payment_id' => $payment_id,
            'new_status' => $updated_record['payment_status'],
            'type' => 'quarterly',
            'annual_cancelled' => isset($existing_annual) ? true : false
        ];
        
        // Add clearance info if generated
        if ($clearance_generated) {
            $response['business_tax_clearance'] = [
                'generated' => true,
                'certificate_number' => $certificate_number,
                'year' => $year,
                'message' => 'Tax clearance certificate has been generated for your business'
            ];
        }
        
        echo json_encode($response);
    }
    
} catch (PDOException $e) {
    // Database error
    if (isset($business_pdo)) {
        $business_pdo->rollBack();
    }
    
    error_log("Business PDO Exception: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Business database error: ' . $e->getMessage(),
        'reference_id' => $reference_id
    ]);
} catch (Exception $e) {
    // General error
    if (isset($business_pdo)) {
        $business_pdo->rollBack();
    }
    
    error_log("Business General Exception: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Business API error: ' . $e->getMessage(),
        'reference_id' => $reference_id
    ]);
}
?>