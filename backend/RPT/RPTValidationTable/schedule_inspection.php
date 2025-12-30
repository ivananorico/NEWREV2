<?php
// ================================================
// SCHEDULE INSPECTION API - UPDATED
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
    echo json_encode(["success" => false, "message" => "Database config file not found at: " . $dbPath]);
    exit();
}

require_once $dbPath;

// Get PDO connection
$pdo = getDatabaseConnection();
if (!$pdo || (is_array($pdo) && isset($pdo['error']))) {
    http_response_code(500);
    $errorMsg = is_array($pdo) ? $pdo['message'] : "Failed to connect to database";
    echo json_encode(["success" => false, "message" => "Database connection failed: " . $errorMsg]);
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
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
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
        echo json_encode(["success" => false, "message" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }

    // Validate required fields
    $requiredFields = ['registration_id', 'scheduled_date', 'assessor_name'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Missing required field: " . $field]);
            return;
        }
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // 1. Check/create property_inspections table
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

        // 2. Insert into property_inspections table
        $stmt1 = $pdo->prepare("
            INSERT INTO property_inspections 
            (registration_id, scheduled_date, assessor_name, status, created_at, updated_at)
            VALUES (?, ?, ?, 'scheduled', NOW(), NOW())
        ");

        $stmt1->execute([
            intval($data['registration_id']),
            $data['scheduled_date'],
            trim($data['assessor_name'])
        ]);

        // 3. UPDATE the registration status to 'for_inspection'
        $stmt2 = $pdo->prepare("
            UPDATE property_registrations 
            SET status = 'for_inspection', 
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt2->execute([intval($data['registration_id'])]);
        
        // Check if status was updated
        if ($stmt2->rowCount() === 0) {
            // If no rows affected, check current status
            $checkStmt = $pdo->prepare("SELECT status FROM property_registrations WHERE id = ?");
            $checkStmt->execute([intval($data['registration_id'])]);
            $currentStatus = $checkStmt->fetchColumn();
            
            if ($currentStatus !== 'for_inspection') {
                throw new Exception("Failed to update registration status. Current status: " . $currentStatus);
            }
        }

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            "success" => true,
            "status" => "success",
            "message" => "Inspection scheduled successfully and status updated to 'for_inspection'",
            "data" => [
                "registration_id" => intval($data['registration_id']),
                "scheduled_date" => $data['scheduled_date'],
                "assessor_name" => trim($data['assessor_name']),
                "new_status" => "for_inspection"
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to schedule inspection: " . $e->getMessage()]);
    }
}
?>