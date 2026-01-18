<?php
// dashboard_data.php - Complete Dashboard Backend API
header('Content-Type: application/json');

// CORS headers
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
} else {
    header("Access-Control-Allow-Origin: *");
}
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

// Include database connection
require_once '../../../db/Market/market_db.php';

// Get time range parameter
$timeRange = $_GET['range'] ?? 'month';

try {
    // 1. Get Total Approved Citizens
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ro.id) as total_citizens
        FROM renter_owner ro
        JOIN rental_registration rr ON ro.registration_id = rr.id
        WHERE rr.application_status = 'approved'
    ");
    $stmt->execute();
    $total_citizens = $stmt->fetchColumn() ?: 0;

    // 2. Get Active Stalls (occupied)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_stalls 
        FROM stalls 
        WHERE status = 'occupied'
    ");
    $stmt->execute();
    $active_stalls = $stmt->fetchColumn() ?: 0;

    // 3. Get Available Stalls
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as available_stalls
        FROM stalls 
        WHERE status = 'available'
    ");
    $stmt->execute();
    $available_stalls = $stmt->fetchColumn() ?: 0;

    // 4. Get Total Monthly Revenue
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rr.monthly_rent), 0) as monthly_revenue
        FROM rental_registration rr
        WHERE rr.application_status = 'approved'
    ");
    $stmt->execute();
    $monthly_revenue = $stmt->fetchColumn() ?: 0;

    // 5. Get Total Contract Value
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rt.monthly_totals), 0) as total_contract_value
        FROM rent_totals rt
        WHERE rt.status = 'active'
    ");
    $stmt->execute();
    $total_contract_value = $stmt->fetchColumn() ?: 0;

    // 6. Get Pending Applications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_applications
        FROM rental_registration 
        WHERE application_status IN ('pending', 'paying')
    ");
    $stmt->execute();
    $pending_applications = $stmt->fetchColumn() ?: 0;

    // 7. Get Overdue Payments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as overdue_payments
        FROM monthly_rent_billing 
        WHERE payment_status = 'overdue'
    ");
    $stmt->execute();
    $overdue_payments = $stmt->fetchColumn() ?: 0;

    // 8. Get Total Paid Amount
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) as total_paid
        FROM market_payment_logs
    ");
    $stmt->execute();
    $total_paid = $stmt->fetchColumn() ?: 0;

    // 9. Get Today's Payments
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) as today_payments
        FROM market_payment_logs 
        WHERE DATE(payment_date) = ?
    ");
    $stmt->execute([$today]);
    $today_payments = $stmt->fetchColumn() ?: 0;

    // 10. Get This Month's Payments
    $this_month = date('Y-m-01');
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) as this_month_payments
        FROM market_payment_logs 
        WHERE payment_date >= ?
    ");
    $stmt->execute([$this_month]);
    $this_month_payments = $stmt->fetchColumn() ?: 0;

    // 11. Get Last Month's Payments
    $last_month = date('Y-m-01', strtotime('-1 month'));
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) as last_month_payments
        FROM market_payment_logs 
        WHERE payment_date >= ? AND payment_date < ?
    ");
    $stmt->execute([$last_month, $this_month]);
    $last_month_payments = $stmt->fetchColumn() ?: 0;

    // 12. Get Active Renters
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_renters
        FROM renter_owner 
        WHERE status = 'active'
    ");
    $stmt->execute();
    $active_renters = $stmt->fetchColumn() ?: 0;

    // 13. Get Recent Payments (WHO PAID THE RENT)
    $stmt = $pdo->prepare("
        SELECT 
            mpl.*,
            CONCAT(ro.first_name, ' ', ro.last_name) as renter_name,
            rs.business_name,
            rs.stall_rights_no,
            s.name as stall_name,
            rr.stall_name as rental_stall_name
        FROM market_payment_logs mpl
        JOIN renter_owner ro ON mpl.renter_code = ro.renter_code
        LEFT JOIN rent_stall rs ON ro.registration_id = rs.registration_id
        LEFT JOIN stalls s ON rs.stall_id = s.id
        LEFT JOIN rental_registration rr ON ro.registration_id = rr.id
        ORDER BY mpl.payment_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 14. Get Top Payers (Highest contributors)
    $stmt = $pdo->prepare("
        SELECT 
            ro.renter_code,
            CONCAT(ro.first_name, ' ', ro.last_name) as full_name,
            COUNT(mpl.id) as payment_count,
            SUM(mpl.amount_paid) as total_paid,
            MAX(mpl.payment_date) as last_payment_date
        FROM market_payment_logs mpl
        JOIN renter_owner ro ON mpl.renter_code = ro.renter_code
        GROUP BY ro.renter_code, ro.first_name, ro.last_name
        ORDER BY total_paid DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_payers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 15. Get Payment Methods Distribution
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(payment_method, 'Not Specified') as method,
            COUNT(*) as count,
            SUM(amount_paid) as total_amount
        FROM market_payment_logs
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->execute();
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 16. Get Recent Activities
    $stmt = $pdo->prepare("
        (SELECT 
            'payment' as type,
            CONCAT('Payment: ₱', FORMAT(amount_paid, 2), ' from ', 
                   (SELECT CONCAT(first_name, ' ', last_name) 
                    FROM renter_owner 
                    WHERE renter_code = mpl.renter_code LIMIT 1)) as description,
            (SELECT CONCAT(first_name, ' ', last_name) 
             FROM renter_owner 
             WHERE renter_code = mpl.renter_code LIMIT 1) as user,
            DATE_FORMAT(payment_date, '%h:%i %p') as time,
            payment_date as sort_date
        FROM market_payment_logs mpl
        ORDER BY payment_date DESC 
        LIMIT 3)
        
        UNION ALL
        
        (SELECT 
            'application' as type,
            CONCAT('New Application: ', stall_name) as description,
            'System' as user,
            DATE_FORMAT(created_at, '%h:%i %p') as time,
            created_at as sort_date
        FROM rental_registration 
        WHERE application_status IN ('approved', 'pending')
        ORDER BY created_at DESC 
        LIMIT 2)
        
        ORDER BY sort_date DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 17. Get Top Performing Stalls
    $stmt = $pdo->prepare("
        SELECT 
            s.name as stall_name,
            rs.business_name,
            CONCAT(ro.first_name, ' ', ro.last_name) as renter_name,
            rs.business_type,
            rr.monthly_rent,
            rt.monthly_totals as contract_value
        FROM rent_stall rs
        JOIN renter_owner ro ON rs.renter_id = ro.id
        JOIN rental_registration rr ON rs.registration_id = rr.id
        JOIN stalls s ON rs.stall_id = s.id
        LEFT JOIN rent_totals rt ON rs.registration_id = rt.registration_id AND rt.status = 'active'
        WHERE rs.status = 'active'
        ORDER BY rr.monthly_rent DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_stalls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 18. Get Business Types Distribution
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(business_type, 'Not Specified') as name,
            COUNT(*) as count
        FROM rent_stall 
        WHERE status = 'active'
        GROUP BY business_type
        ORDER BY count DESC
    ");
    $stmt->execute();
    $business_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add colors to business types
    $colors = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899', '#84cc16'];
    foreach ($business_types as $key => $type) {
        $business_types[$key]['color'] = $colors[$key % count($colors)];
    }

    // 19. Get Revenue Trend (Last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            SUM(amount_paid) as revenue
        FROM market_payment_logs 
        WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ");
    $stmt->execute();
    $revenue_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 20. Get Status Distribution
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(status, 'Unknown') as status,
            COUNT(*) as count
        FROM renter_owner 
        GROUP BY status
    ");
    $stmt->execute();
    $status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare complete response
    $response = [
        'status' => 'success',
        'totals' => [
            'total_citizens' => (int)$total_citizens,
            'active_stalls' => (int)$active_stalls,
            'available_stalls' => (int)$available_stalls,
            'monthly_revenue' => (float)$monthly_revenue,
            'total_contract_value' => (float)$total_contract_value,
            'pending_applications' => (int)$pending_applications,
            'overdue_payments' => (int)$overdue_payments,
            'total_paid' => (float)$total_paid,
            'today_payments' => (float)$today_payments,
            'this_month_payments' => (float)$this_month_payments,
            'last_month_payments' => (float)$last_month_payments,
            'active_renters' => (int)$active_renters
        ],
        'recent_payments' => $recent_payments,
        'top_payers' => $top_payers,
        'payment_methods' => $payment_methods,
        'recent_activities' => $recent_activities,
        'top_stalls' => $top_stalls,
        'business_types' => $business_types,
        'revenue_trend' => $revenue_trend,
        'status_distribution' => $status_distribution,
        'timestamp' => date('Y-m-d H:i:s'),
        'time_range' => $timeRange
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log('Market Dashboard Error: ' . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to fetch dashboard data',
        'debug' => $e->getMessage(),
        'totals' => [
            'total_citizens' => 0,
            'active_stalls' => 0,
            'available_stalls' => 0,
            'monthly_revenue' => 0,
            'total_contract_value' => 0,
            'pending_applications' => 0,
            'overdue_payments' => 0,
            'total_paid' => 0,
            'today_payments' => 0,
            'this_month_payments' => 0,
            'last_month_payments' => 0,
            'active_renters' => 0
        ],
        'recent_payments' => [],
        'top_payers' => [],
        'payment_methods' => [],
        'recent_activities' => [],
        'top_stalls' => [],
        'business_types' => [],
        'revenue_trend' => [],
        'status_distribution' => []
    ]);
}
?>