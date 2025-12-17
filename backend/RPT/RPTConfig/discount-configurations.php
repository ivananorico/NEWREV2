<?php
// ================================================
// DISCOUNT CONFIGURATIONS API
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
        // Check if table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'discount_configurations'");
        if ($checkTable->rowCount() === 0) {
            // Table doesn't exist, return empty array
            echo json_encode([]);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM discount_configurations
            ORDER BY 
                CASE WHEN status = 'active' THEN 1 ELSE 2 END,
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
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid ID parameter"]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM discount_configurations WHERE id = ?");
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
    $requiredFields = ['discount_percent', 'effective_date'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }

    // Validate numeric value
    if (!is_numeric($data['discount_percent'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid numeric value for discount_percent"]);
        return;
    }

    // Validate discount percent range
    $discountPercent = floatval($data['discount_percent']);
    if ($discountPercent < 0 || $discountPercent > 100) {
        http_response_code(400);
        echo json_encode(["error" => "Discount percent must be between 0 and 100"]);
        return;
    }

    // Validate dates
    $effectiveDate = $data['effective_date'];
    if (!strtotime($effectiveDate)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid effective_date format"]);
        return;
    }

    // Validate expiration date if provided
    if (!empty($data['expiration_date'])) {
        $expirationDate = $data['expiration_date'];
        if (!strtotime($expirationDate)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid expiration_date format"]);
            return;
        }
        
        // Check if expiration date is after effective date
        if (strtotime($expirationDate) <= strtotime($effectiveDate)) {
            http_response_code(400);
            echo json_encode(["error" => "Expiration date must be after effective date"]);
            return;
        }
    }

    // Validate status
    $status = $data['status'] ?? 'active';
    if (!in_array($status, ['active', 'expired'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid status value. Must be 'active' or 'expired'"]);
        return;
    }

    try {
        // Check if table exists, create it if not
        $checkTable = $pdo->query("SHOW TABLES LIKE 'discount_configurations'");
        if ($checkTable->rowCount() === 0) {
            // Create table if it doesn't exist
            $createTable = $pdo->exec("
                CREATE TABLE discount_configurations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    discount_percent DECIMAL(5,2) NOT NULL,
                    effective_date DATE NOT NULL,
                    expiration_date DATE NULL,
                    status ENUM('active', 'expired') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        }

        // Check for overlapping date ranges for active configurations
        if ($status === 'active') {
            $checkOverlap = $pdo->prepare("
                SELECT id FROM discount_configurations 
                WHERE status = 'active' 
                AND (
                    (effective_date <= ? AND (expiration_date IS NULL OR expiration_date >= ?))
                    OR (effective_date <= ? AND expiration_date IS NULL)
                )
            ");
            
            $checkOverlap->execute([$effectiveDate, $effectiveDate, $effectiveDate]);
            $overlappingConfigs = $checkOverlap->fetchAll();
            
            if (count($overlappingConfigs) > 0) {
                http_response_code(400);
                echo json_encode(["error" => "Another active discount configuration already exists for this date range"]);
                return;
            }
        }

        // Insert new configuration
        $stmt = $pdo->prepare("
            INSERT INTO discount_configurations (
                discount_percent, effective_date, expiration_date, 
                status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, NOW(), NOW())
        ");

        $expiration_date = !empty($data['expiration_date']) ? $data['expiration_date'] : null;

        $success = $stmt->execute([
            $discountPercent,
            $effectiveDate,
            $expiration_date,
            $status
        ]);

        if ($success) {
            $newId = $pdo->lastInsertId();
            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Discount configuration created successfully",
                "id" => $newId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to create discount configuration"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create discount configuration: " . $e->getMessage()]);
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
        // First check if the configuration exists
        $checkStmt = $pdo->prepare("SELECT id FROM discount_configurations WHERE id = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Discount configuration not found"]);
            return;
        }

        // Validate data if provided
        if (isset($data['discount_percent'])) {
            if (!is_numeric($data['discount_percent'])) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid numeric value for discount_percent"]);
                return;
            }
            $discountPercent = floatval($data['discount_percent']);
            if ($discountPercent < 0 || $discountPercent > 100) {
                http_response_code(400);
                echo json_encode(["error" => "Discount percent must be between 0 and 100"]);
                return;
            }
        }

        // Validate dates if provided
        if (isset($data['effective_date'])) {
            if (!strtotime($data['effective_date'])) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid effective_date format"]);
                return;
            }
        }

        if (isset($data['expiration_date']) && !empty($data['expiration_date'])) {
            if (!strtotime($data['expiration_date'])) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid expiration_date format"]);
                return;
            }
        }

        // Validate status if provided
        if (isset($data['status']) && !in_array($data['status'], ['active', 'expired'])) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid status value. Must be 'active' or 'expired'"]);
            return;
        }

        $fields = [];
        $values = [];
        $allowedFields = ['discount_percent', 'effective_date', 'expiration_date', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                if ($field === 'discount_percent') {
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
        $sql = "UPDATE discount_configurations SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Discount configuration updated successfully"
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "message" => "No changes were made to the discount configuration"
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update discount configuration: " . $e->getMessage()]);
    }
}

function patchConfiguration($pdo, $id) {
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
        // First check if the configuration exists
        $checkStmt = $pdo->prepare("SELECT id FROM discount_configurations WHERE id = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Discount configuration not found"]);
            return;
        }

        // Validate data if provided
        if (isset($data['discount_percent'])) {
            if (!is_numeric($data['discount_percent'])) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid numeric value for discount_percent"]);
                return;
            }
            $discountPercent = floatval($data['discount_percent']);
            if ($discountPercent < 0 || $discountPercent > 100) {
                http_response_code(400);
                echo json_encode(["error" => "Discount percent must be between 0 and 100"]);
                return;
            }
        }

        // Validate status if provided
        if (isset($data['status']) && !in_array($data['status'], ['active', 'expired'])) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid status value. Must be 'active' or 'expired'"]);
            return;
        }

        $fields = [];
        $values = [];
        $allowedFields = ['status', 'expiration_date', 'discount_percent', 'effective_date'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                
                if ($field === 'discount_percent') {
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
        $sql = "UPDATE discount_configurations SET " . implode(', ', $fields) . " WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Discount configuration updated successfully"
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "message" => "No changes were made to the discount configuration"
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update discount configuration: " . $e->getMessage()]);
    }
}

function deleteConfiguration($pdo, $id) {
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing or invalid ID parameter"]);
        return;
    }

    try {
        // First check if the configuration exists
        $checkStmt = $pdo->prepare("SELECT id FROM discount_configurations WHERE id = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Discount configuration not found"]);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM discount_configurations WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Discount configuration deleted successfully"
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Discount configuration not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete discount configuration: " . $e->getMessage()]);
    }
}