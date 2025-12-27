<?php
// revenue2/backend/RPT/RPTValidationTable/get_registrations.php

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
        getRegistrations($pdo);
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

function getRegistrations($pdo) {
    try {
        // OPTION 1: Get raw data and concatenate in PHP (Recommended)
        $stmt = $pdo->prepare("
            SELECT 
                pr.id,
                pr.reference_number,
                pr.lot_location,
                pr.barangay,
                pr.district,
                pr.has_building,
                pr.status,
                pr.created_at,
                po.first_name,
                po.last_name,
                po.middle_name,
                po.suffix,
                po.email,
                po.phone
            FROM property_registrations pr
            LEFT JOIN property_owners po ON pr.owner_id = po.id
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute();
        $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Concatenate names in PHP properly
        foreach ($registrations as &$registration) {
            $firstName = $registration['first_name'] ?? '';
            $middleName = $registration['middle_name'] ?? '';
            $lastName = $registration['last_name'] ?? '';
            $suffix = $registration['suffix'] ?? '';
            
            // Format 1: Full middle name: Ivan Dolera Anorico
            // $registration['owner_name'] = trim($firstName . 
            //     (!empty($middleName) ? ' ' . $middleName : '') . 
            //     ' ' . $lastName . 
            //     (!empty($suffix) ? ' ' . $suffix : ''));
            
            // Format 2: Middle initial: Ivan D. Anorico
            $registration['owner_name'] = trim($firstName . 
                (!empty($middleName) ? ' ' . substr($middleName, 0, 1) . '.' : '') . 
                ' ' . $lastName . 
                (!empty($suffix) ? ' ' . $suffix : ''));
        }

        echo json_encode([
            "success" => true,
            "data" => $registrations ?: []
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Database error in get_registrations: " . $e->getMessage());
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}