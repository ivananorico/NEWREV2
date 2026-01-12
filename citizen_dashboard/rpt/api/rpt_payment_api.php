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
// The universal system sends 'reference_id' not 'quarterly_id'
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
    // In RPT system, reference_id IS the quarterly_id
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
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Payment updated successfully in RPT database',
        'rows_updated' => $rows_updated,
        'reference_id' => $reference_id,
        'quarterly_id' => $quarterly_id,
        'receipt_number' => $receipt_number,
        'payment_date' => $paid_at,
        'new_status' => $updated_record['payment_status']
    ]);
    
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