<?php
/**
 * Consolidated Database Configuration
 * Handles connections to all 5 databases for Revenue System
 */

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Determine if we're in production or local
$isProduction = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'revenuetreasury.goserveph.com';

if ($isProduction) {
    // PRODUCTION DATABASE CREDENTIALS
    $db_configs = [
        'treasury' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'reve_treasury',
            'user' => 'reve_treasury',
            'pass' => 'your_treasury_password'
        ],
        'rpt' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'reve_rpt',
            'user' => 'reve_rpt',
            'pass' => 'your_rpt_password'
        ],
        'business_tax' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'reve_business',
            'user' => 'reve_business',
            'pass' => 'eIuEFsbOpmlo-hpu'
        ],
        'market_rent' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'reve_market',
            'user' => 'reve_market',
            'pass' => 'your_market_password'
        ],
        'digital' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'reve_digital',
            'user' => 'reve_digital',
            'pass' => 'your_digital_password'
        ]
    ];
} else {
    // LOCAL DEVELOPMENT CREDENTIALS
    $db_configs = [
        'treasury' => [
            'host' => 'localhost',
            'port' => 3307,
            'name' => 'treasury',
            'user' => 'root',
            'pass' => ''
        ],
        'rpt' => [
            'host' => 'localhost',
            'port' => 3307,
            'name' => 'rpt',
            'user' => 'root',
            'pass' => ''
        ],
        'business_tax' => [
            'host' => 'localhost',
            'port' => 3307,
            'name' => 'business_tax',
            'user' => 'root',
            'pass' => ''
        ],
        'market_rent' => [
            'host' => 'localhost',
            'port' => 3307,
            'name' => 'market_rent',
            'user' => 'root',
            'pass' => ''
        ],
        'digital' => [
            'host' => 'localhost',
            'port' => 3307,
            'name' => 'digital',
            'user' => 'root',
            'pass' => ''
        ]
    ];
}

/**
 * Get PDO connection for specific database
 * @param string $db_name - 'treasury', 'rpt', 'business_tax', 'market_rent', 'digital'
 * @return PDO|false
 */
function getDatabaseConnection($db_name = 'treasury') {
    global $db_configs;
    
    if (!isset($db_configs[$db_name])) {
        error_log("Database config not found for: $db_name");
        return false;
    }
    
    $config = $db_configs[$db_name];
    
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO($dsn, $config['user'], $config['pass'], $options);
        
    } catch (PDOException $e) {
        error_log("$db_name Database connection failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get Treasury connection (primary for revenue data)
 */
function getTreasuryConnection() {
    return getDatabaseConnection('treasury');
}

/**
 * Get RPT connection
 */
function getRPTConnection() {
    return getDatabaseConnection('rpt');
}

/**
 * Get Business Tax connection
 */
function getBusinessTaxConnection() {
    return getDatabaseConnection('business_tax');
}

/**
 * Get Market Rent connection
 */
function getMarketRentConnection() {
    return getDatabaseConnection('market_rent');
}

/**
 * Get Digital payment connection
 */
function getDigitalConnection() {
    return getDatabaseConnection('digital');
}

// Test all connections
if (isset($_GET['test_all_db']) && $_GET['test_all_db'] == 'true') {
    $databases = ['treasury', 'rpt', 'business_tax', 'market_rent', 'digital'];
    
    foreach ($databases as $db) {
        $conn = getDatabaseConnection($db);
        if ($conn) {
            echo "✅ $db connection successful!<br>";
            
            // Test a simple query
            try {
                $stmt = $conn->query("SELECT DATABASE() as db_name");
                $result = $stmt->fetch();
                echo "&nbsp;&nbsp;Connected to: " . $result['db_name'] . "<br>";
                
                // Show table count
                $tables = $conn->query("SHOW TABLES")->fetchAll();
                echo "&nbsp;&nbsp;Tables: " . count($tables) . "<br>";
                
            } catch (Exception $e) {
                echo "&nbsp;&nbsp;Query test failed: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "❌ $db connection failed!<br>";
        }
        echo "<hr>";
    }
    exit();
}
?>