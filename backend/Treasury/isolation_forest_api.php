<?php
// revenue2/backend/Treasury/isolation_forest_api.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

class IsolationForestAPI {
    private $db;
    
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        try {
            switch ($action) {
                case 'get_years':
                    $this->getAvailableYears();
                    break;
                case 'get_data':
                    $this->getRevenueData();
                    break;
                default:
                    $this->sendError('Invalid action', 400);
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 500);
        }
    }

    private function getAvailableYears() {
        $years = [2026, 2025, 2024, 2023];
        
        $this->sendResponse([
            'success' => true,
            'years' => $years
        ]);
    }

    private function getRevenueData() {
        $year = $_GET['year'] ?? date('Y');
        $system = $_GET['system'] ?? 'all';
        
        // Simple sample data with clear anomalies
        $data = [
            ['month' => 'Jan', 'amount' => 125000],
            ['month' => 'Feb', 'amount' => 132000],
            ['month' => 'Mar', 'amount' => 315000], // BIG SPIKE
            ['month' => 'Apr', 'amount' => 128000],
            ['month' => 'May', 'amount' => 135000],
            ['month' => 'Jun', 'amount' => 42000],  // BIG DROP
            ['month' => 'Jul', 'amount' => 142000],
            ['month' => 'Aug', 'amount' => 148000],
            ['month' => 'Sep', 'amount' => 153000],
            ['month' => 'Oct', 'amount' => 289000], // SPIKE
            ['month' => 'Nov', 'amount' => 168000],
            ['month' => 'Dec', 'amount' => 175000]
        ];

        $this->sendResponse([
            'success' => true,
            'year' => $year,
            'system' => $system,
            'data' => $data,
            'total' => array_sum(array_column($data, 'amount'))
        ]);
    }

    private function sendResponse($data) {
        echo json_encode($data);
        exit;
    }

    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
}

$api = new IsolationForestAPI();
$api->handleRequest();
?>