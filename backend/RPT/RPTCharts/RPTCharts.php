<?php
// ================================================
// RPT CHARTS API - COMPLETE DATA
// ================================================

// Debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Path to DB config
$configPath = __DIR__ . '/../../../db/RPT/rpt_db.php';

if (!file_exists($configPath)) {
    echo json_encode(["success" => false, "error" => "DB config not found"]);
    exit();
}

require_once $configPath;
$pdo = getDatabaseConnection();

if (is_array($pdo) && isset($pdo['error'])) {
    echo json_encode(["success" => false, "error" => "DB connection failed"]);
    exit();
}

try {
    // Get ALL chart data at once
    $data = [
        'summary' => getSummaryData($pdo),
        'revenue_by_barangay' => getRevenueByBarangay($pdo),
        'revenue_by_property_type' => getRevenueByPropertyType($pdo),
        'revenue_by_district' => getRevenueByDistrict($pdo),
        'annual_revenue_trend' => getAnnualRevenueTrend($pdo),
        'quarterly_collection' => getQuarterlyCollection($pdo),
        'top_properties' => getTopProperties($pdo),
        'payment_status' => getPaymentStatus($pdo)
    ];
    
    echo json_encode(["success" => true, "data" => $data]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

// ==========================
// CHART FUNCTIONS - REAL DATA
// ==========================

function getSummaryData($pdo) {
    $year = date('Y');
    $summary = [];
    
    // Total Properties
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM property_registrations WHERE status = 'approved'");
    $stmt->execute();
    $result = $stmt->fetch();
    $summary['total_properties'] = intval($result['count']);
    
    // Active Buildings
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM building_properties WHERE status = 'active'");
    $stmt->execute();
    $result = $stmt->fetch();
    $summary['active_buildings'] = intval($result['count']);
    
    // Total Annual revenue2
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_annual_tax), 0) as total FROM property_totals");
    $stmt->execute();
    $result = $stmt->fetch();
    $summary['total_annual_revenue'] = floatval($result['total']);
    
    // This Year Collection
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END), 0) as collected,
            COALESCE(SUM(CASE WHEN payment_status IN ('pending', 'overdue') THEN total_quarterly_tax ELSE 0 END), 0) as pending,
            COUNT(CASE WHEN payment_status = 'overdue' THEN 1 END) as overdue_count
        FROM quarterly_taxes
        WHERE YEAR(due_date) = ?
    ");
    $stmt->execute([$year]);
    $result = $stmt->fetch();
    $summary['collected_this_year'] = floatval($result['collected']);
    $summary['pending_this_year'] = floatval($result['pending']);
    $summary['overdue_count'] = intval($result['overdue_count']);
    
    // Collection Rate
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid,
            COUNT(*) as total
        FROM quarterly_taxes
        WHERE YEAR(due_date) = ?
    ");
    $stmt->execute([$year]);
    $result = $stmt->fetch();
    $total = intval($result['total']);
    $paid = intval($result['paid']);
    $summary['collection_rate'] = $total > 0 ? round(($paid / $total) * 100, 1) : 0;
    
    // Average Property Tax
    $stmt = $pdo->prepare("SELECT COALESCE(AVG(total_annual_tax), 0) as avg_tax FROM property_totals");
    $stmt->execute();
    $result = $stmt->fetch();
    $summary['avg_property_tax'] = floatval($result['avg_tax']);
    
    return $summary;
}

