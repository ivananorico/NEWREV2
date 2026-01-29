<?php
// market_db.php - FIXED VERSION
// PDO connection for market database

// ================================================
// DATABASE CONFIGURATION FOR BOTH ENVIRONMENTS
// ================================================

function getDatabaseConfig() {
    // Check if we're on production or localhost
    $isProduction = $_SERVER['HTTP_HOST'] === 'revenuetreasury.goserveph.com';
    
    if ($isProduction) {
        // PRODUCTION SETTINGS
        return [
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'reve_market_rent',
            'user' => 'reve_market_rent',
            'pass' => 'EN%7%snqAgp8Nss2'
        ];
    } else {
        // LOCALHOST SETTINGS
        return [
            'host' => 'localhost',
            'port' => 3307,
            'dbname' => 'market_rent',
            'user' => 'root',
            'pass' => ''
        ];
    }
}

// ================================================
// MAIN CONNECTION CODE (creates global $pdo)
// ================================================

try {
    $config = getDatabaseConfig();
    
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    // Create global $pdo variable
    $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
    
} catch (PDOException $e) {
    // Error handling
    $isLocal = $_SERVER['HTTP_HOST'] === 'localhost' || 
               strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
               strpos($_SERVER['HTTP_HOST'], 'local.') !== false;
    
    if ($isLocal) {
        $errorResponse = [
            "status" => "error",
            "message" => "Database connection failed: " . $e->getMessage(),
            "config" => $config
        ];
    } else {
        $errorResponse = [
            "status" => "error",
            "message" => "Database connection failed. Please contact administrator."
        ];
        error_log("Market DB Error: " . $e->getMessage());
    }
    
    echo json_encode($errorResponse);
    exit;
}
?>