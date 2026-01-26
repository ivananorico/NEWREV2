<?php
/**
 * CORS Proxy for External Permit System API
 * This bypasses CORS restrictions by fetching from the server-side
 * AND filters out businesses already imported to our database
 */

// Handle preflight request first
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    } else {
        header("Access-Control-Allow-Origin: http://localhost:5173");
    }
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma, Accept, Origin");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit();
}

// Set CORS headers for actual request
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
} else {
    header("Access-Control-Allow-Origin: http://localhost:5173");
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma, Accept, Origin");
header("Content-Type: application/json");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require_once 'config/database.php'; // Create this file or adjust path

// External API URL
$external_api_url = "https://e-plms.goserveph.com/backend/business_permit/admin_fetch.php";

function getAlreadyImportedBusinessIds($conn) {
    $imported_ids = [];
    
    try {
        $query = "SELECT applicant_id FROM business_permits";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $imported_ids[] = $row['applicant_id'];
            }
        }
    } catch (Exception $e) {
        error_log("Database error fetching imported IDs: " . $e->getMessage());
    }
    
    return $imported_ids;
}

try {
    // First, get already imported business IDs from our database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $alreadyImported = getAlreadyImportedBusinessIds($conn);
    $conn->close();
    
    error_log("Already imported business count: " . count($alreadyImported));
    
    // Fetch data from external API
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $external_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $headers = [
        'Accept: application/json',
        'User-Agent: RevenueTreasurySystem/1.0'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        throw new Exception("cURL Error: " . $error);
    }
    
    if ($http_code != 200) {
        $error_data = json_decode($response, true);
        $error_msg = is_array($error_data) && isset($error_data['message']) 
            ? $error_data['message'] 
            : "External API returned HTTP code: " . $http_code;
        
        throw new Exception($error_msg);
    }
    
    // Check if response is valid JSON
    $external_data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from external API: " . json_last_error_msg());
    }
    
    // Filter out already imported businesses
    $filtered_data = [];
    $total_external = 0;
    $already_imported_count = 0;
    $new_records_count = 0;
    
    if (isset($external_data['data']) && is_array($external_data['data'])) {
        $total_external = count($external_data['data']);
        
        foreach ($external_data['data'] as $business) {
            // Check if business is already imported
            $business_id = $business['applicant_id'] ?? $business['id'] ?? null;
            
            if ($business_id && in_array($business_id, $alreadyImported)) {
                $already_imported_count++;
                // Optionally, you can mark this as already imported
                $business['already_imported'] = true;
            } else {
                $new_records_count++;
                $business['already_imported'] = false;
                $filtered_data[] = $business;
            }
        }
    }
    
    // Return the filtered response
    echo json_encode([
        'success' => true,
        'message' => 'Data fetched successfully',
        'data' => $filtered_data,
        'counts' => [
            'total_external' => $total_external,
            'already_imported' => $already_imported_count,
            'new_records' => $new_records_count,
            'filtered_total' => count($filtered_data)
        ],
        'filter_info' => [
            'filter_applied' => true,
            'imported_ids_count' => count($alreadyImported)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Proxy Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch from external system: ' . $e->getMessage(),
        'data' => [],
        'counts' => [
            'total_external' => 0,
            'already_imported' => 0,
            'new_records' => 0,
            'filtered_total' => 0
        ]
    ]);
}
?>