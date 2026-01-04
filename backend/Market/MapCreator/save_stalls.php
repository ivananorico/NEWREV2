<?php
// save_stalls.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Enable error logging for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log file for debugging
$logFile = __DIR__ . '/save_stalls.log';
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Starting save_stalls.php\n", FILE_APPEND);

try {
    // Debug: Log all incoming data
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] FILES data: " . print_r($_FILES, true) . "\n", FILE_APPEND);

    require_once __DIR__ . "/../../../db/Market/market_db.php";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST allowed. Received: " . $_SERVER['REQUEST_METHOD']);
    }

    // Check required fields
    if (empty($_POST['mapName'])) {
        throw new Exception("mapName is required");
    }
    
    if (!isset($_FILES['mapImage']) || $_FILES['mapImage']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("mapImage is required or upload failed. Error: " . ($_FILES['mapImage']['error'] ?? 'No file'));
    }
    
    if (empty($_POST['stalls'])) {
        throw new Exception("stalls data is required");
    }

    $mapName = trim($_POST['mapName']);
    $stallsJson = $_POST['stalls'];
    
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Map Name: " . $mapName . "\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Stalls JSON length: " . strlen($stallsJson) . "\n", FILE_APPEND);
    
    $stalls = json_decode($stallsJson, true);
    if ($stalls === null) {
        $jsonError = json_last_error_msg();
        throw new Exception("Invalid JSON for stalls: " . $jsonError . " | JSON: " . substr($stallsJson, 0, 200));
    }
    
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Decoded stalls count: " . count($stalls) . "\n", FILE_APPEND);

    // Handle map image upload
    $file = $_FILES['mapImage'];
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] File info: " . print_r($file, true) . "\n", FILE_APPEND);
    
    $allowed = ['jpg','jpeg','png','gif','webp','svg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        throw new Exception("Invalid file type: ." . $ext . ". Allowed: " . implode(', ', $allowed));
    }

    // Create uploads directory if it doesn't exist
    $uploadsDir = __DIR__ . "/../../../uploads/market/maps";
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0755, true)) {
            throw new Exception("Failed to create uploads directory: " . $uploadsDir);
        }
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Created directory: " . $uploadsDir . "\n", FILE_APPEND);
    }

    $filename = 'map_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $target = $uploadsDir . '/' . $filename;
    
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Target path: " . $target . "\n", FILE_APPEND);

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception("Failed to move uploaded file. Temp: " . $file['tmp_name'] . " to " . $target);
    }
    
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] File uploaded successfully\n", FILE_APPEND);

    $imagePath = "uploads/market/maps/" . $filename;

    // Insert map
    $stmt = $pdo->prepare("INSERT INTO maps (name, file_path) VALUES (?, ?)");
    if (!$stmt->execute([$mapName, $imagePath])) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception("Failed to insert map: " . $errorInfo[2]);
    }
    
    $mapId = $pdo->lastInsertId();
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Map inserted with ID: " . $mapId . "\n", FILE_APPEND);

    // Insert stalls - UPDATED query to match your database schema
    $stmtStall = $pdo->prepare("
        INSERT INTO stalls (
            map_id, 
            name, 
            pos_x, 
            pos_y, 
            price, 
            height, 
            length, 
            width, 
            pixel_width, 
            pixel_height, 
            status, 
            class_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $insertedStalls = 0;
    foreach ($stalls as $index => $stall) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Processing stall " . $index . ": " . print_r($stall, true) . "\n", FILE_APPEND);
        
        $params = [
            $mapId,
            $stall['name'] ?? 'Stall ' . ($index + 1),
            (int)($stall['pos_x'] ?? 0),
            (int)($stall['pos_y'] ?? 0),
            (float)($stall['price'] ?? 0),
            (float)($stall['height'] ?? 0),
            (float)($stall['length'] ?? 0),
            (float)($stall['width'] ?? 0),
            (float)($stall['pixel_width'] ?? 80),
            (float)($stall['pixel_height'] ?? 60),
            $stall['status'] ?? 'available',
            (int)($stall['class_id'] ?? 3)
        ];
        
        if (!$stmtStall->execute($params)) {
            $errorInfo = $stmtStall->errorInfo();
            throw new Exception("Failed to insert stall " . $index . ": " . $errorInfo[2]);
        }
        
        $insertedStalls++;
    }
    
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Inserted " . $insertedStalls . " stalls\n", FILE_APPEND);

    echo json_encode([
        "status" => "success",
        "map_id" => (int)$mapId,
        "file_path" => $imagePath,
        "stalls_inserted" => $insertedStalls,
        "message" => "Map and stalls saved successfully"
    ]);
    
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Success response sent\n", FILE_APPEND);
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR: " . $errorMessage . "\n", FILE_APPEND);
    
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $errorMessage
    ]);
}
?>