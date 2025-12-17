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
    
    $required = ['registration_id', 'scheduled_date', 'assessor_name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO property_inspections 
        (registration_id, scheduled_date, assessor_name, status)
        VALUES (?, ?, ?, 'scheduled')
    ");

    $stmt->execute([
        $input['registration_id'],
        $input['scheduled_date'],
        $input['assessor_name']
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Inspection scheduled successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>