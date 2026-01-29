<?php
// revenue2/db/Business/business_db.php

/**
 * Get database connection for Business database
 * 
 * @return PDO|bool Database connection or false on failure
 */
function getDatabaseConnection() {
    // Determine if we're in local or production environment
    $isProduction = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'revenuetreasury.goserveph.com';

    if ($isProduction) {
        // Production database credentials
        $host = 'localhost';
        $port = 3306;
        $dbname = 'reve_business';
        $user = 'reve_business';
        $pass = 'IMCF*e^P#nDvoPNl';
    } else {
        // Local development credentials
        $host = 'localhost';
        $port = 3307;
        $dbname = 'business_tax';
        $user = 'root';
        $pass = '';
    }

    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
        
    } catch (PDOException $e) {
        // Return false on connection failure
        error_log("Business Database connection failed: " . $e->getMessage());
        return false;
    }
}
?>