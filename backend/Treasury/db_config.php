<?php
// revenue2/backend/config/database.php

class Database {
    private static $instances = [];
    private static $env = null;

    /**
     * Determine current environment
     */
    public static function getEnvironment() {
        if (self::$env !== null) {
            return self::$env;
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        self::$env = ($host === 'revenuetreasury.goserveph.com') ? 'production' : 'development';
        return self::$env;
    }

    /**
     * Get database configuration
     */
    public static function getConfig($database = 'default') {
        $env = self::getEnvironment();

        $configs = [
            'business' => [
                'development' => [
                    'host' => 'localhost',
                    'port' => 3307,
                    'dbname' => 'business_tax',
                    'user' => 'root',
                    'pass' => ''
                ],
                'production' => [
                    'host' => 'localhost',
                    'port' => 3306,
                    'dbname' => 'reve_business',
                    'user' => 'reve_business',
                    'pass' => '#dBB9-#j-m^t%3Jc'
                ]
            ],
            'rpt' => [
                'development' => [
                    'host' => 'localhost',
                    'port' => 3307,
                    'dbname' => 'rpt',
                    'user' => 'root',
                    'pass' => ''
                ],
                'production' => [
                    'host' => 'localhost',
                    'port' => 3306,
                    'dbname' => 'reve_rpt',
                    'user' => 'reve_rpt',
                    'pass' => 'u0I6%f#6!-LM#EAr'
                ]
            ],
            'market' => [
                'development' => [
                    'host' => 'localhost',
                    'port' => 3307,
                    'dbname' => 'market_rent',
                    'user' => 'root',
                    'pass' => ''
                ],
                'production' => [
                    'host' => 'localhost',
                    'port' => 3306,
                    'dbname' => 'reve_market',
                    'user' => 'reve_market',
                    'pass' => '^NHcAIm^n6Ko7fvgy'
                ]
            ]
        ];

        return $configs[$database][$env] ?? null;
    }

    /**
     * Get database connection
     */
    public static function getConnection($database = 'default') {
        if (isset(self::$instances[$database])) {
            return self::$instances[$database];
        }

        $config = self::getConfig($database);
        
        if (!$config) {
            throw new Exception("Database configuration not found for: {$database}");
        }

        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['user'], $config['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            self::$instances[$database] = $pdo;
            return $pdo;
            
        } catch (PDOException $e) {
            throw new Exception("Connection failed for {$database}: " . $e->getMessage());
        }
    }

    /**
     * Close all connections
     */
    public static function closeAll() {
        self::$instances = [];
    }
}
?>