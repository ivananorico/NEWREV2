<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Use your existing database connection
require_once '../../../db/Business/business_db.php';

try {
    // Get all active capital investment tax brackets
    $query = "SELECT * FROM capital_investment_tax_config 
              WHERE (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE()) 
              ORDER BY min_amount ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $configs = $stmt->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'data' => $configs,
        'count' => count($configs)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>