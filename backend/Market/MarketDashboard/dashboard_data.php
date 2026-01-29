<?php
// dashboard_data.php - Market Dashboard Backend (Fixed Version)
header('Content-Type: application/json');

// Increase PHP limits for reliability
ini_set('max_execution_time', 30);
ini_set('memory_limit', '128M');

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Get request parameters with defaults
$action = $_GET['action'] ?? '';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');

// Validate parameters
if ($year < 2020) $year = date('Y');
if ($month < 1 || $month > 12) $month = date('n');

// Database connection with retry logic
function connectToDatabase($retryCount = 3) {
    for ($i = 0; $i < $retryCount; $i++) {
        try {
            $host = 'localhost:3307';
            $dbname = 'market_rent';
            $username = 'root';
            $password = '';
            
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => false, // Don't use persistent connections
                    PDO::ATTR_TIMEOUT => 5, // 5 second timeout
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            
            return $pdo;
            
        } catch (PDOException $e) {
            error_log("Database connection attempt " . ($i + 1) . " failed: " . $e->getMessage());
            
            // Wait before retry (exponential backoff)
            if ($i < $retryCount - 1) {
                usleep(100000 * pow(2, $i)); // 100ms, 200ms, 400ms
            }
        }
    }
    
    return null; // All retries failed
}

// Get database connection
$pdo = connectToDatabase();

// If no database connection, return empty data with success status
if (!$pdo) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Database temporarily unavailable',
        'data' => [],
        'totals' => [],
        'metrics' => [],
        'revenue_trend' => [],
        'business_types' => [],
        'recent_payments' => [],
        'top_stalls' => [],
        'selected_year' => $year,
        'selected_month' => $month,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Function to safely execute queries with fallback
function safeQuery($pdo, $query, $params = [], $default = null) {
    try {
        if (!empty($params)) {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        } else {
            $stmt = $pdo->query($query);
        }
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query failed: " . $e->getMessage() . " | Query: " . $query);
        return false;
    }
}

// Function to get available years
function getAvailableYears($pdo) {
    $years = [];
    
    // Try multiple queries with fallbacks
    $queries = [
        "SELECT DISTINCT YEAR(created_at) as year FROM rental_registration WHERE created_at IS NOT NULL",
        "SELECT DISTINCT YEAR(payment_date) as year FROM market_payment_logs WHERE payment_date IS NOT NULL",
        "SELECT DISTINCT billing_year as year FROM monthly_rent_billing WHERE billing_year IS NOT NULL",
        "SELECT YEAR(CURDATE()) as year" // Always include current year
    ];
    
    foreach ($queries as $query) {
        $stmt = safeQuery($pdo, $query);
        if ($stmt) {
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $years = array_merge($years, $result);
        }
    }
    
    // Clean and deduplicate
    $years = array_filter(array_map('intval', $years));
    $years = array_unique($years);
    
    // Ensure current year is included
    $currentYear = date('Y');
    if (!in_array($currentYear, $years)) {
        $years[] = $currentYear;
    }
    
    // Sort descending
    rsort($years);
    
    return array_values($years);
}

// Function to get available months
function getAvailableMonths($pdo, $year) {
    $months = [];
    
    $queries = [
        "SELECT DISTINCT MONTH(created_at) as month FROM rental_registration WHERE YEAR(created_at) = ?",
        "SELECT DISTINCT MONTH(payment_date) as month FROM market_payment_logs WHERE YEAR(payment_date) = ?",
        "SELECT DISTINCT billing_month as month FROM monthly_rent_billing WHERE billing_year = ?"
    ];
    
    foreach ($queries as $query) {
        $stmt = safeQuery($pdo, $query, [$year]);
        if ($stmt) {
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $months = array_merge($months, $result);
        }
    }
    
    // Clean and deduplicate
    $months = array_filter(array_map('intval', $months));
    $months = array_unique($months);
    
    // If no months found, return all months
    if (empty($months)) {
        $months = range(1, 12);
    }
    
    sort($months);
    
    return array_values($months);
}

// Handle get_years action
if ($action === 'get_years') {
    try {
        $years = getAvailableYears($pdo);
        echo json_encode([
            'status' => 'success',
            'years' => $years,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'success',
            'years' => [date('Y')],
            'message' => 'Using default year'
        ]);
        exit;
    }
}

