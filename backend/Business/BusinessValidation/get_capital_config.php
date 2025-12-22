<?php
// Enable CORS with proper headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, expires, Cache-Control, Pragma");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Use your existing database connection
require_once '../../../db/Business/business_db.php';

try {
    // Get all active capital investment tax brackets
    $query = "SELECT * FROM capital_investment_tax_config 
              WHERE (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE()) 
              ORDER BY min_amount ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $configs,
        'count' => count($configs)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>