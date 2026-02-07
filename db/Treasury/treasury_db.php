<?php
// revenue2/db/treasury/treasury_db.php

function getTreasuryConfig() {
    // Check if we're on production (domain) or localhost
    $isProduction = $_SERVER['HTTP_HOST'] === 'revenuetreasury.goserveph.com';
    
    if ($isProduction) {
        // PRODUCTION SETTINGS (Domain)
        return [
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'reve_treasury',
            'user' => 'reve_treasury',
            'pass' => '0P9d10nosJ*tG2C6'  // CHANGE THIS!
        ];
    } else {
        // LOCALHOST SETTINGS
        return [
            'host' => 'localhost',
            'port' => 3307,
            'dbname' => 'treasury',
            'user' => 'root',
            'pass' => ''
        ];
    }
}

function getTreasuryDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        $config = getTreasuryConfig();
        
        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
            
            $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
        } catch (PDOException $e) {
            error_log("Treasury DB Connection Error: " . $e->getMessage());
            
            $isProduction = $_SERVER['HTTP_HOST'] === 'revenuetreasury.goserveph.com';
            
            if ($isProduction) {
                throw new Exception("Could not connect to Treasury database.");
            } else {
                throw new Exception("Local Treasury DB Error: " . $e->getMessage());
            }
        }
    }
    
    return $pdo;
}
?>