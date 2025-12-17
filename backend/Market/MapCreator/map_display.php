<?php
// map_display.php
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

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception("Only GET allowed");
    }

    if (!isset($_GET['map_id'])) {
        throw new Exception("map_id parameter is required");
    }

    $mapId = (int)$_GET['map_id'];

    // Fetch map data
    $stmt = $pdo->prepare("SELECT id, name, file_path FROM maps WHERE id = ?");
    $stmt->execute([$mapId]);
    $map = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$map) {
        throw new Exception("Map not found");
    }

    // Fetch stalls for this map WITH PIXEL DIMENSIONS - UPDATED QUERY
    $stmtStalls = $pdo->prepare("
        SELECT 
            s.id, 
            s.name, 
            s.pos_x, 
            s.pos_y, 
            s.price, 
            s.height, 
            s.length, 
            s.width,
            s.pixel_width,     -- ADD THIS
            s.pixel_height,    -- ADD THIS
            s.status,
            s.class_id,
            s.section_id,
            sc.class_name,
            sec.name as section_name
        FROM stalls s 
        LEFT JOIN stall_rights sc ON s.class_id = sc.class_id 
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.map_id = ?
    ");
    $stmtStalls->execute([$mapId]);
    $stalls = $stmtStalls->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "map" => [
            "id" => (int)$map['id'],
            "name" => $map['name'],
            "image_path" => $map['file_path']
        ],
        "stalls" => $stalls
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>