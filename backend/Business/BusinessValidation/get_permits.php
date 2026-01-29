<?php
// get_permits.php
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Cache-Control, Pragma");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit();
}

// Set CORS headers for actual request
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Cache-Control, Pragma");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// DATABASE CONNECTION
// ============================================
$dbPath = '../../../db/Business/business_db.php';

if (!file_exists($dbPath)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection file not found',
        'debug' => ['path' => $dbPath]
    ]);
    exit();
}

require_once $dbPath;
$pdo = getDatabaseConnection();

if (!$pdo) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'permits' => [],
        'count' => 0
    ]);
    exit();
}

// ============================================
// FETCH PERMITS FROM DATABASE
// ============================================
try {
    // Test connection
    $pdo->query("SELECT 1")->fetch();
    
    // Get current year for tax summary
    $current_year = date('Y');
    
    // Query to get permits with both tax_status from business_permits AND tax_summary
    $query = "
        SELECT 
            bp.id,
            bp.applicant_id,
            bp.business_name,
            bp.owner_full_name as owner_name,
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
            bp.issue_date,
            bp.expiry_date,
            bp.permit_status as status,
            bp.tax_status,  -- This is from business_permits table
            bp.created_at,
            bp.updated_at,
            bp.user_id,
            ts.tax_status as yearly_tax_status,  -- This is from tax_summary table for current year
            ts.year as tax_year,
            ts.created_at as tax_summary_created
        FROM business_permits bp
        LEFT JOIN tax_summary ts ON bp.id = ts.business_id AND ts.year = ?
        ORDER BY bp.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$current_year]);
    $permits = $stmt->fetchAll();
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Business permits retrieved successfully',
        'permits' => $permits,
        'count' => count($permits),
        'current_year' => $current_year,
        'debug' => [
            'database_connected' => true,
            'record_count' => count($permits),
            'tax_summary_year' => $current_year,
            'has_business_permits_tax_status' => true
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database query error: ' . $e->getMessage(),
        'permits' => [],
        'count' => 0,
        'debug' => [
            'error_details' => $e->getMessage()
        ]
    ]);
}