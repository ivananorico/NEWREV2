<?php
// get_assessors.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Try to include DB connection
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database config file not found at: " . $dbPath]);
    exit();
}

require_once $dbPath;

// Create PDO connection
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
        error_log("Database connection failed: " . $e->getMessage());
        return [
            'error' => true,
            'message' => 'Database connection failed.',
            'debug' => ($_SERVER['HTTP_HOST'] !== 'revenuetreasury.goserveph.com') ? $e->getMessage() : null
        ];
    }
}

// Get PDO connection
$pdo = createPDOConnection();
if (!$pdo || (is_array($pdo) && isset($pdo['error']))) {
    http_response_code(500);
    $errorMsg = is_array($pdo) ? $pdo['message'] : "Failed to connect to database";
    echo json_encode(["success" => false, "message" => "Database connection failed: " . $errorMsg]);
    exit();
}

try {
    // Check if assessors table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'assessors'");
    if ($checkTable->rowCount() === 0) {
        // Create the table if it doesn't exist
        $pdo->exec("
            CREATE TABLE assessors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                status ENUM('available', 'not_available') DEFAULT 'available',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert some default assessors
        $defaultAssessors = [
            ['John Smith', 'available'],
            ['Maria Garcia', 'available'],
            ['Robert Johnson', 'available'],
            ['Sarah Williams', 'available'],
            ['David Jones', 'not_available']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO assessors (name, status) VALUES (?, ?)");
        foreach ($defaultAssessors as $assessor) {
            $stmt->execute($assessor);
        }
    }
    
    // Fetch assessors
    $stmt = $pdo->query("SELECT * FROM assessors ORDER BY 
        CASE status 
            WHEN 'available' THEN 1 
            WHEN 'not_available' THEN 2 
            ELSE 3 
        END, name");
    
    $assessors = $stmt->fetchAll();
    
    echo json_encode([
        "success" => true,
        "status" => "success",
        "message" => "Assessors retrieved successfully",
        "data" => $assessors
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to get assessors: " . $e->getMessage()]);
}
?>