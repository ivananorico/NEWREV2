<?php
// ================================================
// PROPERTY CONFIGURATIONS API
// ================================================

// Enable CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
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

// Get ID parameter if exists
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Route based on method
switch ($method) {
    case 'GET':
        getPropertyConfigurations($pdo, $id);
        break;
    case 'POST':
        createPropertyConfiguration($pdo);
        break;
    case 'PUT':
        updatePropertyConfiguration($pdo, $id);
        break;
    case 'PATCH':
        patchPropertyConfiguration($pdo, $id);
        break;
    case 'DELETE':
        deletePropertyConfiguration($pdo, $id);
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}

// ==========================
// FUNCTIONS
// ==========================

function getPropertyConfigurations($pdo, $id) {
    try {
        if ($id) {
            // Get single configuration
            $stmt = $pdo->prepare("SELECT * FROM property_configurations WHERE id = ?");
            $stmt->execute([$id]);
            $configuration = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($configuration) {
                echo json_encode([
                    "success" => true,
                    "data" => $configuration
                ]);
            } else {
                http_response_code(404);
                echo json_encode(["error" => "Property configuration not found"]);
            }
        } else {
            // Get all configurations
            $stmt = $pdo->prepare("
                SELECT * FROM property_configurations 
                WHERE status = 'active' 
                ORDER BY classification, material_type
            ");
            $stmt->execute();
            $configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "data" => $configurations ?: []
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function createPropertyConfiguration($pdo) {
    // Get input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }

    // Validate required fields
    $requiredFields = ['classification', 'material_type', 'unit_cost', 'depreciation_rate', 
                      'min_value', 'max_value', 'effective_date'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && empty(trim($data[$field])))) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO property_configurations 
            (classification, material_type, unit_cost, depreciation_rate, 
             min_value, max_value, effective_date, expiration_date, status,
             created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $success = $stmt->execute([
            trim($data['classification']),
            trim($data['material_type']),
            floatval($data['unit_cost']),
            floatval($data['depreciation_rate']),
            floatval($data['min_value']),
            floatval($data['max_value']),
            $data['effective_date'],
            !empty($data['expiration_date']) ? $data['expiration_date'] : null,
            $data['status'] ?? 'active'
        ]);

        if ($success) {
            $newId = $pdo->lastInsertId();
            echo json_encode([
                "success" => true,
                "message" => "Property configuration created successfully",
                "id" => $newId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to create property configuration"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create property configuration: " . $e->getMessage()]);
    }
}

function updatePropertyConfiguration($pdo, $id) {
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing or invalid ID parameter"]);
        return;
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }

    try {
        $fields = [];
        $values = [];
        $allowedFields = ['classification', 'material_type', 'unit_cost', 'depreciation_rate', 
                         'min_value', 'max_value', 'effective_date', 'expiration_date', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                if (in_array($field, ['unit_cost', 'depreciation_rate', 'min_value', 'max_value'])) {
                    $values[] = floatval($data[$field]);
                } else if ($field === 'expiration_date' && empty($data[$field])) {
                    $values[] = null;
                } else {
                    $values[] = $data[$field];
                }
            }
        }
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(["error" => "No fields to update"]);
            return;
        }
        
        $fields[] = "updated_at = NOW()";
        $values[] = $id;
        $sql = "UPDATE property_configurations SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Property configuration updated successfully"
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "message" => "No changes were made to the property configuration"
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update property configuration: " . $e->getMessage()]);
    }
}

function patchPropertyConfiguration($pdo, $id) {
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing or invalid ID parameter"]);
        return;
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }

    $fields = [];
    $values = [];
    $allowedFields = ['status', 'expiration_date', 'unit_cost', 'depreciation_rate', 
                     'min_value', 'max_value', 'classification', 'material_type', 'effective_date'];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            
            if (in_array($field, ['unit_cost', 'depreciation_rate', 'min_value', 'max_value'])) {
                $values[] = floatval($data[$field]);
            } else if ($field === 'expiration_date' && empty($data[$field])) {
                $values[] = null;
            } else {
                $values[] = $data[$field];
            }
        }
    }

    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(["error" => "No valid fields to update"]);
        return;
    }

    $fields[] = "updated_at = NOW()";
    $values[] = $id;
    $sql = "UPDATE property_configurations SET " . implode(', ', $fields) . " WHERE id = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Property configuration updated successfully"
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "message" => "No changes were made to the property configuration"
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update property configuration: " . $e->getMessage()]);
    }
}

function deletePropertyConfiguration($pdo, $id) {
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing or invalid ID parameter"]);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM property_configurations WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Property configuration deleted successfully"
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Property configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete property configuration: " . $e->getMessage()]);
    }
}