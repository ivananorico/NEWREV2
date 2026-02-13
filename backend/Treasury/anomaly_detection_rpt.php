<?php
// Add CORS headers
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
// revenue2/backend/Treasury/anomaly_detection_rpt.php
// FOCUS: RPT Quarterly Tax Anomalies Only

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';

class RPTAnomalyDetection {
    private $conn;
    
    public function __construct() {
        $this->conn = Database::getConnection('rpt');
    }
    
    /**
     * MAIN METHOD: Detect all quarterly tax anomalies
     */
    public function detectAllAnomalies() {
        return [
            'anomalies' => array_merge(
                $this->detectLatePaymentAnomalies(),
                $this->detectPaymentPatternAnomalies(),
                $this->detectDiscountAnomalies(),
                $this->detectGeographicLatePaymentClusters(),
                $this->detectConsistentLatePayers()
            ),
            'quarterly_stats' => $this->getQuarterlyStats(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 1. DETECT LATE PAYMENT ANOMALIES
     * Properties with excessive lateness or unusual penalty patterns
     */
    private function detectLatePaymentAnomalies() {
        $anomalies = [];
        
        // Critical late payments (>30 days late) for current quarter
        $sql = "SELECT 
                    qt.id,
                    qt.property_total_id,
                    qt.quarter,
                    qt.year,
                    qt.due_date,
                    qt.payment_date,
                    qt.days_late,
                    qt.penalty_amount,
                    qt.total_quarterly_tax,
                    qt.payment_status,
                    po.id as owner_id,
                    po.first_name,
                    po.last_name,
                    po.owner_code,
                    pr.barangay,
                    pr.district,
                    pt.total_annual_tax
                FROM quarterly_taxes qt
                JOIN property_totals pt ON qt.property_total_id = pt.id
                JOIN property_registrations pr ON pt.registration_id = pr.id
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE qt.year = YEAR(CURDATE())
                    AND qt.quarter = CONCAT('Q', QUARTER(CURDATE()))
                    AND qt.payment_status = 'overdue'
                    AND qt.days_late > 30
                ORDER BY qt.days_late DESC";
        
        $stmt = $this->conn->query($sql);
        $late_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($late_payments as $late) {
            $anomalies[] = [
                'id' => 'LATE-' . uniqid(),
                'type' => 'CRITICAL_LATE_PAYMENT',
                'severity' => $late['days_late'] > 60 ? 'critical' : 'warning',
                'title' => 'Excessively Late Quarterly Tax Payment',
                'description' => sprintf(
                    '%s %s is %d days late for %s %d quarterly tax',
                    $late['first_name'],
                    $late['last_name'],
                    $late['days_late'],
                    $late['quarter'],
                    $late['year']
                ),
                'owner' => $late['first_name'] . ' ' . $late['last_name'],
                'owner_code' => $late['owner_code'],
                'barangay' => $late['barangay'],
                'district' => $late['district'],
                'quarter' => $late['quarter'],
                'year' => $late['year'],
                'days_late' => intval($late['days_late']),
                'due_date' => $late['due_date'],
                'tax_amount' => floatval($late['total_quarterly_tax']),
                'penalty_amount' => floatval($late['penalty_amount']),
                'penalty_rate' => $late['days_late'] > 0 ? 
                    round(($late['penalty_amount'] / $late['total_quarterly_tax']) * 100, 2) : 0,
                'ai_analysis' => $this->analyzeLatePayment($late),
                'recommendation' => $this->getLatePaymentRecommendation($late)
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * 2. DETECT PAYMENT PATTERN ANOMALIES
     * Sudden changes in payment behavior (early to late, etc.)
     */
    private function detectPaymentPatternAnomalies() {
        $anomalies = [];
        
        // Compare payment patterns year over year
        $sql = "SELECT 
                    po.id as owner_id,
                    po.first_name,
                    po.last_name,
                    po.owner_code,
                    pr.barangay,
                    pr.district,
                    MAX(CASE WHEN qt.year = YEAR(CURDATE()) - 1 THEN qt.payment_status END) as prev_year_status,
                    MAX(CASE WHEN qt.year = YEAR(CURDATE()) - 1 THEN qt.days_late END) as prev_year_days_late,
                    MAX(CASE WHEN qt.year = YEAR(CURDATE()) - 1 THEN qt.quarter END) as prev_year_quarter,
                    MAX(CASE WHEN qt.year = YEAR(CURDATE()) THEN qt.payment_status END) as current_year_status,
                    MAX(CASE WHEN qt.year = YEAR(CURDATE()) THEN qt.days_late END) as current_year_days_late,
                    MAX(CASE WHEN qt.year = YEAR(CURDATE()) THEN qt.quarter END) as current_year_quarter
                FROM quarterly_taxes qt
                JOIN property_totals pt ON qt.property_total_id = pt.id
                JOIN property_registrations pr ON pt.registration_id = pr.id
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE qt.year IN (YEAR(CURDATE()) - 1, YEAR(CURDATE()))
                    AND qt.quarter = CONCAT('Q', QUARTER(CURDATE()))
                GROUP BY po.id
                HAVING prev_year_status = 'paid' 
                    AND prev_year_days_late = 0
                    AND current_year_status = 'overdue'
                    AND current_year_days_late > 15";
        
        $stmt = $this->conn->query($sql);
        $pattern_changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pattern_changes as $pattern) {
            $anomalies[] = [
                'id' => 'PATTERN-' . uniqid(),
                'type' => 'PAYMENT_PATTERN_CHANGE',
                'severity' => 'warning',
                'title' => 'Sudden Change in Payment Behavior',
                'description' => sprintf(
                    '%s %s switched from on-time payments last year to %d days late this quarter',
                    $pattern['first_name'],
                    $pattern['last_name'],
                    $pattern['current_year_days_late']
                ),
                'owner' => $pattern['first_name'] . ' ' . $pattern['last_name'],
                'owner_code' => $pattern['owner_code'],
                'barangay' => $pattern['barangay'],
                'district' => $pattern['district'],
                'previous_status' => 'On-time',
                'current_status' => $pattern['current_year_days_late'] . ' days late',
                'change_percent' => 100,
                'ai_analysis' => 'Property owner has consistently paid on time for previous years but suddenly became delinquent. May indicate financial distress or change in ownership.',
                'recommendation' => 'Contact property owner to verify contact information and offer payment assistance programs.'
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * 3. DETECT DISCOUNT ANOMALIES
     * Suspicious discount patterns or missed discounts
     */
    private function detectDiscountAnomalies() {
        $anomalies = [];
        
        // Properties that always get discount (suspicious)
        $sql = "SELECT 
                    po.id as owner_id,
                    po.first_name,
                    po.last_name,
                    po.owner_code,
                    pr.barangay,
                    COUNT(DISTINCT pt.id) as property_count,
                    COUNT(qt.id) as total_payments,
                    SUM(CASE WHEN qt.discount_applied = 1 THEN 1 ELSE 0 END) as discounted_payments,
                    ROUND((SUM(CASE WHEN qt.discount_applied = 1 THEN 1 ELSE 0 END) / COUNT(qt.id)) * 100, 1) as discount_rate
                FROM quarterly_taxes qt
                JOIN property_totals pt ON qt.property_total_id = pt.id
                JOIN property_registrations pr ON pt.registration_id = pr.id
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE qt.year >= YEAR(CURDATE()) - 2
                    AND qt.payment_status = 'paid'
                GROUP BY po.id
                HAVING property_count >= 2 
                    AND discount_rate > 80
                    AND COUNT(qt.id) >= 4";
        
        $stmt = $this->conn->query($sql);
        $high_discount = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($high_discount as $owner) {
            $anomalies[] = [
                'id' => 'DISCOUNT-' . uniqid(),
                'type' => 'UNUSUAL_DISCOUNT_PATTERN',
                'severity' => 'warning',
                'title' => 'Consistently High Discount Rate',
                'description' => sprintf(
                    '%s %s receives discount on %s%% of %d payments across %d properties',
                    $owner['first_name'],
                    $owner['last_name'],
                    $owner['discount_rate'],
                    $owner['total_payments'],
                    $owner['property_count']
                ),
                'owner' => $owner['first_name'] . ' ' . $owner['last_name'],
                'owner_code' => $owner['owner_code'],
                'barangay' => $owner['barangay'],
                'property_count' => intval($owner['property_count']),
                'total_payments' => intval($owner['total_payments']),
                'discounted_payments' => intval($owner['discounted_payments']),
                'discount_rate' => floatval($owner['discount_rate']),
                'expected_rate' => 30.0,
                'ai_analysis' => 'This owner receives discounts significantly more often than average. Verify if all payments are made within discount period.',
                'recommendation' => 'Audit payment dates for this owner across all properties to verify discount eligibility.'
            ];
        }
        
        // Properties that missed discount this year but always got it before
        $sql = "SELECT 
                    po.first_name,
                    po.last_name,
                    po.owner_code,
                    pr.barangay,
                    MAX(CASE WHEN qt.year = YEAR(CURDATE()) - 1 AND qt.discount_applied = 1 THEN 1 ELSE 0 END) as prev_year_discount,
                    MAX(CASE WHEN qt.year = YEAR(CURDATE()) AND qt.discount_applied = 0 THEN 1 ELSE 0 END) as current_year_no_discount,
                    MAX(CASE WHEN qt.year = YEAR(CURDATE()) THEN qt.payment_date END) as current_payment_date,
                    MAX(CASE WHEN qt.year = YEAR(CURDATE()) THEN qt.due_date END) as due_date
                FROM quarterly_taxes qt
                JOIN property_totals pt ON qt.property_total_id = pt.id
                JOIN property_registrations pr ON pt.registration_id = pr.id
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE qt.year >= YEAR(CURDATE()) - 1
                    AND qt.quarter = CONCAT('Q', QUARTER(CURDATE()))
                GROUP BY po.id
                HAVING prev_year_discount = 1 AND current_year_no_discount = 1";
        
        $stmt = $this->conn->query($sql);
        $missed_discount = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($missed_discount as $missed) {
            $anomalies[] = [
                'id' => 'MISSED-DISCOUNT-' . uniqid(),
                'type' => 'MISSED_DISCOUNT_OPPORTUNITY',
                'severity' => 'info',
                'title' => 'Property Missed Early Payment Discount',
                'description' => sprintf(
                    '%s %s always paid early in previous years but missed discount period this quarter',
                    $missed['first_name'],
                    $missed['last_name']
                ),
                'owner' => $missed['first_name'] . ' ' . $missed['last_name'],
                'owner_code' => $missed['owner_code'],
                'barangay' => $missed['barangay'],
                'payment_date' => $missed['current_payment_date'],
                'due_date' => $missed['due_date'],
                'potential_savings' => 500.00,
                'ai_analysis' => 'Owner has changed payment timing and lost discount benefits.',
                'recommendation' => 'Send reminder about early payment discount benefits for next quarter.'
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * 4. DETECT GEOGRAPHIC LATE PAYMENT CLUSTERS
     * Barangays/Districts with unusually high late payment rates
     */
    private function detectGeographicLatePaymentClusters() {
        $anomalies = [];
        
        $sql = "SELECT 
                    pr.barangay,
                    pr.district,
                    COUNT(qt.id) as total_payments,
                    SUM(CASE WHEN qt.payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                    ROUND((SUM(CASE WHEN qt.payment_status = 'overdue' THEN 1 ELSE 0 END) / COUNT(qt.id)) * 100, 1) as overdue_rate,
                    AVG(CASE WHEN qt.days_late > 0 THEN qt.days_late ELSE 0 END) as avg_days_late,
                    COUNT(DISTINCT pr.id) as property_count
                FROM quarterly_taxes qt
                JOIN property_totals pt ON qt.property_total_id = pt.id
                JOIN property_registrations pr ON pt.registration_id = pr.id
                WHERE qt.year = YEAR(CURDATE())
                GROUP BY pr.barangay, pr.district
                HAVING overdue_rate > 40 AND total_payments > 5
                ORDER BY overdue_rate DESC";
        
        $stmt = $this->conn->query($sql);
        $clusters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($clusters as $cluster) {
            $anomalies[] = [
                'id' => 'GEO-' . uniqid(),
                'type' => 'LATE_PAYMENT_CLUSTER',
                'severity' => $cluster['overdue_rate'] > 60 ? 'critical' : 'warning',
                'title' => 'High Concentration of Late Payments',
                'description' => sprintf(
                    '%s has %s%% overdue rate (%d of %d payments)',
                    $cluster['barangay'],
                    $cluster['overdue_rate'],
                    $cluster['overdue_count'],
                    $cluster['total_payments']
                ),
                'barangay' => $cluster['barangay'],
                'district' => $cluster['district'],
                'overdue_rate' => floatval($cluster['overdue_rate']),
                'overdue_count' => intval($cluster['overdue_count']),
                'total_payments' => intval($cluster['total_payments']),
                'avg_days_late' => round(floatval($cluster['avg_days_late']), 1),
                'property_count' => intval($cluster['property_count']),
                'ai_analysis' => sprintf(
                    'This barangay has %s%% overdue rate, which is significantly higher than the city average. Target for collection campaign.',
                    $cluster['overdue_rate']
                ),
                'recommendation' => 'Conduct barangay-wide information campaign about payment deadlines and penalties.'
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * 5. DETECT CONSISTENT LATE PAYERS
     * Properties that are always late quarter after quarter
     */
    private function detectConsistentLatePayers() {
        $anomalies = [];
        
        $sql = "SELECT 
                    po.first_name,
                    po.last_name,
                    po.owner_code,
                    pr.barangay,
                    pr.district,
                    COUNT(qt.id) as total_quarters,
                    SUM(CASE WHEN qt.payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_quarters,
                    ROUND((SUM(CASE WHEN qt.payment_status = 'overdue' THEN 1 ELSE 0 END) / COUNT(qt.id)) * 100, 1) as chronic_late_rate,
                    AVG(qt.days_late) as avg_days_late,
                    SUM(qt.penalty_amount) as total_penalties_paid
                FROM quarterly_taxes qt
                JOIN property_totals pt ON qt.property_total_id = pt.id
                JOIN property_registrations pr ON pt.registration_id = pr.id
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE qt.year >= YEAR(CURDATE()) - 2
                GROUP BY po.id
                HAVING chronic_late_rate > 75 AND total_quarters >= 4
                ORDER BY chronic_late_rate DESC";
        
        $stmt = $this->conn->query($sql);
        $chronic_late = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($chronic_late as $late) {
            $anomalies[] = [
                'id' => 'CHRONIC-' . uniqid(),
                'type' => 'CHRONIC_LATE_PAYER',
                'severity' => 'critical',
                'title' => 'Chronic Late Payer - Always Overdue',
                'description' => sprintf(
                    '%s %s is late on %s%% of quarterly payments over last 2 years',
                    $late['first_name'],
                    $late['last_name'],
                    $late['chronic_late_rate']
                ),
                'owner' => $late['first_name'] . ' ' . $late['last_name'],
                'owner_code' => $late['owner_code'],
                'barangay' => $late['barangay'],
                'district' => $late['district'],
                'chronic_late_rate' => floatval($late['chronic_late_rate']),
                'total_quarters' => intval($late['total_quarters']),
                'overdue_quarters' => intval($late['overdue_quarters']),
                'avg_days_late' => round(floatval($late['avg_days_late']), 1),
                'total_penalties_paid' => floatval($late['total_penalties_paid']),
                'ai_analysis' => sprintf(
                    'This property owner has been late on %d out of %d quarters. Total penalties paid: ₱%s',
                    $late['overdue_quarters'],
                    $late['total_quarters'],
                    number_format($late['total_penalties_paid'], 2)
                ),
                'recommendation' => 'Flag account for mandatory electronic payment enrollment. Consider setting up automatic debit arrangement.'
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * GET QUARTERLY STATISTICS
     */
    private function getQuarterlyStats() {
        $stats = [];
        
        // Current quarter stats
        $sql = "SELECT 
                    CONCAT('Q', QUARTER(CURDATE())) as current_quarter,
                    YEAR(CURDATE()) as current_year,
                    COUNT(*) as total_assessments,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    ROUND(AVG(CASE WHEN days_late > 0 THEN days_late ELSE 0 END), 1) as avg_days_late,
                    SUM(penalty_amount) as total_penalties,
                    SUM(CASE WHEN discount_applied = 1 THEN 1 ELSE 0 END) as discount_count,
                    ROUND((SUM(CASE WHEN discount_applied = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as discount_rate
                FROM quarterly_taxes
                WHERE year = YEAR(CURDATE()) 
                    AND quarter = CONCAT('Q', QUARTER(CURDATE()))";
        
        $stmt = $this->conn->query($sql);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current) {
            $stats['current_quarter'] = [
                'quarter' => $current['current_quarter'],
                'year' => $current['current_year'],
                'total' => intval($current['total_assessments']),
                'paid' => intval($current['paid_count']),
                'overdue' => intval($current['overdue_count']),
                'pending' => intval($current['pending_count']),
                'collection_rate' => $current['total_assessments'] > 0 ? 
                    round(($current['paid_count'] / $current['total_assessments']) * 100, 1) : 0,
                'avg_days_late' => floatval($current['avg_days_late']),
                'total_penalties' => floatval($current['total_penalties']),
                'discount_rate' => floatval($current['discount_rate'])
            ];
        }
        
        // Previous quarter stats for comparison
        $sql = "SELECT 
                    CONCAT('Q', QUARTER(DATE_SUB(CURDATE(), INTERVAL 3 MONTH))) as prev_quarter,
                    YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) as prev_year,
                    COUNT(*) as total_assessments,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    ROUND((SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as collection_rate
                FROM quarterly_taxes
                WHERE year = YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH))
                    AND quarter = CONCAT('Q', QUARTER(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)))";
        
        $stmt = $this->conn->query($sql);
        $previous = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($previous) {
            $stats['previous_quarter'] = [
                'quarter' => $previous['prev_quarter'],
                'year' => $previous['prev_year'],
                'total' => intval($previous['total_assessments']),
                'paid' => intval($previous['paid_count']),
                'collection_rate' => floatval($previous['collection_rate'])
            ];
            
            // Calculate change
            if ($stats['current_quarter'] && $stats['previous_quarter']) {
                $stats['quarter_over_quarter_change'] = round(
                    $stats['current_quarter']['collection_rate'] - $stats['previous_quarter']['collection_rate'], 
                    1
                );
            }
        }
        
        // Top late barangays
        $sql = "SELECT 
                    pr.barangay,
                    COUNT(qt.id) as total_payments,
                    SUM(CASE WHEN qt.payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                    ROUND((SUM(CASE WHEN qt.payment_status = 'overdue' THEN 1 ELSE 0 END) / COUNT(qt.id)) * 100, 1) as overdue_rate
                FROM quarterly_taxes qt
                JOIN property_totals pt ON qt.property_total_id = pt.id
                JOIN property_registrations pr ON pt.registration_id = pr.id
                WHERE qt.year = YEAR(CURDATE())
                GROUP BY pr.barangay
                ORDER BY overdue_rate DESC
                LIMIT 5";
        
        $stmt = $this->conn->query($sql);
        $stats['top_late_barangays'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
    
    /**
     * ANALYZE LATE PAYMENT PATTERNS
     */
    private function analyzeLatePayment($payment) {
        if ($payment['days_late'] > 60) {
            return "CRITICAL: Payment is {$payment['days_late']} days overdue. This property is at risk of delinquency proceedings.";
        } elseif ($payment['days_late'] > 30) {
            return "This payment is {$payment['days_late']} days late. Additional penalties will continue to accrue.";
        }
        return "Payment is {$payment['days_late']} days late. Standard late payment detected.";
    }
    
    /**
     * GET LATE PAYMENT RECOMMENDATION
     */
    private function getLatePaymentRecommendation($payment) {
        if ($payment['days_late'] > 60) {
            return "IMMEDIATE ACTION: Send final notice and prepare for delinquency proceedings if payment not received within 15 days.";
        } elseif ($payment['days_late'] > 30) {
            return "Send follow-up notice with updated penalty amount. Offer payment plan if this is first offense.";
        } else {
            return "Send standard reminder notice. No further action required.";
        }
    }
}

// ============================================
// FIXED: DIRECT ACCESS HANDLER
// ============================================
// This makes the page show JSON when accessed directly
if (basename($_SERVER['PHP_SELF']) == 'anomaly_detection_rpt.php') {
    header('Content-Type: application/json');
    try {
        $detector = new RPTAnomalyDetection();
        echo json_encode($detector->detectAllAnomalies(), JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        echo json_encode([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], JSON_PRETTY_PRINT);
    }
    exit;
}
?>