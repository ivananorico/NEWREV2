<?php
// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('Content-Type: application/json');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include your database config
require_once __DIR__ . '/../../../db/treasury/treasury_db.php';

function deleteAllocation($allocationId) {
    $pdo = getTreasuryDB();
    
    try {
        $pdo->beginTransaction();
        
        // Get allocation data
        $stmt = $pdo->prepare("SELECT allocated_amount, fund_id FROM allocations WHERE id = ?");
        $stmt->execute([$allocationId]);
        $allocation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$allocation) {
            throw new Exception("Allocation not found");
        }
        
        // Delete the allocation
        $stmt = $pdo->prepare("DELETE FROM allocations WHERE id = ?");
        $stmt->execute([$allocationId]);
        
        $pdo->commit();
        
        return [
            'status' => 'success',
            'message' => 'Allocation deleted successfully',
            'data' => [
                'allocation_id' => $allocationId,
                'amount_freed' => $allocation['allocated_amount']
            ]
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

// Main execution
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Allocation ID is required']);
        exit;
    }
    
    $result = deleteAllocation($input['id']);
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Use POST']);
}
?>