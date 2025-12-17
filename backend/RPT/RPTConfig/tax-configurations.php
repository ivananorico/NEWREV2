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
        // Get all configurations and calculate status based on dates
        $stmt = $pdo->prepare("
            SELECT *, 
                CASE 
                    WHEN expiration_date IS NOT NULL AND expiration_date < ? THEN 'expired'
                    ELSE 'active'
                END as status
            FROM rpt_tax_config 
            WHERE effective_date <= ?
            ORDER BY status ASC, effective_date DESC, tax_name
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
    
    // Validate required fields
    if (!isset($input['tax_name']) || !isset($input['tax_percent']) || !isset($input['effective_date'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields: tax_name, tax_percent, effective_date"]);
        return;
    }
    
    // Validate tax name - only allow Basic Tax or SEF Tax
    if (!in_array($input['tax_name'], ['Basic Tax', 'SEF Tax'])) {
        http_response_code(400);
        echo json_encode(["error" => "Tax name must be either 'Basic Tax' or 'SEF Tax'"]);
        return;
    }
    
    // Validate tax percentage
    if (!is_numeric($input['tax_percent']) || $input['tax_percent'] < 0 || $input['tax_percent'] > 100) {
        http_response_code(400);
        echo json_encode(["error" => "Tax percentage must be a number between 0 and 100"]);
        return;
    }
    
    try {
        // Check if active configuration already exists for this tax type
        $checkStmt = $pdo->prepare("
            SELECT id FROM rpt_tax_config 
            WHERE tax_name = ? 
            AND status = 'active'
        ");
        $checkStmt->execute([$input['tax_name']]);
        
        if ($checkStmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(["error" => "An active configuration already exists for " . $input['tax_name']]);
            return;
        }
        
        // Calculate status based on expiration date
        $status = 'active';
        if (!empty($input['expiration_date']) && $input['expiration_date'] < date('Y-m-d')) {
            $status = 'expired';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO rpt_tax_config (
                tax_name, tax_percent, effective_date, expiration_date, status
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['tax_name'],
            $input['tax_percent'],
            $input['effective_date'],
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $status
        ]);
        
        echo json_encode([
            "message" => "Tax configuration created successfully", 
            "id" => $pdo->lastInsertId()
        ]);
        
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
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data"]);
        return;
    }
    
    // Validate tax name if provided
    if (isset($input['tax_name']) && !in_array($input['tax_name'], ['Basic Tax', 'SEF Tax'])) {
        http_response_code(400);
        echo json_encode(["error" => "Tax name must be either 'Basic Tax' or 'SEF Tax'"]);
        return;
    }
    
    try {
        // Check if updating tax name would create duplicate active configuration
        if (isset($input['tax_name'])) {
            $checkStmt = $pdo->prepare("
                SELECT id FROM rpt_tax_config 
                WHERE tax_name = ? 
                AND status = 'active'
                AND id != ?
            ");
            $checkStmt->execute([$input['tax_name'], $id]);
            
            if ($checkStmt->rowCount() > 0) {
                http_response_code(400);
                echo json_encode(["error" => "An active configuration already exists for " . $input['tax_name']]);
                return;
            }
        }
        
        // Build dynamic update query
        $fields = [];
        $values = [];
        
        if (isset($input['tax_name'])) {
            $fields[] = "tax_name = ?";
            $values[] = $input['tax_name'];
        }
        if (isset($input['tax_percent'])) {
            $fields[] = "tax_percent = ?";
            $values[] = $input['tax_percent'];
        }
        if (isset($input['effective_date'])) {
            $fields[] = "effective_date = ?";
            $values[] = $input['effective_date'];
        }
        if (isset($input['expiration_date'])) {
            $fields[] = "expiration_date = ?";
            $values[] = !empty($input['expiration_date']) ? $input['expiration_date'] : null;
        }
        if (isset($input['status'])) {
            $fields[] = "status = ?";
            $values[] = $input['status'];
        }
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(["error" => "No fields to update"]);
            return;
        }
        
        $fields[] = "updated_at = NOW()";
        $values[] = $id;
        
        $sql = "UPDATE rpt_tax_config SET " . implode(', ', $fields) . " WHERE id = ?";
        
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
    
    try {
        // Handle status expiration
        if (isset($input['status']) && $input['status'] === 'expired') {
            $stmt = $pdo->prepare("
                UPDATE rpt_tax_config 
                SET status = 'expired', expiration_date = CURDATE(), updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
        } else {
            // Handle other patch operations
            $fields = [];
            $values = [];
            
            $allowedFields = ['expiration_date', 'tax_percent', 'status'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $input[$field];
                }
            }
            
            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(["error" => "No valid fields to update"]);
                return;
            }
            
            $values[] = $id;
            $sql = "UPDATE rpt_tax_config SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
        }
        
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
        $stmt = $pdo->prepare("DELETE FROM rpt_tax_config WHERE id = ?");
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