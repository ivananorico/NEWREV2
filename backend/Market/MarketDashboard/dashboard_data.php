<?php
// dashboard_data.php - Market Dashboard Backend API
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

// Include database connection
require_once '../../../db/Market/market_db.php';

// Get request parameters
$action = $_GET['action'] ?? '';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');

// Function to get available years from database
function getAvailableYears($pdo) {
    try {
        // Get years from multiple tables
        $queries = [
            "SELECT DISTINCT YEAR(payment_date) as year FROM market_payment_logs WHERE payment_date IS NOT NULL",
            "SELECT DISTINCT YEAR(created_at) as year FROM rental_registration WHERE created_at IS NOT NULL",
            "SELECT DISTINCT billing_year as year FROM monthly_rent_billing WHERE billing_year IS NOT NULL",
            "SELECT DISTINCT payment_year as year FROM market_annual_payments WHERE payment_year IS NOT NULL"
        ];
        
        $allYears = [];
        
        foreach ($queries as $query) {
            $stmt = $pdo->query($query);
            $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $allYears = array_merge($allYears, $years);
        }
        
        // Remove duplicates and sort
        $uniqueYears = array_unique(array_filter($allYears));
        rsort($uniqueYears);
        
        // If no years found, use current year
        if (empty($uniqueYears)) {
            $uniqueYears = [date('Y')];
        }
        
        return $uniqueYears;
        
    } catch (PDOException $e) {
        error_log('Error getting available years: ' . $e->getMessage());
        return [date('Y')];
    }
}

// Function to get available months for a specific year
function getAvailableMonths($pdo, $year) {
    try {
        // Get months from multiple tables
        $queries = [
            "SELECT DISTINCT MONTH(payment_date) as month FROM market_payment_logs WHERE YEAR(payment_date) = ?",
            "SELECT DISTINCT MONTH(created_at) as month FROM rental_registration WHERE YEAR(created_at) = ?",
            "SELECT DISTINCT billing_month as month FROM monthly_rent_billing WHERE billing_year = ?"
        ];
        
        $allMonths = [];
        
        foreach ($queries as $query) {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$year]);
            $months = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $allMonths = array_merge($allMonths, $months);
        }
        
        // Remove duplicates and sort
        $uniqueMonths = array_unique(array_filter($allMonths));
        rsort($uniqueMonths);
        
        // If no months found, return all months (1-12)
        if (empty($uniqueMonths)) {
            return range(1, 12);
        }
        
        return $uniqueMonths;
        
    } catch (PDOException $e) {
        error_log('Error getting available months: ' . $e->getMessage());
        return range(1, 12);
    }
}