function getRevenueByBarangay($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            pr.barangay,
            COUNT(DISTINCT pr.id) as property_count,
            COALESCE(SUM(pt.total_annual_tax), 0) as total_revenue,
            COALESCE(AVG(pt.total_annual_tax), 0) as avg_revenue
        FROM property_registrations pr
        LEFT JOIN land_properties lp ON pr.id = lp.registration_id
        LEFT JOIN property_totals pt ON lp.id = pt.land_id
        WHERE pr.status = 'approved'
        AND pr.barangay IS NOT NULL
        AND pr.barangay != ''
        GROUP BY pr.barangay
        HAVING total_revenue > 0
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRevenueByPropertyType($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            lp.property_type,
            COUNT(DISTINCT lp.id) as property_count,
            COALESCE(SUM(pt.total_annual_tax), 0) as total_revenue,
            COALESCE(AVG(pt.total_annual_tax), 0) as avg_revenue
        FROM land_properties lp
        LEFT JOIN property_totals pt ON lp.id = pt.land_id
        LEFT JOIN property_registrations pr ON lp.registration_id = pr.id
        WHERE pr.status = 'approved'
        AND lp.property_type IS NOT NULL
        GROUP BY lp.property_type
        ORDER BY total_revenue DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRevenueByDistrict($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            pr.district,
            COUNT(DISTINCT pr.id) as property_count,
            COALESCE(SUM(pt.total_annual_tax), 0) as total_revenue,
            COALESCE(AVG(pt.total_annual_tax), 0) as avg_revenue
        FROM property_registrations pr
        LEFT JOIN land_properties lp ON pr.id = lp.registration_id
        LEFT JOIN property_totals pt ON lp.id = pt.land_id
        WHERE pr.status = 'approved'
        AND pr.district IS NOT NULL
        AND pr.district != ''
        GROUP BY pr.district
        ORDER BY total_revenue DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAnnualRevenueTrend($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            YEAR(qt.due_date) as year,
            COALESCE(SUM(qt.total_quarterly_tax), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN qt.payment_status = 'paid' THEN qt.total_quarterly_tax ELSE 0 END), 0) as collected,
            COUNT(DISTINCT qt.property_total_id) as property_count
        FROM quarterly_taxes qt
        WHERE qt.due_date IS NOT NULL
        GROUP BY YEAR(qt.due_date)
        ORDER BY year DESC
        LIMIT 5
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getQuarterlyCollection($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("
        SELECT 
            qt.quarter,
            COALESCE(SUM(qt.total_quarterly_tax), 0) as total_tax,
            COALESCE(SUM(CASE WHEN qt.payment_status = 'paid' THEN qt.total_quarterly_tax ELSE 0 END), 0) as collected,
            COUNT(CASE WHEN qt.payment_status = 'paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN qt.payment_status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN qt.payment_status = 'overdue' THEN 1 END) as overdue_count
        FROM quarterly_taxes qt
        WHERE YEAR(qt.due_date) = ?
        GROUP BY qt.quarter
        ORDER BY FIELD(qt.quarter, 'Q1', 'Q2', 'Q3', 'Q4')
    ");
    $stmt->execute([$year]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure all quarters
    $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
    $quarterData = [];
    
    foreach ($quarters as $quarter) {
        $found = false;
        foreach ($data as $item) {
            if ($item['quarter'] === $quarter) {
                $quarterData[] = $item;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $quarterData[] = [
                'quarter' => $quarter,
                'total_tax' => 0,
                'collected' => 0,
                'paid_count' => 0,
                'pending_count' => 0,
                'overdue_count' => 0
            ];
        }
    }
    
    return $quarterData;
}

function getTopProperties($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            pr.reference_number,
            pr.lot_location,
            pr.barangay,
            po.full_name as owner_name,
            pt.total_annual_tax,
            lp.land_area_sqm,
            COUNT(bp.id) as building_count
        FROM property_registrations pr
        LEFT JOIN property_owners po ON pr.owner_id = po.id
        LEFT JOIN land_properties lp ON pr.id = lp.registration_id
        LEFT JOIN property_totals pt ON lp.id = pt.land_id
        LEFT JOIN building_properties bp ON lp.id = bp.land_id AND bp.status = 'active'
        WHERE pr.status = 'approved'
        AND pt.total_annual_tax IS NOT NULL
        GROUP BY pr.id
        ORDER BY pt.total_annual_tax DESC
        LIMIT 5
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPaymentStatus($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("
        SELECT 
            payment_status,
            COUNT(*) as count,
            COALESCE(SUM(total_quarterly_tax), 0) as total_amount
        FROM quarterly_taxes
        WHERE YEAR(due_date) = ?
        GROUP BY payment_status
    ");
    $stmt->execute([$year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>