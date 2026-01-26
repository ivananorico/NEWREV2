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

// Get database connection
$pdo = getDatabaseConnection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to connect to database"
    ]);
    exit();
}

try {
    // Test connection
    $pdo->query("SELECT 1");
    
    // Get ALL approved and active permits (including pending taxes)
    $sql = "
        SELECT 
            bp.id,
            bp.applicant_id as business_permit_id,  -- Changed from business_permit_id to applicant_id
            bp.business_name,
            bp.owner_full_name as owner_name,  -- Changed from full_name to owner_full_name
            bp.business_nature as business_type,  -- Changed from business_type to business_nature
            bp.tax_calculation_type,
            bp.taxable_amount,
            bp.tax_rate,
            bp.tax_amount,
            bp.regulatory_fees,
            bp.total_tax,
            bp.approved_date,
            bp.issue_date,
            bp.expiry_date,
            bp.permit_status as status,  -- Changed from status to permit_status
            bp.tax_status,
            bp.created_at,
            bp.user_id,
            bp.contact_number,
            bp.email_address as owner_email,  -- Changed from personal_email to email_address
            bp.business_barangay,
            bp.business_district,
            bp.business_city,
            bp.business_province,
            bp.business_zipcode,
            bp.capital_investment,
            bp.owner_type,
            bp.trade_name,
            
            -- Calculate total paid tax from quarterly taxes
            COALESCE((
                SELECT SUM(bqt.total_quarterly_tax) 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id 
                AND bqt.payment_status = 'paid'
            ), 0) as total_paid_tax,
            
            -- Calculate total pending tax (including overdue)
            COALESCE((
                SELECT SUM(bqt.total_quarterly_tax) 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id 
                AND bqt.payment_status IN ('pending', 'overdue')
            ), 0) as total_pending_tax,
            
            -- Calculate overdue tax
            COALESCE((
                SELECT SUM(bqt.total_quarterly_tax + COALESCE(bqt.penalty_amount, 0)) 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id 
                AND bqt.payment_status = 'overdue'
            ), 0) as overdue_tax_amount,
            
            -- Get next pending quarter details
            (
                SELECT bqt.due_date 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id 
                AND bqt.payment_status IN ('pending', 'overdue')
                ORDER BY bqt.due_date ASC 
                LIMIT 1
            ) as next_due_date,
            
            -- Count of all quarters
            COALESCE((
                SELECT COUNT(*) 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id
            ), 0) as total_quarters,
            
            -- Count of paid quarters
            COALESCE((
                SELECT COUNT(*) 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id 
                AND bqt.payment_status = 'paid'
            ), 0) as paid_quarters,
            
            -- Count of pending/overdue quarters
            COALESCE((
                SELECT COUNT(*) 
                FROM business_quarterly_taxes bqt 
                WHERE bqt.business_permit_id = bp.id 
                AND bqt.payment_status IN ('pending', 'overdue')
            ), 0) as pending_quarters
            
        FROM business_permits bp
        WHERE bp.permit_status IN ('APPROVED', 'ACTIVE', 'RENEWED')  -- Changed to match your database values
        ORDER BY bp.approved_date DESC, bp.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($permits === false) {
        throw new Exception("Failed to fetch permits data");
    }

    // Calculate overall tax summary
    $total_annual_revenue = 0;
    $total_paid_tax = 0;
    $total_pending_tax = 0;
    $total_overdue_tax = 0;
    $businesses_with_pending_tax = 0;

    foreach ($permits as &$permit) {
        // Ensure numeric values
        $permit['total_tax'] = floatval($permit['total_tax'] ?? 0);
        $permit['total_paid_tax'] = floatval($permit['total_paid_tax'] ?? 0);
        $permit['total_pending_tax'] = floatval($permit['total_pending_tax'] ?? 0);
        $permit['overdue_tax_amount'] = floatval($permit['overdue_tax_amount'] ?? 0);
        
        // Add to totals
        $total_annual_revenue += $permit['total_tax'];
        $total_paid_tax += $permit['total_paid_tax'];
        $total_pending_tax += $permit['total_pending_tax'];
        $total_overdue_tax += $permit['overdue_tax_amount'];
        
        // Count businesses with pending tax
        if ($permit['total_pending_tax'] > 0) {
            $businesses_with_pending_tax++;
        }
        
        // Calculate tax payment percentage
        if ($permit['total_tax'] > 0) {
            $permit['tax_paid_percentage'] = round(($permit['total_paid_tax'] / $permit['total_tax']) * 100, 1);
        } else {
            $permit['tax_paid_percentage'] = 0;
        }
        
        // Determine payment status
        if ($permit['total_pending_tax'] <= 0 && $permit['total_tax'] > 0) {
            $permit['payment_status'] = 'fully_paid';
        } elseif ($permit['overdue_tax_amount'] > 0) {
            $permit['payment_status'] = 'overdue';
        } elseif ($permit['total_pending_tax'] > 0) {
            $permit['payment_status'] = 'pending';
        } else {
            $permit['payment_status'] = 'no_tax';
        }
        
        // Format dates for display
        if ($permit['next_due_date']) {
            $permit['next_due_date_formatted'] = date('M d, Y', strtotime($permit['next_due_date']));
        }
        
        if ($permit['issue_date']) {
            $permit['issue_date_formatted'] = date('M d, Y', strtotime($permit['issue_date']));
        }
        
        if ($permit['expiry_date']) {
            $permit['expiry_date_formatted'] = date('M d, Y', strtotime($permit['expiry_date']));
        }
        
        // Add progress info
        $permit['progress_info'] = [
            'paid_quarters' => $permit['paid_quarters'],
            'pending_quarters' => $permit['pending_quarters'],
            'total_quarters' => $permit['total_quarters'],
            'completion_rate' => $permit['total_quarters'] > 0 ? 
                round(($permit['paid_quarters'] / $permit['total_quarters']) * 100, 0) : 0
        ];
    }

    // Prepare comprehensive summary
    $summary = [
        'total_businesses' => count($permits),
        'total_annual_revenue' => $total_annual_revenue,
        'total_collected' => $total_paid_tax,
        'total_pending' => $total_pending_tax,
        'total_overdue' => $total_overdue_tax,
        'businesses_with_pending_tax' => $businesses_with_pending_tax,
        'collection_rate' => $total_annual_revenue > 0 ? 
            round(($total_paid_tax / $total_annual_revenue) * 100, 1) : 0,
        'fully_paid_businesses' => count(array_filter($permits, function($p) {
            return $p['payment_status'] === 'fully_paid';
        })),
        'pending_businesses' => count(array_filter($permits, function($p) {
            return $p['payment_status'] === 'pending';
        })),
        'overdue_businesses' => count(array_filter($permits, function($p) {
            return $p['payment_status'] === 'overdue';
        }))
    ];

    // Send success response
    echo json_encode([
        "status" => "success",
        "message" => "Permits retrieved successfully",
        "permits" => $permits,
        "summary" => $summary,
        "count" => count($permits),
        "timestamp" => date('Y-m-d H:i:s'),
        "debug_info" => [
            "database_schema_matched" => true,
            "total_records" => count($permits),
            "sample_data_check" => count($permits) > 0 ? [
                "has_correct_fields" => isset($permits[0]['applicant_id']) && isset($permits[0]['owner_full_name']),
                "permit_id_field" => $permits[0]['applicant_id'] ?? 'not_found',
                "status_field" => $permits[0]['permit_status'] ?? 'not_found'
            ] : "no_data"
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error occurred",
        "error" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Application error occurred",
        "error" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
}
?>