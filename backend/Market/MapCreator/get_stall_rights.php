<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Include DB - use the same path as your other files
    require_once __DIR__ . "/../../../db/Market/market_db.php";

    $sql = "SELECT * FROM stall_rights ORDER BY price DESC";
    $result = $pdo->query($sql); // Use $pdo instead of $conn

    if ($result) {
        $classes = [];
        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $classes[] = $row;
        }
        echo json_encode([
            "status" => "success",
            "classes" => $classes
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No stall classes found"
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>