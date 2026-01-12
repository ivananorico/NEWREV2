<?php
// db.php
// PDO connection for market1 with dual environment support

// ================================================
// DATABASE CONFIGURATION FOR BOTH ENVIRONMENTS
// ================================================

function getDatabaseConfig() {
    // Check if we're on production (your domain) or localhost
    // UPDATED: Changed to your actual domain
    $isProduction = $_SERVER['HTTP_HOST'] === 'revenuetreasury.goserveph.com';
    
    if ($isProduction) {
        // PRODUCTION SETTINGS (Domain)
        return [
            'host' => 'localhost',
            'port' => 3306,  // Production usually uses default port
            'dbname' => 'market_rent',  // Your production database name
            'user' => 'reve_market_rent', // Your production username
            'pass' => '8MtfM779bzt-eD!u' // Your production password
        ];
    } else {
        // LOCALHOST SETTINGS
        return [
            'host' => 'localhost',
            'port' => 3307,  // Your local XAMPP/WAMP port
            'dbname' => 'market_rent',  // Local database name
            'user' => 'root',  // Local XAMPP default user
            'pass' => ''       // Local XAMPP default password
        ];
    }
}

// ================================================
// MAIN CONNECTION CODE
// ================================================

try {
    // Get configuration based on environment
    $config = getDatabaseConfig();
    
    // Use the configuration
    $host = $config['host'];
    $port = $config['port'];
    $dbname = $config['dbname'];
    $user = $config['user'];
    $pass = $config['pass'];
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // fetch as associative array
        PDO::ATTR_EMULATE_PREPARES => false, // use real prepared statements
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    
} catch (PDOException $e) {
    // Better error handling with environment detection
    $isLocal = $_SERVER['HTTP_HOST'] === 'localhost' || 
               strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
               strpos($_SERVER['HTTP_HOST'], 'local.') !== false;
    
    if ($isLocal) {
        // Show detailed error for local development
        $errorResponse = [
            "status" => "error",
            "message" => "Database connection failed: " . $e->getMessage(),
            "config" => [
                "host" => $host,
                "port" => $port,
                "dbname" => $dbname,
                "user" => $user
            ]
        ];
    } else {
        // Show generic error for production
        $errorResponse = [
            "status" => "error",
            "message" => "Database connection failed. Please contact administrator."
        ];
        // Log the actual error for debugging
        error_log("Production DB Error: " . $e->getMessage());
    }
    
    echo json_encode($errorResponse);
    exit;
}

// Optional: Test connection (remove in production)
// echo json_encode(["status" => "success", "message" => "Connected to database: " . $dbname]);
?>