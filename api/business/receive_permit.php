<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include your PDO DB connection
require_once '../../db/Business/business_db.php';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    calculateAndSaveBusinessTax();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// ==================== CALCULATE AND SAVE BUSINESS TAX ====================
function calculateAndSaveBusinessTax() {
    global $pdo;
    
    // Get POST data from Business Permit module
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No data received']);
        exit();
    }

    // Validate required fields from Business Permit - REMOVED owner_name, ADDED full_name
    $required_fields = ['business_permit_id', 'business_name', 'full_name', 'business_type', 'taxable_amount', 'tax_calculation_type'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required fields from Business Permit: ' . implode(', ', $missing_fields)
        ]);
        exit();
    }

    try {
        $pdo->beginTransaction();
        
        $business_permit_id = $input['business_permit_id'];
        $tax_calculation_type = $input['tax_calculation_type']; // 'capital_investment' or 'gross_sales'
        $taxable_amount = floatval($input['taxable_amount']);
        
        // First, check if tax calculation already exists for this permit
        $checkSql = "SELECT id FROM business_permits WHERE business_permit_id = :business_permit_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':business_permit_id' => $business_permit_id]);
        $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingRecord) {
            // Update existing record
            $result = updateBusinessTax($pdo, $business_permit_id, $input);
        } else {
            // Create new record
            $result = createBusinessTax($pdo, $input);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Business tax calculated and saved successfully',
            'business_permit_id' => $business_permit_id,
            'tax_calculation' => $result
        ]);
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        
        // Log the error
        error_log("Business Tax Calculation Error: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to calculate business tax: ' . $e->getMessage()
        ]);
    }
}

