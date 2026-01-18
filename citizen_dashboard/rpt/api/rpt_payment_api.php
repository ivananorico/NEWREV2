<?php
// revenue2/citizen_dashboard/rpt/api/rpt_payment_api.php

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

// Extract data - UPDATED FOR UNIVERSAL SYSTEM
$reference_id = $data['reference_id'] ?? null;
$receipt_number = $data['receipt_number'] ?? null;
$amount = $data['amount'] ?? null;
$purpose = $data['purpose'] ?? null;
$paid_at = $data['paid_at'] ?? date('Y-m-d H:i:s');
$client_system = $data['client_system'] ?? null;
$payment_id = $data['payment_id'] ?? null;
$phone = $data['phone'] ?? null;

// Log for debugging - UPDATED
error_log("RPT API received callback from Universal System:");
error_log("Reference ID: $reference_id");
error_log("Receipt: $receipt_number");
error_log("Client System: $client_system");
error_log("Payment ID: $payment_id");
error_log("Full data: " . print_r($data, true));

// Validate required fields - UPDATED
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
error_log("Looking for database file at: " . $rpt_db_path);

if (!file_exists($rpt_db_path)) {
    error_log("Database file NOT found at: " . $rpt_db_path);
    
    // Try alternative paths
    $alt_paths = [
        __DIR__ . '/../../../../db/RPT/rpt_db.php',
        'C:/xampp/htdocs/revenue2/db/RPT/rpt_db.php',
        '../../../db/RPT/rpt_db.php'
    ];
    
    foreach ($alt_paths as $path) {
        error_log("Checking alternative: " . $path);
        if (file_exists($path)) {
            $rpt_db_path = $path;
            error_log("Found database file at: " . $path);
            break;
        }
    }
    
    if (!file_exists($rpt_db_path)) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Database configuration not found',
            'searched_paths' => [
                'primary' => __DIR__ . '/../../../db/RPT/rpt_db.php',
                'alternatives' => $alt_paths
            ]
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
        throw new Exception('Failed to connect to RPT database. getDatabaseConnection() returned false.');
    }
    
    // Test the connection
    $rpt_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $rpt_pdo->query('SELECT 1')->fetch();
    
    error_log("Database connection successful");
    
    // First, check if the quarterly tax exists using reference_id
    $quarterly_id = $reference_id;
    
    $check_query = "SELECT id, payment_status, receipt_number FROM quarterly_taxes WHERE id = :quarterly_id";
    $check_stmt = $rpt_pdo->prepare($check_query);
    $check_stmt->execute([':quarterly_id' => $quarterly_id]);
    $quarterly_tax = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quarterly_tax) {
        error_log("Quarterly tax with ID $quarterly_id not found. Checking if it's an annual payment...");
        
        // Check if it's an annual payment (starts with ANNUAL-)
        if (strpos($reference_id, 'ANNUAL-') === 0) {
            // Handle annual payment
            $check_annual_query = "SELECT id FROM annual_payments WHERE reference_number = :reference_id";
            $check_annual_stmt = $rpt_pdo->prepare($check_annual_query);
            $check_annual_stmt->execute([':reference_id' => $reference_id]);
            $annual_payment = $check_annual_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($annual_payment) {
                // Update annual payment
                $update_annual_query = "
                    UPDATE annual_payments 
                    SET payment_status = 'paid',
                        payment_date = :paid_at,
                        receipt_number = :receipt_number,
                        transaction_id = :payment_id
                    WHERE reference_number = :reference_id
                ";
                
                $annual_stmt = $rpt_pdo->prepare($update_annual_query);
                $annual_stmt->execute([
                    ':paid_at' => $paid_at,
                    ':receipt_number' => $receipt_number,
                    ':payment_id' => $payment_id,
                    ':reference_id' => $reference_id
                ]);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Annual payment updated successfully',
                    'reference_id' => $reference_id,
                    'receipt_number' => $receipt_number,
                    'type' => 'annual'
                ]);
                exit();
            }
        }
        
        throw new Exception("Payment reference not found: $reference_id");
    }
    
    error_log("Found quarterly tax: ID={$quarterly_tax['id']}, Status={$quarterly_tax['payment_status']}");
    
    // Check if already paid
    if ($quarterly_tax['payment_status'] == 'paid') {
        error_log("Quarterly tax $quarterly_id is already paid");
        echo json_encode([
            'status' => 'warning',
            'message' => 'This tax is already marked as paid',
            'reference_id' => $reference_id,
            'quarterly_id' => $quarterly_id,
            'current_status' => 'paid',
            'current_receipt' => $quarterly_tax['receipt_number']
        ]);
        exit();
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
        throw new Exception("No rows updated. Reference ID $reference_id may not exist or is already updated.");
    }
    
    // Verify the update
    $verify_stmt = $rpt_pdo->prepare("SELECT id, payment_status, receipt_number FROM quarterly_taxes WHERE id = :quarterly_id");
    $verify_stmt->execute([':quarterly_id' => $quarterly_id]);
    $updated_record = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$updated_record) {
        throw new Exception("Failed to verify update. Record not found after update.");
    }
    
    if ($updated_record['payment_status'] !== 'paid') {
        throw new Exception("Update verification failed. Status is still: " . $updated_record['payment_status']);
    }
    
    error_log("Successfully updated tax $reference_id. New status: paid, Receipt: $receipt_number");
    
    // =============== TAX CLEARANCE AUTOMATION ===============
    try {
        // Get property info for this quarterly tax
        $property_query = "
            SELECT qt.property_total_id, qt.year 
            FROM quarterly_taxes qt 
            WHERE qt.id = :quarterly_id
        ";
        $property_stmt = $rpt_pdo->prepare($property_query);
        $property_stmt->execute([':quarterly_id' => $quarterly_id]);
        $property_info = $property_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($property_info) {
            $property_total_id = $property_info['property_total_id'];
            $year = $property_info['year'];
            
            error_log("Checking tax clearance eligibility for property_total_id: $property_total_id, year: $year");
            
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
                ':property_total_id' => $property_total_id,
                ':year' => $year
            ]);
            $status = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Tax status for year $year: {$status['paid_quarters']}/{$status['total_quarters']} quarters paid");
            
            // If all 4 quarters are paid
            if ($status['total_quarters'] == 4 && $status['paid_quarters'] == 4) {
                error_log("ALL QUARTERS PAID! Generating tax clearance for year $year");
                
                // Check if clearance already exists
                $check_clearance_query = "
                    SELECT id FROM tax_clearances 
                    WHERE property_total_id = :property_total_id 
                    AND clearance_year = :year
                ";
                $clearance_stmt = $rpt_pdo->prepare($check_clearance_query);
                $clearance_stmt->execute([
                    ':property_total_id' => $property_total_id,
                    ':year' => $year
                ]);
                
                if ($clearance_stmt->rowCount() == 0) {
                    // Generate certificate number
                    $certificate_number = 'RPT-CLR-' . date('Y') . '-' . str_pad($property_total_id, 6, '0', STR_PAD_LEFT) . '-' . $year;
                    
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
                        ':year' => $year
                    ]);
                    
                    $clearance_id = $rpt_pdo->lastInsertId();
                    
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
                    
                    // Get user info for notification
                    $user_query = "
                        SELECT po.email, po.first_name, po.last_name, pr.reference_number
                        FROM property_totals pt
                        JOIN property_registrations pr ON pt.registration_id = pr.id
                        JOIN property_owners po ON pr.owner_id = po.id
                        WHERE pt.id = :property_total_id
                    ";
                    $user_stmt = $rpt_pdo->prepare($user_query);
                    $user_stmt->execute([':property_total_id' => $property_total_id]);
                    $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user_info) {
                        // Log notification
                        $notification_query = "
                            INSERT INTO notifications 
                            (user_email, subject, message, sent_at) 
                            VALUES (:email, :subject, :message, NOW())
                        ";
                        
                        $notification_stmt = $rpt_pdo->prepare($notification_query);
                        $notification_stmt->execute([
                            ':email' => $user_info['email'],
                            ':subject' => 'Tax Clearance Certificate Generated - ' . $certificate_number,
                            ':message' => "Dear " . $user_info['first_name'] . " " . $user_info['last_name'] . ",\n\nYour tax clearance certificate has been generated for property " . $user_info['reference_number'] . " for year " . $year . ".\n\nCertificate Number: " . $certificate_number . "\n\nYou can download it from your dashboard.\n\nThank you!"
                        ]);
                        
                        error_log("Notification sent to: " . $user_info['email']);
                    }
                    
                    $clearance_generated = true;
                } else {
                    error_log("Tax clearance already exists for this property and year");
                    $clearance_generated = false;
                }
            } else {
                error_log("Not all quarters are paid yet. No clearance generated.");
                $clearance_generated = false;
            }
        } else {
            error_log("Could not find property info for quarterly tax ID: $quarterly_id");
            $clearance_generated = false;
        }
    } catch (Exception $clearance_error) {
        // Don't fail the whole payment if clearance generation fails
        error_log("Tax clearance generation error (non-critical): " . $clearance_error->getMessage());
        $clearance_generated = false;
    }
    // =============== END TAX CLEARANCE AUTOMATION ===============
    
    // Return success response
    $response = [
        'status' => 'success',
        'message' => 'Payment updated successfully in RPT database',
        'rows_updated' => $rows_updated,
        'reference_id' => $reference_id,
        'quarterly_id' => $quarterly_id,
        'receipt_number' => $receipt_number,
        'payment_date' => $paid_at,
        'new_status' => $updated_record['payment_status']
    ];
    
    // Add clearance info if generated
    if ($clearance_generated ?? false) {
        $response['tax_clearance'] = [
            'generated' => true,
            'certificate_number' => $certificate_number ?? null,
            'year' => $year ?? null
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Database error
    error_log("PDO Exception: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'reference_id' => $reference_id
    ]);
} catch (Exception $e) {
    // General error
    error_log("General Exception: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'API error: ' . $e->getMessage(),
        'reference_id' => $reference_id
    ]);
}
?>