<?php
// citizen_details.php
// Get detailed information for a specific citizen

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../db/Market/market_db.php';

// Get citizen ID from query parameter
$citizenId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$citizenId) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Citizen ID is required'
    ]);
    exit;
}

try {
    // Fixed SQL query based on your actual table structure
    $sql = "
        SELECT 
            ro.*,
            ro.first_name,
            ro.middle_name,
            ro.last_name,
            ro.suffix,
            ro.email,
            ro.mobile,
            ro.status,
            ro.created_at as renter_created,
            rr.stall_rights_no,
            rr.stall_name,
            rr.monthly_rent,
            rr.created_at as registration_date,
            rs.business_name,
            rs.business_type,
            rs.start_date,
            CONCAT(
                ro.first_name, ' ',
                IFNULL(CONCAT(ro.middle_name, ' '), ''),
                ro.last_name,
                IFNULL(CONCAT(' ', ro.suffix), '')
            ) as full_name
        FROM renter_owner ro
        JOIN rental_registration rr ON ro.registration_id = rr.id
        LEFT JOIN rent_stall rs ON ro.registration_id = rs.registration_id
        WHERE ro.id = :id AND rr.application_status = 'approved'
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $citizenId, PDO::PARAM_INT);
    $stmt->execute();
    
    $citizen = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($citizen) {
        // Format dates
        $citizen['registration_date_formatted'] = date('M d, Y', strtotime($citizen['registration_date']));
        
        if ($citizen['start_date']) {
            $citizen['start_date_formatted'] = date('M d, Y', strtotime($citizen['start_date']));
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $citizen
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Citizen not found or not approved'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'sql_error' => $e->errorInfo
    ]);
}
?>