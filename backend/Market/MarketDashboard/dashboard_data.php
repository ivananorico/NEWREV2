<?php
// dashboard_data.php - Market Dashboard API (Database Only)
header('Content-Type: application/json');

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database connection
function connectDatabase() {
    $host = 'localhost:3307';
    $dbname = 'market_rent';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // Return error if connection fails
        return [
            'error' => true,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ];
    }
}

// Get database connection
$pdo_response = connectDatabase();

if (isset($pdo_response['error'])) {
    echo json_encode([
        'status' => 'error',
        'message' => $pdo_response['message'],
        'data' => []
    ]);
    exit;
}

$pdo = $pdo_response;

// Get request parameters
$action = $_GET['action'] ?? '';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');

// Function to get available years
function getAvailableYears($pdo) {
    $years = [];
    
    // Try to get years from multiple tables
    try {
        // From payment logs
        $query = "SELECT DISTINCT YEAR(payment_date) as year FROM market_payment_logs WHERE payment_date IS NOT NULL";
        $stmt = $pdo->query($query);
        $payment_years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $years = array_merge($years, $payment_years);
    } catch (Exception $e) {}
    
    try {
        // From rental registration
        $query = "SELECT DISTINCT YEAR(created_at) as year FROM rental_registration WHERE created_at IS NOT NULL";
        $stmt = $pdo->query($query);
        $reg_years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $years = array_merge($years, $reg_years);
    } catch (Exception $e) {}
    
    try {
        // From monthly rent billing
        $query = "SELECT DISTINCT billing_year as year FROM monthly_rent_billing WHERE billing_year IS NOT NULL";
        $stmt = $pdo->query($query);
        $billing_years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $years = array_merge($years, $billing_years);
    } catch (Exception $e) {}
    
    // Clean up years
    $years = array_filter($years); // Remove null values
    $years = array_map('intval', $years); // Convert to integers
    $years = array_unique($years); // Remove duplicates
    
    if (empty($years)) {
        $years = [date('Y')];
    }
    
    rsort($years); // Sort descending
    return $years;
}

// Function to get available months for a year
function getAvailableMonths($pdo, $year) {
    $months = [];
    
    try {
        // From payment logs
        $query = "SELECT DISTINCT MONTH(payment_date) as month FROM market_payment_logs WHERE YEAR(payment_date) = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        $payment_months = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $months = array_merge($months, $payment_months);
    } catch (Exception $e) {}
    
    try {
        // From rental registration
        $query = "SELECT DISTINCT MONTH(created_at) as month FROM rental_registration WHERE YEAR(created_at) = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        $reg_months = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $months = array_merge($months, $reg_months);
    } catch (Exception $e) {}
    
    try {
        // From monthly rent billing
        $query = "SELECT DISTINCT billing_month as month FROM monthly_rent_billing WHERE billing_year = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        $billing_months = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $months = array_merge($months, $billing_months);
    } catch (Exception $e) {}
    
    // Clean up months
    $months = array_filter($months); // Remove null values
    $months = array_map('intval', $months); // Convert to integers
    $months = array_unique($months); // Remove duplicates
    
    if (empty($months)) {
        $months = range(1, 12);
    }
    
    sort($months); // Sort ascending
    return $months;
}

// Handle get_years action
if ($action === 'get_years') {
    try {
        $years = getAvailableYears($pdo);
        echo json_encode([
            'status' => 'success',
            'years' => $years
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to get years: ' . $e->getMessage(),
            'years' => []
        ]);
        exit;
    }
}

// Handle get_months action
if ($action === 'get_months') {
    try {
        if (empty($year)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Year parameter is required',
                'months' => []
            ]);
            exit;
        }
        
        $months = getAvailableMonths($pdo, $year);
        echo json_encode([
            'status' => 'success',
            'months' => $months,
            'year' => $year
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to get months: ' . $e->getMessage(),
            'months' => []
        ]);
        exit;
    }
}

