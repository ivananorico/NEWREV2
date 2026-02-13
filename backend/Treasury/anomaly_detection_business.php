<?php
// revenue2/backend/Treasury/anomaly_detection_business.php
// FOCUS: Business Tax Quarterly Anomalies Only
// NO ML - Simple rule-based detection

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

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';

class BusinessAnomalyDetection {
    private $conn;
    
    public function __construct() {
        $this->conn = Database::getConnection('business');
    }
    
    /**
     * MAIN METHOD: Detect all business tax anomalies
     */
    public function detectAllAnomalies() {
        return [
            'anomalies' => array_merge(
                $this->detectLatePaymentAnomalies(),
                $this->detectPaymentPatternAnomalies(),
                $this->detectDiscountAnomalies(),
                $this->detectGeographicLatePaymentClusters(),
                $this->detectConsistentLatePayers()
                // Removed detectCapitalInvestmentAnomalies due to missing column
            ),
            'quarterly_stats' => $this->getQuarterlyStats(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 1. DETECT LATE PAYMENT ANOMALIES
     * Businesses with excessive lateness
     */
    private function detectLatePaymentAnomalies() {
        $anomalies = [];
        
        // Critical late payments (>30 days late) for current quarter
        $sql = "SELECT 
                    bqt.id,
                    bqt.business_permit_id,
                    bqt.quarter,
                    bqt.year,
                    bqt.due_date,
                    bqt.payment_date,
                    bqt.days_late,
                    bqt.penalty_amount,
                    bqt.total_quarterly_tax,
                    bqt.payment_status,
                    bp.business_name,
                    bp.owner_full_name,
                    bp.applicant_id,
                    bp.business_barangay,
                    bp.business_district,
                    bp.business_city,
                    bp.capital_investment,
                    bp.tax_rate,
                    bp.total_tax
                FROM business_quarterly_taxes bqt
                JOIN business_permits bp ON bqt.business_permit_id = bp.id
                WHERE bqt.year = YEAR(CURDATE())
                    AND bqt.quarter = CONCAT('Q', QUARTER(CURDATE()))
                    AND bqt.payment_status = 'overdue'
                    AND bqt.days_late > 30
                ORDER BY bqt.days_late DESC";
        
        $stmt = $this->conn->query($sql);
        $late_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($late_payments as $late) {
            $anomalies[] = [
                'id' => 'BUS-LATE-' . uniqid(),
                'type' => 'CRITICAL_LATE_PAYMENT',
                'severity' => $late['days_late'] > 60 ? 'critical' : 'warning',
                'title' => 'Excessively Late Quarterly Business Tax',
                'description' => sprintf(
                    '%s (%s) is %d days late for %s %d quarterly tax',
                    $late['business_name'],
                    $late['owner_full_name'],
                    $late['days_late'],
                    $late['quarter'],
                    $late['year']
                ),
                'business_name' => $late['business_name'],
                'owner' => $late['owner_full_name'],
                'applicant_id' => $late['applicant_id'],
                'barangay' => $late['business_barangay'],
                'district' => $late['business_district'],
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
     * Sudden changes in payment behavior
     */
    private function detectPaymentPatternAnomalies() {
        $anomalies = [];
        
        // Compare payment patterns year over year
        $sql = "SELECT 
                    bp.id as business_id,
                    bp.business_name,
                    bp.owner_full_name,
                    bp.applicant_id,
                    bp.business_barangay,
                    bp.business_district,
                    MAX(CASE WHEN bqt.year = YEAR(CURDATE()) - 1 THEN bqt.payment_status END) as prev_year_status,
                    MAX(CASE WHEN bqt.year = YEAR(CURDATE()) - 1 THEN bqt.days_late END) as prev_year_days_late,
                    MAX(CASE WHEN bqt.year = YEAR(CURDATE()) THEN bqt.payment_status END) as current_year_status,
                    MAX(CASE WHEN bqt.year = YEAR(CURDATE()) THEN bqt.days_late END) as current_year_days_late
                FROM business_quarterly_taxes bqt
                JOIN business_permits bp ON bqt.business_permit_id = bp.id
                WHERE bqt.year IN (YEAR(CURDATE()) - 1, YEAR(CURDATE()))
                    AND bqt.quarter = CONCAT('Q', QUARTER(CURDATE()))
                GROUP BY bp.id
                HAVING prev_year_status = 'paid' 
                    AND prev_year_days_late = 0
                    AND current_year_status = 'overdue'
                    AND current_year_days_late > 15";
        
        $stmt = $this->conn->query($sql);
        $pattern_changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pattern_changes as $pattern) {
            $anomalies[] = [
                'id' => 'BUS-PATTERN-' . uniqid(),
                'type' => 'PAYMENT_PATTERN_CHANGE',
                'severity' => 'warning',
                'title' => 'Sudden Change in Business Payment Behavior',
                'description' => sprintf(
                    '%s switched from on-time payments last year to %d days late this quarter',
                    $pattern['business_name'],
                    $pattern['current_year_days_late']
                ),
                'business_name' => $pattern['business_name'],
                'owner' => $pattern['owner_full_name'],
                'applicant_id' => $pattern['applicant_id'],
                'barangay' => $pattern['business_barangay'],
                'district' => $pattern['business_district'],
                'previous_status' => 'On-time',
                'current_status' => $pattern['current_year_days_late'] . ' days late',
                'change_percent' => 100,
                'ai_analysis' => 'Business consistently paid on time in previous years but suddenly became delinquent. May indicate cash flow problems or change in ownership.',
                'recommendation' => 'Contact business owner to verify financial status and offer payment assistance programs.'
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * 3. DETECT DISCOUNT ANOMALIES
     * Suspicious discount patterns
     */
    private function detectDiscountAnomalies() {
        $anomalies = [];
        
        // Businesses that always get discount (suspicious)
        $sql = "SELECT 
                    bp.id as business_id,
                    bp.business_name,
                    bp.owner_full_name,
                    bp.applicant_id,
                    bp.business_barangay,
                    COUNT(bqt.id) as total_payments,
                    SUM(CASE WHEN bqt.discount_applied = 1 THEN 1 ELSE 0 END) as discounted_payments,
                    ROUND((SUM(CASE WHEN bqt.discount_applied = 1 THEN 1 ELSE 0 END) / COUNT(bqt.id)) * 100, 1) as discount_rate
                FROM business_quarterly_taxes bqt
                JOIN business_permits bp ON bqt.business_permit_id = bp.id
                WHERE bqt.year >= YEAR(CURDATE()) - 2
                    AND bqt.payment_status = 'paid'
                GROUP BY bp.id
                HAVING discount_rate > 80 AND COUNT(bqt.id) >= 8";
        
        $stmt = $this->conn->query($sql);
        $high_discount = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($high_discount as $business) {
            $anomalies[] = [
                'id' => 'BUS-DISCOUNT-' . uniqid(),
                'type' => 'UNUSUAL_DISCOUNT_PATTERN',
                'severity' => 'warning',
                'title' => 'Consistently High Discount Rate',
                'description' => sprintf(
                    '%s receives discount on %s%% of %d payments',
                    $business['business_name'],
                    $business['discount_rate'],
                    $business['total_payments']
                ),
                'business_name' => $business['business_name'],
                'owner' => $business['owner_full_name'],
                'applicant_id' => $business['applicant_id'],
                'barangay' => $business['business_barangay'],
                'total_payments' => intval($business['total_payments']),
                'discounted_payments' => intval($business['discounted_payments']),
                'discount_rate' => floatval($business['discount_rate']),
                'expected_rate' => 30.0,
                'ai_analysis' => 'This business receives discounts significantly more often than average. Verify if all payments are made within discount period.',
                'recommendation' => 'Audit payment dates for this business to verify discount eligibility.'
            ];
        }
        
        // Businesses that missed discount this year but always got it before
        $sql = "SELECT 
                    bp.business_name,
                    bp.owner_full_name,
                    bp.applicant_id,
                    bp.business_barangay,
                    MAX(CASE WHEN bqt.year = YEAR(CURDATE()) - 1 AND bqt.discount_applied = 1 THEN 1 ELSE 0 END) as prev_year_discount,
                    MAX(CASE WHEN bqt.year = YEAR(CURDATE()) AND bqt.discount_applied = 0 THEN 1 ELSE 0 END) as current_year_no_discount,
                    MAX(CASE WHEN bqt.year = YEAR(CURDATE()) THEN bqt.payment_date END) as current_payment_date,
                    MAX(CASE WHEN bqt.year = YEAR(CURDATE()) THEN bqt.due_date END) as due_date
                FROM business_quarterly_taxes bqt
                JOIN business_permits bp ON bqt.business_permit_id = bp.id
                WHERE bqt.year >= YEAR(CURDATE()) - 1
                    AND bqt.quarter = CONCAT('Q', QUARTER(CURDATE()))
                GROUP BY bp.id
                HAVING prev_year_discount = 1 AND current_year_no_discount = 1";
        
        $stmt = $this->conn->query($sql);
        $missed_discount = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($missed_discount as $missed) {
            $anomalies[] = [
                'id' => 'BUS-MISSED-DISCOUNT-' . uniqid(),
                'type' => 'MISSED_DISCOUNT_OPPORTUNITY',
                'severity' => 'info',
                'title' => 'Business Missed Early Payment Discount',
                'description' => sprintf(
                    '%s always paid early in previous years but missed discount period this quarter',
                    $missed['business_name']
                ),
                'business_name' => $missed['business_name'],
                'owner' => $missed['owner_full_name'],
                'applicant_id' => $missed['applicant_id'],
                'barangay' => $missed['business_barangay'],
                'payment_date' => $missed['current_payment_date'],
                'due_date' => $missed['due_date'],
                'potential_savings' => $this->estimateDiscountAmount($missed['applicant_id']),
                'ai_analysis' => 'Business has changed payment timing and lost discount benefits.',
                'recommendation' => 'Send reminder about early payment discount benefits for next quarter.'
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * 4. DETECT GEOGRAPHIC LATE PAYMENT CLUSTERS
     * Barangays with unusually high late rates
     */
    private function detectGeographicLatePaymentClusters() {
        $anomalies = [];
        
        $sql = "SELECT 
                    bp.business_barangay,
                    bp.business_district,
                    COUNT(bqt.id) as total_payments,
                    SUM(CASE WHEN bqt.payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                    ROUND((SUM(CASE WHEN bqt.payment_status = 'overdue' THEN 1 ELSE 0 END) / COUNT(bqt.id)) * 100, 1) as overdue_rate,
                    AVG(CASE WHEN bqt.days_late > 0 THEN bqt.days_late ELSE 0 END) as avg_days_late,
                    COUNT(DISTINCT bp.id) as business_count
                FROM business_quarterly_taxes bqt
                JOIN business_permits bp ON bqt.business_permit_id = bp.id
                WHERE bqt.year = YEAR(CURDATE())
                GROUP BY bp.business_barangay, bp.business_district
                HAVING overdue_rate > 40 AND total_payments > 5
                ORDER BY overdue_rate DESC";
        
        $stmt = $this->conn->query($sql);
        $clusters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($clusters as $cluster) {
            $anomalies[] = [
                'id' => 'BUS-GEO-' . uniqid(),
                'type' => 'LATE_PAYMENT_CLUSTER',
                'severity' => $cluster['overdue_rate'] > 60 ? 'critical' : 'warning',
                'title' => 'High Concentration of Late Business Tax Payments',
                'description' => sprintf(
                    '%s has %s%% overdue rate (%d of %d payments)',
                    $cluster['business_barangay'],
                    $cluster['overdue_rate'],
                    $cluster['overdue_count'],
                    $cluster['total_payments']
                ),
                'barangay' => $cluster['business_barangay'],
                'district' => $cluster['business_district'],
                'overdue_rate' => floatval($cluster['overdue_rate']),
                'overdue_count' => intval($cluster['overdue_count']),
                'total_payments' => intval($cluster['total_payments']),
                'avg_days_late' => round(floatval($cluster['avg_days_late']), 1),
                'business_count' => intval($cluster['business_count']),
                'ai_analysis' => sprintf(
                    'This barangay has %s%% overdue rate. Target for BPLO collection campaign.',
                    $cluster['overdue_rate']
                ),
                'recommendation' => 'Conduct barangay-wide information campaign about business tax deadlines and penalties.'
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * 5. DETECT CONSISTENT LATE PAYERS
     * Businesses always late quarter after quarter
     */
    private function detectConsistentLatePayers() {
        $anomalies = [];
        
        $sql = "SELECT 
                    bp.business_name,
                    bp.owner_full_name,
                    bp.applicant_id,
                    bp.business_barangay,
                    bp.business_district,
                    COUNT(bqt.id) as total_quarters,
                    SUM(CASE WHEN bqt.payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_quarters,
                    ROUND((SUM(CASE WHEN bqt.payment_status = 'overdue' THEN 1 ELSE 0 END) / COUNT(bqt.id)) * 100, 1) as chronic_late_rate,
                    AVG(bqt.days_late) as avg_days_late,
                    SUM(bqt.penalty_amount) as total_penalties_paid
                FROM business_quarterly_taxes bqt
                JOIN business_permits bp ON bqt.business_permit_id = bp.id
                WHERE bqt.year >= YEAR(CURDATE()) - 2
                GROUP BY bp.id
                HAVING chronic_late_rate > 75 AND total_quarters >= 4
                ORDER BY chronic_late_rate DESC";
        
        $stmt = $this->conn->query($sql);
        $chronic_late = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($chronic_late as $late) {
            $anomalies[] = [
                'id' => 'BUS-CHRONIC-' . uniqid(),
                'type' => 'CHRONIC_LATE_PAYER',
                'severity' => 'critical',
                'title' => 'Chronic Late Payer - Always Overdue',
                'description' => sprintf(
                    '%s is late on %s%% of quarterly payments over last 2 years',
                    $late['business_name'],
                    $late['chronic_late_rate']
                ),
                'business_name' => $late['business_name'],
                'owner' => $late['owner_full_name'],
                'applicant_id' => $late['applicant_id'],
                'barangay' => $late['business_barangay'],
                'district' => $late['business_district'],
                'chronic_late_rate' => floatval($late['chronic_late_rate']),
                'total_quarters' => intval($late['total_quarters']),
                'overdue_quarters' => intval($late['overdue_quarters']),
                'avg_days_late' => round(floatval($late['avg_days_late']), 1),
                'total_penalties_paid' => floatval($late['total_penalties_paid']),
                'ai_analysis' => sprintf(
                    'This business has been late on %d out of %d quarters. Total penalties paid: ₱%s',
                    $late['overdue_quarters'],
                    $late['total_quarters'],
                    number_format($late['total_penalties_paid'], 2)
                ),
                'recommendation' => 'Flag account for mandatory electronic payment enrollment. Consider business permit revocation if pattern continues.'
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
                FROM business_quarterly_taxes
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
        
        // Previous quarter stats
        $sql = "SELECT 
                    CONCAT('Q', QUARTER(DATE_SUB(CURDATE(), INTERVAL 3 MONTH))) as prev_quarter,
                    YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) as prev_year,
                    COUNT(*) as total_assessments,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    ROUND((SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as collection_rate
                FROM business_quarterly_taxes
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
            
            if ($stats['current_quarter']) {
                $stats['quarter_over_quarter_change'] = round(
                    $stats['current_quarter']['collection_rate'] - $stats['previous_quarter']['collection_rate'], 
                    1
                );
            }
        }
        
        // Top late barangays
        $sql = "SELECT 
                    bp.business_barangay as barangay,
                    COUNT(bqt.id) as total_payments,
                    SUM(CASE WHEN bqt.payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                    ROUND((SUM(CASE WHEN bqt.payment_status = 'overdue' THEN 1 ELSE 0 END) / COUNT(bqt.id)) * 100, 1) as overdue_rate,
                    ROUND(AVG(bqt.days_late), 1) as avg_days_late
                FROM business_quarterly_taxes bqt
                JOIN business_permits bp ON bqt.business_permit_id = bp.id
                WHERE bqt.year = YEAR(CURDATE())
                GROUP BY bp.business_barangay
                ORDER BY overdue_rate DESC
                LIMIT 5";
        
        $stmt = $this->conn->query($sql);
        $stats['top_late_barangays'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Business type breakdown
        $sql = "SELECT 
                    business_nature,
                    COUNT(*) as count,
                    ROUND(AVG(total_tax), 2) as avg_tax
                FROM business_permits
                GROUP BY business_nature
                ORDER BY count DESC
                LIMIT 5";
        
        $stmt = $this->conn->query($sql);
        $stats['business_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
    
    /**
     * HELPER: Analyze late payment
     */
    private function analyzeLatePayment($payment) {
        if ($payment['days_late'] > 60) {
            return "CRITICAL: Payment is {$payment['days_late']} days overdue. Business permit at risk of suspension.";
        } elseif ($payment['days_late'] > 30) {
            return "This payment is {$payment['days_late']} days late. Additional penalties will continue to accrue.";
        }
        return "Payment is {$payment['days_late']} days late. Standard late payment detected.";
    }
    
    /**
     * HELPER: Get late payment recommendation
     */
    private function getLatePaymentRecommendation($payment) {
        if ($payment['days_late'] > 60) {
            return "IMMEDIATE ACTION: Send final notice. Prepare for business permit suspension if payment not received within 15 days.";
        } elseif ($payment['days_late'] > 30) {
            return "Send follow-up notice with updated penalty amount. Offer payment plan if this is first offense.";
        } else {
            return "Send standard reminder notice. No further action required.";
        }
    }
    
    /**
     * HELPER: Estimate discount amount
     */
    private function estimateDiscountAmount($applicant_id) {
        // Placeholder - in real implementation, calculate actual discount
        return 500.00;
    }
}

// Direct access handler
if (basename($_SERVER['PHP_SELF']) == 'anomaly_detection_business.php') {
    try {
        $detector = new BusinessAnomalyDetection();
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