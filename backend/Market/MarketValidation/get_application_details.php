<?php
// revenue2/backend/Market/MarketValidation/get_application_details.php

// Start output buffering
ob_start();

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple debug log
function debugLog($message) {
    $logFile = __DIR__ . '/details_debug.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

debugLog("=== REQUEST START ===");
debugLog("Query string: " . ($_SERVER['QUERY_STRING'] ?? 'none'));
debugLog("GET params: " . print_r($_GET, true));

// Check if ID parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Application ID is required',
        'received_params' => $_GET
    ]);
    debugLog("ERROR: No ID parameter");
    exit;
}

$application_id = intval($_GET['id']);
debugLog("Processing ID: $application_id");

try {
    // DIRECT DATABASE CONNECTION
    $host = 'localhost:3307';
    $dbname = 'market_rent';
    $username = 'root';
    $password = '';
    
    debugLog("Connecting to DB: $dbname on $host");
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    debugLog("Database connected successfully");
    
    // COMPREHENSIVE QUERY - Get ALL data in one go
    $query = "
        SELECT 
            -- Rental Registration data
            rr.id,
            rr.user_id,
            rr.stall_id,
            rr.map_id,
            rr.stall_name,
            rr.stall_class,
            rr.stall_rights_amount,
            rr.monthly_rent,
            rr.security_bond,
            rr.total_amount_due,
            rr.barangay_clearance,
            rr.id_photo_2x2,
            rr.valid_id,
            rr.interview_date,
            rr.interviewer,
            rr.interview_notes,
            rr.payment_date,
            rr.reference_number,
            rr.application_status,
            rr.stall_rights_no,
            rr.remarks,
            rr.correction_notes,
            rr.created_at,
            rr.updated_at,
            
            -- Renter Owner data
            ro.id AS renter_id,
            ro.registration_id,
            ro.user_id AS renter_user_id,
            ro.first_name,
            ro.middle_name,
            ro.last_name,
            ro.suffix,
            ro.email,
            ro.mobile,
            ro.telephone,
            ro.house_number,
            ro.street,
            ro.barangay,
            ro.city,
            ro.province,
            ro.zip_code,
            ro.birth_date,
            ro.gender,
            ro.emergency_name,
            ro.emergency_contact,
            ro.renter_code,
            ro.status AS renter_status,
            ro.created_at AS renter_created_at,
            ro.updated_at AS renter_updated_at,
            
            -- Rent Stall data
            rs.id AS rent_stall_id,
            rs.renter_id AS rs_renter_id,
            rs.business_name,
            rs.business_type,
            rs.stall_rights_no AS rs_stall_rights_no,
            rs.start_date,
            rs.end_date,
            rs.monthly_rent AS rs_monthly_rent,
            rs.status AS rs_status,
            rs.created_at AS rs_created_at,
            rs.updated_at AS rs_updated_at,
            
            -- Stall data
            s.name AS stall_location_name,
            s.pos_x,
            s.pos_y,
            s.height,
            s.length,
            s.width,
            s.pixel_width,
            s.pixel_height,
            s.price AS stall_price,
            s.status AS stall_status,
            s.class_id,
            s.created_at AS stall_created_at,
            s.updated_at AS stall_updated_at,
            
            -- Stall Rights data
            sr.class_name AS stall_class_name,
            sr.price AS stall_class_price,
            sr.description AS stall_class_description
            
        FROM rental_registration rr
        
        -- Join Renter Owner
        LEFT JOIN renter_owner ro ON rr.id = ro.registration_id
        
        -- Join Rent Stall
        LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
        
        -- Join Stall
        LEFT JOIN stalls s ON rr.stall_id = s.id
        
        -- Join Stall Rights
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        
        WHERE rr.id = :id
    ";
    
    debugLog("Executing comprehensive query for ID: $application_id");
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':id', $application_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        debugLog("No application found with ID: $application_id");
        
        // Let's check what IDs are available
        $checkStmt = $pdo->query("SELECT id, stall_rights_no FROM rental_registration ORDER BY id");
        $availableApps = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Application not found',
            'requested_id' => $application_id,
            'available_applications' => $availableApps
        ]);
        exit;
    }
    
    debugLog("Application found. Stall Rights No: " . ($application['stall_rights_no'] ?? 'N/A'));
    debugLog("Application ID in result: " . ($application['id'] ?? 'N/A'));
    
    // Also get maps data if map_id exists
    if ($application['map_id']) {
        $mapStmt = $pdo->prepare("SELECT * FROM maps WHERE id = :map_id");
        $mapStmt->execute([':map_id' => $application['map_id']]);
        $map = $mapStmt->fetch(PDO::FETCH_ASSOC);
        if ($map) {
            $application['map_details'] = $map;
        }
    }
    
    // Calculate derived fields
    $application['full_name'] = trim(
        ($application['first_name'] ?? '') . ' ' . 
        (!empty($application['middle_name']) ? $application['middle_name'] . ' ' : '') . 
        ($application['last_name'] ?? '') . 
        (!empty($application['suffix']) ? ' ' . $application['suffix'] : '')
    );
    
    // Format address
    $addressParts = [];
    if (!empty($application['house_number'])) $addressParts[] = $application['house_number'];
    if (!empty($application['street'])) $addressParts[] = $application['street'];
    if (!empty($application['barangay'])) $addressParts[] = $application['barangay'];
    if (!empty($application['city'])) $addressParts[] = $application['city'];
    if (!empty($application['province'])) $addressParts[] = $application['province'];
    if (!empty($application['zip_code'])) $addressParts[] = $application['zip_code'];
    
    $application['full_address'] = implode(', ', $addressParts);
    
    // Status mapping
    $statusMap = [
        'pending' => 'Pending Interview',
        'interviewed' => 'Interview Completed',
        'paying' => 'Payment Required',
        'paid' => 'Payment Completed',
        'need_correction' => 'Needs Correction',
        'resubmitted' => 'Resubmitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected'
    ];
    
    $application['status_display'] = $statusMap[$application['application_status']] ?? $application['application_status'];
    
    // Format currency
    $application['monthly_rent_formatted'] = '₱' . number_format($application['monthly_rent'] ?? 0, 2);
    $application['stall_rights_amount_formatted'] = '₱' . number_format($application['stall_rights_amount'] ?? 0, 2);
    $application['security_bond_formatted'] = '₱' . number_format($application['security_bond'] ?? 0, 2);
    $application['total_amount_due_formatted'] = '₱' . number_format($application['total_amount_due'] ?? 0, 2);
    
    debugLog("Successfully prepared data for ID: $application_id");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Application retrieved successfully',
        'data' => $application,
        'count' => 1
    ]);
    
} catch(PDOException $e) {
    debugLog("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} catch(Exception $e) {
    debugLog("General error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred',
        'error' => $e->getMessage()
    ]);
}

debugLog("=== REQUEST END ===\n");
ob_end_flush();
?>