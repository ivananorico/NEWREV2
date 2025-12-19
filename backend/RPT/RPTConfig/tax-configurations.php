<?php
// ================================================
// TAX CONFIGURATIONS API
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
        // Check if table exists - try BOTH table names
        $tableName = 'rpt_tax_config'; // Use the correct table name from your database dump
        
        $checkTable = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        if ($checkTable->rowCount() === 0) {
            // Return empty array if table doesn't exist
            echo json_encode([]);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM {$tableName}
            ORDER BY 
                CASE WHEN status = 'active' THEN 1 ELSE 2 END,
                tax_name,
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
        $tableName = 'rpt_tax_config';
        $stmt = $pdo->prepare("SELECT * FROM {$tableName} WHERE id = ?");
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
    $requiredFields = ['tax_name', 'tax_percent', 'effective_date'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }

    // Validate tax_name
    if (!in_array($data['tax_name'], ['Basic Tax', 'SEF Tax'])) {
        http_response_code(400);
        echo json_encode(["error" => "Tax name must be either 'Basic Tax' or 'SEF Tax'"]);
        return;
    }

    // Validate numeric value
    if (!is_numeric($data['tax_percent'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid numeric value for tax_percent"]);
        return;
    }

    try {
        $tableName = 'rpt_tax_config';
        
        // Check if table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        if ($checkTable->rowCount() === 0) {
            // If table doesn't exist, use the correct CREATE statement from your database dump
            $createTable = $pdo->exec("
                CREATE TABLE {$tableName} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tax_name VARCHAR(50) NOT NULL,
                    tax_percent DECIMAL(5,2) NOT NULL,
                    effective_date DATE NOT NULL,
                    expiration_date DATE NULL,
                    status ENUM('active','expired') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        }

        // Insert new configuration
        $stmt = $pdo->prepare("
            INSERT INTO {$tableName} (
                tax_name, tax_percent, effective_date, expiration_date, 
                status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $expiration_date = !empty($data['expiration_date']) ? $data['expiration_date'] : null;
        $status = $data['status'] ?? 'active';

        $success = $stmt->execute([
            trim($data['tax_name']),
            floatval($data['tax_percent']),
            $data['effective_date'],
            $expiration_date,
            $status
        ]);

        if ($success) {
            $newId = $pdo->lastInsertId();
            echo json_encode([
                "success" => true,
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
        $tableName = 'rpt_tax_config';
        $fields = [];
        $values = [];
        $allowedFields = ['tax_name', 'tax_percent', 'effective_date', 'expiration_date', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                if ($field === 'tax_percent') {
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
        $sql = "UPDATE {$tableName} SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($values);

        if ($success && $stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Tax configuration updated successfully"
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Tax configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update tax configuration: " . $e->getMessage()]);
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

    try {
        $tableName = 'rpt_tax_config';
        $fields = [];
        $values = [];
        $allowedFields = ['status', 'expiration_date', 'tax_percent', 'tax_name', 'effective_date'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                
                if ($field === 'tax_percent') {
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
        $sql = "UPDATE {$tableName} SET " . implode(', ', $fields) . " WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Tax configuration updated successfully"
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Tax configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update tax configuration: " . $e->getMessage()]);
    }
}

function deleteConfiguration($pdo, $id) {
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing ID parameter"]);
        return;
    }

    try {
        $tableName = 'rpt_tax_config';
        $stmt = $pdo->prepare("DELETE FROM {$tableName} WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Tax configuration deleted successfully"
            ]);
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