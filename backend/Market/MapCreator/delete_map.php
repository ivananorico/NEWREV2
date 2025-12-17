<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . "/../../../db/Market/market_db.php";

    $input = json_decode(file_get_contents('php://input'), true);
    $mapId = $input['map_id'] ?? null;
    
    if (!$mapId) {
        throw new Exception("No map ID provided");
    }

    // First, check if map exists
    $checkStmt = $pdo->prepare("SELECT id, name FROM maps WHERE id = ?");
    $checkStmt->execute([$mapId]);
    $map = $checkStmt->fetch();

    if (!$map) {
        throw new Exception("Map ID $mapId not found in database");
    }

    // Start transaction
    $pdo->beginTransaction();

    // Delete stalls first
    $stmt = $pdo->prepare("DELETE FROM stalls WHERE map_id = ?");
    $stmt->execute([$mapId]);
    $stallsDeleted = $stmt->rowCount();

    // Delete map
    $stmt = $pdo->prepare("DELETE FROM maps WHERE id = ?");
    $stmt->execute([$mapId]);
    $mapsDeleted = $stmt->rowCount();

    if ($mapsDeleted === 0) {
        throw new Exception("Failed to delete map - no rows affected");
    }

    $pdo->commit();

    echo json_encode([
        "status" => "success", 
        "message" => "Map '{$map['name']}' and $stallsDeleted stalls deleted successfully",
        "map_id" => $mapId
    ]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => $e->getMessage()
    ]);
}