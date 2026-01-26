<?php
// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, expires, Cache-Control, Pragma");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database
require_once '../../../db/Business/business_db.php';

// Get database connection
$pdo = getDatabaseConnection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to connect to database',
        'code' => 'DATABASE_CONNECTION_ERROR'
    ]);
    exit();
}

try {
    // Set error reporting
    error_reporting(E_ALL & ~E_NOTICE);
    
    // Get permit ID from query parameter
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Permit ID is required',
            'code' => 'MISSING_ID'
        ]);
        exit();
    }
    
    // Get specific permit by ID
    $sql = "SELECT * FROM business_permits WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $permit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permit) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Permit not found',
            'code' => 'PERMIT_NOT_FOUND'
        ]);
        exit();
    }
    
    // Get tax configuration based on tax type - ULTRA SIMPLE VERSION
    $taxConfig = [];
    $regulatoryFees = [];
    $currentDate = date('Y-m-d');
    
    if ($permit['tax_calculation_type'] === 'capital_investment') {
        // For capital investment tax
        $capitalAmount = floatval($permit['taxable_amount'] ? $permit['taxable_amount'] : ($permit['capital_investment'] ? $permit['capital_investment'] : 0));
        
        // SIMPLEST POSSIBLE QUERY - No date conditions, just get all and filter in PHP
        $sql = "SELECT * FROM capital_investment_tax_config ORDER BY min_amount";
        $stmt = $pdo->query($sql);
        $allRates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Find matching rate in PHP
        foreach ($allRates as $rate) {
            $min = floatval($rate['min_amount']);
            $max = floatval($rate['max_amount']);
            
            // Check if amount falls within this bracket
            if ($capitalAmount >= $min && $capitalAmount <= $max) {
                $taxConfig = $rate;
                break;
            }
        }
        
        // If no bracket found, use default
        if (!$taxConfig && $capitalAmount > 0) {
            $taxConfig = [
                'tax_percent' => '25.00',
                'min_amount' => '0',
                'max_amount' => '999999999.99'
            ];
        }
        
    } else {
        // For gross sales tax - SIMPLE QUERY
        $sql = "SELECT * FROM gross_sales_tax_config ORDER BY id LIMIT 1";
        $stmt = $pdo->query($sql);
        $taxConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$taxConfig) {
            $taxConfig = [
                'tax_percent' => '2.00',
                'business_type' => 'Retailer'
            ];
        }
    }
    
    // Get regulatory fees - SIMPLE QUERY
    $sql = "SELECT * FROM regulatory_fee_config ORDER BY fee_name";
    $stmt = $pdo->query($sql);
    $regulatoryFees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total regulatory fees
    $totalRegulatoryFees = 0;
    foreach ($regulatoryFees as $fee) {
        $totalRegulatoryFees += floatval($fee['amount']);
    }
    
    // Calculate tax amount
    $taxAmount = isset($permit['tax_amount']) ? floatval($permit['tax_amount']) : 0;
    $totalTax = isset($permit['total_tax']) ? floatval($permit['total_tax']) : 0;
    $calculatedTaxAmount = $taxAmount;
    $calculatedTotalTax = $totalTax;
    
    // Check if tax needs to be calculated
    $taxableAmount = isset($permit['taxable_amount']) ? floatval($permit['taxable_amount']) : 
                     (isset($permit['capital_investment']) ? floatval($permit['capital_investment']) : 0);
    
    if (($taxAmount == 0 || $totalTax == 0) && $taxableAmount > 0 && $taxConfig) {
        $taxRate = isset($taxConfig['tax_percent']) ? floatval($taxConfig['tax_percent']) : 
                   ($permit['tax_calculation_type'] === 'capital_investment' ? 25.00 : 2.00);
        
        // Calculate tax
        $calculatedTaxAmount = ($taxableAmount * $taxRate) / 100;
        $calculatedTotalTax = $calculatedTaxAmount + $totalRegulatoryFees;
        
        // Only update if values are significantly different
        if (abs($calculatedTaxAmount - $taxAmount) > 0.01 || abs($calculatedTotalTax - $totalTax) > 0.01) {
            $updateSql = "UPDATE business_permits 
                         SET tax_amount = ?, 
                             total_tax = ?,
                             tax_rate = ?,
                             updated_at = NOW()
                         WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                $calculatedTaxAmount,
                $calculatedTotalTax,
                $taxRate,
                $id
            ]);
            
            // Update local permit data
            $permit['tax_amount'] = $calculatedTaxAmount;
            $permit['total_tax'] = $calculatedTotalTax;
            $permit['tax_rate'] = $taxRate;
        }
    }
    
    // Get discount and penalty configurations - SIMPLE QUERIES
    $discountConfig = $pdo->query("SELECT * FROM business_discount_config ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $penaltyConfig = $pdo->query("SELECT * FROM business_penalty_config ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    // Prepare response
    $response = [
        'status' => 'success',
        'message' => 'Permit retrieved successfully',
        'permit' => $permit,
        'tax_config' => $taxConfig ?: null,
        'regulatory_fees' => $regulatoryFees,
        'discount_config' => $discountConfig ?: null,
        'penalty_config' => $penaltyConfig ?: null,
        'total_regulatory_fees' => $totalRegulatoryFees,
        'calculated_tax_amount' => $calculatedTaxAmount,
        'calculated_total_tax' => $calculatedTotalTax,
        'calculation_date' => date('Y-m-d H:i:s'),
        'calculation_info' => [
            'tax_type' => $permit['tax_calculation_type'],
            'taxable_amount' => $taxableAmount,
            'effective_tax_rate' => $taxConfig ? floatval($taxConfig['tax_percent']) : null,
            'business_nature' => $permit['business_nature']
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'code' => 'DATABASE_ERROR',
        'trace' => $e->getTraceAsString(),
        'debug_info' => [
            'permit_id' => isset($id) ? $id : 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    error_log("BusinessValidation Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}
?>