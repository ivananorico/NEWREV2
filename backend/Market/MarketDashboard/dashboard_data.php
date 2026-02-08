<?php
// dashboard_data.php - Market Dashboard Backend
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

// Include the database connection file
require_once __DIR__ . "/../../../db/Market/market_db.php";

// Check if connection was successful
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'message' => 'Unable to connect to database'
    ]);
    exit;
}

// Get request parameters
$action = $_GET['action'] ?? '';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Handle get_years action
if ($action === 'get_years') {
    try {
        $years = [];
        
        // Get years from various tables
        $queries = [
            "SELECT DISTINCT YEAR(created_at) as year FROM rental_registration WHERE created_at IS NOT NULL",
            "SELECT DISTINCT YEAR(start_date) as year FROM rent_stall WHERE start_date IS NOT NULL",
            "SELECT DISTINCT YEAR(payment_date) as year FROM market_payment_logs WHERE payment_date IS NOT NULL"
        ];
        
        foreach ($queries as $query) {
            $stmt = $pdo->query($query);
            if ($stmt) {
                $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $years = array_merge($years, array_filter($result));
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
        
        // If still no years, add default years
        if (empty($years)) {
            $years = [$currentYear, $currentYear - 1];
        }
        
        // Sort descending
        rsort($years);
        
        echo json_encode([
            'success' => true,
            'years' => array_values($years)
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => true,
            'years' => [date('Y'), date('Y') - 1],
            'message' => 'Using default years'
        ]);
        exit;
    }
}

// Main dashboard data action
if ($action === 'dashboard') {
    try {
        // Initialize response structure
        $response = [
            'success' => true,
            'selected_year' => $year,
            'available_years' => [],
            'market_stats' => [],
            'revenue_stats' => [],
            'quarterly_analysis' => [],
            'top_stalls' => [],
            'payment_analysis' => [],
            'recent_activities' => [],
            'business_distribution' => [],
            'current_quarter' => getCurrentQuarter(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // 1. Get available years
        try {
            $stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM rental_registration WHERE created_at IS NOT NULL 
                                UNION 
                                SELECT DISTINCT YEAR(start_date) as year FROM rent_stall WHERE start_date IS NOT NULL 
                                UNION 
                                SELECT DISTINCT YEAR(payment_date) as year FROM market_payment_logs WHERE payment_date IS NOT NULL 
                                ORDER BY year DESC");
            if ($stmt) {
                $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $response['available_years'] = array_values(array_filter(array_map('intval', $years)));
            }
        } catch (Exception $e) {
            // If query fails, use default years
            $response['available_years'] = [$year, $year - 1];
        }
        
        // 2. Market Stats
        $response['market_stats'] = getMarketStats($pdo, $year);
        
        // 3. Revenue Stats
        $response['revenue_stats'] = getRevenueStats($pdo, $year);
        
        // 4. Quarterly Analysis
        $response['quarterly_analysis'] = getQuarterlyAnalysis($pdo, $year);
        
        // 5. Top Stalls
        $response['top_stalls'] = getTopStalls($pdo, $year);
        
        // 6. Payment Analysis
        $response['payment_analysis'] = getPaymentAnalysis($pdo, $year);
        
        // 7. Recent Activities
        $response['recent_activities'] = getRecentActivities($pdo, $year);
        
        // 8. Business Distribution
        $response['business_distribution'] = getBusinessDistribution($pdo);
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        error_log("Dashboard error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Failed to load dashboard data',
            'message' => $e->getMessage()
        ]);
    }
} else {
    // Invalid action
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action',
        'message' => 'Please specify a valid action (get_years or dashboard)'
    ]);
}

// Helper functions
function getCurrentQuarter() {
    $month = date('n');
    if ($month >= 1 && $month <= 3) return 'Q1';
    if ($month >= 4 && $month <= 6) return 'Q2';
    if ($month >= 7 && $month <= 9) return 'Q3';
    return 'Q4';
}

function getMarketStats($pdo, $year) {
    $stats = [
        'total_stalls' => 0,
        'active_stalls' => 0,
        'available_stalls' => 0,
        'active_renters' => 0,
        'pending_applications' => 0,
        'stalls_by_class' => []
    ];
    
    try {
        // Total stalls
        $query = "SELECT COUNT(*) as total_stalls FROM stalls";
        $stmt = $pdo->query($query);
        if ($stmt) {
            $row = $stmt->fetch();
            $stats['total_stalls'] = (int)($row['total_stalls'] ?? 0);
        }
        
        // Active/Occupied stalls
        $query = "SELECT COUNT(*) as active_stalls FROM stalls WHERE status = 'occupied'";
        $stmt = $pdo->query($query);
        if ($stmt) {
            $row = $stmt->fetch();
            $stats['active_stalls'] = (int)($row['active_stalls'] ?? 0);
        }
        
        // Available stalls
        $stats['available_stalls'] = $stats['total_stalls'] - $stats['active_stalls'];
        
        // Active renters
        $query = "SELECT COUNT(*) as active_renters FROM renter_owner WHERE status = 'active'";
        $stmt = $pdo->query($query);
        if ($stmt) {
            $row = $stmt->fetch();
            $stats['active_renters'] = (int)($row['active_renters'] ?? 0);
        }
        
        // Pending applications
        $query = "SELECT COUNT(*) as pending_applications FROM rental_registration WHERE application_status IN ('pending', 'paying')";
        $stmt = $pdo->query($query);
        if ($stmt) {
            $row = $stmt->fetch();
            $stats['pending_applications'] = (int)($row['pending_applications'] ?? 0);
        }
        
        // Stalls by class
        $query = "SELECT 
                    sr.class_name,
                    COUNT(s.id) as stall_count,
                    COALESCE(SUM(s.price), 0) as total_value
                  FROM stalls s
                  JOIN stall_rights sr ON s.class_id = sr.class_id
                  GROUP BY sr.class_name
                  ORDER BY sr.class_name";
        $stmt = $pdo->query($query);
        if ($stmt) {
            $stats['stalls_by_class'] = $stmt->fetchAll() ?: [];
        }
        
    } catch (Exception $e) {
        error_log("Market stats error: " . $e->getMessage());
    }
    
    return $stats;
}

function getRevenueStats($pdo, $year) {
    $stats = [
        'annual' => [
            'total_annual_revenue' => 0,
            'total_monthly_revenue' => 0,
            'food_revenue' => 0,
            'retail_revenue' => 0,
            'service_revenue' => 0,
            'active_contracts' => 0
        ],
        'quarterly' => [],
        'current_quarter' => [
            'current_quarter_target' => 0,
            'current_quarter_collected' => 0
        ],
        'outstanding' => [
            'total_outstanding' => 0,
            'pending_balance' => 0,
            'overdue_balance' => 0,
            'outstanding_bills' => 0
        ]
    ];
    
    try {
        // Annual revenue
        $query = "SELECT 
                    COALESCE(SUM(rs.monthly_rent * 12), 0) as total_annual_revenue,
                    COALESCE(SUM(rs.monthly_rent), 0) as total_monthly_revenue,
                    COALESCE(SUM(CASE 
                        WHEN rs.business_type = 'Food' THEN rs.monthly_rent * 12 
                        ELSE 0 
                    END), 0) as food_revenue,
                    COALESCE(SUM(CASE 
                        WHEN rs.business_type = 'Retail' THEN rs.monthly_rent * 12 
                        ELSE 0 
                    END), 0) as retail_revenue,
                    COALESCE(SUM(CASE 
                        WHEN rs.business_type = 'Service' THEN rs.monthly_rent * 12 
                        ELSE 0 
                    END), 0) as service_revenue,
                    COUNT(DISTINCT rs.id) as active_contracts
                  FROM rent_stall rs
                  WHERE rs.status = 'active'
                  AND YEAR(rs.start_date) <= ?
                  AND (rs.end_date IS NULL OR YEAR(rs.end_date) >= ?)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year, $year]);
        if ($stmt) {
            $row = $stmt->fetch();
            if ($row) {
                $stats['annual'] = [
                    'total_annual_revenue' => (float)$row['total_annual_revenue'],
                    'total_monthly_revenue' => (float)$row['total_monthly_revenue'],
                    'food_revenue' => (float)$row['food_revenue'],
                    'retail_revenue' => (float)$row['retail_revenue'],
                    'service_revenue' => (float)$row['service_revenue'],
                    'active_contracts' => (int)$row['active_contracts']
                ];
            }
        }
        
        // Quarterly data
        $quarterlyData = [];
        for ($q = 1; $q <= 4; $q++) {
            $quarter = "Q$q";
            $startMonth = ($q - 1) * 3 + 1;
            $endMonth = $q * 3;
            
            // Quarterly target (sum of monthly rents * 3)
            $targetQuery = "SELECT COALESCE(SUM(rs.monthly_rent * 3), 0) as quarterly_target 
                           FROM rent_stall rs 
                           WHERE rs.status = 'active'";
            $targetStmt = $pdo->query($targetQuery);
            $target = $targetStmt ? (float)$targetStmt->fetchColumn() : 0;
            
            // Collected revenue
            $collectedQuery = "SELECT COALESCE(SUM(amount_paid), 0) as collected 
                              FROM market_payment_logs 
                              WHERE YEAR(payment_date) = ?
                              AND MONTH(payment_date) BETWEEN ? AND ?";
            $collectedStmt = $pdo->prepare($collectedQuery);
            $collectedStmt->execute([$year, $startMonth, $endMonth]);
            $collected = $collectedStmt ? (float)$collectedStmt->fetchColumn() : 0;
            
            $quarterlyData[] = [
                'quarter' => $quarter,
                'quarterly_target' => $target,
                'collected_revenue' => $collected,
                'collection_rate' => $target > 0 ? round(($collected / $target) * 100, 1) : 0
            ];
        }
        $stats['quarterly'] = $quarterlyData;
        
        // Current quarter performance
        $currentQuarter = getCurrentQuarter();
        $quarterNum = substr($currentQuarter, 1);
        $startMonth = ($quarterNum - 1) * 3 + 1;
        $endMonth = $quarterNum * 3;
        
        $query = "SELECT 
                    COALESCE(SUM(rs.monthly_rent * 3), 0) as current_quarter_target
                  FROM rent_stall rs
                  WHERE rs.status = 'active'";
        
        $targetStmt = $pdo->query($query);
        $target = $targetStmt ? (float)$targetStmt->fetchColumn() : 0;
        
        $collectedQuery = "SELECT COALESCE(SUM(amount_paid), 0) as current_quarter_collected 
                          FROM market_payment_logs 
                          WHERE YEAR(payment_date) = ?
                          AND MONTH(payment_date) BETWEEN ? AND ?";
        $collectedStmt = $pdo->prepare($collectedQuery);
        $collectedStmt->execute([$year, $startMonth, $endMonth]);
        $collected = $collectedStmt ? (float)$collectedStmt->fetchColumn() : 0;
        
        $stats['current_quarter'] = [
            'current_quarter_target' => $target,
            'current_quarter_collected' => $collected
        ];
        
        // Outstanding payments
        $query = "SELECT 
                    COALESCE(SUM(mrb.total_amount_due), 0) as total_outstanding,
                    COALESCE(SUM(CASE WHEN mrb.payment_status = 'pending' THEN mrb.total_amount_due ELSE 0 END), 0) as pending_balance,
                    COALESCE(SUM(CASE WHEN mrb.payment_status = 'overdue' THEN mrb.total_amount_due ELSE 0 END), 0) as overdue_balance,
                    COUNT(CASE WHEN mrb.payment_status IN ('pending', 'overdue') THEN 1 END) as outstanding_bills
                  FROM monthly_rent_billing mrb
                  WHERE YEAR(mrb.due_date) = ?
                  AND mrb.payment_status IN ('pending', 'overdue')";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        if ($stmt) {
            $row = $stmt->fetch();
            if ($row) {
                $stats['outstanding'] = [
                    'total_outstanding' => (float)$row['total_outstanding'],
                    'pending_balance' => (float)$row['pending_balance'],
                    'overdue_balance' => (float)$row['overdue_balance'],
                    'outstanding_bills' => (int)$row['outstanding_bills']
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Revenue stats error: " . $e->getMessage());
    }
    
    return $stats;
}

function getQuarterlyAnalysis($pdo, $year) {
    $analysis = [];
    
    try {
        for ($q = 1; $q <= 4; $q++) {
            $quarter = "Q$q";
            $startMonth = ($q - 1) * 3 + 1;
            $endMonth = $q * 3;
            
            // Target revenue
            $targetQuery = "SELECT COALESCE(SUM(rs.monthly_rent * 3), 0) as quarterly_target 
                           FROM rent_stall rs 
                           WHERE rs.status = 'active'";
            $targetStmt = $pdo->query($targetQuery);
            $target = $targetStmt ? (float)$targetStmt->fetchColumn() : 0;
            
            // Collected revenue
            $collectedQuery = "SELECT COALESCE(SUM(amount_paid), 0) as collected 
                              FROM market_payment_logs 
                              WHERE YEAR(payment_date) = ?
                              AND MONTH(payment_date) BETWEEN ? AND ?";
            $collectedStmt = $pdo->prepare($collectedQuery);
            $collectedStmt->execute([$year, $startMonth, $endMonth]);
            $collected = $collectedStmt ? (float)$collectedStmt->fetchColumn() : 0;
            
            // Payment counts
            $paymentQuery = "SELECT 
                              COUNT(*) as total_payments,
                              SUM(CASE WHEN DAY(payment_date) <= 10 THEN 1 ELSE 0 END) as early_payments,
                              SUM(CASE WHEN DAY(payment_date) > 10 AND DAY(payment_date) <= 20 THEN 1 ELSE 0 END) as ontime_payments,
                              SUM(CASE WHEN DAY(payment_date) > 20 THEN 1 ELSE 0 END) as late_payments
                            FROM market_payment_logs 
                            WHERE YEAR(payment_date) = ?
                            AND MONTH(payment_date) BETWEEN ? AND ?";
            
            $paymentStmt = $pdo->prepare($paymentQuery);
            $paymentStmt->execute([$year, $startMonth, $endMonth]);
            $paymentData = $paymentStmt ? $paymentStmt->fetch() : [
                'total_payments' => 0,
                'early_payments' => 0,
                'ontime_payments' => 0,
                'late_payments' => 0
            ];
            
            $analysis[] = [
                'quarter' => $quarter,
                'year' => $year,
                'quarterly_target' => $target,
                'collected_revenue' => $collected,
                'collection_rate' => $target > 0 ? round(($collected / $target) * 100, 1) : 0,
                'total_payments' => (int)($paymentData['total_payments'] ?? 0),
                'early_payments' => (int)($paymentData['early_payments'] ?? 0),
                'ontime_payments' => (int)($paymentData['ontime_payments'] ?? 0),
                'late_payments' => (int)($paymentData['late_payments'] ?? 0)
            ];
        }
    } catch (Exception $e) {
        error_log("Quarterly analysis error: " . $e->getMessage());
    }
    
    return $analysis;
}

function getTopStalls($pdo, $year) {
    try {
        $query = "SELECT 
                    rs.business_name,
                    CONCAT(ro.first_name, ' ', ro.last_name) as renter_name,
                    rs.business_type,
                    s.name as stall_number,
                    sr.class_name as stall_class,
                    rs.monthly_rent,
                    (rs.monthly_rent * 12) as annual_revenue,
                    DATE_FORMAT(rs.start_date, '%Y-%m-%d') as start_date,
                    rs.status
                  FROM rent_stall rs
                  JOIN renter_owner ro ON rs.renter_id = ro.id
                  JOIN stalls s ON rs.stall_id = s.id
                  JOIN stall_rights sr ON s.class_id = sr.class_id
                  WHERE rs.status = 'active'
                  AND YEAR(rs.start_date) <= ?
                  AND (rs.end_date IS NULL OR YEAR(rs.end_date) >= ?)
                  ORDER BY rs.monthly_rent DESC
                  LIMIT 10";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year, $year]);
        
        return $stmt ? $stmt->fetchAll() : [];
        
    } catch (Exception $e) {
        error_log("Top stalls error: " . $e->getMessage());
        return [];
    }
}

function getPaymentAnalysis($pdo, $year) {
    $analysis = [
        'payment_timing' => [],
        'discount_penalty' => [],
        'delinquency' => []
    ];
    
    try {
        // Payment timing analysis
        $query = "SELECT 
                    CASE 
                        WHEN DAY(payment_date) <= 10 THEN 'Early Payment (1-10)'
                        WHEN DAY(payment_date) <= 20 THEN 'On Time (11-20)'
                        ELSE 'Late Payment (21+)'
                    END as payment_timing,
                    COUNT(*) as count,
                    SUM(amount_paid) as amount
                  FROM market_payment_logs 
                  WHERE YEAR(payment_date) = ?
                  GROUP BY 
                    CASE 
                        WHEN DAY(payment_date) <= 10 THEN 'Early Payment (1-10)'
                        WHEN DAY(payment_date) <= 20 THEN 'On Time (11-20)'
                        ELSE 'Late Payment (21+)'
                    END
                  ORDER BY FIELD(payment_timing, 'Early Payment (1-10)', 'On Time (11-20)', 'Late Payment (21+)')";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        if ($stmt) {
            $analysis['payment_timing'] = $stmt->fetchAll() ?: [];
        }
        
        // Discount vs penalty analysis
        $query = "SELECT 
                    COALESCE(SUM(mrb.discount_amount), 0) as total_discounts_given,
                    COALESCE(SUM(mrb.penalty_amount), 0) as total_penalties_collected,
                    COUNT(CASE WHEN mrb.discount_amount > 0 THEN 1 END) as discount_count,
                    COUNT(CASE WHEN mrb.penalty_amount > 0 THEN 1 END) as penalty_count
                  FROM monthly_rent_billing mrb
                  WHERE YEAR(mrb.due_date) = ?
                  AND mrb.payment_status = 'paid'";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        if ($stmt) {
            $row = $stmt->fetch();
            if ($row) {
                $analysis['discount_penalty'] = [
                    'total_discounts_given' => (float)$row['total_discounts_given'],
                    'total_penalties_collected' => (float)$row['total_penalties_collected'],
                    'discount_count' => (int)$row['discount_count'],
                    'penalty_count' => (int)$row['penalty_count']
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Payment analysis error: " . $e->getMessage());
    }
    
    return $analysis;
}

function getRecentActivities($pdo, $year) {
    $activities = [
        'payments' => [],
        'registrations' => [],
        'overdue' => []
    ];
    
    try {
        // Recent payments
        $query = "SELECT 
                    mpl.receipt_number,
                    CONCAT(ro.first_name, ' ', ro.last_name) as renter_name,
                    rs.business_name,
                    rs.business_type,
                    mpl.amount_paid,
                    DATE_FORMAT(mpl.payment_date, '%Y-%m-%d') as payment_date,
                    mpl.payment_method,
                    s.name as stall_number
                  FROM market_payment_logs mpl
                  JOIN renter_owner ro ON mpl.renter_code = ro.renter_code
                  LEFT JOIN rent_stall rs ON ro.registration_id = rs.registration_id
                  LEFT JOIN stalls s ON rs.stall_id = s.id
                  WHERE YEAR(mpl.payment_date) = ?
                  ORDER BY mpl.payment_date DESC
                  LIMIT 10";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        if ($stmt) {
            $activities['payments'] = $stmt->fetchAll() ?: [];
        }
        
        // Recent registrations
        $query = "SELECT 
                    rr.stall_rights_no,
                    CONCAT(ro.first_name, ' ', ro.last_name) as renter_name,
                    ro.renter_code,
                    rr.stall_name,
                    rr.monthly_rent,
                    DATE_FORMAT(rr.created_at, '%Y-%m-%d') as created_at,
                    rr.application_status,
                    s.name as stall_number
                  FROM rental_registration rr
                  JOIN renter_owner ro ON rr.id = ro.registration_id
                  JOIN stalls s ON rr.stall_id = s.id
                  WHERE YEAR(rr.created_at) = ?
                  ORDER BY rr.created_at DESC
                  LIMIT 10";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        if ($stmt) {
            $activities['registrations'] = $stmt->fetchAll() ?: [];
        }
        
        // Overdue notices
        $query = "SELECT 
                    mrb.id,
                    CONCAT(ro.first_name, ' ', ro.last_name) as renter_name,
                    rs.business_name,
                    DATE_FORMAT(mrb.due_date, '%Y-%m-%d') as due_date,
                    mrb.total_amount_due,
                    mrb.days_late,
                    DATEDIFF(CURDATE(), mrb.due_date) as current_days_late,
                    s.name as stall_number
                  FROM monthly_rent_billing mrb
                  JOIN rent_totals rt ON mrb.rent_total_id = rt.id
                  JOIN rent_stall rs ON rt.rent_stall_id = rs.id
                  JOIN renter_owner ro ON rs.renter_id = ro.id
                  JOIN stalls s ON rs.stall_id = s.id
                  WHERE YEAR(mrb.due_date) = ?
                  AND mrb.payment_status = 'overdue'
                  AND mrb.due_date < CURDATE()
                  ORDER BY mrb.days_late DESC
                  LIMIT 10";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        if ($stmt) {
            $activities['overdue'] = $stmt->fetchAll() ?: [];
        }
        
    } catch (Exception $e) {
        error_log("Recent activities error: " . $e->getMessage());
    }
    
    return $activities;
}

