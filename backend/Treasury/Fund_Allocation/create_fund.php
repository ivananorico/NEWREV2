<?php
// revenue2/backend/Treasury/Fund_Allocation/create_fund.php

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get raw input
$rawInput = file_get_contents('php://input');

// Log for debugging
error_log("Raw input received: " . $rawInput);

if (empty($rawInput)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No data received. Raw input is empty.',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
            'raw_input_empty' => true
        ]
    ]);
    exit;
}

// Try to decode JSON
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON input: ' . json_last_error_msg(),
        'raw_input_preview' => substr($rawInput, 0, 200),
        'json_error' => json_last_error()
    ]);
    exit;
}

// Validate required fields
if (empty($input['fund_name'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Fund name is required'
    ]);
    exit;
}

if (!isset($input['initial_balance']) || $input['initial_balance'] === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Initial balance is required'
    ]);
    exit;
}

// Include database connection
require_once '../../../db/Treasury/treasury_db.php';

try {
    $pdo = getTreasuryDB();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Check bank balance
    $stmt = $pdo->prepare("SELECT current_balance FROM bank_accounts WHERE id = 1 FOR UPDATE");
    $stmt->execute();
    $bank = $stmt->fetch();
    
    if (!$bank) {
        throw new Exception("Bank account not found");
    }
    
    $requestedAmount = floatval($input['initial_balance']);
    $currentBalance = floatval($bank['current_balance']);
    
    if ($requestedAmount > $currentBalance) {
        throw new Exception("Insufficient bank balance. Available: " . number_format($currentBalance, 2));
    }
    
    if ($requestedAmount <= 0) {
        throw new Exception("Initial balance must be greater than 0");
    }
    
    // 2. Create new fund
    $sql = "INSERT INTO funds (
                fund_name, 
                description, 
                initial_balance, 
                current_balance, 
                fiscal_year,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        trim($input['fund_name']),
        isset($input['description']) ? trim($input['description']) : '',
        $requestedAmount,
        $requestedAmount, // current_balance starts same as initial
        isset($input['fiscal_year']) ? intval($input['fiscal_year']) : date('Y')
    ]);
    
    $fundId = $pdo->lastInsertId();
    
    // 3. Deduct from bank account
    $newBankBalance = $currentBalance - $requestedAmount;
    $updateBank = $pdo->prepare("UPDATE bank_accounts SET current_balance = ?, updated_at = NOW() WHERE id = 1");
    $updateBank->execute([$newBankBalance]);
    
    // Commit transaction
    $pdo->commit();
    
    // Get the created fund
    $stmt = $pdo->prepare("SELECT * FROM funds WHERE id = ?");
    $stmt->execute([$fundId]);
    $newFund = $stmt->fetch();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Fund created successfully',
        'fund_id' => $fundId,
        'data' => $newFund,
        'bank_balance_updated' => $newBankBalance
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>