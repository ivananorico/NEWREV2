<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error handling - disable display errors for JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include your existing database connections
require_once '../../db/RPT/rpt_db.php';
require_once '../../db/Business/business_db.php';

class CollectionAnomalyAPI {
    private $rptConn;
    private $businessConn;
    
    public function __construct() {
        // Use your existing connection functions
        $this->rptConn = getRPTConnection();
        $this->businessConn = getBusinessConnection();
    }
    
    private function sendJson($data) {
        echo json_encode($data);
        exit;
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        try {
            switch($action) {
                case 'get_available_years':
                    $this->getAvailableYears();
                    break;
                case 'get_collection_data':
                    $this->getCollectionData();
                    break;
                case 'get_anomaly_history':
                    $this->getAnomalyHistory();
                    break;
                case 'get_system_health':
                    $this->getSystemHealth();
                    break;
                default:
                    $this->sendJson(['success' => false, 'message' => 'Invalid action']);
            }
        } catch (Exception $e) {
            $this->sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    private function getAvailableYears() {
        $years = [];
        $currentYear = (int)date('Y');
        
        // Try RPT
        if ($this->rptConn && !isset($this->rptConn['error'])) {
            try {
                $query = "SELECT DISTINCT YEAR(due_date) as year FROM quarterly_taxes 
                         UNION 
                         SELECT DISTINCT YEAR(payment_date) as year FROM quarterly_taxes 
                         WHERE payment_date IS NOT NULL
                         ORDER BY year DESC";
                $stmt = $this->rptConn->query($query);
                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $years[] = (int)$row['year'];
                }
            } catch (Exception $e) {
                error_log('RPT years error: ' . $e->getMessage());
            }
        }
        
        // Try Business
        if ($this->businessConn && !isset($this->businessConn['error'])) {
            try {
                $query = "SELECT DISTINCT YEAR(due_date) as year FROM business_quarterly_taxes 
                         UNION 
                         SELECT DISTINCT YEAR(payment_date) as year FROM business_quarterly_taxes 
                         WHERE payment_date IS NOT NULL
                         ORDER BY year DESC";
                $stmt = $this->businessConn->query($query);
                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $years[] = (int)$row['year'];
                }
            } catch (Exception $e) {
                error_log('Business years error: ' . $e->getMessage());
            }
        }
        
        // If no years found, add recent years
        if (empty($years)) {
            $years = [$currentYear, $currentYear - 1, $currentYear - 2];
        } else {
            $years = array_unique($years);
            rsort($years);
        }
        
        $this->sendJson([
            'success' => true,
            'years' => array_slice($years, 0, 10)
        ]);
    }
    
