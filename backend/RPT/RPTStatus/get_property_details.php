<?php
// backend/RPT/RPTStatus/get_property_details.php

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

    // Get property + owner + land info - UPDATED to include all necessary fields
    $propertyQuery = "
        SELECT 
            pr.id,
            pr.reference_number,
            pr.status,
            pr.lot_location,
            pr.barangay,
            pr.district,
            pr.city,
            pr.province,
            pr.zip_code,
            pr.has_building,
            pr.created_at,
            pr.updated_at,

            -- Owner fields - UPDATED to include all fields
            po.first_name,
            po.last_name,
            po.middle_name,
            po.suffix,
            CONCAT(
                po.first_name, 
                IF(po.middle_name IS NOT NULL AND po.middle_name != '', CONCAT(' ', po.middle_name), ''), 
                ' ', 
                po.last_name,
                IF(po.suffix IS NOT NULL AND po.suffix != '', CONCAT(' ', po.suffix), '')
            ) AS owner_name,
            po.birthdate,
            po.sex,
            po.marital_status,
            po.email,
            po.phone,
            po.address AS owner_address,
            po.house_number,
            po.street,
            po.barangay AS owner_barangay,
            po.district AS owner_district,
            po.city AS owner_city,
            po.province AS owner_province,
            po.zip_code AS owner_zip_code,

            -- Land property fields
            lp.id as land_id,
            lp.land_area_sqm,
            lp.land_market_value,
            lp.land_assessed_value,
            lp.total_assessed_value,
            lp.assessment_level,
            lp.annual_tax as land_annual_tax,
            lp.basic_tax_amount as land_basic_tax,
            lp.sef_tax_amount as land_sef_tax,
            lp.property_type as land_classification,
            lp.tdn as land_tdn,

            -- Property totals
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

    // Ensure all required fields exist with proper fallbacks
    $property['owner_name'] = $property['owner_name'] ?? '';
    $property['first_name'] = $property['first_name'] ?? '';
    $property['last_name'] = $property['last_name'] ?? '';
    $property['middle_name'] = $property['middle_name'] ?? '';
    $property['birthdate'] = $property['birthdate'] ?? null;
    $property['sex'] = $property['sex'] ?? null;
    $property['marital_status'] = $property['marital_status'] ?? null;
    $property['email'] = $property['email'] ?? '';
    $property['phone'] = $property['phone'] ?? '';
    $property['contact_number'] = $property['phone']; // Add alias for React component
    $property['email_address'] = $property['email']; // Add alias for React component
    $property['owner_address'] = $property['owner_address'] ?? '';
    
    // Property location fields
    $property['lot_location'] = $property['lot_location'] ?? '';
    $property['barangay'] = $property['barangay'] ?? '';
    $property['district'] = $property['district'] ?? '';
    $property['city'] = $property['city'] ?? '';
    $property['municipality_city'] = $property['city']; // Add alias for React component
    $property['province'] = $property['province'] ?? '';
    $property['zip_code'] = $property['zip_code'] ?? '';
    
    // Land property fields with defaults
    $property['property_type'] = $property['land_classification'] ?? 'Residential';
    $property['land_area_sqm'] = floatval($property['land_area_sqm'] ?? 0);
    $property['land_market_value'] = floatval($property['land_market_value'] ?? 0);
    $property['land_assessed_value'] = floatval($property['land_assessed_value'] ?? 0);
    $property['total_assessed_value'] = floatval($property['total_assessed_value'] ?? 0);
    $property['assessment_level'] = floatval($property['assessment_level'] ?? 0);
    $property['land_annual_tax'] = floatval($property['land_annual_tax'] ?? 0);
    
    // Dates formatting
    $property['created_at'] = $property['created_at'] ?? '';
    $property['updated_at'] = $property['updated_at'] ?? '';
    $property['date_registered'] = $property['created_at']; // Add alias for React component

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
                bp.total_assessed_value as building_total_assessed,
                bp.assessment_level,
                bp.annual_tax as building_annual_tax,
                bp.basic_tax_amount,
                bp.sef_tax_amount,
                bp.status,
                bp.created_at
            FROM building_properties bp
            WHERE bp.land_id = ?
              AND bp.status = 'active'
            ORDER BY bp.created_at DESC
        ";
        $buildingStmt = $pdo->prepare($buildingQuery);
        $buildingStmt->execute([$property['land_id']]);
        $buildings = $buildingStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($buildings as &$b) {
            // Format building fields
            $b['floor_area_sqm'] = floatval($b['floor_area_sqm'] ?? 0);
            $b['building_market_value'] = floatval($b['building_market_value'] ?? 0);
            $b['building_depreciated_value'] = floatval($b['building_depreciated_value'] ?? 0);
            $b['depreciation_percent'] = floatval($b['depreciation_percent'] ?? 0);
            $b['building_assessed_value'] = floatval($b['building_assessed_value'] ?? 0);
            $b['assessment_level'] = floatval($b['assessment_level'] ?? 0);
            $b['building_annual_tax'] = floatval($b['building_annual_tax'] ?? 0);
            $b['basic_tax_amount'] = floatval($b['basic_tax_amount'] ?? 0);
            $b['sef_tax_amount'] = floatval($b['sef_tax_amount'] ?? 0);
            
            $totalBuildingAnnualTax += $b['building_annual_tax'];
        }
    }

    // Get quarterly taxes
    $quarterlyTaxes = [];
    if (isset($property['id'])) {
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
                qt.discount_percent_used,
                qt.days_late,
                qt.penalty_percent_used
            FROM quarterly_taxes qt
            INNER JOIN property_totals pt ON qt.property_total_id = pt.id
            WHERE pt.registration_id = ?
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

        foreach ($quarterlyTaxes as &$tax) {
            // Format tax fields
            $tax['total_quarterly_tax'] = floatval($tax['total_quarterly_tax'] ?? 0);
            $tax['penalty_amount'] = floatval($tax['penalty_amount'] ?? 0);
            $tax['discount_amount'] = floatval($tax['discount_amount'] ?? 0);
            $tax['days_late'] = intval($tax['days_late'] ?? 0);
            $tax['penalty_percent_used'] = floatval($tax['penalty_percent_used'] ?? 0);
            $tax['discount_percent_used'] = floatval($tax['discount_percent_used'] ?? 0);
        }
    }

    // Calculate totals
    $totalAnnualTax = floatval($property['total_annual_tax'] ?? 0);
    $landAnnualTax = floatval($property['land_annual_tax'] ?? 0);
    if ($totalAnnualTax == 0) {
        $totalAnnualTax = $landAnnualTax + $totalBuildingAnnualTax;
    }

    $totalPaid = 0;
    foreach ($quarterlyTaxes as $tax) {
        if ($tax['payment_status'] === 'paid') {
            $totalPaid += floatval($tax['total_quarterly_tax'] ?? 0);
        }
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