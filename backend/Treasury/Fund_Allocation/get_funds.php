<?php
// revenue2/backend/Treasury/Fund_Allocation/get_funds.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../../db/Treasury/treasury_db.php';

try {
    $pdo = getTreasuryDB();
    
    // Get all funds with allocation totals
    $sql = "SELECT 
                f.*,
                COALESCE(SUM(a.allocated_amount), 0) as total_allocated
            FROM funds f
            LEFT JOIN allocations a ON f.id = a.fund_id
            GROUP BY f.id
            ORDER BY f.created_at DESC";
    
    $stmt = $pdo->query($sql);
    $funds = $stmt->fetchAll();
    
    // Format the data
    foreach ($funds as &$fund) {
        $fund['available_balance'] = floatval($fund['current_balance']) - floatval($fund['total_allocated']);
    }
    
    echo json_encode([
        'status' => 'success',
        'count' => count($funds),
        'data' => $funds
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>