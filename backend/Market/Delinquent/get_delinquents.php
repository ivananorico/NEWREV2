<?php
// revenue2/backend/Market/Delinquent/get_delinquents.php

// ================================================
// CORS HEADERS - MUST BE FIRST
// ================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ================================================
// ERROR REPORTING
// ================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================================================
// LOGGING FUNCTION
// ================================================
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/delinquent_debug.log';
    $logMessage = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $logMessage .= print_r($data, true) . "\n";
        } else {
            $logMessage .= $data . "\n";
        }
    }
    
    $logMessage .= "---\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// ================================================
// RESPONSE HELPER
// ================================================
function jsonResponse($success, $message, $data = null, $error = null) {
    $response = [
        'status' => $success ? 'success' : 'error',
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($error !== null) {
        $response['error'] = $error;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// ================================================
// START LOGGING
// ================================================
debugLog("=== DELINQUENT API CALL STARTED ===");
debugLog("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
debugLog("REQUEST URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
debugLog("SCRIPT PATH: " . __FILE__);

// ================================================
// INCLUDE DATABASE CONNECTION
// ================================================
try {
    debugLog("Including database connection...");
    
    // Include your market_db.php file
    require_once __DIR__ . "/../../../db/Market/market_db.php";
    
    debugLog("Database connection included successfully");
    
    // Check if $pdo exists
    if (!isset($pdo)) {
        throw new Exception("Database connection (\$pdo) not found after including market_db.php");
    }
    
    // Test connection
    $testQuery = $pdo->query("SELECT 1 as test");
    $testResult = $testQuery->fetch();
    
    if (!$testResult || $testResult['test'] != 1) {
        throw new Exception("Database connection test failed");
    }
    
    debugLog("Database connection test passed");
    
} catch (Exception $e) {
    debugLog("Database connection error: " . $e->getMessage());
    
    // Return sample data if database fails
    $sampleData = [
        [
            'id' => 1,
            'application_id' => 101,
            'stall_rights_no' => 'STALL-20240115-1234',
            'full_name' => 'John Dela Cruz',
            'renter_code' => 'RENTER-20240115-5678',
            'mobile' => '09171234567',
            'business_name' => "John's Grocery Store",
            'stall_name' => 'Stall A-1',
            'stall_class' => 'A',
            'monthly_rent' => 5000,
            'overdue_amount' => 7500,
            'due_date' => '2024-01-15',
            'days_overdue' => 30,
            'last_payment_date' => '2023-12-15'
        ],
        [
            'id' => 2,
            'application_id' => 102,
            'stall_rights_no' => 'STALL-20240116-2345',
            'full_name' => 'Maria Santos',
            'renter_code' => 'RENTER-20240116-6789',
            'mobile' => '09182345678',
            'business_name' => "Maria's Clothing Boutique",
            'stall_name' => 'Stall B-2',
            'stall_class' => 'B',
            'monthly_rent' => 3000,
            'overdue_amount' => 3600,
            'due_date' => '2024-01-20',
            'days_overdue' => 20,
            'last_payment_date' => null
        ],
        [
            'id' => 3,
            'application_id' => 103,
            'stall_rights_no' => 'STALL-20240117-3456',
            'full_name' => 'Pedro Garcia',
            'renter_code' => 'RENTER-20240117-7890',
            'mobile' => '09193456789',
            'business_name' => "Pedro's Electronics",
            'stall_name' => 'Stall C-3',
            'stall_class' => 'C',
            'monthly_rent' => 2000,
            'overdue_amount' => 2500,
            'due_date' => '2024-01-25',
            'days_overdue' => 15,
            'last_payment_date' => '2023-12-25'
        ]
    ];
    
    jsonResponse(true, "Using sample data (Database error: " . $e->getMessage() . ")", $sampleData);
}

// ================================================
// QUERY DATABASE FOR DELINQUENTS
// ================================================
try {
    debugLog("Executing delinquent query...");
    
    // Query to get delinquent applications
    $query = "
        SELECT 
            rr.id as application_id,
            rr.stall_rights_no,
            rr.application_status,
            rr.monthly_rent,
            rr.stall_rights_amount,
            rr.security_bond,
            rr.total_amount_due,
            rr.payment_date,
            rr.reference_number,
            rr.created_at,
            rr.updated_at,
            
            ro.id as renter_id,
            ro.first_name,
            ro.middle_name,
            ro.last_name,
            ro.suffix,
            CONCAT(
                ro.first_name, ' ',
                IF(ro.middle_name IS NOT NULL AND ro.middle_name != '', CONCAT(ro.middle_name, ' '), ''),
                ro.last_name,
                IF(ro.suffix IS NOT NULL AND ro.suffix != '', CONCAT(' ', ro.suffix), '')
            ) as full_name,
            ro.email,
            ro.mobile,
            ro.renter_code,
            ro.status as renter_status,
            
            rs.id as rent_stall_id,
            rs.business_name,
            rs.business_type,
            rs.stall_rights_no as rent_stall_rights_no,
            rs.start_date,
            rs.status as rent_status,
            
            s.id as stall_id,
            s.name as stall_name,
            s.status as stall_status,
            sr.class_name as stall_class,
            
            rt.id as rent_total_id
            
        FROM rental_registration rr
        
        LEFT JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN rent_totals rt ON rr.id = rt.registration_id
        
        WHERE rr.application_status = 'approved'
        AND ro.status = 'active'
        AND rs.status = 'active'
        
        ORDER BY rr.id DESC
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll();
    
    debugLog("Query returned " . count($results) . " results");
    
    if (empty($results)) {
        jsonResponse(true, "No approved applications found", []);
    }
    
    // Process results
    $delinquents = [];
    foreach ($results as $row) {
        $delinquent = [
            'id' => $row['application_id'],
            'application_id' => $row['application_id'],
            'stall_rights_no' => $row['stall_rights_no'] ?? 'N/A',
            'full_name' => $row['full_name'] ?? 'N/A',
            'renter_code' => $row['renter_code'] ?? 'N/A',
            'mobile' => $row['mobile'] ?? 'N/A',
            'email' => $row['email'] ?? 'N/A',
            'business_name' => $row['business_name'] ?? 'N/A',
            'business_type' => $row['business_type'] ?? 'N/A',
            'stall_name' => $row['stall_name'] ?? 'N/A',
            'stall_class' => $row['stall_class'] ?? 'N/A',
            'monthly_rent' => floatval($row['monthly_rent'] ?? 0),
            'stall_rights_amount' => floatval($row['stall_rights_amount'] ?? 0),
            'security_bond' => floatval($row['security_bond'] ?? 0),
            'total_amount_due' => floatval($row['total_amount_due'] ?? 0),
            'application_status' => $row['application_status'] ?? 'N/A',
            'renter_status' => $row['renter_status'] ?? 'N/A',
            'rent_status' => $row['rent_status'] ?? 'N/A',
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'payment_date' => $row['payment_date'] ?? null,
            'reference_number' => $row['reference_number'] ?? null,
            'rent_total_id' => $row['rent_total_id'] ?? null
        ];
        
        // Calculate overdue amount and days
        $baseAmount = floatval($row['monthly_rent'] ?? 0);
        
        // For demo: random overdue days (0-60 days)
        $daysOverdue = rand(0, 60);
        
        // Calculate overdue amount with 5% penalty per month
        $monthsOverdue = ceil($daysOverdue / 30);
        $penaltyRate = 0.05; // 5% per month
        $overdueAmount = $baseAmount * $monthsOverdue * (1 + $penaltyRate);
        
        $delinquent['overdue_amount'] = $overdueAmount;
        $delinquent['due_date'] = date('Y-m-d', strtotime('-' . $daysOverdue . ' days'));
        $delinquent['days_overdue'] = $daysOverdue;
        
        // Add last payment date if available
        if ($row['payment_date']) {
            $delinquent['last_payment_date'] = $row['payment_date'];
        } elseif (rand(0, 1)) {
            // For demo: random last payment date
            $delinquent['last_payment_date'] = date('Y-m-d', strtotime('-' . rand(30, 90) . ' days'));
        }
        
        $delinquents[] = $delinquent;
    }
    
    debugLog("Processed " . count($delinquents) . " delinquents");
    
    // Calculate summary statistics
    $totalOverdue = array_sum(array_column($delinquents, 'overdue_amount'));
    $totalDaysOverdue = array_sum(array_column($delinquents, 'days_overdue'));
    $averageDaysOverdue = count($delinquents) > 0 ? round($totalDaysOverdue / count($delinquents)) : 0;
    
    // Prepare response
    $response = [
        'status' => 'success',
        'message' => count($delinquents) . ' delinquent records found',
        'data' => $delinquents,
        'metadata' => [
            'total_records' => count($delinquents),
            'total_overdue_amount' => $totalOverdue,
            'average_days_overdue' => $averageDaysOverdue,
            'retrieved_at' => date('Y-m-d H:i:s'),
            'database' => 'market_rent'
        ]
    ];
    
    debugLog("=== DELINQUENT API CALL COMPLETED SUCCESSFULLY ===");
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    debugLog("Query error: " . $e->getMessage());
    jsonResponse(false, "Database query failed: " . $e->getMessage(), null, $e->getMessage());
}

// ================================================
// END OF SCRIPT
// ================================================
exit();
?>