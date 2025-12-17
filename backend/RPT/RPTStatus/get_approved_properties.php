<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../../db/RPT/rpt_db.php';

if (!isset($pdo)) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

try {
    // Query to get approved properties - GROUP BY property registration to avoid duplicates
    $query = "
        SELECT 
            pr.id,
            pr.reference_number,
            pr.lot_location,
            pr.barangay,
            pr.district,
            pr.has_building,
            pr.status,
            pr.created_at,
            po.full_name as owner_name,
            po.email,
            po.phone,
            po.address as owner_address,
            lp.land_area_sqm,
            lp.land_market_value,
            lp.land_assessed_value,
            lp.annual_tax as land_annual_tax,
            pt.total_annual_tax,
            pt.approval_date,
            lc.classification as land_classification,
            (SELECT COUNT(*) FROM building_properties bp WHERE bp.land_id = lp.id AND bp.status = 'active') as building_count
        FROM property_registrations pr
        LEFT JOIN property_owners po ON pr.owner_id = po.id
        LEFT JOIN land_properties lp ON pr.id = lp.registration_id
        LEFT JOIN property_totals pt ON pr.id = pt.registration_id
        LEFT JOIN land_configurations lc ON lp.land_config_id = lc.id
        WHERE pr.status = 'approved'
        GROUP BY pr.id  -- Group by property registration to avoid duplicates
        ORDER BY pt.approval_date DESC, pr.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "status" => "success",
        "properties" => $properties,
        "count" => count($properties)
    ]);
    
} catch (PDOException $exception) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $exception->getMessage()
    ]);
}
?>