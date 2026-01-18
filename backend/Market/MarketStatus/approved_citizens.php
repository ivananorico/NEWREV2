<?php
// approved_citizens.php
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
    // SQL query to get approved citizens WITH totals
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
            ro.status,
            ro.created_at as renter_created,
            rr.stall_rights_no,
            rr.stall_name,
            rr.monthly_rent,
            rr.created_at as registration_date,
            rs.business_name,
            rs.business_type,
            rs.start_date,
            rs.end_date,
            rt.id as rent_total_id,
            rt.monthly_rent as contract_monthly_rent,
            rt.monthly_totals,
            rt.start_date as contract_start,
            rt.end_date as contract_end,
            rt.status as contract_status,
            s.name as stall_name_detail,
            sr.class_name
        FROM renter_owner ro
        JOIN rental_registration rr ON ro.registration_id = rr.id
        LEFT JOIN rent_stall rs ON ro.registration_id = rs.registration_id
        LEFT JOIN rent_totals rt ON ro.registration_id = rt.registration_id AND rt.status = 'active'
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        WHERE rr.application_status = 'approved'
        ORDER BY ro.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $citizens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate overall totals
    $overall_totals = [
        'total_citizens' => count($citizens),
        'total_monthly_rent' => 0,
        'total_contract_value' => 0,
        'active_citizens' => 0,
        'total_business_types' => 0,
        'total_stall_classes' => 0
    ];
    
    // Format names and data
    foreach ($citizens as &$citizen) {
        // Build full name
        $citizen['full_name'] = trim($citizen['first_name'] . ' ' . 
                                ($citizen['middle_name'] ? $citizen['middle_name'] . ' ' : '') . 
                                $citizen['last_name'] . 
                                ($citizen['suffix'] ? ' ' . $citizen['suffix'] : ''));
        
        // Format dates
        $citizen['registration_date_formatted'] = $citizen['registration_date'] 
            ? date('M d, Y', strtotime($citizen['registration_date'])) 
            : 'N/A';
            
        $citizen['start_date_formatted'] = $citizen['start_date'] 
            ? date('M d, Y', strtotime($citizen['start_date'])) 
            : 'N/A';
            
        $citizen['contract_start_formatted'] = $citizen['contract_start'] 
            ? date('M d, Y', strtotime($citizen['contract_start'])) 
            : 'N/A';
            
        $citizen['contract_end_formatted'] = $citizen['contract_end'] 
            ? date('M d, Y', strtotime($citizen['contract_end'])) 
            : 'N/A';
        
        // Calculate contract months
        if ($citizen['contract_start'] && $citizen['contract_end']) {
            $start = new DateTime($citizen['contract_start']);
            $end = new DateTime($citizen['contract_end']);
            $interval = $start->diff($end);
            $months = ($interval->y * 12) + $interval->m;
            if ($interval->d > 0) $months += 1;
            $citizen['contract_months'] = $months;
        } else {
            $citizen['contract_months'] = 0;
        }
        
        // Add to overall totals
        $monthly_rent = floatval($citizen['monthly_rent'] ?? 0);
        $monthly_totals = floatval($citizen['monthly_totals'] ?? 0);
        
        $overall_totals['total_monthly_rent'] += $monthly_rent;
        $overall_totals['total_contract_value'] += $monthly_totals;
        
        if ($citizen['status'] === 'active') {
            $overall_totals['active_citizens']++;
        }
    }
    
    // Get unique business types and stall classes
    $business_types = array_filter(array_unique(array_column($citizens, 'business_type')));
    $stall_classes = array_filter(array_unique(array_column($citizens, 'class_name')));
    
    $overall_totals['total_business_types'] = count($business_types);
    $overall_totals['total_stall_classes'] = count($stall_classes);
    $overall_totals['average_monthly_rent'] = $overall_totals['total_citizens'] > 0 
        ? $overall_totals['total_monthly_rent'] / $overall_totals['total_citizens'] 
        : 0;
    $overall_totals['average_contract_value'] = $overall_totals['total_citizens'] > 0 
        ? $overall_totals['total_contract_value'] / $overall_totals['total_citizens'] 
        : 0;
    
    echo json_encode([
        'status' => 'success',
        'data' => $citizens,
        'totals' => $overall_totals,
        'count' => count($citizens)
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in approved_citizens.php: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>