// ==================== CREATE NEW BUSINESS TAX RECORD ====================
function createBusinessTax($pdo, $input) {
    $business_permit_id = $input['business_permit_id'];
    $tax_calculation_type = $input['tax_calculation_type'];
    $taxable_amount = floatval($input['taxable_amount']);
    $business_type = $input['business_type'];
    
    // Calculate taxes
    $taxResult = calculateTaxes($business_type, $taxable_amount, $tax_calculation_type);
    
    // Insert into business_permits table (for tax calculation) - REMOVED owner_name
    $sql = "INSERT INTO business_permits (
        business_permit_id, 
        business_name, 
        
        -- Personal Information (full_name only, no owner_name)
        full_name, 
        sex, 
        date_of_birth, 
        marital_status,
        personal_street, 
        personal_barangay, 
        personal_district, 
        personal_city, 
        personal_province, 
        personal_zipcode, 
        personal_contact, 
        personal_email,
        
        -- Business Address (no phone/email)
        business_street, 
        business_barangay, 
        business_district, 
        business_city, 
        business_province, 
        business_zipcode,
        
        -- Business and Tax Information
        business_type,
        tax_calculation_type,
        taxable_amount, 
        tax_rate, 
        tax_amount, 
        regulatory_fees, 
        total_tax,
        
        -- Dates and Status
        issue_date, 
        expiry_date, 
        status,
        created_at,
        updated_at
    ) VALUES (
        :business_permit_id, 
        :business_name, 
        
        -- Personal Information
        :full_name, 
        :sex, 
        :date_of_birth, 
        :marital_status,
        :personal_street, 
        :personal_barangay, 
        :personal_district, 
        :personal_city, 
        :personal_province, 
        :personal_zipcode, 
        :personal_contact, 
        :personal_email,
        
        -- Business Address
        :business_street, 
        :business_barangay, 
        :business_district, 
        :business_city, 
        :business_province, 
        :business_zipcode,
        
        -- Business and Tax Information
        :business_type,
        :tax_calculation_type,
        :taxable_amount, 
        :tax_rate, 
        :tax_amount, 
        :regulatory_fees, 
        :total_tax,
        
        -- Dates and Status
        :issue_date, 
        :expiry_date, 
        :status,
        NOW(),
        NOW()
    )";

    $stmt = $pdo->prepare($sql);
    
    // Check if tax is calculated (taxable_amount > 0 and tax_amount > 0)
    $isTaxCalculated = ($taxable_amount > 0 && $taxResult['tax_amount'] > 0);
    
    // Prepare personal information data with defaults
    // Use full_name from input, fallback to owner_name for backward compatibility
    $full_name = isset($input['full_name']) ? htmlspecialchars($input['full_name']) : 
                (isset($input['owner_name']) ? htmlspecialchars($input['owner_name']) : '');
    
    $sex = isset($input['sex']) ? htmlspecialchars($input['sex']) : null;
    $date_of_birth = isset($input['date_of_birth']) ? $input['date_of_birth'] : null;
    $marital_status = isset($input['marital_status']) ? htmlspecialchars($input['marital_status']) : null;
    
    // Personal address
    $personal_street = isset($input['personal_street']) ? htmlspecialchars($input['personal_street']) : (isset($input['street']) ? htmlspecialchars($input['street']) : '');
    $personal_barangay = isset($input['personal_barangay']) ? htmlspecialchars($input['personal_barangay']) : (isset($input['barangay']) ? htmlspecialchars($input['barangay']) : 'Unknown');
    $personal_district = isset($input['personal_district']) ? htmlspecialchars($input['personal_district']) : (isset($input['district']) ? htmlspecialchars($input['district']) : 'Unknown');
    $personal_city = isset($input['personal_city']) ? htmlspecialchars($input['personal_city']) : (isset($input['city']) ? htmlspecialchars($input['city']) : 'Quezon City');
    $personal_province = isset($input['personal_province']) ? htmlspecialchars($input['personal_province']) : (isset($input['province']) ? htmlspecialchars($input['province']) : 'Metro Manila');
    $personal_zipcode = isset($input['personal_zipcode']) ? htmlspecialchars($input['personal_zipcode']) : (isset($input['zipcode']) ? htmlspecialchars($input['zipcode']) : '');
    $personal_contact = isset($input['personal_contact']) ? htmlspecialchars($input['personal_contact']) : (isset($input['contact_number']) ? htmlspecialchars($input['contact_number']) : '');
    $personal_email = isset($input['personal_email']) ? htmlspecialchars($input['personal_email']) : (isset($input['owner_email']) ? htmlspecialchars($input['owner_email']) : '');
    
    // Business address
    $business_street = isset($input['business_street']) ? htmlspecialchars($input['business_street']) : $personal_street;
    $business_barangay = isset($input['business_barangay']) ? htmlspecialchars($input['business_barangay']) : $personal_barangay;
    $business_district = isset($input['business_district']) ? htmlspecialchars($input['business_district']) : $personal_district;
    $business_city = isset($input['business_city']) ? htmlspecialchars($input['business_city']) : $personal_city;
    $business_province = isset($input['business_province']) ? htmlspecialchars($input['business_province']) : $personal_province;
    $business_zipcode = isset($input['business_zipcode']) ? htmlspecialchars($input['business_zipcode']) : $personal_zipcode;
    
    $params = [
        ':business_permit_id' => htmlspecialchars($business_permit_id),
        ':business_name' => htmlspecialchars($input['business_name']),
        
        // Personal Information (full_name only)
        ':full_name' => $full_name,
        ':sex' => $sex,
        ':date_of_birth' => $date_of_birth,
        ':marital_status' => $marital_status,
        ':personal_street' => $personal_street,
        ':personal_barangay' => $personal_barangay,
        ':personal_district' => $personal_district,
        ':personal_city' => $personal_city,
        ':personal_province' => $personal_province,
        ':personal_zipcode' => $personal_zipcode,
        ':personal_contact' => $personal_contact,
        ':personal_email' => $personal_email,
        
        // Business Address
        ':business_street' => $business_street,
        ':business_barangay' => $business_barangay,
        ':business_district' => $business_district,
        ':business_city' => $business_city,
        ':business_province' => $business_province,
        ':business_zipcode' => $business_zipcode,
        
        // Business and Tax Information
        ':business_type' => htmlspecialchars($business_type),
        ':tax_calculation_type' => $tax_calculation_type,
        ':taxable_amount' => $taxable_amount,
        ':tax_rate' => $taxResult['tax_rate'],
        ':tax_amount' => $taxResult['tax_amount'],
        ':regulatory_fees' => $taxResult['regulatory_fees'],
        ':total_tax' => $taxResult['total_tax'],
        
        // Dates
        ':issue_date' => isset($input['issue_date']) ? $input['issue_date'] : date('Y-m-d'),
        ':expiry_date' => isset($input['expiry_date']) ? $input['expiry_date'] : date('Y-m-d', strtotime('+1 year')),
        ':status' => $isTaxCalculated ? 'Approved' : (isset($input['status']) ? $input['status'] : 'Pending')
    ];

    $stmt->execute($params);
    $recordId = $pdo->lastInsertId();
    
    $taxResult['record_id'] = $recordId;
    $taxResult['action'] = 'created';
    
    return $taxResult;
}

