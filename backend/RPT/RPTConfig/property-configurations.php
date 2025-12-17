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
    
    error_log("Fetching property configurations for date: " . $currentDate);
    
    try {
        // Show configurations that are effective on or before the current date
        // and either haven't expired or have no expiration date
        $stmt = $pdo->prepare("
            SELECT * FROM property_configurations 
            WHERE status = 'active'
            AND effective_date <= ?
            AND (expiration_date IS NULL OR expiration_date >= ?)
            ORDER BY classification, material_type, min_value ASC
        ");
        $stmt->execute([$currentDate, $currentDate]);
        $configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Found " . count($configurations) . " configurations for date: " . $currentDate);
        
        echo json_encode($configurations);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function createConfiguration() {
    global $pdo;
    
    // Get JSON input
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    
    error_log("Received data: " . print_r($input, true));
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON error: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }
    
    // Validate required fields based on ACTUAL database schema
    // From your database dump: classification, material_type, unit_cost, depreciation_rate, min_value, max_value
    $requiredFields = ['classification', 'material_type', 'unit_cost', 'depreciation_rate', 'min_value', 'max_value', 'effective_date'];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            error_log("Missing field: " . $field);
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }
    
    // Validate numeric values
    $numericFields = ['unit_cost', 'depreciation_rate', 'min_value', 'max_value'];
    foreach ($numericFields as $field) {
        if (!is_numeric($input[$field])) {
            error_log("Invalid numeric value for field: " . $field);
            http_response_code(400);
            echo json_encode(["error" => "Invalid numeric value for field: " . $field]);
            return;
        }
    }
    
    // Ensure min_value is less than max_value
    if ($input['min_value'] >= $input['max_value']) {
        error_log("min_value must be less than max_value");
        http_response_code(400);
        echo json_encode(["error" => "Minimum value must be less than maximum value"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO property_configurations (
                classification, material_type, unit_cost, depreciation_rate,
                min_value, max_value, effective_date, expiration_date, status,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $result = $stmt->execute([
            $input['classification'],
            $input['material_type'],
            $input['unit_cost'],
            $input['depreciation_rate'],
            $input['min_value'],
            $input['max_value'],
            $input['effective_date'],
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $input['status'] ?? 'active'
        ]);
        
        if ($result) {
            $newId = $pdo->lastInsertId();
            error_log("Successfully created configuration with ID: " . $newId);
            echo json_encode([
                "message" => "Property configuration created successfully", 
                "id" => $newId
            ]);
        } else {
            error_log("Insert failed");
            http_response_code(500);
            echo json_encode(["error" => "Failed to create property configuration"]);
        }
    } catch (PDOException $e) {
        error_log("Database error on create: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "Failed to create property configuration: " . $e->getMessage()]);
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
    
    error_log("Updating configuration ID: " . $id . " with data: " . print_r($input, true));
    
    // Validate required fields
    $requiredFields = ['classification', 'material_type', 'unit_cost', 'depreciation_rate', 'min_value', 'max_value', 'effective_date'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            error_log("Missing field during update: " . $field);
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE property_configurations SET 
                classification = ?, material_type = ?, unit_cost = ?, depreciation_rate = ?,
                min_value = ?, max_value = ?, effective_date = ?, 
                expiration_date = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $input['classification'],
            $input['material_type'],
            $input['unit_cost'],
            $input['depreciation_rate'],
            $input['min_value'],
            $input['max_value'],
            $input['effective_date'],
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $input['status'] ?? 'active',
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            error_log("Successfully updated configuration ID: " . $id);
            echo json_encode(["message" => "Property configuration updated successfully"]);
        } else {
            error_log("Configuration not found: " . $id);
            http_response_code(404);
            echo json_encode(["error" => "Property configuration not found"]);
        }
    } catch (PDOException $e) {
        error_log("Database error on update: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "Failed to update property configuration: " . $e->getMessage()]);
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
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }
    
    // Build dynamic update query based on ACTUAL database columns
    $fields = [];
    $values = [];
    
    // Allowed fields from the property_configurations table schema
    $allowedFields = ['status', 'expiration_date', 'unit_cost', 'depreciation_rate', 
                     'min_value', 'max_value', 'classification', 'material_type', 'effective_date'];
    
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
    $sql = "UPDATE property_configurations SET " . implode(', ', $fields) . " WHERE id = ?";
    
    error_log("Patch SQL: " . $sql);
    error_log("Patch values: " . print_r($values, true));
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Property configuration updated successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Property configuration not found"]);
        }
    } catch (PDOException $e) {
        error_log("Database error on patch: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "Failed to update property configuration: " . $e->getMessage()]);
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
        $stmt = $pdo->prepare("DELETE FROM property_configurations WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Property configuration deleted successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Property configuration not found"]);
        }
    } catch (PDOException $e) {
        error_log("Database error on delete: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete property configuration: " . $e->getMessage()]);
    }
}
?>