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
    
    // CORRECTED QUERY - using ONLY columns that exist in your table
    $stmt = $pdo->query("
        SELECT 
            id,
            applicant_id,  -- This is your permit identifier
            business_name,
            owner_full_name as owner_name,
            business_nature as business_type,
            trade_name,
            business_barangay as barangay,
            business_district as district,
            business_city as city,
            business_province as province,
            business_zipcode as zipcode,
            contact_number,
            email_address as owner_email,
            capital_investment,
            tax_calculation_type,
            taxable_amount,
            tax_rate,
            tax_amount,
            regulatory_fees,
            total_tax,
            application_date,
            issue_date,
            expiry_date,
            permit_status as status,
            tax_status,
            created_at,
            updated_at,
            user_id
        FROM business_permits 
        ORDER BY created_at DESC
    ");
    
    $permits = $stmt->fetchAll();
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Business permits retrieved successfully',
        'permits' => $permits,
        'count' => count($permits),
        'debug' => [
            'database_connected' => true,
            'columns_used' => ['id', 'applicant_id', 'business_name', 'owner_full_name', 'business_nature', 'trade_name', 'business_barangay', 'business_district', 'business_city', 'business_province', 'business_zipcode', 'contact_number', 'email_address', 'capital_investment', 'tax_calculation_type', 'taxable_amount', 'tax_rate', 'tax_amount', 'regulatory_fees', 'total_tax', 'application_date', 'issue_date', 'expiry_date', 'permit_status', 'tax_status', 'created_at', 'updated_at', 'user_id'],
            'record_count' => count($permits),
            'table_name' => 'business_permits'
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database query error: ' . $e->getMessage(),
        'permits' => [],
        'count' => 0,
        'debug' => [
            'error_details' => $e->getMessage(),
            'table_check' => 'business_permits table exists'
        ]
    ]);
}
?>