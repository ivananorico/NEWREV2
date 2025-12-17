<?php
// ================================================
// LAND CONFIGURATIONS API
// ================================================

// Enable CORS and JSON response
header("Access-Control-Allow-Origin: *"); // Allow all origins for debugging
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include DB connection
require_once '../../../db/RPT/rpt_db.php';

// Get PDO connection
$pdo = getDatabaseConnection();
if (is_array($pdo) && isset($pdo['error']) && $pdo['error'] === true) {
    http_response_code(500);
    echo json_encode(["error" => $pdo['message']]);
    exit;
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
    // Get ALL records from database (ignore date filters)
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM land_configurations
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
        
        // Debug logging
        error_log("Fetched " . count($configs) . " land configurations from database");
        
        echo json_encode($configs, JSON_PRETTY_PRINT);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage(), "debug" => $e->getTraceAsString()]);
    }
}

function getConfiguration($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM land_configurations WHERE id = ?");
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
    
    // Debug: Log the raw input
    error_log("Raw input for create: " . $input);
    
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }

    // Validate required fields
    $requiredFields = ['classification', 'market_value', 'assessment_level', 'effective_date'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }

    try {
        // Check for overlapping active configs (optional)
        $checkStmt = $pdo->prepare("
            SELECT id FROM land_configurations 
            WHERE classification = ? AND status = 'active'
            AND effective_date <= ? 
            AND (expiration_date IS NULL OR expiration_date >= ?)
        ");
        $checkStmt->execute([
            $data['classification'],
            $data['effective_date'],
            $data['effective_date']
        ]);

        if ($checkStmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(["error" => "Active configuration already exists for this classification on the selected date"]);
            return;
        }

        // Insert new configuration
        $stmt = $pdo->prepare("
            INSERT INTO land_configurations (
                classification, market_value, assessment_level, description,
                effective_date, expiration_date, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        // Set default values for optional fields
        $description = $data['description'] ?? null;
        $expiration_date = !empty($data['expiration_date']) ? $data['expiration_date'] : null;
        $status = $data['status'] ?? 'active';

        $success = $stmt->execute([
            trim($data['classification']),
            floatval($data['market_value']),
            floatval($data['assessment_level']),
            $description,
            $data['effective_date'],
            $expiration_date,
            $status
        ]);

        if ($success) {
            $newId = $pdo->lastInsertId();
            
            // Fetch the newly created record to return
            $fetchStmt = $pdo->prepare("SELECT * FROM land_configurations WHERE id = ?");
            $fetchStmt->execute([$newId]);
            $newConfig = $fetchStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "success" => true,
                "message" => "Land configuration created successfully",
                "id" => $newId,
                "data" => $newConfig
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to create land configuration", "debug" => $stmt->errorInfo()]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create land configuration: " . $e->getMessage()]);
    }
}

function updateConfiguration($pdo, $id) {
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing or invalid ID parameter"]);
        return;
    }

    // Get input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }

    try {
        // Check if record exists
        $checkStmt = $pdo->prepare("SELECT id FROM land_configurations WHERE id = ?");
        $checkStmt->execute([$id]);
        
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Land configuration not found"]);
            return;
        }

        // Check for overlapping active configurations excluding current record
        if (isset($data['classification']) && isset($data['effective_date'])) {
            $checkOverlap = $pdo->prepare("
                SELECT id FROM land_configurations 
                WHERE classification = ? AND status = 'active'
                AND effective_date <= ? 
                AND (expiration_date IS NULL OR expiration_date >= ?)
                AND id != ?
            ");
            $checkOverlap->execute([
                $data['classification'],
                $data['effective_date'],
                $data['effective_date'],
                $id
            ]);

            if ($checkOverlap->rowCount() > 0) {
                http_response_code(400);
                echo json_encode(["error" => "Active configuration already exists for this classification on the selected date"]);
                return;
            }
        }

        // Build update query dynamically
        $fields = [];
        $values = [];
        $allowedFields = ['classification', 'market_value', 'assessment_level', 'description', 'effective_date', 'expiration_date', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                if (in_array($field, ['market_value', 'assessment_level'])) {
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
        $sql = "UPDATE land_configurations SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($values);

        if ($success) {
            // Fetch updated record
            $fetchStmt = $pdo->prepare("SELECT * FROM land_configurations WHERE id = ?");
            $fetchStmt->execute([$id]);
            $updatedConfig = $fetchStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "success" => true,
                "message" => "Land configuration updated successfully",
                "data" => $updatedConfig
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to update land configuration"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update land configuration: " . $e->getMessage()]);
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
    $allowedFields = ['status', 'expiration_date', 'market_value', 'assessment_level', 'description', 'classification', 'effective_date'];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            
            // Handle special cases
            if (in_array($field, ['market_value', 'assessment_level'])) {
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
    $sql = "UPDATE land_configurations SET " . implode(', ', $fields) . " WHERE id = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            // Fetch updated record
            $fetchStmt = $pdo->prepare("SELECT * FROM land_configurations WHERE id = ?");
            $fetchStmt->execute([$id]);
            $updatedConfig = $fetchStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "success" => true,
                "message" => "Land configuration updated successfully",
                "data" => $updatedConfig
            ]);
        } else {
            // Check if record exists
            $checkExists = $pdo->prepare("SELECT id FROM land_configurations WHERE id = ?");
            $checkExists->execute([$id]);
            
            if ($checkExists->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "Land configuration not found"]);
            } else {
                echo json_encode([
                    "success" => true,
                    "message" => "No changes made to land configuration",
                    "data" => null
                ]);
            }
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update land configuration: " . $e->getMessage()]);
    }
}

function deleteConfiguration($pdo, $id) {
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing ID parameter"]);
        return;
    }

    try {
        // First, check if the record exists
        $checkStmt = $pdo->prepare("SELECT * FROM land_configurations WHERE id = ?");
        $checkStmt->execute([$id]);
        $record = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            http_response_code(404);
            echo json_encode(["error" => "Land configuration not found"]);
            return;
        }

        // Delete the record
        $stmt = $pdo->prepare("DELETE FROM land_configurations WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Land configuration deleted successfully",
                "deleted_id" => $id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to delete land configuration"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete land configuration: " . $e->getMessage()]);
    }
}
?>