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
$reference_id = $data['reference_id'] ?? null;  // This is the quarterly_id
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

// For business system, quarterly_id = reference_id
$quarterly_id = $reference_id;

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
    
    // First, check if the quarterly tax exists
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
                $clearance_generated = false;
            }
        } else {
            error_log("Not all business quarters are paid yet. No clearance generated.");
            $clearance_generated = false;
        }
    } catch (Exception $clearance_error) {
        // Don't fail the whole payment if clearance generation fails
        error_log("Business tax clearance generation error (non-critical): " . $clearance_error->getMessage());
        $clearance_generated = false;
    }
    // =============== END BUSINESS TAX CLEARANCE AUTOMATION ===============
    
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
        'new_status' => $updated_record['payment_status']
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
    
} catch (PDOException $e) {
    // Database error
    error_log("Business PDO Exception: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Business database error: ' . $e->getMessage(),
        'reference_id' => $reference_id
    ]);
} catch (Exception $e) {
    // General error
    error_log("Business General Exception: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Business API error: ' . $e->getMessage(),
        'reference_id' => $reference_id
    ]);
}
?>