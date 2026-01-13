<?php
// revenue2/citizen_dashboard/digital/mark_abandoned.php
session_start();
require_once '../../db/Digital/digital_db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$payment_id = $input['payment_id'] ?? '';

if (!empty($payment_id)) {
    $pdo = getDigitalDB();
    if ($pdo) {
        try {
            $update_query = "
                UPDATE payment_transactions 
                SET payment_status = 'failed',
                    callback_response = CONCAT(COALESCE(callback_response, ''), ' | Payment abandoned - User left page')
                WHERE payment_id = :payment_id AND payment_status = 'pending'
            ";
            
            $stmt = $pdo->prepare($update_query);
            $stmt->execute([':payment_id' => $payment_id]);
            
            echo json_encode(['status' => 'success', 'message' => 'Marked as abandoned']);
        } catch (PDOException $e) {
            error_log("Failed to mark abandoned: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
?>