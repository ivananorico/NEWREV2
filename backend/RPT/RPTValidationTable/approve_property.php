<?php
// revenue2/backend/RPT/RPTValidationTable/approve_property.php
// ================================================
// PROPERTY APPROVAL API (with Quarterly & Annual Payment Generation)
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

// Create database connection using the config from rpt_db.php
function createPDOConnection() {
    $config = getDatabaseConfig();
    
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        return $pdo;
        
    } catch (PDOException $e) {
        // Log error but don't expose details to user
        error_log("Database connection failed: " . $e->getMessage());
        
        // Return user-friendly error
        return [
            'error' => true,
            'message' => 'Database connection failed. Please try again later.',
            'debug' => ($_SERVER['HTTP_HOST'] !== 'revenuetreasury.goserveph.com') ? $e->getMessage() : null
        ];
    }
}

// Try to include DB connection with proper error handling
$dbPath = dirname(__DIR__, 3) . '/db/RPT/rpt_db.php'; // Adjusted path - same as property_config.php

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Database config file not found at: " . $dbPath]);
    exit();
}

require_once $dbPath;

// Get PDO connection
$pdo = createPDOConnection();
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
        approveProperty($pdo);
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

// Function to generate unique reference number
function generateReferenceNumber($prefix = 'APAY') {
    $date = date('Ymd');
    $random = mt_rand(1000, 9999);
    return $prefix . '-' . $date . '-' . $random;
}

// Function to get active discount percentage
function getActiveDiscountPercent($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT discount_percent 
            FROM discount_configurations 
            WHERE status = 'active' 
            AND effective_date <= CURDATE() 
            AND (expiration_date IS NULL OR expiration_date >= CURDATE())
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? floatval($result['discount_percent']) : 0.00;
    } catch (Exception $e) {
        error_log("Error getting discount: " . $e->getMessage());
        return 0.00;
    }
}

