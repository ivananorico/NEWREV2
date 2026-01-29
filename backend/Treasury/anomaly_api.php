<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Database configurations
$configs = [
    'rpt' => [
        'host' => 'localhost:3307',
        'dbname' => 'rpt',
        'username' => 'root',
        'password' => ''
    ],
    'business' => [
        'host' => 'localhost:3307',
        'dbname' => 'business_tax',
        'username' => 'root',
        'password' => ''
    ],
    'market' => [
        'host' => 'localhost:3307',
        'dbname' => 'market_rent',
        'username' => 'root',
        'password' => ''
    ]
];

function getConnection($dbname) {
    global $configs;
    $config = $configs[$dbname] ?? null;
    if (!$config) return null;
    
    try {
        $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['dbname']);
        if ($conn->connect_error) {
            return null;
        }
        return $conn;
    } catch (Exception $e) {
        return null;
    }
}

function getAvailableYears() {
    $years = [];
    
    // Get years from all systems
    $systems = ['rpt', 'business', 'market'];
    
    foreach ($systems as $system) {
        $conn = getConnection($system);
        if ($conn) {
            // Try different tables based on system
            switch ($system) {
                case 'rpt':
                    $queries = [
                        "SELECT DISTINCT payment_year FROM annual_payments",
                        "SELECT DISTINCT payment_year FROM quarterly_taxes",
                        "SELECT DISTINCT YEAR(created_at) as payment_year FROM property_registrations"
                    ];
                    break;
                case 'business':
                    $queries = [
                        "SELECT DISTINCT payment_year FROM annual_payments",
                        "SELECT DISTINCT YEAR(issue_date) as payment_year FROM business_permits"
                    ];
                    break;
                case 'market':
                    $queries = [
                        "SELECT DISTINCT payment_year FROM market_annual_payments",
                        "SELECT DISTINCT YEAR(created_at) as payment_year FROM rental_registration"
                    ];
                    break;
                default:
                    $queries = [];
            }
            
            foreach ($queries as $query) {
                $result = $conn->query($query);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        if (isset($row['payment_year'])) {
                            $year = intval($row['payment_year']);
                            if ($year > 2000 && !in_array($year, $years)) {
                                $years[] = $year;
                            }
                        }
                    }
                }
            }
            $conn->close();
        }
    }
    
    if (empty($years)) {
        $years[] = date('Y');
        $years[] = date('Y') - 1;
    }
    
    $years = array_unique($years);
    rsort($years);
    
    return array_values($years);
}

function getRevenueData($year) {
    $response = [
        'rpt' => ['monthly' => []],
        'business' => ['monthly' => []],
        'market' => ['monthly' => []]
    ];
    
    // Initialize monthly data
    $monthlyData = [];
    for ($i = 1; $i <= 12; $i++) {
        $monthlyData[$i] = [
            'month' => $i,
            'month_name' => date('F', mktime(0, 0, 0, $i, 1)),
            'revenue' => 0,
            'transactions' => 0
        ];
    }
    
    // Get RPT data
    $rpt_conn = getConnection('rpt');
    if ($rpt_conn) {
        // Get monthly revenue from annual payments
        $sql = "SELECT 
                    MONTH(payment_date) as month,
                    SUM(final_amount) as revenue,
                    COUNT(*) as transactions
                FROM annual_payments 
                WHERE payment_status = 'paid' 
                AND payment_year = ?
                AND payment_date IS NOT NULL
                GROUP BY MONTH(payment_date)";
        
        $stmt = $rpt_conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $year);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $month = intval($row['month']);
                if ($month >= 1 && $month <= 12) {
                    $monthlyData[$month]['revenue'] += floatval($row['revenue'] ?? 0);
                    $monthlyData[$month]['transactions'] += intval($row['transactions'] ?? 0);
                }
            }
            $stmt->close();
        }
        $rpt_conn->close();
    }
    
    $response['rpt']['monthly'] = array_values($monthlyData);
    
    // Get Business data
    $business_conn = getConnection('business');
    if ($business_conn) {
        // Reset monthly data for business
        $businessMonthly = [];
        for ($i = 1; $i <= 12; $i++) {
            $businessMonthly[$i] = [
                'month' => $i,
                'month_name' => date('F', mktime(0, 0, 0, $i, 1)),
                'revenue' => 0,
                'transactions' => 0
            ];
        }
        
        // Get from annual payments
        $sql = "SELECT 
                    MONTH(payment_date) as month,
                    SUM(final_amount) as revenue,
                    COUNT(*) as transactions
                FROM annual_payments 
                WHERE payment_status = 'paid' 
                AND payment_year = ?
                AND payment_date IS NOT NULL
                GROUP BY MONTH(payment_date)";
        
        $stmt = $business_conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $year);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $month = intval($row['month']);
                if ($month >= 1 && $month <= 12) {
                    $businessMonthly[$month]['revenue'] = floatval($row['revenue'] ?? 0);
                    $businessMonthly[$month]['transactions'] = intval($row['transactions'] ?? 0);
                }
            }
            $stmt->close();
        }
        
        $response['business']['monthly'] = array_values($businessMonthly);
        $business_conn->close();
    }
    
    // Get Market data
    $market_conn = getConnection('market');
    if ($market_conn) {
        // Reset monthly data for market
        $marketMonthly = [];
        for ($i = 1; $i <= 12; $i++) {
            $marketMonthly[$i] = [
                'month' => $i,
                'month_name' => date('F', mktime(0, 0, 0, $i, 1)),
                'revenue' => 0,
                'transactions' => 0
            ];
        }
        
        // Get from market annual payments
        $sql = "SELECT 
                    MONTH(payment_date) as month,
                    SUM(final_amount) as revenue,
                    COUNT(*) as transactions
                FROM market_annual_payments 
                WHERE payment_status = 'paid' 
                AND payment_year = ?
                AND payment_date IS NOT NULL
                GROUP BY MONTH(payment_date)";
        
        $stmt = $market_conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $year);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $month = intval($row['month']);
                if ($month >= 1 && $month <= 12) {
                    $marketMonthly[$month]['revenue'] = floatval($row['revenue'] ?? 0);
                    $marketMonthly[$month]['transactions'] = intval($row['transactions'] ?? 0);
                }
            }
            $stmt->close();
        }
        
        $response['market']['monthly'] = array_values($marketMonthly);
        $market_conn->close();
    }
    
    return $response;
}

// Handle API requests
try {
    switch ($action) {
        case 'get_available_years':
            $years = getAvailableYears();
            echo json_encode([
                'success' => true,
                'years' => $years
            ]);
            break;
            
        case 'get_revenue_data':
            $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
            $data = getRevenueData($year);
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>