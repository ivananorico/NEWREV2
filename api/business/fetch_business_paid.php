<?php
// fetch_business_paid.php - SIMPLIFIED VERSION
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Cache-Control, Pragma, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit();
}

// Set CORS headers for actual request
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Cache-Control, Pragma, Authorization");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// DATABASE CONNECTION
// ============================================
$dbPath = '../../db/Business/business_db.php';

if (!file_exists($dbPath)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection file not found'
    ]);
    exit();
}

require_once $dbPath;
$pdo = getDatabaseConnection();

if (!$pdo) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'data' => [],
        'count' => 0
    ]);
    exit();
}

// ============================================
// GET PARAMETERS
// ============================================
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$business_id = isset($_GET['business_id']) ? intval($_GET['business_id']) : null;
$tax_status = isset($_GET['tax_status']) ? $_GET['tax_status'] : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// ============================================
// FETCH FROM TAX_SUMMARY TABLE ONLY
// ============================================
try {
    // Build query for tax_summary table ONLY
    $query = "
        SELECT 
            id,
            business_id,
            year,
            name as business_name,
            owner_type,
            business_nature,
            trade_name,
            tax_status,
            created_at,
            updated_at
        FROM tax_summary
        WHERE 1=1
    ";
    
    $params = [];
    
    // Add filters
    if ($business_id !== null) {
        $query .= " AND business_id = ?";
        $params[] = $business_id;
    }
    
    if ($year !== null) {
        $query .= " AND year = ?";
        $params[] = $year;
    }
    
    if ($tax_status !== null && in_array($tax_status, ['Paid', 'Partially Paid', 'Unpaid', 'Overdue', 'Exempt'])) {
        $query .= " AND tax_status = ?";
        $params[] = $tax_status;
    }
    
    // Add order and limit
    $query .= " ORDER BY year DESC, updated_at DESC, created_at DESC";
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tax_summaries = $stmt->fetchAll();
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM tax_summary WHERE 1=1";
    $count_params = [];
    
    if ($business_id !== null) {
        $count_query .= " AND business_id = ?";
        $count_params[] = $business_id;
    }
    
    if ($year !== null) {
        $count_query .= " AND year = ?";
        $count_params[] = $year;
    }
    
    if ($tax_status !== null && in_array($tax_status, ['Paid', 'Partially Paid', 'Unpaid', 'Overdue', 'Exempt'])) {
        $count_query .= " AND tax_status = ?";
        $count_params[] = $tax_status;
    }
    
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_count = $count_stmt->fetch()['total'];
    
    // Format dates
    foreach ($tax_summaries as &$summary) {
        if ($summary['created_at']) {
            $summary['created_at_formatted'] = date('M d, Y h:i A', strtotime($summary['created_at']));
        }
        if ($summary['updated_at']) {
            $summary['updated_at_formatted'] = date('M d, Y h:i A', strtotime($summary['updated_at']));
        }
    }
    
    // Return response
    echo json_encode([
        'status' => 'success',
        'message' => 'Tax summary data retrieved successfully',
        'data' => $tax_summaries,
        'pagination' => [
            'total' => intval($total_count),
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + count($tax_summaries)) < $total_count
        ],
        'filters_applied' => [
            'year' => $year,
            'business_id' => $business_id,
            'tax_status' => $tax_status
        ],
        'current_year' => date('Y'),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database query error: ' . $e->getMessage(),
        'data' => [],
        'count' => 0
    ]);
}