<?php
// revenue2/backend/Treasury/anomaly_detection_market.php
// FOCUS: Market Rent Monthly Anomalies Only
// 5 Anomaly Types: Late Payments, Pattern Changes, Discount Abuse, Geographic Clusters, Chronic Late

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

class MarketAnomalyDetection {
    private $conn;
    
    public function __construct() {
        $this->conn = Database::getConnection('market');
    }
    
    /**
     * MAIN METHOD: Detect all market rent anomalies
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
            'monthly_stats' => $this->getMonthlyStats(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 1. DETECT LATE PAYMENT ANOMALIES
     * Stalls with excessive lateness (>15 days)
     */
    private function detectLatePaymentAnomalies() {
        $anomalies = [];
        
        // Critical late payments (>15 days late) for current month
        $sql = "SELECT 
                    mrb.id,
                    mrb.rent_total_id,
                    mrb.billing_month,
                    mrb.billing_year,
                    mrb.due_date,
                    mrb.payment_date,
                    mrb.days_late,
                    mrb.penalty_amount,
                    mrb.base_rent,
                    mrb.total_amount_due,
                    mrb.payment_status,
                    rs.business_name,
                    rs.stall_rights_no,
                    ro.first_name,
                    ro.last_name,
                    ro.renter_code,
                    ro.barangay,
                    m.name as market_name,
                    sc.class_name,
                    sc.price as standard_rent
                FROM monthly_rent_billing mrb
                JOIN rent_totals rt ON mrb.rent_total_id = rt.id
                JOIN rent_stall rs ON rt.rent_stall_id = rs.id
                JOIN renter_owner ro ON rt.renter_id = ro.id
                JOIN stalls s ON rs.stall_id = s.id
                JOIN maps m ON s.map_id = m.id
                JOIN stall_rights sc ON s.class_id = sc.class_id
                WHERE mrb.billing_year = YEAR(CURDATE())
                    AND mrb.billing_month = MONTH(CURDATE())
                    AND mrb.payment_status = 'overdue'
                    AND mrb.days_late > 15
                ORDER BY mrb.days_late DESC";
        
        $stmt = $this->conn->query($sql);
        $late_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($late_payments as $late) {
            $anomalies[] = [
                'id' => 'MKT-LATE-' . uniqid(),
                'type' => 'CRITICAL_LATE_PAYMENT',
                'severity' => $late['days_late'] > 25 ? 'critical' : 'warning',
                'title' => 'Excessively Late Monthly Rent Payment',
                'description' => sprintf(
                    '%s (%s) is %d days late for %s/%d rent',
                    $late['business_name'],
                    $late['first_name'] . ' ' . $late['last_name'],
                    $late['days_late'],
                    $late['billing_month'],
                    $late['billing_year']
                ),
                'business_name' => $late['business_name'],
                'renter' => $late['first_name'] . ' ' . $late['last_name'],
                'renter_code' => $late['renter_code'],
                'stall_no' => $late['stall_rights_no'],
                'stall_class' => $late['class_name'],
                'barangay' => $late['barangay'],
                'market' => $late['market_name'],
                'month' => $late['billing_month'],
                'year' => $late['billing_year'],
                'days_late' => intval($late['days_late']),
                'due_date' => $late['due_date'],
                'rent_amount' => floatval($late['base_rent']),
                'penalty_amount' => floatval($late['penalty_amount']),
                'total_due' => floatval($late['total_amount_due']),
                'penalty_rate' => $late['days_late'] > 0 ? 
                    round(($late['penalty_amount'] / $late['base_rent']) * 100, 2) : 0,
                'ai_analysis' => $this->analyzeLatePayment($late),
                'recommendation' => $this->getLatePaymentRecommendation($late)
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * 2. DETECT PAYMENT PATTERN ANOMALIES
     * Sudden changes in payment behavior (on-time → late)
     */
    private function detectPaymentPatternAnomalies() {
        $anomalies = [];
        
        // Compare payment patterns month over month, year over year
        $sql = "SELECT 
                    rs.id as stall_id,
                    rs.business_name,
                    rs.stall_rights_no,
                    ro.first_name,
                    ro.last_name,
                    ro.renter_code,
                    ro.barangay,
                    sc.class_name,
                    AVG(CASE WHEN mrb.billing_year = YEAR(CURDATE()) - 1 
                        AND mrb.billing_month = MONTH(CURDATE()) 
                        THEN mrb.days_late ELSE NULL END) as prev_year_days_late,
                    AVG(CASE WHEN mrb.billing_year = YEAR(CURDATE()) 
                        AND mrb.billing_month = MONTH(CURDATE()) 
                        THEN mrb.days_late ELSE NULL END) as current_year_days_late,
                    COUNT(CASE WHEN mrb.billing_year = YEAR(CURDATE()) - 1 
                        AND mrb.payment_status = 'paid' THEN 1 END) as prev_year_paid_count,
                    COUNT(CASE WHEN mrb.billing_year = YEAR(CURDATE()) 
                        AND mrb.payment_status = 'overdue' THEN 1 END) as current_year_overdue_count
                FROM monthly_rent_billing mrb
                JOIN rent_totals rt ON mrb.rent_total_id = rt.id
                JOIN rent_stall rs ON rt.rent_stall_id = rs.id
                JOIN renter_owner ro ON rt.renter_id = ro.id
                JOIN stalls s ON rs.stall_id = s.id
                JOIN stall_rights sc ON s.class_id = sc.class_id
                WHERE mrb.billing_year IN (YEAR(CURDATE()) - 1, YEAR(CURDATE()))
                    AND mrb.billing_month = MONTH(CURDATE())
                GROUP BY rs.id
                HAVING prev_year_paid_count > 0 
                    AND prev_year_days_late = 0
                    AND current_year_overdue_count > 0
                    AND current_year_days_late > 10";
        
        $stmt = $this->conn->query($sql);
        $pattern_changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pattern_changes as $pattern) {
            $anomalies[] = [
                'id' => 'MKT-PATTERN-' . uniqid(),
                'type' => 'PAYMENT_PATTERN_CHANGE',
                'severity' => 'warning',
                'title' => 'Sudden Change in Rent Payment Behavior',
                'description' => sprintf(
                    '%s switched from on-time payments last year to %d days late this month',
                    $pattern['business_name'],
                    round($pattern['current_year_days_late'])
                ),
                'business_name' => $pattern['business_name'],
                'renter' => $pattern['first_name'] . ' ' . $pattern['last_name'],
                'renter_code' => $pattern['renter_code'],
                'stall_no' => $pattern['stall_rights_no'],
                'stall_class' => $pattern['class_name'],
                'barangay' => $pattern['barangay'],
                'previous_status' => 'On-time',
                'current_status' => round($pattern['current_year_days_late']) . ' days late',
                'change_percent' => 100,
                'ai_analysis' => 'Renter consistently paid on time last year but is now delinquent. May indicate business difficulties or cash flow problems.',
                'recommendation' => 'Contact renter to verify business status and offer payment assistance programs.'
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
        
        // Stalls that always get discount (suspicious - 100% discount rate)
        $sql = "SELECT 
                    rs.id as stall_id,
                    rs.business_name,
                    rs.stall_rights_no,
                    ro.first_name,
                    ro.last_name,
                    ro.renter_code,
                    ro.barangay,
                    sc.class_name,
                    COUNT(mrb.id) as total_payments,
                    SUM(CASE WHEN mrb.discount_applied = 1 THEN 1 ELSE 0 END) as discounted_payments,
                    ROUND((SUM(CASE WHEN mrb.discount_applied = 1 THEN 1 ELSE 0 END) / COUNT(mrb.id)) * 100, 1) as discount_rate,
                    AVG(mrb.days_late) as avg_days_late
                FROM monthly_rent_billing mrb
                JOIN rent_totals rt ON mrb.rent_total_id = rt.id
                JOIN rent_stall rs ON rt.rent_stall_id = rs.id
                JOIN renter_owner ro ON rt.renter_id = ro.id
                JOIN stalls s ON rs.stall_id = s.id
                JOIN stall_rights sc ON s.class_id = sc.class_id
                WHERE mrb.billing_year >= YEAR(CURDATE()) - 1
                    AND mrb.payment_status = 'paid'
                GROUP BY rs.id
                HAVING discount_rate >= 90 AND total_payments >= 6";
        
        $stmt = $this->conn->query($sql);
        $high_discount = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($high_discount as $stall) {
            $anomalies[] = [
                'id' => 'MKT-DISCOUNT-' . uniqid(),
                'type' => 'UNUSUAL_DISCOUNT_PATTERN',
                'severity' => 'warning',
                'title' => 'Consistently High Discount Rate',
                'description' => sprintf(
                    '%s receives discount on %s%% of %d payments',
                    $stall['business_name'],
                    $stall['discount_rate'],
                    $stall['total_payments']
                ),
                'business_name' => $stall['business_name'],
                'renter' => $stall['first_name'] . ' ' . $stall['last_name'],
                'renter_code' => $stall['renter_code'],
                'stall_no' => $stall['stall_rights_no'],
                'stall_class' => $stall['class_name'],
                'barangay' => $stall['barangay'],
                'total_payments' => intval($stall['total_payments']),
                'discounted_payments' => intval($stall['discounted_payments']),
                'discount_rate' => floatval($stall['discount_rate']),
                'avg_days_late' => floatval($stall['avg_days_late']),
                'expected_rate' => 30.0,
                'ai_analysis' => 'This stall receives discounts significantly more often than average. Verify if all payments are made within discount period (1st-10th).',
                'recommendation' => 'Audit payment dates for this stall to verify discount eligibility. Check if payments are backdated.'
            ];
        }
        
        // Stalls that missed discount this month but always got it before
        $sql = "SELECT 
                    rs.business_name,
                    rs.stall_rights_no,
                    ro.first_name,
                    ro.last_name,
                    ro.renter_code,
                    ro.barangay,
                    sc.class_name,
                    MAX(CASE WHEN mrb.billing_year = YEAR(CURDATE()) - 1 
                        AND mrb.billing_month = MONTH(CURDATE())
                        AND mrb.discount_applied = 1 THEN 1 ELSE 0 END) as prev_year_discount,
                    MAX(CASE WHEN mrb.billing_year = YEAR(CURDATE()) 
                        AND mrb.billing_month = MONTH(CURDATE())
                        AND mrb.discount_applied = 0 THEN 1 ELSE 0 END) as current_year_no_discount,
                    MAX(CASE WHEN mrb.billing_year = YEAR(CURDATE()) 
                        AND mrb.billing_month = MONTH(CURDATE())
                        THEN mrb.payment_date END) as current_payment_date,
                    MAX(CASE WHEN mrb.billing_year = YEAR(CURDATE()) 
                        AND mrb.billing_month = MONTH(CURDATE())
                        THEN mrb.due_date END) as due_date
                FROM monthly_rent_billing mrb
                JOIN rent_totals rt ON mrb.rent_total_id = rt.id
                JOIN rent_stall rs ON rt.rent_stall_id = rs.id
                JOIN renter_owner ro ON rt.renter_id = ro.id
                JOIN stalls s ON rs.stall_id = s.id
                JOIN stall_rights sc ON s.class_id = sc.class_id
                WHERE mrb.billing_year >= YEAR(CURDATE()) - 1
                    AND mrb.billing_month = MONTH(CURDATE())
                GROUP BY rs.id
                HAVING prev_year_discount = 1 AND current_year_no_discount = 1";
        
        $stmt = $this->conn->query($sql);
        $missed_discount = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($missed_discount as $missed) {
            $anomalies[] = [
                'id' => 'MKT-MISSED-DISCOUNT-' . uniqid(),
                'type' => 'MISSED_DISCOUNT_OPPORTUNITY',
                'severity' => 'info',
                'title' => 'Stall Missed Early Payment Discount',
                'description' => sprintf(
                    '%s always paid early last year but missed discount period this month',
                    $missed['business_name']
                ),
                'business_name' => $missed['business_name'],
                'renter' => $missed['first_name'] . ' ' . $missed['last_name'],
                'renter_code' => $missed['renter_code'],
                'stall_no' => $missed['stall_rights_no'],
                'stall_class' => $missed['class_name'],
                'barangay' => $missed['barangay'],
                'payment_date' => $missed['current_payment_date'],
                'due_date' => $missed['due_date'],
                'potential_savings' => $this->estimateDiscountAmount($missed['stall_class']),
                'ai_analysis' => 'Renter has changed payment timing and lost discount benefits. May need reminder about 10% early payment discount.',
                'recommendation' => 'Send SMS/notice about early payment discount benefits for next month.'
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * 4. DETECT GEOGRAPHIC LATE PAYMENT CLUSTERS
     * Barangays with unusually high late payment rates
     */
    private function detectGeographicLatePaymentClusters() {
        $anomalies = [];
        
        $sql = "SELECT 
                    ro.barangay,
                    COUNT(mrb.id) as total_payments,
                    SUM(CASE WHEN mrb.payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                    ROUND((SUM(CASE WHEN mrb.payment_status = 'overdue' THEN 1 ELSE 0 END) / COUNT(mrb.id)) * 100, 1) as overdue_rate,
                    AVG(CASE WHEN mrb.days_late > 0 THEN mrb.days_late ELSE 0 END) as avg_days_late,
                    COUNT(DISTINCT rs.id) as stall_count
                FROM monthly_rent_billing mrb
                JOIN rent_totals rt ON mrb.rent_total_id = rt.id
                JOIN rent_stall rs ON rt.rent_stall_id = rs.id
                JOIN renter_owner ro ON rt.renter_id = ro.id
                WHERE mrb.billing_year = YEAR(CURDATE())
                GROUP BY ro.barangay
                HAVING overdue_rate > 40 AND total_payments > 10
                ORDER BY overdue_rate DESC";
        
        $stmt = $this->conn->query($sql);
        $clusters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($clusters as $cluster) {
            $anomalies[] = [
                'id' => 'MKT-GEO-' . uniqid(),
                'type' => 'LATE_PAYMENT_CLUSTER',
                'severity' => $cluster['overdue_rate'] > 60 ? 'critical' : 'warning',
                'title' => 'High Concentration of Late Rent Payments',
                'description' => sprintf(
                    '%s has %s%% overdue rate (%d of %d payments)',
                    $cluster['barangay'],
                    $cluster['overdue_rate'],
                    $cluster['overdue_count'],
                    $cluster['total_payments']
                ),
                'barangay' => $cluster['barangay'],
                'overdue_rate' => floatval($cluster['overdue_rate']),
                'overdue_count' => intval($cluster['overdue_count']),
                'total_payments' => intval($cluster['total_payments']),
                'avg_days_late' => round(floatval($cluster['avg_days_late']), 1),
                'stall_count' => intval($cluster['stall_count']),
                'ai_analysis' => sprintf(
                    'This barangay has %s%% overdue rate. Consider targeted collection campaign.',
                    $cluster['overdue_rate']
                ),
                'recommendation' => 'Coordinate with barangay captain for information campaign. Post payment deadline notices in the area.'
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * 5. DETECT CONSISTENT LATE PAYERS
     * Stalls that are always late month after month
     */
    private function detectConsistentLatePayers() {
        $anomalies = [];
        
        $sql = "SELECT 
                    rs.business_name,
                    rs.stall_rights_no,
                    ro.first_name,
                    ro.last_name,
                    ro.renter_code,
                    ro.barangay,
                    sc.class_name,
                    COUNT(mrb.id) as total_months,
                    SUM(CASE WHEN mrb.payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_months,
                    ROUND((SUM(CASE WHEN mrb.payment_status = 'overdue' THEN 1 ELSE 0 END) / COUNT(mrb.id)) * 100, 1) as chronic_late_rate,
                    AVG(mrb.days_late) as avg_days_late,
                    SUM(mrb.penalty_amount) as total_penalties_paid
                FROM monthly_rent_billing mrb
                JOIN rent_totals rt ON mrb.rent_total_id = rt.id
                JOIN rent_stall rs ON rt.rent_stall_id = rs.id
                JOIN renter_owner ro ON rt.renter_id = ro.id
                JOIN stalls s ON rs.stall_id = s.id
                JOIN stall_rights sc ON s.class_id = sc.class_id
                WHERE mrb.billing_year >= YEAR(CURDATE()) - 1
                GROUP BY rs.id
                HAVING chronic_late_rate > 70 AND total_months >= 6
                ORDER BY chronic_late_rate DESC";
        
        $stmt = $this->conn->query($sql);
        $chronic_late = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($chronic_late as $late) {
            $anomalies[] = [
                'id' => 'MKT-CHRONIC-' . uniqid(),
                'type' => 'CHRONIC_LATE_PAYER',
                'severity' => 'critical',
                'title' => 'Chronic Late Payer - Always Overdue',
                'description' => sprintf(
                    '%s is late on %s%% of monthly payments over last 12 months',
                    $late['business_name'],
                    $late['chronic_late_rate']
                ),
                'business_name' => $late['business_name'],
                'renter' => $late['first_name'] . ' ' . $late['last_name'],
                'renter_code' => $late['renter_code'],
                'stall_no' => $late['stall_rights_no'],
                'stall_class' => $late['class_name'],
                'barangay' => $late['barangay'],
                'chronic_late_rate' => floatval($late['chronic_late_rate']),
                'total_months' => intval($late['total_months']),
                'overdue_months' => intval($late['overdue_months']),
                'avg_days_late' => round(floatval($late['avg_days_late']), 1),
                'total_penalties_paid' => floatval($late['total_penalties_paid']),
                'ai_analysis' => sprintf(
                    'This stall has been late on %d out of %d months. Total penalties paid: ₱%s',
                    $late['overdue_months'],
                    $late['total_months'],
                    number_format($late['total_penalties_paid'], 2)
                ),
                'recommendation' => 'Flag account for mandatory advance rent deposit. Consider stall rights revocation if pattern continues.'
            ];
        }
        
        return $anomalies;
    }
    
    /**
     * GET MONTHLY STATISTICS
     */
    private function getMonthlyStats() {
        $stats = [];
        
        // Current month stats
        $sql = "SELECT 
                    MONTHNAME(CURDATE()) as current_month,
                    YEAR(CURDATE()) as current_year,
                    MONTH(CURDATE()) as month_num,
                    COUNT(*) as total_billings,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    ROUND(AVG(CASE WHEN days_late > 0 THEN days_late ELSE 0 END), 1) as avg_days_late,
                    SUM(penalty_amount) as total_penalties,
                    SUM(CASE WHEN discount_applied = 1 THEN 1 ELSE 0 END) as discount_count,
                    ROUND((SUM(CASE WHEN discount_applied = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as discount_rate,
                    SUM(total_amount_due) as total_collection
                FROM monthly_rent_billing
                WHERE billing_year = YEAR(CURDATE()) 
                    AND billing_month = MONTH(CURDATE())";
        
        $stmt = $this->conn->query($sql);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current) {
            $stats['current_month'] = [
                'month' => $current['current_month'],
                'month_num' => intval($current['month_num']),
                'year' => $current['current_year'],
                'total' => intval($current['total_billings']),
                'paid' => intval($current['paid_count']),
                'overdue' => intval($current['overdue_count']),
                'pending' => intval($current['pending_count']),
                'collection_rate' => $current['total_billings'] > 0 ? 
                    round(($current['paid_count'] / $current['total_billings']) * 100, 1) : 0,
                'avg_days_late' => floatval($current['avg_days_late']),
                'total_penalties' => floatval($current['total_penalties']),
                'discount_rate' => floatval($current['discount_rate']),
                'total_collection' => floatval($current['total_collection'])
            ];
        }
        
        // Previous month stats
        $sql = "SELECT 
                    MONTHNAME(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) as prev_month,
                    MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) as prev_month_num,
                    YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) as prev_year,
                    COUNT(*) as total_billings,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    ROUND((SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as collection_rate,
                    SUM(total_amount_due) as total_collection
                FROM monthly_rent_billing
                WHERE billing_year = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                    AND billing_month = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        
        $stmt = $this->conn->query($sql);
        $previous = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($previous) {
            $stats['previous_month'] = [
                'month' => $previous['prev_month'],
                'year' => $previous['prev_year'],
                'total' => intval($previous['total_billings']),
                'paid' => intval($previous['paid_count']),
                'collection_rate' => floatval($previous['collection_rate']),
                'total_collection' => floatval($previous['total_collection'])
            ];
            
            if ($stats['current_month']) {
                $stats['month_over_month_change'] = round(
                    $stats['current_month']['collection_rate'] - $stats['previous_month']['collection_rate'], 
                    1
                );
            }
        }
        
        // Top late barangays
        $sql = "SELECT 
                    ro.barangay,
                    COUNT(mrb.id) as total_payments,
                    SUM(CASE WHEN mrb.payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                    ROUND((SUM(CASE WHEN mrb.payment_status = 'overdue' THEN 1 ELSE 0 END) / COUNT(mrb.id)) * 100, 1) as overdue_rate,
                    ROUND(AVG(mrb.days_late), 1) as avg_days_late,
                    COUNT(DISTINCT rs.id) as stall_count
                FROM monthly_rent_billing mrb
                JOIN rent_totals rt ON mrb.rent_total_id = rt.id
                JOIN rent_stall rs ON rt.rent_stall_id = rs.id
                JOIN renter_owner ro ON rt.renter_id = ro.id
                WHERE mrb.billing_year = YEAR(CURDATE())
                GROUP BY ro.barangay
                ORDER BY overdue_rate DESC
                LIMIT 5";
        
        $stmt = $this->conn->query($sql);
        $stats['top_late_barangays'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Stall class occupancy and payment stats
        $sql = "SELECT 
                    sc.class_name,
                    COUNT(DISTINCT rs.id) as occupied_stalls,
                    COUNT(mrb.id) as total_payments,
                    ROUND(AVG(mrb.days_late), 1) as avg_days_late,
                    ROUND((SUM(CASE WHEN mrb.payment_status = 'paid' THEN 1 ELSE 0 END) / COUNT(mrb.id)) * 100, 1) as payment_rate
                FROM monthly_rent_billing mrb
                JOIN rent_totals rt ON mrb.rent_total_id = rt.id
                JOIN rent_stall rs ON rt.rent_stall_id = rs.id
                JOIN stalls s ON rs.stall_id = s.id
                JOIN stall_rights sc ON s.class_id = sc.class_id
                WHERE mrb.billing_year = YEAR(CURDATE())
                GROUP BY sc.class_name
                ORDER BY sc.class_name";
        
        $stmt = $this->conn->query($sql);
        $stats['stall_classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
    
    /**
     * HELPER: Analyze late payment
     */
    private function analyzeLatePayment($payment) {
        if ($payment['days_late'] > 25) {
            return "CRITICAL: Payment is {$payment['days_late']} days overdue. Stall rights at risk of termination.";
        } elseif ($payment['days_late'] > 15) {
            return "This payment is {$payment['days_late']} days late. Additional 5% penalty per month will continue to accrue.";
        }
        return "Payment is {$payment['days_late']} days late. Standard late payment detected.";
    }
    
    /**
     * HELPER: Get late payment recommendation
     */
    private function getLatePaymentRecommendation($payment) {
        if ($payment['days_late'] > 25) {
            return "IMMEDIATE ACTION: Send final notice. Prepare for stall rights revocation if payment not received within 7 days.";
        } elseif ($payment['days_late'] > 15) {
            return "Send follow-up notice with updated penalty amount. Offer payment plan if this is first offense.";
        } else {
            return "Send standard reminder notice. No further action required.";
        }
    }
    
    /**
     * HELPER: Estimate discount amount based on stall class
     */
    private function estimateDiscountAmount($class_name) {
        switch ($class_name) {
            case 'A':
                return 1500.00;
            case 'B':
                return 1000.00;
            case 'C':
                return 500.00;
            case 'D':
                return 250.00;
            default:
                return 500.00;
        }
    }
}

// Direct access handler
if (basename($_SERVER['PHP_SELF']) == 'anomaly_detection_market.php') {
    try {
        $detector = new MarketAnomalyDetection();
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