try {
    // Handle different actions
    if ($action === 'get_years') {
        $years = getAvailableYears($pdo);
        echo json_encode([
            'status' => 'success',
            'years' => $years,
            'current_year' => date('Y')
        ]);
        exit;
    }
    
    if ($action === 'get_months') {
        if (!$year) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Year parameter is required'
            ]);
            exit;
        }
        
        $months = getAvailableMonths($pdo, $year);
        echo json_encode([
            'status' => 'success',
            'months' => $months,
            'current_month' => date('n')
        ]);
        exit;
    }

    // MAIN DASHBOARD DATA =============================================
    
    // 1. Get Total Stalls
    $query = "SELECT COUNT(*) as total_stalls FROM stalls";
    $stmt = $pdo->query($query);
    $total_stalls = $stmt->fetchColumn() ?: 0;
    
    // 2. Get Active Stalls (occupied)
    $query = "SELECT COUNT(*) as active_stalls FROM stalls WHERE status = 'occupied'";
    $stmt = $pdo->query($query);
    $active_stalls = $stmt->fetchColumn() ?: 0;
    
    // 3. Get Available Stalls
    $query = "SELECT COUNT(*) as available_stalls FROM stalls WHERE status = 'available'";
    $stmt = $pdo->query($query);
    $available_stalls = $stmt->fetchColumn() ?: 0;
    
    // 4. Get Active Renters
    $query = "SELECT COUNT(*) as active_renters FROM renter_owner WHERE status = 'active'";
    $stmt = $pdo->query($query);
    $active_renters = $stmt->fetchColumn() ?: 0;
    
    // 5. Get Monthly Revenue (from active rent stalls)
    $query = "
        SELECT COALESCE(SUM(rs.monthly_rent), 0) as monthly_revenue
        FROM rent_stall rs
        WHERE rs.status = 'active'
    ";
    $stmt = $pdo->query($query);
    $monthly_revenue = $stmt->fetchColumn() ?: 0;
    
    // 6. Get Total Contract Value (from rent totals)
    $query = "
        SELECT COALESCE(SUM(monthly_totals), 0) as total_contract_value
        FROM rent_totals
        WHERE status = 'active'
    ";
    $stmt = $pdo->query($query);
    $total_contract_value = $stmt->fetchColumn() ?: 0;
    
    // 7. Get This Month's Payments
    $startDate = date("{$year}-{$month}-01");
    $endDate = date("{$year}-{$month}-t", strtotime($startDate));
    
    $query = "
        SELECT COALESCE(SUM(amount_paid), 0) as this_month_payments
        FROM market_payment_logs 
        WHERE DATE(payment_date) BETWEEN ? AND ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$startDate, $endDate]);
    $this_month_payments = $stmt->fetchColumn() ?: 0;
    
    // 8. Get Today's Payments
    $today = date('Y-m-d');
    $query = "
        SELECT COALESCE(SUM(amount_paid), 0) as today_payments
        FROM market_payment_logs 
        WHERE DATE(payment_date) = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$today]);
    $today_payments = $stmt->fetchColumn() ?: 0;
    
    // 9. Get Overdue Payments Amount (sum, not count)
    $query = "
        SELECT COALESCE(SUM(total_amount_due), 0) as overdue_payments
        FROM monthly_rent_billing 
        WHERE payment_status = 'overdue' AND due_date < CURDATE()
    ";
    $stmt = $pdo->query($query);
    $overdue_payments = $stmt->fetchColumn() ?: 0;
    
    // 10. Get Pending Applications
    $query = "
        SELECT COUNT(*) as pending_applications
        FROM rental_registration 
        WHERE application_status IN ('pending', 'paying')
    ";
    $stmt = $pdo->query($query);
    $pending_applications = $stmt->fetchColumn() ?: 0;
    
    // 11. Get Recent Payments (Last 10 payments)
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
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 12. Get Revenue Trend for selected year
    $query = "
        SELECT 
            m.month_name as month,
            m.month_num,
            COALESCE(SUM(
                CASE 
                    WHEN mrb.payment_status = 'paid' THEN mrb.base_rent 
                    ELSE 0 
                END
            ), 0) as revenue
        FROM (
            SELECT 1 as month_num, 'January' as month_name UNION SELECT 2, 'February' UNION SELECT 3, 'March'
            UNION SELECT 4, 'April' UNION SELECT 5, 'May' UNION SELECT 6, 'June'
            UNION SELECT 7, 'July' UNION SELECT 8, 'August' UNION SELECT 9, 'September'
            UNION SELECT 10, 'October' UNION SELECT 11, 'November' UNION SELECT 12, 'December'
        ) m
        LEFT JOIN monthly_rent_billing mrb ON m.month_num = mrb.billing_month AND mrb.billing_year = ?
        GROUP BY m.month_num, m.month_name
        ORDER BY m.month_num
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$year]);
    $revenue_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 13. Get Business Types Distribution
    $query = "
        SELECT 
            COALESCE(rs.business_type, 'Not Specified') as business_type,
            COUNT(*) as count
        FROM rent_stall rs
        WHERE rs.status = 'active'
        GROUP BY rs.business_type
        ORDER BY count DESC
    ";
    $stmt = $pdo->query($query);
    $business_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 14. Get Top Performing Stalls (by monthly rent)
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
    $top_stalls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 15. Calculate occupancy rate
    $occupancy_rate = $total_stalls > 0 ? ($active_stalls / $total_stalls) * 100 : 0;
    
    // 16. Calculate collection rate
    $collection_rate = $monthly_revenue > 0 ? ($this_month_payments / $monthly_revenue) * 100 : 0;
    
    // Prepare complete response
    $response = [
        'status' => 'success',
        'totals' => [
            'total_citizens' => (int)$total_stalls,
            'active_stalls' => (int)$active_stalls,
            'available_stalls' => (int)$available_stalls,
            'active_renters' => (int)$active_renters,
            'monthly_revenue' => (float)$monthly_revenue,
            'total_contract_value' => (float)$total_contract_value,
            'this_month_payments' => (float)$this_month_payments,
            'today_payments' => (float)$today_payments,
            'overdue_payments' => (float)$overdue_payments,
            'pending_applications' => (int)$pending_applications
        ],
        'metrics' => [
            'occupancy_rate' => (float)$occupancy_rate,
            'collection_rate' => (float)$collection_rate
        ],
        'revenue_trend' => $revenue_trend,
        'business_types' => $business_types,
        'recent_payments' => $recent_payments,
        'top_stalls' => $top_stalls,
        'selected_year' => $year,
        'selected_month' => $month,
        'timestamp' => date('Y-m-d H:i:s'),
        'current_date' => date('Y-m-d')
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    error_log('Market Dashboard Error: ' . $e->getMessage());
    
    // Fallback with your actual data from database
    $response = [
        'status' => 'error',
        'message' => 'Unable to fetch dashboard data: ' . $e->getMessage(),
        'debug_info' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ],
        'totals' => [
            'total_citizens' => 1,
            'active_stalls' => 1,
            'available_stalls' => 0,
            'active_renters' => 1,
            'monthly_revenue' => 5000.00,
            'total_contract_value' => 60000.00,
            'this_month_payments' => 65000.00,
            'today_payments' => 65000.00,
            'overdue_payments' => 0.00,
            'pending_applications' => 0
        ],
        'metrics' => [
            'occupancy_rate' => 100.0,
            'collection_rate' => 100.0
        ],
        'revenue_trend' => [
            ['month' => 'January', 'revenue' => 5000],
            ['month' => 'February', 'revenue' => 5000],
            ['month' => 'March', 'revenue' => 5000],
            ['month' => 'April', 'revenue' => 5000],
            ['month' => 'May', 'revenue' => 5000],
            ['month' => 'June', 'revenue' => 5000],
            ['month' => 'July', 'revenue' => 5000],
            ['month' => 'August', 'revenue' => 5000],
            ['month' => 'September', 'revenue' => 5000],
            ['month' => 'October', 'revenue' => 5000],
            ['month' => 'November', 'revenue' => 5000],
            ['month' => 'December', 'revenue' => 5000]
        ],
        'business_types' => [
            ['business_type' => 'Food', 'count' => 1]
        ],
        'recent_payments' => [
            [
                'renter_name' => 'Ivan Anorico',
                'business_name' => 'Water',
                'business_type' => 'Food',
                'stall_rights_no' => 'STALL-20260125-5610',
                'amount_paid' => 5000.00,
                'payment_date' => date('Y-m-d H:i:s'),
                'payment_method' => 'online',
                'receipt_number' => 'RCPT-20260125081546-5826'
            ]
        ],
        'top_stalls' => [
            [
                'business_name' => 'Water',
                'renter_name' => 'Ivan Anorico',
                'business_type' => 'Food',
                'monthly_rent' => 5000.00,
                'contract_value' => 60000.00
            ]
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
}
?>