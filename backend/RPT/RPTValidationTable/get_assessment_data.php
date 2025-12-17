<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../../../db/RPT/rpt_db.php";

try {
    if (!isset($_GET['id'])) {
        throw new Exception("Registration ID is required");
    }

    $registration_id = $_GET['id'];

    // Get land assessment
    $land_stmt = $pdo->prepare("
        SELECT 
            lp.id, lp.tdn, lp.property_type, lp.land_area_sqm, 
            lp.land_market_value, lp.land_assessed_value, lp.assessment_level,
            lp.basic_tax_amount, lp.sef_tax_amount,
            lp.annual_tax,
            lc.classification as land_classification,
            basic_tax.tax_name as basic_tax_name,
            basic_tax.tax_percent as basic_tax_percent,
            sef_tax.tax_name as sef_tax_name,
            sef_tax.tax_percent as sef_tax_percent
        FROM land_properties lp
        LEFT JOIN land_configurations lc ON lp.land_config_id = lc.id
        LEFT JOIN rpt_tax_config basic_tax ON lp.basic_tax_config_id = basic_tax.id
        LEFT JOIN rpt_tax_config sef_tax ON lp.sef_tax_config_id = sef_tax.id
        WHERE lp.registration_id = ?
        ORDER BY lp.id DESC LIMIT 1
    ");
    $land_stmt->execute([$registration_id]);
    $land_assessment = $land_stmt->fetch(PDO::FETCH_ASSOC);

    // Get building assessment
    $building_stmt = $pdo->prepare("
        SELECT 
            bp.id, bp.tdn, bp.construction_type, bp.floor_area_sqm, 
            bp.year_built, bp.building_market_value, bp.building_depreciated_value,
            bp.depreciation_percent, bp.building_assessed_value, bp.assessment_level,
            bp.basic_tax_amount, bp.sef_tax_amount,
            bp.annual_tax,
            pc.material_type,
            basic_tax.tax_name as basic_tax_name,
            basic_tax.tax_percent as basic_tax_percent,
            sef_tax.tax_name as sef_tax_name,
            sef_tax.tax_percent as sef_tax_percent
        FROM building_properties bp
        LEFT JOIN property_configurations pc ON bp.property_config_id = pc.id
        LEFT JOIN rpt_tax_config basic_tax ON bp.basic_tax_config_id = basic_tax.id
        LEFT JOIN rpt_tax_config sef_tax ON bp.sef_tax_config_id = sef_tax.id
        WHERE bp.land_id IN (SELECT id FROM land_properties WHERE registration_id = ?)
        ORDER BY bp.id DESC LIMIT 1
    ");
    $building_stmt->execute([$registration_id]);
    $building_assessment = $building_stmt->fetch(PDO::FETCH_ASSOC);

    // Get total annual tax from property_totals if approved
    $total_stmt = $pdo->prepare("
        SELECT total_annual_tax 
        FROM property_totals 
        WHERE registration_id = ? 
        AND status = 'active' 
        LIMIT 1
    ");
    $total_stmt->execute([$registration_id]);
    $total_annual_tax = $total_stmt->fetch(PDO::FETCH_COLUMN);

    echo json_encode([
        'status' => 'success',
        'land_assessment' => $land_assessment ? $land_assessment : null,
        'building_assessment' => $building_assessment ? $building_assessment : null,
        'total_annual_tax' => $total_annual_tax ? $total_annual_tax : 0
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>