<?php
// ================================================
// GET APPROVED PROPERTIES API
// ================================================

// Enable CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Try to include DB connection with proper error handling
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Database config file not found at: " . $dbPath]);
    exit();
}

require_once $dbPath;

// Get PDO connection
$pdo = getDatabaseConnection();
if (!$pdo || (is_array($pdo) && isset($pdo['error']))) {
    http_response_code(500);
    $errorMsg = is_array($pdo) ? $pdo['message'] : "Failed to connect to database";
    echo json_encode(["error" => "Database connection failed: " . $errorMsg]);
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
        echo json_encode(["error" => "Method not allowed"]);
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
            "count" => count($properties)
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}
?>