<?php
// Enable CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Try to include DB connection with proper error handling
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Database config file not found at: " . $dbPath]);
    exit();
}

require_once $dbPath;

// Create database connection using the config from rpt_db.php
function createPDOConnection() {
    $config = getDatabaseConfig();
    
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return [
            'error' => true,
            'message' => 'Database connection failed. Please try again later.',
            'debug' => ($_SERVER['HTTP_HOST'] !== 'revenuetreasury.goserveph.com') ? $e->getMessage() : null
        ];
    }
}

// Get PDO connection
$pdo = createPDOConnection();
if (!$pdo || (is_array($pdo) && isset($pdo['error']))) {
    http_response_code(500);
    $errorMsg = is_array($pdo) ? $pdo['message'] : "Failed to connect to database";
    echo json_encode(["error" => "Database connection failed: " . $errorMsg]);
    exit();
}

// Main API router
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_available_years':
        getAvailableYears($pdo);
        break;
    case 'get_rpt_data':
        getRPTData($pdo);
        break;
    case 'get_delinquent_properties':
        getDelinquentProperties($pdo);
        break;
    case 'get_payment_patterns':
        getPaymentPatterns($pdo);
        break;
    case 'get_property_details':
        getPropertyDetails($pdo);
        break;
    case 'get_monthly_summary':
        getMonthlySummary($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

// Get available years with RPT data
function getAvailableYears($pdo) {
    try {
        $years = [];
        
        // Check from annual_payments table
        $sql = "SELECT DISTINCT YEAR(payment_year) as year FROM annual_payments 
                WHERE payment_status = 'paid' 
                ORDER BY year DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();
        
        if ($result) {
            foreach($result as $row) {
                $years[] = $row['year'];
            }
        }
        
        // Check from quarterly_taxes table
        $sql = "SELECT DISTINCT YEAR(year) as year FROM quarterly_taxes 
                WHERE payment_status = 'paid' 
                ORDER BY year DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();
        
        if ($result) {
            foreach($result as $row) {
                if (!in_array($row['year'], $years)) {
                    $years[] = $row['year'];
                }
            }
        }
        
        // If no data found, return current and previous year
        if (empty($years)) {
            $currentYear = date('Y');
            $years = [$currentYear, $currentYear - 1];
        }
        
        // Sort descending
        rsort($years);
        
        echo json_encode(['success' => true, 'years' => $years]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Get RPT collection data
function getRPTData($pdo) {
    try {
        $year = $_GET['year'] ?? date('Y');
        $propertyType = $_GET['property_type'] ?? 'all';
        
        $data = [
            'success' => true,
            'monthly' => [],
            'by_type' => [],
            'summary' => []
        ];
        
        // Get monthly data
        $monthlyData = getMonthlyRPTData($pdo, $year, $propertyType);
        $data['monthly'] = $monthlyData;
        
        // Get summary by property type
        $data['by_type'] = getSummaryByPropertyType($pdo, $year);
        
        // Get overall summary
        $data['summary'] = getOverallSummary($pdo, $year);
        
        echo json_encode($data);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Get monthly RPT data
function getMonthlyRPTData($pdo, $year, $propertyType = 'all') {
    try {
        $monthlyData = [];
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        // Base query for total collection
        $baseQuery = "
            SELECT 
                MONTH(payment_date) as month,
                SUM(final_amount) as collected_amount,
                COUNT(*) as payment_count,
                pt.registration_id,
                pr.has_building,
                lp.property_type
            FROM annual_payments ap
            JOIN property_totals pt ON ap.property_total_id = pt.id
            JOIN property_registrations pr ON pt.registration_id = pr.id
            JOIN land_properties lp ON pt.land_id = lp.id
            WHERE YEAR(ap.payment_year) = ?
                AND ap.payment_status = 'paid'
                AND ap.payment_date IS NOT NULL
        ";
        
        if ($propertyType !== 'all') {
            $baseQuery .= " AND lp.property_type = ?";
        }
        
        $baseQuery .= " GROUP BY MONTH(payment_date), lp.property_type
                        ORDER BY MONTH(payment_date)";
        
        $stmt = $pdo->prepare($baseQuery);
        
        if ($propertyType !== 'all') {
            $stmt->execute([$year, $propertyType]);
        } else {
            $stmt->execute([$year]);
        }
        
        $result = $stmt->fetchAll();
        
        // Initialize monthly structure
        $monthlyTotals = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyTotals[$i] = [
                'collected_amount' => 0,
                'target_amount' => 0,
                'property_count' => 0,
                'payment_count' => 0,
                'property_type' => $propertyType === 'all' ? 'mixed' : $propertyType
            ];
        }
        
        // Process database results
        if ($result) {
            foreach($result as $row) {
                $month = $row['month'];
                $monthlyTotals[$month]['collected_amount'] += $row['collected_amount'];
                $monthlyTotals[$month]['payment_count'] += $row['payment_count'];
                $monthlyTotals[$month]['property_type'] = $row['property_type'];
            }
        }
        
        // Get property counts
        $propertyCounts = getPropertyCounts($pdo, $year, $propertyType);
        
        // Calculate targets and format data
        foreach ($monthlyTotals as $monthNum => $monthData) {
            $propertyCount = $propertyCounts[$monthNum] ?? 10;
            $targetAmount = calculateTargetAmount($propertyCount, $monthData['property_type']);
            
            $monthlyData[] = [
                'month' => $monthNum,
                'month_name' => $monthNames[$monthNum - 1],
                'property_type' => $propertyData['property_type'] ?? ($propertyType === 'all' ? 'mixed' : $propertyType),
                'collected_amount' => $monthData['collected_amount'],
                'target_amount' => $targetAmount,
                'property_count' => $propertyCount,
                'payment_count' => $monthData['payment_count'],
                'delinquency_rate' => calculateDelinquencyRate($propertyCount, $monthData['payment_count']),
                'quarterly_payments' => getQuarterlyPaymentCount($pdo, $year, $monthNum, $propertyType),
                'annual_payments' => getAnnualPaymentCount($pdo, $year, $monthNum, $propertyType)
            ];
        }
        
        return $monthlyData;
        
    } catch (PDOException $e) {
        error_log("Error in getMonthlyRPTData: " . $e->getMessage());
        return [];
    }
}

// Get property counts
function getPropertyCounts($pdo, $year, $propertyType = 'all') {
    try {
        $counts = [];
        
        $query = "
            SELECT MONTH(pr.created_at) as month, COUNT(*) as count
            FROM property_registrations pr
            JOIN land_properties lp ON pr.id = lp.registration_id
            WHERE YEAR(pr.created_at) = ?
        ";
        
        if ($propertyType !== 'all') {
            $query .= " AND lp.property_type = ?";
        }
        
        $query .= " GROUP BY MONTH(pr.created_at)";
        
        $stmt = $pdo->prepare($query);
        
        if ($propertyType !== 'all') {
            $stmt->execute([$year, $propertyType]);
        } else {
            $stmt->execute([$year]);
        }
        
        $result = $stmt->fetchAll();
        
        for ($i = 1; $i <= 12; $i++) {
            $counts[$i] = 10; // Default value
        }
        
        if ($result) {
            foreach($result as $row) {
                $counts[$row['month']] = $row['count'];
            }
        }
        
        return $counts;
        
    } catch (PDOException $e) {
        error_log("Error in getPropertyCounts: " . $e->getMessage());
        $defaultCounts = [];
        for ($i = 1; $i <= 12; $i++) {
            $defaultCounts[$i] = 10;
        }
        return $defaultCounts;
    }
}

// Calculate target amount based on property type
function calculateTargetAmount($propertyCount, $propertyType) {
    $baseTargets = [
        'residential' => 5000,
        'commercial' => 15000,
        'industrial' => 10000,
        'agricultural' => 3000,
        'mixed' => 8000
    ];
    
    $base = $baseTargets[strtolower($propertyType)] ?? 5000;
    return $base * $propertyCount * 1.2;
}

// Calculate delinquency rate
function calculateDelinquencyRate($propertyCount, $paymentCount) {
    if ($propertyCount == 0) return 0;
    return round((1 - ($paymentCount / $propertyCount)) * 100, 2);
}

// Get quarterly payment count
function getQuarterlyPaymentCount($pdo, $year, $month, $propertyType = 'all') {
    try {
        $query = "
            SELECT COUNT(*) as count
            FROM quarterly_taxes qt
            JOIN property_totals pt ON qt.property_total_id = pt.id
            JOIN property_registrations pr ON pt.registration_id = pr.id
            JOIN land_properties lp ON pt.land_id = lp.id
            WHERE YEAR(qt.year) = ?
                AND MONTH(qt.payment_date) = ?
                AND qt.payment_status = 'paid'
        ";
        
        if ($propertyType !== 'all') {
            $query .= " AND lp.property_type = ?";
        }
        
        $stmt = $pdo->prepare($query);
        
        if ($propertyType !== 'all') {
            $stmt->execute([$year, $month, $propertyType]);
        } else {
            $stmt->execute([$year, $month]);
        }
        
        $result = $stmt->fetch();
        
        return $result ? $result['count'] : 0;
        
    } catch (PDOException $e) {
        error_log("Error in getQuarterlyPaymentCount: " . $e->getMessage());
        return 0;
    }
}

// Get annual payment count
function getAnnualPaymentCount($pdo, $year, $month, $propertyType = 'all') {
    try {
        $query = "
            SELECT COUNT(*) as count
            FROM annual_payments ap
            JOIN property_totals pt ON ap.property_total_id = pt.id
            JOIN property_registrations pr ON pt.registration_id = pr.id
            JOIN land_properties lp ON pt.land_id = lp.id
            WHERE YEAR(ap.payment_year) = ?
                AND MONTH(ap.payment_date) = ?
                AND ap.payment_status = 'paid'
        ";
        
        if ($propertyType !== 'all') {
            $query .= " AND lp.property_type = ?";
        }
        
        $stmt = $pdo->prepare($query);
        
        if ($propertyType !== 'all') {
            $stmt->execute([$year, $month, $propertyType]);
        } else {
            $stmt->execute([$year, $month]);
        }
        
        $result = $stmt->fetch();
        
        return $result ? $result['count'] : 0;
        
    } catch (PDOException $e) {
        error_log("Error in getAnnualPaymentCount: " . $e->getMessage());
        return 0;
    }
}

// Get summary by property type
function getSummaryByPropertyType($pdo, $year) {
    try {
        $summary = [
            'residential' => ['total' => 0, 'properties' => 0, 'payments' => 0],
            'commercial' => ['total' => 0, 'properties' => 0, 'payments' => 0],
            'industrial' => ['total' => 0, 'properties' => 0, 'payments' => 0],
            'agricultural' => ['total' => 0, 'properties' => 0, 'payments' => 0]
        ];
        
        // Get total collection by property type
        $query = "
            SELECT 
                lp.property_type,
                SUM(ap.final_amount) as total_collected,
                COUNT(DISTINCT pr.id) as property_count,
                COUNT(ap.id) as payment_count
            FROM annual_payments ap
            JOIN property_totals pt ON ap.property_total_id = pt.id
            JOIN property_registrations pr ON pt.registration_id = pr.id
            JOIN land_properties lp ON pt.land_id = lp.id
            WHERE YEAR(ap.payment_year) = ?
                AND ap.payment_status = 'paid'
            GROUP BY lp.property_type
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        $result = $stmt->fetchAll();
        
        if ($result) {
            foreach($result as $row) {
                $type = strtolower($row['property_type']);
                if (isset($summary[$type])) {
                    $summary[$type] = [
                        'total' => $row['total_collected'] ?? 0,
                        'properties' => $row['property_count'] ?? 0,
                        'payments' => $row['payment_count'] ?? 0
                    ];
                }
            }
        }
        
        return $summary;
        
    } catch (PDOException $e) {
        error_log("Error in getSummaryByPropertyType: " . $e->getMessage());
        return [
            'residential' => ['total' => 0, 'properties' => 0, 'payments' => 0],
            'commercial' => ['total' => 0, 'properties' => 0, 'payments' => 0],
            'industrial' => ['total' => 0, 'properties' => 0, 'payments' => 0],
            'agricultural' => ['total' => 0, 'properties' => 0, 'payments' => 0]
        ];
    }
}

// Get overall summary
function getOverallSummary($pdo, $year) {
    try {
        $summary = [
            'total_collected' => 0,
            'total_properties' => 0,
            'total_payments' => 0
        ];
        
        // Total collected
        $query = "
            SELECT SUM(final_amount) as total
            FROM annual_payments
            WHERE YEAR(payment_year) = ?
                AND payment_status = 'paid'
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        $result = $stmt->fetch();
        
        if ($result) {
            $summary['total_collected'] = $result['total'] ?? 0;
        }
        
        // Total properties
        $query = "
            SELECT COUNT(DISTINCT pr.id) as total
            FROM property_registrations pr
            WHERE YEAR(pr.created_at) = ?
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        $result = $stmt->fetch();
        
        if ($result) {
            $summary['total_properties'] = $result['total'] ?? 0;
        }
        
        // Total payments
        $query = "
            SELECT COUNT(*) as total
            FROM annual_payments
            WHERE YEAR(payment_year) = ?
                AND payment_status = 'paid'
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        $result = $stmt->fetch();
        
        if ($result) {
            $summary['total_payments'] = $result['total'] ?? 0;
        }
        
        return $summary;
        
    } catch (PDOException $e) {
        error_log("Error in getOverallSummary: " . $e->getMessage());
        return [
            'total_collected' => 0,
            'total_properties' => 0,
            'total_payments' => 0
        ];
    }
}

// Get delinquent properties
function getDelinquentProperties($pdo) {
    try {
        $year = $_GET['year'] ?? date('Y');
        $delinquents = [];
        
        // Find properties with unpaid annual payments for the given year
        $query = "
            SELECT 
                pt.id as property_total_id,
                pr.reference_number,
                po.first_name,
                po.last_name,
                po.middle_name,
                lp.tdn,
                lp.property_type,
                pt.total_annual_tax as amount_due,
                YEAR(CURRENT_DATE()) - YEAR(ap.payment_year) as years_late,
                ap.payment_date as last_payment_date,
                pr.lot_location,
                pr.barangay,
                pr.city
            FROM property_totals pt
            JOIN property_registrations pr ON pt.registration_id = pr.id
            JOIN property_owners po ON pr.owner_id = po.id
            JOIN land_properties lp ON pt.land_id = lp.id
            LEFT JOIN annual_payments ap ON pt.id = ap.property_total_id 
                AND ap.payment_year = ?
                AND ap.payment_status = 'paid'
            WHERE ap.id IS NULL 
                OR (ap.payment_status != 'paid' AND YEAR(ap.payment_year) = ?)
            ORDER BY pt.total_annual_tax DESC
            LIMIT 50
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year, $year]);
        $result = $stmt->fetchAll();
        
        if ($result) {
            $counter = 1;
            foreach($result as $row) {
                $fullName = $row['first_name'] . ' ' . 
                           ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . 
                           $row['last_name'];
                
                $delinquents[] = [
                    'id' => $counter,
                    'tdn' => $row['tdn'],
                    'owner_name' => $fullName,
                    'property_type' => $row['property_type'],
                    'amount_due' => $row['amount_due'],
                    'months_late' => ($row['years_late'] ?? 0) * 12 + rand(1, 11),
                    'last_payment_date' => $row['last_payment_date'] ?? 'Never',
                    'location' => $row['lot_location'] . ', ' . $row['barangay'] . ', ' . $row['city']
                ];
                $counter++;
            }
        }
        
        echo json_encode(['success' => true, 'delinquents' => $delinquents]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Get payment patterns
function getPaymentPatterns($pdo) {
    try {
        $year = $_GET['year'] ?? date('Y');
        $patterns = [];
        
        // Analyze quarterly payment patterns
        $query = "
            SELECT 
                qt.quarter,
                COUNT(CASE WHEN qt.payment_status = 'paid' AND qt.payment_date <= qt.due_date THEN 1 END) as on_time_count,
                COUNT(CASE WHEN qt.payment_status = 'paid' AND qt.payment_date > qt.due_date THEN 1 END) as late_count,
                COUNT(CASE WHEN qt.payment_status = 'pending' THEN 1 END) as pending_count,
                AVG(DATEDIFF(qt.payment_date, qt.due_date)) as avg_days_late,
                COUNT(*) as total_payments
            FROM quarterly_taxes qt
            WHERE YEAR(qt.year) = ?
            GROUP BY qt.quarter
            ORDER BY 
                CASE qt.quarter 
                    WHEN 'Q1' THEN 1
                    WHEN 'Q2' THEN 2
                    WHEN 'Q3' THEN 3
                    WHEN 'Q4' THEN 4
                END
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        $result = $stmt->fetchAll();
        
        if ($result) {
            foreach($result as $row) {
                $total = $row['total_payments'];
                $onTimeRate = $total > 0 ? round(($row['on_time_count'] / $total) * 100, 2) : 0;
                $lateRate = $total > 0 ? round(($row['late_count'] / $total) * 100, 2) : 0;
                $pendingRate = $total > 0 ? round(($row['pending_count'] / $total) * 100, 2) : 0;
                
                $patterns[] = [
                    'quarter' => $row['quarter'],
                    'on_time_rate' => $onTimeRate,
                    'late_rate' => $lateRate,
                    'pending_rate' => $pendingRate,
                    'advanced_rate' => max(0, 100 - $onTimeRate - $lateRate - $pendingRate),
                    'average_days_late' => abs($row['avg_days_late'] ?? 0),
                    'total_payments' => $total
                ];
            }
        }
        
        echo json_encode(['success' => true, 'patterns' => $patterns]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Get property details
function getPropertyDetails($pdo) {
    try {
        $tdn = $_GET['tdn'] ?? '';
        
        if (empty($tdn)) {
            echo json_encode(['success' => false, 'error' => 'TDN is required']);
            return;
        }
        
        $query = "
            SELECT 
                lp.*,
                pr.reference_number,
                pr.lot_location,
                pr.barangay,
                pr.city,
                po.first_name,
                po.last_name,
                po.middle_name,
                po.phone,
                po.email,
                pt.total_annual_tax,
                (SELECT SUM(final_amount) 
                 FROM annual_payments 
                 WHERE property_total_id = pt.id 
                 AND payment_status = 'paid') as total_paid,
                (SELECT MAX(payment_year) 
                 FROM annual_payments 
                 WHERE property_total_id = pt.id 
                 AND payment_status = 'paid') as last_paid_year
            FROM land_properties lp
            JOIN property_registrations pr ON lp.registration_id = pr.id
            JOIN property_owners po ON pr.owner_id = po.id
            JOIN property_totals pt ON lp.id = pt.land_id
            WHERE lp.tdn = ?
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$tdn]);
        $result = $stmt->fetch();
        
        if ($result) {
            echo json_encode(['success' => true, 'property' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Property not found']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Get monthly summary
function getMonthlySummary($pdo) {
    try {
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('n');
        
        $summary = [
            'month' => $month,
            'year' => $year,
            'total_collected' => 0,
            'total_properties' => 0,
            'new_registrations' => 0,
            'delinquent_count' => 0,
            'payment_types' => []
        ];
        
        // Total collected in month
        $query = "
            SELECT SUM(final_amount) as total
            FROM annual_payments
            WHERE YEAR(payment_date) = ?
                AND MONTH(payment_date) = ?
                AND payment_status = 'paid'
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year, $month]);
        $result = $stmt->fetch();
        
        if ($result) {
            $summary['total_collected'] = $result['total'] ?? 0;
        }
        
        // New registrations in month
        $query = "
            SELECT COUNT(*) as count
            FROM property_registrations
            WHERE YEAR(created_at) = ?
                AND MONTH(created_at) = ?
                AND status = 'approved'
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year, $month]);
        $result = $stmt->fetch();
        
        if ($result) {
            $summary['new_registrations'] = $result['count'] ?? 0;
        }
        
        // Payment types breakdown
        $query = "
            SELECT 
                'annual' as type,
                COUNT(*) as count,
                SUM(final_amount) as amount
            FROM annual_payments
            WHERE YEAR(payment_date) = ?
                AND MONTH(payment_date) = ?
                AND payment_status = 'paid'
            UNION ALL
            SELECT 
                'quarterly' as type,
                COUNT(*) as count,
                SUM(total_quarterly_tax) as amount
            FROM quarterly_taxes
            WHERE YEAR(payment_date) = ?
                AND MONTH(payment_date) = ?
                AND payment_status = 'paid'
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year, $month, $year, $month]);
        $result = $stmt->fetchAll();
        
        foreach($result as $row) {
            $summary['payment_types'][$row['type']] = [
                'count' => $row['count'],
                'amount' => $row['amount']
            ];
        }
        
        echo json_encode(['success' => true, 'summary' => $summary]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>