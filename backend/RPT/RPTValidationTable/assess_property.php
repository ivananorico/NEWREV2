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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['registration_id'])) {
        throw new Exception("Registration ID is required");
    }

    $registration_id = $input['registration_id'];

    // Validate required land assessment data
    if (!isset($input['land_property_type']) || 
        !isset($input['land_area_sqm']) || 
        !isset($input['land_assessed_value']) || 
        !isset($input['land_assessment_level'])) {
        throw new Exception("Missing required land assessment data");
    }

    // Get tax config IDs and percentages
    $tax_stmt = $pdo->prepare("SELECT id, tax_name, tax_percent FROM rpt_tax_config WHERE status = 'active'");
    $tax_stmt->execute();
    $tax_configs = $tax_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $basic_tax_id = null;
    $sef_tax_id = null;
    $basic_tax_percent = 0;
    $sef_tax_percent = 0;
    
    foreach ($tax_configs as $config) {
        if ($config['tax_name'] === 'Basic Tax') {
            $basic_tax_id = $config['id'];
            $basic_tax_percent = floatval($config['tax_percent']);
        } elseif ($config['tax_name'] === 'SEF Tax') {
            $sef_tax_id = $config['id'];
            $sef_tax_percent = floatval($config['tax_percent']);
        }
    }

    // Get inspection_id
    $inspection_stmt = $pdo->prepare("SELECT id FROM property_inspections WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
    $inspection_stmt->execute([$registration_id]);
    $inspection_id = $inspection_stmt->fetch(PDO::FETCH_COLUMN);

    // Get land_config_id
    $land_config_stmt = $pdo->prepare("SELECT id, market_value, assessment_level FROM land_configurations WHERE classification = ? AND status = 'active' LIMIT 1");
    $land_config_stmt->execute([$input['land_property_type']]);
    $land_config = $land_config_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$land_config) {
        throw new Exception("No active land configuration found for classification: " . $input['land_property_type']);
    }
    
    $land_config_id = $land_config['id'];
    $land_market_value_per_sqm = floatval($land_config['market_value']);
    $land_assessment_level = floatval($land_config['assessment_level']);

    // Calculate land values (if not provided in input)
    $land_area_sqm = floatval($input['land_area_sqm']);
    $land_market_value = $land_area_sqm * $land_market_value_per_sqm;
    $land_assessed_value = $land_market_value * ($land_assessment_level / 100);

    // Use provided values if they exist (for updates)
    if (isset($input['land_market_value']) && $input['land_market_value'] > 0) {
        $land_market_value = floatval($input['land_market_value']);
    }
    if (isset($input['land_assessed_value']) && $input['land_assessed_value'] > 0) {
        $land_assessed_value = floatval($input['land_assessed_value']);
    }

    // Calculate land taxes based on actual tax percentages
    $total_tax_rate = ($basic_tax_percent + $sef_tax_percent) / 100;
    $land_annual_tax = $land_assessed_value * $total_tax_rate;
    
    // Distribute taxes proportionally
    $total_tax_percent = $basic_tax_percent + $sef_tax_percent;
    $land_basic_tax = $land_annual_tax * ($basic_tax_percent / $total_tax_percent);
    $land_sef_tax = $land_annual_tax * ($sef_tax_percent / $total_tax_percent);

    // Check if land assessment already exists for this registration
    $check_land_stmt = $pdo->prepare("SELECT id FROM land_properties WHERE registration_id = ?");
    $check_land_stmt->execute([$registration_id]);
    $existing_land_id = $check_land_stmt->fetch(PDO::FETCH_COLUMN);

    if ($existing_land_id) {
        // UPDATE existing land assessment
        $land_query = "
            UPDATE land_properties SET
            property_type = ?,
            land_config_id = ?,
            land_area_sqm = ?,
            land_market_value = ?,
            land_assessed_value = ?,
            assessment_level = ?,
            basic_tax_config_id = ?,
            sef_tax_config_id = ?,
            basic_tax_amount = ?,
            sef_tax_amount = ?,
            annual_tax = ?
            WHERE registration_id = ?
        ";
        
        $land_stmt = $pdo->prepare($land_query);
        $land_stmt->execute([
            $input['land_property_type'], 
            $land_config_id,
            $land_area_sqm, 
            $land_market_value, 
            $land_assessed_value, 
            $land_assessment_level,
            $basic_tax_id, 
            $sef_tax_id, 
            $land_basic_tax, 
            $land_sef_tax, 
            $land_annual_tax,
            $registration_id
        ]);
        
        $land_id = $existing_land_id;
        $land_action = 'updated';
    } else {
        // INSERT new land assessment
        $land_query = "
            INSERT INTO land_properties 
            (registration_id, inspection_id, property_type, land_config_id, 
             land_area_sqm, land_market_value, land_assessed_value, assessment_level,
             basic_tax_config_id, sef_tax_config_id, basic_tax_amount, sef_tax_amount, annual_tax)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $land_stmt = $pdo->prepare($land_query);
        $land_stmt->execute([
            $registration_id, 
            $inspection_id, 
            $input['land_property_type'], 
            $land_config_id,
            $land_area_sqm, 
            $land_market_value, 
            $land_assessed_value, 
            $land_assessment_level,
            $basic_tax_id, 
            $sef_tax_id, 
            $land_basic_tax, 
            $land_sef_tax, 
            $land_annual_tax
        ]);
        
        $land_id = $pdo->lastInsertId();
        $land_action = 'inserted';
    }

    // Handle building assessment if property has building
    $building_action = 'none';
    $building_annual_tax = 0;
    
    if (isset($input['construction_type']) && isset($input['floor_area_sqm']) && $input['floor_area_sqm'] > 0) {
        // Get property_config_id - MUST match both material_type AND classification
        $prop_config_stmt = $pdo->prepare("
            SELECT id, unit_cost, depreciation_rate 
            FROM property_configurations 
            WHERE material_type = ? 
            AND classification = ? 
            AND status = 'active' 
            LIMIT 1
        ");
        $prop_config_stmt->execute([
            $input['construction_type'], 
            $input['land_property_type'] // This is the classification
        ]);
        $property_config = $prop_config_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$property_config) {
            throw new Exception("No active property configuration found for construction type '{$input['construction_type']}' in '{$input['land_property_type']}' classification");
        }
        
        $property_config_id = $property_config['id'];
        $unit_cost = floatval($property_config['unit_cost']);
        $depreciation_rate = floatval($property_config['depreciation_rate']);

        // Calculate building values
        $floor_area_sqm = floatval($input['floor_area_sqm']);
        $year_built = isset($input['year_built']) ? intval($input['year_built']) : date('Y');
        $current_year = date('Y');
        $building_age = $current_year - $year_built;
        
        // Market value calculation
        $building_market_value = $floor_area_sqm * $unit_cost;
        
        // Depreciation calculation
        $depreciation_percent = min(100, $building_age * $depreciation_rate);
        $building_depreciated_value = $building_market_value * ((100 - $depreciation_percent) / 100);

        // Get building assessment level from building_assessment_levels table
        $assessment_level_stmt = $pdo->prepare("
            SELECT level_percent 
            FROM building_assessment_levels 
            WHERE classification = ? 
            AND ? BETWEEN min_assessed_value AND max_assessed_value
            AND status = 'active' 
            LIMIT 1
        ");
        $assessment_level_stmt->execute([
            $input['land_property_type'], // Classification
            $building_depreciated_value    // Depreciated value to check range
        ]);
        
        $building_assessment_level = 0;
        $assessment_data = $assessment_level_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assessment_data) {
            $building_assessment_level = floatval($assessment_data['level_percent']);
        } else {
            // No matching assessment level found - check if we should proceed
            if (!isset($input['force_save']) || $input['force_save'] !== true) {
                // Get the range for better error message
                $range_stmt = $pdo->prepare("
                    SELECT MIN(min_assessed_value) as min_range, MAX(max_assessed_value) as max_range
                    FROM building_assessment_levels 
                    WHERE classification = ? 
                    AND status = 'active'
                ");
                $range_stmt->execute([$input['land_property_type']]);
                $range_data = $range_stmt->fetch(PDO::FETCH_ASSOC);
                
                $min_range = $range_data['min_range'] ?? 0;
                $max_range = $range_data['max_range'] ?? 0;
                
                throw new Exception(
                    "Building depreciated value (₱" . number_format($building_depreciated_value, 2) . 
                    ") is outside configured assessed value ranges for {$input['land_property_type']} classification " .
                    "(₱" . number_format($min_range, 2) . " - ₱" . number_format($max_range, 2) . "). " .
                    "Assessment level and assessed value cannot be calculated."
                );
            }
        }

        // Calculate building assessed value (only if assessment level is available)
        $building_assessed_value = 0;
        if ($building_assessment_level > 0) {
            $building_assessed_value = $building_depreciated_value * ($building_assessment_level / 100);
        }

        // Use provided values if they exist (for updates or forced saves)
        if (isset($input['building_market_value']) && $input['building_market_value'] > 0) {
            $building_market_value = floatval($input['building_market_value']);
        }
        if (isset($input['building_depreciated_value']) && $input['building_depreciated_value'] > 0) {
            $building_depreciated_value = floatval($input['building_depreciated_value']);
        }
        if (isset($input['depreciation_percent']) && $input['depreciation_percent'] > 0) {
            $depreciation_percent = floatval($input['depreciation_percent']);
        }
        if (isset($input['building_assessed_value']) && $input['building_assessed_value'] > 0) {
            $building_assessed_value = floatval($input['building_assessed_value']);
        }
        if (isset($input['building_assessment_level']) && $input['building_assessment_level'] > 0) {
            $building_assessment_level = floatval($input['building_assessment_level']);
        }

        // Calculate building taxes
        $building_annual_tax = $building_assessed_value * $total_tax_rate;
        $building_basic_tax = $building_annual_tax * ($basic_tax_percent / $total_tax_percent);
        $building_sef_tax = $building_annual_tax * ($sef_tax_percent / $total_tax_percent);

        // Check if building assessment already exists for this land
        $check_building_stmt = $pdo->prepare("SELECT id FROM building_properties WHERE land_id = ?");
        $check_building_stmt->execute([$land_id]);
        $existing_building_id = $check_building_stmt->fetch(PDO::FETCH_COLUMN);

        if ($existing_building_id) {
            // UPDATE existing building assessment
            $building_query = "
                UPDATE building_properties SET
                construction_type = ?,
                property_config_id = ?,
                floor_area_sqm = ?,
                year_built = ?,
                building_market_value = ?,
                building_depreciated_value = ?,
                depreciation_percent = ?,
                building_assessed_value = ?,
                assessment_level = ?,
                basic_tax_config_id = ?,
                sef_tax_config_id = ?,
                basic_tax_amount = ?,
                sef_tax_amount = ?,
                annual_tax = ?
                WHERE land_id = ?
            ";
            
            $building_stmt = $pdo->prepare($building_query);
            $building_stmt->execute([
                $input['construction_type'], 
                $property_config_id,
                $floor_area_sqm, 
                $year_built, 
                $building_market_value, 
                $building_depreciated_value,
                $depreciation_percent, 
                $building_assessed_value, 
                $building_assessment_level,
                $basic_tax_id, 
                $sef_tax_id, 
                $building_basic_tax, 
                $building_sef_tax, 
                $building_annual_tax,
                $land_id
            ]);
            
            $building_action = 'updated';
        } else {
            // INSERT new building assessment
            $building_query = "
                INSERT INTO building_properties 
                (land_id, inspection_id, construction_type, property_config_id,
                 floor_area_sqm, year_built, building_market_value, building_depreciated_value,
                 depreciation_percent, building_assessed_value, assessment_level,
                 basic_tax_config_id, sef_tax_config_id, basic_tax_amount, sef_tax_amount, annual_tax)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $building_stmt = $pdo->prepare($building_query);
            $building_stmt->execute([
                $land_id, 
                $inspection_id, 
                $input['construction_type'], 
                $property_config_id,
                $floor_area_sqm, 
                $year_built, 
                $building_market_value, 
                $building_depreciated_value,
                $depreciation_percent, 
                $building_assessed_value, 
                $building_assessment_level,
                $basic_tax_id, 
                $sef_tax_id, 
                $building_basic_tax, 
                $building_sef_tax, 
                $building_annual_tax
            ]);
            
            $building_action = 'inserted';
        }
    }

    // Handle property_totals - UPDATE if exists, INSERT if not
    $total_annual_tax = $land_annual_tax + $building_annual_tax;
    
    $check_totals_stmt = $pdo->prepare("SELECT id FROM property_totals WHERE registration_id = ?");
    $check_totals_stmt->execute([$registration_id]);
    $existing_totals_id = $check_totals_stmt->fetch(PDO::FETCH_COLUMN);

    if ($existing_totals_id) {
        // UPDATE existing totals
        $totals_query = "
            UPDATE property_totals SET
            land_annual_tax = ?,
            total_building_annual_tax = ?,
            total_annual_tax = ?,
            status = 'active'
            WHERE registration_id = ?
        ";
        
        $totals_stmt = $pdo->prepare($totals_query);
        $totals_stmt->execute([
            $land_annual_tax,
            $building_annual_tax,
            $total_annual_tax,
            $registration_id
        ]);
    } else {
        // INSERT new totals
        $totals_query = "
            INSERT INTO property_totals 
            (registration_id, land_id, land_annual_tax, total_building_annual_tax, total_annual_tax, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ";
        
        $totals_stmt = $pdo->prepare($totals_query);
        $totals_stmt->execute([
            $registration_id, 
            $land_id,
            $land_annual_tax,
            $building_annual_tax,
            $total_annual_tax
        ]);
    }

    // Update registration status to assessed (only if not already approved)
    $status_stmt = $pdo->prepare("UPDATE property_registrations SET status = 'assessed' WHERE id = ? AND status != 'approved'");
    $status_stmt->execute([$registration_id]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Property assessment saved successfully',
        'action' => [
            'land' => $land_action,
            'building' => $building_action !== 'none' ? $building_action : 'none'
        ],
        'calculations' => [
            'land' => [
                'market_value' => $land_market_value,
                'assessed_value' => $land_assessed_value,
                'assessment_level' => $land_assessment_level,
                'basic_tax' => $land_basic_tax,
                'sef_tax' => $land_sef_tax,
                'annual_tax' => $land_annual_tax
            ],
            'building' => [
                'market_value' => $building_market_value ?? 0,
                'depreciated_value' => $building_depreciated_value ?? 0,
                'depreciation_percent' => $depreciation_percent ?? 0,
                'assessed_value' => $building_assessed_value ?? 0,
                'assessment_level' => $building_assessment_level ?? 0,
                'basic_tax' => $building_basic_tax ?? 0,
                'sef_tax' => $building_sef_tax ?? 0,
                'annual_tax' => $building_annual_tax
            ],
            'tax_rates' => [
                'basic_tax_percent' => $basic_tax_percent,
                'sef_tax_percent' => $sef_tax_percent,
                'total_tax_rate' => $total_tax_rate * 100 // Convert to percentage
            ]
        ],
        'summary' => [
            'land_annual_tax' => $land_annual_tax,
            'building_annual_tax' => $building_annual_tax,
            'total_annual_tax' => $total_annual_tax
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>