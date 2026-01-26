<?php
// import_external_permits.php
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(200);
    exit();
}

// Set CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// DEBUG: Show we're on domain
// ============================================
$debugInfo = [
    'server' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'time' => date('Y-m-d H:i:s'),
    'is_local' => (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) ? 'yes' : 'no',
    'on_domain' => 'YES - ' . ($_SERVER['HTTP_HOST'] ?? 'unknown')
];

// ============================================
// DATABASE CONNECTION
// ============================================
require_once '../../../db/Business/business_db.php';

$pdo = getDatabaseConnection();

if (!$pdo) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection FAILED on domain',
        'debug' => $debugInfo,
        'action' => 'Check database credentials for domain'
    ]);
    exit();
}

$debugInfo['database_connected'] = 'YES';

// Test database
try {
    // Count existing permits
    $countStmt = $pdo->query("SELECT COUNT(*) as count FROM business_permits");
    $countResult = $countStmt->fetch();
    $existingCount = $countResult['count'] ?? 0;
    
    $debugInfo['existing_permits'] = $existingCount;
    
    // Get table structure
    $columns = $pdo->query("DESCRIBE business_permits")->fetchAll(PDO::FETCH_COLUMN, 0);
    $debugInfo['table_columns_count'] = count($columns);
    $debugInfo['table_columns_sample'] = array_slice($columns, 0, 5);
    
} catch (Exception $e) {
    $debugInfo['table_check_error'] = $e->getMessage();
}

// ============================================
// GET INPUT DATA
// ============================================
$jsonInput = file_get_contents('php://input');
$debugInfo['input_received'] = !empty($jsonInput) ? 'YES - ' . strlen($jsonInput) . ' bytes' : 'NO';

if (empty($jsonInput)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No data received from browser',
        'debug' => $debugInfo
    ]);
    exit();
}

$input = json_decode($jsonInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON: ' . json_last_error_msg(),
        'debug' => $debugInfo,
        'raw_input' => substr($jsonInput, 0, 200)
    ]);
    exit();
}

$debugInfo['permits_received'] = isset($input['permits']) ? count($input['permits']) : 0;

if (!isset($input['permits']) || !is_array($input['permits'])) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'No permits array in input',
        'debug' => $debugInfo,
        'input_keys' => array_keys($input)
    ]);
    exit();
}

// ============================================
// IMPORT PROCESS
// ============================================
$imported = 0;
$skipped = 0;
$errors = [];
$importDetails = [];

