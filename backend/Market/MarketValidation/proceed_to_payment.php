<?php
// revenue2/backend/Market/MarketValidation/proceed_to_payment.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../../db/Market/market_db.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$app_id = intval($data['application_id'] ?? 0);

if ($app_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid application ID']);
    exit;
}

try {
    // Check current status
    $stmt = $pdo->prepare("SELECT id, application_status FROM rental_registration WHERE id = ?");
    $stmt->execute([$app_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        echo json_encode(['status' => 'error', 'message' => 'Application not found']);
        exit;
    }
    
    if ($existing['application_status'] !== 'interviewed') {
        echo json_encode(['status' => 'error', 'message' => 'Application is not in interviewed status']);
        exit;
    }
    
    // Update to paying
    $sql = "UPDATE rental_registration SET 
            application_status = 'paying',
            updated_at = NOW()
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $app_id]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Application marked as ready for payment',
        'data' => ['application_id' => $app_id, 'new_status' => 'paying']
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>