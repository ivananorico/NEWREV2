<?php
// citizen_details.php
header('Content-Type: application/json');

// CORS headers
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

require_once '../../../db/Market/market_db.php';

try {
    // Get the ID parameter (could be renter_id or renter_code)
    $id = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['renter_code']) ? $_GET['renter_code'] : '');
    
    if (empty($id)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ID or Renter Code parameter is required'
        ]);
        exit;
    }
    
    // SQL query to get detailed citizen information
    $sql = "
        SELECT 
            ro.id,
            ro.renter_code,
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
            ro.status,
            ro.created_at as renter_created_at,
            ro.updated_at as renter_updated_at,
            
            rr.id as registration_id,
            rr.stall_rights_no,
            rr.stall_name,
            rr.stall_class,
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
            rr.remarks,
            rr.correction_notes,
            rr.created_at as registration_date,
            rr.updated_at as registration_updated,
            
            rs.id as rent_stall_id,
            rs.business_name,
            rs.business_type,
            rs.start_date as stall_start_date,
            rs.end_date as stall_end_date,
            rs.status as stall_status,
            
            rt.id as rent_total_id,
            rt.monthly_rent as contract_monthly_rent,
            rt.monthly_totals,
            rt.start_date as contract_start,
            rt.end_date as contract_end,
            rt.status as contract_status,
            
            s.id as stall_id,
            s.name as stall_name_detail,
            s.pos_x,
            s.pos_y,
            s.height,
            s.length,
            s.width,
            s.price,
            s.status as stall_availability_status,
            
            sr.class_id,
            sr.class_name,
            sr.price as stall_rights_price,
            sr.description as stall_class_description,
            
            CONCAT(ro.first_name, ' ', 
                  COALESCE(CONCAT(ro.middle_name, ' '), ''), 
                  ro.last_name, 
                  COALESCE(CONCAT(' ', ro.suffix), '')) as full_name,
            
            DATEDIFF(COALESCE(rt.end_date, rs.end_date), COALESCE(rt.start_date, rs.start_date)) / 30 as contract_months
            
        FROM renter_owner ro
        JOIN rental_registration rr ON ro.registration_id = rr.id
        LEFT JOIN rent_stall rs ON ro.registration_id = rs.registration_id
        LEFT JOIN rent_totals rt ON ro.registration_id = rt.registration_id
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        WHERE ro.id = :id OR ro.renter_code = :renter_code
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':renter_code', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $citizen = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get billing history
        $billingQuery = "
            SELECT 
                mrb.*,
                rt.monthly_totals,
                ro.renter_code
            FROM monthly_rent_billing mrb
            JOIN rent_totals rt ON mrb.rent_total_id = rt.id
            JOIN renter_owner ro ON rt.renter_id = ro.id
            WHERE ro.id = :renter_id OR ro.renter_code = :renter_code
            ORDER BY mrb.billing_year, mrb.billing_month
        ";
        
        $billingStmt = $pdo->prepare($billingQuery);
        $billingStmt->bindParam(':renter_id', $citizen['id']);
        $billingStmt->bindParam(':renter_code', $citizen['renter_code']);
        $billingStmt->execute();
        $billingHistory = $billingStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get payment logs
        $paymentQuery = "
            SELECT *
            FROM market_payment_logs 
            WHERE renter_code = :renter_code
            ORDER BY payment_date DESC
        ";
        
        $paymentStmt = $pdo->prepare($paymentQuery);
        $paymentStmt->bindParam(':renter_code', $citizen['renter_code']);
        $paymentStmt->execute();
        $paymentLogs = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "status" => "success",
            "citizen" => $citizen,
            "billing_history" => $billingHistory,
            "payment_logs" => $paymentLogs
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Citizen not found"
        ]);
    }
} catch(PDOException $e) {
    error_log('Database error in citizen_details.php: ' . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>