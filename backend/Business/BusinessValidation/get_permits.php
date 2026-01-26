<?php
/**
 * ======================================
 * CORS CONFIGURATION
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
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * ======================================
 * DATABASE CONNECTION
 * ======================================
 */
require_once '../../../db/Business/business_db.php';

// Get database connection
$pdo = getDatabaseConnection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Failed to connect to database',
        'permits' => [],
        'count'   => 0
    ]);
    exit();
}

try {
    /**
     * ======================================
     * FETCH BUSINESS PERMITS (UPDATED FOR YOUR DB STRUCTURE)
     * ======================================
     */
    $sql = "SELECT 
                bp.id,
                bp.applicant_id as business_permit_id,
                bp.business_name,
                bp.owner_full_name as owner_name,
                bp.owner_type,
                bp.business_nature as business_type,
                bp.trade_name,
                bp.business_barangay as barangay,
                bp.business_district as district,
                bp.business_city as city,
                bp.business_province as province,
                bp.business_zipcode as zipcode,
                bp.contact_number,
                bp.email_address as owner_email,
                bp.capital_investment,
                bp.tax_calculation_type,
                bp.taxable_amount,
                bp.tax_rate,
                bp.tax_amount,
                bp.regulatory_fees,
                bp.total_tax,
                bp.application_date,
                bp.approved_date,
                bp.issue_date,
                bp.expiry_date,
                bp.permit_status as status,
                bp.tax_status,
                bp.created_at,
                bp.updated_at,
                bp.user_id,
                -- Create full address
                CONCAT(
                    COALESCE(bp.business_barangay, ''), 
                    ', ', 
                    COALESCE(bp.business_city, ''),
                    ', ', 
                    COALESCE(bp.business_province, ''),
                    ' ', 
                    COALESCE(bp.business_zipcode, '')
                ) as address
            FROM business_permits bp
            ORDER BY bp.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add some debugging info
    if (empty($permits)) {
        error_log("No permits found in database");
    } else {
        error_log("Found " . count($permits) . " permits in database");
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'Business permits retrieved successfully',
        'permits' => $permits,
        'count'   => count($permits),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in get_permits.php: " . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'error_details' => $e->getMessage(),
        'permits' => [],
        'count'   => 0
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General error in get_permits.php: " . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Server error: ' . $e->getMessage(),
        'permits' => [],
        'count'   => 0
    ]);
}
?>