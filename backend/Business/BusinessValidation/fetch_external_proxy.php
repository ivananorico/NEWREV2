<?php
/**
 * CORS Proxy for External Permit System API with Duplicate Filtering
 */

// Enable error reporting for debugging (but don't display to users)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1); // Log errors instead

// Start output buffering to prevent accidental output
ob_start();

// Handle preflight request first
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    } else {
        header("Access-Control-Allow-Origin: *");
    }
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma, Accept, Origin");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    ob_end_flush();
    exit();
}

// Set CORS headers for actual request
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma, Accept, Origin");
header("Content-Type: application/json; charset=UTF-8");

// Database configuration - ADJUST THESE VALUES
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username_here'); // CHANGE THIS
define('DB_PASS', 'your_password_here'); // CHANGE THIS
define('DB_NAME', 'reve_business');

function getAlreadyImportedBusinessIds() {
    $imported_ids = [];
    
    try {
        // Create connection
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Database connection error: " . $conn->connect_error);
            return $imported_ids; // Return empty array on connection error
        }
        
        $query = "SELECT applicant_id FROM business_permits";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $imported_ids[] = $row['applicant_id'];
            }
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
    }
    
    return $imported_ids;
}

function fetchExternalData() {
    $external_api_url = "https://e-plms.goserveph.com/backend/business_permit/admin_fetch.php";
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $external_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    
    $headers = [
        'Accept: application/json',
        'User-Agent: RevenueTreasurySystem/1.0'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error: " . $error_msg);
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code != 200) {
        throw new Exception("External API returned HTTP code: " . $http_code);
    }
    
    return $response;
}

try {
    // Clear any previous output
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Get already imported IDs
    $alreadyImported = getAlreadyImportedBusinessIds();
    
    // Fetch external data
    $external_response = fetchExternalData();
    
    // Decode the response
    $external_data = json_decode($external_response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON from external API: " . json_last_error_msg());
    }
    
    // Filter out already imported businesses
    $filtered_data = [];
    $total_external = 0;
    $already_imported_count = 0;
    $new_records_count = 0;
    
    if (isset($external_data['data']) && is_array($external_data['data'])) {
        $total_external = count($external_data['data']);
        
        foreach ($external_data['data'] as $business) {
            $business_id = $business['applicant_id'] ?? $business['id'] ?? null;
            
            if ($business_id && in_array($business_id, $alreadyImported)) {
                $already_imported_count++;
            } else {
                $new_records_count++;
                $business['already_imported'] = false;
                $filtered_data[] = $business;
            }
        }
    }
    
    // Return success response
    $response = [
        'success' => true,
        'message' => 'Data fetched successfully',
        'data' => $filtered_data,
        'counts' => [
            'total_external' => $total_external,
            'already_imported' => $already_imported_count,
            'new_records' => $new_records_count,
            'filtered_total' => count($filtered_data)
        ],
        'meta' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'imported_ids_count' => count($alreadyImported)
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Clear any partial output
    if (ob_get_length()) {
        ob_clean();
    }
    
    http_response_code(500);
    
    $error_response = [
        'success' => false,
        'message' => 'Failed to fetch data: ' . $e->getMessage(),
        'data' => [],
        'counts' => [
            'total_external' => 0,
            'already_imported' => 0,
            'new_records' => 0,
            'filtered_total' => 0
        ]
    ];
    
    echo json_encode($error_response);
    error_log("Proxy Error: " . $e->getMessage());
}

// End output buffering and send
if (ob_get_length()) {
    ob_end_flush();
}
?>