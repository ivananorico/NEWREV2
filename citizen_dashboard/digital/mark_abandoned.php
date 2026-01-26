<?php
// revenue2/citizen_dashboard/digital/mark_abandoned.php

// IMPORTANT: NO session_start() here - this is an API endpoint

// Get JSON input
$input = file_get_contents('php://input');
error_log("MARK_ABANDONED - Raw input: " . $input);

$data = json_decode($input, true);
$payment_id = $data['payment_id'] ?? '';

error_log("MARK_ABANDONED - Payment ID: " . $payment_id);

if (empty($payment_id)) {
    error_log("MARK_ABANDONED - ERROR: No payment ID provided");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payment ID required']);
    exit();
}

// Connect to database
$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/db/Digital/digital_db.php';

$pdo = getDigitalDB();
if (!$pdo) {
    error_log("MARK_ABANDONED - ERROR: Database connection failed");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

try {
    // Check if payment is still pending
    $check_query = "SELECT payment_status, created_at FROM payment_transactions WHERE payment_id = :payment_id";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([':payment_id' => $payment_id]);
    $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        error_log("MARK_ABANDONED - ERROR: Payment not found: " . $payment_id);
        echo json_encode(['status' => 'error', 'message' => 'Payment not found']);
        exit();
    }
    
    error_log("MARK_ABANDONED - Current status: " . $result['payment_status']);
    
    // Only mark as abandoned if still pending
    if ($result['payment_status'] === 'pending') {
        $update_query = "
            UPDATE payment_transactions 
            SET payment_status = 'failed',
                callback_response = CONCAT(
                    COALESCE(callback_response, ''), 
                    ' | Payment abandoned at " . date('Y-m-d H:i:s') . "'
                )
            WHERE payment_id = :payment_id
        ";
        
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([':payment_id' => $payment_id]);
        
        $rows_updated = $stmt->rowCount();
        error_log("MARK_ABANDONED - SUCCESS: Marked as abandoned. Rows updated: " . $rows_updated);
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Marked as abandoned',
            'payment_id' => $payment_id,
            'rows_updated' => $rows_updated
        ]);
    } else {
        error_log("MARK_ABANDONED - INFO: Payment already " . $result['payment_status']);
        echo json_encode([
            'status' => 'info', 
            'message' => 'Payment already ' . $result['payment_status'],
            'current_status' => $result['payment_status']
        ]);
    }
    
} catch (PDOException $e) {
    error_log("MARK_ABANDONED - PDO Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("MARK_ABANDONED - General Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>