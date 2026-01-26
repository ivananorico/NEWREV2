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

// Database connection
$host = 'localhost';
$dbname = 'business_tax';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit();
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['permits']) || !is_array($input['permits'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid input data'
    ]);
    exit();
}

$imported = 0;
$skipped = 0;
$errors = [];

foreach ($input['permits'] as $permit) {
    try {
        // Check if already exists
        $check = $pdo->prepare("SELECT id FROM business_permits WHERE applicant_id = ?");
        $check->execute([$permit['applicant_id']]);
        
        if ($check->rowCount() > 0) {
            $skipped++;
            continue;
        }
        
        // Prepare data
        $ownerFullName = $permit['owner_last_name'] . ', ' . 
                        $permit['owner_first_name'] . ' ' . 
                        ($permit['owner_middle_name'] ?? '');
        
        // Calculate tax
        $capital = floatval($permit['capital_investment']) ?? 0;
        $taxRate = 25.00;
        if ($capital <= 5000) $taxRate = 20.00;
        
        $taxAmount = ($capital * $taxRate) / 100;
        $regulatoryFees = 499.98 + 500 + 300;
        $totalTax = $taxAmount + $regulatoryFees;
        
        // Generate IDs
        $business_permit_id = "BUS" . date('Y') . rand(1000, 9999);
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
        
        $stmt->execute([
            $permit['applicant_id'],
            $business_permit_id,
            $permit['business_name'],
            $ownerFullName,
            $permit['owner_type'] ?? 'Individual',
            $permit['business_nature'],
            $permit['trade_name'] ?? null,
            $permit['barangay'],
            $permit['district'],
            $permit['city_municipality'],
            $permit['province'],
            $permit['zip_code'] ?? '',
            $permit['contact_number'],
            $permit['email_address'],
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
        
        $imported++;
        
    } catch (Exception $e) {
        $errors[] = "Permit {$permit['applicant_id']}: " . $e->getMessage();
    }
}

// Return result
echo json_encode([
    'status' => 'success',
    'message' => "Import completed. Imported: $imported, Skipped: $skipped",
    'imported_count' => $imported,
    'skipped_count' => $skipped,
    'errors' => $errors
]);
?>