<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . "/../../../db/Market/market_db.php";

    // Get all maps with stall count
    $stmt = $pdo->query("
        SELECT m.*, COUNT(s.id) as stall_count 
        FROM maps m 
        LEFT JOIN stalls s ON m.id = s.map_id 
        GROUP BY m.id 
        ORDER BY m.created_at DESC
    ");
    $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "maps" => $maps
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}