function getBusinessDistribution($pdo) {
    $distribution = [
        'business_types' => [],
        'stall_classes' => []
    ];
    
    try {
        // Business types
        $query = "SELECT 
                    COALESCE(rs.business_type, 'Unknown') as business_type,
                    COUNT(*) as count,
                    COALESCE(SUM(rs.monthly_rent), 0) as total_monthly_revenue,
                    COALESCE(SUM(rs.monthly_rent * 12), 0) as total_annual_revenue,
                    COALESCE(AVG(rs.monthly_rent), 0) as avg_monthly_rent
                  FROM rent_stall rs
                  WHERE rs.status = 'active'
                  GROUP BY rs.business_type
                  ORDER BY total_monthly_revenue DESC";
        
        $stmt = $pdo->query($query);
        if ($stmt) {
            $distribution['business_types'] = $stmt->fetchAll() ?: [];
        }
        
        // Stall classes distribution
        $query = "SELECT 
                    sr.class_name,
                    COUNT(s.id) as stall_count,
                    COALESCE(SUM(CASE WHEN s.status = 'occupied' THEN 1 ELSE 0 END), 0) as occupied_count,
                    COALESCE(SUM(s.price), 0) as total_value,
                    COALESCE(AVG(s.price), 0) as avg_price
                  FROM stalls s
                  JOIN stall_rights sr ON s.class_id = sr.class_id
                  GROUP BY sr.class_name
                  ORDER BY sr.class_name";
        
        $stmt = $pdo->query($query);
        if ($stmt) {
            $distribution['stall_classes'] = $stmt->fetchAll() ?: [];
        }
        
    } catch (Exception $e) {
        error_log("Business distribution error: " . $e->getMessage());
    }
    
    return $distribution;
}
?>