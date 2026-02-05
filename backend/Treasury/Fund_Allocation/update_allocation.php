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

function updateAllocation($data) {
    $pdo = getTreasuryDB();
    
    try {
        $pdo->beginTransaction();
        
        // Get current allocation data
        $stmt = $pdo->prepare("SELECT allocated_amount, fund_id FROM allocations WHERE id = ?");
        $stmt->execute([$data['id']]);
        $currentAlloc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentAlloc) {
            throw new Exception("Allocation not found");
        }
        
        // Check if fund exists
        $stmt = $pdo->prepare("SELECT current_balance FROM funds WHERE id = ?");
        $stmt->execute([$data['fund_id']]);
        $fund = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fund) {
            throw new Exception("Selected fund not found");
        }
        
        // Get total allocations for this fund (excluding current one being updated)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(allocated_amount), 0) as total_allocated 
            FROM allocations 
            WHERE fund_id = ? AND id != ?
        ");
        $stmt->execute([$data['fund_id'], $data['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalAllocated = $result['total_allocated'];
        
        // Check if new allocation amount is within fund balance
        $availableBalance = $fund['current_balance'] - $totalAllocated;
        $amountChange = $data['allocated_amount'] - $currentAlloc['allocated_amount'];
        
        if ($amountChange > 0 && $amountChange > $availableBalance) {
            throw new Exception("Insufficient fund balance. Available: " . $availableBalance);
        }
        
        // Update the allocation
        $stmt = $pdo->prepare("
            UPDATE allocations 
            SET fund_id = ?, department = ?, purpose = ?, 
                allocated_amount = ?, allocated_date = ?, 
                created_at = created_at 
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['fund_id'],
            $data['department'],
            $data['purpose'],
            $data['allocated_amount'],
            $data['allocated_date'],
            $data['id']
        ]);
        
        $pdo->commit();
        
        return [
            'status' => 'success',
            'message' => 'Allocation updated successfully',
            'data' => [
                'id' => $data['id'],
                'allocated_amount' => $data['allocated_amount']
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
    
    $result = updateAllocation($input);
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Use POST']);
}
?>