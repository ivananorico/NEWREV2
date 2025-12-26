<?php
// generate_otp.php
header('Content-Type: application/json');

// Fix database path
include_once '../../db/Digital/digital_db.php';

// Get the input data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log for debugging
error_log("Generate OTP received: " . print_r($data, true));

if (!$data) {
    error_log("Generate OTP: Invalid JSON input");
    echo json_encode(['status' => 'error', 'message' => 'Invalid input data']);
    exit;
}

// Validate required fields
$required_fields = ['client_system', 'client_reference', 'purpose', 'amount', 'payment_method', 'phone'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        error_log("Generate OTP: Missing field - $field");
        echo json_encode(['status' => 'error', 'message' => "Missing required field: $field"]);
        exit;
    }
}

try {
    // Generate unique payment ID
    $payment_id = 'PAY-' . date('YmdHis') . '-' . rand(1000, 9999);
    
    // Generate OTP (6 digits)
    $otp_code = sprintf('%06d', rand(0, 999999));
    
    // Set webhook URL based on client system - FIXED URL
    $webhook_url = getWebhookUrl($data['client_system']);
    
    if (!$webhook_url) {
        throw new Exception("No webhook URL configured for system: " . $data['client_system']);
    }
    
    error_log("Using webhook URL: " . $webhook_url);
    
    // Insert into payment_transactions - NO updated_at column
    $stmt = $pdo->prepare("
        INSERT INTO payment_transactions (
            payment_id, client_system, client_reference, purpose, amount, 
            phone, payment_method, webhook_url, otp_code, payment_status, created_at
        ) VALUES (
            :payment_id, :client_system, :client_reference, :purpose, :amount,
            :phone, :payment_method, :webhook_url, :otp_code, 'pending', NOW()
        )
    ");
    
    $result = $stmt->execute([
        ':payment_id' => $payment_id,
        ':client_system' => $data['client_system'],
        ':client_reference' => $data['client_reference'],
        ':purpose' => $data['purpose'],
        ':amount' => $data['amount'],
        ':phone' => $data['phone'],
        ':payment_method' => $data['payment_method'],
        ':webhook_url' => $webhook_url,
        ':otp_code' => $otp_code
    ]);
    
    if (!$result) {
        throw new Exception("Failed to insert payment transaction");
    }
    
    $inserted_id = $pdo->lastInsertId();
    error_log("Generate OTP: Payment created - ID: $inserted_id, Payment ID: $payment_id");
    
    // Log OTP (in production, send via SMS)
    error_log("OTP for payment $payment_id: $otp_code - Sent to: " . $data['phone']);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'OTP sent successfully',
        'payment_id' => $payment_id,
        'otp_code' => $otp_code, // Remove this in production
        'webhook_url' => $webhook_url
    ]);
    
} catch (PDOException $e) {
    error_log("Generate OTP PDO Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Generate OTP Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function getWebhookUrl($clientSystem) {
    // FIXED: Use correct base URL for your setup
    $base_url = 'http://localhost'; // Or your actual domain
    
    $webhooks = [
        'rpt' => $base_url . '/revenue2/citizen_dashboard/rpt/rpt_tax_payment/rpt_webhook.php',
        // Add other systems if needed
    ];
    
    return $webhooks[$clientSystem] ?? null;
}
?>