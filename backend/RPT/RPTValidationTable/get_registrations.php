<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://revenuetreasury.goserveph.com");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../../../db/RPT/rpt_db.php";

try {
    $stmt = $pdo->query("
        SELECT 
            pr.id,
            pr.reference_number,
            pr.lot_location,
            pr.barangay,
            pr.district,
            pr.has_building,
            pr.status,
            pr.created_at,
            po.full_name AS owner_name,
            po.email,
            po.phone
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        ORDER BY pr.created_at DESC
    ");

    echo json_encode([
        "status" => "success",
        "registrations" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
