<?php
// ================================================
// DATABASE CONFIGURATION FOR BOTH ENVIRONMENTS
// ================================================

function getDatabaseConfig() {
    // Check if we're on production (domain) or localhost
    $isProduction = $_SERVER['HTTP_HOST'] === 'revenuetreasury.goserveph.com';
    
    if ($isProduction) {
        // PRODUCTION SETTINGS (Domain)
        return [
            'host' => 'localhost',
            'port' => 3306,  // Production usually uses default port
            'dbname' => 'reve_rpt',
            'user' => 'reve_rpt',
            'pass' => '9A^jzp1k*J192zp+'
        ];
    } else {
        // LOCALHOST SETTINGS
        return [
            'host' => 'localhost',
            'port' => 3307,  // Your local XAMPP port
            'dbname' => 'rpt',
            'user' => 'root',  // Local XAMPP default user
            'pass' => ''       // Local XAMPP default password
        ];
    }
}

// ================================================
// CONNECTION FUNCTION
// ================================================

function getDatabaseConnection() {
    $config = getDatabaseConfig();
    
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        return $pdo;
        
    } catch (PDOException $e) {
        // Log error but don't expose details to user
        error_log("Database connection failed: " . $e->getMessage());
        
        // Return user-friendly error
        return [
            'error' => true,
            'message' => 'Database connection failed. Please try again later.',
            'debug' => ($_SERVER['HTTP_HOST'] !== 'revenuetreasury.goserveph.com') ? $e->getMessage() : null
        ];
    }
}
?>