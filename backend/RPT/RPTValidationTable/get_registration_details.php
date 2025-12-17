<?php
// revenue/backend/RPT/RPTValidationTable/get_registration_details.php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Get registration ID from query parameter
    $registrationId = $_GET['id'] ?? null;
    
    if (!$registrationId) {
        throw new Exception("Registration ID is required");
    }

    // Include your RPT database connection
    require_once __DIR__ . "/../../../db/RPT/rpt_db.php";

    // Fetch specific registration with owner information - FIXED COLUMNS
    $query = "
        SELECT 
            pr.id,
            pr.reference_number,
            pr.lot_location as location_address,
            pr.barangay,
            pr.district as municipality_city,
            '' as province,
            '' as property_type,
            pr.has_building,
            pr.status,
            pr.correction_notes as remarks,
            pr.created_at as date_registered,
            po.full_name as owner_name,
            po.email as email_address,
            po.phone as contact_number,
            po.tin_number as tin,
            po.address as owner_address
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE pr.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$registrationId]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        throw new Exception("Registration not found for ID: " . $registrationId);
    }

    echo json_encode([
        'status' => 'success',
        'registration' => $registration
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>