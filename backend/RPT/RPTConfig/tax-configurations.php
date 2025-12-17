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
    
    // Get current date or use provided date
    $currentDate = isset($_GET['current_date']) ? $_GET['current_date'] : date('Y-m-d');
    
    try {
        // Show configurations that are effective on or before the current date
        $stmt = $pdo->prepare("
            SELECT * FROM tax_configurations 
            WHERE status = 'active'
            AND effective_date <= ?
            AND (expiration_date IS NULL OR expiration_date >= ?)
            ORDER BY tax_name, effective_date DESC
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
    
    // Get JSON input
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }
    
    // Validate required fields
    $requiredFields = ['tax_name', 'tax_percent', 'effective_date'];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }
    
    // Validate tax_name
    if (!in_array($input['tax_name'], ['Basic Tax', 'SEF Tax'])) {
        http_response_code(400);
        echo json_encode(["error" => "Tax name must be either 'Basic Tax' or 'SEF Tax'"]);
        return;
    }
    
    // Validate numeric value
    if (!is_numeric($input['tax_percent'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid numeric value for tax_percent"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tax_configurations (
                tax_name, tax_percent, effective_date, expiration_date, 
                status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $result = $stmt->execute([
            $input['tax_name'],
            $input['tax_percent'],
            $input['effective_date'],
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $input['status'] ?? 'active'
        ]);
        
        if ($result) {
            $newId = $pdo->lastInsertId();
            echo json_encode([
                "message" => "Tax configuration created successfully", 
                "id" => $newId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to create tax configuration"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create tax configuration: " . $e->getMessage()]);
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
    
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }
    
    // Validate required fields
    $requiredFields = ['tax_name', 'tax_percent', 'effective_date'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }
    
    // Validate tax_name
    if (!in_array($input['tax_name'], ['Basic Tax', 'SEF Tax'])) {
        http_response_code(400);
        echo json_encode(["error" => "Tax name must be either 'Basic Tax' or 'SEF Tax'"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE tax_configurations SET 
                tax_name = ?, tax_percent = ?, effective_date = ?, 
                expiration_date = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $input['tax_name'],
            $input['tax_percent'],
            $input['effective_date'],
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $input['status'] ?? 'active',
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Tax configuration updated successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Tax configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update tax configuration: " . $e->getMessage()]);
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
    
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data"]);
        return;
    }
    
    $fields = [];
    $values = [];
    $allowedFields = ['status', 'expiration_date', 'tax_percent', 'tax_name', 'effective_date'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            // Validate tax_name if being updated
            if ($field === 'tax_name' && !in_array($input[$field], ['Basic Tax', 'SEF Tax'])) {
                http_response_code(400);
                echo json_encode(["error" => "Tax name must be either 'Basic Tax' or 'SEF Tax'"]);
                return;
            }
            $fields[] = "$field = ?";
            $values[] = $input[$field];
        }
    }
    
    $fields[] = "updated_at = NOW()";
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(["error" => "No valid fields to update"]);
        return;
    }
    
    $values[] = $id;
    $sql = "UPDATE tax_configurations SET " . implode(', ', $fields) . " WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Tax configuration updated successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Tax configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update tax configuration: " . $e->getMessage()]);
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
        $stmt = $pdo->prepare("DELETE FROM tax_configurations WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Tax configuration deleted successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Tax configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete tax configuration: " . $e->getMessage()]);
    }
}
?>