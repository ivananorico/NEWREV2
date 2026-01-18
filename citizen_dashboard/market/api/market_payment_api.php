<?php
// revenue2/citizen_dashboard/market/api/market_payment_api.php

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
$reference_id = $data['reference_id'] ?? null;  // This is the monthly_billing_id
$receipt_number = $data['receipt_number'] ?? null;
$paid_at = $data['paid_at'] ?? date('Y-m-d H:i:s');
$amount = $data['amount'] ?? null;
$purpose = $data['purpose'] ?? null;
$client_system = $data['client_system'] ?? null;
$payment_id = $data['payment_id'] ?? null;

// Log for debugging
error_log("=== MARKET RENT PAYMENT API CALLED ===");
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

// Verify this is for market rent system
if ($client_system !== 'market_rent') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'This API is for market rent system only. Received: ' . $client_system
    ]);
    exit();
}

// For market rent system, monthly_billing_id = reference_id
$monthly_billing_id = $reference_id;

// Include the market database connection
$market_db_path = __DIR__ . '/../../../db/Market/market_db.php';

if (!file_exists($market_db_path)) {
    error_log("Market database file NOT found at: " . $market_db_path);
    
    // Try alternative paths
    $alt_paths = [
        __DIR__ . '/../../../../db/Market/market_db.php',
        'C:/xampp/htdocs/revenue2/db/Market/market_db.php'
    ];
    
    foreach ($alt_paths as $path) {
        error_log("Checking alternative: " . $path);
        if (file_exists($path)) {
            $market_db_path = $path;
            error_log("Found market database file at: " . $path);
            break;
        }
    }
    
    if (!file_exists($market_db_path)) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Market database configuration not found'
        ]);
        exit();
    }
}

include_once $market_db_path;

