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

// Log the incoming request
error_log("=== BUSINESS PAYMENT API CALLED ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);

// Get POST data
$input = file_get_contents('php://input');
error_log("Raw Input: " . $input);

$data = json_decode($input, true);

// If JSON input is empty, try form data
if (empty($data)) {
    $data = $_POST;
    error_log("Using POST data");
}

// Debug: Log all received data
error_log("Received Data: " . print_r($data, true));

// Extract data - UNIVERSAL FIELDS (from gcash_otp.php)
$reference_id = $data['reference_id'] ?? $data['client_reference'] ?? null; // This should be the quarterly_id
$receipt_number = $data['receipt_number'] ?? null;
$paid_at = $data['paid_at'] ?? date('Y-m-d H:i:s');
$amount = $data['amount'] ?? null;
$payment_id = $data['payment_id'] ?? null;
$client_system = $data['client_system'] ?? null;

// For business system, quarterly_id is usually in reference_id
$quarterly_id = $data['quarterly_id'] ?? $reference_id;

// Extract from system_data if present
$system_data = $data['system_data'] ?? [];
if (is_string($system_data)) {
    $system_data = json_decode($system_data, true) ?? [];
}

// Check for quarterly_id in system_data
if (!$quarterly_id && isset($system_data['quarterly_id'])) {
    $quarterly_id = $system_data['quarterly_id'];
}

// Log extracted data
error_log("Extracted Data: quarterly_id=$quarterly_id, receipt=$receipt_number, reference=$reference_id, system=$client_system");

// Validate required fields
if (!$quarterly_id || !$receipt_number) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Missing required fields: quarterly_id and receipt_number are required',
        'received_data' => $data,
        'extracted_quarterly_id' => $quarterly_id,
        'extracted_receipt' => $receipt_number
    ]);
    exit();
}

// Include the business database connection
$business_db_path = __DIR__ . '/../../../db/Business/business_db.php';
error_log("Looking for database file at: " . $business_db_path);

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
    $business_pdo->query('SELECT 1')->fetch();
    
    error_log("Business database connection successful");
    
    // First, check if the quarterly tax exists
    $check_query = "
        SELECT 
            bqt.id, 
            bqt.payment_status, 
            bqt.receipt_number,
            bqt.total_quarterly_tax,
            bqt.penalty_amount,
            bqt.business_permit_id,
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
        error_log("Quarterly tax NOT found with ID: $quarterly_id");
        throw new Exception("Business quarterly tax with ID $quarterly_id not found in database");
    }
    
    error_log("Found business quarterly tax: ID={$quarterly_tax['id']}, Status={$quarterly_tax['payment_status']}, Business={$quarterly_tax['business_name']}");
    
    // Check if already paid
    if ($quarterly_tax['payment_status'] == 'paid') {
        error_log("Business quarterly tax $quarterly_id is already paid");
        echo json_encode([
            'status' => 'warning',
            'message' => 'This quarterly business tax is already marked as paid',
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
            receipt_number = :receipt_number,
            penalty_amount = :penalty_amount
        WHERE id = :quarterly_id
    ";
    
    $stmt = $business_pdo->prepare($update_query);
    $stmt->execute([
        ':paid_at' => $paid_at,
        ':receipt_number' => $receipt_number,
        ':penalty_amount' => $quarterly_tax['penalty_amount'] ?? 0,
        ':quarterly_id' => $quarterly_id
    ]);
    
    $rows_updated = $stmt->rowCount();
    
    if ($rows_updated === 0) {
        throw new Exception("No rows updated. Business quarterly tax ID $quarterly_id may not exist or is already updated.");
    }
    
    // Log payment in business_penalty_log if there was penalty
    $penalty_amount = $quarterly_tax['penalty_amount'] ?? 0;
    if ($penalty_amount > 0) {
        try {
            $penalty_log_query = "
                INSERT INTO business_penalty_log 
                (calculated_date, updated_records, total_penalty, penalty_percent_used, created_at)
                VALUES 
                (CURDATE(), 1, :total_penalty, :penalty_percent, NOW())
            ";
            
            $penalty_stmt = $business_pdo->prepare($penalty_log_query);
            $penalty_stmt->execute([
                ':total_penalty' => $penalty_amount,
                ':penalty_percent' => 1.00 // Default business penalty rate
            ]);
            
            error_log("Business penalty logged for tax ID: $quarterly_id, Amount: $penalty_amount");
        } catch (Exception $e) {
            error_log("Failed to log business penalty: " . $e->getMessage());
            // Continue even if penalty logging fails
        }
    }
    
    // Verify the update
    $verify_query = "
        SELECT 
            id, 
            payment_status, 
            receipt_number,
            payment_date,
            penalty_amount
        FROM business_quarterly_taxes 
        WHERE id = :quarterly_id
    ";
    
    $verify_stmt = $business_pdo->prepare($verify_query);
    $verify_stmt->execute([':quarterly_id' => $quarterly_id]);
    $updated_record = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$updated_record) {
        throw new Exception("Failed to verify update. Record not found after update.");
    }
    
    if ($updated_record['payment_status'] !== 'paid') {
        throw new Exception("Update verification failed. Status is still: " . $updated_record['payment_status']);
    }
    
    error_log("Successfully updated business quarterly tax $quarterly_id. New status: paid, Receipt: $receipt_number");
    
    // Return success response
    $response = [
        'status' => 'success',
        'message' => 'Payment updated successfully in Business database',
        'rows_updated' => $rows_updated,
        'quarterly_id' => $quarterly_id,
        'business_permit_id' => $quarterly_tax['permit_number'],
        'business_name' => $quarterly_tax['business_name'],
        'receipt_number' => $receipt_number,
        'payment_date' => $paid_at,
        'payment_id' => $payment_id,
        'amount' => $amount,
        'new_status' => $updated_record['payment_status'],
        'penalty_paid' => $penalty_amount
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Database error
    error_log("Business PDO Exception: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Business database error: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'quarterly_id' => $quarterly_id,
        'trace' => $e->getTraceAsString()
    ]);
} catch (Exception $e) {
    // General error
    error_log("Business General Exception: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Business API error: ' . $e->getMessage(),
        'quarterly_id' => $quarterly_id
    ]);
}

// Log the response
error_log("=== BUSINESS API RESPONSE SENT ===");
error_log(json_encode([
    'status' => 'success',
    'quarterly_id' => $quarterly_id ?? 'unknown',
    'timestamp' => date('Y-m-d H:i:s')
]));
?>