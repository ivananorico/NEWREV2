<?php
// delete_stall.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Include DB - adjust path based on your structure
    require_once __DIR__ . "/../../../db/Market/market_db.php";

    // Get stall ID from POST data
    $stallId = $_POST['stall_id'] ?? null;
    
    if (!$stallId) {
        throw new Exception("Stall ID is required");
    }

    // Validate that stall ID is numeric
    if (!is_numeric($stallId)) {
        throw new Exception("Invalid stall ID format");
    }

    // Log for debugging
    error_log("Deleting stall ID: " . $stallId);

    // Prepare and execute delete statement
    $stmt = $pdo->prepare("DELETE FROM stalls WHERE id = ?");
    $stmt->execute([$stallId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "status" => "success", 
            "message" => "Stall deleted successfully"
        ]);
    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "Stall not found or already deleted"
        ]);
    }
    
} catch (Exception $e) {
    error_log("Delete stall error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => $e->getMessage()
    ]);
}
?>