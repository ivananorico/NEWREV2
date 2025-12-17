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
        getDiscountConfigurations();
        break;
    case 'POST':
        createDiscountConfiguration();
        break;
    case 'PUT':
        updateDiscountConfiguration();
        break;
    case 'PATCH':
        patchDiscountConfiguration();
        break;
    case 'DELETE':
        deleteDiscountConfiguration();
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}

function getDiscountConfigurations() {
    global $pdo;
    
    $currentDate = isset($_GET['current_date']) ? $_GET['current_date'] : date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM discount_configurations 
            WHERE effective_date <= ? 
            AND (expiration_date IS NULL OR expiration_date >= ? OR status = 'expired')
            ORDER BY status ASC, effective_date DESC
        ");
        $stmt->execute([$currentDate, $currentDate]);
        $configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($configurations);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function createDiscountConfiguration() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data"]);
        return;
    }
    
    // Validate required fields
    if (!isset($input['discount_percent']) || !isset($input['effective_date'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields: discount_percent and effective_date are required"]);
        return;
    }
    
    // Check for overlapping active configurations
    try {
        $checkStmt = $pdo->prepare("
            SELECT id FROM discount_configurations 
            WHERE status = 'active'
            AND effective_date <= ? 
            AND (expiration_date IS NULL OR expiration_date >= ?)
        ");
        $checkStmt->execute([
            $input['effective_date'],
            $input['effective_date']
        ]);
        
        if ($checkStmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(["error" => "Active discount configuration already exists for the selected date"]);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO discount_configurations (
                discount_percent, effective_date, expiration_date, status
            ) VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['discount_percent'],
            $input['effective_date'],
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $input['status'] ?? 'active'
        ]);
        
        echo json_encode([
            "message" => "Discount configuration created successfully", 
            "id" => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create discount configuration: " . $e->getMessage()]);
    }
}

function updateDiscountConfiguration() {
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
    
    // Check for overlapping active configurations (excluding current record)
    try {
        $checkStmt = $pdo->prepare("
            SELECT id FROM discount_configurations 
            WHERE status = 'active'
            AND effective_date <= ? 
            AND (expiration_date IS NULL OR expiration_date >= ?)
            AND id != ?
        ");
        $checkStmt->execute([
            $input['effective_date'],
            $input['effective_date'],
            $id
        ]);
        
        if ($checkStmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(["error" => "Active discount configuration already exists for the selected date"]);
            return;
        }
        
        $stmt = $pdo->prepare("
            UPDATE discount_configurations SET 
                discount_percent = ?, effective_date = ?, expiration_date = ?, status = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $input['discount_percent'],
            $input['effective_date'],
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $input['status'] ?? 'active',
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Discount configuration updated successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Discount configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update discount configuration: " . $e->getMessage()]);
    }
}

function patchDiscountConfiguration() {
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
    
    // Build dynamic update query
    $fields = [];
    $values = [];
    
    $allowedFields = ['status', 'expiration_date', 'discount_percent'];
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
    $sql = "UPDATE discount_configurations SET " . implode(', ', $fields) . " WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Discount configuration updated successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Discount configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update discount configuration: " . $e->getMessage()]);
    }
}

function deleteDiscountConfiguration() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing ID parameter"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM discount_configurations WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Discount configuration deleted successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Discount configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete discount configuration: " . $e->getMessage()]);
    }
}
?>