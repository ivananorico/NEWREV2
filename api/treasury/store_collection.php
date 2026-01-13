<?php
// revenue2/api/treasury/store_collection.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Include Treasury DB connection
require_once __DIR__ . '/../../db/treasury/treasury_db.php';

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid JSON data"]);
    exit();
}

// Validate required fields
$required = ['or_number', 'payment_date', 'amount', 'revenue_source'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing field: $field"]);
        exit();
    }
}

try {
    // Get Treasury DB connection
    $pdo = getTreasuryDB();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Insert into collections table
    $stmt = $pdo->prepare("
        INSERT INTO treasury_collections 
        (or_number, payment_date, amount, revenue_source, 
         reference_id, payment_method, transaction_data)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $input['or_number'],
        $input['payment_date'],
        $input['amount'],
        $input['revenue_source'],
        $input['reference_id'] ?? null,
        $input['payment_method'] ?? 'gcash',
        $input['transaction_data'] ?? null
    ]);
    
    $collection_id = $pdo->lastInsertId();
    
    // 2. UPDATE BANK ACCOUNT BALANCE (ADD MONEY)
    // Check if bank_accounts table exists, create if not
    $check_table = $pdo->query("SHOW TABLES LIKE 'bank_accounts'")->fetch();
    if (!$check_table) {
        // Create bank_accounts table if it doesn't exist
        $create_table = "
            CREATE TABLE IF NOT EXISTS bank_accounts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                account_name VARCHAR(100) NOT NULL DEFAULT 'LGU General Fund',
                current_balance DECIMAL(15,2) DEFAULT 0.00,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";
        $pdo->exec($create_table);
        
        // Insert initial record
        $pdo->exec("INSERT INTO bank_accounts (account_name, current_balance) VALUES ('Quezon City Treasury', 0.00)");
    }
    
    // Update bank balance (add the collection amount)
    $update_bank = $pdo->prepare("
        UPDATE bank_accounts 
        SET current_balance = current_balance + ?
        WHERE id = 1
    ");
    $update_bank->execute([$input['amount']]);
    
    // Get new bank balance
    $balance_stmt = $pdo->query("SELECT current_balance FROM bank_accounts WHERE id = 1");
    $new_balance = $balance_stmt->fetchColumn();
    
    // 3. Update daily summary
    updateDailySummary($pdo, $input);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        "success" => true,
        "message" => "Collection stored in Treasury",
        "data" => [
            "collection_id" => $collection_id,
            "bank_balance_updated" => true,
            "new_bank_balance" => $new_balance,
            "amount_added" => $input['amount']
        ],
        "environment" => ($_SERVER['HTTP_HOST'] === 'revenuetreasury.goserveph.com') ? 'production' : 'local'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "error" => "Database error: " . $e->getMessage(),
        "environment" => ($_SERVER['HTTP_HOST'] === 'revenuetreasury.goserveph.com') ? 'production' : 'local'
    ]);
}

function updateDailySummary($pdo, $data) {
    $date = date('Y-m-d', strtotime($data['payment_date']));
    $method = $data['payment_method'] ?? 'gcash';
    $amount = $data['amount'];
    
    // Set amounts based on payment method
    $cash = $method === 'cash' ? $amount : 0;
    $check = $method === 'check' ? $amount : 0;
    $gcash = $method === 'gcash' ? $amount : 0;
    $paymaya = $method === 'paymaya' ? $amount : 0;
    $bank = $method === 'bank' ? $amount : 0;
    
    $stmt = $pdo->prepare("
        INSERT INTO daily_cashbook 
        (collection_date, total_collected, cash_amount, check_amount, 
         gcash_amount, paymaya_amount, bank_amount, transaction_count)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            total_collected = total_collected + VALUES(total_collected),
            cash_amount = cash_amount + VALUES(cash_amount),
            check_amount = check_amount + VALUES(check_amount),
            gcash_amount = gcash_amount + VALUES(gcash_amount),
            paymaya_amount = paymaya_amount + VALUES(paymaya_amount),
            bank_amount = bank_amount + VALUES(bank_amount),
            transaction_count = transaction_count + 1
    ");
    
    $stmt->execute([$date, $amount, $cash, $check, $gcash, $paymaya, $bank]);
}

// Optional: Function to get current treasury status
function getTreasuryStatus($pdo) {
    $status = [];
    
    // Get bank balance
    $balance_stmt = $pdo->query("SELECT current_balance FROM bank_accounts WHERE id = 1");
    $status['bank_balance'] = $balance_stmt->fetchColumn() ?? 0.00;
    
    // Get total collections (verification)
    $collections_stmt = $pdo->query("SELECT SUM(amount) as total FROM treasury_collections");
    $status['total_collections'] = $collections_stmt->fetchColumn() ?? 0.00;
    
    // Get today's collections
    $today_stmt = $pdo->prepare("
        SELECT SUM(amount) as today_total 
        FROM treasury_collections 
        WHERE DATE(payment_date) = CURDATE()
    ");
    $today_stmt->execute();
    $status['today_collections'] = $today_stmt->fetchColumn() ?? 0.00;
    
    return $status;
}
?>