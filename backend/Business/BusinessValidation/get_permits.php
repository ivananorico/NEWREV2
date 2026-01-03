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

try {
    /**
     * ======================================
     * FETCH BUSINESS PERMITS (FIXED)
     * ======================================
     */
    $sql = "SELECT 
                bp.id,
                bp.business_permit_id,
                bp.business_name,
                bp.full_name as owner_name,
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
                CONCAT(bp.business_street, ', ', bp.business_barangay, ', ', bp.business_district, ', ', bp.business_city, ', ', bp.business_province) as address,
                bp.personal_contact as contact_number,
                bp.personal_contact as phone,
                bp.personal_email as owner_email,
                bp.issue_date,
                bp.expiry_date,
                bp.status,
                bp.created_at,
                bp.updated_at,
                bp.user_id
            FROM business_permits bp
            ORDER BY bp.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Business permits retrieved successfully',
        'permits' => $permits,
        'count'   => count($permits),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error',
        'error'   => $e->getMessage(),
        'details' => $e->getTraceAsString(),
        'permits' => [],
        'count'   => 0
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Server error',
        'error'   => $e->getMessage(),
        'permits' => [],
        'count'   => 0
    ]);
}
?>