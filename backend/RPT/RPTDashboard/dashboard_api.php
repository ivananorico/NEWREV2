<?php
// Simple CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include DB config
$configPath = __DIR__ . '/../../../db/RPT/rpt_db.php';

if (!file_exists($configPath)) {
    echo json_encode(["success" => false, "error" => "Database config not found"]);
    exit();
}

require_once $configPath;

try {
    $pdo = getDatabaseConnection();
    
    if (is_array($pdo) && isset($pdo['error'])) {
        throw new Exception($pdo['message']);
    }
    
    // Get data based on your actual database tables
    $data = [
        'stats' => getBasicStats($pdo),
        'pending_items' => getPendingItems($pdo),
        'recent_payments' => getRecentPayments($pdo),
        'recent_registrations' => getRecentRegistrations($pdo)
    ];
    
    echo json_encode([
        "success" => true, 
        "data" => $data,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "error" => $e->getMessage()
    ]);
}

function getBasicStats($pdo) {
    $stats = [];
    
    // Property counts - from your database
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT pr.id) as total_properties,
            COUNT(DISTINCT lp.id) as total_lands,
            COUNT(DISTINCT bp.id) as total_buildings
        FROM property_registrations pr
        LEFT JOIN land_properties lp ON pr.id = lp.registration_id
        LEFT JOIN building_properties bp ON lp.id = bp.land_id
    ");
    $stats['properties'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // Revenue - from property_totals
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(total_annual_tax), 0) as total_annual_tax
        FROM property_totals
        WHERE status = 'active'
    ");
    $stats['revenue'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // Payments - from quarterly_taxes
    $currentYear = date('Y');
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_payments,
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN payment_status = 'overdue' THEN 1 END) as overdue_count
        FROM quarterly_taxes
        WHERE YEAR(due_date) = ?
    ");
    $stmt->execute([$currentYear]);
    $stats['payments'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // Owners - from property_owners
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_owners,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_owners,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_owners
        FROM property_owners
    ");
    $stats['owners'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // Barangays - from property_registrations
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT barangay) as total_barangays
        FROM property_registrations
        WHERE barangay IS NOT NULL
    ");
    $stats['barangay'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // Inspections - from property_inspections
    $stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
            COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled
        FROM property_inspections
    ");
    $stats['inspections'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // Collected this year
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_quarterly_tax), 0) as collected_this_year
        FROM quarterly_taxes
        WHERE payment_status = 'paid' AND YEAR(payment_date) = ?
    ");
    $stmt->execute([$currentYear]);
    $collected = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['revenue']['collected_this_year'] = $collected['collected_this_year'] ?? 0;
    
    return $stats;
}

function getPendingItems($pdo) {
    $pending = [];
    
    // Pending registrations
    $stmt = $pdo->query("
        SELECT 
            pr.reference_number,
            po.full_name as owner,
            pr.status,
            DATEDIFF(CURDATE(), pr.created_at) as days_pending
        FROM property_registrations pr
        LEFT JOIN property_owners po ON pr.owner_id = po.id
        WHERE pr.status IN ('pending', 'needs_correction')
        ORDER BY pr.created_at ASC
        LIMIT 5
    ");
    $pending['registrations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Overdue payments
    $stmt = $pdo->query("
        SELECT 
            pr.reference_number,
            po.full_name as owner,
            qt.quarter,
            qt.year,
            qt.total_quarterly_tax,
            DATEDIFF(CURDATE(), qt.due_date) as days_overdue
        FROM quarterly_taxes qt
        LEFT JOIN property_totals pt ON qt.property_total_id = pt.id
        LEFT JOIN land_properties lp ON pt.land_id = lp.id
        LEFT JOIN property_registrations pr ON lp.registration_id = pr.id
        LEFT JOIN property_owners po ON pr.owner_id = po.id
        WHERE qt.payment_status = 'overdue'
        ORDER BY qt.due_date ASC
        LIMIT 5
    ");
    $pending['overdue_payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Upcoming inspections
    $stmt = $pdo->query("
        SELECT 
            pr.reference_number,
            pi.assessor_name,
            DATE(pi.scheduled_date) as scheduled_date
        FROM property_inspections pi
        LEFT JOIN property_registrations pr ON pi.registration_id = pr.id
        WHERE pi.status = 'scheduled'
        AND pi.scheduled_date >= CURDATE()
        ORDER BY pi.scheduled_date ASC
        LIMIT 5
    ");
    $pending['inspections'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $pending;
}

function getRecentPayments($pdo) {
    $stmt = $pdo->query("
        SELECT 
            qt.receipt_number,
            pr.reference_number,
            po.full_name as owner,
            qt.quarter,
            qt.year,
            qt.total_quarterly_tax,
            DATE_FORMAT(qt.payment_date, '%b %d, %Y') as payment_date
        FROM quarterly_taxes qt
        LEFT JOIN property_totals pt ON qt.property_total_id = pt.id
        LEFT JOIN land_properties lp ON pt.land_id = lp.id
        LEFT JOIN property_registrations pr ON lp.registration_id = pr.id
        LEFT JOIN property_owners po ON pr.owner_id = po.id
        WHERE qt.payment_status = 'paid'
        ORDER BY qt.payment_date DESC
        LIMIT 10
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentRegistrations($pdo) {
    $stmt = $pdo->query("
        SELECT 
            pr.reference_number,
            po.full_name as owner,
            pr.barangay,
            pr.status,
            DATE_FORMAT(pr.created_at, '%b %d, %Y') as registered_date,
            COUNT(DISTINCT lp.id) as land_count,
            COUNT(DISTINCT bp.id) as building_count
        FROM property_registrations pr
        LEFT JOIN property_owners po ON pr.owner_id = po.id
        LEFT JOIN land_properties lp ON pr.id = lp.registration_id
        LEFT JOIN building_properties bp ON lp.id = bp.land_id AND bp.status = 'active'
        GROUP BY pr.id
        ORDER BY pr.created_at DESC
        LIMIT 10
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>