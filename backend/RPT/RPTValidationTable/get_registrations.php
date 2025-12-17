<?php
// revenue/backend/RPT/RPTValidationTable/get_registrations.php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Include your RPT database connection
    require_once __DIR__ . "/../../../db/RPT/rpt_db.php";

    // Fetch all property registrations with owner information
    $stmt = $pdo->prepare("
        SELECT 
            pr.id,
            pr.reference_number,
            pr.lot_location,
            pr.barangay,
            pr.district,
            pr.has_building,
            pr.status,
            pr.correction_notes,
            pr.created_at,
            po.full_name as owner_name,
            po.email,
            po.phone
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        ORDER BY pr.created_at DESC
    ");
    
    $stmt->execute();
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'registrations' => $registrations
    ]);

} catch (Exception $e) {
    error_log("Get registrations error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>