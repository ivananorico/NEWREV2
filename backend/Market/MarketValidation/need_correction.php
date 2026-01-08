<?php
// revenue2/backend/Market/MarketValidation/need_correction.php

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

// Debug log
error_log("Need Correction Request: " . print_r($data, true));

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON data',
        'json_error' => json_last_error_msg(),
        'raw_input' => $rawInput
    ]);
    exit;
}

// Get application ID
$app_id = 0;
if (isset($data['application_id'])) {
    $app_id = intval($data['application_id']);
} elseif (isset($data['id'])) {
    $app_id = intval($data['id']);
}

error_log("Application ID received: " . $app_id);

if ($app_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid application ID',
        'received_data' => $data
    ]);
    exit;
}

// Get correction notes
$correction_notes = isset($data['correction_notes']) ? trim($data['correction_notes']) : '';

if (empty($correction_notes)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Correction notes are required'
    ]);
    exit;
}

try {
    // Check if application exists
    $stmt = $pdo->prepare("SELECT id, application_status FROM rental_registration WHERE id = ?");
    $stmt->execute([$app_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Database query result: " . print_r($existing, true));
    
    if (!$existing) {
        // Also check if it's a different ID format
        $stmt = $pdo->prepare("SELECT id, application_status FROM rental_registration WHERE stall_rights_no = ?");
        $stmt->execute([$app_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Application not found',
                'application_id' => $app_id,
                'available_ids' => 'Check IDs in rental_registration table'
            ]);
            exit;
        }
    }
    
    error_log("Found application. Current status: " . $existing['application_status']);
    
    // Update application status to 'need_correction'
    $sql = "UPDATE rental_registration SET 
            application_status = 'need_correction',
            correction_notes = :correction_notes,
            updated_at = NOW()
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':correction_notes' => $correction_notes,
        ':id' => $existing['id']
    ]);
    
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected > 0) {
        // Get updated data
        $stmt = $pdo->prepare("SELECT * FROM rental_registration WHERE id = ?");
        $stmt->execute([$existing['id']]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Application marked as needs correction',
            'data' => [
                'application_id' => $existing['id'],
                'new_status' => 'need_correction',
                'correction_notes' => $correction_notes,
                'previous_status' => $existing['application_status'],
                'stall_rights_no' => $updated['stall_rights_no']
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'info',
            'message' => 'No changes made',
            'application_id' => $existing['id']
        ]);
    }
    
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
}
?>