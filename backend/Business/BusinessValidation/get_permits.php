<?php
/**
 * ======================================
 * CORS CONFIGURATION (FINAL FIX)
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
                id,
                business_permit_id,
                business_name,
                owner_name,
                business_type,
                tax_calculation_type,
                taxable_amount,
                tax_rate,
                tax_amount,
                regulatory_fees,
                total_tax,
                approved_date,
                address,
                contact_number,
                phone,
                issue_date,
                expiry_date,
                status,
                created_at,
                updated_at,
                user_id
            FROM business_permits 
            WHERE status = 'Pending'
            ORDER BY created_at DESC";

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
