<?php
// ================================================
// PENALTY CONFIGURATIONS API
// ================================================

// Suppress HTML error display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Enable CORS with proper headers
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection with error handling
try {
    $dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';
    
    if (!file_exists($dbPath)) {
        throw new Exception("Database configuration file not found at: " . $dbPath);
    }
    
    require_once $dbPath;
    
    // Check if getDatabaseConnection function exists
    if (!function_exists('getDatabaseConnection')) {
        throw new Exception("getDatabaseConnection function not found in database configuration file");
    }
    
    // Get database connection
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        throw new Exception("Failed to establish database connection");
    }
    
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? $_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            getConfiguration($id);
        } else {
            getConfigurations();
        }
        break;
    case 'POST':
        createConfiguration();
        break;
    case 'PUT':
        updateConfiguration($id);
        break;
    case 'PATCH':
        patchConfiguration($id);
        break;
    case 'DELETE':
        deleteConfiguration($id);
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}

function getConfigurations() {
    global $pdo;
    
    // Get current date or use provided date
    $currentDate = isset($_GET['current_date']) ? $_GET['current_date'] : date('Y-m-d');
    
    try {
        // First, check if table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'penalty_configurations'");
        
        if ($checkTable->rowCount() === 0) {
            // Table doesn't exist, return empty array
            echo json_encode([]);
            return;
        }
        
        // Show configurations that are effective on or before the current date
        $stmt = $pdo->prepare("
            SELECT * FROM penalty_configurations 
            WHERE status = 'active'
            AND effective_date <= ?
            AND (expiration_date IS NULL OR expiration_date >= ?)
            ORDER BY effective_date DESC
        ");
        $stmt->execute([$currentDate, $currentDate]);
        $configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure we always return an array
        echo json_encode($configurations ?: []);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function getConfiguration($id) {
    global $pdo;
    
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid ID parameter"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM penalty_configurations WHERE id = ?");
        $stmt->execute([$id]);
        $configuration = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($configuration) {
            echo json_encode($configuration);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Penalty configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function createConfiguration() {
    global $pdo;
    
    // Get JSON input
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }
    
    // Validate required fields
    $requiredFields = ['penalty_percent', 'effective_date'];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }
    
    // Validate numeric value
    if (!is_numeric($input['penalty_percent'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid numeric value for penalty_percent"]);
        return;
    }
    
    // Validate penalty percent range
    $penaltyPercent = floatval($input['penalty_percent']);
    if ($penaltyPercent < 0 || $penaltyPercent > 100) {
        http_response_code(400);
        echo json_encode(["error" => "Penalty percent must be between 0 and 100"]);
        return;
    }
    
    // Validate dates
    if (!strtotime($input['effective_date'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid effective_date format"]);
        return;
    }
    
    // Validate expiration date if provided
    if (!empty($input['expiration_date']) && !strtotime($input['expiration_date'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid expiration_date format"]);
        return;
    }
    
    try {
        // Check if table exists, create if not
        $checkTable = $pdo->query("SHOW TABLES LIKE 'penalty_configurations'");
        if ($checkTable->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE penalty_configurations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    penalty_percent DECIMAL(5,2) NOT NULL,
                    effective_date DATE NOT NULL,
                    expiration_date DATE NULL,
                    status ENUM('active', 'expired') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO penalty_configurations (
                penalty_percent, effective_date, expiration_date, 
                status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        
        $result = $stmt->execute([
            $penaltyPercent,
            $input['effective_date'],
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $input['status'] ?? 'active'
        ]);
        
        if ($result) {
            $newId = $pdo->lastInsertId();
            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Penalty configuration created successfully", 
                "id" => $newId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to create penalty configuration"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create penalty configuration: " . $e->getMessage()]);
    }
}

function updateConfiguration($id) {
    global $pdo;
    
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing or invalid ID parameter"]);
        return;
    }
    
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }
    
    // Check if configuration exists
    try {
        $checkStmt = $pdo->prepare("SELECT id FROM penalty_configurations WHERE id = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Penalty configuration not found"]);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
        return;
    }
    
    // Validate data if provided
    if (isset($input['penalty_percent'])) {
        if (!is_numeric($input['penalty_percent'])) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid numeric value for penalty_percent"]);
            return;
        }
        $penaltyPercent = floatval($input['penalty_percent']);
        if ($penaltyPercent < 0 || $penaltyPercent > 100) {
            http_response_code(400);
            echo json_encode(["error" => "Penalty percent must be between 0 and 100"]);
            return;
        }
    }
    
    if (isset($input['effective_date']) && !strtotime($input['effective_date'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid effective_date format"]);
        return;
    }
    
    if (isset($input['expiration_date']) && !empty($input['expiration_date']) && !strtotime($input['expiration_date'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid expiration_date format"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE penalty_configurations SET 
                penalty_percent = ?, effective_date = ?, 
                expiration_date = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $input['penalty_percent'] ?? '',
            $input['effective_date'] ?? '',
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $input['status'] ?? 'active',
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Penalty configuration updated successfully"
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "message" => "No changes made to penalty configuration"
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update penalty configuration: " . $e->getMessage()]);
    }
}

function patchConfiguration($id) {
    global $pdo;
    
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing or invalid ID parameter"]);
        return;
    }
    
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data"]);
        return;
    }
    
    // Check if configuration exists
    try {
        $checkStmt = $pdo->prepare("SELECT id FROM penalty_configurations WHERE id = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Penalty configuration not found"]);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
        return;
    }
    
    $fields = [];
    $values = [];
    $allowedFields = ['status', 'expiration_date', 'penalty_percent', 'effective_date'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            // Validate specific fields
            if ($field === 'penalty_percent') {
                if (!is_numeric($input[$field])) {
                    http_response_code(400);
                    echo json_encode(["error" => "Invalid numeric value for penalty_percent"]);
                    return;
                }
                $penaltyPercent = floatval($input[$field]);
                if ($penaltyPercent < 0 || $penaltyPercent > 100) {
                    http_response_code(400);
                    echo json_encode(["error" => "Penalty percent must be between 0 and 100"]);
                    return;
                }
                $values[] = $penaltyPercent;
            } elseif ($field === 'expiration_date' && empty($input[$field])) {
                $values[] = null;
            } else {
                $values[] = $input[$field];
            }
            $fields[] = "$field = ?";
        }
    }
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(["error" => "No valid fields to update"]);
        return;
    }
    
    $fields[] = "updated_at = NOW()";
    $values[] = $id;
    $sql = "UPDATE penalty_configurations SET " . implode(', ', $fields) . " WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Penalty configuration updated successfully"
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "message" => "No changes made to penalty configuration"
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update penalty configuration: " . $e->getMessage()]);
    }
}

function deleteConfiguration($id) {
    global $pdo;
    
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing or invalid ID parameter"]);
        return;
    }
    
    try {
        // First check if exists
        $checkStmt = $pdo->prepare("SELECT id FROM penalty_configurations WHERE id = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Penalty configuration not found"]);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM penalty_configurations WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Penalty configuration deleted successfully"
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Penalty configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete penalty configuration: " . $e->getMessage()]);
    }
}
?>