// ==================== UPDATE EXISTING BUSINESS TAX RECORD ====================
function updateBusinessTax($pdo, $business_permit_id, $input) {
    $tax_calculation_type = $input['tax_calculation_type'];
    $taxable_amount = floatval($input['taxable_amount']);
    $business_type = $input['business_type'];
    
    // Calculate taxes
    $taxResult = calculateTaxes($business_type, $taxable_amount, $tax_calculation_type);
    
    // Prepare personal information data with defaults
    // Use full_name from input, fallback to owner_name for backward compatibility
    $full_name = isset($input['full_name']) ? htmlspecialchars($input['full_name']) : 
                (isset($input['owner_name']) ? htmlspecialchars($input['owner_name']) : '');
    
    $sex = isset($input['sex']) ? htmlspecialchars($input['sex']) : null;
    $date_of_birth = isset($input['date_of_birth']) ? $input['date_of_birth'] : null;
    $marital_status = isset($input['marital_status']) ? htmlspecialchars($input['marital_status']) : null;
    
    // Personal address
    $personal_street = isset($input['personal_street']) ? htmlspecialchars($input['personal_street']) : (isset($input['street']) ? htmlspecialchars($input['street']) : '');
    $personal_barangay = isset($input['personal_barangay']) ? htmlspecialchars($input['personal_barangay']) : (isset($input['barangay']) ? htmlspecialchars($input['barangay']) : 'Unknown');
    $personal_district = isset($input['personal_district']) ? htmlspecialchars($input['personal_district']) : (isset($input['district']) ? htmlspecialchars($input['district']) : 'Unknown');
    $personal_city = isset($input['personal_city']) ? htmlspecialchars($input['personal_city']) : (isset($input['city']) ? htmlspecialchars($input['city']) : 'Quezon City');
    $personal_province = isset($input['personal_province']) ? htmlspecialchars($input['personal_province']) : (isset($input['province']) ? htmlspecialchars($input['province']) : 'Metro Manila');
    $personal_zipcode = isset($input['personal_zipcode']) ? htmlspecialchars($input['personal_zipcode']) : (isset($input['zipcode']) ? htmlspecialchars($input['zipcode']) : '');
    $personal_contact = isset($input['personal_contact']) ? htmlspecialchars($input['personal_contact']) : (isset($input['contact_number']) ? htmlspecialchars($input['contact_number']) : '');
    $personal_email = isset($input['personal_email']) ? htmlspecialchars($input['personal_email']) : (isset($input['owner_email']) ? htmlspecialchars($input['owner_email']) : '');
    
    // Business address
    $business_street = isset($input['business_street']) ? htmlspecialchars($input['business_street']) : $personal_street;
    $business_barangay = isset($input['business_barangay']) ? htmlspecialchars($input['business_barangay']) : $personal_barangay;
    $business_district = isset($input['business_district']) ? htmlspecialchars($input['business_district']) : $personal_district;
    $business_city = isset($input['business_city']) ? htmlspecialchars($input['business_city']) : $personal_city;
    $business_province = isset($input['business_province']) ? htmlspecialchars($input['business_province']) : $personal_province;
    $business_zipcode = isset($input['business_zipcode']) ? htmlspecialchars($input['business_zipcode']) : $personal_zipcode;
    
    // Update existing record with personal and business information - REMOVED owner_name
    $sql = "UPDATE business_permits SET
        business_name = :business_name, 
        
        -- Personal Information (full_name only)
        full_name = :full_name, 
        sex = :sex, 
        date_of_birth = :date_of_birth, 
        marital_status = :marital_status,
        personal_street = :personal_street, 
        personal_barangay = :personal_barangay, 
        personal_district = :personal_district, 
        personal_city = :personal_city, 
        personal_province = :personal_province, 
        personal_zipcode = :personal_zipcode, 
        personal_contact = :personal_contact, 
        personal_email = :personal_email,
        
        -- Business Address
        business_street = :business_street, 
        business_barangay = :business_barangay, 
        business_district = :business_district, 
        business_city = :business_city, 
        business_province = :business_province, 
        business_zipcode = :business_zipcode,
        
        -- Business and Tax Information
        business_type = :business_type,
        tax_calculation_type = :tax_calculation_type,
        taxable_amount = :taxable_amount, 
        tax_rate = :tax_rate, 
        tax_amount = :tax_amount, 
        regulatory_fees = :regulatory_fees, 
        total_tax = :total_tax,
        
        -- Dates and Status
        issue_date = :issue_date, 
        expiry_date = :expiry_date, 
        status = :status,
        updated_at = NOW()
        WHERE business_permit_id = :business_permit_id";

    $stmt = $pdo->prepare($sql);
    
    // Check if tax is calculated (taxable_amount > 0 and tax_amount > 0)
    $isTaxCalculated = ($taxable_amount > 0 && $taxResult['tax_amount'] > 0);
    // Check if tax is approved (if status is already 'Approved' or 'Active')
    $currentStatus = getCurrentStatus($pdo, $business_permit_id);
    $isTaxApproved = ($currentStatus === 'Approved' || $currentStatus === 'Active');
    
    $params = [
        ':business_permit_id' => htmlspecialchars($business_permit_id),
        ':business_name' => htmlspecialchars($input['business_name']),
        
        // Personal Information (full_name only)
        ':full_name' => $full_name,
        ':sex' => $sex,
        ':date_of_birth' => $date_of_birth,
        ':marital_status' => $marital_status,
        ':personal_street' => $personal_street,
        ':personal_barangay' => $personal_barangay,
        ':personal_district' => $personal_district,
        ':personal_city' => $personal_city,
        ':personal_province' => $personal_province,
        ':personal_zipcode' => $personal_zipcode,
        ':personal_contact' => $personal_contact,
        ':personal_email' => $personal_email,
        
        // Business Address
        ':business_street' => $business_street,
        ':business_barangay' => $business_barangay,
        ':business_district' => $business_district,
        ':business_city' => $business_city,
        ':business_province' => $business_province,
        ':business_zipcode' => $business_zipcode,
        
        // Business and Tax Information
        ':business_type' => htmlspecialchars($business_type),
        ':tax_calculation_type' => $tax_calculation_type,
        ':taxable_amount' => $taxable_amount,
        ':tax_rate' => $taxResult['tax_rate'],
        ':tax_amount' => $taxResult['tax_amount'],
        ':regulatory_fees' => $taxResult['regulatory_fees'],
        ':total_tax' => $taxResult['total_tax'],
        
        // Dates
        ':issue_date' => isset($input['issue_date']) ? $input['issue_date'] : date('Y-m-d'),
        ':expiry_date' => isset($input['expiry_date']) ? $input['expiry_date'] : date('Y-m-d', strtotime('+1 year')),
        ':status' => $isTaxApproved ? $currentStatus : ($isTaxCalculated ? 'Approved' : 'Pending')
    ];

    $stmt->execute($params);
    
    $taxResult['action'] = 'updated';
    
    return $taxResult;
}

