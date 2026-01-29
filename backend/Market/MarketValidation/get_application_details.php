<?php
// revenue2/backend/Market/MarketValidation/get_application_details.php

// Start output buffering
ob_start();

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple debug log - always log
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/debug.log';
    $logMessage = date('Y-m-d H:i:s') . " - $message";
    if ($data !== null) {
        $logMessage .= " - " . (is_array($data) ? json_encode($data) : $data);
    }
    file_put_contents($logFile, $logMessage . "\n", FILE_APPEND);
}

debugLog("=== REQUEST START ===");
debugLog("Query string", $_SERVER['QUERY_STRING'] ?? 'none');
debugLog("GET params", $_GET);
debugLog("Script path", __FILE__);

// Validate ID parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Application ID is required',
        'code' => 'MISSING_ID'
    ]);
    exit;
}

$application_id = intval($_GET['id']);
if ($application_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid application ID',
        'code' => 'INVALID_ID',
        'received_id' => $_GET['id']
    ]);
    exit;
}

debugLog("Processing ID", $application_id);

try {
    // First, try to include the database connection file
    $dbPath = realpath(__DIR__ . '/../../../db/Market/market_db.php');
    
    if (!$dbPath) {
        // Try alternative path
        $dbPath = realpath(__DIR__ . '/../../../../db/Market/market_db.php');
    }
    
    if (!$dbPath) {
        // Try one more alternative
        $dbPath = realpath($_SERVER['DOCUMENT_ROOT'] . '/db/Market/market_db.php');
    }
    
    debugLog("Looking for DB file at:", __DIR__ . '/../../../db/Market/market_db.php');
    debugLog("Found DB path:", $dbPath);
    
    if (!$dbPath || !file_exists($dbPath)) {
        throw new Exception("Database connection file not found. Tried: " . 
                           __DIR__ . '/../../../db/Market/market_db.php');
    }
    
    require_once $dbPath;
    debugLog("Database file included successfully");
    
    // Check what connection variable is available
    $db = null;
    if (isset($pdo) && $pdo instanceof PDO) {
        $db = $pdo;
        debugLog("Using \$pdo connection");
    } elseif (isset($conn) && $conn instanceof PDO) {
        $db = $conn;
        debugLog("Using \$conn connection");
    } else {
        // Try to create a direct connection as fallback
        debugLog("No connection found in market_db.php, creating direct connection");
        $host = 'localhost';
        $dbname = 'market_rent';
        $username = 'root';
        $password = '';
        
        $db = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        debugLog("Direct database connection created");
    }
    
    // Test the connection
    $testResult = $db->query("SELECT 1 as test")->fetch();
    debugLog("Database connection test passed", $testResult);
    
    // First, try a simple query to check if the main table exists
    $simpleQuery = "SELECT id, stall_rights_no, application_status, created_at FROM rental_registration WHERE id = :id LIMIT 1";
    debugLog("Executing simple query", $simpleQuery);
    
    $stmt = $db->prepare($simpleQuery);
    $stmt->bindValue(':id', $application_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $application = $stmt->fetch();
    
    if (!$application) {
        debugLog("No application found in rental_registration", $application_id);
        
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Application not found',
            'code' => 'NOT_FOUND',
            'requested_id' => $application_id
        ]);
        exit;
    }
    
    debugLog("Basic application found", $application);
    
    // Now try to get additional information with joins
    try {
        $fullQuery = "
            SELECT 
                rr.*,
                ro.first_name,
                ro.middle_name,
                ro.last_name,
                ro.suffix,
                ro.email,
                ro.mobile,
                ro.telephone,
                ro.house_number,
                ro.street,
                ro.barangay,
                ro.city,
                ro.province,
                ro.zip_code,
                ro.birth_date,
                ro.gender,
                ro.emergency_name,
                ro.emergency_contact,
                ro.renter_code,
                ro.status AS renter_status,
                rs.business_name,
                rs.business_type,
                rs.start_date,
                rs.end_date,
                rs.status AS business_status,
                s.name AS stall_location_name,
                s.class_id,
                s.status AS stall_status,
                s.price AS stall_price,
                sr.class_name,
                sr.description AS stall_class_description
            FROM rental_registration rr
            LEFT JOIN renter_owner ro ON rr.id = ro.registration_id
            LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
            LEFT JOIN stalls s ON rr.stall_id = s.id
            LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
            WHERE rr.id = :id
            LIMIT 1
        ";
        
        $fullStmt = $db->prepare($fullQuery);
        $fullStmt->bindValue(':id', $application_id, PDO::PARAM_INT);
        $fullStmt->execute();
        
        $fullApplication = $fullStmt->fetch();
        
        if ($fullApplication) {
            $application = array_merge($application, $fullApplication);
            debugLog("Full application data retrieved");
        }
    } catch (Exception $e) {
        debugLog("Could not get full application data, using basic data", $e->getMessage());
        // Continue with basic data
    }
    
    // Calculate derived fields
    $application['full_name'] = trim(
        ($application['first_name'] ?? '') . ' ' .
        (!empty($application['middle_name']) ? $application['middle_name'] . ' ' : '') .
        ($application['last_name'] ?? '') .
        (!empty($application['suffix']) ? ' ' . $application['suffix'] : '')
    );
    
    // Build address
    $addressParts = [];
    if (!empty($application['house_number'])) $addressParts[] = $application['house_number'];
    if (!empty($application['street'])) $addressParts[] = $application['street'];
    if (!empty($application['barangay'])) $addressParts[] = 'Brgy. ' . $application['barangay'];
    if (!empty($application['city'])) $addressParts[] = $application['city'];
    if (!empty($application['province'])) $addressParts[] = $application['province'];
    if (!empty($application['zip_code'])) $addressParts[] = $application['zip_code'];
    
    $application['full_address'] = !empty($addressParts) ? implode(', ', $addressParts) : 'N/A';
    
    // Status mapping for display
    $statusMap = [
        'pending' => 'Pending Interview',
        'interview_scheduled' => 'Interview Scheduled',
        'interviewed' => 'Interview Completed',
        'paying' => 'Payment Required',
        'paid' => 'Payment Completed',
        'need_correction' => 'Needs Correction',
        'resubmitted' => 'Resubmitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected'
    ];
    
    $application['status_display'] = $statusMap[$application['application_status']] ?? 
                                   ucfirst(str_replace('_', ' ', $application['application_status']));
    
    // Format dates
    $application['created_at_formatted'] = $application['created_at'] ? 
        date('F j, Y g:i A', strtotime($application['created_at'])) : 'N/A';
    
    $application['updated_at_formatted'] = $application['updated_at'] ? 
        date('F j, Y g:i A', strtotime($application['updated_at'])) : 'N/A';
    
    $application['interview_date_formatted'] = $application['interview_date'] ? 
        date('F j, Y g:i A', strtotime($application['interview_date'])) : null;
    
    $application['payment_date_formatted'] = $application['payment_date'] ? 
        date('F j, Y g:i A', strtotime($application['payment_date'])) : null;
    
    $application['birth_date_formatted'] = $application['birth_date'] ? 
        date('F j, Y', strtotime($application['birth_date'])) : 'N/A';
    
    // Format currency if amounts exist
    if (isset($application['monthly_rent'])) {
        $application['monthly_rent_formatted'] = '₱' . number_format($application['monthly_rent'], 2);
    }
    
    if (isset($application['stall_rights_amount'])) {
        $application['stall_rights_amount_formatted'] = '₱' . number_format($application['stall_rights_amount'], 2);
    }
    
    if (isset($application['security_bond'])) {
        $application['security_bond_formatted'] = '₱' . number_format($application['security_bond'], 2);
    }
    
    if (isset($application['total_amount_due'])) {
        $application['total_amount_due_formatted'] = '₱' . number_format($application['total_amount_due'], 2);
    }
    
    // Calculate total amount due if not provided
    if (!isset($application['total_amount_due']) && 
        (isset($application['stall_rights_amount']) || isset($application['security_bond']))) {
        $application['total_amount_due'] = 
            ($application['stall_rights_amount'] ?? 0) + 
            ($application['security_bond'] ?? 0);
        $application['total_amount_due_formatted'] = '₱' . number_format($application['total_amount_due'], 2);
    }
    
    // Prepare response
    $response = [
        'status' => 'success',
        'message' => 'Application retrieved successfully',
        'data' => $application,
        'metadata' => [
            'retrieved_at' => date('Y-m-d H:i:s'),
            'id' => $application_id,
            'database' => 'Connected',
            'tables_found' => true
        ]
    ];
    
    debugLog("Success response prepared");
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch(PDOException $e) {
    debugLog("Database PDO error", $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error',
        'code' => 'DB_ERROR',
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode()
        ]
    ]);
} catch(Exception $e) {
    debugLog("General error", $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred',
        'code' => 'GENERAL_ERROR',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

debugLog("=== REQUEST END ===\n");

// Clean output buffer
while (ob_get_level() > 0) {
    ob_end_flush();
}
?>