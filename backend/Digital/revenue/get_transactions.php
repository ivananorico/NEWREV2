<?php
// backend/Digital/revenue/get_transactions.php

// Set CORS headers FIRST
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Then set content type
header('Content-Type: application/json');

// Include database connection
require_once '../../../db/Digital/digital_db.php';

try {
    $pdo = getDigitalDB();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Get parameters with defaults
    $start_date = $_GET['start_date'] ?? '2026-01-01';
    $end_date = $_GET['end_date'] ?? '2026-02-07';
    $client_system = $_GET['client_system'] ?? null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 1000;

    // Base query for transactions
    $query = "SELECT 
                client_system,
                DATE(created_at) as transaction_date,
                amount,
                payment_status,
                payment_method,
                payment_id,
                purpose
              FROM payment_transactions 
              WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
    
    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    if ($client_system && $client_system !== 'all') {
        $query .= " AND client_system = :client_system";
        $params[':client_system'] = $client_system;
    }

    $query .= " ORDER BY created_at DESC LIMIT :limit";
    $params[':limit'] = $limit;

    // Get transactions
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $value) {
        if ($key === ':limit') {
            $stmt->bindValue($key, (int)$value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary statistics
    $summaryQuery = "SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_revenue,
        AVG(CASE WHEN payment_status = 'paid' THEN amount ELSE NULL END) as avg_transaction_value,
        MIN(created_at) as earliest_date,
        MAX(created_at) as latest_date
    FROM payment_transactions 
    WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
    
    if ($client_system && $client_system !== 'all') {
        $summaryQuery .= " AND client_system = :client_system";
    }

    $summaryStmt = $pdo->prepare($summaryQuery);
    $summaryStmt->bindValue(':start_date', $start_date);
    $summaryStmt->bindValue(':end_date', $end_date);
    if ($client_system && $client_system !== 'all') {
        $summaryStmt->bindValue(':client_system', $client_system);
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    // Get revenue by system
    $systemQuery = "SELECT 
        client_system,
        COUNT(*) as transaction_count,
        SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_revenue,
        ROUND(AVG(CASE WHEN payment_status = 'paid' THEN amount ELSE NULL END), 2) as avg_revenue
    FROM payment_transactions 
    WHERE DATE(created_at) BETWEEN :start_date AND :end_date
    GROUP BY client_system 
    ORDER BY total_revenue DESC";

    $systemStmt = $pdo->prepare($systemQuery);
    $systemStmt->bindValue(':start_date', $start_date);
    $systemStmt->bindValue(':end_date', $end_date);
    $systemStmt->execute();
    $systemRevenue = $systemStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get daily revenue trend (last 30 days)
    $dailyQuery = "SELECT 
        DATE(created_at) as date,
        COUNT(*) as transaction_count,
        SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as daily_revenue
    FROM payment_transactions 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND DATE(created_at) BETWEEN :start_date AND :end_date
    GROUP BY DATE(created_at) 
    ORDER BY date ASC";

    $dailyStmt = $pdo->prepare($dailyQuery);
    $dailyStmt->bindValue(':start_date', $start_date);
    $dailyStmt->bindValue(':end_date', $end_date);
    $dailyStmt->execute();
    $dailyTrend = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get payment method distribution
    $methodQuery = "SELECT 
        payment_method,
        COUNT(*) as count,
        SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as revenue
    FROM payment_transactions 
    WHERE DATE(created_at) BETWEEN :start_date AND :end_date
    GROUP BY payment_method";

    $methodStmt = $pdo->prepare($methodQuery);
    $methodStmt->bindValue(':start_date', $start_date);
    $methodStmt->bindValue(':end_date', $end_date);
    $methodStmt->execute();
    $paymentMethods = $methodStmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare response
    $response = [
        'status' => 'success',
        'message' => 'Revenue data retrieved successfully',
        'data' => [
            'transactions' => $transactions,
            'summary' => $summary,
            'system_revenue' => $systemRevenue,
            'daily_trend' => $dailyTrend,
            'payment_methods' => $paymentMethods,
            'filters' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'client_system' => $client_system,
                'limit' => $limit
            ]
        ],
        'timestamp' => date('Y-m-d H:i:s'),
        'total_records' => count($transactions)
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => []
    ]);
}
?>