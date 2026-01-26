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

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// DATABASE CONNECTION
// ============================================

// Try to use your existing connection
$pdo = null;
$connectionMethod = '';

if (file_exists('../../../db/Business/business_db.php')) {
    require_once '../../../db/Business/business_db.php';
    $pdo = getDatabaseConnection();
    $connectionMethod = 'business_db.php';
    
    if ($pdo) {
        error_log("Connected using business_db.php");
    }
}

// If still no connection, try direct
if (!$pdo) {
    $isLocal = (isset($_SERVER['HTTP_HOST']) && 
               (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false));
    
    if ($isLocal) {
        // Local
        $host = 'localhost';
        $port = 3307;
        $dbname = 'business_tax';
        $user = 'root';
        $pass = '';
    } else {
        // Production
        $host = 'localhost';
        $port = 3306;
        $dbname = 'reve_business';
        $user = 'reve_business';
        $pass = 'eIuEFsbOpmlo-hpu';
    }
    
    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $connectionMethod = 'direct';
        error_log("Connected directly to $dbname");
    } catch (Exception $e) {
        error_log("Direct connection failed: " . $e->getMessage());
    }
}

if (!$pdo) {
    http_response_code(200);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'connection_method' => $connectionMethod
    ]);
    exit();
}

// ============================================
// PROCESS IMPORT
// ============================================

try {
    // Get input
    $jsonInput = file_get_contents('php://input');
    
    if (empty($jsonInput)) {
        throw new Exception('No input data received');
    }
    
    $input = json_decode($jsonInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    if (!isset($input['permits']) || !is_array($input['permits'])) {
        throw new Exception('No permits array in input');
    }
    
    $imported = 0;
    $skipped = 0;
    $updated = 0;
    $errors = [];
    $details = [];
    
    error_log("Starting import of " . count($input['permits']) . " permits");
    
    // First, let's check what's already in the database
    $existingPermits = [];
    try {
        $stmt = $pdo->query("SELECT applicant_id FROM business_permits");
        $existingPermits = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        error_log("Found " . count($existingPermits) . " existing permits in database");
    } catch (Exception $e) {
        error_log("Error checking existing permits: " . $e->getMessage());
    }
    
    foreach ($input['permits'] as $index => $permit) {
        $permitId = $permit['applicant_id'] ?? 'unknown-' . $index;
        
        try {
            // Validate required fields
            if (empty($permit['applicant_id'])) {
                $errors[] = "Permit #$index: Missing applicant_id";
                continue;
            }
            
            if (empty($permit['business_name'])) {
                $errors[] = "Permit $permitId: Missing business_name";
                continue;
            }
            
            $applicantId = $permit['applicant_id'];
            
            // Check if exists
            $exists = in_array($applicantId, $existingPermits);
            
            if ($exists) {
                $skipped++;
                $details[] = "Skipped: $applicantId - {$permit['business_name']} (already exists)";
                continue;
            }
            
            // Prepare data
            $ownerFullName = trim(
                ($permit['owner_last_name'] ?? '') . ', ' .
                ($permit['owner_first_name'] ?? '') . ' ' .
                ($permit['owner_middle_name'] ?? '')
            );
            
            if (empty($ownerFullName) || $ownerFullName === ',  ') {
                $ownerFullName = $permit['full_name'] ?? 'Unknown Owner';
            }
            
            // Calculate tax
            $capital = floatval($permit['capital_investment'] ?? 0);
            $taxRate = ($capital <= 5000) ? 20.00 : 25.00;
            $taxAmount = ($capital * $taxRate) / 100;
            $regulatoryFees = 499.98 + 500 + 300;
            $totalTax = $taxAmount + $regulatoryFees;
            
            // Generate business permit ID
            $businessPermitId = "BUS" . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Dates
            $issueDate = date('Y-m-d');
            $expiryDate = date('Y-m-d', strtotime('+1 year'));
            $appDate = $permit['application_date'] ?? $issueDate;
            
            // Insert the permit
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
                $appDate,
                'PENDING',
                'Pending',
                $issueDate,
                $expiryDate
            ]);
            
            if ($success) {
                $imported++;
                $details[] = "Imported: $applicantId - {$permit['business_name']}";
                error_log("Successfully imported: $applicantId");
                
                // Add to existing array to prevent duplicate inserts in same batch
                $existingPermits[] = $applicantId;
            } else {
                $errors[] = "Permit $applicantId: Insert failed";
            }
            
        } catch (Exception $e) {
            $errors[] = "Permit $permitId: " . $e->getMessage();
            error_log("Error with permit $permitId: " . $e->getMessage());
        }
    }
    
    // Return detailed results
    $result = [
        'status' => 'success',
        'message' => "Import completed. Imported: $imported, Skipped: $skipped",
        'imported_count' => $imported,
        'skipped_count' => $skipped,
        'updated_count' => $updated,
        'error_count' => count($errors),
        'total_processed' => count($input['permits']),
        'connection_method' => $connectionMethod,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Add details if any
    if (!empty($details)) {
        $result['details'] = array_slice($details, 0, 10); // First 10 details
    }
    
    if (!empty($errors)) {
        $result['errors'] = array_slice($errors, 0, 10); // First 10 errors
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(200);
    echo json_encode([
        'status' => 'error',
        'message' => 'Import failed: ' . $e->getMessage(),
        'error_details' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>