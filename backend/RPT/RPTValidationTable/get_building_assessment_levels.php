<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../../../db/RPT/rpt_db.php";

try {
    $stmt = $pdo->prepare("
        SELECT id, classification, min_assessed_value, max_assessed_value, 
               level_percent, effective_date, expiration_date, status
        FROM building_assessment_levels 
        WHERE status = 'active'
        ORDER BY classification, min_assessed_value
    ");
    $stmt->execute();
    
    $assessment_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'assessment_levels' => $assessment_levels
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>