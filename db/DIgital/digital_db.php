<?php
// revenue2/db/Digital/digital_db.php

function getDigitalDB() {
    // Detect environment
    $isLocal = ($_SERVER['SERVER_NAME'] == 'localhost' || 
                $_SERVER['SERVER_NAME'] == '127.0.0.1' || 
                strpos($_SERVER['SERVER_NAME'], '.local') !== false);
    
    if ($isLocal) {
        // Local development settings
        $host = 'localhost:3307';  // Your local port
        $dbname = 'digital';
        $username = 'root';
        $password = '';
    } else {
        // Production/Live server settings
        $host = 'localhost';  // Usually just 'localhost' on live servers
        $dbname = 'reve_digital';  // Might be different on live server
        $username = 'reve_digital';  // Change this for production
        $password = 'AfcDj6zIzi@^J7p!';  // Change this for production
    }
    
    // Alternatively, use environment variables for more flexibility
    // $host = getenv('DB_HOST') ?: 'localhost';
    // $dbname = getenv('DB_NAME') ?: 'digital';
    // $username = getenv('DB_USER') ?: 'root';
    // $password = getenv('DB_PASS') ?: '';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // Log error with environment info
        error_log("Digital DB Connection failed [Host: $host, DB: $dbname]: " . $e->getMessage());
        return null;
    }
}

// Alternative version with config file support:
function getDigitalDBWithConfig() {
    // Try to load configuration from config file
    $configFile = dirname(__FILE__) . '/../../config/database.php';
    
    if (file_exists($configFile)) {
        $config = require $configFile;
        
        if (isset($config['digital'])) {
            $dbConfig = $config['digital'];
            $host = $dbConfig['host'] ?? 'localhost';
            $dbname = $dbConfig['database'] ?? 'digital';
            $username = $dbConfig['username'] ?? 'root';
            $password = $dbConfig['password'] ?? '';
            $port = $dbConfig['port'] ?? 3306;
            
            // Use port if specified
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        } else {
            // Fallback to environment detection
            return getDigitalDB();
        }
    } else {
        // Fallback to environment detection
        return getDigitalDB();
    }
    
    try {
        $pdo = new PDO(
            $dsn,
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Digital DB Connection failed: " . $e->getMessage());
        return null;
    }
}

// You can also create a more flexible version:
function getDatabaseConnection($database = 'digital') {
    $connections = [
        'digital' => [
            'local' => [
                'host' => 'localhost:3307',
                'dbname' => 'digital',
                'username' => 'root',
                'password' => ''
            ],
            'production' => [
                'host' => 'localhost',
                'dbname' => 'digital_prod',
                'username' => 'prod_user',
                'password' => 'prod_password'
            ]
        ],
        // Add other databases if needed
        'rpt' => [
            'local' => [
                'host' => 'localhost:3307',
                'dbname' => 'rpt',
                'username' => 'root',
                'password' => ''
            ],
            'production' => [
                'host' => 'localhost',
                'dbname' => 'rpt_prod',
                'username' => 'prod_user',
                'password' => 'prod_password'
            ]
        ]
    ];
    
    // Detect environment
    $isLocal = ($_SERVER['SERVER_NAME'] == 'localhost' || 
                $_SERVER['SERVER_NAME'] == '127.0.0.1' || 
                strpos($_SERVER['SERVER_NAME'], '.local') !== false);
    
    $env = $isLocal ? 'local' : 'production';
    
    if (!isset($connections[$database][$env])) {
        error_log("Database configuration not found for: $database ($env)");
        return null;
    }
    
    $config = $connections[$database][$env];
    
    try {
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("$database DB Connection failed ($env): " . $e->getMessage());
        return null;
    }
}

// Usage for Digital DB: getDatabaseConnection('digital');
// Usage for RPT DB: getDatabaseConnection('rpt');
?>