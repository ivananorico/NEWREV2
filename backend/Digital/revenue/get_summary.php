<?php
// backend/Digital/revenue/get_summary.php

// Set headers for CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include the database connection
require_once '../../../db/Digital/digital_db.php';

try {
    // Get database connection
    $pdo = getDigitalDB();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Get period parameter
    $period = isset($_GET['period']) ? $_GET['period'] : 'month';
    
    // Query for summary data
    $query = "SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_revenue,
        AVG(CASE WHEN payment_status = 'paid' THEN amount ELSE NULL END) as avg_transaction_value
    FROM payment_transactions";

    // Add date filter based on period
    switch($period) {
        case 'day':
            $query .= " WHERE DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $query .= " WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())";
            break;
        case 'month':
            $query .= " WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
            break;
        case 'year':
            $query .= " WHERE YEAR(created_at) = YEAR(CURDATE())";
            break;
        default:
            $query .= " WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get top 5 systems by revenue
    $topSystemsQuery = "SELECT 
        client_system,
        COUNT(*) as transaction_count,
        SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as revenue
    FROM payment_transactions
    WHERE payment_status = 'paid'";

    // Apply same period filter
    if ($period === 'day') {
        $topSystemsQuery .= " AND DATE(created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $topSystemsQuery .= " AND YEARWEEK(created_at) = YEARWEEK(CURDATE())";
    } elseif ($period === 'month') {
        $topSystemsQuery .= " AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
    } elseif ($period === 'year') {
        $topSystemsQuery .= " AND YEAR(created_at) = YEAR(CURDATE())";
    }

    $topSystemsQuery .= " GROUP BY client_system ORDER BY revenue DESC LIMIT 5";

    $topSystemsStmt = $pdo->prepare($topSystemsQuery);
    $topSystemsStmt->execute();
    $topSystems = $topSystemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare response
    $response = [
        'status' => 'success',
        'message' => 'Summary data retrieved successfully',
        'data' => [
            'summary' => $summary,
            'top_systems' => $topSystems,
            'period' => $period
        ]
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    // Database error
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'data' => []
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // General error
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => []
    ], JSON_PRETTY_PRINT);
}
?>