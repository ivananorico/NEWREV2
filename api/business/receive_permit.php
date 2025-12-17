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

    // Validate required fields from Business Permit
    $required_fields = ['business_permit_id', 'business_name', 'owner_name', 'business_type', 'taxable_amount', 'tax_calculation_type'];
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
    
    // Insert into business_permits table (for tax calculation)
    $sql = "INSERT INTO business_permits (
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
        tax_calculated,
        tax_approved,
        address, 
        contact_number, 
        phone,
        issue_date, 
        expiry_date, 
        status,
        created_at,
        updated_at
    ) VALUES (
        :business_permit_id, 
        :business_name, 
        :owner_name, 
        :business_type,
        :tax_calculation_type,
        :taxable_amount, 
        :tax_rate, 
        :tax_amount, 
        :regulatory_fees, 
        :total_tax,
        :tax_calculated,
        :tax_approved,
        :address, 
        :contact_number, 
        :phone,
        :issue_date, 
        :expiry_date, 
        :status,
        NOW(),
        NOW()
    )";

    $stmt = $pdo->prepare($sql);
    
    $params = [
        ':business_permit_id' => htmlspecialchars($business_permit_id),
        ':business_name' => htmlspecialchars($input['business_name']),
        ':owner_name' => htmlspecialchars($input['owner_name']),
        ':business_type' => htmlspecialchars($business_type),
        ':tax_calculation_type' => $tax_calculation_type,
        ':taxable_amount' => $taxable_amount,
        ':tax_rate' => $taxResult['tax_rate'],
        ':tax_amount' => $taxResult['tax_amount'],
        ':regulatory_fees' => $taxResult['regulatory_fees'],
        ':total_tax' => $taxResult['total_tax'],
        ':tax_calculated' => 1,
        ':tax_approved' => 0,
        ':address' => isset($input['address']) ? htmlspecialchars($input['address']) : '',
        ':contact_number' => isset($input['contact_number']) ? htmlspecialchars($input['contact_number']) : '',
        ':phone' => isset($input['phone']) ? htmlspecialchars($input['phone']) : (isset($input['contact_number']) ? htmlspecialchars($input['contact_number']) : ''),
        ':issue_date' => isset($input['issue_date']) ? $input['issue_date'] : date('Y-m-d'),
        ':expiry_date' => isset($input['expiry_date']) ? $input['expiry_date'] : date('Y-m-d', strtotime('+1 year')),
        ':status' => isset($input['status']) ? $input['status'] : 'Pending'
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
    
    // Update existing record
    $sql = "UPDATE business_permits SET
        business_name = :business_name, 
        owner_name = :owner_name, 
        business_type = :business_type,
        tax_calculation_type = :tax_calculation_type,
        taxable_amount = :taxable_amount, 
        tax_rate = :tax_rate, 
        tax_amount = :tax_amount, 
        regulatory_fees = :regulatory_fees, 
        total_tax = :total_tax,
        tax_calculated = :tax_calculated,
        address = :address, 
        contact_number = :contact_number, 
        phone = :phone,
        issue_date = :issue_date, 
        expiry_date = :expiry_date, 
        status = :status,
        updated_at = NOW()
        WHERE business_permit_id = :business_permit_id";

    $stmt = $pdo->prepare($sql);
    
    $params = [
        ':business_permit_id' => htmlspecialchars($business_permit_id),
        ':business_name' => htmlspecialchars($input['business_name']),
        ':owner_name' => htmlspecialchars($input['owner_name']),
        ':business_type' => htmlspecialchars($business_type),
        ':tax_calculation_type' => $tax_calculation_type,
        ':taxable_amount' => $taxable_amount,
        ':tax_rate' => $taxResult['tax_rate'],
        ':tax_amount' => $taxResult['tax_amount'],
        ':regulatory_fees' => $taxResult['regulatory_fees'],
        ':total_tax' => $taxResult['total_tax'],
        ':tax_calculated' => 1,
        ':address' => isset($input['address']) ? htmlspecialchars($input['address']) : '',
        ':contact_number' => isset($input['contact_number']) ? htmlspecialchars($input['contact_number']) : '',
        ':phone' => isset($input['phone']) ? htmlspecialchars($input['phone']) : (isset($input['contact_number']) ? htmlspecialchars($input['contact_number']) : ''),
        ':issue_date' => isset($input['issue_date']) ? $input['issue_date'] : date('Y-m-d'),
        ':expiry_date' => isset($input['expiry_date']) ? $input['expiry_date'] : date('Y-m-d', strtotime('+1 year')),
        ':status' => isset($input['status']) ? $input['status'] : 'Pending'
    ];

    $stmt->execute($params);
    
    $taxResult['action'] = 'updated';
    
    return $taxResult;
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