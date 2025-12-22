<?php
// Enable CORS with proper headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, expires, Cache-Control, Pragma");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once '../../../db/Business/business_db.php';

// Helper function for consistent responses
function jsonResponse($success, $message = '', $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_PRETTY_PRINT);
    exit();
}

// Get current date from query or use today
$currentDate = isset($_GET['current_date']) ? $_GET['current_date'] : date('Y-m-d');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        case 'DELETE':
            handleDelete();
            break;
        case 'PATCH':
            handlePatch();
            break;
        default:
            jsonResponse(false, 'Method not allowed', null, 405);
    }
    
} catch (Exception $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}

function handleGet() {
    global $pdo, $currentDate;
    
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if ($id) {
        // Get single configuration
        $stmt = $pdo->prepare("SELECT * FROM gross_sales_tax_config WHERE id = ?");
        $stmt->execute([$id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            jsonResponse(true, 'Configuration retrieved', $config);
        } else {
            jsonResponse(false, 'Configuration not found', null, 404);
        }
    } else {
        // FIXED: Get all configurations - handle NULL, empty, and '0000-00-00' dates
        $stmt = $pdo->prepare("
            SELECT * FROM gross_sales_tax_config 
            WHERE effective_date <= ? 
            AND (
                expiration_date IS NULL 
                OR expiration_date = '' 
                OR expiration_date = '0000-00-00' 
                OR expiration_date = '0000-00-00 00:00:00'
                OR expiration_date >= ?
            )
            ORDER BY business_type, effective_date DESC
        ");
        $stmt->execute([$currentDate, $currentDate]);
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log the count
        error_log("Fetched " . count($configs) . " business configurations for date: $currentDate");
        
        jsonResponse(true, 'Business configurations retrieved', $configs);
    }
}

function handlePost() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(false, 'Invalid JSON data', null, 400);
    }
    
    // Validate required fields
    $required = ['business_type', 'tax_percent', 'effective_date'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            jsonResponse(false, "Missing required field: $field", null, 400);
        }
    }
    
    // Convert empty expiration_date to NULL
    if (isset($input['expiration_date']) && (empty($input['expiration_date']) || $input['expiration_date'] == '0000-00-00')) {
        $input['expiration_date'] = null;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO gross_sales_tax_config 
            (business_type, tax_percent, effective_date, expiration_date, remarks, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $input['business_type'],
            $input['tax_percent'],
            $input['effective_date'],
            $input['expiration_date'] ?? null,
            $input['remarks'] ?? null
        ]);
        
        $id = $pdo->lastInsertId();
        jsonResponse(true, 'Business configuration created successfully', ['id' => $id]);
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    }
}

function handlePut() {
    global $pdo;
    
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$id) {
        jsonResponse(false, 'Configuration ID is required', null, 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(false, 'Invalid JSON data', null, 400);
    }
    
    // Convert empty expiration_date to NULL
    if (isset($input['expiration_date']) && (empty($input['expiration_date']) || $input['expiration_date'] == '0000-00-00')) {
        $input['expiration_date'] = null;
    }
    
    try {
        // Build dynamic update query
        $fields = [];
        $values = [];
        
        $allowedFields = ['business_type', 'tax_percent', 'effective_date', 'expiration_date', 'remarks'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $fields[] = "$field = ?";
                $values[] = $input[$field];
            }
        }
        
        if (empty($fields)) {
            jsonResponse(false, 'No fields to update', null, 400);
        }
        
        $fields[] = "updated_at = NOW()";
        $values[] = $id;
        
        $sql = "UPDATE gross_sales_tax_config SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        jsonResponse(true, 'Business configuration updated successfully');
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete() {
    global $pdo;
    
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$id) {
        jsonResponse(false, 'Configuration ID is required', null, 400);
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM gross_sales_tax_config WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse(true, 'Business configuration deleted successfully');
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    }
}

function handlePatch() {
    global $pdo;
    
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$id) {
        jsonResponse(false, 'Configuration ID is required', null, 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(false, 'Invalid JSON data', null, 400);
    }
    
    $expirationDate = $input['expiration_date'] ?? date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("
            UPDATE gross_sales_tax_config 
            SET expiration_date = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$expirationDate, $id]);
        
        jsonResponse(true, 'Business configuration expired successfully');
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    }
}