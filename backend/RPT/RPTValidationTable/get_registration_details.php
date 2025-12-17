<?php
// revenue/backend/RPT/RPTValidationTable/get_registration_details.php

// Allow CORS for all origins in development, specific in production
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Define allowed origins
$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost',
    'https://revenuetreasury.goserveph.com',
    'https://www.revenuetreasury.goserveph.com'
];

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // For development, allow all
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        header("Access-Control-Allow-Origin: *");
    }
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for development
if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

try {
    // Get and validate ID
    $registrationId = isset($_GET['id']) ? trim($_GET['id']) : null;
    
    if (!$registrationId) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Registration ID is required'
        ]);
        exit;
    }

    // Validate ID is numeric
    if (!is_numeric($registrationId)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid registration ID format'
        ]);
        exit;
    }

    // Include database connection
    $dbPath = __DIR__ . "/../../../db/RPT/rpt_db.php";
    
    if (!file_exists($dbPath)) {
        throw new Exception("Database configuration file not found at: " . $dbPath);
    }
    
    require_once $dbPath;

    // Check if connection is established
    if (!isset($pdo)) {
        throw new Exception("Database connection failed");
    }

    // Log the request (for debugging)
    error_log("GET registration_details.php - ID: " . $registrationId . " - IP: " . $_SERVER['REMOTE_ADDR']);

    // Fetch registration with ALL required fields
    $query = "
        SELECT 
            pr.id,
            pr.reference_number,
            pr.lot_location as location_address,
            pr.barangay,
            pr.district as municipality_city,
            pr.province,
            'Residential' as property_type,
            pr.has_building,
            pr.status,
            COALESCE(pr.correction_notes, '') as remarks,
            pr.created_at as date_registered,
            COALESCE(pr.updated_at, pr.created_at) as last_updated,
            po.full_name as owner_name,
            po.email as email_address,
            po.phone as contact_number,
            COALESCE(po.tin_number, '') as tin,
            COALESCE(po.address, '') as owner_address
        FROM property_registrations pr
        LEFT JOIN property_owners po ON pr.owner_id = po.id
        WHERE pr.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$registrationId]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Registration not found for ID: " . $registrationId
        ]);
        exit;
    }

    // Ensure all fields have values
    $registration = array_map(function($value) {
        return $value === null ? '' : $value;
    }, $registration);

    // Log success
    error_log("SUCCESS: Registration details fetched for ID: " . $registrationId);

    echo json_encode([
        'status' => 'success',
        'registration' => $registration,
        'server_time' => date('Y-m-d H:i:s'),
        'environment' => $_SERVER['HTTP_HOST']
    ]);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred',
        'debug' => ($_SERVER['HTTP_HOST'] === 'localhost') ? $e->getMessage() : null
    ]);
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>