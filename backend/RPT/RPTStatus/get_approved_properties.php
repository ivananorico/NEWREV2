<?php
// ================================================
// GET APPROVED PROPERTIES API - UPDATED CORS FOR BOTH LOCALHOST AND PRODUCTION
// ================================================

// Get the origin from the request
$http_origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// List of allowed origins (add your domains here)
$allowed_origins = [
    'http://localhost:5173',          // React dev server
    'http://localhost:3000',          // Alternative React dev
    'http://127.0.0.1:5173',          // Localhost IP
    'http://127.0.0.1:3000',          // Localhost IP alternative
    'https://revenuetreasury.goserveph.com',  // Your production domain
    'https://www.revenuetreasury.goserveph.com',
    'https://localhost',               // HTTPS localhost (if using SSL)
    'https://127.0.0.1'                // HTTPS localhost IP
];

// Debug logging for development
if (isset($_GET['debug'])) {
    error_log("CORS Debug: Origin = " . $http_origin);
    error_log("CORS Debug: Allowed origins = " . implode(", ", $allowed_origins));
}

// Set CORS headers - Allow all for development, specific for production
if (in_array($http_origin, $allowed_origins)) {
    // Specific allowed origin
    header("Access-Control-Allow-Origin: " . $http_origin);
    header("Access-Control-Allow-Credentials: true");
} elseif (empty($http_origin)) {
    // No origin header (server-side request or direct browser access)
    // For development, allow all
    if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
        strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false) {
        header("Access-Control-Allow-Origin: *");
    }
} else {
    // Origin not in allowed list - for security, you might want to deny
    // For now, allow with wildcard but this will work with credentials: 'omit'
    header("Access-Control-Allow-Origin: *");
}

// Common CORS headers
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin");
header("Access-Control-Max-Age: 86400"); // 24 hours cache for preflight
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Try to include DB connection with proper error handling
$basePath = dirname(__DIR__, 3); // Go up 3 levels from current directory
$dbPath = $basePath . '/db/RPT/rpt_db.php';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database config file not found",
        "path" => $dbPath,
        "basePath" => $basePath
    ]);
    exit();
}

require_once $dbPath;

// Get PDO connection
$pdo = getDatabaseConnection();
if (!$pdo || (is_array($pdo) && isset($pdo['error']))) {
    http_response_code(500);
    $errorMsg = is_array($pdo) ? $pdo['message'] : "Failed to connect to database";
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed",
        "message" => $errorMsg
    ]);
    exit();
}

// Determine HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Route based on method
switch ($method) {
    case 'GET':
        getApprovedProperties($pdo);
        break;
    case 'OPTIONS':
        http_response_code(200);
        exit();
    default:
        http_response_code(405);
        echo json_encode([
            "success" => false,
            "error" => "Method not allowed",
            "allowed_methods" => ["GET", "OPTIONS"]
        ]);
        break;
}

// ==========================
// FUNCTIONS
// ==========================

function getApprovedProperties($pdo) {
    try {
        // Query to get approved properties
        $query = "
            SELECT 
                pr.id,
                pr.reference_number,
                pr.lot_location,
                pr.barangay,
                pr.district,
                pr.has_building,
                pr.status,
                pr.created_at,
                po.full_name as owner_name,
                po.email,
                po.phone,
                po.address as owner_address,
                lp.land_area_sqm,
                lp.land_market_value,
                lp.land_assessed_value,
                lp.annual_tax as land_annual_tax,
                pt.total_annual_tax,
                pt.approval_date,
                lc.classification as land_classification,
                (
                    SELECT COUNT(*) 
                    FROM building_properties bp 
                    WHERE bp.land_id = lp.id 
                    AND bp.status = 'active'
                ) as building_count
            FROM property_registrations pr
            LEFT JOIN property_owners po ON pr.owner_id = po.id
            LEFT JOIN land_properties lp ON pr.id = lp.registration_id
            LEFT JOIN property_totals pt ON pr.id = pt.registration_id
            LEFT JOIN land_configurations lc ON lp.land_config_id = lc.id
            WHERE pr.status = 'approved'
            GROUP BY pr.id
            ORDER BY COALESCE(pt.approval_date, pr.created_at) DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure all fields have values
        $properties = array_map(function($property) {
            return array_map(function($value) {
                return $value === null ? '' : $value;
            }, $property);
        }, $properties);
        
        echo json_encode([
            "success" => true,
            "data" => $properties,
            "count" => count($properties),
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Database error",
            "message" => $e->getMessage(),
            "trace" => (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) ? $e->getTraceAsString() : null
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Server error",
            "message" => $e->getMessage()
        ]);
    }
}
?>