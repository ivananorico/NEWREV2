<?php
// reject_registration.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../../db/RPT/rpt_db.php';

$pdo = getDatabaseConnection();

if (is_array($pdo) && isset($pdo['error'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

try {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $registration_id = $data['registration_id'] ?? null;
    $status = $data['status'] ?? 'needs_correction';
    $correction_notes = $data['correction_notes'] ?? '';
    
    if (!$registration_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Registration ID is required'
        ]);
        exit();
    }
    
    $stmt = $pdo->prepare("
        UPDATE property_registrations 
        SET status = ?, 
            correction_notes = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$status, $correction_notes, $registration_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Application marked as needs correction'
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>