try {
    // Get database connection from market_db.php
    global $pdo; // Use the global $pdo from market_db.php
    
    if (!$pdo) {
        throw new Exception('Failed to connect to Market database.');
    }
    
    // Test the connection
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // First, check if the monthly rent billing exists and get related data
    $check_query = "
        SELECT 
            mrb.id, 
            mrb.payment_status, 
            mrb.receipt_number,
            mrb.penalty_amount,
            mrb.base_rent,
            mrb.total_amount_due,
            mrb.billing_month,
            mrb.billing_year,
            mrb.rent_total_id,
            rt.registration_id,
            rs.id as rent_stall_id,
            rs.business_name,
            rs.stall_rights_no,
            s.name as stall_name,
            ro.renter_code,
            ro.first_name,
            ro.last_name,
            ro.email,
            ro.mobile
        FROM monthly_rent_billing mrb
        INNER JOIN rent_totals rt ON mrb.rent_total_id = rt.id
        INNER JOIN rent_stall rs ON rt.registration_id = rs.registration_id
        INNER JOIN stalls s ON rs.stall_id = s.id
        INNER JOIN renter_owner ro ON rs.renter_id = ro.id
        WHERE mrb.id = :monthly_billing_id
    ";
    
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([':monthly_billing_id' => $monthly_billing_id]);
    $monthly_billing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$monthly_billing) {
        error_log("Market monthly rent billing NOT found with ID: $monthly_billing_id");
        throw new Exception("Market monthly rent billing with ID $monthly_billing_id not found in database");
    }
    
    error_log("Found market monthly billing: ID={$monthly_billing['id']}, Status={$monthly_billing['payment_status']}, Year={$monthly_billing['billing_year']}");
    
    // Check if already paid
    if ($monthly_billing['payment_status'] == 'paid') {
        error_log("Market monthly billing $monthly_billing_id is already paid");
        echo json_encode([
            'status' => 'warning',
            'message' => 'This monthly market rent is already marked as paid',
            'reference_id' => $reference_id,
            'monthly_billing_id' => $monthly_billing_id,
            'current_status' => 'paid',
            'current_receipt' => $monthly_billing['receipt_number']
        ]);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Update monthly_rent_billing
        $update_query = "
            UPDATE monthly_rent_billing 
            SET payment_status = 'paid',
                payment_date = :paid_at,
                receipt_number = :receipt_number
            WHERE id = :monthly_billing_id
        ";
        
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([
            ':paid_at' => $paid_at,
            ':receipt_number' => $receipt_number,
            ':monthly_billing_id' => $monthly_billing_id
        ]);
        
        $rows_updated = $stmt->rowCount();
        
        if ($rows_updated === 0) {
            throw new Exception("No rows updated. Market monthly billing ID $monthly_billing_id may not exist or is already updated.");
        }
        
        // Update rent_stall status to active if it was in any other status
        $update_stall_query = "
            UPDATE rent_stall 
            SET status = 'active'
            WHERE registration_id = :registration_id 
            AND status != 'active'
        ";
        
        $stall_stmt = $pdo->prepare($update_stall_query);
        $stall_stmt->execute([':registration_id' => $monthly_billing['registration_id']]);
        
        // Log the payment transaction
        $log_query = "
            INSERT INTO market_payment_logs 
            (monthly_billing_id, receipt_number, amount_paid, payment_date, payment_method, renter_code, stall_rights_no, business_name, created_at)
            VALUES 
            (:monthly_billing_id, :receipt_number, :amount_paid, :payment_date, 'online', :renter_code, :stall_rights_no, :business_name, NOW())
        ";
        
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([
            ':monthly_billing_id' => $monthly_billing_id,
            ':receipt_number' => $receipt_number,
            ':amount_paid' => $monthly_billing['total_amount_due'],
            ':payment_date' => $paid_at,
            ':renter_code' => $monthly_billing['renter_code'],
            ':stall_rights_no' => $monthly_billing['stall_rights_no'],
            ':business_name' => $monthly_billing['business_name']
        ]);
        
        // =============== MARKET RENT CLEARANCE AUTOMATION ===============
        $clearance_generated = false;
        $certificate_number = null;
        $year = $monthly_billing['billing_year'];
        $rent_total_id = $monthly_billing['rent_total_id'];
        $rent_stall_id = $monthly_billing['rent_stall_id'];
        
        try {
            error_log("Checking market rent clearance eligibility for rent_total_id: $rent_total_id, year: $year");
            
            // Check if all months for this year are now paid
            $check_all_paid_query = "
                SELECT 
                    COUNT(*) as total_months,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_months
                FROM monthly_rent_billing 
                WHERE rent_total_id = :rent_total_id 
                AND billing_year = :year
            ";
            
            $check_stmt = $pdo->prepare($check_all_paid_query);
            $check_stmt->execute([
                ':rent_total_id' => $rent_total_id,
                ':year' => $year
            ]);
            $status = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Market rent status for year $year: {$status['paid_months']}/{$status['total_months']} months paid");
            
            // If all 12 months are paid
            if ($status['total_months'] == 12 && $status['paid_months'] == 12) {
                error_log("ALL MARKET RENT MONTHS PAID! Generating rent clearance for year $year");
                
                // First, create the clearance table if it doesn't exist
                createMarketClearanceTable($pdo);
                
                // Check if clearance already exists
                $check_clearance_query = "
                    SELECT id FROM market_rent_clearances 
                    WHERE rent_total_id = :rent_total_id 
                    AND clearance_year = :year
                ";
                $clearance_stmt = $pdo->prepare($check_clearance_query);
                $clearance_stmt->execute([
                    ':rent_total_id' => $rent_total_id,
                    ':year' => $year
                ]);
                
                if ($clearance_stmt->rowCount() == 0) {
                    // Generate certificate number
                    $certificate_number = 'MRC-' . date('Y') . '-' . str_pad($rent_total_id, 6, '0', STR_PAD_LEFT) . '-' . $year;
                    
                    // Insert into market_rent_clearances table
                    $insert_query = "
                        INSERT INTO market_rent_clearances 
                        (certificate_number, rent_stall_id, rent_total_id, clearance_year, issue_date, generated_by)
                        VALUES (:cert_number, :rent_stall_id, :rent_total_id, :year, CURDATE(), 1)
                    ";
                    
                    $insert_stmt = $pdo->prepare($insert_query);
                    $insert_stmt->execute([
                        ':cert_number' => $certificate_number,
                        ':rent_stall_id' => $rent_stall_id,
                        ':rent_total_id' => $rent_total_id,
                        ':year' => $year
                    ]);
                    
                    $clearance_id = $pdo->lastInsertId();
                    
                    error_log("Market rent clearance generated! ID: $clearance_id, Certificate: $certificate_number");
                    
                    // Get renter info for notification
                    $renter_query = "
                        SELECT ro.email, ro.first_name, ro.last_name, rs.business_name, rs.stall_rights_no
                        FROM renter_owner ro
                        INNER JOIN rent_stall rs ON ro.id = rs.renter_id
                        WHERE rs.id = :rent_stall_id
                    ";
                    $renter_stmt = $pdo->prepare($renter_query);
                    $renter_stmt->execute([':rent_stall_id' => $rent_stall_id]);
                    $renter_info = $renter_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($renter_info) {
                        // Log notification
                        $notification_query = "
                            INSERT INTO notifications 
                            (user_email, subject, message, sent_at) 
                            VALUES (:email, :subject, :message, NOW())
                        ";
                        
                        $notification_stmt = $pdo->prepare($notification_query);
                        $notification_stmt->execute([
                            ':email' => $renter_info['email'],
                            ':subject' => 'Market Rent Clearance Certificate Generated - ' . $certificate_number,
                            ':message' => "Dear " . $renter_info['first_name'] . " " . $renter_info['last_name'] . ",\n\nYour market rent clearance certificate has been generated for business " . $renter_info['business_name'] . " (Stall: " . $renter_info['stall_rights_no'] . ") for year " . $year . ".\n\nCertificate Number: " . $certificate_number . "\n\nYou can download it from your dashboard.\n\nThank you!"
                        ]);
                        
                        error_log("Notification sent to: " . $renter_info['email']);
                    }
                    
                    $clearance_generated = true;
                } else {
                    error_log("Market rent clearance already exists for this stall and year");
                    $clearance_generated = false;
                }
            } else {
                error_log("Not all market rent months are paid yet. No clearance generated.");
                $clearance_generated = false;
            }
        } catch (Exception $clearance_error) {
            // Don't fail the whole payment if clearance generation fails
            error_log("Market rent clearance generation error (non-critical): " . $clearance_error->getMessage());
            $clearance_generated = false;
        }
        // =============== END MARKET RENT CLEARANCE AUTOMATION ===============
        
        // Commit transaction
        $pdo->commit();
        
        // Verify the update
        $verify_stmt = $pdo->prepare("
            SELECT id, payment_status, receipt_number, payment_date 
            FROM monthly_rent_billing 
            WHERE id = :monthly_billing_id
        ");
        $verify_stmt->execute([':monthly_billing_id' => $monthly_billing_id]);
        $updated_record = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$updated_record) {
            throw new Exception("Failed to verify update. Record not found after update.");
        }
        
        if ($updated_record['payment_status'] !== 'paid') {
            throw new Exception("Update verification failed. Status is still: " . $updated_record['payment_status']);
        }
        
        // Prepare response data
        $month_names = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        
        $month_name = $month_names[$monthly_billing['billing_month']] ?? 'Unknown';
        $month_year = $month_name . ' ' . $monthly_billing['billing_year'];
        
        error_log("Successfully updated market rent $reference_id. New status: paid, Receipt: $receipt_number, Month: $month_year");
        
        // Return success response
        $response = [
            'status' => 'success',
            'message' => 'Market rent payment updated successfully',
            'rows_updated' => $rows_updated,
            'reference_id' => $reference_id,
            'monthly_billing_id' => $monthly_billing_id,
            'stall_name' => $monthly_billing['stall_name'],
            'stall_rights_no' => $monthly_billing['stall_rights_no'],
            'renter_code' => $monthly_billing['renter_code'],
            'business_name' => $monthly_billing['business_name'],
            'month_year' => $month_year,
            'receipt_number' => $receipt_number,
            'amount_paid' => $monthly_billing['total_amount_due'],
            'base_rent' => $monthly_billing['base_rent'],
            'penalty_amount' => $monthly_billing['penalty_amount'],
            'payment_date' => $paid_at,
            'payment_id' => $payment_id,
            'new_status' => $updated_record['payment_status']
        ];
        
        // Add clearance info if generated
        if ($clearance_generated) {
            $response['market_rent_clearance'] = [
                'generated' => true,
                'certificate_number' => $certificate_number,
                'year' => $year,
                'message' => 'Rent clearance certificate has been generated for your stall'
            ];
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    // Database error
    error_log("Market PDO Exception: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Market database error: ' . $e->getMessage(),
        'reference_id' => $reference_id
    ]);
} catch (Exception $e) {
    // General error
    error_log("Market General Exception: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Market API error: ' . $e->getMessage(),
        'reference_id' => $reference_id
    ]);
}