    private function getCollectionData() {
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $system = $_GET['system'] ?? 'all';
        
        $response = [
            'success' => true,
            'monthly' => [],
            'totals' => [
                'collected_amount' => 0,
                'target_amount' => 0,
                'transaction_count' => 0
            ]
        ];
        
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        // Initialize monthly data arrays
        $rptData = [];
        $businessData = [];
        
        for ($i = 1; $i <= 12; $i++) {
            $rptData[] = [
                'month' => $i,
                'month_name' => $months[$i-1],
                'collected_amount' => 0,
                'target_amount' => 0,
                'transaction_count' => 0,
                'system' => 'RPT',
                'variance' => 0
            ];
            
            $businessData[] = [
                'month' => $i,
                'month_name' => $months[$i-1],
                'collected_amount' => 0,
                'target_amount' => 0,
                'transaction_count' => 0,
                'system' => 'Business Tax',
                'variance' => 0
            ];
        }
        
        // Get RPT data
        if ($this->rptConn && !isset($this->rptConn['error']) && ($system === 'all' || $system === 'rpt')) {
            try {
                $query = "SELECT 
                            MONTH(due_date) as month,
                            COALESCE(SUM(total_quarterly_tax), 0) as collected,
                            COUNT(*) as transactions
                          FROM quarterly_taxes 
                          WHERE YEAR(due_date) = ? AND payment_status = 'paid'
                          GROUP BY MONTH(due_date)";
                
                $stmt = $this->rptConn->prepare($query);
                $stmt->execute([$year]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $row) {
                    $monthIdx = (int)$row['month'] - 1;
                    $rptData[$monthIdx]['collected_amount'] = (float)$row['collected'];
                    $rptData[$monthIdx]['transaction_count'] = (int)$row['transactions'];
                    $rptData[$monthIdx]['target_amount'] = (float)$row['collected'] * 1.1;
                    $rptData[$monthIdx]['variance'] = 10.0;
                }
            } catch (Exception $e) {
                error_log('RPT data error: ' . $e->getMessage());
            }
        }
        
        // Get Business data
        if ($this->businessConn && !isset($this->businessConn['error']) && ($system === 'all' || $system === 'business')) {
            try {
                $query = "SELECT 
                            MONTH(due_date) as month,
                            COALESCE(SUM(total_quarterly_tax), 0) as collected,
                            COUNT(*) as transactions
                          FROM business_quarterly_taxes 
                          WHERE YEAR(due_date) = ? AND payment_status = 'paid'
                          GROUP BY MONTH(due_date)";
                
                $stmt = $this->businessConn->prepare($query);
                $stmt->execute([$year]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $row) {
                    $monthIdx = (int)$row['month'] - 1;
                    $businessData[$monthIdx]['collected_amount'] = (float)$row['collected'];
                    $businessData[$monthIdx]['transaction_count'] = (int)$row['transactions'];
                    $businessData[$monthIdx]['target_amount'] = (float)$row['collected'] * 1.1;
                    $businessData[$monthIdx]['variance'] = 10.0;
                }
            } catch (Exception $e) {
                error_log('Business data error: ' . $e->getMessage());
            }
        }
        
        // Build response based on system filter
        if ($system === 'rpt') {
            $response['monthly'] = $rptData;
            $response['totals'] = $this->calculateTotal($rptData);
            $response['rpt'] = ['monthly' => $rptData, 'total' => $response['totals']];
        } elseif ($system === 'business') {
            $response['monthly'] = $businessData;
            $response['totals'] = $this->calculateTotal($businessData);
            $response['business'] = ['monthly' => $businessData, 'total' => $response['totals']];
        } else {
            $response['rpt'] = ['monthly' => $rptData, 'total' => $this->calculateTotal($rptData)];
            $response['business'] = ['monthly' => $businessData, 'total' => $this->calculateTotal($businessData)];
            $response['monthly'] = array_merge($rptData, $businessData);
            $response['totals'] = [
                'collected_amount' => $response['rpt']['total']['collected_amount'] + $response['business']['total']['collected_amount'],
                'target_amount' => $response['rpt']['total']['target_amount'] + $response['business']['total']['target_amount'],
                'transaction_count' => $response['rpt']['total']['transaction_count'] + $response['business']['total']['transaction_count']
            ];
            $response['system_breakdown'] = [
                ['system' => 'RPT', 'collected_amount' => $response['rpt']['total']['collected_amount']],
                ['system' => 'Business Tax', 'collected_amount' => $response['business']['total']['collected_amount']]
            ];
        }
        
        $this->sendJson($response);
    }
    
    private function calculateTotal($data) {
        $total = [
            'collected_amount' => 0,
            'target_amount' => 0,
            'transaction_count' => 0
        ];
        
        foreach ($data as $month) {
            $total['collected_amount'] += $month['collected_amount'];
            $total['target_amount'] += $month['target_amount'];
            $total['transaction_count'] += $month['transaction_count'];
        }
        
        return $total;
    }
    
    private function getAnomalyHistory() {
        $history = [];
        
        // Return empty history - tables may not exist
        $this->sendJson(['success' => true, 'history' => $history]);
    }
    
    private function getSystemHealth() {
        $health = [
            'rpt' => ['status' => 'unknown', 'response_time' => 0, 'record_count' => 0],
            'business' => ['status' => 'unknown', 'response_time' => 0, 'record_count' => 0],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Check RPT
        if ($this->rptConn && !isset($this->rptConn['error'])) {
            try {
                $start = microtime(true);
                $query = "SELECT COUNT(*) as count FROM quarterly_taxes";
                $stmt = $this->rptConn->query($query);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $end = microtime(true);
                
                $health['rpt'] = [
                    'status' => 'healthy',
                    'response_time' => round(($end - $start) * 1000, 2),
                    'record_count' => (int)$result['count']
                ];
            } catch (Exception $e) {
                $health['rpt']['status'] = 'error';
                $health['rpt']['error'] = $e->getMessage();
            }
        }
        
        // Check Business
        if ($this->businessConn && !isset($this->businessConn['error'])) {
            try {
                $start = microtime(true);
                $query = "SELECT COUNT(*) as count FROM business_quarterly_taxes";
                $stmt = $this->businessConn->query($query);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $end = microtime(true);
                
                $health['business'] = [
                    'status' => 'healthy',
                    'response_time' => round(($end - $start) * 1000, 2),
                    'record_count' => (int)$result['count']
                ];
            } catch (Exception $e) {
                $health['business']['status'] = 'error';
                $health['business']['error'] = $e->getMessage();
            }
        }
        
        $this->sendJson(['success' => true, 'health' => $health]);
    }
}

$api = new CollectionAnomalyAPI();
$api->handleRequest();
?>