<?php
// ================================================
// PROPERTY ASSESSMENT API
// ================================================

// Enable CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Try to include DB connection with proper error handling
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php'; // Adjusted path

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Database config file not found at: " . $dbPath]);
    exit();
}

require_once $dbPath;

// Get PDO connection
$pdo = getDatabaseConnection();
if (!$pdo || (is_array($pdo) && isset($pdo['error']))) {
    http_response_code(500);
    $errorMsg = is_array($pdo) ? $pdo['message'] : "Failed to connect to database";
    echo json_encode(["error" => "Database connection failed: " . $errorMsg]);
    exit();
}

// Determine HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Route based on method
switch ($method) {
    case 'POST':
        assessProperty($pdo);
        break;
    case 'OPTIONS':
        http_response_code(200);
        exit();
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}

// ==========================
// FUNCTIONS
// ==========================

function assessProperty($pdo) {
    // Get input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data: " . json_last_error_msg()]);
        return;
    }

    // Validate required fields
    $requiredFields = ['registration_id'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }

    $registration_id = intval($data['registration_id']);

    // Validate required land assessment data
    if (!isset($data['land_property_type']) || 
        !isset($data['land_area_sqm']) || 
        !isset($data['land_assessed_value']) || 
        !isset($data['land_assessment_level'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required land assessment data"]);
        return;
    }

    try {
        // Get tax percentages from rpt_tax_config table
        $tax_stmt = $pdo->prepare("SELECT tax_name, tax_percent FROM rpt_tax_config WHERE status = 'active'");
        $tax_stmt->execute();
        $tax_configs = $tax_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $basic_tax_percent = 0;
        $sef_tax_percent = 0;
        
        foreach ($tax_configs as $config) {
            if ($config['tax_name'] === 'Basic Tax') {
                $basic_tax_percent = floatval($config['tax_percent']);
            } elseif ($config['tax_name'] === 'SEF Tax') {
                $sef_tax_percent = floatval($config['tax_percent']);
            }
        }

        // Get inspection_id
        $inspection_stmt = $pdo->prepare("SELECT id FROM property_inspections WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
        $inspection_stmt->execute([$registration_id]);
        $inspection_id = $inspection_stmt->fetch(PDO::FETCH_COLUMN);

        // Get land_config_id
        $land_config_stmt = $pdo->prepare("SELECT id, market_value, assessment_level FROM land_configurations WHERE classification = ? AND status = 'active' LIMIT 1");
        $land_config_stmt->execute([$data['land_property_type']]);
        $land_config = $land_config_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$land_config) {
            http_response_code(400);
            echo json_encode(["error" => "No active land configuration found for classification: " . $data['land_property_type']]);
            return;
        }
        
        $land_config_id = $land_config['id'];
        $land_market_value_per_sqm = floatval($land_config['market_value']);
        $land_assessment_level = floatval($land_config['assessment_level']);

        // Calculate land values (if not provided in input)
        $land_area_sqm = floatval($data['land_area_sqm']);
        $land_market_value = $land_area_sqm * $land_market_value_per_sqm;
        $land_assessed_value = $land_market_value * ($land_assessment_level / 100);

        // Use provided values if they exist (for updates)
        if (isset($data['land_market_value']) && $data['land_market_value'] > 0) {
            $land_market_value = floatval($data['land_market_value']);
        }
        if (isset($data['land_assessed_value']) && $data['land_assessed_value'] > 0) {
            $land_assessed_value = floatval($data['land_assessed_value']);
        }

        // Calculate land taxes based on actual tax percentages
        $total_tax_rate = ($basic_tax_percent + $sef_tax_percent) / 100;
        $land_annual_tax = $land_assessed_value * $total_tax_rate;
        
        // Distribute taxes proportionally
        $total_tax_percent = $basic_tax_percent + $sef_tax_percent;
        $land_basic_tax = $land_annual_tax * ($basic_tax_percent / $total_tax_percent);
        $land_sef_tax = $land_annual_tax * ($sef_tax_percent / $total_tax_percent);

        // Start transaction
        $pdo->beginTransaction();

        try {
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
                    basic_tax_amount = ?,
                    sef_tax_amount = ?,
                    annual_tax = ?,
                    updated_at = NOW()
                    WHERE registration_id = ?
                ";
                
                $land_stmt = $pdo->prepare($land_query);
                $land_stmt->execute([
                    $data['land_property_type'], 
                    $land_config_id,
                    $land_area_sqm, 
                    $land_market_value, 
                    $land_assessed_value, 
                    $land_assessment_level,
                    $land_basic_tax, 
                    $land_sef_tax, 
                    $land_annual_tax,
                    $registration_id
                ]);
                
                $land_id = $existing_land_id;
                $land_action = 'updated';
            } else {
                // Generate TDN for land
                $tdn = generateTDN($pdo, 'L');
                
                // INSERT new land assessment
                $land_query = "
                    INSERT INTO land_properties 
                    (registration_id, inspection_id, tdn, property_type, land_config_id, 
                     land_area_sqm, land_market_value, land_assessed_value, assessment_level,
                     basic_tax_amount, sef_tax_amount, 
                     annual_tax, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
                ";
                
                $land_stmt = $pdo->prepare($land_query);
                $land_stmt->execute([
                    $registration_id, 
                    $inspection_id, 
                    $tdn,
                    $data['land_property_type'], 
                    $land_config_id,
                    $land_area_sqm, 
                    $land_market_value, 
                    $land_assessed_value, 
                    $land_assessment_level,
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
            
            if (isset($data['construction_type']) && isset($data['floor_area_sqm']) && $data['floor_area_sqm'] > 0) {
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
                    $data['construction_type'], 
                    $data['land_property_type'] // This is the classification
                ]);
                $property_config = $prop_config_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$property_config) {
                    throw new Exception("No active property configuration found for construction type '{$data['construction_type']}' in '{$data['land_property_type']}' classification");
                }
                
                $property_config_id = $property_config['id'];
                $unit_cost = floatval($property_config['unit_cost']);
                $depreciation_rate = floatval($property_config['depreciation_rate']);

                // Calculate building values
                $floor_area_sqm = floatval($data['floor_area_sqm']);
                $year_built = isset($data['year_built']) ? intval($data['year_built']) : date('Y');
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
                    $data['land_property_type'], // Classification
                    $building_depreciated_value    // Depreciated value to check range
                ]);
                
                $building_assessment_level = 0;
                $assessment_data = $assessment_level_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($assessment_data) {
                    $building_assessment_level = floatval($assessment_data['level_percent']);
                } else {
                    // No matching assessment level found
                    $range_stmt = $pdo->prepare("
                        SELECT MIN(min_assessed_value) as min_range, MAX(max_assessed_value) as max_range
                        FROM building_assessment_levels 
                        WHERE classification = ? 
                        AND status = 'active'
                    ");
                    $range_stmt->execute([$data['land_property_type']]);
                    $range_data = $range_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $min_range = $range_data['min_range'] ?? 0;
                    $max_range = $range_data['max_range'] ?? 0;
                    
                    throw new Exception(
                        "Building depreciated value (₱" . number_format($building_depreciated_value, 2) . 
                        ") is outside configured assessed value ranges for {$data['land_property_type']} classification " .
                        "(₱" . number_format($min_range, 2) . " - ₱" . number_format($max_range, 2) . ")."
                    );
                }

                // Calculate building assessed value (only if assessment level is available)
                $building_assessed_value = 0;
                if ($building_assessment_level > 0) {
                    $building_assessed_value = $building_depreciated_value * ($building_assessment_level / 100);
                }

                // Use provided values if they exist (for updates or forced saves)
                if (isset($data['building_market_value']) && $data['building_market_value'] > 0) {
                    $building_market_value = floatval($data['building_market_value']);
                }
                if (isset($data['building_depreciated_value']) && $data['building_depreciated_value'] > 0) {
                    $building_depreciated_value = floatval($data['building_depreciated_value']);
                }
                if (isset($data['depreciation_percent']) && $data['depreciation_percent'] > 0) {
                    $depreciation_percent = floatval($data['depreciation_percent']);
                }
                if (isset($data['building_assessed_value']) && $data['building_assessed_value'] > 0) {
                    $building_assessed_value = floatval($data['building_assessed_value']);
                }
                if (isset($data['building_assessment_level']) && $data['building_assessment_level'] > 0) {
                    $building_assessment_level = floatval($data['building_assessment_level']);
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
                        basic_tax_amount = ?,
                        sef_tax_amount = ?,
                        annual_tax = ?,
                        updated_at = NOW()
                        WHERE land_id = ?
                    ";
                    
                    $building_stmt = $pdo->prepare($building_query);
                    $building_stmt->execute([
                        $data['construction_type'], 
                        $property_config_id,
                        $floor_area_sqm, 
                        $year_built, 
                        $building_market_value, 
                        $building_depreciated_value,
                        $depreciation_percent, 
                        $building_assessed_value, 
                        $building_assessment_level,
                        $building_basic_tax, 
                        $building_sef_tax, 
                        $building_annual_tax,
                        $land_id
                    ]);
                    
                    $building_action = 'updated';
                } else {
                    // Generate TDN for building
                    $building_tdn = generateTDN($pdo, 'B');
                    
                    // INSERT new building assessment
                    $building_query = "
                        INSERT INTO building_properties 
                        (land_id, inspection_id, tdn, construction_type, property_config_id,
                         floor_area_sqm, year_built, building_market_value, building_depreciated_value,
                         depreciation_percent, building_assessed_value, assessment_level,
                         basic_tax_amount, sef_tax_amount, 
                         annual_tax, status, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
                    ";
                    
                    $building_stmt = $pdo->prepare($building_query);
                    $building_stmt->execute([
                        $land_id, 
                        $inspection_id, 
                        $building_tdn,
                        $data['construction_type'], 
                        $property_config_id,
                        $floor_area_sqm, 
                        $year_built, 
                        $building_market_value, 
                        $building_depreciated_value,
                        $depreciation_percent, 
                        $building_assessed_value, 
                        $building_assessment_level,
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
                    status = 'active',
                    updated_at = NOW()
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
                    (registration_id, land_id, land_annual_tax, total_building_annual_tax, total_annual_tax, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
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

            $pdo->commit();

            echo json_encode([
                "success" => true,
                "message" => "Property assessment saved successfully",
                "data" => [
                    "action" => [
                        'land' => $land_action,
                        'building' => $building_action !== 'none' ? $building_action : 'none'
                    ],
                    "calculations" => [
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
                            'total_tax_rate' => $total_tax_rate * 100
                        ]
                    ],
                    "summary" => [
                        'land_annual_tax' => $land_annual_tax,
                        'building_annual_tax' => $building_annual_tax,
                        'total_annual_tax' => $total_annual_tax
                    ]
                ]
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(["error" => $e->getMessage()]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Assessment error: " . $e->getMessage()]);
    }
}

// Helper function to generate TDN (Tax Declaration Number)
function generateTDN($pdo, $type = 'L') {
    $current_year = date('Y');
    $prefix = "TDN-{$type}-{$current_year}-";
    
    // Get the last TDN for this type and year
    $stmt = $pdo->prepare("
        SELECT tdn 
        FROM " . ($type == 'L' ? 'land_properties' : 'building_properties') . " 
        WHERE tdn LIKE ? 
        ORDER BY tdn DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $last_tdn = $stmt->fetch(PDO::FETCH_COLUMN);
    
    if ($last_tdn) {
        // Extract the number part and increment
        $last_number = intval(substr($last_tdn, strlen($prefix)));
        $next_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $next_number = '0001';
    }
    
    return $prefix . $next_number;
}