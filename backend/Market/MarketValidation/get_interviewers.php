<?php
// revenue2/backend/Market/MarketValidation/get_interviewers_debug.php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Turn on error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../../../db/Market/market_db.php';

$response = [
    'debug' => [
        'php_version' => phpversion(),
        'pdo_drivers' => PDO::getAvailableDrivers(),
        'include_path' => __FILE__,
        'db_connection' => 'unknown'
    ]
];

try {
    // Test database connection
    $response['debug']['db_connection'] = 'connected';
    
    // Check if table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'market_interviewers'");
    $response['debug']['table_exists'] = $table_check->rowCount() > 0;
    
    if ($response['debug']['table_exists']) {
        // Get count
        $count = $pdo->query("SELECT COUNT(*) FROM market_interviewers")->fetchColumn();
        $response['debug']['record_count'] = $count;
        
        // Get actual data
        $query = "SELECT id, name, status FROM market_interviewers ORDER BY name";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        $interviewers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response['status'] = 'success';
        $response['data'] = $interviewers;
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Table market_interviewers does not exist';
    }
    
} catch (PDOException $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
    $response['debug']['error'] = [
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>