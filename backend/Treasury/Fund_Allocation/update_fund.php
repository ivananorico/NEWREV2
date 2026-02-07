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
require_once __DIR__ . '/../../../db/Treasury/treasury_db.php';

function updateFund($data) {
    $pdo = getTreasuryDB();
    
    try {
        $pdo->beginTransaction();
        
        // Get current fund data
        $stmt = $pdo->prepare("SELECT initial_balance, current_balance FROM funds WHERE id = ?");
        $stmt->execute([$data['id']]);
        $currentFund = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentFund) {
            throw new Exception("Fund not found");
        }
        
        // Update the fund
        $stmt = $pdo->prepare("
            UPDATE funds 
            SET fund_name = ?, description = ?, initial_balance = ?, 
                current_balance = current_balance + (? - ?), fiscal_year = ?, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['fund_name'],
            $data['description'],
            $data['initial_balance'],
            $data['initial_balance'], // new balance
            $currentFund['initial_balance'], // old balance
            $data['fiscal_year'],
            $data['id']
        ]);
        
        $pdo->commit();
        
        return [
            'status' => 'success',
            'message' => 'Fund updated successfully',
            'data' => [
                'id' => $data['id'],
                'fund_name' => $data['fund_name']
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
    
    $result = updateFund($input);
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Use POST']);
}
?>