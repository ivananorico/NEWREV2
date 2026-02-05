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

function deleteFund($fundId) {
    $pdo = getTreasuryDB();
    
    try {
        $pdo->beginTransaction();
        
        // Get fund balance to return to bank
        $stmt = $pdo->prepare("SELECT current_balance FROM funds WHERE id = ?");
        $stmt->execute([$fundId]);
        $fund = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fund) {
            throw new Exception("Fund not found");
        }
        
        // First delete allocations for this fund
        $stmt = $pdo->prepare("DELETE FROM allocations WHERE fund_id = ?");
        $stmt->execute([$fundId]);
        
        // Then delete the fund
        $stmt = $pdo->prepare("DELETE FROM funds WHERE id = ?");
        $stmt->execute([$fundId]);
        
        // Update bank account (return money to bank)
        $stmt = $pdo->prepare("
            UPDATE bank_accounts 
            SET current_balance = current_balance + ?, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = 1
        ");
        $stmt->execute([$fund['current_balance']]);
        
        $pdo->commit();
        
        return [
            'status' => 'success',
            'message' => 'Fund and its allocations deleted successfully',
            'data' => [
                'fund_id' => $fundId,
                'balance_returned' => $fund['current_balance']
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
        echo json_encode(['status' => 'error', 'message' => 'Fund ID is required']);
        exit;
    }
    
    $result = deleteFund($input['id']);
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Use POST']);
}
?>