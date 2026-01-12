<?php
// revenue2/backend/Market/MarketValidation/mark_interview_completed.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database connection
require_once '../../../db/Market/market_db.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON data',
        'json_error' => json_last_error_msg()
    ]);
    exit;
}

// Get application ID
$app_id = 0;
if (isset($data['application_id'])) {
    $app_id = intval($data['application_id']);
}

if ($app_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid application ID'
    ]);
    exit;
}

try {
    // Database connection is already available from market_db.php
    
    // Check if application exists and get current status
    $stmt = $pdo->prepare("SELECT id, application_status FROM rental_registration WHERE id = ?");
    $stmt->execute([$app_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Application not found',
            'application_id' => $app_id
        ]);
        exit;
    }
    
    // Check if current status is 'interview_scheduled'
    $current_status = $existing['application_status'];
    
    if ($current_status !== 'interview_scheduled') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot mark interview as completed. Application status must be "interview_scheduled". Current status: ' . $current_status,
            'current_status' => $current_status,
            'required_status' => 'interview_scheduled'
        ]);
        exit;
    }
    
    // Update application status to 'interviewed'
    $sql = "UPDATE rental_registration SET 
            application_status = 'interviewed',
            updated_at = NOW()
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':id' => $app_id
    ]);
    
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Interview marked as completed successfully',
            'data' => [
                'application_id' => $app_id,
                'new_status' => 'interviewed',
                'previous_status' => $current_status,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'info',
            'message' => 'No changes made',
            'application_id' => $app_id
        ]);
    }
    
} catch(PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error',
        'error' => $e->getMessage(),
        'error_details' => $e->errorInfo ?? []
    ]);
}
?>