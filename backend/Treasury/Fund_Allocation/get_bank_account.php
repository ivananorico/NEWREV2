<?php
// revenue2/backend/Treasury/Fund_Allocation/get_bank_account.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../../db/treasury/Treasury_db.php';

try {
    $pdo = getTreasuryDB();
    
    // Get bank account (always id=1 for main account)
    $stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = 1");
    $stmt->execute();
    $account = $stmt->fetch();
    
    if ($account) {
        echo json_encode([
            'status' => 'success',
            'data' => $account
        ]);
    } else {
        // Create default bank account if it doesn't exist
        $stmt = $pdo->prepare("INSERT INTO bank_accounts (account_name, current_balance) VALUES (?, ?)");
        $stmt->execute(['LGU General Fund', 0]);
        
        $stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = ?");
        $stmt->execute([$pdo->lastInsertId()]);
        $account = $stmt->fetch();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Default bank account created',
            'data' => $account
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>