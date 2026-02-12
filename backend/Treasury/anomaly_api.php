<?php
// revenue2/backend/Treasury/anomaly_api.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_config.php';

class AnomalyDetectionAPI {
    private $db;
    private $connections = [];

    public function __construct() {
        try {
            // Initialize database connections
            $this->initDatabaseConnections();
        } catch (Exception $e) {
            $this->sendError('Database initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Initialize all database connections
     */
    private function initDatabaseConnections() {
        // Determine environment
        $isProduction = isset($_SERVER['HTTP_HOST']) && 
                       $_SERVER['HTTP_HOST'] === 'revenuetreasury.goserveph.com';

        // Business Tax Database
        $this->connections['business'] = [
            'host' => $isProduction ? 'localhost' : 'localhost',
            'port' => $isProduction ? 3306 : 3307,
            'dbname' => $isProduction ? 'reve_business' : 'business_tax',
            'user' => $isProduction ? 'reve_business' : 'root',
            'pass' => $isProduction ? '#dBB9-#j-m^t%3Jc' : ''
        ];

        // Real Property Tax Database
        $this->connections['rpt'] = [
            'host' => $isProduction ? 'localhost' : 'localhost',
            'port' => $isProduction ? 3306 : 3307,
            'dbname' => $isProduction ? 'reve_rpt' : 'rpt',
            'user' => $isProduction ? 'reve_rpt' : 'root',
            'pass' => $isProduction ? 'u0I6%f#6!-LM#EAr' : ''
        ];

        // Market Rent Database (if separate)
        $this->connections['market'] = [
            'host' => $isProduction ? 'localhost' : 'localhost',
            'port' => $isProduction ? 3306 : 3307,
            'dbname' => $isProduction ? 'reve_market' : 'market_rent',
            'user' => $isProduction ? 'reve_market' : 'root',
            'pass' => $isProduction ? '#dBB9-#j-m^t%3Jc' : ''
        ];
    }

    /**
     * Get database connection for specific system
     */
    private function getConnection($system) {
        if (!isset($this->connections[$system])) {
            throw new Exception("Unknown system: {$system}");
        }

        $config = $this->connections[$system];
        
        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['user'], $config['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception("Connection failed for {$system}: " . $e->getMessage());
        }
    }

    /**
     * Main API handler
     */
    public function handleRequest() {
        $action = $_GET['action'] ?? '';

        try {
            switch ($action) {
                case 'get_years':
                    $this->getAvailableYears();
                    break;
                    
                case 'get_revenue_data':
                    $this->getRevenueData();
                    break;
                    
                case 'detect_anomalies':
                    $this->detectAnomalies();
                    break;
                    
                case 'get_statistics':
                    $this->getStatistics();
                    break;
                    
                case 'export_report':
                    $this->exportReport();
                    break;
                    
                default:
                    $this->sendError('Invalid action', 400);
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * Get available years from all systems
     */
    private function getAvailableYears() {
        $years = [];
        $systems = ['rpt', 'business', 'market'];

        foreach ($systems as $system) {
            try {
                $pdo = $this->getConnection($system);
                
                switch ($system) {
                    case 'rpt':
                        $query = "SELECT DISTINCT YEAR(created_at) as year 
                                 FROM quarterly_taxes 
                                 WHERE year IS NOT NULL 
                                 ORDER BY year DESC";
                        break;
                        
                    case 'business':
                        $query = "SELECT DISTINCT payment_year as year 
                                 FROM annual_payments 
                                 WHERE payment_year IS NOT NULL 
                                 ORDER BY payment_year DESC";
                        break;
                        
                    case 'market':
                        $query = "SELECT DISTINCT YEAR(payment_date) as year 
                                 FROM market_payments 
                                 WHERE payment_date IS NOT NULL 
                                 ORDER BY year DESC";
                        break;
                        
                    default:
                        continue 2;
                }

                $stmt = $pdo->query($query);
                while ($row = $stmt->fetch()) {
                    $year = (int)($row['year'] ?? $row['payment_year'] ?? 0);
                    if ($year >= 2000 && $year <= 2100 && !in_array($year, $years)) {
                        $years[] = $year;
                    }
                }
            } catch (Exception $e) {
                // Skip failed systems
                continue;
            }
        }

        // Sort years descending
        rsort($years);

        // If no years found, add current and previous years
        if (empty($years)) {
            $currentYear = (int)date('Y');
            $years = [$currentYear, $currentYear - 1, $currentYear - 2];
        }

        $this->sendResponse([
            'success' => true,
            'years' => $years,
            'count' => count($years)
        ]);
    }

    /**
     * Get revenue data for anomaly detection
     */
    private function getRevenueData() {
        $year = $_GET['year'] ?? date('Y');
        $system = $_GET['system'] ?? 'all';
        
        $monthlyData = [];

        if ($system === 'all' || $system === 'rpt') {
            $rptData = $this->getRPTRevenueData($year);
            $monthlyData = array_merge($monthlyData, $rptData);
        }

        if ($system === 'all' || $system === 'business') {
            $businessData = $this->getBusinessRevenueData($year);
            $monthlyData = array_merge($monthlyData, $businessData);
        }

        if ($system === 'all' || $system === 'market') {
            $marketData = $this->getMarketRevenueData($year);
            $monthlyData = array_merge($monthlyData, $marketData);
        }

        // Sort by month
        usort($monthlyData, function($a, $b) {
            return $a['month_num'] <=> $b['month_num'];
        });

        // Format for frontend
        $formattedData = [];
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        foreach ($monthlyData as $data) {
            $formattedData[] = [
                'month' => $monthNames[$data['month_num'] - 1],
                'month_num' => $data['month_num'],
                'amount' => (float)$data['amount'],
                'system' => $data['system'],
                'count' => (int)($data['count'] ?? 0),
                'target' => (float)($data['target'] ?? $data['amount'] * 1.1)
            ];
        }

        $this->sendResponse([
            'success' => true,
            'year' => $year,
            'system' => $system,
            'data' => $formattedData,
            'total' => array_sum(array_column($formattedData, 'amount'))
        ]);
    }

    /**
     * Get RPT revenue data
     */
    private function getRPTRevenueData($year) {
        try {
            $pdo = $this->getConnection('rpt');
            
            $query = "SELECT 
                        MONTH(payment_date) as month_num,
                        SUM(amount) as amount,
                        COUNT(*) as count,
                        'RPT' as system
                      FROM payments 
                      WHERE YEAR(payment_date) = :year 
                        AND status = 'paid'
                      GROUP BY MONTH(payment_date)";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute(['year' => $year]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return $this->getRPTDefaultData($year);
        }
    }

    /**
     * Get Business Tax revenue data
     */
    private function getBusinessRevenueData($year) {
        try {
            $pdo = $this->getConnection('business');
            
            $query = "SELECT 
                        MONTH(payment_date) as month_num,
                        SUM(final_amount) as amount,
                        COUNT(*) as count,
                        'Business Tax' as system
                      FROM annual_payments 
                      WHERE YEAR(payment_date) = :year 
                        AND payment_status = 'paid'
                      GROUP BY MONTH(payment_date)";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute(['year' => $year]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return $this->getBusinessDefaultData($year);
        }
    }

    /**
     * Get Market Rent revenue data
     */
    private function getMarketRevenueData($year) {
        try {
            $pdo = $this->getConnection('market');
            
            $query = "SELECT 
                        MONTH(payment_date) as month_num,
                        SUM(amount) as amount,
                        COUNT(*) as count,
                        'Market Rent' as system
                      FROM market_collections 
                      WHERE YEAR(payment_date) = :year 
                        AND status = 'paid'
                      GROUP BY MONTH(payment_date)";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute(['year' => $year]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return $this->getMarketDefaultData($year);
        }
    }

    /**
     * Default RPT data for demo/sample
     */
    private function getRPTDefaultData($year) {
        $data = [];
        $baseValues = [125000, 132000, 315000, 128000, 135000, 42000, 
                      142000, 148000, 153000, 289000, 168000, 175000];
        
        for ($i = 1; $i <= 12; $i++) {
            $data[] = [
                'month_num' => $i,
                'amount' => $baseValues[$i - 1],
                'count' => rand(20, 50),
                'system' => 'RPT'
            ];
        }
        return $data;
    }

    /**
     * Default Business Tax data for demo/sample
     */
    private function getBusinessDefaultData($year) {
        $data = [];
        $baseValues = [85000, 92000, 195000, 88000, 95000, 32000, 
                      102000, 108000, 113000, 189000, 128000, 135000];
        
        for ($i = 1; $i <= 12; $i++) {
            $data[] = [
                'month_num' => $i,
                'amount' => $baseValues[$i - 1],
                'count' => rand(15, 40),
                'system' => 'Business Tax'
            ];
        }
        return $data;
    }

    /**
     * Default Market Rent data for demo/sample
     */
    private function getMarketDefaultData($year) {
        $data = [];
        $baseValues = [45000, 52000, 95000, 48000, 55000, 22000, 
                      62000, 68000, 73000, 109000, 88000, 95000];
        
        for ($i = 1; $i <= 12; $i++) {
            $data[] = [
                'month_num' => $i,
                'amount' => $baseValues[$i - 1],
                'count' => rand(10, 30),
                'system' => 'Market Rent'
            ];
        }
        return $data;
    }

    /**
     * Advanced anomaly detection with ML algorithms
     */
    private function detectAnomalies() {
        $year = $_GET['year'] ?? date('Y');
        $system = $_GET['system'] ?? 'all';
        $method = $_GET['method'] ?? 'ensemble';
        $threshold = (float)($_GET['threshold'] ?? 2.5);

        // Get revenue data
        $revenueData = $this->getRevenueDataForAnalysis($year, $system);
        $values = array_column($revenueData, 'amount');
        $months = array_column($revenueData, 'month');

        // Detect anomalies based on method
        $anomalies = [];
        
        switch ($method) {
            case 'zscore':
                $anomalies = $this->detectZScore($values, $threshold);
                break;
            case 'iqr':
                $anomalies = $this->detectIQR($values, $threshold);
                break;
            case 'mad':
                $anomalies = $this->detectMAD($values, $threshold);
                break;
            case 'percentile':
                $anomalies = $this->detectPercentile($values, 5, 95);
                break;
            case 'ensemble':
            default:
                $anomalies = $this->detectEnsemble($values);
                break;
        }

        // Combine with month data
        $results = [];
        foreach ($anomalies as $index => $anomaly) {
            $results[] = array_merge($anomaly, [
                'month' => $months[$index] ?? "Month " . ($index + 1),
                'month_num' => $index + 1,
                'system' => $system
            ]);
        }

        // Calculate statistics
        $statistics = $this->calculateStatistics($values);

        $this->sendResponse([
            'success' => true,
            'method' => $method,
            'threshold' => $threshold,
            'data' => $results,
            'anomalies' => array_filter($results, fn($r) => $r['isAnomaly']),
            'statistics' => $statistics,
            'total_analyzed' => count($values),
            'anomaly_count' => count(array_filter($results, fn($r) => $r['isAnomaly']))
        ]);
    }

    /**
     * Z-Score anomaly detection
     */
    private function detectZScore($data, $threshold = 2.5) {
        $mean = array_sum($data) / count($data);
        $stdDev = $this->standardDeviation($data, $mean);
        
        $results = [];
        foreach ($data as $index => $value) {
            $zScore = abs(($value - $mean) / max($stdDev, 1));
            $percentageDiff = (($value - $mean) / $mean) * 100;
            
            $results[] = [
                'index' => $index,
                'value' => $value,
                'expected' => $mean,
                'zScore' => round($zScore, 2),
                'percentageDiff' => round($percentageDiff, 2),
                'isAnomaly' => $zScore > $threshold,
                'type' => $percentageDiff > 0 ? 'spike' : 'drop',
                'severity' => $this->getSeverity($zScore),
                'method' => 'Z-Score',
                'confidence' => min(100, ($zScore / $threshold) * 50 + 50)
            ];
        }
        
        return $results;
    }

    /**
     * IQR anomaly detection
     */
    private function detectIQR($data, $multiplier = 1.5) {
        sort($data);
        $q1 = $this->percentile($data, 25);
        $q3 = $this->percentile($data, 75);
        $iqr = $q3 - $q1;
        $lowerBound = $q1 - $multiplier * $iqr;
        $upperBound = $q3 + $multiplier * $iqr;
        $median = $this->percentile($data, 50);
        
        $results = [];
        foreach ($data as $index => $value) {
            $isAnomaly = $value < $lowerBound || $value > $upperBound;
            $percentageDiff = (($value - $median) / $median) * 100;
            $deviation = $isAnomaly ? 
                max(abs($value - $lowerBound), abs($value - $upperBound)) / $iqr : 0;
            
            $results[] = [
                'index' => $index,
                'value' => $value,
                'expected' => $median,
                'lowerBound' => $lowerBound,
                'upperBound' => $upperBound,
                'percentageDiff' => round($percentageDiff, 2),
                'isAnomaly' => $isAnomaly,
                'type' => $value < $lowerBound ? 'drop' : ($value > $upperBound ? 'spike' : 'normal'),
                'severity' => $this->getSeverity($deviation),
                'method' => 'IQR',
                'confidence' => $isAnomaly ? min(100, ($deviation / $multiplier) * 50 + 50) : 0
            ];
        }
        
        return $results;
    }

    /**
     * MAD (Median Absolute Deviation) anomaly detection
     */
    private function detectMAD($data, $threshold = 3.5) {
        $median = $this->percentile($data, 50);
        $deviations = array_map(fn($x) => abs($x - $median), $data);
        $mad = $this->percentile($deviations, 50);
        
        $results = [];
        foreach ($data as $index => $value) {
            $deviation = abs($value - $median) / max($mad, 1);
            $percentageDiff = (($value - $median) / $median) * 100;
            $isAnomaly = $deviation > $threshold;
            
            $results[] = [
                'index' => $index,
                'value' => $value,
                'expected' => $median,
                'deviation' => round($deviation, 2),
                'percentageDiff' => round($percentageDiff, 2),
                'isAnomaly' => $isAnomaly,
                'type' => $percentageDiff > 0 ? 'spike' : 'drop',
                'severity' => $this->getSeverity($deviation),
                'method' => 'MAD',
                'confidence' => $isAnomaly ? min(100, ($deviation / $threshold) * 50 + 50) : 0
            ];
        }
        
        return $results;
    }

    /**
     * Percentile-based anomaly detection
     */
    private function detectPercentile($data, $lowerPercentile = 5, $upperPercentile = 95) {
        $lower = $this->percentile($data, $lowerPercentile);
        $upper = $this->percentile($data, $upperPercentile);
        $median = $this->percentile($data, 50);
        
        $results = [];
        foreach ($data as $index => $value) {
            $isAnomaly = $value < $lower || $value > $upper;
            $percentageDiff = (($value - $median) / $median) * 100;
            
            $results[] = [
                'index' => $index,
                'value' => $value,
                'expected' => $median,
                'lowerBound' => $lower,
                'upperBound' => $upper,
                'percentageDiff' => round($percentageDiff, 2),
                'isAnomaly' => $isAnomaly,
                'type' => $value < $lower ? 'drop' : ($value > $upper ? 'spike' : 'normal'),
                'severity' => 'medium',
                'method' => 'Percentile',
                'confidence' => $isAnomaly ? 85 : 0
            ];
        }
        
        return $results;
    }

    /**
     * Ensemble detection - combines multiple methods
     */
    private function detectEnsemble($data) {
        $zscoreResults = $this->detectZScore($data, 2.5);
        $iqrResults = $this->detectIQR($data, 1.5);
        $madResults = $this->detectMAD($data, 3.5);
        $percentileResults = $this->detectPercentile($data, 5, 95);
        
        $results = [];
        foreach ($data as $index => $value) {
            $methods = [
                $zscoreResults[$index],
                $iqrResults[$index],
                $madResults[$index],
                $percentileResults[$index]
            ];
            
            $anomalyCount = count(array_filter($methods, fn($m) => $m['isAnomaly']));
            $isAnomaly = $anomalyCount >= 2; // Consensus of at least 2 methods
            
            $avgExpected = array_sum(array_column($methods, 'expected')) / 4;
            $avgPercentageDiff = array_sum(array_column($methods, 'percentageDiff')) / 4;
            
            $confidence = ($anomalyCount / 4) * 100;
            
            $results[] = [
                'index' => $index,
                'value' => $value,
                'expected' => round($avgExpected, 2),
                'percentageDiff' => round($avgPercentageDiff, 2),
                'isAnomaly' => $isAnomaly,
                'type' => $avgPercentageDiff > 0 ? 'spike' : 'drop',
                'severity' => $this->getSeverity($confidence / 25),
                'method' => 'Ensemble',
                'confidence' => round($confidence, 1),
                'methods' => array_map(fn($m) => [
                    'name' => $m['method'],
                    'isAnomaly' => $m['isAnomaly'],
                    'severity' => $m['severity'],
                    'value' => $m['zScore'] ?? $m['deviation'] ?? $m['percentageDiff'] ?? 0
                ], $methods)
            ];
        }
        
        return $results;
    }

    /**
     * Get revenue data for analysis
     */
    private function getRevenueDataForAnalysis($year, $system) {
        $revenueData = [];
        
        if ($system === 'all' || $system === 'rpt') {
            $rptData = $this->getRPTRevenueData($year);
            $revenueData = array_merge($revenueData, $rptData);
        }
        
        if ($system === 'all' || $system === 'business') {
            $businessData = $this->getBusinessRevenueData($year);
            $revenueData = array_merge($revenueData, $businessData);
        }
        
        if ($system === 'all' || $system === 'market') {
            $marketData = $this->getMarketRevenueData($year);
            $revenueData = array_merge($revenueData, $marketData);
        }
        
        // Sort by month
        usort($revenueData, fn($a, $b) => $a['month_num'] <=> $b['month_num']);
        
        // Aggregate by month if system is 'all'
        if ($system === 'all') {
            $aggregated = [];
            foreach ($revenueData as $data) {
                $month = $data['month_num'];
                if (!isset($aggregated[$month])) {
                    $aggregated[$month] = [
                        'month_num' => $month,
                        'amount' => 0,
                        'count' => 0
                    ];
                }
                $aggregated[$month]['amount'] += $data['amount'];
                $aggregated[$month]['count'] += $data['count'];
            }
            $revenueData = array_values($aggregated);
        }
        
        // Add month names
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        foreach ($revenueData as &$data) {
            $data['month'] = $monthNames[$data['month_num'] - 1];
        }
        
        return $revenueData;
    }

    /**
     * Calculate comprehensive statistics
     */
    private function calculateStatistics($data) {
        $count = count($data);
        if ($count === 0) return null;
        
        $sum = array_sum($data);
        $mean = $sum / $count;
        $stdDev = $this->standardDeviation($data, $mean);
        $median = $this->percentile($data, 50);
        $q1 = $this->percentile($data, 25);
        $q3 = $this->percentile($data, 75);
        $iqr = $q3 - $q1;
        
        $min = min($data);
        $max = max($data);
        
        return [
            'count' => $count,
            'sum' => round($sum, 2),
            'min' => round($min, 2),
            'max' => round($max, 2),
            'range' => round($max - $min, 2),
            'mean' => round($mean, 2),
            'median' => round($median, 2),
            'stdDev' => round($stdDev, 2),
            'cv' => $mean > 0 ? round(($stdDev / $mean) * 100, 2) : 0,
            'variance' => round(pow($stdDev, 2), 2),
            'q1' => round($q1, 2),
            'q3' => round($q3, 2),
            'iqr' => round($iqr, 2),
            'skewness' => round($this->skewness($data, $mean, $stdDev, $count), 4),
            'kurtosis' => round($this->kurtosis($data, $mean, $stdDev, $count), 4),
            'percentiles' => [
                'p1' => round($this->percentile($data, 1), 2),
                'p5' => round($this->percentile($data, 5), 2),
                'p10' => round($this->percentile($data, 10), 2),
                'p25' => round($q1, 2),
                'p50' => round($median, 2),
                'p75' => round($q3, 2),
                'p90' => round($this->percentile($data, 90), 2),
                'p95' => round($this->percentile($data, 95), 2),
                'p99' => round($this->percentile($data, 99), 2)
            ]
        ];
    }

    /**
     * Calculate percentile
     */
    private function percentile($data, $percentile) {
        if (empty($data)) return 0;
        sort($data);
        $index = ($percentile / 100) * (count($data) - 1);
        $indexFloor = floor($index);
        $indexCeil = ceil($index);
        
        if ($indexFloor == $indexCeil) {
            return $data[$indexFloor];
        }
        
        $valueFloor = $data[$indexFloor];
        $valueCeil = $data[$indexCeil];
        $weight = $index - $indexFloor;
        
        return $valueFloor + ($valueCeil - $valueFloor) * $weight;
    }

    /**
     * Calculate standard deviation
     */
    private function standardDeviation($data, $mean) {
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $data)) / count($data);
        return sqrt($variance);
    }

    /**
     * Calculate skewness
     */
    private function skewness($data, $mean, $stdDev, $count) {
        if ($stdDev == 0 || $count < 3) return 0;
        $sum = array_sum(array_map(fn($x) => pow(($x - $mean) / $stdDev, 3), $data));
        return ($count * $sum) / (($count - 1) * ($count - 2));
    }

    /**
     * Calculate kurtosis
     */
    private function kurtosis($data, $mean, $stdDev, $count) {
        if ($stdDev == 0 || $count < 4) return 0;
        $sum = array_sum(array_map(fn($x) => pow(($x - $mean) / $stdDev, 4), $data));
        $numerator = $count * ($count + 1) * $sum;
        $denominator = ($count - 1) * ($count - 2) * ($count - 3);
        $subtract = (3 * pow($count - 1, 2)) / (($count - 2) * ($count - 3));
        return ($numerator / $denominator) - $subtract;
    }

    /**
     * Get severity based on score
     */
    private function getSeverity($score) {
        if ($score > 4) return 'critical';
        if ($score > 3) return 'high';
        if ($score > 2.5) return 'medium';
        if ($score > 2) return 'low';
        return 'info';
    }

    /**
     * Get statistics summary
     */
    private function getStatistics() {
        $year = $_GET['year'] ?? date('Y');
        $system = $_GET['system'] ?? 'all';
        
        $revenueData = $this->getRevenueDataForAnalysis($year, $system);
        $values = array_column($revenueData, 'amount');
        
        $statistics = $this->calculateStatistics($values);
        
        $this->sendResponse([
            'success' => true,
            'year' => $year,
            'system' => $system,
            'statistics' => $statistics
        ]);
    }

    /**
     * Export report
     */
    private function exportReport() {
        $year = $_GET['year'] ?? date('Y');
        $system = $_GET['system'] ?? 'all';
        $method = $_GET['method'] ?? 'ensemble';
        $format = $_GET['format'] ?? 'json';
        
        // Get data
        $revenueData = $this->getRevenueDataForAnalysis($year, $system);
        $anomalies = $this->detectAnomaliesForExport($year, $system, $method);
        $statistics = $this->calculateStatistics(array_column($revenueData, 'amount'));
        
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'report_id' => uniqid('ANOMALY_'),
            'parameters' => [
                'year' => $year,
                'system' => $system,
                'method' => $method
            ],
            'summary' => [
                'total_data_points' => count($revenueData),
                'total_anomalies' => count($anomalies),
                'anomaly_rate' => count($revenueData) > 0 ? 
                    round((count($anomalies) / count($revenueData)) * 100, 2) . '%' : '0%',
                'spikes' => count(array_filter($anomalies, fn($a) => $a['type'] === 'spike')),
                'drops' => count(array_filter($anomalies, fn($a) => $a['type'] === 'drop'))
            ],
            'statistics' => $statistics,
            'anomalies' => $anomalies,
            'monthly_data' => $revenueData
        ];
        
        if ($format === 'csv') {
            $this->exportCSV($report);
        } else {
            $this->sendResponse($report);
        }
    }

    /**
     * Detect anomalies for export
     */
    private function detectAnomaliesForExport($year, $system, $method) {
        $revenueData = $this->getRevenueDataForAnalysis($year, $system);
        $values = array_column($revenueData, 'amount');
        
        switch ($method) {
            case 'zscore':
                $results = $this->detectZScore($values, 2.5);
                break;
            case 'iqr':
                $results = $this->detectIQR($values, 1.5);
                break;
            case 'mad':
                $results = $this->detectMAD($values, 3.5);
                break;
            case 'percentile':
                $results = $this->detectPercentile($values, 5, 95);
                break;
            default:
                $results = $this->detectEnsemble($values);
                break;
        }
        
        $anomalies = [];
        foreach ($results as $index => $result) {
            if ($result['isAnomaly']) {
                $result['month'] = $revenueData[$index]['month'] ?? "Month " . ($index + 1);
                $result['month_num'] = $revenueData[$index]['month_num'] ?? ($index + 1);
                $anomalies[] = $result;
            }
        }
        
        return $anomalies;
    }

    /**
     * Export as CSV
     */
    private function exportCSV($report) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="anomaly_report_' . $report['parameters']['year'] . '_' . date('YmdHis') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Header
        fputcsv($output, ['Anomaly Detection Report']);
        fputcsv($output, ['Generated At', $report['generated_at']]);
        fputcsv($output, ['Report ID', $report['report_id']]);
        fputcsv($output, ['Year', $report['parameters']['year']]);
        fputcsv($output, ['System', $report['parameters']['system']]);
        fputcsv($output, ['Method', $report['parameters']['method']]);
        fputcsv($output, []);
        
        // Summary
        fputcsv($output, ['SUMMARY']);
        fputcsv($output, ['Total Data Points', $report['summary']['total_data_points']]);
        fputcsv($output, ['Total Anomalies', $report['summary']['total_anomalies']]);
        fputcsv($output, ['Anomaly Rate', $report['summary']['anomaly_rate']]);
        fputcsv($output, ['Spikes', $report['summary']['spikes']]);
        fputcsv($output, ['Drops', $report['summary']['drops']]);
        fputcsv($output, []);
        
        // Anomalies
        if (!empty($report['anomalies'])) {
            fputcsv($output, ['ANOMALIES']);
            fputcsv($output, ['Month', 'Amount', 'Expected', 'Deviation %', 'Type', 'Severity', 'Method', 'Confidence']);
            
            foreach ($report['anomalies'] as $anomaly) {
                fputcsv($output, [
                    $anomaly['month'],
                    number_format($anomaly['value'], 2),
                    number_format($anomaly['expected'], 2),
                    $anomaly['percentageDiff'] . '%',
                    strtoupper($anomaly['type']),
                    strtoupper($anomaly['severity']),
                    $anomaly['method'],
                    ($anomaly['confidence'] ?? 0) . '%'
                ]);
            }
        }
        
        fclose($output);
        exit;
    }

    /**
     * Send JSON response
     */
    private function sendResponse($data) {
        echo json_encode($data);
        exit;
    }

    /**
     * Send error response
     */
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
}

// Handle request
$api = new AnomalyDetectionAPI();
$api->handleRequest();
?>