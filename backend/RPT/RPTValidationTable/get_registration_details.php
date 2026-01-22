<?php
//revenue2/backend/RPT/RPTValidationTable/get_registration_details.php
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

// Create database connection using the config from rpt_db.php
function createPDOConnection() {
    $config = getDatabaseConfig();
    
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        return $pdo;
        
    } catch (PDOException $e) {
        // Log error but don't expose details to user
        error_log("Database connection failed: " . $e->getMessage());
        
        // Return user-friendly error
        return [
            'error' => true,
            'message' => 'Database connection failed. Please try again later.',
            'debug' => ($_SERVER['HTTP_HOST'] !== 'revenuetreasury.goserveph.com') ? $e->getMessage() : null
        ];
    }
}

// Include DB connection
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Database config file not found at: " . $dbPath]);
    exit();
}

require_once $dbPath;

// Get PDO connection
$pdo = createPDOConnection();
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
                pr.*,
                po.first_name,
                po.last_name,
                po.middle_name,
                po.suffix,
                po.email,
                po.phone,
                COALESCE(po.tin_number, '') as tin_number,
                po.address,
                po.house_number,
                po.street,
                po.barangay as owner_barangay,
                po.district as owner_district,
                po.city as owner_city,
                po.province as owner_province,
                po.zip_code as owner_zip_code,
                po.birthdate,
                po.sex,
                po.marital_status
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
        $ownerName = trim(
            $registration['first_name'] . ' ' .
            (!empty($registration['middle_name']) ? substr($registration['middle_name'],0,1).'. ' : '') .
            $registration['last_name'] .
            (!empty($registration['suffix']) ? ' '.$registration['suffix'] : '')
        );

        // Construct owner address
        $ownerAddress = $registration['address'];
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

        // Fetch LATEST inspection for this registration
        $inspectionQuery = "
            SELECT 
                id,
                scheduled_date, 
                assessor_name, 
                status,
                created_at,
                updated_at
            FROM property_inspections 
            WHERE registration_id = ? 
            ORDER BY created_at DESC, scheduled_date DESC
            LIMIT 1
        ";
        
        $stmtInspect = $pdo->prepare($inspectionQuery);
        $stmtInspect->execute([$registrationId]);
        $inspection = $stmtInspect->fetch(PDO::FETCH_ASSOC);

        // Fetch ALL inspections for this registration (for debugging)
        $allInspectionsQuery = "
            SELECT id, scheduled_date, assessor_name, status, created_at
            FROM property_inspections 
            WHERE registration_id = ? 
            ORDER BY created_at DESC
        ";
        $stmtAll = $pdo->prepare($allInspectionsQuery);
        $stmtAll->execute([$registrationId]);
        $allInspections = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        // Fetch documents for this registration
        $documentsQuery = "
            SELECT 
                id,
                document_type,
                file_name,
                file_path,
                file_size,
                file_type,
                uploaded_by,
                created_at
            FROM property_documents 
            WHERE registration_id = ? 
            ORDER BY created_at DESC
        ";
        $stmtDocs = $pdo->prepare($documentsQuery);
        $stmtDocs->execute([$registrationId]);
        $documents = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

        // Prepare inspection data
        $inspectionData = $inspection ?: [];
        
        // Prepare response
        $responseData = [
            "id" => $registration['id'],
            "reference_number" => $registration['reference_number'],
            "lot_location" => $registration['lot_location'],
            "location_address" => $registration['lot_location'], // For frontend compatibility
            "barangay" => $registration['barangay'],
            "municipality_city" => $registration['city'] ?? 'Quezon City',
            "city" => $registration['city'] ?? 'Quezon City',
            "district" => $registration['district'],
            "province" => $registration['province'] ?? 'Metro Manila',
            "zip_code" => $registration['zip_code'],
            "property_type" => "Residential",
            "has_building" => $registration['has_building'],
            "status" => $registration['status'],
            "correction_notes" => $registration['correction_notes'] ?? '',
            "remarks" => $registration['correction_notes'] ?? '',
            "created_at" => $registration['created_at'],
            "date_registered" => $registration['created_at'], // For frontend compatibility
            "updated_at" => $registration['updated_at'],
            "last_updated" => $registration['updated_at'],
            
            // Owner information
            "owner_name" => $ownerName,
            "first_name" => $registration['first_name'],
            "last_name" => $registration['last_name'],
            "middle_name" => $registration['middle_name'],
            "suffix" => $registration['suffix'],
            "email" => $registration['email'],
            "email_address" => $registration['email'], // For frontend compatibility
            "phone" => $registration['phone'],
            "contact_number" => $registration['phone'], // For frontend compatibility
            "tin_number" => $registration['tin_number'],
            "tin" => $registration['tin_number'], // For frontend compatibility
            "address" => $ownerAddress,
            "owner_address" => $ownerAddress, // For frontend compatibility
            "birthdate" => $registration['birthdate'],
            "sex" => $registration['sex'],
            "marital_status" => $registration['marital_status'],
            "owner_city" => $registration['owner_city'] ?? 'Quezon City',
            "owner_province" => $registration['owner_province'] ?? 'Metro Manila',
            "owner_zip_code" => $registration['owner_zip_code'],
            
            // Latest inspection data
            "inspection" => $inspectionData,
            "inspection_date" => $inspectionData['scheduled_date'] ?? '',
            "inspector_name" => $inspectionData['assessor_name'] ?? '',
            "inspection_status" => $inspectionData['status'] ?? '',
            
            // Debug info (only in development)
            "debug" => [
                "all_inspections_count" => count($allInspections),
                "all_inspections" => ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') ? $allInspections : [],
                "documents_count" => count($documents)
            ]
        ];

        // Replace nulls with empty strings
        $responseData = array_map(function($v){ return $v===null?'':$v; }, $responseData);

        echo json_encode([
            "success" => true,
            "data" => $responseData,
            "documents" => $documents
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Database error in getRegistrationDetails: " . $e->getMessage());
        echo json_encode([
            "error" => "Database error", 
            "message" => $e->getMessage(),
            "trace" => ($_SERVER['HTTP_HOST'] !== 'revenuetreasury.goserveph.com') ? $e->getTraceAsString() : null
        ]);
    }
}
?>