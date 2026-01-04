<?php
// delete_map.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . "/../../../db/Market/market_db.php";

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }
    
    $mapId = $input['map_id'] ?? null;
    
    if (!$mapId) {
        throw new Exception("Map ID is required");
    }

    // Validate map ID is numeric
    if (!is_numeric($mapId)) {
        throw new Exception("Invalid map ID format");
    }

    $mapId = (int)$mapId;

    // First, check if map exists and get its details
    $checkStmt = $pdo->prepare("SELECT id, name, file_path FROM maps WHERE id = ?");
    $checkStmt->execute([$mapId]);
    $map = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$map) {
        throw new Exception("Map with ID $mapId not found in database");
    }

    // Count stalls before deletion (for response)
    $countStmt = $pdo->prepare("SELECT COUNT(*) as stall_count FROM stalls WHERE map_id = ?");
    $countStmt->execute([$mapId]);
    $result = $countStmt->fetch(PDO::FETCH_ASSOC);
    $stallCount = $result['stall_count'] ?? 0;

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Delete stalls first (foreign key constraint might require this order)
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

        // Optional: Delete the uploaded image file
        if (!empty($map['file_path'])) {
            $imagePath = __DIR__ . "/../../../" . $map['file_path'];
            if (file_exists($imagePath) && is_file($imagePath)) {
                @unlink($imagePath); // Use @ to suppress errors if file doesn't exist
            }
        }

        $pdo->commit();

        echo json_encode([
            "status" => "success", 
            "message" => "Map '{$map['name']}' deleted successfully",
            "details" => [
                "map_id" => $mapId,
                "map_name" => $map['name'],
                "stalls_deleted" => $stallsDeleted,
                "image_deleted" => !empty($map['file_path'])
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => $e->getMessage()
    ]);
}
?>