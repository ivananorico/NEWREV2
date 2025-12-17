<?php
// Enable CORS with proper headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
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
    ]);
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
        $stmt = $pdo->prepare("SELECT * FROM capital_investment_tax_config WHERE id = ?");
        $stmt->execute([$id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            // FIXED: Send the database field names directly (min_amount, max_amount)
            jsonResponse(true, 'Configuration retrieved', $config);
        } else {
            jsonResponse(false, 'Configuration not found', null, 404);
        }
    } else {
        // Get all configurations
        $stmt = $pdo->prepare("
            SELECT * FROM capital_investment_tax_config 
            WHERE effective_date <= ? 
            ORDER BY min_amount ASC, effective_date DESC
        ");
        $stmt->execute([$currentDate]);
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // FIXED: No mapping needed - send database field names directly
        jsonResponse(true, 'Capital investment configurations retrieved', $configs);
    }
}

function handlePost() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(false, 'Invalid JSON data', null, 400);
    }
    
    // FIXED: Accept both naming conventions
    $minAmount = $input['min_amount'] ?? $input['min_capital'] ?? null;
    $maxAmount = $input['max_amount'] ?? $input['max_capital'] ?? null;
    $taxPercent = $input['tax_percent'] ?? null;
    $effectiveDate = $input['effective_date'] ?? null;
    
    // Validate required fields
    if (!$minAmount || !$maxAmount || !$taxPercent || !$effectiveDate) {
        jsonResponse(false, "Missing required fields", null, 400);
    }
    
    // Validate min < max
    if (floatval($minAmount) >= floatval($maxAmount)) {
        jsonResponse(false, "Minimum capital must be less than maximum capital", null, 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO capital_investment_tax_config 
            (min_amount, max_amount, tax_percent, effective_date, expiration_date, remarks, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $minAmount,
            $maxAmount,
            $taxPercent,
            $effectiveDate,
            $input['expiration_date'] ?? null,
            $input['remarks'] ?? null
        ]);
        
        $id = $pdo->lastInsertId();
        jsonResponse(true, 'Capital investment configuration created successfully', ['id' => $id]);
        
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
    
    try {
        // Build dynamic update query
        $fields = [];
        $values = [];
        
        // FIXED: Handle both naming conventions
        if (isset($input['min_amount']) || isset($input['min_capital'])) {
            $fields[] = "min_amount = ?";
            $values[] = $input['min_amount'] ?? $input['min_capital'];
        }
        
        if (isset($input['max_amount']) || isset($input['max_capital'])) {
            $fields[] = "max_amount = ?";
            $values[] = $input['max_amount'] ?? $input['max_capital'];
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
            $values[] = $input['expiration_date'];
        }
        
        if (isset($input['remarks'])) {
            $fields[] = "remarks = ?";
            $values[] = $input['remarks'];
        }
        
        if (empty($fields)) {
            jsonResponse(false, 'No fields to update', null, 400);
        }
        
        $fields[] = "updated_at = NOW()";
        $values[] = $id;
        
        $sql = "UPDATE capital_investment_tax_config SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        jsonResponse(true, 'Capital investment configuration updated successfully');
        
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
        $stmt = $pdo->prepare("DELETE FROM capital_investment_tax_config WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse(true, 'Capital investment configuration deleted successfully');
        
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
            UPDATE capital_investment_tax_config 
            SET expiration_date = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$expirationDate, $id]);
        
        jsonResponse(true, 'Capital investment configuration expired successfully');
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    }
}
?>