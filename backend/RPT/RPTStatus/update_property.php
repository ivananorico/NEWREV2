<?php
// backend/RPT/RPTStatus/update_property.php

// Handle CORS properly
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = [
    "http://localhost:5173",
    "http://localhost:3000",
    "https://revenuetreasury.goserveph.com"
];
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");
header("Access-Control-Allow-Credentials: false");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Method not allowed"
    ]);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid input data"
    ]);
    exit();
}

$property_id = intval($input['id']);

// Database connection
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';
if (!file_exists($dbPath)) {
    echo json_encode([
        "status" => "error",
        "message" => "Database configuration not found"
    ]);
    exit();
}
require_once $dbPath;

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
        error_log("Database connection failed: " . $e->getMessage());
        return [
            'error' => true,
            'message' => 'Database connection failed. Please try again later.'
        ];
    }
}

try {
    $pdo = createPDOConnection();
    
    if (is_array($pdo) && isset($pdo['error'])) {
        throw new Exception($pdo['message']);
    }

    // Start transaction
    $pdo->beginTransaction();

    // First, get the owner_id from property_registrations
    $stmt = $pdo->prepare("SELECT owner_id FROM property_registrations WHERE id = ?");
    $stmt->execute([$property_id]);
    $result = $stmt->fetch();

    if (!$result) {
        throw new Exception("Property not found");
    }

    $owner_id = $result['owner_id'];

    // Update property_owners table
    if ($owner_id) {
        $ownerUpdateQuery = "
            UPDATE property_owners SET
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                suffix = ?,
                birthdate = ?,
                sex = ?,
                marital_status = ?,
                email = ?,
                phone = ?,
                address = ?,
                updated_at = NOW()
            WHERE id = ?
        ";

        // Parse full name into components
        $fullName = $input['owner_name'] ?? '';
        $nameParts = explode(' ', trim($fullName));
        $firstName = $nameParts[0] ?? '';
        $lastName = end($nameParts) ?? '';
        $middleName = count($nameParts) > 2 ? implode(' ', array_slice($nameParts, 1, -1)) : null;
        $suffix = null;

        $ownerStmt = $pdo->prepare($ownerUpdateQuery);
        $ownerStmt->execute([
            $firstName,
            $middleName,
            $lastName,
            $suffix,
            $input['birthdate'] ?? null,
            $input['sex'] ?? null,
            $input['marital_status'] ?? null,
            $input['email'] ?? null,
            $input['phone'] ?? null,
            $input['owner_address'] ?? null,
            $owner_id
        ]);
    }

    // Update property_registrations table
    $propertyUpdateQuery = "
        UPDATE property_registrations SET
            lot_location = ?,
            barangay = ?,
            district = ?,
            city = ?,
            province = ?,
            zip_code = ?,
            updated_at = NOW()
        WHERE id = ?
    ";

    $propertyStmt = $pdo->prepare($propertyUpdateQuery);
    $propertyStmt->execute([
        $input['lot_location'] ?? null,
        $input['barangay'] ?? null,
        $input['district'] ?? null,
        $input['city'] ?? null,
        $input['province'] ?? null,
        $input['zip_code'] ?? null,
        $property_id
    ]);

    // Update land_properties table if exists
    if (isset($input['land_id'])) {
        $landUpdateQuery = "
            UPDATE land_properties SET
                property_type = ?
            WHERE id = ?
        ";

        $landStmt = $pdo->prepare($landUpdateQuery);
        $landStmt->execute([
            $input['property_type'] ?? 'Residential',
            $input['land_id']
        ]);
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Property updated successfully"
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && !is_array($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Update Property Error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "An error occurred while updating the property",
        "debug" => $e->getMessage()
    ]);
}
?>