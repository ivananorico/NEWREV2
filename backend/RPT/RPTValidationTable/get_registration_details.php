<?php
// ================================================
// GET REGISTRATION DETAILS API
// ================================================

// Enable CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Cache-Control, Pragma");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Try to include DB connection
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
        // Main registration + owner data
        $query = "
            SELECT 
                pr.id,
                pr.reference_number,
                pr.lot_location,
                pr.barangay,
                pr.district,
                pr.city,
                pr.province,
                pr.zip_code,
                pr.has_building,
                pr.status,
                COALESCE(pr.correction_notes, '') as correction_notes,
                pr.created_at,
                pr.updated_at,
                po.first_name,
                po.last_name,
                po.middle_name,
                po.suffix,
                po.email,
                po.phone,
                COALESCE(po.tin_number, '') as tin_number,
                COALESCE(po.address, '') as owner_address,
                po.house_number,
                po.street,
                po.barangay as owner_barangay,
                po.district as owner_district,
                po.city as owner_city,
                po.province as owner_province,
                po.zip_code as owner_zip_code,
                po.birthdate
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

        // Construct full owner name
        $firstName = $registration['first_name'] ?? '';
        $lastName = $registration['last_name'] ?? '';
        $middleName = $registration['middle_name'] ?? '';
        $suffix = $registration['suffix'] ?? '';
        $ownerName = trim($firstName . ' ' . (!empty($middleName) ? substr($middleName,0,1).'. ' : '') . $lastName . (!empty($suffix) ? ' '.$suffix : ''));

        // Construct owner address
        $ownerAddress = $registration['owner_address'];
        if (empty($ownerAddress)) {
            $ownerAddress = trim(
                (!empty($registration['house_number']) ? $registration['house_number'].' ' : '') .
                (!empty($registration['street']) ? $registration['street'].', ' : '') .
                (!empty($registration['owner_barangay']) ? $registration['owner_barangay'].', ' : '') .
                (!empty($registration['owner_district']) ? 'District '.$registration['owner_district'].', ' : '') .
                (!empty($registration['owner_city']) ? $registration['owner_city'].', ' : '') .
                (!empty($registration['owner_province']) ? $registration['owner_province'].' ' : '') .
                (!empty($registration['owner_zip_code']) ? $registration['owner_zip_code'] : '')
            );
        }

        // Initialize inspection fields
        $inspectorName = '';
        $inspectionDate = '';

        // Fetch inspection info if status is 'for inspection'
        if (strtolower($registration['status']) === 'for inspection') {
            $inspectionQuery = "
                SELECT i.inspection_date, u.full_name as inspector_name
                FROM property_inspections i
                LEFT JOIN users u ON i.inspector_id = u.id
                WHERE i.registration_id = ?
                ORDER BY i.inspection_date DESC
                LIMIT 1
            ";
            $stmtInspect = $pdo->prepare($inspectionQuery);
            $stmtInspect->execute([$registrationId]);
            $inspection = $stmtInspect->fetch(PDO::FETCH_ASSOC);
            if ($inspection) {
                $inspectorName = $inspection['inspector_name'] ?? '';
                $inspectionDate = $inspection['inspection_date'] ?? '';
            }
        }

        // Prepare response
        $responseData = [
            "id" => $registration['id'],
            "reference_number" => $registration['reference_number'],
            "location_address" => $registration['lot_location'],
            "barangay" => $registration['barangay'],
            "municipality_city" => $registration['city'] ?? 'Quezon City',
            "district" => $registration['district'],
            "province" => $registration['province'] ?? 'Metro Manila',
            "zip_code" => $registration['zip_code'],
            "property_type" => "Residential",
            "has_building" => $registration['has_building'],
            "status" => $registration['status'],
            "remarks" => $registration['correction_notes'],
            "date_registered" => $registration['created_at'],
            "last_updated" => $registration['updated_at'],
            "owner_name" => $ownerName,
            "email_address" => $registration['email'],
            "contact_number" => $registration['phone'],
            "tin" => $registration['tin_number'],
            "owner_address" => $ownerAddress,
            "first_name" => $registration['first_name'],
            "last_name" => $registration['last_name'],
            "middle_name" => $registration['middle_name'],
            "suffix" => $registration['suffix'],
            "birthdate" => $registration['birthdate'],
            "owner_city" => $registration['owner_city'] ?? 'Quezon City',
            "owner_province" => $registration['owner_province'] ?? 'Metro Manila',
            // Inspection fields
            "inspector_name" => $inspectorName,
            "inspection_date" => $inspectionDate
        ];

        // Replace nulls with empty strings
        $responseData = array_map(function($v){ return $v===null?'':$v; }, $responseData);

        echo json_encode([
            "success" => true,
            "data" => $responseData
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Database error in getRegistrationDetails: " . $e->getMessage());
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}
