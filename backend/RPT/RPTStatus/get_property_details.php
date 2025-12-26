<?php
// ================================================
// GET PROPERTY DETAILS API - SIMPLE VERSION
// ================================================

// CORS Headers - Simple and works everywhere
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        "success" => false,
        "error" => "Property ID is required"
    ]);
    exit();
}

$property_id = intval($_GET['id']);

// Database connection - adjust path if needed
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';

if (!file_exists($dbPath)) {
    echo json_encode([
        "success" => false,
        "error" => "Database config not found"
    ]);
    exit();
}

require_once $dbPath;

$pdo = getDatabaseConnection();
if (!$pdo) {
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed"
    ]);
    exit();
}

// Get property basic info - UPDATED TO INCLUDE PROPERTY TYPE
$propertyQuery = "
    SELECT 
        pr.id,
        pr.reference_number,
        pr.status,
        pr.lot_location,
        pr.barangay,
        pr.district,
        pr.has_building,
        pr.created_at,
        
        po.full_name AS owner_name,
        po.email,
        po.phone,
        po.address AS owner_address,
        
        lp.land_area_sqm,
        lp.land_market_value,
        lp.land_assessed_value,
        lp.annual_tax as land_annual_tax,
        lp.property_type as land_classification,  -- ADDED THIS
        lp.tdn as land_tdn,                       -- ADDED THIS
        
        pt.total_annual_tax
        
    FROM property_registrations pr
    
    LEFT JOIN property_owners po ON pr.owner_id = po.id
    LEFT JOIN land_properties lp ON pr.id = lp.registration_id
    LEFT JOIN property_totals pt ON pr.id = pt.registration_id
    
    WHERE pr.id = ? 
    LIMIT 1
";

try {
    $stmt = $pdo->prepare($propertyQuery);
    $stmt->execute([$property_id]);
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            "success" => false,
            "error" => "Property not found"
        ]);
        exit();
    }
    
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get building details if exists
    $buildings = [];
    if ($property['has_building'] === 'yes') {
        $buildingQuery = "
            SELECT 
                bp.id,
                bp.tdn,
                bp.construction_type,
                bp.floor_area_sqm,
                bp.year_built,
                bp.building_market_value,
                bp.building_assessed_value,
                bp.annual_tax as building_annual_tax,
                bp.assessment_level
            FROM building_properties bp
            WHERE bp.land_id = (
                SELECT id FROM land_properties WHERE registration_id = ?
            ) AND bp.status = 'active'
        ";
        
        $buildingStmt = $pdo->prepare($buildingQuery);
        $buildingStmt->execute([$property_id]);
        $buildings = $buildingStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get quarterly taxes
    $quarterlyTaxes = [];
    $taxQuery = "
        SELECT 
            qt.id,
            qt.quarter,
            qt.year,
            qt.due_date,
            qt.total_quarterly_tax,
            qt.payment_status,
            qt.penalty_amount,
            qt.payment_date,
            qt.receipt_number
        FROM quarterly_taxes qt
        WHERE qt.property_total_id = (
            SELECT id FROM property_totals WHERE registration_id = ?
        )
        ORDER BY year DESC, 
                 CASE quarter 
                    WHEN 'Q1' THEN 1 
                    WHEN 'Q2' THEN 2 
                    WHEN 'Q3' THEN 3 
                    WHEN 'Q4' THEN 4 
                 END DESC
    ";
    
    $taxStmt = $pdo->prepare($taxQuery);
    $taxStmt->execute([$property_id]);
    $quarterlyTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare response
    $response = [
        "success" => true,
        "data" => [
            "property" => $property,
            "buildings" => $buildings,
            "quarterly_taxes" => $quarterlyTaxes
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>