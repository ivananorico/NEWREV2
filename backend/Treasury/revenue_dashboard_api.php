<?php
/**
 * Revenue Dashboard API
 * Consolidated data from all 4 databases for revenue dashboard
 */

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db_config.php';

$action = $_GET['action'] ?? '';
$year = $_GET['year'] ?? date('Y');
$system = $_GET['system'] ?? 'all'; // 'all', 'rpt', 'business', 'market'

try {
    switch ($action) {
        case 'get_revenue_data':
            getRevenueData($year, $system);
            break;
            
        case 'get_years':
            getAvailableYears($system);
            break;
            
        case 'get_systems':
            getAvailableSystems();
            break;
            
        case 'test':
            echo json_encode([
                'success' => true,
                'message' => 'API Working',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false, 
                'error' => 'Invalid action',
                'available_actions' => ['get_revenue_data', 'get_years', 'get_systems', 'test']
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}

function getRevenueData($year, $system = 'all') {
    $data = [
        'success' => true,
        'year' => $year,
        'system' => $system,
        'timestamp' => date('Y-m-d H:i:s'),
        'total' => [],
        'treasury' => [],
        'rpt' => [],
        'business' => [],
        'market' => [],
        'quarterly_data' => [],
        'system_breakdown' => [],
        'monthly_data' => []
    ];
    
    // Get data based on system filter
    if ($system === 'all' || $system === 'rpt') {
        $data['rpt'] = getRPTData($year);
    }
    
    if ($system === 'all' || $system === 'business') {
        $data['business'] = getBusinessTaxData($year);
    }
    
    if ($system === 'all' || $system === 'market') {
        $data['market'] = getMarketRentData($year);
    }
    
    // Get treasury data for all or specific system
    $data['treasury'] = getTreasuryData($year, $system);
    
    // Calculate totals
    $totalRevenue = $data['treasury']['total_revenue'] ?? 0;
    $rptTarget = $data['rpt']['annual_target'] ?? 0;
    $businessTarget = $data['business']['annual_target'] ?? 0;
    $marketTarget = $data['market']['annual_target'] ?? 0;
    $totalTarget = $rptTarget + $businessTarget + $marketTarget;
    
    $data['total'] = [
        'total_revenue' => $totalRevenue,
        'total_target' => $totalTarget,
        'collection_rate' => $totalTarget > 0 ? ($totalRevenue / $totalTarget) * 100 : 0
    ];
    
    // Get quarterly data
    $data['quarterly_data'] = getQuarterlyData($year, $system);
    
    // Get system breakdown
    $data['system_breakdown'] = getSystemBreakdownData($year);
    
    // Get monthly data
    $data['monthly_data'] = getMonthlyData($year, $system);
    
    echo json_encode($data);
}

function getTreasuryData($year, $system = 'all') {
    $conn = getTreasuryConnection();
    if (!$conn) return ['total_revenue' => 0, 'transaction_count' => 0];
    
    try {
        // Build query based on system filter
        $sql = "SELECT 
                    SUM(amount) as total_revenue,
                    COUNT(*) as transaction_count
                FROM treasury_collections 
                WHERE YEAR(payment_date) = :year";
        
        // Add system filter
        if ($system === 'rpt') {
            $sql .= " AND (revenue_source LIKE '%RPT%' OR revenue_source LIKE '%Real Property%')";
        } elseif ($system === 'business') {
            $sql .= " AND revenue_source LIKE '%Business%'";
        } elseif ($system === 'market') {
            $sql .= " AND (revenue_source LIKE '%Market%' OR revenue_source LIKE '%Market Fee%')";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':year' => $year]);
        $result = $stmt->fetch();
        
        return [
            'total_revenue' => (float)($result['total_revenue'] ?? 0),
            'transaction_count' => (int)($result['transaction_count'] ?? 0)
        ];
        
    } catch (Exception $e) {
        error_log("Treasury data error: " . $e->getMessage());
        return ['total_revenue' => 0, 'transaction_count' => 0];
    }
}

function getRPTData($year) {
    $conn = getRPTConnection();
    if (!$conn) return getDefaultSystemData();
    
    try {
        // Annual target
        $targetSql = "SELECT SUM(pt.total_annual_tax) as annual_target
                     FROM property_totals pt
                     WHERE YEAR(pt.approval_date) <= :year
                       AND pt.status = 'active'";
        
        $targetStmt = $conn->prepare($targetSql);
        $targetStmt->execute([':year' => $year]);
        $targetData = $targetStmt->fetch();
        
        // Collected revenue
        $collectedSql = "SELECT 
                            SUM(qt.total_quarterly_tax) as collected_revenue,
                            SUM(qt.penalty_amount) as total_penalties,
                            SUM(qt.discount_amount) as total_discounts,
                            COUNT(*) as transactions
                        FROM quarterly_taxes qt
                        WHERE qt.year = :year
                          AND qt.payment_status = 'paid'";
        
        $collectedStmt = $conn->prepare($collectedSql);
        $collectedStmt->execute([':year' => $year]);
        $collectedData = $collectedStmt->fetch();
        
        // Outstanding
        $outstandingSql = "SELECT 
                            SUM(qt.total_quarterly_tax) as outstanding_balance,
                            COUNT(*) as outstanding_bills
                        FROM quarterly_taxes qt
                        WHERE qt.year = :year
                          AND qt.payment_status IN ('pending', 'overdue')";
        
        $outstandingStmt = $conn->prepare($outstandingSql);
        $outstandingStmt->execute([':year' => $year]);
        $outstandingData = $outstandingStmt->fetch();
        
        $collectionRate = 0;
        if (!empty($targetData['annual_target']) && $targetData['annual_target'] > 0) {
            $collectionRate = ((float)($collectedData['collected_revenue'] ?? 0) / (float)$targetData['annual_target']) * 100;
        }
        
        return [
            'annual_target' => (float)($targetData['annual_target'] ?? 0),
            'collected_revenue' => (float)($collectedData['collected_revenue'] ?? 0),
            'total_penalties' => (float)($collectedData['total_penalties'] ?? 0),
            'total_discounts' => (float)($collectedData['total_discounts'] ?? 0),
            'transactions' => (int)($collectedData['transactions'] ?? 0),
            'outstanding_balance' => (float)($outstandingData['outstanding_balance'] ?? 0),
            'outstanding_bills' => (int)($outstandingData['outstanding_bills'] ?? 0),
            'collection_rate' => $collectionRate
        ];
        
    } catch (Exception $e) {
        error_log("RPT data error: " . $e->getMessage());
        return getDefaultSystemData();
    }
}

function getBusinessTaxData($year) {
    $conn = getBusinessTaxConnection();
    if (!$conn) return getDefaultSystemData();
    
    try {
        // Annual target
        $targetSql = "SELECT SUM(total_tax) as annual_target
                     FROM business_permits 
                     WHERE YEAR(issue_date) <= :year 
                       AND permit_status IN ('APPROVED', 'ACTIVE', 'RENEWED')";
        
        $targetStmt = $conn->prepare($targetSql);
        $targetStmt->execute([':year' => $year]);
        $targetData = $targetStmt->fetch();
        
        // Collected revenue
        $collectedSql = "SELECT 
                            SUM(bqt.total_quarterly_tax) as collected_revenue,
                            SUM(bqt.penalty_amount) as total_penalties,
                            SUM(bqt.discount_amount) as total_discounts,
                            COUNT(*) as transactions
                        FROM business_quarterly_taxes bqt
                        WHERE bqt.year = :year
                          AND bqt.payment_status = 'paid'";
        
        $collectedStmt = $conn->prepare($collectedSql);
        $collectedStmt->execute([':year' => $year]);
        $collectedData = $collectedStmt->fetch();
        
        // Outstanding
        $outstandingSql = "SELECT 
                            SUM(bqt.total_quarterly_tax) as outstanding_balance,
                            COUNT(*) as outstanding_bills
                        FROM business_quarterly_taxes bqt
                        WHERE bqt.year = :year
                          AND bqt.payment_status IN ('pending', 'overdue')";
        
        $outstandingStmt = $conn->prepare($outstandingSql);
        $outstandingStmt->execute([':year' => $year]);
        $outstandingData = $outstandingStmt->fetch();
        
        $collectionRate = 0;
        if (!empty($targetData['annual_target']) && $targetData['annual_target'] > 0) {
            $collectionRate = ((float)($collectedData['collected_revenue'] ?? 0) / (float)$targetData['annual_target']) * 100;
        }
        
        return [
            'annual_target' => (float)($targetData['annual_target'] ?? 0),
            'collected_revenue' => (float)($collectedData['collected_revenue'] ?? 0),
            'total_penalties' => (float)($collectedData['total_penalties'] ?? 0),
            'total_discounts' => (float)($collectedData['total_discounts'] ?? 0),
            'transactions' => (int)($collectedData['transactions'] ?? 0),
            'outstanding_balance' => (float)($outstandingData['outstanding_balance'] ?? 0),
            'outstanding_bills' => (int)($outstandingData['outstanding_bills'] ?? 0),
            'collection_rate' => $collectionRate
        ];
        
    } catch (Exception $e) {
        error_log("Business Tax data error: " . $e->getMessage());
        return getDefaultSystemData();
    }
}

function getMarketRentData($year) {
    $conn = getMarketRentConnection();
    if (!$conn) return getDefaultSystemData();
    
    try {
        // Annual target
        $targetSql = "SELECT SUM(mrb.total_amount_due) as annual_target
                     FROM monthly_rent_billing mrb
                     WHERE mrb.billing_year = :year";
        
        $targetStmt = $conn->prepare($targetSql);
        $targetStmt->execute([':year' => $year]);
        $targetData = $targetStmt->fetch();
        
        // Collected revenue
        $collectedSql = "SELECT 
                            SUM(mrb.total_amount_due) as collected_revenue,
                            SUM(mrb.penalty_amount) as total_penalties,
                            SUM(mrb.discount_amount) as total_discounts,
                            COUNT(*) as transactions
                        FROM monthly_rent_billing mrb
                        WHERE mrb.billing_year = :year
                          AND mrb.payment_status = 'paid'";
        
        $collectedStmt = $conn->prepare($collectedSql);
        $collectedStmt->execute([':year' => $year]);
        $collectedData = $collectedStmt->fetch();
        
        // Outstanding
        $outstandingSql = "SELECT 
                            SUM(mrb.total_amount_due) as outstanding_balance,
                            COUNT(*) as outstanding_bills
                        FROM monthly_rent_billing mrb
                        WHERE mrb.billing_year = :year
                          AND mrb.payment_status IN ('pending', 'overdue')";
        
        $outstandingStmt = $conn->prepare($outstandingSql);
        $outstandingStmt->execute([':year' => $year]);
        $outstandingData = $outstandingStmt->fetch();
        
        $collectionRate = 0;
        if (!empty($targetData['annual_target']) && $targetData['annual_target'] > 0) {
            $collectionRate = ((float)($collectedData['collected_revenue'] ?? 0) / (float)$targetData['annual_target']) * 100;
        }
        
        return [
            'annual_target' => (float)($targetData['annual_target'] ?? 0),
            'collected_revenue' => (float)($collectedData['collected_revenue'] ?? 0),
            'total_penalties' => (float)($collectedData['total_penalties'] ?? 0),
            'total_discounts' => (float)($collectedData['total_discounts'] ?? 0),
            'transactions' => (int)($collectedData['transactions'] ?? 0),
            'outstanding_balance' => (float)($outstandingData['outstanding_balance'] ?? 0),
            'outstanding_bills' => (int)($outstandingData['outstanding_bills'] ?? 0),
            'collection_rate' => $collectionRate
        ];
        
    } catch (Exception $e) {
        error_log("Market Rent data error: " . $e->getMessage());
        return getDefaultSystemData();
    }
}

function getDefaultSystemData() {
    return [
        'annual_target' => 0,
        'collected_revenue' => 0,
        'total_penalties' => 0,
        'total_discounts' => 0,
        'transactions' => 0,
        'outstanding_balance' => 0,
        'outstanding_bills' => 0,
        'collection_rate' => 0
    ];
}

function getAvailableYears($system = 'all') {
    $conn = getTreasuryConnection();
    if (!$conn) {
        // Fallback to current and previous years
        $currentYear = date('Y');
        echo json_encode([
            'success' => true, 
            'years' => [$currentYear, $currentYear - 1, $currentYear - 2],
            'note' => 'Using fallback years'
        ]);
        return;
    }
    
    try {
        // Get years from treasury collections
        $sql = "SELECT DISTINCT YEAR(payment_date) as year 
                FROM treasury_collections 
                WHERE payment_date IS NOT NULL";
        
        // Filter by system if specified
        if ($system === 'rpt') {
            $sql .= " AND (revenue_source LIKE '%RPT%' OR revenue_source LIKE '%Real Property%')";
        } elseif ($system === 'business') {
            $sql .= " AND revenue_source LIKE '%Business%'";
        } elseif ($system === 'market') {
            $sql .= " AND (revenue_source LIKE '%Market%' OR revenue_source LIKE '%Market Fee%')";
        }
        
        $sql .= " ORDER BY year DESC";
        
        $stmt = $conn->query($sql);
        $treasuryYears = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        // Also check other databases for years with data
        $allYears = $treasuryYears;
        
        // Check RPT database
        if ($system === 'all' || $system === 'rpt') {
            $rptConn = getRPTConnection();
            if ($rptConn) {
                $rptSql = "SELECT DISTINCT year FROM quarterly_taxes WHERE year IS NOT NULL UNION 
                          SELECT DISTINCT YEAR(approval_date) FROM property_totals WHERE approval_date IS NOT NULL";
                $rptStmt = $rptConn->query($rptSql);
                $rptYears = $rptStmt->fetchAll(PDO::FETCH_COLUMN, 0);
                $allYears = array_merge($allYears, $rptYears);
            }
        }
        
        // Check Business Tax database
        if ($system === 'all' || $system === 'business') {
            $businessConn = getBusinessTaxConnection();
            if ($businessConn) {
                $businessSql = "SELECT DISTINCT year FROM business_quarterly_taxes WHERE year IS NOT NULL UNION 
                               SELECT DISTINCT YEAR(issue_date) FROM business_permits WHERE issue_date IS NOT NULL";
                $businessStmt = $businessConn->query($businessSql);
                $businessYears = $businessStmt->fetchAll(PDO::FETCH_COLUMN, 0);
                $allYears = array_merge($allYears, $businessYears);
            }
        }
        
        // Check Market Rent database
        if ($system === 'all' || $system === 'market') {
            $marketConn = getMarketRentConnection();
            if ($marketConn) {
                $marketSql = "SELECT DISTINCT billing_year FROM monthly_rent_billing WHERE billing_year IS NOT NULL UNION 
                             SELECT DISTINCT YEAR(start_date) FROM rent_stall WHERE start_date IS NOT NULL";
                $marketStmt = $marketConn->query($marketSql);
                $marketYears = $marketStmt->fetchAll(PDO::FETCH_COLUMN, 0);
                $allYears = array_merge($allYears, $marketYears);
            }
        }
        
        // Remove duplicates, filter valid years, sort descending
        $allYears = array_unique($allYears);
        $allYears = array_filter($allYears, function($year) {
            return is_numeric($year) && $year > 2000 && $year <= date('Y') + 5;
        });
        
        rsort($allYears);
        
        // If no years found, use current year
        if (empty($allYears)) {
            $currentYear = date('Y');
            $allYears = [$currentYear];
        }
        
        echo json_encode([
            'success' => true, 
            'years' => array_values($allYears),
            'system' => $system,
            'count' => count($allYears)
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting years: " . $e->getMessage());
        $currentYear = date('Y');
        echo json_encode([
            'success' => true, 
            'years' => [$currentYear, $currentYear - 1, $currentYear - 2],
            'note' => 'Fallback due to error'
        ]);
    }
}

function getAvailableSystems() {
    $conn = getTreasuryConnection();
    if (!$conn) {
        echo json_encode([
            'success' => true,
            'systems' => ['all', 'rpt', 'business', 'market'],
            'note' => 'Using default systems'
        ]);
        return;
    }
    
    try {
        $sql = "SELECT DISTINCT 
                    CASE 
                        WHEN revenue_source LIKE '%RPT%' OR revenue_source LIKE '%Real Property%' THEN 'rpt'
                        WHEN revenue_source LIKE '%Business%' THEN 'business'
                        WHEN revenue_source LIKE '%Market%' OR revenue_source LIKE '%Market Fee%' THEN 'market'
                        ELSE 'other'
                    END as system_type
                FROM treasury_collections 
                WHERE system_type != 'other' 
                GROUP BY system_type
                ORDER BY system_type";
        
        $stmt = $conn->query($sql);
        $systems = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        // Always include 'all' option
        array_unshift($systems, 'all');
        $systems = array_unique($systems);
        
        echo json_encode([
            'success' => true,
            'systems' => $systems,
            'count' => count($systems)
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting systems: " . $e->getMessage());
        echo json_encode([
            'success' => true,
            'systems' => ['all', 'rpt', 'business', 'market'],
            'note' => 'Default systems due to error'
        ]);
    }
}

function getQuarterlyData($year, $system = 'all') {
    $conn = getTreasuryConnection();
    if (!$conn) return [];
    
    try {
        $sql = "SELECT 
                    CASE 
                        WHEN QUARTER(payment_date) = 1 THEN 'Q1'
                        WHEN QUARTER(payment_date) = 2 THEN 'Q2'
                        WHEN QUARTER(payment_date) = 3 THEN 'Q3'
                        WHEN QUARTER(payment_date) = 4 THEN 'Q4'
                    END as quarter,
                    SUM(CASE WHEN revenue_source LIKE '%RPT%' OR revenue_source LIKE '%Real Property%' THEN amount ELSE 0 END) as rpt_revenue,
                    SUM(CASE WHEN revenue_source LIKE '%Business%' THEN amount ELSE 0 END) as business_revenue,
                    SUM(CASE WHEN revenue_source LIKE '%Market%' OR revenue_source LIKE '%Market Fee%' THEN amount ELSE 0 END) as market_revenue,
                    SUM(amount) as total_revenue,
                    COUNT(*) as transactions
                FROM treasury_collections 
                WHERE YEAR(payment_date) = :year";
        
        if ($system === 'rpt') {
            $sql .= " AND (revenue_source LIKE '%RPT%' OR revenue_source LIKE '%Real Property%')";
        } elseif ($system === 'business') {
            $sql .= " AND revenue_source LIKE '%Business%'";
        } elseif ($system === 'market') {
            $sql .= " AND (revenue_source LIKE '%Market%' OR revenue_source LIKE '%Market Fee%')";
        }
        
        $sql .= " GROUP BY QUARTER(payment_date)
                ORDER BY QUARTER(payment_date)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':year' => $year]);
        $results = $stmt->fetchAll();
        
        // Ensure all quarters are present
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $quarterMap = [];
        foreach ($results as $row) {
            $quarterMap[$row['quarter']] = $row;
        }
        
        $completeResults = [];
        foreach ($quarters as $quarter) {
            if (isset($quarterMap[$quarter])) {
                $completeResults[] = $quarterMap[$quarter];
            } else {
                $completeResults[] = [
                    'quarter' => $quarter,
                    'rpt_revenue' => 0,
                    'business_revenue' => 0,
                    'market_revenue' => 0,
                    'total_revenue' => 0,
                    'transactions' => 0
                ];
            }
        }
        
        return $completeResults;
        
    } catch (Exception $e) {
        error_log("Quarterly data error: " . $e->getMessage());
        return [];
    }
}

function getSystemBreakdownData($year) {
    $conn = getTreasuryConnection();
    if (!$conn) return [];
    
    try {
        $sql = "SELECT 
                    CASE 
                        WHEN revenue_source LIKE '%RPT%' OR revenue_source LIKE '%Real Property%' THEN 'RPT'
                        WHEN revenue_source LIKE '%Business%' THEN 'Business Tax'
                        WHEN revenue_source LIKE '%Market%' OR revenue_source LIKE '%Market Fee%' THEN 'Market Rent'
                        ELSE 'Other'
                    END as system,
                    SUM(amount) as revenue,
                    COUNT(*) as transactions
                FROM treasury_collections 
                WHERE YEAR(payment_date) = :year
                GROUP BY 
                    CASE 
                        WHEN revenue_source LIKE '%RPT%' OR revenue_source LIKE '%Real Property%' THEN 'RPT'
                        WHEN revenue_source LIKE '%Business%' THEN 'Business Tax'
                        WHEN revenue_source LIKE '%Market%' OR revenue_source LIKE '%Market Fee%' THEN 'Market Rent'
                        ELSE 'Other'
                    END
                HAVING system != 'Other'
                ORDER BY revenue DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':year' => $year]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("System breakdown error: " . $e->getMessage());
        return [];
    }
}

function getMonthlyData($year, $system = 'all') {
    $conn = getTreasuryConnection();
    if (!$conn) return [];
    
    try {
        $sql = "SELECT 
                    MONTH(payment_date) as month,
                    SUM(amount) as revenue,
                    COUNT(*) as transactions
                FROM treasury_collections 
                WHERE YEAR(payment_date) = :year";
        
        if ($system === 'rpt') {
            $sql .= " AND (revenue_source LIKE '%RPT%' OR revenue_source LIKE '%Real Property%')";
        } elseif ($system === 'business') {
            $sql .= " AND revenue_source LIKE '%Business%'";
        } elseif ($system === 'market') {
            $sql .= " AND (revenue_source LIKE '%Market%' OR revenue_source LIKE '%Market Fee%')";
        }
        
        $sql .= " GROUP BY MONTH(payment_date)
                ORDER BY month";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':year' => $year]);
        $results = $stmt->fetchAll();
        
        // Fill missing months with zero
        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $found = false;
            foreach ($results as $row) {
                if ($row['month'] == $month) {
                    $monthlyData[] = $row;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $monthlyData[] = [
                    'month' => $month,
                    'revenue' => 0,
                    'transactions' => 0
                ];
            }
        }
        
        return $monthlyData;
        
    } catch (Exception $e) {
        error_log("Monthly data error: " . $e->getMessage());
        return [];
    }
}
?>