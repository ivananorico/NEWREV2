<?php
// ================================================
// GET APPROVED PROPERTIES API - SIMPLE VERSION
// ================================================

// Set CORS headers - Allow both localhost and domain
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
$basePath = dirname(__DIR__, 3); // Adjust based on your folder structure
$dbPath = $basePath . '/db/RPT/rpt_db.php';

if (!file_exists($dbPath)) {
    echo json_encode([
        "success" => false,
        "error" => "Database config not found"
    ]);
    exit();
}

require_once $dbPath;

$pdo = getDatabaseConnection();
if (!$pdo) {
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed"
    ]);
    exit();
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    getApprovedProperties($pdo);
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
}

function getApprovedProperties($pdo) {
    try {
        $query = "
            SELECT 
                pr.id,
                pr.reference_number,
                pr.lot_location,
                pr.barangay,
                pr.district,
                pr.status,
                pr.created_at,
                pr.updated_at,
                pr.has_building,
                po.first_name,
                po.last_name,
                CONCAT(po.first_name, ' ', po.last_name) as owner_name,
                po.email,
                po.phone,
                lp.land_area_sqm,
                lp.land_market_value,
                lp.land_assessed_value,
                lp.property_type,  -- CHANGED: Remove alias, use original name
                lp.tdn as land_tdn,
                lc.classification as land_classification,
                lc.description as land_description,
                pt.total_annual_tax,
                (SELECT COUNT(*) FROM building_properties bp 
                 WHERE bp.land_id = lp.id AND bp.status = 'active') as building_count
            FROM property_registrations pr
            LEFT JOIN property_owners po ON pr.owner_id = po.id
            LEFT JOIN land_properties lp ON pr.id = lp.registration_id
            LEFT JOIN land_configurations lc ON lp.land_config_id = lc.id
            LEFT JOIN property_totals pt ON pr.id = pt.registration_id
            WHERE pr.status = 'approved'
            ORDER BY pr.created_at DESC
        ";
        
        $stmt = $pdo->query($query);
        $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "success" => true,
            "status" => "success",
            "data" => $properties,
            "count" => count($properties)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}
?>