<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . "/../../../db/Market/market_db.php";

    $input = json_decode(file_get_contents('php://input'), true);
    $mapId = $input['map_id'] ?? null;
    $stalls = $input['stalls'] ?? [];

    if (!$mapId) {
        throw new Exception("Map ID is required");
    }

    // Get the first available class ID as default
    $defaultClassStmt = $pdo->query("SELECT class_id FROM stall_rights ORDER BY price ASC LIMIT 1");
    $defaultClass = $defaultClassStmt->fetch(PDO::FETCH_ASSOC);
    $defaultClassId = $defaultClass ? $defaultClass['class_id'] : null;

    // Update existing stalls and insert new ones - UPDATED WITH PIXEL DIMENSIONS
    $updateStmt = $pdo->prepare("
        UPDATE stalls 
        SET name = ?, pos_x = ?, pos_y = ?, price = ?, height = ?, length = ?, width = ?, pixel_width = ?, pixel_height = ?, status = ?, class_id = ?, section_id = ?
        WHERE id = ? AND map_id = ?
    ");

    $insertStmt = $pdo->prepare("
        INSERT INTO stalls (map_id, name, pos_x, pos_y, price, height, length, width, pixel_width, pixel_height, status, class_id, section_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $updatedCount = 0;
    $insertedCount = 0;

    foreach ($stalls as $stall) {
        // Determine class_id - use provided, or default, or null if no classes exist
        $class_id = $stall['class_id'] ?? $defaultClassId;
        
        // Handle section_id - convert to NULL if empty or not set
        $section_id = isset($stall['section_id']) && $stall['section_id'] !== '' ? $stall['section_id'] : null;
        
        // Handle pixel dimensions - use provided or defaults
        $pixel_width = $stall['pixel_width'] ?? 80;
        $pixel_height = $stall['pixel_height'] ?? 60;
        
        if (isset($stall['id']) && !isset($stall['isNew'])) {
            // Update existing stall
            $updateStmt->execute([
                $stall['name'] ?? 'Unnamed Stall',
                $stall['pos_x'] ?? 0,
                $stall['pos_y'] ?? 0,
                $stall['price'] ?? 0,
                $stall['height'] ?? 0,
                $stall['length'] ?? 0,
                $stall['width'] ?? 0,
                $pixel_width,      // ADD THIS
                $pixel_height,     // ADD THIS
                $stall['status'] ?? 'available',
                $class_id,
                $section_id,
                $stall['id'],
                $mapId
            ]);
            $updatedCount++;
        } else if (isset($stall['isNew'])) {
            // Insert new stall
            $insertStmt->execute([
                $mapId,
                $stall['name'] ?? 'Unnamed Stall',
                $stall['pos_x'] ?? 0,
                $stall['pos_y'] ?? 0,
                $stall['price'] ?? 0,
                $stall['height'] ?? 0,
                $stall['length'] ?? 0,
                $stall['width'] ?? 0,
                $pixel_width,      // ADD THIS
                $pixel_height,     // ADD THIS
                $stall['status'] ?? 'available',
                $class_id,
                $section_id
            ]);
            $insertedCount++;
        }
    }

    echo json_encode([
        "status" => "success",
        "message" => "Map updated successfully",
        "updated" => $updatedCount,
        "inserted" => $insertedCount
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>