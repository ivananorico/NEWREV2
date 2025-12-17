<?php
// ================================================
// GET BUILDING ASSESSMENT LEVELS API
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

// Try to include DB connection with proper error handling
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Database config file not found at: " . $dbPath]);
    exit();
}

require_once $dbPath;

// Get PDO connection
$pdo = getDatabaseConnection();
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
        getBuildingAssessmentLevels($pdo);
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

function getBuildingAssessmentLevels($pdo) {
    try {
        // Check if table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'building_assessment_levels'");
        if ($checkTable->rowCount() === 0) {
            // Table doesn't exist, return empty array
            echo json_encode([
                "success" => true,
                "data" => []
            ]);
            return;
        }

        $stmt = $pdo->prepare("
            SELECT id, classification, min_assessed_value, max_assessed_value, 
                   level_percent, effective_date, expiration_date, status
            FROM building_assessment_levels 
            WHERE status = 'active'
            ORDER BY classification, min_assessed_value
        ");
        $stmt->execute();
        
        $assessment_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "success" => true,
            "data" => $assessment_levels ?: []
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}