// Function to create market clearance table if it doesn't exist
function createMarketClearanceTable($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS market_rent_clearances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            certificate_number VARCHAR(50) NOT NULL UNIQUE,
            rent_stall_id INT NOT NULL,
            rent_total_id INT NOT NULL,
            clearance_year YEAR(4) NOT NULL,
            issue_date DATE NOT NULL,
            generated_by INT NOT NULL COMMENT 'user_id',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (rent_stall_id) REFERENCES rent_stall(id),
            FOREIGN KEY (rent_total_id) REFERENCES rent_totals(id),
            INDEX idx_clearance_year (clearance_year),
            INDEX idx_issue_date (issue_date)
        )";
        
        $pdo->exec($sql);
        error_log("Market rent clearances table created or already exists");
    } catch (PDOException $e) {
        error_log("Error creating market_rent_clearances table: " . $e->getMessage());
    }
}

// Function to create payment logs table if it doesn't exist
function createPaymentLogsTable($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS market_payment_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            monthly_billing_id INT NOT NULL,
            receipt_number VARCHAR(100) NOT NULL,
            amount_paid DECIMAL(10,2) NOT NULL,
            payment_date DATETIME NOT NULL,
            payment_method VARCHAR(50) DEFAULT 'online',
            renter_code VARCHAR(50) NOT NULL,
            stall_rights_no VARCHAR(50) NOT NULL,
            business_name VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_receipt (receipt_number),
            INDEX idx_renter (renter_code),
            INDEX idx_stall (stall_rights_no),
            INDEX idx_date (payment_date)
        )";
        
        $pdo->exec($sql);
        error_log("Market payment logs table created or already exists");
    } catch (PDOException $e) {
        error_log("Error creating market_payment_logs table: " . $e->getMessage());
    }
}

// Call the functions to ensure tables exist
if (isset($pdo) && $pdo) {
    createPaymentLogsTable($pdo);
    createMarketClearanceTable($pdo);
}
?>