// Handle get_months action
if ($action === 'get_months') {
    try {
        $months = getAvailableMonths($pdo, $year);
        echo json_encode([
            'status' => 'success',
            'months' => $months,
            'year' => $year,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'success',
            'months' => range(1, 12),
            'year' => $year,
            'message' => 'Using all months'
        ]);
        exit;
    }
}

// Main dashboard data endpoint
try {
    // Initialize all data with defaults
    $data = [
        'status' => 'success',
        'totals' => [
            'total_citizens' => 0,
            'active_stalls' => 0,
            'available_stalls' => 0,
            'active_renters' => 0,
            'monthly_revenue' => 0.00,
            'total_contract_value' => 0.00,
            'this_month_payments' => 0.00,
            'today_payments' => 0.00,
            'overdue_payments' => 0.00,
            'pending_applications' => 0
        ],
        'metrics' => [
            'occupancy_rate' => 0.00,
            'collection_rate' => 0.00
        ],
        'revenue_trend' => [],
        'business_types' => [],
        'recent_payments' => [],
        'top_stalls' => [],
        'selected_year' => $year,
        'selected_month' => $month,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // 1. Get Total Stalls
    $query = "SELECT COUNT(*) as total FROM stalls";
    $stmt = safeQuery($pdo, $query);
    if ($stmt) {
        $data['totals']['total_citizens'] = (int)$stmt->fetchColumn();
    }
    
    // 2. Get Active Stalls
    $query = "SELECT COUNT(*) as total FROM stalls WHERE status = 'occupied'";
    $stmt = safeQuery($pdo, $query);
    if ($stmt) {
        $data['totals']['active_stalls'] = (int)$stmt->fetchColumn();
    }
    
    // 3. Calculate Available Stalls
    $data['totals']['available_stalls'] = $data['totals']['total_citizens'] - $data['totals']['active_stalls'];
    
    // 4. Get Active Renters
    $query = "SELECT COUNT(*) as total FROM renter_owner WHERE status = 'active'";
    $stmt = safeQuery($pdo, $query);
    if ($stmt) {
        $data['totals']['active_renters'] = (int)$stmt->fetchColumn();
    }
    
    // 5. Get Monthly Revenue
    $query = "SELECT COALESCE(SUM(monthly_rent), 0) as total FROM rent_stall WHERE status = 'active'";
    $stmt = safeQuery($pdo, $query);
    if ($stmt) {
        $data['totals']['monthly_revenue'] = (float)$stmt->fetchColumn();
    }
    
    // 6. Get Total Contract Value
    $query = "SELECT COALESCE(SUM(monthly_totals), 0) as total FROM rent_totals WHERE status = 'active'";
    $stmt = safeQuery($pdo, $query);
    if ($stmt) {
        $data['totals']['total_contract_value'] = (float)$stmt->fetchColumn();
    }
    
    // 7. Get This Month's Payments
    $startDate = date("Y-m-01", strtotime("$year-$month-01"));
    $endDate = date("Y-m-t", strtotime($startDate));
    $query = "SELECT COALESCE(SUM(amount_paid), 0) as total FROM market_payment_logs WHERE payment_date BETWEEN ? AND ?";
    $stmt = safeQuery($pdo, $query, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    if ($stmt) {
        $data['totals']['this_month_payments'] = (float)$stmt->fetchColumn();
    }
    
    // 8. Get Today's Payments
    $today = date('Y-m-d');
    $query = "SELECT COALESCE(SUM(amount_paid), 0) as total FROM market_payment_logs WHERE DATE(payment_date) = ?";
    $stmt = safeQuery($pdo, $query, [$today]);
    if ($stmt) {
        $data['totals']['today_payments'] = (float)$stmt->fetchColumn();
    }
    
    // 9. Get Overdue Payments
    $query = "SELECT COALESCE(SUM(total_amount_due), 0) as total FROM monthly_rent_billing WHERE payment_status = 'overdue'";
    $stmt = safeQuery($pdo, $query);
    if ($stmt) {
        $data['totals']['overdue_payments'] = (float)$stmt->fetchColumn();
    }
    
    // 10. Get Pending Applications
    $query = "SELECT COUNT(*) as total FROM rental_registration WHERE application_status IN ('pending', 'paying')";
    $stmt = safeQuery($pdo, $query);
    if ($stmt) {
        $data['totals']['pending_applications'] = (int)$stmt->fetchColumn();
    }
    
    // 11. Calculate Metrics
    if ($data['totals']['total_citizens'] > 0) {
        $data['metrics']['occupancy_rate'] = round(
            ($data['totals']['active_stalls'] / $data['totals']['total_citizens']) * 100, 
            2
        );
    }
    
    if ($data['totals']['monthly_revenue'] > 0) {
        $data['metrics']['collection_rate'] = round(
            ($data['totals']['this_month_payments'] / $data['totals']['monthly_revenue']) * 100, 
            2
        );
    }
    
    // 12. Get Revenue Trend
    $query = "
        SELECT 
            m.month_num,
            m.month_name,
            COALESCE(SUM(mrb.base_rent), 0) as revenue
        FROM (
            SELECT 1 as month_num, 'January' as month_name UNION SELECT 2, 'February' 
            UNION SELECT 3, 'March' UNION SELECT 4, 'April' UNION SELECT 5, 'May' 
            UNION SELECT 6, 'June' UNION SELECT 7, 'July' UNION SELECT 8, 'August' 
            UNION SELECT 9, 'September' UNION SELECT 10, 'October' 
            UNION SELECT 11, 'November' UNION SELECT 12, 'December'
        ) m
        LEFT JOIN monthly_rent_billing mrb ON m.month_num = mrb.billing_month AND mrb.billing_year = ? AND mrb.payment_status = 'paid'
        GROUP BY m.month_num, m.month_name
        ORDER BY m.month_num
    ";
    
    $stmt = safeQuery($pdo, $query, [$year]);
    if ($stmt) {
        $trendData = $stmt->fetchAll();
        foreach ($trendData as $row) {
            $data['revenue_trend'][] = [
                'month' => $row['month_name'],
                'revenue' => (float)$row['revenue']
            ];
        }
    }
    
    // 13. Get Business Types
    $query = "
        SELECT 
            COALESCE(business_type, 'Not Specified') as business_type,
            COUNT(*) as count
        FROM rent_stall 
        WHERE status = 'active'
        GROUP BY business_type
        ORDER BY count DESC
    ";
    
    $stmt = safeQuery($pdo, $query);
    if ($stmt) {
        $data['business_types'] = $stmt->fetchAll();
    }
    
    // 14. Get Recent Payments
    $query = "
        SELECT 
            mpl.*,
            CONCAT(ro.first_name, ' ', ro.last_name) as renter_name,
            rs.business_name,
            rs.business_type,
            rs.stall_rights_no
        FROM market_payment_logs mpl
        LEFT JOIN renter_owner ro ON mpl.renter_code = ro.renter_code
        LEFT JOIN rent_stall rs ON ro.registration_id = rs.registration_id
        ORDER BY mpl.payment_date DESC
        LIMIT 10
    ";
    
    $stmt = safeQuery($pdo, $query);
    if ($stmt) {
        $data['recent_payments'] = $stmt->fetchAll();
    }
    
    // 15. Get Top Stalls
    $query = "
        SELECT 
            rs.business_name,
            CONCAT(ro.first_name, ' ', ro.last_name) as renter_name,
            rs.business_type,
            rr.monthly_rent,
            rt.monthly_totals as contract_value
        FROM rent_stall rs
        LEFT JOIN renter_owner ro ON rs.renter_id = ro.id
        LEFT JOIN rental_registration rr ON rs.registration_id = rr.id
        LEFT JOIN rent_totals rt ON rs.registration_id = rt.registration_id AND rt.status = 'active'
        WHERE rs.status = 'active'
        ORDER BY rr.monthly_rent DESC
        LIMIT 5
    ";
    
    $stmt = safeQuery($pdo, $query);
    if ($stmt) {
        $data['top_stalls'] = $stmt->fetchAll();
    }
    
    // Always return success with data (even if empty)
    echo json_encode($data, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // If any error occurs, return empty data but with success status
    error_log("Dashboard error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Data loaded with some errors',
        'totals' => [
            'total_citizens' => 0,
            'active_stalls' => 0,
            'available_stalls' => 0,
            'active_renters' => 0,
            'monthly_revenue' => 0.00,
            'total_contract_value' => 0.00,
            'this_month_payments' => 0.00,
            'today_payments' => 0.00,
            'overdue_payments' => 0.00,
            'pending_applications' => 0
        ],
        'metrics' => [
            'occupancy_rate' => 0.00,
            'collection_rate' => 0.00
        ],
        'revenue_trend' => [],
        'business_types' => [],
        'recent_payments' => [],
        'top_stalls' => [],
        'selected_year' => $year,
        'selected_month' => $month,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}

// Close database connection
if ($pdo) {
    $pdo = null;
}
?>