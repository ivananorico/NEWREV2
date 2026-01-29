<?php
/**
 * ======================================
 * CORS (credentials-safe)
 * ======================================
 */
$allowed_origins = [
    "http://localhost:5173",
    "https://revenuetreasury.goserveph.com"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma");
header("Content-Type: application/json; charset=utf-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * ======================================
 * DB CONNECTION
 * ======================================
 */
$host = 'localhost:3307';
$dbname = 'business_tax';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get ONLY OVERDUE business taxes
    $sql = "
        SELECT 
            bqt.id,
            bqt.business_permit_id,
            bqt.quarter,
            bqt.year,
            bqt.due_date,
            bqt.total_quarterly_tax,
            bqt.penalty_amount,
            bqt.payment_status,
            bqt.days_late,
            bqt.created_at,
            bqt.penalty_percent_used,
            bqt.discount_amount,
            bqt.tax_type_used,
            bqt.tax_rate_used,
            
            bp.applicant_id,
            bp.business_name,
            bp.owner_full_name,
            bp.owner_type,
            bp.business_nature,
            bp.trade_name,
            bp.business_barangay,
            bp.business_district,
            bp.business_city,
            bp.business_province,
            bp.business_zipcode,
            bp.contact_number,
            bp.email_address,
            bp.capital_investment,
            bp.tax_calculation_type,
            bp.taxable_amount,
            bp.tax_amount,
            bp.regulatory_fees,
            bp.total_tax,
            bp.permit_status,
            bp.tax_status
            
        FROM business_quarterly_taxes bqt
        LEFT JOIN business_permits bp ON bqt.business_permit_id = bp.id
        
        WHERE bqt.payment_status = 'overdue'
        
        ORDER BY 
            bqt.due_date ASC,
            bqt.days_late DESC,
            bqt.total_quarterly_tax DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $overdueTaxes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate additional information
    $currentDate = new DateTime();
    foreach ($overdueTaxes as &$tax) {
        // Calculate days late if not already calculated
        if ($tax['due_date']) {
            $dueDate = new DateTime($tax['due_date']);
            $interval = $currentDate->diff($dueDate);
            
            if ($dueDate < $currentDate) {
                $tax['days_late'] = $interval->days;
            } else {
                $tax['days_late'] = 0;
            }
        }
        
        // Calculate penalty if not set
        if (!$tax['penalty_amount'] || $tax['penalty_amount'] == 0) {
            if ($tax['due_date'] && $tax['total_quarterly_tax']) {
                $dueDate = new DateTime($tax['due_date']);
                if ($dueDate < $currentDate) {
                    $daysLate = $currentDate->diff($dueDate)->days;
                    $monthsLate = ceil($daysLate / 30);
                    $penaltyRate = $tax['penalty_percent_used'] ? $tax['penalty_percent_used'] / 100 : 0.02;
                    $tax['penalty_amount'] = number_format(
                        $tax['total_quarterly_tax'] * $penaltyRate * $monthsLate, 
                        2, '.', ''
                    );
                }
            }
        }
        
        // Format dates
        if ($tax['due_date']) {
            $tax['due_date_formatted'] = date('M d, Y', strtotime($tax['due_date']));
        }
        
        if ($tax['created_at']) {
            $tax['created_at_formatted'] = date('M d, Y H:i', strtotime($tax['created_at']));
        }
        
        // Clean up names
        $tax['owner_full_name'] = trim($tax['owner_full_name'] ?: '');
        $tax['business_name'] = trim($tax['business_name'] ?: '');
        
        // Generate business ID
        $tax['business_id'] = $tax['applicant_id'] ?: 'BUS-' . $tax['id'];
    }
    
    echo json_encode([
        "success" => true,
        "data" => $overdueTaxes,
        "count" => count($overdueTaxes),
        "timestamp" => date('Y-m-d H:i:s'),
        "current_date" => $currentDate->format('Y-m-d'),
        "status_filter" => "overdue_only"
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "error" => "Database error: " . $e->getMessage(),
        "timestamp" => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => "Application error: " . $e->getMessage(),
        "timestamp" => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>