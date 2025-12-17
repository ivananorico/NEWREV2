<?php
// Enable CORS with proper headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once '../../../db/Business/business_db.php';

$method = $_SERVER['REQUEST_METHOD'];
$currentDate = isset($_GET['current_date']) ? $_GET['current_date'] : date('Y-m-d');

try {
    if ($method === 'GET') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM regulatory_fee_config WHERE id = ?");
            $stmt->execute([$id]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($config) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Regulatory configuration retrieved',
                    'data' => $config
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Configuration not found',
                    'data' => null
                ]);
            }
        } else {
            // FIXED: Handle '0000-00-00' dates
            $stmt = $pdo->prepare("
                SELECT * FROM regulatory_fee_config 
                WHERE effective_date <= ? 
                AND (
                    expiration_date IS NULL 
                    OR expiration_date = '' 
                    OR expiration_date = '0000-00-00' 
                    OR expiration_date = '0000-00-00 00:00:00'
                    OR expiration_date >= ?
                )
                ORDER BY fee_name, effective_date DESC
            ");
            $stmt->execute([$currentDate, $currentDate]);
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug log
            error_log("Fetched " . count($configs) . " regulatory fee configurations for date: $currentDate");
            
            echo json_encode([
                'success' => true,
                'message' => 'Regulatory fee configurations retrieved',
                'data' => $configs
            ]);
        }
    }
    
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON data',
                'data' => null
            ]);
            exit();
        }
        
        $required = ['fee_name', 'amount', 'effective_date'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "Missing required field: $field",
                    'data' => null
                ]);
                exit();
            }
        }
        
        // Convert empty expiration_date to NULL
        if (isset($input['expiration_date']) && (empty($input['expiration_date']) || $input['expiration_date'] == '0000-00-00')) {
            $input['expiration_date'] = null;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO regulatory_fee_config 
            (fee_name, amount, effective_date, expiration_date, remarks) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['fee_name'],
            $input['amount'],
            $input['effective_date'],
            $input['expiration_date'] ?? null,
            $input['remarks'] ?? null
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Regulatory configuration created successfully',
            'data' => ['id' => $pdo->lastInsertId()]
        ]);
    }
    
    elseif ($method === 'PUT') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Configuration ID is required',
                'data' => null
            ]);
            exit();
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON data',
                'data' => null
            ]);
            exit();
        }
        
        // Convert empty expiration_date to NULL
        if (isset($input['expiration_date']) && (empty($input['expiration_date']) || $input['expiration_date'] == '0000-00-00')) {
            $input['expiration_date'] = null;
        }
        
        $fields = [];
        $values = [];
        
        if (isset($input['fee_name'])) {
            $fields[] = "fee_name = ?";
            $values[] = $input['fee_name'];
        }
        
        if (isset($input['amount'])) {
            $fields[] = "amount = ?";
            $values[] = $input['amount'];
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
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No fields to update',
                'data' => null
            ]);
            exit();
        }
        
        $values[] = $id;
        
        $sql = "UPDATE regulatory_fee_config SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        echo json_encode([
            'success' => true,
            'message' => 'Regulatory configuration updated successfully',
            'data' => null
        ]);
    }
    
    elseif ($method === 'DELETE') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Configuration ID is required',
                'data' => null
            ]);
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM regulatory_fee_config WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Regulatory configuration deleted successfully',
            'data' => null
        ]);
    }
    
    elseif ($method === 'PATCH') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Configuration ID is required',
                'data' => null
            ]);
            exit();
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON data',
                'data' => null
            ]);
            exit();
        }
        
        $expirationDate = $input['expiration_date'] ?? date('Y-m-d');
        
        $stmt = $pdo->prepare("
            UPDATE regulatory_fee_config 
            SET expiration_date = ? 
            WHERE id = ?
        ");
        $stmt->execute([$expirationDate, $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Regulatory configuration expired successfully',
            'data' => null
        ]);
    }
    
    else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed',
            'data' => null
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'data' => null
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'data' => null
    ]);
}