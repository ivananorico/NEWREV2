<?php
// ================================================
// GET PROPERTY DETAILS API
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
        getPropertyDetails($pdo);
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

function getPropertyDetails($pdo) {
    // Validate required parameters
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Property ID is required"]);
        return;
    }

    $property_id = $_GET['id'];

    try {
        // Get basic property information
        $query = "
            SELECT 
                pr.*,
                po.*,
                lp.*,
                lp.annual_tax as land_annual_tax,
                pt.total_annual_tax,
                pt.approval_date,
                lp.tdn as land_tdn,
                lc.classification as land_classification
            FROM property_registrations pr
            LEFT JOIN property_owners po ON pr.owner_id = po.id
            LEFT JOIN land_properties lp ON pr.id = lp.registration_id
            LEFT JOIN land_configurations lc ON lp.land_config_id = lc.id
            LEFT JOIN property_totals pt ON pr.id = pt.registration_id
            WHERE pr.id = ? AND pr.status = 'approved'
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$property_id]);
        
        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["error" => "Property not found or not approved"]);
            return;
        }
        
        $property = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure all fields have values
        $property = array_map(function($value) {
            return $value === null ? '' : $value;
        }, $property);
        
        // Get building details
        $land_id = $property['id'];
        $buildingQuery = "
            SELECT 
                bp.*,
                pc.classification as construction_type,
                pc.material_type
            FROM building_properties bp
            LEFT JOIN property_configurations pc ON bp.property_config_id = pc.id
            WHERE bp.land_id = ? AND bp.status = 'active'
        ";
        
        $buildingStmt = $pdo->prepare($buildingQuery);
        $buildingStmt->execute([$land_id]);
        $buildings = $buildingStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure building fields have values
        $buildings = array_map(function($building) {
            return array_map(function($value) {
                return $value === null ? '' : $value;
            }, $building);
        }, $buildings);
        
        // Get quarterly tax details
        $taxQuery = "
            SELECT 
                qt.*
            FROM quarterly_taxes qt
            JOIN property_totals pt ON qt.property_total_id = pt.id
            WHERE pt.registration_id = ?
            ORDER BY qt.year DESC, qt.quarter
        ";
        
        $taxStmt = $pdo->prepare($taxQuery);
        $taxStmt->execute([$property_id]);
        $quarterly_taxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure tax fields have values
        $quarterly_taxes = array_map(function($tax) {
            return array_map(function($value) {
                return $value === null ? '' : $value;
            }, $tax);
        }, $quarterly_taxes);
        
        echo json_encode([
            "success" => true,
            "data" => [
                "property" => $property,
                "buildings" => $buildings,
                "quarterly_taxes" => $quarterly_taxes
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}
?>