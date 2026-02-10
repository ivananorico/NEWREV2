<?php
// schedule_inspection.php - SIMPLER VERSION without updated_at issues

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database config file not found at: " . $dbPath]);
    exit();
}

require_once $dbPath;

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
            'message' => 'Database connection failed. Please try again later.',
            'debug' => ($_SERVER['HTTP_HOST'] !== 'revenuetreasury.goserveph.com') ? $e->getMessage() : null
        ];
    }
}

$pdo = createPDOConnection();
if (!$pdo || (is_array($pdo) && isset($pdo['error']))) {
    http_response_code(500);
    $errorMsg = is_array($pdo) ? $pdo['message'] : "Failed to connect to database";
    echo json_encode(["success" => false, "message" => "Database connection failed: " . $errorMsg]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

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

function scheduleInspection($pdo) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }

    // Validate required fields
    $requiredFields = ['registration_id', 'scheduled_date', 'assessor_name', 'assessor_id'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && empty(trim($data[$field])))) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Missing required field: " . $field . " (value: " . json_encode($data[$field] ?? 'null') . ")"]);
            return;
        }
    }

    try {
        // Check if we need to add inspector columns to property_registrations
        $checkInspectorColumn = $pdo->query("SHOW COLUMNS FROM property_registrations LIKE 'inspector_name'");
        if ($checkInspectorColumn->rowCount() === 0) {
            $pdo->exec("ALTER TABLE property_registrations ADD COLUMN inspector_name VARCHAR(255) DEFAULT 'To be assigned'");
            $pdo->exec("ALTER TABLE property_registrations ADD COLUMN inspector_id INT NULL AFTER inspector_name");
        }

        // Start transaction
        $pdo->beginTransaction();

        // 1. Insert into property_inspections table
        $stmt1 = $pdo->prepare("
            INSERT INTO property_inspections 
            (registration_id, scheduled_date, assessor_name, assessor_id, status, created_at)
            VALUES (?, ?, ?, ?, 'scheduled', NOW())
        ");

        $assessorId = intval($data['assessor_id']);
        $assessorName = trim($data['assessor_name']);
        
        $stmt1->execute([
            intval($data['registration_id']),
            $data['scheduled_date'],
            $assessorName,
            $assessorId
        ]);

        $inspectionId = $pdo->lastInsertId();

        // 2. UPDATE the property_registrations table with inspector info
        $stmt2 = $pdo->prepare("
            UPDATE property_registrations 
            SET status = 'for_inspection', 
                inspector_name = ?,
                inspector_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt2->execute([
            $assessorName,
            $assessorId,
            intval($data['registration_id'])
        ]);

        // 3. Update assessor status to 'not_available'
        // First check what columns exist in assessors table
        $assessorColumns = $pdo->query("SHOW COLUMNS FROM assessors")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('updated_at', $assessorColumns)) {
            $stmt3 = $pdo->prepare("
                UPDATE assessors 
                SET status = 'not_available',
                    updated_at = NOW()
                WHERE id = ?
            ");
        } else {
            $stmt3 = $pdo->prepare("
                UPDATE assessors 
                SET status = 'not_available'
                WHERE id = ?
            ");
        }
        
        $stmt3->execute([$assessorId]);

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            "success" => true,
            "status" => "success",
            "message" => "Inspection scheduled successfully",
            "data" => [
                "registration_id" => intval($data['registration_id']),
                "inspection_id" => $inspectionId,
                "scheduled_date" => $data['scheduled_date'],
                "assessor_name" => $assessorName,
                "assessor_id" => $assessorId,
                "new_status" => "for_inspection",
                "inspector_updated" => true,
                "assessor_updated" => true
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        error_log("Schedule inspection error: " . $e->getMessage() . " | Data: " . json_encode($data));
        echo json_encode(["success" => false, "message" => "Failed to schedule inspection: " . $e->getMessage()]);
    }
}
?>