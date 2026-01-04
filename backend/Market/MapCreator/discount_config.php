<?php
// discount_config.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/../../../db/Market/market_db.php';
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method == 'GET') {
        // Check if table exists
        $checkTable = "SHOW TABLES LIKE 'market_discount_config'";
        $tableExists = $pdo->query($checkTable)->rowCount() > 0;
        
        if (!$tableExists) {
            // Create table if it doesn't exist
            $createTable = "CREATE TABLE IF NOT EXISTS `market_discount_config` (
                `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `discount_percent` DECIMAL(5,2) NOT NULL,
                `effective_date` DATE NOT NULL,
                `expiration_date` DATE DEFAULT NULL,
                `remarks` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            $pdo->exec($createTable);
            
            echo json_encode([
                'status' => 'success',
                'configs' => [],
                'message' => 'Table created successfully'
            ]);
            exit();
        }
        
        // Check if remarks column exists, add if missing
        $checkColumn = "SHOW COLUMNS FROM `market_discount_config` LIKE 'remarks'";
        $columnExists = $pdo->query($checkColumn)->rowCount() > 0;
        
        if (!$columnExists) {
            $addColumn = "ALTER TABLE `market_discount_config` 
                         ADD COLUMN `remarks` TEXT DEFAULT NULL AFTER `expiration_date`";
            $pdo->exec($addColumn);
        }
        
        $sql = "SELECT * FROM market_discount_config ORDER BY effective_date DESC";
        $stmt = $pdo->query($sql);
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format dates and ensure remarks is included
        foreach ($configs as &$config) {
            $config['discount_percent'] = floatval($config['discount_percent']);
            $config['remarks'] = $config['remarks'] ?? '';
        }
        
        echo json_encode([
            'status' => 'success',
            'configs' => $configs,
            'count' => count($configs)
        ]);
        
    } elseif ($method == 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (empty($data)) {
            throw new Exception("No data received");
        }
        
        $action = $data['action'] ?? '';
        
        if ($action == 'add') {
            // Validate required fields
            if (!isset($data['discount_percent']) || !isset($data['effective_date'])) {
                throw new Exception("Missing required fields");
            }
            
            $sql = "INSERT INTO market_discount_config (discount_percent, effective_date, expiration_date, remarks) 
                    VALUES (:discount_percent, :effective_date, :expiration_date, :remarks)";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                ':discount_percent' => floatval($data['discount_percent']),
                ':effective_date' => $data['effective_date'],
                ':expiration_date' => !empty($data['expiration_date']) ? $data['expiration_date'] : null,
                ':remarks' => !empty($data['remarks']) ? $data['remarks'] : null
            ]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Discount added successfully',
                'id' => $pdo->lastInsertId()
            ]);
            
        } elseif ($action == 'update') {
            if (!isset($data['id'])) {
                throw new Exception("Missing discount ID");
            }
            
            $sql = "UPDATE market_discount_config 
                    SET discount_percent = :discount_percent, 
                        effective_date = :effective_date, 
                        expiration_date = :expiration_date, 
                        remarks = :remarks
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                ':discount_percent' => floatval($data['discount_percent']),
                ':effective_date' => $data['effective_date'],
                ':expiration_date' => !empty($data['expiration_date']) ? $data['expiration_date'] : null,
                ':remarks' => !empty($data['remarks']) ? $data['remarks'] : null,
                ':id' => intval($data['id'])
            ]);
            
            $affectedRows = $stmt->rowCount();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Discount updated successfully',
                'affected_rows' => $affectedRows
            ]);
            
        } elseif ($action == 'delete') {
            if (!isset($data['id'])) {
                throw new Exception("Missing discount ID");
            }
            
            $sql = "DELETE FROM market_discount_config WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => intval($data['id'])]);
            
            $affectedRows = $stmt->rowCount();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Discount deleted successfully',
                'affected_rows' => $affectedRows
            ]);
            
        } else {
            throw new Exception("Invalid action. Valid actions are: add, update, delete");
        }
        
    } else {
        http_response_code(405);
        throw new Exception("Method not allowed. Only GET and POST are supported");
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>