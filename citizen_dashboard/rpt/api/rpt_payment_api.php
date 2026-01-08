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

// Extract data
$quarterly_id = $data['quarterly_id'] ?? null;
$amount = $data['amount'] ?? null;
$purpose = $data['purpose'] ?? null;
$receipt_number = $data['receipt_number'] ?? null;
$paid_at = $data['paid_at'] ?? date('Y-m-d H:i:s');

// Log for debugging
error_log("RPT API received: quarterly_id=$quarterly_id, receipt=$receipt_number");

// Validate required fields
if (!$quarterly_id || !$receipt_number) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Missing required fields: quarterly_id and receipt_number are required',
        'received_data' => $data
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
    
    // First, check if the quarterly tax exists
    $check_query = "SELECT id, payment_status, receipt_number FROM quarterly_taxes WHERE id = :quarterly_id";
    $check_stmt = $rpt_pdo->prepare($check_query);
    $check_stmt->execute([':quarterly_id' => $quarterly_id]);
    $quarterly_tax = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quarterly_tax) {
        throw new Exception("Quarterly tax with ID $quarterly_id not found in database");
    }
    
    error_log("Found quarterly tax: ID={$quarterly_tax['id']}, Status={$quarterly_tax['payment_status']}");
    
    // Check if already paid
    if ($quarterly_tax['payment_status'] == 'paid') {
        error_log("Quarterly tax $quarterly_id is already paid");
        echo json_encode([
            'status' => 'warning',
            'message' => 'This quarterly tax is already marked as paid',
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
        throw new Exception("No rows updated. Quarterly tax ID $quarterly_id may not exist or is already updated.");
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
    
    error_log("Successfully updated quarterly tax $quarterly_id. New status: paid, Receipt: $receipt_number");
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Payment updated successfully in RPT database',
        'rows_updated' => $rows_updated,
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
        'quarterly_id' => $quarterly_id
    ]);
} catch (Exception $e) {
    // General error
    error_log("General Exception: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'API error: ' . $e->getMessage(),
        'quarterly_id' => $quarterly_id
    ]);
}
?>