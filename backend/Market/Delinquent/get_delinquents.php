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
    jsonResponse(false, "Database connection failed: " . $e->getMessage());
}

// ================================================
// QUERY DATABASE FOR DELINQUENTS
// ================================================
try {
    debugLog("Executing delinquent query...");
    
    // Get current date
    $currentDate = date('Y-m-d');
    $currentYear = date('Y');
    $currentMonth = date('m');
    
    // Query to get delinquent accounts from monthly_rent_billing
    // Accounts are delinquent if they have pending bills with due_date in the past
    $query = "
        SELECT 
            mrb.id as billing_id,
            mrb.rent_total_id,
            mrb.billing_month,
            mrb.billing_year,
            mrb.due_date,
            mrb.base_rent,
            mrb.penalty_amount,
            mrb.total_amount_due,
            mrb.payment_status,
            mrb.payment_date,
            mrb.receipt_number,
            
            rt.id as rent_total_id,
            rt.rent_stall_id,
            rt.renter_id,
            rt.registration_id,
            rt.monthly_rent,
            rt.start_date,
            
            rs.id as rent_stall_id,
            rs.registration_id as rent_stall_registration_id,
            rs.renter_id as rent_stall_renter_id,
            rs.stall_id,
            rs.business_name,
            rs.business_type,
            rs.stall_rights_no,
            rs.status as rent_stall_status,
            
            ro.id as renter_owner_id,
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
            
            rr.id as application_id,
            rr.stall_rights_no as app_stall_rights_no,
            rr.application_status,
            rr.stall_name as app_stall_name,
            rr.stall_class,
            
            s.id as stall_id,
            s.name as stall_name,
            s.map_id,
            m.name as map_name,
            
            sr.class_name as stall_class_name
            
        FROM monthly_rent_billing mrb
        INNER JOIN rent_totals rt ON mrb.rent_total_id = rt.id
        INNER JOIN rent_stall rs ON rt.rent_stall_id = rs.id
        INNER JOIN renter_owner ro ON rt.renter_id = ro.id
        INNER JOIN rental_registration rr ON rt.registration_id = rr.id
        INNER JOIN stalls s ON rs.stall_id = s.id
        LEFT JOIN maps m ON s.map_id = m.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        
        WHERE mrb.payment_status IN ('pending', 'overdue')
        AND mrb.due_date < :current_date
        AND rs.status = 'active'
        AND ro.status = 'active'
        AND rr.application_status = 'approved'
        
        ORDER BY mrb.due_date ASC, ro.last_name ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['current_date' => $currentDate]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    debugLog("Query returned " . count($results) . " delinquent records");
    
    if (empty($results)) {
        jsonResponse(true, "No delinquent accounts found", []);
    }
    
    // Process results - Group by renter/stall and calculate totals
    $delinquents = [];
    $processedRenters = [];
    
    foreach ($results as $row) {
        $renterKey = $row['renter_id'] . '_' . $row['stall_rights_no'];
        
        // Calculate days overdue
        $dueDate = new DateTime($row['due_date']);
        $current = new DateTime();
        $daysOverdue = 0;
        
        if ($dueDate < $current) {
            $daysOverdue = $current->diff($dueDate)->days;
        }
        
        // If we haven't processed this renter/stall combination yet
        if (!isset($processedRenters[$renterKey])) {
            $delinquent = [
                'id' => $row['application_id'],
                'application_id' => $row['application_id'],
                'stall_rights_no' => $row['stall_rights_no'] ?? $row['app_stall_rights_no'] ?? 'N/A',
                'full_name' => $row['full_name'] ?? 'N/A',
                'renter_code' => $row['renter_code'] ?? 'N/A',
                'mobile' => $row['mobile'] ?? 'N/A',
                'email' => $row['email'] ?? 'N/A',
                'business_name' => $row['business_name'] ?? 'N/A',
                'business_type' => $row['business_type'] ?? 'N/A',
                'stall_name' => $row['stall_name'] ?? $row['app_stall_name'] ?? 'N/A',
                'stall_class' => $row['stall_class'] ?? $row['stall_class_name'] ?? 'N/A',
                'map_name' => $row['map_name'] ?? 'N/A',
                'monthly_rent' => floatval($row['monthly_rent'] ?? 0),
                'overdue_amount' => floatval($row['total_amount_due'] ?? 0),
                'due_date' => $row['due_date'],
                'days_overdue' => $daysOverdue,
                'payment_status' => $row['payment_status'],
                'billing_month' => $row['billing_month'],
                'billing_year' => $row['billing_year'],
                'billing_id' => $row['billing_id'],
                'base_rent' => floatval($row['base_rent'] ?? 0),
                'penalty_amount' => floatval($row['penalty_amount'] ?? 0),
                'last_payment_date' => null,
                'last_payment_amount' => 0,
                'application_status' => $row['application_status'],
                'renter_status' => $row['rent_stall_status'] ?? 'active',
                'rent_status' => $row['rent_stall_status'],
                'created_at' => null,
                'updated_at' => null,
                'payment_date' => null,
                'reference_number' => null,
                'rent_total_id' => $row['rent_total_id']
            ];
            
            $processedRenters[$renterKey] = $delinquent;
        } else {
            // Add additional overdue amounts to existing delinquent
            $processedRenters[$renterKey]['overdue_amount'] += floatval($row['total_amount_due'] ?? 0);
            
            // Update days overdue to the maximum
            if ($daysOverdue > $processedRenters[$renterKey]['days_overdue']) {
                $processedRenters[$renterKey]['days_overdue'] = $daysOverdue;
                $processedRenters[$renterKey]['due_date'] = $row['due_date'];
            }
        }
    }
    
    // Convert to indexed array
    $delinquents = array_values($processedRenters);
    
    // Get last payment information for each delinquent
    foreach ($delinquents as &$delinquent) {
        if ($delinquent['rent_total_id']) {
            $lastPaymentQuery = "
                SELECT payment_date, total_amount_due as amount, receipt_number
                FROM monthly_rent_billing
                WHERE rent_total_id = :rent_total_id
                AND payment_status = 'paid'
                AND payment_date IS NOT NULL
                ORDER BY payment_date DESC
                LIMIT 1
            ";
            
            $stmt = $pdo->prepare($lastPaymentQuery);
            $stmt->execute(['rent_total_id' => $delinquent['rent_total_id']]);
            $lastPayment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lastPayment) {
                $delinquent['last_payment_date'] = $lastPayment['payment_date'];
                $delinquent['last_payment_amount'] = floatval($lastPayment['amount'] ?? 0);
                $delinquent['last_receipt'] = $lastPayment['receipt_number'] ?? null;
            }
        }
    }
    
    debugLog("Processed " . count($delinquents) . " unique delinquent accounts");
    
    // Calculate summary statistics
    $totalOverdue = array_sum(array_column($delinquents, 'overdue_amount'));
    $totalDaysOverdue = array_sum(array_column($delinquents, 'days_overdue'));
    $averageDaysOverdue = count($delinquents) > 0 ? round($totalDaysOverdue / count($delinquents)) : 0;
    
    // Count by severity
    $severityCounts = [
        'current' => 0,
        'mild' => 0,
        'moderate' => 0,
        'severe' => 0
    ];
    
    foreach ($delinquents as $d) {
        $days = $d['days_overdue'] ?? 0;
        if ($days == 0) $severityCounts['current']++;
        elseif ($days <= 7) $severityCounts['mild']++;
        elseif ($days <= 30) $severityCounts['moderate']++;
        else $severityCounts['severe']++;
    }
    
    // Prepare response
    $response = [
        'status' => 'success',
        'message' => count($delinquents) . ' delinquent records found',
        'data' => $delinquents,
        'metadata' => [
            'total_records' => count($delinquents),
            'total_overdue_amount' => $totalOverdue,
            'average_days_overdue' => $averageDaysOverdue,
            'severity_counts' => $severityCounts,
            'current_date' => $currentDate,
            'retrieved_at' => date('Y-m-d H:i:s'),
            'database' => 'market_rent'
        ]
    ];
    
    debugLog("=== DELINQUENT API CALL COMPLETED SUCCESSFULLY ===");
    debugLog("Total overdue amount: ₱" . number_format($totalOverdue, 2));
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    debugLog("Query error: " . $e->getMessage());
    debugLog("Stack trace: " . $e->getTraceAsString());
    jsonResponse(false, "Database query failed: " . $e->getMessage(), null, $e->getMessage());
}

// ================================================
// END OF SCRIPT
// ================================================
exit();
?>