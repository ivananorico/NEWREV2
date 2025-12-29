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

// Get action from request
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

switch ($action) {
    case 'dashboard':
        getDashboardData($pdo, $year);
        break;
    case 'get_years':
        getAvailableYears($pdo);
        break;
    case 'export_data':
        exportData($pdo, $year);
        break;
    default:
        getDashboardData($pdo, $year);
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
        $data['business_types'] = getBusinessTypesDistribution($pdo);
        
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
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'year' => $year,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Server error: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

function getBusinessStatistics($pdo) {
    $stats = [];
    
    // Total businesses
    $query = "SELECT COUNT(*) as total FROM business_permits WHERE status != 'Deleted'";
    $stmt = $pdo->query($query);
    $stats['total_businesses'] = $stmt->fetch()['total'] ?? 0;
    
    // Active businesses
    $query = "SELECT COUNT(*) as active FROM business_permits WHERE status = 'Active'";
    $stmt = $pdo->query($query);
    $stats['active_businesses'] = $stmt->fetch()['active'] ?? 0;
    
    // Pending applications
    $query = "SELECT COUNT(*) as pending FROM business_permits WHERE status = 'Pending'";
    $stmt = $pdo->query($query);
    $stats['pending_applications'] = $stmt->fetch()['pending'] ?? 0;
    
    // Businesses by type
    $query = "SELECT business_type, COUNT(*) as count FROM business_permits WHERE status = 'Active' GROUP BY business_type";
    $stmt = $pdo->query($query);
    $stats['business_by_type'] = $stmt->fetchAll() ?: [];
    
    // Total capital investment
    $query = "SELECT SUM(taxable_amount) as total_capital FROM business_permits WHERE status = 'Active'";
    $stmt = $pdo->query($query);
    $stats['total_capital_investment'] = $stmt->fetch()['total_capital'] ?? 0;
    
    return $stats;
}

function getTaxStatistics($pdo, $year) {
    $stats = [];
    
    // Annual tax assessment
    $query = "SELECT 
                SUM(b.total_tax) as total_annual_tax,
                SUM(b.tax_amount) as total_tax_amount,
                SUM(b.regulatory_fees) as total_fees
              FROM business_permits b
              WHERE b.status = 'Active'";
    $stmt = $pdo->query($query);
    $annual = $stmt->fetch();
    $stats['annual'] = [
        'total_annual_tax' => $annual['total_annual_tax'] ?? 0,
        'total_tax_amount' => $annual['total_tax_amount'] ?? 0,
        'total_fees' => $annual['total_fees'] ?? 0
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
    $current = $stmt->fetch();
    $stats['current_quarter'] = [
        'current_quarter_paid' => $current['current_quarter_paid'] ?? 0,
        'bills_paid' => $current['bills_paid'] ?? 0
    ];
    
    // Quarterly target (1/4 of annual tax)
    $stats['quarterly_target'] = ($stats['annual']['total_annual_tax'] ?? 0) / 4;
    
    // Outstanding balance
    $query = "SELECT 
                SUM(CASE WHEN payment_status = 'overdue' THEN total_quarterly_tax + penalty_amount ELSE 0 END) as overdue_balance,
                SUM(CASE WHEN payment_status = 'pending' THEN total_quarterly_tax ELSE 0 END) as pending_balance,
                SUM(CASE WHEN payment_status IN ('overdue', 'pending') THEN 1 ELSE 0 END) as outstanding_bills
              FROM business_quarterly_taxes 
              WHERE year = :year";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['year' => $year]);
    $outstanding = $stmt->fetch();
    $stats['outstanding'] = [
        'overdue_balance' => $outstanding['overdue_balance'] ?? 0,
        'pending_balance' => $outstanding['pending_balance'] ?? 0,
        'outstanding_bills' => $outstanding['outstanding_bills'] ?? 0,
        'total_outstanding' => ($outstanding['overdue_balance'] ?? 0) + ($outstanding['pending_balance'] ?? 0)
    ];
    
    return $stats;
}

function getQuarterlyAnalysis($pdo, $year) {
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
    $quarters = $stmt->fetchAll() ?: [];
    
    // Calculate collection rate for each quarter
    foreach ($quarters as &$quarter) {
        $quarter['collection_rate'] = ($quarter['total_due'] ?? 0) > 0 
            ? (($quarter['collected'] ?? 0) / $quarter['total_due']) * 100 
            : 0;
    }
    
    return $quarters;
}

function getBusinessTypesDistribution($pdo) {
    $query = "SELECT 
                business_type,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM business_permits WHERE status = 'Active'), 2) as percentage
              FROM business_permits 
              WHERE status = 'Active'
              GROUP BY business_type
              ORDER BY count DESC";
    
    $stmt = $pdo->query($query);
    return $stmt->fetchAll() ?: [];
}

function getTopTaxpayers($pdo, $year, $limit = 10) {
    $query = "SELECT 
                bp.business_name,
                bp.business_type,
                bp.barangay,
                bp.owner_name,
                SUM(bqt.total_quarterly_tax) as total_tax_paid,
                COUNT(bqt.id) as quarters_paid,
                MAX(bqt.payment_date) as last_payment
              FROM business_permits bp
              LEFT JOIN business_quarterly_taxes bqt ON bp.id = bqt.business_permit_id
              WHERE bqt.payment_status = 'paid' AND bqt.year = :year
              GROUP BY bp.id
              ORDER BY total_tax_paid DESC
              LIMIT :limit";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':year', $year, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll() ?: [];
}

