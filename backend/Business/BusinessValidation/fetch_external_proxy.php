<?php
/**
 * CORS Proxy for External Permit System API
 * This bypasses CORS restrictions by fetching from the server-side
 */

// Handle preflight request first
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Set CORS headers for preflight
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    } else {
        header("Access-Control-Allow-Origin: http://localhost:5173");
    }
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma, Accept, Origin");
    header("Access-Control-Max-Age: 86400"); // 24 hours
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

// Set content type
header("Content-Type: application/json");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// External API URL
$external_api_url = "https://e-plms.goserveph.com/backend/business_permit/admin_fetch.php";

try {
    // Fetch data from external API
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $external_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only - remove in production
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // For testing only - remove in production
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Set headers
    $headers = [
        'Accept: application/json',
        'User-Agent: RevenueTreasurySystem/1.0'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    // Log for debugging
    error_log("External API Response Code: " . $http_code);
    error_log("External API Error: " . $error);
    
    if ($error) {
        throw new Exception("cURL Error: " . $error);
    }
    
    if ($http_code != 200) {
        // Try to parse error response
        $error_data = json_decode($response, true);
        $error_msg = is_array($error_data) && isset($error_data['message']) 
            ? $error_data['message'] 
            : "External API returned HTTP code: " . $http_code;
        
        throw new Exception($error_msg);
    }
    
    // Check if response is valid JSON
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from external API: " . json_last_error_msg());
    }
    
    // Return the external API response
    echo json_encode([
        'success' => true,
        'message' => 'Data fetched successfully',
        'data' => $data['data'] ?? [],
        'counts' => $data['counts'] ?? ['total' => 0],
        'original_response' => $data // Include full response for debugging
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Proxy Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch from external system: ' . $e->getMessage(),
        'data' => [],
        'counts' => [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'compliance' => 0
        ]
    ]);
}
?>