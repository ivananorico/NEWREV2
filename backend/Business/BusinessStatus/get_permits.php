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
// Add error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if database file exists
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
    // Debug: Check if connection is established
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
            bp.owner_name,
            bp.business_type,
            bp.tax_calculation_type,
            bp.taxable_amount,
            bp.tax_rate,
            bp.tax_amount,
            bp.regulatory_fees,
            bp.total_tax,
            bp.approved_date,
            bp.street,
            bp.barangay,
            bp.district,
            bp.city,
            bp.province,
            bp.contact_number,
            bp.phone,
            bp.owner_email,
            bp.issue_date,
            bp.expiry_date,
            bp.status,
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

    // Debug: Check if we got data
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

        // Format currency values for frontend
        $permit['total_paid_tax_formatted'] = number_format($permit['total_paid_tax'], 2);
        $permit['total_pending_tax_formatted'] = number_format($permit['total_pending_tax'], 2);
        $permit['next_pending_tax_amount_formatted'] = number_format($permit['next_pending_tax_amount'], 2);
        $permit['overdue_tax_amount_formatted'] = number_format($permit['overdue_tax_amount'], 2);
        $permit['total_tax_formatted'] = number_format($permit['total_tax'], 2);
        
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

    // Get pending quarterly taxes for each business
    foreach ($permits as &$permit) {
        $quarterlySql = "
            SELECT 
                quarter,
                year,
                due_date,
                total_quarterly_tax,
                penalty_amount,
                payment_status
            FROM business_quarterly_taxes 
            WHERE business_permit_id = ?
            AND payment_status IN ('pending', 'overdue')
            ORDER BY year, 
                CASE quarter 
                    WHEN 'Q1' THEN 1 
                    WHEN 'Q2' THEN 2 
                    WHEN 'Q3' THEN 3 
                    WHEN 'Q4' THEN 4 
                END
        ";
        
        $quarterlyStmt = $pdo->prepare($quarterlySql);
        $quarterlyStmt->execute([$permit['id']]);
        $quarterlyData = $quarterlyStmt->fetchAll(PDO::FETCH_ASSOC);
        $permit['pending_quarterly_taxes'] = $quarterlyData !== false ? $quarterlyData : [];
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

    // Format summary values
    $tax_summary['total_revenue_formatted'] = number_format($total_revenue, 2);
    $tax_summary['total_pending_formatted'] = number_format($total_pending, 2);
    $tax_summary['total_overdue_formatted'] = number_format($total_overdue, 2);
    $tax_summary['total_next_pending_formatted'] = number_format($total_next_pending, 2);

    // Send success response
    echo json_encode([
        "status"  => "success",
        "message" => "Permits retrieved successfully",
        "permits" => $permits,
        "count"   => count($permits),
        "tax_summary" => $tax_summary,
        "timestamp" => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error occurred",
        "error" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
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