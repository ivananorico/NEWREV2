<?php
// revenue2/backend/Treasury/Fund_Allocation/get_allocations.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../../db/treasury/treasury_db.php';

try {
    $pdo = getTreasuryDB();
    
    // Get all allocations with fund names
    $sql = "SELECT 
                a.*,
                f.fund_name,
                f.current_balance as fund_balance
            FROM allocations a
            LEFT JOIN funds f ON a.fund_id = f.id
            ORDER BY a.allocated_date DESC, a.created_at DESC";
    
    $stmt = $pdo->query($sql);
    $allocations = $stmt->fetchAll();
    
    // Calculate available balance per fund
    $fundAllocations = [];
    foreach ($allocations as $alloc) {
        $fundId = $alloc['fund_id'];
        if (!isset($fundAllocations[$fundId])) {
            $fundAllocations[$fundId] = 0;
        }
        $fundAllocations[$fundId] += floatval($alloc['allocated_amount']);
    }
    
    // Add available balance to each allocation
    foreach ($allocations as &$alloc) {
        $fundId = $alloc['fund_id'];
        $totalAllocated = $fundAllocations[$fundId] ?? 0;
        $alloc['fund_available'] = floatval($alloc['fund_balance']) - $totalAllocated;
    }
    
    echo json_encode([
        'status' => 'success',
        'count' => count($allocations),
        'data' => $allocations
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>