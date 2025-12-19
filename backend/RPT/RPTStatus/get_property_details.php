<?php
// ================================================
// GET PROPERTY DETAILS API - PRODUCTION VERSION
// ================================================

// CORS Headers
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
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Property ID is required",
        "example_url" => "http://localhost/revenue/backend/RPT/RPTStatus/get_property_details.php?id=1"
    ]);
    exit();
}

$property_id = intval($_GET['id']);

// Database connection
try {
    // Path to your database configuration
    $dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php';
    
    if (!file_exists($dbPath)) {
        throw new Exception("Database configuration file not found at: " . $dbPath);
    }
    
    require_once $dbPath;
    
    // Get database connection
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        throw new Exception("Failed to connect to database");
    }
    
    // Check if connection returns error array
    if (is_array($pdo) && isset($pdo['error'])) {
        throw new Exception($pdo['message'] ?? "Database connection error");
    }
    
    // Query 1: Get property basic info
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
            
            po.owner_name,
            po.email,
            po.phone,
            po.owner_address,
            po.tin_number,
            
            lp.id as land_id,
            lp.tdn as land_tdn,
            lp.property_type,
            lp.land_area_sqm,
            lp.land_market_value,
            lp.land_assessed_value,
            lp.assessment_level as land_assessment_level,
            lp.annual_tax as land_annual_tax,
            lp.basic_tax_amount as land_basic_tax,
            lp.sef_tax_amount as land_sef_tax,
            
            lc.classification as land_classification,
            lc.market_value as land_market_value_per_sqm,
            
            pt.total_annual_tax,
            pt.total_building_annual_tax,
            pt.approval_date
            
        FROM property_registrations pr
        
        INNER JOIN property_owners po 
            ON pr.owner_id = po.id
            
        INNER JOIN land_properties lp 
            ON pr.id = lp.registration_id
            
        INNER JOIN land_configurations lc 
            ON lp.land_config_id = lc.id
            
        INNER JOIN property_totals pt 
            ON pr.id = pt.registration_id
            
        WHERE pr.id = ? 
        AND pr.status = 'approved'
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($propertyQuery);
    $stmt->execute([$property_id]);
    
    if ($stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "Property not found or not approved"
        ]);
        exit();
    }
    
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Query 2: Get building details
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
                bp.depreciation_percent,
                bp.assessment_level as building_assessment_level,
                bp.annual_tax as building_annual_tax,
                bp.basic_tax_amount as building_basic_tax,
                bp.sef_tax_amount as building_sef_tax,
                bp.building_depreciated_value,
                bp.useful_life_years,
                bp.depreciation_rate,
                
                pc.material_type,
                pc.unit_cost as building_market_value_per_sqm
                
            FROM building_properties bp
            
            LEFT JOIN property_configurations pc 
                ON bp.property_config_id = pc.id
                
            WHERE bp.land_id = ? 
            AND bp.status = 'active'
        ";
        
        $buildingStmt = $pdo->prepare($buildingQuery);
        $buildingStmt->execute([$property['land_id']]);
        $buildings = $buildingStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Query 3: Get quarterly taxes
    $quarterlyTaxes = [];
    $taxQuery = "
        SELECT 
            qt.id,
            qt.quarter,
            qt.year,
            qt.due_date,
            qt.total_quarterly_tax,
            qt.penalty_amount,
            qt.payment_status,
            qt.payment_date,
            qt.receipt_number,
            qt.created_at
            
        FROM quarterly_taxes qt
        
        WHERE qt.property_total_id = (
            SELECT id FROM property_totals WHERE registration_id = ?
        )
        
        ORDER BY qt.year DESC, 
                 CASE qt.quarter 
                     WHEN 'Q1' THEN 1 
                     WHEN 'Q2' THEN 2 
                     WHEN 'Q3' THEN 3 
                     WHEN 'Q4' THEN 4 
                 END DESC
    ";
    
    $taxStmt = $pdo->prepare($taxQuery);
    $taxStmt->execute([$property_id]);
    $quarterlyTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Query 4: Get tax configuration
    $taxConfigQuery = "
        SELECT 
            tax_name,
            tax_percent
        FROM rpt_tax_config 
        WHERE status = 'active'
        ORDER BY tax_name
    ";
    
    $taxConfigStmt = $pdo->query($taxConfigQuery);
    $taxConfigs = $taxConfigStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process tax config
    $basicTaxPercent = 0;
    $sefTaxPercent = 0;
    
    foreach ($taxConfigs as $config) {
        if ($config['tax_name'] === 'Basic Tax') {
            $basicTaxPercent = floatval($config['tax_percent']);
        } elseif ($config['tax_name'] === 'SEF Tax') {
            $sefTaxPercent = floatval($config['tax_percent']);
        }
    }
    
    // Calculate tax totals
    $total_basic_tax = floatval($property['land_basic_tax'] ?? 0);
    $total_sef_tax = floatval($property['land_sef_tax'] ?? 0);
    $total_land_tax = floatval($property['land_annual_tax'] ?? 0);
    
    $total_building_basic_tax = 0;
    $total_building_sef_tax = 0;
    $total_building_tax = 0;
    
    foreach ($buildings as $building) {
        $total_building_basic_tax += floatval($building['building_basic_tax'] ?? 0);
        $total_building_sef_tax += floatval($building['building_sef_tax'] ?? 0);
        $total_building_tax += floatval($building['building_annual_tax'] ?? 0);
    }
    
    $total_basic_tax += $total_building_basic_tax;
    $total_sef_tax += $total_building_sef_tax;
    $total_annual_tax = floatval($property['total_annual_tax'] ?? 0);
    
    // Add tax breakdown to response
    $property['tax_breakdown'] = [
        'basic_tax_percent' => $basicTaxPercent,
        'sef_tax_percent' => $sefTaxPercent,
        'land_basic_tax' => $property['land_basic_tax'] ?? 0,
        'land_sef_tax' => $property['land_sef_tax'] ?? 0,
        'total_land_tax' => $property['land_annual_tax'] ?? 0,
        'total_building_basic_tax' => $total_building_basic_tax,
        'total_building_sef_tax' => $total_building_sef_tax,
        'total_building_tax' => $total_building_tax,
        'total_basic_tax' => $total_basic_tax,
        'total_sef_tax' => $total_sef_tax,
        'total_annual_tax' => $total_annual_tax
    ];
    
    // Prepare final response
    $response = [
        "success" => true,
        "data" => [
            "property" => $property,
            "buildings" => $buildings,
            "quarterly_taxes" => $quarterlyTaxes,
            "tax_config" => [
                "basic_tax_percent" => $basicTaxPercent,
                "sef_tax_percent" => $sefTaxPercent,
                "total_tax_rate" => $basicTaxPercent + $sefTaxPercent
            ]
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database error",
        "message" => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Server error",
        "message" => $e->getMessage()
    ]);
}
?>