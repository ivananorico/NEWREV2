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

// revenue2/backend/Treasury/anomaly_detection.php
require_once 'db_config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Route to appropriate anomaly detector
$system = $_GET['system'] ?? 'all';
$action = $_GET['action'] ?? 'detect';

try {
    switch($system) {
        case 'rpt':
            require_once 'anomaly_detection_rpt.php';
            $detector = new RPTAnomalyDetection();
            echo json_encode($detector->detectAllAnomalies());
            break;
            
        case 'business':
            require_once 'anomaly_detection_business.php';
            $detector = new BusinessAnomalyDetection();
            echo json_encode($detector->detectAllAnomalies());
            break;
            
        case 'market':
            // FIXED: Market Rent module is now LIVE
            require_once 'anomaly_detection_market.php';
            $detector = new MarketAnomalyDetection();
            echo json_encode($detector->detectAllAnomalies());
            break;
            
        case 'all':
            $response = [];
            
            // RPT
            require_once 'anomaly_detection_rpt.php';
            $rpt = new RPTAnomalyDetection();
            $response['rpt'] = $rpt->detectAllAnomalies();
            
            // Business
            require_once 'anomaly_detection_business.php';
            $business = new BusinessAnomalyDetection();
            $response['business'] = $business->detectAllAnomalies();
            
            // Market - FIXED: Now with real data
            require_once 'anomaly_detection_market.php';
            $market = new MarketAnomalyDetection();
            $response['market'] = $market->detectAllAnomalies();
            
            echo json_encode($response);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid system specified']);
    }
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>