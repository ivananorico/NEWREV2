<?php
// revenue2/backend/Digital/payments.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include the database connection file
require_once __DIR__ . '/../../db/Digital/digital_db.php';

// Get database connection
$pdo = getDigitalDB();

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_payments':
        getPayments($pdo);
        break;
    case 'get_stats':
        getStats($pdo);
        break;
    case 'get_systems':
        getSystems($pdo);
        break;
    case 'get_all_systems':
        getAllSystems($pdo);
        break;
    case 'get_dashboard_summary':
        getDashboardSummary($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action. Available: get_payments, get_stats, get_systems, get_all_systems, get_dashboard_summary']);
        break;
}

function getPayments($pdo) {
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;
    
    // Build WHERE conditions - ONLY PAID transactions
    $whereConditions = ["payment_status = 'paid'", "DATE(created_at) BETWEEN :start_date AND :end_date"];
    $params = [
        'start_date' => $startDate,
        'end_date' => $endDate
    ];
    
    // Add filters
    if (!empty($_GET['payment_method']) && $_GET['payment_method'] !== 'all') {
        $whereConditions[] = "payment_method = :payment_method";
        $params['payment_method'] = $_GET['payment_method'];
    }
    
    if (!empty($_GET['client_system']) && $_GET['client_system'] !== 'all') {
        $whereConditions[] = "client_system = :client_system";
        $params['client_system'] = $_GET['client_system'];
    }
    
    if (!empty($_GET['search'])) {
        $searchTerm = "%{$_GET['search']}%";
        $whereConditions[] = "(payment_id LIKE :search OR client_reference LIKE :search OR purpose LIKE :search OR phone LIKE :search)";
        $params['search'] = $searchTerm;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    try {
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM payment_transactions WHERE $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = $countStmt->fetch()['total'];
        
        // Get paginated data
        $sql = "SELECT * FROM payment_transactions WHERE $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        // Bind other parameters
        foreach ($params as $key => $value) {
            if ($key !== 'limit' && $key !== 'offset') {
                $stmt->bindValue(":$key", $value);
            }
        }
        
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $payments,
            'pagination' => [
                'total' => (int)$totalCount,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($totalCount / $limit)
            ],
            'filters' => [
                'date_range' => ['start' => $startDate, 'end' => $endDate],
                'search' => $_GET['search'] ?? '',
                'payment_method' => $_GET['payment_method'] ?? 'all',
                'client_system' => $_GET['client_system'] ?? 'all'
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getStats($pdo) {
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    try {
        // Basic stats - ONLY PAID transactions
        $sql = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN payment_method = 'gcash' THEN 1 ELSE 0 END) as gcash_count,
                    SUM(CASE WHEN payment_method = 'maya' THEN 1 ELSE 0 END) as maya_count,
                    SUM(CASE WHEN payment_method = 'card' THEN 1 ELSE 0 END) as card_count,
                    AVG(amount) as average_amount
                FROM payment_transactions 
                WHERE payment_status = 'paid' 
                AND DATE(created_at) BETWEEN :start_date AND :end_date";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $basicStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Also get counts for different statuses for display
        $statusSql = "SELECT 
                        COUNT(*) as total_all,
                        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_count
                     FROM payment_transactions 
                     WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
        
        $statusStmt = $pdo->prepare($statusSql);
        $statusStmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $statusStats = $statusStmt->fetch(PDO::FETCH_ASSOC);
        
        // By system stats - ONLY PAID transactions
        $sql = "SELECT 
                    client_system,
                    COUNT(*) as count,
                    SUM(amount) as amount
                FROM payment_transactions 
                WHERE payment_status = 'paid' 
                AND DATE(created_at) BETWEEN :start_date AND :end_date
                GROUP BY client_system
                ORDER BY amount DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $bySystem = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $systemStats = [];
        foreach ($bySystem as $row) {
            $systemStats[$row['client_system']] = [
                'count' => (int)$row['count'],
                'amount' => (float)$row['amount']
            ];
        }
        
        // Daily trend - ONLY PAID transactions
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count,
                    SUM(amount) as amount,
                    GROUP_CONCAT(DISTINCT client_system) as systems
                FROM payment_transactions 
                WHERE payment_status = 'paid' 
                AND DATE(created_at) BETWEEN :start_date AND :end_date
                GROUP BY DATE(created_at)
                ORDER BY date";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $dailyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Monthly summary - ONLY PAID transactions
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count,
                    SUM(amount) as amount
                FROM payment_transactions 
                WHERE payment_status = 'paid' 
                AND DATE(created_at) BETWEEN :start_date AND :end_date
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC
                LIMIT 6";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $monthlySummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top payments - ONLY PAID transactions
        $sql = "SELECT 
                    payment_id,
                    client_system,
                    purpose,
                    amount,
                    payment_method,
                    created_at
                FROM payment_transactions 
                WHERE payment_status = 'paid' 
                AND DATE(created_at) BETWEEN :start_date AND :end_date
                ORDER BY amount DESC
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $topPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_transactions' => (int)$basicStats['total_transactions'],
                'total_amount' => (float)($basicStats['total_amount'] ?? 0),
                'paid_count' => (int)$statusStats['paid_count'],
                'pending_count' => (int)$statusStats['pending_count'],
                'failed_count' => (int)$statusStats['failed_count'],
                'total_all_transactions' => (int)$statusStats['total_all'],
                'gcash_count' => (int)$basicStats['gcash_count'],
                'maya_count' => (int)$basicStats['maya_count'],
                'card_count' => (int)$basicStats['card_count'],
                'average_amount' => (float)($basicStats['average_amount'] ?? 0),
                'success_rate' => $statusStats['total_all'] > 0 ? 
                    round(($statusStats['paid_count'] / $statusStats['total_all']) * 100, 2) : 0,
                'by_system' => $systemStats,
                'daily_trend' => $dailyTrend,
                'monthly_summary' => $monthlySummary,
                'top_payments' => $topPayments
            ],
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
                'days' => (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24) + 1
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getSystems($pdo) {
    try {
        $sql = "SELECT DISTINCT client_system FROM payment_transactions ORDER BY client_system";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $systems = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true,
            'data' => $systems
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * FIXED: Get ALL unique client systems from the entire database
 * This ensures only systems that actually exist in YOUR database are displayed
 */
function getAllSystems($pdo) {
    try {
        // Get all unique client systems from YOUR database only
        $sql = "SELECT DISTINCT client_system FROM payment_transactions ORDER BY client_system";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $systems = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get comprehensive info for each system that ACTUALLY EXISTS
        $systemsWithInfo = [];
        foreach ($systems as $system) {
            // Overall stats (all time) - ONLY PAID transactions
            $infoSql = "SELECT 
                        COUNT(*) as total_transactions,
                        SUM(amount) as total_amount,
                        SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                        MIN(created_at) as first_transaction,
                        MAX(created_at) as last_transaction
                       FROM payment_transactions 
                       WHERE client_system = :client_system 
                       AND payment_status = 'paid'";
            
            $infoStmt = $pdo->prepare($infoSql);
            $infoStmt->execute(['client_system' => $system]);
            $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get count of all transactions for success rate calculation
            $allCountSql = "SELECT 
                           COUNT(*) as total_all,
                           SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count
                          FROM payment_transactions 
                          WHERE client_system = :client_system";
            
            $allCountStmt = $pdo->prepare($allCountSql);
            $allCountStmt->execute(['client_system' => $system]);
            $allCounts = $allCountStmt->fetch(PDO::FETCH_ASSOC);
            
            // Only include systems that actually have data
            if ($info['total_transactions'] > 0) {
                // Get current month stats - ONLY PAID transactions
                $currentMonthSql = "SELECT 
                                   COUNT(*) as current_month_count,
                                   SUM(amount) as current_month_amount
                                  FROM payment_transactions 
                                  WHERE client_system = :client_system 
                                  AND payment_status = 'paid'
                                  AND MONTH(created_at) = MONTH(CURDATE()) 
                                  AND YEAR(created_at) = YEAR(CURDATE())";
                
                $currentMonthStmt = $pdo->prepare($currentMonthSql);
                $currentMonthStmt->execute(['client_system' => $system]);
                $currentMonth = $currentMonthStmt->fetch(PDO::FETCH_ASSOC);
                
                // Get payment method distribution - ONLY PAID transactions
                $methodSql = "SELECT 
                             payment_method,
                             COUNT(*) as method_count,
                             SUM(amount) as method_amount
                            FROM payment_transactions 
                            WHERE client_system = :client_system 
                            AND payment_status = 'paid'
                            GROUP BY payment_method";
                
                $methodStmt = $pdo->prepare($methodSql);
                $methodStmt->execute(['client_system' => $system]);
                $paymentMethods = $methodStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $systemsWithInfo[] = [
                    'system' => $system,
                    'total_transactions' => (int)$info['total_transactions'],
                    'total_amount' => (float)($info['total_amount'] ?? 0),
                    'paid_amount' => (float)($info['paid_amount'] ?? 0),
                    'paid_count' => (int)$allCounts['paid_count'],
                    'total_all_transactions' => (int)$allCounts['total_all'],
                    'current_month_count' => (int)$currentMonth['current_month_count'],
                    'current_month_amount' => (float)($currentMonth['current_month_amount'] ?? 0),
                    'first_transaction' => $info['first_transaction'],
                    'last_transaction' => $info['last_transaction'],
                    'payment_methods' => $paymentMethods,
                    'success_rate' => $allCounts['total_all'] > 0 ? 
                        round(($allCounts['paid_count'] / $allCounts['total_all']) * 100, 2) : 0
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $systemsWithInfo,
            'total_systems' => count($systemsWithInfo)
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getDashboardSummary($pdo) {
    try {
        // Today's summary - ONLY PAID transactions
        $today = date('Y-m-d');
        $sqlToday = "SELECT 
                        COUNT(*) as count,
                        SUM(amount) as amount
                     FROM payment_transactions 
                     WHERE payment_status = 'paid' 
                     AND DATE(created_at) = :today";
        
        $stmt = $pdo->prepare($sqlToday);
        $stmt->execute(['today' => $today]);
        $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Yesterday's summary - ONLY PAID transactions
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $sqlYesterday = "SELECT 
                            COUNT(*) as count,
                            SUM(amount) as amount
                         FROM payment_transactions 
                         WHERE payment_status = 'paid' 
                         AND DATE(created_at) = :yesterday";
        
        $stmt = $pdo->prepare($sqlYesterday);
        $stmt->execute(['yesterday' => $yesterday]);
        $yesterdayStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // This month summary - ONLY PAID transactions
        $thisMonth = date('Y-m');
        $sqlMonth = "SELECT 
                        COUNT(*) as count,
                        SUM(amount) as amount
                     FROM payment_transactions 
                     WHERE payment_status = 'paid' 
                     AND DATE_FORMAT(created_at, '%Y-%m') = :month";
        
        $stmt = $pdo->prepare($sqlMonth);
        $stmt->execute(['month' => $thisMonth]);
        $monthStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Overall summary - ONLY PAID transactions
        $sqlOverall = "SELECT 
                          COUNT(*) as total_transactions,
                          SUM(amount) as total_amount,
                          MIN(created_at) as first_transaction,
                          MAX(created_at) as last_transaction
                       FROM payment_transactions
                       WHERE payment_status = 'paid'";
        
        $stmt = $pdo->prepare($sqlOverall);
        $stmt->execute();
        $overallStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Recent payments - ONLY PAID transactions
        $sqlRecent = "SELECT * FROM payment_transactions 
                      WHERE payment_status = 'paid'
                      ORDER BY created_at DESC 
                      LIMIT 10";
        
        $stmt = $pdo->prepare($sqlRecent);
        $stmt->execute();
        $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'today' => [
                    'count' => (int)($todayStats['count'] ?? 0),
                    'amount' => (float)($todayStats['amount'] ?? 0)
                ],
                'yesterday' => [
                    'count' => (int)($yesterdayStats['count'] ?? 0),
                    'amount' => (float)($yesterdayStats['amount'] ?? 0)
                ],
                'this_month' => [
                    'count' => (int)($monthStats['count'] ?? 0),
                    'amount' => (float)($monthStats['amount'] ?? 0)
                ],
                'overall' => [
                    'total_transactions' => (int)($overallStats['total_transactions'] ?? 0),
                    'total_amount' => (float)($overallStats['total_amount'] ?? 0),
                    'first_transaction' => $overallStats['first_transaction'] ?? null,
                    'last_transaction' => $overallStats['last_transaction'] ?? null
                ],
                'recent_payments' => $recentPayments
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>