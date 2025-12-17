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
        getBuildingAssessmentLevels();
        break;
    case 'POST':
        createBuildingAssessmentLevel();
        break;
    case 'PUT':
        updateBuildingAssessmentLevel();
        break;
    case 'PATCH':
        patchBuildingAssessmentLevel();
        break;
    case 'DELETE':
        deleteBuildingAssessmentLevel();
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}

function getBuildingAssessmentLevels() {
    global $pdo;
    
    // Get current date or use provided date
    $currentDate = isset($_GET['current_date']) ? $_GET['current_date'] : date('Y-m-d');
    
    error_log("Fetching building assessment levels for date: " . $currentDate);
    
    try {
        // Show all active configurations
        $stmt = $pdo->prepare("
            SELECT * FROM building_assessment_levels 
            WHERE status = 'active'
            ORDER BY classification, min_assessed_value ASC
        ");
        $stmt->execute();
        $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Found " . count($levels) . " building assessment levels");
        
        echo json_encode($levels);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function createBuildingAssessmentLevel() {
    global $pdo;
    
    // Get JSON input
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    
    error_log("Received data for building assessment: " . print_r($input, true));
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON error: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }
    
    // Validate required fields
    $requiredFields = ['classification', 'min_assessed_value', 'max_assessed_value', 'level_percent', 'effective_date'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            error_log("Missing field: " . $field);
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }
    
    try {
        // Check for overlapping ranges
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM building_assessment_levels 
            WHERE classification = ? 
            AND status = 'active'
            AND (
                (min_assessed_value <= ? AND max_assessed_value >= ?) OR
                (min_assessed_value >= ? AND min_assessed_value <= ?) OR
                (max_assessed_value >= ? AND max_assessed_value <= ?)
            )
        ");
        $checkStmt->execute([
            $input['classification'],
            $input['max_assessed_value'],
            $input['min_assessed_value'],
            $input['min_assessed_value'],
            $input['max_assessed_value'],
            $input['min_assessed_value'],
            $input['max_assessed_value']
        ]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            http_response_code(400);
            echo json_encode(["error" => "Overlapping value range for this classification already exists"]);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO building_assessment_levels (
                classification, min_assessed_value, max_assessed_value, level_percent, 
                effective_date, expiration_date, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $input['classification'],
            $input['min_assessed_value'],
            $input['max_assessed_value'],
            $input['level_percent'],
            $input['effective_date'],
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $input['status'] ?? 'active'
        ]);
        
        if ($result) {
            $newId = $pdo->lastInsertId();
            error_log("Successfully created building assessment level with ID: " . $newId);
            echo json_encode([
                "message" => "Building assessment level created successfully", 
                "id" => $newId
            ]);
        } else {
            error_log("Insert failed");
            http_response_code(500);
            echo json_encode(["error" => "Failed to create building assessment level"]);
        }
    } catch (PDOException $e) {
        error_log("Database error on create: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "Failed to create building assessment level: " . $e->getMessage()]);
    }
}

function updateBuildingAssessmentLevel() {
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
        // Check for overlapping ranges (excluding current record)
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM building_assessment_levels 
            WHERE classification = ? 
            AND id != ?
            AND status = 'active'
            AND (
                (min_assessed_value <= ? AND max_assessed_value >= ?) OR
                (min_assessed_value >= ? AND min_assessed_value <= ?) OR
                (max_assessed_value >= ? AND max_assessed_value <= ?)
            )
        ");
        $checkStmt->execute([
            $input['classification'],
            $id,
            $input['max_assessed_value'],
            $input['min_assessed_value'],
            $input['min_assessed_value'],
            $input['max_assessed_value'],
            $input['min_assessed_value'],
            $input['max_assessed_value']
        ]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            http_response_code(400);
            echo json_encode(["error" => "Overlapping value range for this classification already exists"]);
            return;
        }
        
        $stmt = $pdo->prepare("
            UPDATE building_assessment_levels SET 
                classification = ?, min_assessed_value = ?, max_assessed_value = ?, 
                level_percent = ?, effective_date = ?, expiration_date = ?, status = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $input['classification'],
            $input['min_assessed_value'],
            $input['max_assessed_value'],
            $input['level_percent'],
            $input['effective_date'],
            !empty($input['expiration_date']) ? $input['expiration_date'] : null,
            $input['status'] ?? 'active',
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Building assessment level updated successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Building assessment level not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update building assessment level: " . $e->getMessage()]);
    }
}

function patchBuildingAssessmentLevel() {
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
    
    $allowedFields = ['status', 'expiration_date', 'level_percent', 'min_assessed_value', 'max_assessed_value'];
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
    $sql = "UPDATE building_assessment_levels SET " . implode(', ', $fields) . " WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Building assessment level updated successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Building assessment level not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update building assessment level: " . $e->getMessage()]);
    }
}

function deleteBuildingAssessmentLevel() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing ID parameter"]);
        return;
    }
    
    try {
        // First check if this assessment level is being used
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM building_properties 
            WHERE assessment_level = (SELECT level_percent FROM building_assessment_levels WHERE id = ?)
        ");
        $checkStmt->execute([$id]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            http_response_code(400);
            echo json_encode(["error" => "Cannot delete: This assessment level is being used in building properties"]);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM building_assessment_levels WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["message" => "Building assessment level deleted successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Building assessment level not found"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to delete building assessment level: " . $e->getMessage()]);
    }
}
?>