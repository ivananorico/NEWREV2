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
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma");
header("Content-Type: application/json");

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
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbPath = __DIR__ . '/../../../db/Business/business_db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database configuration file not found",
        "path" => $dbPath
    ]);
    exit();
}

require_once $dbPath;

try {
    if (!$pdo) {
        throw new Exception("Database connection failed - PDO object is null");
    }
    
    // Test connection
    $pdo->query("SELECT 1");
    
    $sql = "
        SELECT 
            bp.id,
            bp.business_permit_id,
            bp.business_name,
            bp.full_name as owner_name,  -- Changed from owner_name to full_name
            bp.business_type,
            bp.tax_calculation_type,
            bp.taxable_amount,
            bp.tax_rate,
            bp.tax_amount,
            bp.regulatory_fees,
            bp.total_tax,
            bp.approved_date,
            bp.business_street as street,
            bp.business_barangay as barangay,
            bp.business_district as district,
            bp.business_city as city,
            bp.business_province as province,
            bp.personal_contact as contact_number,
            bp.personal_contact as phone,
            bp.personal_email as owner_email,
            bp.issue_date,
            bp.expiry_date,
            bp.status,  -- This is the business status (Approved/Active)
            bp.created_at,
            bp.user_id,
            -- Calculate total paid tax from quarterly taxes
            COALESCE((
                SELECT SUM(bqt.total_quarterly_tax) 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id 
                AND bqt.payment_status = 'paid'
            ), 0) as total_paid_tax,
            -- Calculate total pending tax
            COALESCE((
                SELECT SUM(bqt.total_quarterly_tax) 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id 
                AND bqt.payment_status IN ('pending', 'overdue')
            ), 0) as total_pending_tax,
            -- Get next pending tax amount and due date
            COALESCE((
                SELECT bqt.total_quarterly_tax 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id 
                AND bqt.payment_status IN ('pending', 'overdue')
                AND bqt.due_date >= CURDATE()
                ORDER BY bqt.due_date ASC 
                LIMIT 1
            ), 0) as next_pending_tax_amount,
            -- Get next pending due date
            COALESCE((
                SELECT bqt.due_date 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id 
                AND bqt.payment_status IN ('pending', 'overdue')
                AND bqt.due_date >= CURDATE()
                ORDER BY bqt.due_date ASC 
                LIMIT 1
            ), NULL) as next_pending_due_date,
            -- Get overdue tax amount
            COALESCE((
                SELECT SUM(bqt.total_quarterly_tax + COALESCE(bqt.penalty_amount, 0)) 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id 
                AND bqt.payment_status = 'overdue'
            ), 0) as overdue_tax_amount,
            -- Get count of pending quarters
            COALESCE((
                SELECT COUNT(*) 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id 
                AND bqt.payment_status IN ('pending', 'overdue')
            ), 0) as pending_quarters_count
        FROM business_permits bp
        WHERE bp.status IN ('Approved', 'Active')
        ORDER BY bp.approved_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($permits === false) {
        throw new Exception("Failed to fetch permits data");
    }

    // Calculate overall tax summary
    $total_revenue = 0;
    $total_pending = 0;
    $total_overdue = 0;
    $total_next_pending = 0;
    $pending_business_count = 0;

    foreach ($permits as &$permit) {
        $total_revenue += $permit['total_paid_tax'];
        $total_pending += $permit['total_pending_tax'];
        $total_overdue += $permit['overdue_tax_amount'];
        $total_next_pending += $permit['next_pending_tax_amount'];
        
        if ($permit['total_pending_tax'] > 0) {
            $pending_business_count++;
        }

        // Calculate tax percentage
        if ($permit['total_tax'] > 0) {
            $permit['tax_paid_percentage'] = round(($permit['total_paid_tax'] / $permit['total_tax']) * 100, 1);
        } else {
            $permit['tax_paid_percentage'] = 0;
        }
        
        // Determine payment status
        if ($permit['total_pending_tax'] <= 0) {
            $permit['payment_status'] = 'fully_paid';
        } elseif ($permit['overdue_tax_amount'] > 0) {
            $permit['payment_status'] = 'overdue';
        } elseif ($permit['next_pending_tax_amount'] > 0) {
            $permit['payment_status'] = 'pending';
        } else {
            $permit['payment_status'] = 'unknown';
        }
    }

    // Prepare tax summary
    $tax_summary = [
        'total_revenue' => $total_revenue,
        'total_pending' => $total_pending,
        'total_overdue' => $total_overdue,
        'total_next_pending' => $total_next_pending,
        'pending_business_count' => $pending_business_count,
        'total_businesses' => count($permits),
        'collection_rate' => count($permits) > 0 ? round(($total_revenue / ($total_revenue + $total_pending)) * 100, 1) : 0
    ];

    // Send success response
    echo json_encode([
        "status"  => "success",
        "message" => "Permits retrieved successfully",
        "permits" => $permits,
        "count"   => count($permits),
        "tax_summary" => $tax_summary,
        "timestamp" => date('Y-m-d H:i:s'),
        "debug_info" => [
            "query_status" => "executed",
            "permit_count" => count($permits),
            "sample_permit" => count($permits) > 0 ? [
                "has_status_field" => isset($permits[0]['status']),
                "status_value" => $permits[0]['status'] ?? 'not_found',
                "fields_present" => array_keys($permits[0])
            ] : "no_data"
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error occurred",
        "error" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine(),
        "sql_query" => $sql ?? "not_set"
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Application error occurred",
        "error" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
}
?>