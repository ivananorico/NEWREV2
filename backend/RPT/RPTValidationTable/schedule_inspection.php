<?php
// ================================================
// SCHEDULE INSPECTION API
// ================================================

// Enable CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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
    case 'POST':
        scheduleInspection($pdo);
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

function scheduleInspection($pdo) {
    // Get input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }

    // Validate required fields
    $requiredFields = ['registration_id', 'scheduled_date', 'assessor_name'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }

    try {
        // Check if table exists, create if not
        $checkTable = $pdo->query("SHOW TABLES LIKE 'property_inspections'");
        if ($checkTable->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE property_inspections (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    registration_id INT NOT NULL,
                    scheduled_date DATE NOT NULL,
                    assessor_name VARCHAR(255) NOT NULL,
                    status VARCHAR(50) DEFAULT 'scheduled',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        }

        $stmt = $pdo->prepare("
            INSERT INTO property_inspections 
            (registration_id, scheduled_date, assessor_name, status, created_at, updated_at)
            VALUES (?, ?, ?, 'scheduled', NOW(), NOW())
        ");

        $success = $stmt->execute([
            intval($data['registration_id']),
            $data['scheduled_date'],
            trim($data['assessor_name'])
        ]);

        if ($success) {
            echo json_encode([
                "success" => true,
                "message" => "Inspection scheduled successfully"
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to schedule inspection"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to schedule inspection: " . $e->getMessage()]);
    }
}