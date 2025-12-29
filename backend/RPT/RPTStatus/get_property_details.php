<?php
// backend/RPT/RPTStatus/get_property_details.php

// Handle CORS properly
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = [
    "http://localhost:5173",
    "http://localhost:3000",
    "https://revenuetreasury.goserveph.com"
];

// If origin is allowed, use it, otherwise use wildcard
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Accept, Content-Type");
header("Access-Control-Allow-Credentials: false");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Property ID is required"
    ]);
    exit();
}

$property_id = intval($_GET['id']);

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

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Get property basic info with enhanced building data
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
            pr.updated_at,
            
            po.first_name,
            po.last_name,
            CONCAT(po.first_name, ' ', po.last_name) AS owner_name,
            po.email,
            po.phone,
            po.address AS owner_address,
            
            lp.id as land_id,
            lp.land_area_sqm,
            lp.land_market_value,
            lp.land_assessed_value,
            lp.annual_tax as land_annual_tax,
            lp.basic_tax_amount as land_basic_tax,
            lp.sef_tax_amount as land_sef_tax,
            lp.property_type as land_classification,
            lp.tdn as land_tdn,
            
            pt.total_annual_tax
            
        FROM property_registrations pr
        
        LEFT JOIN property_owners po ON pr.owner_id = po.id
        LEFT JOIN land_properties lp ON pr.id = lp.registration_id
        LEFT JOIN property_totals pt ON pr.id = pt.registration_id
        
        WHERE pr.id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($propertyQuery);
    $stmt->execute([$property_id]);
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Property not found"
        ]);
        exit();
    }
    
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get building details if exists
    $buildings = [];
    $totalBuildingAnnualTax = 0;
    if ($property['has_building'] === 'yes' && isset($property['land_id'])) {
        $buildingQuery = "
            SELECT 
                bp.id,
                bp.tdn,
                bp.construction_type,
                bp.floor_area_sqm,
                bp.year_built,
                bp.building_market_value,
                bp.building_depreciated_value,
                bp.depreciation_percent,
                bp.building_assessed_value,
                bp.annual_tax as building_annual_tax,
                bp.assessment_level,
                bp.basic_tax_amount,
                bp.sef_tax_amount,
                pc.classification,
                bal.level_percent as actual_assessment_level
            FROM building_properties bp
            LEFT JOIN property_configurations pc ON bp.property_config_id = pc.id
            LEFT JOIN building_assessment_levels bal ON pc.classification = bal.classification 
                AND bp.building_assessed_value >= bal.min_assessed_value 
                AND bp.building_assessed_value <= bal.max_assessed_value
                AND bal.status = 'active'
            WHERE bp.land_id = ?
            AND bp.status = 'active'
        ";
        
        $buildingStmt = $pdo->prepare($buildingQuery);
        $buildingStmt->execute([$property['land_id']]);
        $buildings = $buildingStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate total building annual tax
        foreach ($buildings as $building) {
            $totalBuildingAnnualTax += floatval($building['building_annual_tax'] ?? 0);
        }
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
            qt.receipt_number,
            qt.discount_applied,
            qt.discount_amount,
            qt.discount_percent_used
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
    
    // Calculate totals
    $totalAnnualTax = floatval($property['total_annual_tax'] ?? 0);
    $landAnnualTax = floatval($property['land_annual_tax'] ?? 0);
    
    // If total annual tax is not set, calculate it
    if ($totalAnnualTax == 0) {
        $totalAnnualTax = $landAnnualTax + $totalBuildingAnnualTax;
    }
    
    // Get paid taxes
    $paidTaxes = array_filter($quarterlyTaxes, function($tax) {
        return $tax['payment_status'] === 'paid';
    });
    
    $totalPaid = 0;
    foreach ($paidTaxes as $tax) {
        $totalPaid += floatval($tax['total_quarterly_tax']);
    }
    
    $collectionRate = $totalAnnualTax > 0 ? ($totalPaid / $totalAnnualTax) * 100 : 0;
    
    // Prepare response
    $response = [
        "status" => "success",
        "data" => [
            "property" => $property,
            "buildings" => $buildings,
            "quarterly_taxes" => $quarterlyTaxes,
            "totals" => [
                "land_annual_tax" => $landAnnualTax,
                "building_annual_tax" => $totalBuildingAnnualTax,
                "total_annual_tax" => $totalAnnualTax,
                "total_paid" => $totalPaid,
                "collection_rate" => round($collectionRate, 2)
            ]
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
    
} catch (Exception $e) {
    error_log("Property Details Error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "An error occurred while fetching property details",
        "debug" => $e->getMessage()
    ]);
}
?>