// Main approval function
function approveProperty($pdo) {
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
    $current_year = date('Y');

    try {
        // Get property registration details with owner info
        $registration_stmt = $pdo->prepare("
            SELECT pr.*, po.id as owner_id, po.user_id, po.status as owner_status 
            FROM property_registrations pr
            LEFT JOIN property_owners po ON pr.owner_id = po.id
            WHERE pr.id = ?
        ");
        $registration_stmt->execute([$registration_id]);
        $registration = $registration_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registration) {
            http_response_code(404);
            echo json_encode(["error" => "Registration not found"]);
            return;
        }

        // Check if property is already approved
        if ($registration['status'] === 'approved') {
            http_response_code(400);
            echo json_encode(["error" => "Property is already approved"]);
            return;
        }

        // Get existing property totals
        $totals_stmt = $pdo->prepare("
            SELECT pt.*, lp.id as land_id, lp.tdn as land_tdn
            FROM property_totals pt
            LEFT JOIN land_properties lp ON pt.land_id = lp.id
            WHERE pt.registration_id = ? AND pt.status = 'active'
            LIMIT 1
        ");
        $totals_stmt->execute([$registration_id]);
        $property_totals = $totals_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$property_totals) {
            http_response_code(400);
            echo json_encode(["error" => "No assessment totals found. Please save assessment first"]);
            return;
        }

        // Start transaction
        $pdo->beginTransaction();

        try {
            // 1. Update property_owners status to 'active'
            $update_owner_stmt = $pdo->prepare("UPDATE property_owners SET status = 'active' WHERE id = ?");
            $update_owner_stmt->execute([$registration['owner_id']]);

            // 2. Generate TDNs
            $current_year = date('Y');
            $land_tdn = "TDN-L-{$current_year}-" . str_pad($registration_id, 4, '0', STR_PAD_LEFT);
            
            $building_tdn = null;
            if ($property_totals['total_building_annual_tax'] > 0) {
                $building_tdn = "TDN-B-{$current_year}-" . str_pad($registration_id, 4, '0', STR_PAD_LEFT);
            }

            // 3. Update TDNs in land_properties and building_properties
            $update_land_tdn = $pdo->prepare("UPDATE land_properties SET tdn = ? WHERE id = ?");
            $update_land_tdn->execute([$land_tdn, $property_totals['land_id']]);

            if ($building_tdn) {
                $update_building_tdn = $pdo->prepare("UPDATE building_properties SET tdn = ? WHERE land_id = ?");
                $update_building_tdn->execute([$building_tdn, $property_totals['land_id']]);
            }

            // 4. Generate quarterly taxes for current year
            $quarters = [
                ['Q1', $current_year . '-03-31'],
                ['Q2', $current_year . '-06-30'], 
                ['Q3', $current_year . '-09-30'],
                ['Q4', $current_year . '-12-31']
            ];

            $quarterly_tax = $property_totals['total_annual_tax'] / 4;

            foreach ($quarters as $quarter) {
                // Check if quarterly tax already exists
                $check_quarterly = $pdo->prepare("
                    SELECT id FROM quarterly_taxes 
                    WHERE property_total_id = ? AND quarter = ? AND year = ?
                ");
                $check_quarterly->execute([
                    $property_totals['id'],
                    $quarter[0],
                    $current_year
                ]);
                
                if (!$check_quarterly->fetch()) {
                    // Insert only if doesn't exist
                    $quarterly_query = "
                        INSERT INTO quarterly_taxes 
                        (property_total_id, quarter, year, due_date, total_quarterly_tax, payment_status)
                        VALUES (?, ?, ?, ?, ?, 'pending')
                    ";
                    
                    $quarterly_stmt = $pdo->prepare($quarterly_query);
                    $quarterly_stmt->execute([
                        $property_totals['id'],
                        $quarter[0],
                        $current_year,
                        $quarter[1],
                        $quarterly_tax
                    ]);
                }
            }

            // 5. GENERATE ANNUAL PAYMENT RECORD
            $total_amount = $property_totals['total_annual_tax'];
            
            // Get active discount percentage
            $discount_percent = getActiveDiscountPercent($pdo);
            $discount_amount = $total_amount * ($discount_percent / 100);
            $final_amount = $total_amount - $discount_amount;
            
            // Generate reference and receipt numbers
            $reference_number = generateReferenceNumber();
            $receipt_number = 'RCPT-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
            
            // Check if annual payment already exists for this year
            $check_annual_stmt = $pdo->prepare("
                SELECT id FROM annual_payments 
                WHERE property_total_id = ? AND payment_year = ? AND status = 'active'
            ");
            $check_annual_stmt->execute([$property_totals['id'], $current_year]);
            
            $annual_payment_id = null;
            if (!$check_annual_stmt->fetch()) {
                // Insert new annual payment record
                $annual_query = "
                    INSERT INTO annual_payments 
                    (property_total_id, user_id, payment_year, reference_number, total_amount, 
                     discount_percent, discount_amount, final_amount, payment_status, 
                     receipt_number, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'active')
                ";
                
                $annual_stmt = $pdo->prepare($annual_query);
                $annual_stmt->execute([
                    $property_totals['id'],
                    $registration['user_id'] ?? null, // user_id from property_owners
                    $current_year,
                    $reference_number,
                    $total_amount,
                    $discount_percent,
                    $discount_amount,
                    $final_amount,
                    $receipt_number
                ]);
                
                $annual_payment_id = $pdo->lastInsertId();
            }

            // 6. Update registration status to approved
            $status_stmt = $pdo->prepare("UPDATE property_registrations SET status = 'approved' WHERE id = ?");
            $status_stmt->execute([$registration_id]);

            // 7. Also update any inspection status to completed
            $inspection_stmt = $pdo->prepare("
                UPDATE property_inspections 
                SET status = 'completed' 
                WHERE registration_id = ? AND status IN ('scheduled', 'completed')
            ");
            $inspection_stmt->execute([$registration_id]);

            // 8. Update land_properties status to active (if not already)
            $update_land_status = $pdo->prepare("UPDATE land_properties SET status = 'active' WHERE id = ?");
            $update_land_status->execute([$property_totals['land_id']]);

            // 9. Update building_properties status to active (if exists)
            if ($building_tdn) {
                $update_building_status = $pdo->prepare("
                    UPDATE building_properties SET status = 'active' 
                    WHERE land_id = ? AND status = 'active'
                ");
                $update_building_status->execute([$property_totals['land_id']]);
            }

            $pdo->commit();

            // Prepare response data
            $response_data = [
                'owner_updated' => true,
                'owner_id' => $registration['owner_id'],
                'owner_new_status' => 'active',
                'tdns' => [
                    'land_tdn' => $land_tdn,
                    'building_tdn' => $building_tdn
                ],
                'totals' => [
                    'land_annual_tax' => $property_totals['land_annual_tax'],
                    'building_annual_tax' => $property_totals['total_building_annual_tax'],
                    'total_annual_tax' => $property_totals['total_annual_tax']
                ],
                'quarterly_tax' => $quarterly_tax,
                'registration_status' => 'approved'
            ];

            // Add annual payment info if created
            if ($annual_payment_id) {
                $response_data['annual_payment'] = [
                    'id' => $annual_payment_id,
                    'reference_number' => $reference_number,
                    'receipt_number' => $receipt_number,
                    'total_amount' => $total_amount,
                    'discount_percent' => $discount_percent,
                    'discount_amount' => $discount_amount,
                    'final_amount' => $final_amount,
                    'payment_year' => $current_year
                ];
            }

            echo json_encode([
                "success" => true,
                "message" => "Property approved successfully. Quarterly taxes and annual payment record generated.",
                "data" => $response_data
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(["error" => "Transaction failed: " . $e->getMessage()]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}
?>