foreach ($input['permits'] as $index => $permit) {
    $permitId = $permit['applicant_id'] ?? 'no-id-' . $index;
    
    try {
        // REQUIRED FIELDS CHECK
        if (empty($permit['applicant_id'])) {
            $errors[] = "Missing applicant_id for permit #$index";
            continue;
        }
        
        if (empty($permit['business_name'])) {
            $errors[] = "Missing business_name for $permitId";
            continue;
        }
        
        $applicantId = $permit['applicant_id'];
        
        // CHECK IF ALREADY EXISTS
        $checkStmt = $pdo->prepare("SELECT id FROM business_permits WHERE applicant_id = ?");
        $checkStmt->execute([$applicantId]);
        
        if ($checkStmt->rowCount() > 0) {
            $skipped++;
            $importDetails[] = "SKIP: $applicantId already exists";
            continue;
        }
        
        // PREPARE DATA
        $ownerFullName = trim(
            ($permit['owner_last_name'] ?? '') . ', ' .
            ($permit['owner_first_name'] ?? '') . ' ' .
            ($permit['owner_middle_name'] ?? '')
        );
        
        if (empty($ownerFullName) || $ownerFullName === ', ') {
            $ownerFullName = $permit['full_name'] ?? 'Unknown Owner';
        }
        
        // CALCULATE TAX
        $capital = floatval($permit['capital_investment'] ?? 0);
        $taxRate = ($capital <= 5000) ? 20.00 : 25.00;
        $taxAmount = ($capital * $taxRate) / 100;
        $regulatoryFees = 499.98 + 500 + 300; // Mayor + Sanitary + Registration
        $totalTax = $taxAmount + $regulatoryFees;
        
        // GENERATE IDs
        $businessPermitId = "BUS" . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $issueDate = date('Y-m-d');
        $expiryDate = date('Y-m-d', strtotime('+1 year'));
        
        // DEBUG: Show what we're inserting
        $debugInsert = [
            'applicant_id' => $applicantId,
            'business_name' => $permit['business_name'],
            'capital' => $capital,
            'tax_rate' => $taxRate,
            'total_tax' => $totalTax
        ];
        
        // INSERT INTO DATABASE
        $stmt = $pdo->prepare("
            INSERT INTO business_permits (
                applicant_id, business_permit_id, business_name, owner_full_name, 
                owner_type, business_nature, trade_name, business_barangay, 
                business_district, business_city, business_province, business_zipcode, 
                contact_number, email_address, capital_investment, tax_calculation_type,
                taxable_amount, tax_rate, tax_amount, regulatory_fees, total_tax,
                application_date, permit_status, tax_status, issue_date, expiry_date,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $success = $stmt->execute([
            $applicantId,
            $businessPermitId,
            $permit['business_name'] ?? '',
            $ownerFullName,
            $permit['owner_type'] ?? 'Individual',
            $permit['business_nature'] ?? '',
            $permit['trade_name'] ?? null,
            $permit['barangay'] ?? '',
            $permit['district'] ?? '',
            $permit['city_municipality'] ?? '',
            $permit['province'] ?? '',
            $permit['zip_code'] ?? '',
            $permit['contact_number'] ?? '',
            $permit['email_address'] ?? '',
            $capital,
            'capital_investment',
            $capital,
            $taxRate,
            $taxAmount,
            $regulatoryFees,
            $totalTax,
            $permit['application_date'] ?? $issueDate,
            'PENDING',
            'Pending',
            $issueDate,
            $expiryDate
        ]);
        
        if ($success) {
            $imported++;
            $importDetails[] = "IMPORTED: $applicantId - " . ($permit['business_name'] ?? '');
        } else {
            $errors[] = "Insert failed for $applicantId";
        }
        
    } catch (Exception $e) {
        $errors[] = "$permitId: " . $e->getMessage();
    }
}

// ============================================
// FINAL RESPONSE WITH DEBUG
// ============================================
$debugInfo['import_results'] = [
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => count($errors),
    'total_processed' => count($input['permits'])
];

if ($imported > 0) {
    $response = [
        'status' => 'success',
        'message' => "SUCCESS! Imported $imported new permits to your database",
        'imported_count' => $imported,
        'skipped_count' => $skipped,
        'error_count' => count($errors),
        'import_details' => array_slice($importDetails, 0, 5), // First 5
        'debug_summary' => [
            'on_domain' => $debugInfo['on_domain'],
            'existing_before' => $debugInfo['existing_permits'],
            'table_has_columns' => $debugInfo['table_columns_count'] ?? 'unknown'
        ]
    ];
    
    if (count($errors) > 0) {
        $response['errors_sample'] = array_slice($errors, 0, 3);
    }
    
} else {
    $response = [
        'status' => $skipped > 0 ? 'warning' : 'error',
        'message' => $skipped > 0 ? 
            "All permits already exist in database (skipped $skipped)" : 
            "No permits were imported",
        'imported_count' => 0,
        'skipped_count' => $skipped,
        'error_count' => count($errors),
        'debug_full' => $debugInfo, // Show full debug on failure
        'suggestion' => $skipped > 0 ? 
            'Try importing different permits or clear your database first' :
            'Check database permissions and table structure'
    ];
    
    if (count($errors) > 0) {
        $response['errors'] = array_slice($errors, 0, 5);
    }
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>