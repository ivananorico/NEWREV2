<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../../../db/RPT/rpt_db.php";

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['registration_id']) || empty($input['status'])) {
        throw new Exception("Registration ID and status are required");
    }

    $stmt = $pdo->prepare("
        UPDATE property_registrations 
        SET status = ? 
        WHERE id = ?
    ");

    $stmt->execute([
        $input['status'],
        $input['registration_id']
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Registration status updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>