<?php
// revenue2/backend/Business/BusinessTaxDashboard/BusinessTaxDashboard.php

// Enable CORS and set JSON header FIRST
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once '../../../db/Business/business_db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get action from request
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Log for debugging
error_log("Dashboard Request: Action=$action, Year=$year");

try {
    // Validate PDO connection
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Database connection failed');
    }

    switch ($action) {
        case 'dashboard':
            getDashboardData($pdo, $year);
            break;
        case 'get_years':
            getAvailableYears($pdo);
            break;
        default:
            getDashboardData($pdo, $year);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Server error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

function getDashboardData($pdo, $year) {
    try {
        $data = [];
        $currentQuarter = getCurrentQuarter();
        
        // 1. Business Statistics
        $data['business_stats'] = getBusinessStatistics($pdo);
        
        // 2. Tax Collection Statistics
        $data['tax_stats'] = getTaxStatistics($pdo, $year);
        
        // 3. Quarterly Analysis
        $data['quarterly_analysis'] = getQuarterlyAnalysis($pdo, $year);
        
        // 4. Business Types Distribution
        $data['business_types'] = $data['business_stats']['business_by_type'] ?? [];
        
        // 5. Top Taxpayers
        $data['top_taxpayers'] = getTopTaxpayers($pdo, $year);
        
        // 6. Barangay-wise Collection
        $data['barangay_collection'] = getBarangayCollection($pdo, $year);
        
        // 7. Overdue Taxes
        $data['overdue_taxes'] = getOverdueTaxes($pdo, $year);
        
        // 8. Recent Payments
        $data['recent_payments'] = getRecentPayments($pdo);
        
        // 9. Current Configuration
        $data['config'] = getCurrentConfig($pdo);
        
        // 10. Yearly Summary
        $data['yearly_summary'] = getYearlySummary($pdo, $year);
        
        // 11. Current Quarter
        $data['current_quarter'] = $currentQuarter;
        
        // 12. Timestamp
        $data['timestamp'] = date('Y-m-d H:i:s');
        
        // 13. Available Years
        $data['available_years'] = getAvailableYearsFromData($pdo);
        
        // 14. Pending applications count
        $data['pending_applications'] = $data['business_stats']['pending_applications'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'year' => $year,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
        
    } catch (Exception $e) {
        throw new Exception('Dashboard data error: ' . $e->getMessage());
    }
}

function getBusinessStatistics($pdo) {
    $stats = [];
    
    try {
        // Total businesses - include all non-deleted statuses
        $query = "SELECT COUNT(*) as total FROM business_permits WHERE status != 'Deleted'";
        $stmt = $pdo->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_businesses'] = $result['total'] ?? 0;
        
        // Active businesses - include both Active and Approved
        $query = "SELECT COUNT(*) as active FROM business_permits WHERE status IN ('Active', 'Approved')";
        $stmt = $pdo->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['active_businesses'] = $result['active'] ?? 0;
        
        // Pending applications
        $query = "SELECT COUNT(*) as pending FROM business_permits WHERE status = 'Pending'";
        $stmt = $pdo->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['pending_applications'] = $result['pending'] ?? 0;
        
        // Businesses by type - include both Active and Approved
        $query = "SELECT business_type, COUNT(*) as count FROM business_permits WHERE status IN ('Active', 'Approved') GROUP BY business_type";
        $stmt = $pdo->query($query);
        $businessTypes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Calculate percentages
        $totalActive = $stats['active_businesses'] ?? 0;
        foreach ($businessTypes as &$type) {
            $type['percentage'] = $totalActive > 0 ? round(($type['count'] / $totalActive) * 100, 2) : 0;
        }
        
        $stats['business_by_type'] = $businessTypes;
        
        // Total capital investment - include both Active and Approved
        $query = "SELECT SUM(taxable_amount) as total_capital FROM business_permits WHERE status IN ('Active', 'Approved')";
        $stmt = $pdo->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_capital_investment'] = floatval($result['total_capital'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Business statistics error: " . $e->getMessage());
        $stats = [
            'total_businesses' => 0,
            'active_businesses' => 0,
            'pending_applications' => 0,
            'business_by_type' => [],
            'total_capital_investment' => 0
        ];
    }
    
    return $stats;
}

function getTaxStatistics($pdo, $year) {
    $stats = [];
    
    try {
        // Annual tax assessment - include both Active and Approved
        $query = "SELECT 
                    SUM(b.total_tax) as total_annual_tax,
                    SUM(b.tax_amount) as total_tax_amount,
                    SUM(b.regulatory_fees) as total_fees
                  FROM business_permits b
                  WHERE b.status IN ('Active', 'Approved')";
        $stmt = $pdo->query($query);
        $annual = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no annual tax from approved businesses, use the one business in your database
        if (floatval($annual['total_annual_tax'] ?? 0) == 0) {
            $query = "SELECT 
                        SUM(total_tax) as total_annual_tax,
                        SUM(tax_amount) as total_tax_amount,
                        SUM(regulatory_fees) as total_fees
                      FROM business_permits b
                      WHERE b.status != 'Deleted'";
            $stmt = $pdo->query($query);
            $annual = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        $stats['annual'] = [
            'total_annual_tax' => floatval($annual['total_annual_tax'] ?? 0),
            'total_tax_amount' => floatval($annual['total_tax_amount'] ?? 0),
            'total_fees' => floatval($annual['total_fees'] ?? 0)
        ];
        
        // Current quarter collection
        $currentQuarter = getCurrentQuarter();
        $query = "SELECT 
                    SUM(total_quarterly_tax) as current_quarter_paid,
                    COUNT(*) as bills_paid
                  FROM business_quarterly_taxes 
                  WHERE quarter = :quarter AND year = :year AND payment_status = 'paid'";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['quarter' => $currentQuarter, 'year' => $year]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['current_quarter'] = [
            'current_quarter_paid' => floatval($current['current_quarter_paid'] ?? 0),
            'bills_paid' => intval($current['bills_paid'] ?? 0)
        ];
        
        // Quarterly target (1/4 of annual tax)
        $stats['quarterly_target'] = floatval($stats['annual']['total_annual_tax'] ?? 0) / 4;
        
        // Outstanding balance
        $query = "SELECT 
                    SUM(CASE WHEN payment_status = 'overdue' THEN total_quarterly_tax + penalty_amount ELSE 0 END) as overdue_balance,
                    SUM(CASE WHEN payment_status = 'pending' THEN total_quarterly_tax ELSE 0 END) as pending_balance,
                    SUM(CASE WHEN payment_status IN ('overdue', 'pending') THEN 1 ELSE 0 END) as outstanding_bills
                  FROM business_quarterly_taxes 
                  WHERE year = :year";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['year' => $year]);
        $outstanding = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['outstanding'] = [
            'overdue_balance' => floatval($outstanding['overdue_balance'] ?? 0),
            'pending_balance' => floatval($outstanding['pending_balance'] ?? 0),
            'outstanding_bills' => intval($outstanding['outstanding_bills'] ?? 0),
            'total_outstanding' => floatval($outstanding['overdue_balance'] ?? 0) + floatval($outstanding['pending_balance'] ?? 0)
        ];
        
    } catch (Exception $e) {
        error_log("Tax statistics error: " . $e->getMessage());
        $stats = [
            'annual' => ['total_annual_tax' => 0, 'total_tax_amount' => 0, 'total_fees' => 0],
            'current_quarter' => ['current_quarter_paid' => 0, 'bills_paid' => 0],
            'quarterly_target' => 0,
            'outstanding' => ['overdue_balance' => 0, 'pending_balance' => 0, 'outstanding_bills' => 0, 'total_outstanding' => 0]
        ];
    }
    
    return $stats;
}

function getQuarterlyAnalysis($pdo, $year) {
    $quarters = [];
    
    try {
        $query = "SELECT 
                    quarter,
                    year,
                    SUM(total_quarterly_tax) as total_due,
                    SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END) as collected,
                    SUM(CASE WHEN payment_status = 'overdue' THEN total_quarterly_tax + penalty_amount ELSE 0 END) as overdue_amount,
                    SUM(CASE WHEN payment_status = 'pending' THEN total_quarterly_tax ELSE 0 END) as pending_amount,
                    COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count,
                    COUNT(CASE WHEN payment_status = 'overdue' THEN 1 END) as overdue_count,
                    COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
                    AVG(CASE WHEN payment_status = 'paid' THEN days_late ELSE NULL END) as avg_days_late,
                    SUM(discount_amount) as total_discounts,
                    SUM(penalty_amount) as total_penalties
                  FROM business_quarterly_taxes 
                  WHERE year = :year
                  GROUP BY quarter, year
                  ORDER BY 
                    CASE quarter 
                        WHEN 'Q1' THEN 1
                        WHEN 'Q2' THEN 2
                        WHEN 'Q3' THEN 3
                        WHEN 'Q4' THEN 4
                    END";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute(['year' => $year]);
        $quarters = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Calculate collection rate for each quarter
        foreach ($quarters as &$quarter) {
            $totalDue = floatval($quarter['total_due'] ?? 0);
            $collected = floatval($quarter['collected'] ?? 0);
            $quarter['collection_rate'] = $totalDue > 0 ? ($collected / $totalDue) * 100 : 0;
        }
        
    } catch (Exception $e) {
        error_log("Quarterly analysis error: " . $e->getMessage());
        $quarters = [];
    }
    
    return $quarters;
}

function getTopTaxpayers($pdo, $year, $limit = 10) {
    $taxpayers = [];
    
    try {
        $query = "SELECT 
                    bp.business_name,
                    bp.business_type,
                    bp.business_barangay as barangay,
                    bp.full_name as owner_name,
                    SUM(bqt.total_quarterly_tax) as total_tax_paid,
                    COUNT(bqt.id) as quarters_paid,
                    MAX(bqt.payment_date) as last_payment
                  FROM business_permits bp
                  LEFT JOIN business_quarterly_taxes bqt ON bp.id = bqt.business_permit_id
                  WHERE bqt.payment_status = 'paid' AND bqt.year = :year
                  AND bp.status IN ('Active', 'Approved')
                  GROUP BY bp.id
                  ORDER BY total_tax_paid DESC
                  LIMIT :limit";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $taxpayers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
    } catch (Exception $e) {
        error_log("Top taxpayers error: " . $e->getMessage());
        $taxpayers = [];
    }
    
    return $taxpayers;
}

function getBarangayCollection($pdo, $year) {
    $collections = [];
    
    try {
        $query = "SELECT 
                    bp.business_barangay as barangay,
                    COUNT(DISTINCT bp.id) as business_count,
                    SUM(CASE WHEN bqt.payment_status = 'paid' THEN bqt.total_quarterly_tax ELSE 0 END) as total_collection,
                    AVG(CASE WHEN bqt.payment_status = 'paid' THEN bqt.total_quarterly_tax ELSE NULL END) as avg_tax_per_business
                  FROM business_permits bp
                  LEFT JOIN business_quarterly_taxes bqt ON bp.id = bqt.business_permit_id
                  WHERE bqt.year = :year
                  AND bp.status IN ('Active', 'Approved')
                  GROUP BY bp.business_barangay
                  ORDER BY total_collection DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute(['year' => $year]);
        $collections = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
    } catch (Exception $e) {
        error_log("Barangay collection error: " . $e->getMessage());
        $collections = [];
    }
    
    return $collections;
}

function getOverdueTaxes($pdo, $year, $limit = 15) {
    $overdue = [];
    
    try {
        $query = "SELECT 
                    bqt.*,
                    bp.business_name,
                    bp.business_permit_id,
                    bp.full_name as owner_name,
                    bp.personal_contact,
                    bp.business_barangay as barangay,
                    DATEDIFF(CURDATE(), bqt.due_date) as days_overdue
                  FROM business_quarterly_taxes bqt
                  JOIN business_permits bp ON bqt.business_permit_id = bp.id
                  WHERE bqt.payment_status = 'overdue' AND bqt.year = :year
                  AND bp.status IN ('Active', 'Approved')
                  ORDER BY bqt.due_date ASC
                  LIMIT :limit";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $overdue = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
    } catch (Exception $e) {
        error_log("Overdue taxes error: " . $e->getMessage());
        $overdue = [];
    }
    
    return $overdue;
}

function getRecentPayments($pdo, $limit = 10) {
    $payments = [];
    
    try {
        $query = "SELECT 
                    bqt.*,
                    bp.business_name,
                    bp.business_permit_id,
                    bp.full_name as owner_name,
                    bp.business_barangay as barangay
                  FROM business_quarterly_taxes bqt
                  JOIN business_permits bp ON bqt.business_permit_id = bp.id
                  WHERE bqt.payment_date IS NOT NULL
                  AND bp.status IN ('Active', 'Approved')
                  ORDER BY bqt.payment_date DESC
                  LIMIT :limit";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
    } catch (Exception $e) {
        error_log("Recent payments error: " . $e->getMessage());
        $payments = [];
    }
    
    return $payments;
}

function getCurrentConfig($pdo) {
    $config = [];
    
    try {
        // Penalty config
        $query = "SELECT * FROM business_penalty_config WHERE expiration_date IS NULL OR expiration_date >= CURDATE() ORDER BY effective_date DESC LIMIT 1";
        $stmt = $pdo->query($query);
        $config['penalty'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        // Discount config
        $query = "SELECT * FROM business_discount_config WHERE expiration_date IS NULL OR expiration_date >= CURDATE() ORDER BY effective_date DESC LIMIT 1";
        $stmt = $pdo->query($query);
        $config['discount'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        // Capital investment config
        $query = "SELECT * FROM capital_investment_tax_config WHERE expiration_date IS NULL OR expiration_date >= CURDATE() ORDER BY min_amount";
        $stmt = $pdo->query($query);
        $config['capital_investment'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Gross sales config
        $query = "SELECT * FROM gross_sales_tax_config WHERE expiration_date IS NULL OR expiration_date >= CURDATE()";
        $stmt = $pdo->query($query);
        $config['gross_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Regulatory fees
        $query = "SELECT * FROM regulatory_fee_config WHERE expiration_date IS NULL OR expiration_date >= CURDATE()";
        $stmt = $pdo->query($query);
        $config['regulatory_fees'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
    } catch (Exception $e) {
        error_log("Config error: " . $e->getMessage());
        $config = [];
    }
    
    return $config;
}

function getYearlySummary($pdo, $year) {
    $summary = [];
    
    try {
        $query = "SELECT 
                    year,
                    COUNT(*) as total_quarters,
                    SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END) as total_collected,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as quarters_paid,
                    SUM(penalty_amount) as total_penalties,
                    SUM(discount_amount) as total_discounts,
                    AVG(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE NULL END) as avg_tax_payment,
                    MIN(payment_date) as first_payment,
                    MAX(payment_date) as last_payment
                  FROM business_quarterly_taxes 
                  WHERE year = :year";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute(['year' => $year]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        // Add collection rate
        $query2 = "SELECT 
                     SUM(total_quarterly_tax) as total_due
                   FROM business_quarterly_taxes 
                   WHERE year = :year";
        
        $stmt2 = $pdo->prepare($query2);
        $stmt2->execute(['year' => $year]);
        $totalDue = $stmt2->fetch(PDO::FETCH_ASSOC);
        $totalDue = floatval($totalDue['total_due'] ?? 0);
        
        $totalCollected = floatval($summary['total_collected'] ?? 0);
        $summary['collection_rate'] = $totalDue > 0 ? ($totalCollected / $totalDue) * 100 : 0;
        
    } catch (Exception $e) {
        error_log("Yearly summary error: " . $e->getMessage());
        $summary = [
            'total_quarters' => 0,
            'total_collected' => 0,
            'quarters_paid' => 0,
            'total_penalties' => 0,
            'total_discounts' => 0,
            'avg_tax_payment' => 0,
            'collection_rate' => 0
        ];
    }
    
    return $summary;
}

function getCurrentQuarter() {
    $month = date('n');
    if ($month >= 1 && $month <= 3) return 'Q1';
    if ($month >= 4 && $month <= 6) return 'Q2';
    if ($month >= 7 && $month <= 9) return 'Q3';
    return 'Q4';
}

function getAvailableYears($pdo) {
    try {
        $years = getAvailableYearsFromData($pdo);
        echo json_encode([
            'success' => true, 
            'years' => $years,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'Years error: ' . $e->getMessage(),
            'years' => [date('Y') - 1, date('Y')]
        ]);
    }
}

function getAvailableYearsFromData($pdo) {
    $years = [];
    
    try {
        // First try from quarterly taxes
        $query = "SELECT DISTINCT year FROM business_quarterly_taxes ORDER BY year DESC";
        $stmt = $pdo->query($query);
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        
        // If no data in quarterly taxes, get from business permits
        if (empty($years)) {
            $query = "SELECT DISTINCT YEAR(issue_date) as year FROM business_permits WHERE issue_date IS NOT NULL ORDER BY year DESC";
            $stmt = $pdo->query($query);
            $years = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
        
        // Add current year if not present
        $currentYear = date('Y');
        if (!in_array($currentYear, $years)) {
            array_unshift($years, $currentYear);
        }
        
        // Add previous year if empty
        if (empty($years)) {
            $years = [$currentYear - 1, $currentYear];
        }
        
    } catch (Exception $e) {
        error_log("Available years error: " . $e->getMessage());
        $years = [date('Y') - 1, date('Y')];
    }
    
    return $years;
}
?>