// ==================== GET CURRENT STATUS HELPER FUNCTION ====================
function getCurrentStatus($pdo, $business_permit_id) {
    $sql = "SELECT status FROM business_permits WHERE business_permit_id = :business_permit_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':business_permit_id' => $business_permit_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['status'] : 'Pending';
}

// ==================== TAX CALCULATION FUNCTION ====================
function calculateTaxes($businessType, $taxableAmount, $calculationType) {
    global $pdo;
    
    try {
        $taxRate = 0;
        $taxAmount = 0;
        
        if ($calculationType === 'capital_investment') {
            // Calculate capital investment tax
            $capitalTaxQuery = "SELECT tax_percent FROM capital_investment_tax_config 
                               WHERE :amount >= min_amount AND :amount <= max_amount
                               AND (
                                   expiration_date IS NULL 
                                   OR expiration_date = '' 
                                   OR expiration_date = '0000-00-00' 
                                   OR expiration_date >= CURDATE()
                               )
                               ORDER BY effective_date DESC LIMIT 1";
            
            $capitalTaxStmt = $pdo->prepare($capitalTaxQuery);
            $capitalTaxStmt->execute([':amount' => $taxableAmount]);
            $capitalTax = $capitalTaxStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($capitalTax) {
                $taxRate = floatval($capitalTax['tax_percent']);
                $taxAmount = $taxableAmount * ($taxRate / 100);
            }
            
        } elseif ($calculationType === 'gross_sales') {
            // Calculate gross sales tax
            $grossTaxQuery = "SELECT tax_percent FROM gross_sales_tax_config 
                             WHERE business_type = :business_type
                             AND (
                                 expiration_date IS NULL 
                                 OR expiration_date = '' 
                                 OR expiration_date = '0000-00-00' 
                                 OR expiration_date >= CURDATE()
                             )
                             ORDER BY effective_date DESC LIMIT 1";
            
            $grossTaxStmt = $pdo->prepare($grossTaxQuery);
            $grossTaxStmt->execute([':business_type' => $businessType]);
            $grossTax = $grossTaxStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($grossTax) {
                $taxRate = floatval($grossTax['tax_percent']);
                $taxAmount = $taxableAmount * ($taxRate / 100);
            }
        }
        
        // Get regulatory fees (sum all active regulatory fees)
        $regulatoryQuery = "SELECT SUM(amount) as total_fees FROM regulatory_fee_config 
                           WHERE (
                               expiration_date IS NULL 
                               OR expiration_date = '' 
                               OR expiration_date = '0000-00-00' 
                               OR expiration_date >= CURDATE()
                           )";
        
        $regulatoryStmt = $pdo->prepare($regulatoryQuery);
        $regulatoryStmt->execute();
        $regulatoryFees = $regulatoryStmt->fetch(PDO::FETCH_ASSOC);
        
        $totalRegulatory = $regulatoryFees['total_fees'] ?? 0;
        
        // Calculate total tax
        $totalTax = $taxAmount + $totalRegulatory;
        
        return [
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'regulatory_fees' => $totalRegulatory,
            'total_tax' => $totalTax,
            'calculation_type' => $calculationType
        ];
        
    } catch (Exception $e) {
        error_log("Tax Calculation Error: " . $e->getMessage());
        
        // Return default values if calculation fails
        return [
            'tax_rate' => 0,
            'tax_amount' => 0,
            'regulatory_fees' => 0,
            'total_tax' => 0,
            'calculation_type' => $calculationType,
            'error' => $e->getMessage()
        ];
    }
}
?>