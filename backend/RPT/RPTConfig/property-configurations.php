<?php
// ================================================
// PROPERTY CONFIGURATIONS API
// ================================================

// Enable CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Try to include DB connection with proper error handling
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php'; // Adjusted path

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
        if ($id) {
            getConfiguration($pdo, $id);
        } else {
            getConfigurations($pdo);
        }
        break;
    case 'POST':
        createConfiguration($pdo);
        break;
    case 'PUT':
        updateConfiguration($pdo, $id);
        break;
    case 'PATCH':
        patchConfiguration($pdo, $id);
        break;
    case 'DELETE':
        deleteConfiguration($pdo, $id);
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}

// ==========================
// FUNCTIONS
// ==========================

function getConfigurations($pdo) {
    try {
        // Check if table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'property_configurations'");
        if ($checkTable->rowCount() === 0) {
            // Table doesn't exist, return empty array
            echo json_encode([]);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM property_configurations
            ORDER BY 
                CASE WHEN status = 'active' THEN 1 ELSE 2 END,
                classification,
                effective_date DESC
        ");
        $stmt->execute();
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Always return array, even if empty
        if ($configs === false) {
            $configs = [];
        }
        
        echo json_encode($configs);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function getConfiguration($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM property_configurations WHERE id = ?");
        $stmt->execute([$id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            echo json_encode($config);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function createConfiguration($pdo) {
    // Get input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }

    // Validate required fields
    $requiredFields = ['classification', 'material_type', 'unit_cost', 'depreciation_rate', 'min_value', 'max_value', 'effective_date'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }

    try {
        // Check if table exists, create it if not
        $checkTable = $pdo->query("SHOW TABLES LIKE 'property_configurations'");
        if ($checkTable->rowCount() === 0) {
            // Create table if it doesn't exist
            $createTable = $pdo->exec("
                CREATE TABLE property_configurations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    classification VARCHAR(100) NOT NULL,
                    material_type VARCHAR(100) NOT NULL,
                    unit_cost DECIMAL(15,2) NOT NULL,
                    depreciation_rate DECIMAL(5,2) NOT NULL,
                    min_value DECIMAL(15,2) NOT NULL,
                    max_value DECIMAL(15,2) NOT NULL,
                    effective_date DATE NOT NULL,
                    expiration_date DATE NULL,
                    status ENUM('active', 'expired') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        }

        // Insert new configuration
        $stmt = $pdo->prepare("
            INSERT INTO property_configurations (
                classification, material_type, unit_cost, depreciation_rate,
                min_value, max_value, effective_date, expiration_date, status,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $description = $data['description'] ?? null;
        $expiration_date = !empty($data['expiration_date']) ? $data['expiration_date'] : null;
        $status = $data['status'] ?? 'active';

        $success = $stmt->execute([
            trim($data['classification']),
            trim($data['material_type']),
            floatval($data['unit_cost']),
            floatval($data['depreciation_rate']),
            floatval($data['min_value']),
            floatval($data['max_value']),
            $data['effective_date'],
            $expiration_date,
            $status
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

function updateConfiguration($pdo, $id) {
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
        
        $fields[] = "updated_at = NOW()";
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(["error" => "No fields to update"]);
            return;
        }
        
        $values[] = $id;
        $sql = "UPDATE property_configurations SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($values);

        if ($success && $stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Property configuration updated successfully"
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Property configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update property configuration: " . $e->getMessage()]);
    }
}

function patchConfiguration($pdo, $id) {
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing ID parameter"]);
        return;
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data"]);
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

    $fields[] = "updated_at = NOW()";

    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(["error" => "No valid fields to update"]);
        return;
    }

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
            http_response_code(404);
            echo json_encode(["error" => "Property configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update property configuration: " . $e->getMessage()]);
    }
}

function deleteConfiguration($pdo, $id) {
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing ID parameter"]);
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
?>