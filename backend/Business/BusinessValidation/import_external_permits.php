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
// DATABASE CONNECTION WITH FALLBACK
// ============================================

$pdo = null;

// Try 1: Use your existing connection file
if (file_exists('../../../db/Business/business_db.php')) {
    require_once '../../../db/Business/business_db.php';
    $pdo = getDatabaseConnection();
}

// Try 2: If that fails, try direct connections
if (!$pdo) {
    // Determine environment
    $isLocal = (isset($_SERVER['HTTP_HOST']) && 
               (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false));
    
    if ($isLocal) {
        // LOCAL DATABASE (for testing)
        $host = 'localhost';
        $port = 3307;
        $dbname = 'business_tax';
        $user = 'root';
        $pass = ''; // Empty password for local
    } else {
        // PRODUCTION DATABASE (your actual domain)
        $host = 'localhost';
        $port = 3306;
        $dbname = 'reve_business'; // Your actual database name
        $user = 'reve_business'; // Your actual username
        $pass = 'eIuEFsbOpmlo-hpu'; // Your actual password
    }
    
    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $user, $pass, $options);
        error_log("Database connected successfully to: $dbname");
        
    } catch (PDOException $e) {
        // Try alternative connections
        error_log("Primary connection failed: " . $e->getMessage());
        
        // Try common alternatives
        $alternativePasswords = ['', 'root', 'password', 'admin'];
        
        foreach ($alternativePasswords as $altPass) {
            try {
                $pdo = new PDO($dsn, $user, $altPass, $options);
                error_log("Connected with alternative password");
                break;
            } catch (PDOException $e2) {
                // Continue trying
            }
        }
        
        if (!$pdo) {
            // Final fallback - create a simple response
            http_response_code(200); // Return 200 but with error message
            echo json_encode([
                'status' => 'error',
                'message' => 'Database connection failed. Please check your credentials.',
                'environment' => $isLocal ? 'local' : 'production',
                'database' => $dbname,
                'user' => $user,
                'host' => $host . ':' . $port,
                'tip' => 'Check your database credentials in business_db.php'
            ]);
            exit();
        }
    }
}

if (!$pdo) {
    http_response_code(200);
    echo json_encode([
        'status' => 'error',
        'message' => 'Could not establish database connection',
        'action' => 'Please check your database configuration'
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
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    if (!isset($input['permits']) || !is_array($input['permits'])) {
        throw new Exception('No permits data found in input');
    }
    
    $imported = 0;
    $skipped = 0;
    $errors = [];
    
    error_log("Processing " . count($input['permits']) . " permits for import");
    
    foreach ($input['permits'] as $index => $permit) {
        try {
            // Validate
            if (empty($permit['applicant_id'])) {
                $errors[] = "Permit #$index: Missing applicant_id";
                continue;
            }
            
            $applicantId = $permit['applicant_id'];
            
            // Check if exists
            $check = $pdo->prepare("SELECT id FROM business_permits WHERE applicant_id = ?");
            $check->execute([$applicantId]);
            
            if ($check->rowCount() > 0) {
                $skipped++;
                continue;
            }
            
            // Prepare data
            $ownerFullName = $permit['owner_last_name'] . ', ' . 
                            $permit['owner_first_name'] . ' ' . 
                            ($permit['owner_middle_name'] ?? '');
            
            // Calculate tax
            $capital = floatval($permit['capital_investment'] ?? 0);
            $taxRate = ($capital <= 5000) ? 20.00 : 25.00;
            $taxAmount = ($capital * $taxRate) / 100;
            $regulatoryFees = 499.98 + 500 + 300;
            $totalTax = $taxAmount + $regulatoryFees;
            
            // Generate IDs
            $businessPermitId = "BUS" . date('Y') . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $issueDate = date('Y-m-d');
            $expiryDate = date('Y-m-d', strtotime('+1 year'));
            
            // Insert
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
                trim($ownerFullName),
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
                error_log("Imported: $applicantId");
            } else {
                $errors[] = "Permit $applicantId: Insert failed";
            }
            
        } catch (Exception $e) {
            $errors[] = "Permit $applicantId: " . $e->getMessage();
            error_log("Error importing $applicantId: " . $e->getMessage());
        }
    }
    
    // Return success
    echo json_encode([
        'status' => 'success',
        'message' => "Import completed successfully",
        'imported_count' => $imported,
        'skipped_count' => $skipped,
        'error_count' => count($errors),
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(200);
    echo json_encode([
        'status' => 'error',
        'message' => 'Import processing failed: ' . $e->getMessage(),
        'error_details' => $e->getTraceAsString(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>