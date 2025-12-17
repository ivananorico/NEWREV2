<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../../../db/RPT/rpt_db.php";

// GET - Fetch all property configurations
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM property_configurations 
            WHERE status = 'active' 
            ORDER BY classification, material_type
        ");
        $stmt->execute();
        $configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'configurations' => $configurations
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

// POST - Create new property configuration
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // CORRECT FIELDS (based on your database table)
        $stmt = $pdo->prepare("
            INSERT INTO property_configurations 
            (classification, material_type, unit_cost, depreciation_rate, 
             min_value, max_value, effective_date, expiration_date, status)
            VALUES 
            (:classification, :material_type, :unit_cost, :depreciation_rate,
             :min_value, :max_value, :effective_date, :expiration_date, :status)
        ");
        
        $stmt->execute([
            ':classification' => $data['classification'],
            ':material_type' => $data['material_type'],
            ':unit_cost' => $data['unit_cost'],
            ':depreciation_rate' => $data['depreciation_rate'],
            ':min_value' => $data['min_value'],
            ':max_value' => $data['max_value'],
            ':effective_date' => $data['effective_date'],
            ':expiration_date' => $data['expiration_date'] ?? null,
            ':status' => $data['status'] ?? 'active'
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Property configuration created successfully',
            'id' => $pdo->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to create property configuration: ' . $e->getMessage()
        ]);
    }
}

// PUT - Update property configuration
elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $_GET['id'] ?? $data['id'] ?? null;
        
        if (!$id) {
            throw new Exception('ID is required');
        }
        
        // CORRECT FIELDS (based on your database table)
        $stmt = $pdo->prepare("
            UPDATE property_configurations SET
            classification = :classification,
            material_type = :material_type,
            unit_cost = :unit_cost,
            depreciation_rate = :depreciation_rate,
            min_value = :min_value,
            max_value = :max_value,
            effective_date = :effective_date,
            expiration_date = :expiration_date,
            status = :status,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':id' => $id,
            ':classification' => $data['classification'],
            ':material_type' => $data['material_type'],
            ':unit_cost' => $data['unit_cost'],
            ':depreciation_rate' => $data['depreciation_rate'],
            ':min_value' => $data['min_value'],
            ':max_value' => $data['max_value'],
            ':effective_date' => $data['effective_date'],
            ':expiration_date' => $data['expiration_date'] ?? null,
            ':status' => $data['status'] ?? 'active'
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Property configuration updated successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update property configuration: ' . $e->getMessage()
        ]);
    }
}

// DELETE - Delete property configuration
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            throw new Exception('ID is required');
        }
        
        $stmt = $pdo->prepare("DELETE FROM property_configurations WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Property configuration deleted successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete property configuration: ' . $e->getMessage()
        ]);
    }
}

// PATCH - Update status or expire
elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $_GET['id'] ?? $data['id'] ?? null;
        
        if (!$id) {
            throw new Exception('ID is required');
        }
        
        $updates = [];
        $params = [':id' => $id];
        
        if (isset($data['status'])) {
            $updates[] = 'status = :status';
            $params[':status'] = $data['status'];
        }
        
        if (isset($data['expiration_date'])) {
            $updates[] = 'expiration_date = :expiration_date';
            $params[':expiration_date'] = $data['expiration_date'];
        }
        
        if (empty($updates)) {
            throw new Exception('No fields to update');
        }
        
        $updates[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = "UPDATE property_configurations SET " . implode(', ', $updates) . " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Property configuration updated successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update property configuration: ' . $e->getMessage()
        ]);
    }
}
?>