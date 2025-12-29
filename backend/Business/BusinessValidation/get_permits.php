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

/**
 * ======================================
 * DATABASE CONNECTION
 * ======================================
 */
require_once '../../../db/Business/business_db.php';

try {
    /**
     * ======================================
     * FETCH PENDING BUSINESS PERMITS
     * ======================================
     */
    $sql = "SELECT 
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
                CONCAT(bp.street, ', ', bp.barangay, ', ', bp.district, ', ', bp.city, ', ', bp.province) as address,
                bp.contact_number,
                bp.phone,
                bp.owner_email,
                bp.issue_date,
                bp.expiry_date,
                bp.status,
                bp.created_at,
                bp.updated_at,
                bp.user_id
            FROM business_permits bp
            WHERE bp.status = 'Pending'
            ORDER BY bp.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Pending business permits retrieved successfully',
        'permits' => $permits,
        'count'   => count($permits)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error',
        'error'   => $e->getMessage(),
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