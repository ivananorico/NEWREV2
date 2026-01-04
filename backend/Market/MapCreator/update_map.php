<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Enable error logging for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . "/../../../db/Market/market_db.php";

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }
    
    $mapId = $input['map_id'] ?? null;
    $stalls = $input['stalls'] ?? [];

    if (!$mapId) {
        throw new Exception("Map ID is required");
    }

    // Verify map exists
    $mapCheck = $pdo->prepare("SELECT id FROM maps WHERE id = ?");
    $mapCheck->execute([$mapId]);
    if (!$mapCheck->fetch()) {
        throw new Exception("Map with ID $mapId not found");
    }

    // Get the first available class ID as default
    $defaultClassStmt = $pdo->query("SELECT class_id FROM stall_rights ORDER BY price ASC LIMIT 1");
    $defaultClass = $defaultClassStmt->fetch(PDO::FETCH_ASSOC);
    $defaultClassId = $defaultClass ? $defaultClass['class_id'] : 3; // Default to 3 if no classes

    // Update existing stalls - CORRECTED: Removed section_id
    $updateStmt = $pdo->prepare("
        UPDATE stalls 
        SET name = ?, pos_x = ?, pos_y = ?, price = ?, 
            height = ?, length = ?, width = ?, 
            pixel_width = ?, pixel_height = ?, 
            status = ?, class_id = ?
        WHERE id = ? AND map_id = ?
    ");

    // Insert new stalls - CORRECTED: Removed section_id
    $insertStmt = $pdo->prepare("
        INSERT INTO stalls (
            map_id, name, pos_x, pos_y, price, 
            height, length, width, 
            pixel_width, pixel_height, 
            status, class_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $updatedCount = 0;
    $insertedCount = 0;

    foreach ($stalls as $stall) {
        // Validate required fields
        if (empty($stall['name'])) {
            continue; // Skip stalls without a name
        }
        
        // Determine class_id - use provided, or default
        $class_id = isset($stall['class_id']) ? (int)$stall['class_id'] : $defaultClassId;
        
        // Handle pixel dimensions - use provided or defaults
        $pixel_width = isset($stall['pixel_width']) ? (float)$stall['pixel_width'] : 80;
        $pixel_height = isset($stall['pixel_height']) ? (float)$stall['pixel_height'] : 60;
        
        if (isset($stall['id']) && !isset($stall['isNew'])) {
            // Update existing stall
            $success = $updateStmt->execute([
                $stall['name'],
                (int)($stall['pos_x'] ?? 0),
                (int)($stall['pos_y'] ?? 0),
                (float)($stall['price'] ?? 0),
                (float)($stall['height'] ?? 0),
                (float)($stall['length'] ?? 0),
                (float)($stall['width'] ?? 0),
                $pixel_width,
                $pixel_height,
                $stall['status'] ?? 'available',
                $class_id,
                (int)$stall['id'],
                (int)$mapId
            ]);
            
            if ($success) {
                $updatedCount++;
            }
        } else if (isset($stall['isNew']) && $stall['isNew'] === true) {
            // Insert new stall
            $success = $insertStmt->execute([
                (int)$mapId,
                $stall['name'],
                (int)($stall['pos_x'] ?? 0),
                (int)($stall['pos_y'] ?? 0),
                (float)($stall['price'] ?? 0),
                (float)($stall['height'] ?? 0),
                (float)($stall['length'] ?? 0),
                (float)($stall['width'] ?? 0),
                $pixel_width,
                $pixel_height,
                $stall['status'] ?? 'available',
                $class_id
            ]);
            
            if ($success) {
                $insertedCount++;
            }
        }
    }

    echo json_encode([
        "status" => "success",
        "message" => "Map updated successfully",
        "updated" => $updatedCount,
        "inserted" => $insertedCount,
        "total_processed" => count($stalls)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "trace" => $e->getTraceAsString() // For debugging, remove in production
    ]);
}
?>