// MAIN DASHBOARD DATA =============================================
try {
    // Initialize totals
    $totals = [
        'total_citizens' => 0,
        'active_stalls' => 0,
        'available_stalls' => 0,
        'active_renters' => 0,
        'monthly_revenue' => 0,
        'total_contract_value' => 0,
        'this_month_payments' => 0,
        'today_payments' => 0,
        'overdue_payments' => 0,
        'pending_applications' => 0
    ];
    
    // 1. Get Total Stalls
    try {
        $query = "SELECT COUNT(*) as total FROM stalls";
        $stmt = $pdo->query($query);
        $totals['total_citizens'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $totals['total_citizens'] = 0;
    }
    
    // 2. Get Active Stalls (occupied)
    try {
        $query = "SELECT COUNT(*) as total FROM stalls WHERE status = 'occupied'";
        $stmt = $pdo->query($query);
        $totals['active_stalls'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $totals['active_stalls'] = 0;
    }
    
    // 3. Get Available Stalls
    try {
        $query = "SELECT COUNT(*) as total FROM stalls WHERE status = 'available'";
        $stmt = $pdo->query($query);
        $totals['available_stalls'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $totals['available_stalls'] = 0;
    }
    
    // 4. Get Active Renters
    try {
        $query = "SELECT COUNT(*) as total FROM renter_owner WHERE status = 'active'";
        $stmt = $pdo->query($query);
        $totals['active_renters'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $totals['active_renters'] = 0;
    }
    
    // 5. Get Monthly Revenue (from active rent stalls)
    try {
        $query = "SELECT COALESCE(SUM(monthly_rent), 0) as total FROM rent_stall WHERE status = 'active'";
        $stmt = $pdo->query($query);
        $totals['monthly_revenue'] = (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        $totals['monthly_revenue'] = 0;
    }
    
    // 6. Get Total Contract Value (from rent totals)
    try {
        $query = "SELECT COALESCE(SUM(monthly_totals), 0) as total FROM rent_totals WHERE status = 'active'";
        $stmt = $pdo->query($query);
        $totals['total_contract_value'] = (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        $totals['total_contract_value'] = 0;
    }
    
    // 7. Get This Month's Payments
    $startDate = date("Y-m-01", strtotime("$year-$month-01"));
    $endDate = date("Y-m-t", strtotime($startDate));
    
    try {
        $query = "SELECT COALESCE(SUM(amount_paid), 0) as total FROM market_payment_logs WHERE payment_date BETWEEN ? AND ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $totals['this_month_payments'] = (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        $totals['this_month_payments'] = 0;
    }
    
    // 8. Get Today's Payments
    $today = date('Y-m-d');
    try {
        $query = "SELECT COALESCE(SUM(amount_paid), 0) as total FROM market_payment_logs WHERE DATE(payment_date) = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$today]);
        $totals['today_payments'] = (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        $totals['today_payments'] = 0;
    }
    
    // 9. Get Overdue Payments
    try {
        $query = "SELECT COALESCE(SUM(total_amount_due), 0) as total FROM monthly_rent_billing WHERE payment_status = 'overdue'";
        $stmt = $pdo->query($query);
        $totals['overdue_payments'] = (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        $totals['overdue_payments'] = 0;
    }
    
    // 10. Get Pending Applications
    try {
        $query = "SELECT COUNT(*) as total FROM rental_registration WHERE application_status IN ('pending', 'paying')";
        $stmt = $pdo->query($query);
        $totals['pending_applications'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $totals['pending_applications'] = 0;
    }
    
    // 11. Calculate Metrics
    $metrics = [
        'occupancy_rate' => 0,
        'collection_rate' => 0
    ];
    
    if ($totals['total_citizens'] > 0) {
        $metrics['occupancy_rate'] = round(($totals['active_stalls'] / $totals['total_citizens']) * 100, 2);
    }
    
    if ($totals['monthly_revenue'] > 0) {
        $metrics['collection_rate'] = round(($totals['this_month_payments'] / $totals['monthly_revenue']) * 100, 2);
    }
    
    // 12. Get Revenue Trend for selected year
    $revenue_trend = [];
    try {
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
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        $trend_data = $stmt->fetchAll();
        
        foreach ($trend_data as $row) {
            $revenue_trend[] = [
                'month' => $row['month_name'],
                'revenue' => (float)$row['revenue']
            ];
        }
    } catch (Exception $e) {
        // If error, return empty trend
        $revenue_trend = [];
    }
    
    // 13. Get Business Types Distribution
    $business_types = [];
    try {
        $query = "
            SELECT 
                COALESCE(business_type, 'Not Specified') as business_type,
                COUNT(*) as count
            FROM rent_stall 
            WHERE status = 'active'
            GROUP BY business_type
            ORDER BY count DESC
        ";
        
        $stmt = $pdo->query($query);
        $business_types = $stmt->fetchAll();
    } catch (Exception $e) {
        $business_types = [];
    }
    
    // 14. Get Recent Payments (Last 10)
    $recent_payments = [];
    try {
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
        
        $stmt = $pdo->query($query);
        $recent_payments = $stmt->fetchAll();
    } catch (Exception $e) {
        $recent_payments = [];
    }
    
    // 15. Get Top Performing Stalls
    $top_stalls = [];
    try {
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
        
        $stmt = $pdo->query($query);
        $top_stalls = $stmt->fetchAll();
    } catch (Exception $e) {
        $top_stalls = [];
    }
    
    // Prepare final response
    $response = [
        'status' => 'success',
        'totals' => $totals,
        'metrics' => $metrics,
        'revenue_trend' => $revenue_trend,
        'business_types' => $business_types,
        'recent_payments' => $recent_payments,
        'top_stalls' => $top_stalls,
        'selected_year' => $year,
        'selected_month' => $month,
        'timestamp' => date('Y-m-d H:i:s'),
        'data_source' => 'database'
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // If any error occurs, return empty data structure
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch dashboard data: ' . $e->getMessage(),
        'totals' => [
            'total_citizens' => 0,
            'active_stalls' => 0,
            'available_stalls' => 0,
            'active_renters' => 0,
            'monthly_revenue' => 0,
            'total_contract_value' => 0,
            'this_month_payments' => 0,
            'today_payments' => 0,
            'overdue_payments' => 0,
            'pending_applications' => 0
        ],
        'metrics' => [
            'occupancy_rate' => 0,
            'collection_rate' => 0
        ],
        'revenue_trend' => [],
        'business_types' => [],
        'recent_payments' => [],
        'top_stalls' => [],
        'selected_year' => $year,
        'selected_month' => $month,
        'timestamp' => date('Y-m-d H:i:s'),
        'data_source' => 'error'
    ], JSON_PRETTY_PRINT);
}
?>