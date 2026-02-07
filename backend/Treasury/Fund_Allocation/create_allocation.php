<?php
// revenue2/backend/Treasury/Fund_Allocation/create_allocation.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../../db/Treasury/treasury_db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON input'
    ]);
    exit;
}

// Validate required fields
$required = ['fund_id', 'department', 'purpose', 'allocated_amount'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        echo json_encode([
            'status' => 'error',
            'message' => "Missing required field: $field"
        ]);
        exit;
    }
}

try {
    $pdo = getTreasuryDB();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Get fund details and lock row
    $fundId = intval($input['fund_id']);
    $stmt = $pdo->prepare("SELECT * FROM funds WHERE id = ? FOR UPDATE");
    $stmt->execute([$fundId]);
    $fund = $stmt->fetch();
    
    if (!$fund) {
        throw new Exception("Fund not found");
    }
    
    // 2. Calculate current allocations for this fund
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(allocated_amount), 0) as total_allocated FROM allocations WHERE fund_id = ?");
    $stmt->execute([$fundId]);
    $result = $stmt->fetch();
    $totalAllocated = floatval($result['total_allocated']);
    
    // 3. Check if fund has enough balance
    $requestedAmount = floatval($input['allocated_amount']);
    $fundBalance = floatval($fund['current_balance']);
    $availableBalance = $fundBalance - $totalAllocated;
    
    if ($requestedAmount > $availableBalance) {
        throw new Exception("Insufficient fund balance. Available: " . number_format($availableBalance, 2));
    }
    
    if ($requestedAmount <= 0) {
        throw new Exception("Allocation amount must be greater than 0");
    }
    
    // 4. Create allocation
    $sql = "INSERT INTO allocations (
                fund_id,
                department,
                purpose,
                allocated_amount,
                allocated_date,
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $fundId,
        trim($input['department']),
        trim($input['purpose']),
        $requestedAmount,
        isset($input['allocated_date']) ? $input['allocated_date'] : date('Y-m-d')
    ]);
    
    $allocationId = $pdo->lastInsertId();
    
    // 5. Update fund's current balance (optional - depends on your logic)
    // If you want to reduce fund balance when allocating:
    // $newFundBalance = $fundBalance - $requestedAmount;
    // $updateFund = $pdo->prepare("UPDATE funds SET current_balance = ?, updated_at = NOW() WHERE id = ?");
    // $updateFund->execute([$newFundBalance, $fundId]);
    
    // Commit transaction
    $pdo->commit();
    
    // Get the created allocation with fund info
    $sql = "SELECT a.*, f.fund_name, f.current_balance as fund_balance 
            FROM allocations a 
            LEFT JOIN funds f ON a.fund_id = f.id 
            WHERE a.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$allocationId]);
    $newAllocation = $stmt->fetch();
    
    // Calculate remaining balance
    $newAllocation['fund_available'] = $availableBalance - $requestedAmount;
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Allocation created successfully',
        'allocation_id' => $allocationId,
        'data' => $newAllocation
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