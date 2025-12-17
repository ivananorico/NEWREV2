<?php
// Enable CORS with proper headers
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once '../../../db/RPT/rpt_db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getConfigurations();
        break;
    case 'POST':
        createConfiguration();
        break;
    case 'PUT':
        updateConfiguration();
        break;
    case 'PATCH':
        patchConfiguration();
        break;
    case 'DELETE':
        deleteConfiguration();
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}

function getConfigurations() {
    global $pdo;
    
    $currentDate = isset($_GET['current_date']) ? $_GET['current_date'] : date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM land_configurations 
            WHERE effective_date <= ? 
            AND (expiration_date IS NULL OR expiration_date >= ? OR status = 'expired')
            ORDER BY status ASC, effective_date DESC, classification
        ");
        $stmt->execute([$currentDate, $currentDate]);
        $configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($configurations);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function createConfiguration() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data"]);
        return;
    }
    
    // Validate required fields (without vicinity)
    if (!isset($input['classification']) || !isset($input['market_value']) || 
        !isset($input['assessment_level']) || !isset($input['effective_date'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields"]);
        return;
    }
    
    // Check for overlapping active configurations with same classification
    try {
        $checkStmt = $pdo->prepare("
            SELECT id FROM land_configurations 
            WHERE classification = ? 
            AND status = 'active'
            AND effective_date <= ? 
            AND (expiration_date IS NULL OR expiration_date >= ?)
        ");
        $checkStmt->execute([
            $input['classification'],
            $input['effective_date'],
            $input['effective_date']
        ]);
        
        if ($checkStmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(["error" => "Active configuration already exists for this classification on the selected date"]);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO land_configurations (
                classification, market_value, assessment_level, description,
                effective_date, expiration_date, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $input['classification'],
            $input['market_value'],
            $input['assessment_level'],
            $input['description'] ?? null,
            $input['effective_date'],
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $input['status'] ?? 'active'
        ]);
        
        echo json_encode([
            "message" => "Land configuration created successfully", 
            "id" => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create land configuration: " . $e->getMessage()]);
    }
}

function updateConfiguration() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing ID parameter"]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data"]);
        return;
    }
    
    // Check for overlapping active configurations with same classification (excluding current record)
    try {
        $checkStmt = $pdo->prepare("
            SELECT id FROM land_configurations 
            WHERE classification = ? 
            AND status = 'active'
            AND effective_date <= ? 
            AND (expiration_date IS NULL OR expiration_date >= ?)
            AND id != ?
        ");
        $checkStmt->execute([
            $input['classification'],
            $input['effective_date'],
            $input['effective_date'],
            $id
        ]);
        
        if ($checkStmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(["error" => "Active configuration already exists for this classification on the selected date"]);
            return;
        }
        
        $stmt = $pdo->prepare("
            UPDATE land_configurations SET 
                classification = ?, market_value = ?, assessment_level = ?, description = ?,
                effective_date = ?, expiration_date = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $input['classification'],
            $input['market_value'],
            $input['assessment_level'],
            $input['description'] ?? null,
            $input['effective_date'],
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $input['status'] ?? 'active',
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Land configuration updated successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Land configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update land configuration: " . $e->getMessage()]);
    }
}

function patchConfiguration() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing ID parameter"]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data"]);
        return;
    }
    
    // Build dynamic update query (without vicinity)
    $fields = [];
    $values = [];
    
    $allowedFields = ['status', 'expiration_date', 'market_value', 'assessment_level', 'description'];
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $fields[] = "$field = ?";
            $values[] = $input[$field];
        }
    }
    
    // Always update the updated_at timestamp
    $fields[] = "updated_at = NOW()";
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(["error" => "No valid fields to update"]);
        return;
    }
    
    $values[] = $id;
    $sql = "UPDATE land_configurations SET " . implode(', ', $fields) . " WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Land configuration updated successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Land configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update land configuration: " . $e->getMessage()]);
    }
}

function deleteConfiguration() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing ID parameter"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM land_configurations WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Land configuration deleted successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Land configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete land configuration: " . $e->getMessage()]);
    }
}
?>