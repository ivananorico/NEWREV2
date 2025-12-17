<?php
// ================================================
// GET REGISTRATION DETAILS API
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
        getRegistrationDetails($pdo);
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

function getRegistrationDetails($pdo) {
    // Validate required parameters
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Registration ID is required"]);
        return;
    }

    $registrationId = trim($_GET['id']);
    
    if (!is_numeric($registrationId)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid registration ID format"]);
        return;
    }

    try {
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
            echo json_encode(["error" => "Registration not found for ID: " . $registrationId]);
            return;
        }

        // Ensure all fields have values
        $registration = array_map(function($value) {
            return $value === null ? '' : $value;
        }, $registration);

        echo json_encode([
            "success" => true,
            "data" => $registration
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}