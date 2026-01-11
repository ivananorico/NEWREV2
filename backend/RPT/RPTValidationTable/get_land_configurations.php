<?php
// ================================================
// GET LAND CONFIGURATIONS API
// ================================================

// Enable CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Create database connection using the config from rpt_db.php
function createPDOConnection() {
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

// Try to include DB connection with proper error handling
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Database config file not found at: " . $dbPath]);
    exit();
}

require_once $dbPath;

// Get PDO connection
$pdo = createPDOConnection();
if (!$pdo || (is_array($pdo) && isset($pdo['error']))) {
    http_response_code(500);
    $errorMsg = is_array($pdo) ? $pdo['message'] : "Failed to connect to database";
    echo json_encode(["error" => "Database connection failed: " . $errorMsg]);
    exit();
}

// Determine HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Route based on method
switch ($method) {
    case 'GET':
        getLandConfigurations($pdo);
        break;
    case 'OPTIONS':
        http_response_code(200);
        exit();
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}

// ==========================
// FUNCTIONS
// ==========================

function getLandConfigurations($pdo) {
    try {
        // Check if table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'land_configurations'");
        if ($checkTable->rowCount() === 0) {
            // Table doesn't exist, return empty array
            echo json_encode([
                "success" => true,
                "data" => []
            ]);
            return;
        }

        $stmt = $pdo->prepare("
            SELECT * FROM land_configurations 
            WHERE status = 'active' 
            ORDER BY classification
        ");
        $stmt->execute();
        $configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "data" => $configurations ?: []
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}
?>