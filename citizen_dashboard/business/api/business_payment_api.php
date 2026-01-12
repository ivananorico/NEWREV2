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
        error_log("Business quarterly tax NOT found with ID: $quarterly_id");
        throw new Exception("Business quarterly tax with ID $quarterly_id not found in database");
    }
    
    error_log("Found business quarterly tax: ID={$quarterly_tax['id']}, Status={$quarterly_tax['payment_status']}");
    
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
    
    // Return success response
    echo json_encode([
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
    ]);
    
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