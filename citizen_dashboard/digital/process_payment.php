<?php
// process_payment.php
header('Content-Type: application/json');

// Include database connection
include_once '../../db/Digital/digital_db.php';

// Get the input data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['payment_id']) || !isset($data['otp_code'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input data']);
    exit;
}

try {
    // Verify OTP and get payment details
    $stmt = $pdo->prepare("
        SELECT * FROM payment_transactions 
        WHERE payment_id = :payment_id AND otp_code = :otp_code AND payment_status = 'pending'
    ");
    $stmt->execute([
        ':payment_id' => $data['payment_id'],
        ':otp_code' => $data['otp_code']
    ]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        throw new Exception("Invalid OTP or payment not found");
    }
    
    // Generate receipt number
    $receipt_number = 'RCPT-' . date('YmdHis') . '-' . rand(1000, 9999);
    
    // Update payment status - NO updated_at column
    $update_stmt = $pdo->prepare("
        UPDATE payment_transactions 
        SET payment_status = 'paid', 
            otp_verified = 1,
            receipt_number = :receipt_number,
            paid_at = NOW()
        WHERE payment_id = :payment_id
    ");
    $update_stmt->execute([
        ':receipt_number' => $receipt_number,
        ':payment_id' => $data['payment_id']
    ]);
    
    // Prepare webhook data
    $webhook_data = [
        'payment_id' => $payment['payment_id'],
        'client_system' => $payment['client_system'],
        'client_reference' => $payment['client_reference'],
        'purpose' => $payment['purpose'],
        'amount' => $payment['amount'],
        'payment_method' => $payment['payment_method'],
        'receipt_number' => $receipt_number,
        'paid_at' => date('Y-m-d H:i:s'),
        'status' => 'paid',
        'phone' => $payment['phone']
    ];
    
    error_log("Sending webhook to: " . $payment['webhook_url']);
    error_log("Webhook data: " . json_encode($webhook_data));
    
    // Send webhook to RPT system
    $ch = curl_init($payment['webhook_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $webhook_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Log the webhook response
    error_log("Webhook response (HTTP $http_code): " . $webhook_response);
    
    // Also log in webhook_logs table
    $log_stmt = $pdo->prepare("
        INSERT INTO webhook_logs (transaction_id, webhook_url, payload, response, status_code, created_at)
        VALUES (:transaction_id, :webhook_url, :payload, :response, :status_code, NOW())
    ");
    $log_stmt->execute([
        ':transaction_id' => $payment['id'],
        ':webhook_url' => $payment['webhook_url'],
        ':payload' => json_encode($webhook_data),
        ':response' => $webhook_response,
        ':status_code' => $http_code
    ]);
    
    curl_close($ch);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Payment processed successfully',
        'receipt_number' => $receipt_number,
        'webhook_sent' => true,
        'webhook_response' => json_decode($webhook_response, true),
        'http_code' => $http_code
    ]);
    
} catch (Exception $e) {
    error_log("Process payment error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Payment failed: ' . $e->getMessage()
    ]);
}
?>