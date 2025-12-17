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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Property ID is required"
    ]);
    exit;
}

$property_id = $_GET['id'];

try {
    // Get basic property information - FIXED: Added annual_tax field
    $query = "
        SELECT 
            pr.*,
            po.*,
            lp.*,
            lp.annual_tax as land_annual_tax,  -- This line was added
            pt.total_annual_tax,
            pt.approval_date,
            lp.tdn as land_tdn,
            lc.classification as land_classification
        FROM property_registrations pr
        LEFT JOIN property_owners po ON pr.owner_id = po.id
        LEFT JOIN land_properties lp ON pr.id = lp.registration_id
        LEFT JOIN land_configurations lc ON lp.land_config_id = lc.id
        LEFT JOIN property_totals pt ON pr.id = pt.registration_id
        WHERE pr.id = :id AND pr.status = 'approved'
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $property_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Property not found or not approved"
        ]);
        exit;
    }
    
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get building details
    $buildingQuery = "
        SELECT 
            bp.*,
            pc.classification as construction_type,
            pc.material_type
        FROM building_properties bp
        LEFT JOIN property_configurations pc ON bp.property_config_id = pc.id
        WHERE bp.land_id = :land_id AND bp.status = 'active'
    ";
    
    $buildingStmt = $pdo->prepare($buildingQuery);
    $buildingStmt->bindParam(':land_id', $property['id']);
    $buildingStmt->execute();
    $buildings = $buildingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get quarterly tax details
    $taxQuery = "
        SELECT 
            qt.*
        FROM quarterly_taxes qt
        JOIN property_totals pt ON qt.property_total_id = pt.id
        WHERE pt.registration_id = :reg_id
        ORDER BY qt.year DESC, qt.quarter
    ";
    
    $taxStmt = $pdo->prepare($taxQuery);
    $taxStmt->bindParam(':reg_id', $property_id);
    $taxStmt->execute();
    $quarterly_taxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "status" => "success",
        "property" => $property,
        "buildings" => $buildings,
        "quarterly_taxes" => $quarterly_taxes
    ]);
    
} catch (PDOException $exception) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $exception->getMessage()
    ]);
}
?>