function getBarangayCollection($pdo, $year) {
    $query = "SELECT 
                bp.barangay,
                COUNT(DISTINCT bp.id) as business_count,
                SUM(CASE WHEN bqt.payment_status = 'paid' THEN bqt.total_quarterly_tax ELSE 0 END) as total_collection,
                AVG(CASE WHEN bqt.payment_status = 'paid' THEN bqt.total_quarterly_tax ELSE NULL END) as avg_tax_per_business
              FROM business_permits bp
              LEFT JOIN business_quarterly_taxes bqt ON bp.id = bqt.business_permit_id
              WHERE bqt.year = :year
              GROUP BY bp.barangay
              ORDER BY total_collection DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['year' => $year]);
    
    return $stmt->fetchAll() ?: [];
}

function getOverdueTaxes($pdo, $year, $limit = 15) {
    $query = "SELECT 
                bqt.*,
                bp.business_name,
                bp.business_permit_id,
                bp.owner_name,
                bp.contact_number,
                bp.barangay,
                DATEDIFF(CURDATE(), bqt.due_date) as days_overdue
              FROM business_quarterly_taxes bqt
              JOIN business_permits bp ON bqt.business_permit_id = bp.id
              WHERE bqt.payment_status = 'overdue' AND bqt.year = :year
              ORDER BY bqt.due_date ASC
              LIMIT :limit";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':year', $year, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll() ?: [];
}

function getRecentPayments($pdo, $limit = 10) {
    $query = "SELECT 
                bqt.*,
                bp.business_name,
                bp.business_permit_id,
                bp.owner_name,
                bp.barangay
              FROM business_quarterly_taxes bqt
              JOIN business_permits bp ON bqt.business_permit_id = bp.id
              WHERE bqt.payment_date IS NOT NULL
              ORDER BY bqt.payment_date DESC
              LIMIT :limit";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll() ?: [];
}

function getCurrentConfig($pdo) {
    $config = [];
    
    // Penalty config
    $query = "SELECT * FROM business_penalty_config WHERE expiration_date IS NULL OR expiration_date >= CURDATE() ORDER BY effective_date DESC LIMIT 1";
    $stmt = $pdo->query($query);
    $config['penalty'] = $stmt->fetch() ?: [];
    
    // Discount config
    $query = "SELECT * FROM business_discount_config WHERE expiration_date IS NULL OR expiration_date >= CURDATE() ORDER BY effective_date DESC LIMIT 1";
    $stmt = $pdo->query($query);
    $config['discount'] = $stmt->fetch() ?: [];
    
    // Capital investment config
    $query = "SELECT * FROM capital_investment_tax_config WHERE expiration_date IS NULL OR expiration_date >= CURDATE() ORDER BY min_amount";
    $stmt = $pdo->query($query);
    $config['capital_investment'] = $stmt->fetchAll() ?: [];
    
    // Gross sales config
    $query = "SELECT * FROM gross_sales_tax_config WHERE expiration_date IS NULL OR expiration_date >= CURDATE()";
    $stmt = $pdo->query($query);
    $config['gross_sales'] = $stmt->fetchAll() ?: [];
    
    // Regulatory fees
    $query = "SELECT * FROM regulatory_fee_config WHERE expiration_date IS NULL OR expiration_date >= CURDATE()";
    $stmt = $pdo->query($query);
    $config['regulatory_fees'] = $stmt->fetchAll() ?: [];
    
    return $config;
}

function getYearlySummary($pdo, $year) {
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
    $summary = $stmt->fetch() ?: [];
    
    // Add collection rate
    $query2 = "SELECT 
                 SUM(total_quarterly_tax) as total_due
               FROM business_quarterly_taxes 
               WHERE year = :year";
    
    $stmt2 = $pdo->prepare($query2);
    $stmt2->execute(['year' => $year]);
    $totalDue = $stmt2->fetch()['total_due'] ?? 0;
    
    $summary['collection_rate'] = $totalDue > 0 
        ? (($summary['total_collected'] ?? 0) / $totalDue) * 100 
        : 0;
    
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
    $years = getAvailableYearsFromData($pdo);
    echo json_encode(['success' => true, 'years' => $years]);
}

function getAvailableYearsFromData($pdo) {
    $query = "SELECT DISTINCT year FROM business_quarterly_taxes ORDER BY year DESC";
    $stmt = $pdo->query($query);
    $result = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    
    // If no data in quarterly taxes, get from business permits
    if (empty($result)) {
        $query = "SELECT DISTINCT YEAR(issue_date) as year FROM business_permits WHERE issue_date IS NOT NULL ORDER BY year DESC";
        $stmt = $pdo->query($query);
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
    
    // Add current year if not present
    $currentYear = date('Y');
    if (!in_array($currentYear, $result)) {
        array_unshift($result, $currentYear);
    }
    
    // Add previous year if empty
    if (empty($result)) {
        $result = [$currentYear - 1, $currentYear];
    }
    
    return $result;
}

function exportData($pdo, $year) {
    getDashboardData($pdo, $year);
}
?>