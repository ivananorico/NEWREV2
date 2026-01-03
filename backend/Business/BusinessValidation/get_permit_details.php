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
    $sql = "SELECT * FROM business_permits WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
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
    
    // Get tax configuration based on tax type
    $taxConfig = [];
    $regulatoryFees = [];
    
    if ($permit['tax_calculation_type'] === 'capital_investment') {
        // For capital investment tax
        $sql = "SELECT * FROM capital_investment_tax_config 
                WHERE :amount BETWEEN min_amount AND max_amount 
                AND (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE() OR expiration_date = '') 
                ORDER BY min_amount 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['amount' => floatval($permit['taxable_amount'])]);
        $taxConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$taxConfig) {
            // Fallback: Get the highest bracket that could apply
            $sql = "SELECT * FROM capital_investment_tax_config 
                    WHERE :amount >= min_amount 
                    AND (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE() OR expiration_date = '')
                    ORDER BY min_amount DESC 
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['amount' => floatval($permit['taxable_amount'])]);
            $taxConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        // For gross sales tax
        $sql = "SELECT * FROM gross_sales_tax_config 
                WHERE business_type = :business_type 
                AND (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE() OR expiration_date = '')
                ORDER BY effective_date DESC 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['business_type' => $permit['business_type']]);
        $taxConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$taxConfig) {
            // Fallback: Get default rate for the business type
            $sql = "SELECT * FROM gross_sales_tax_config 
                    WHERE business_type = 'Retailer'
                    AND (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE() OR expiration_date = '')
                    LIMIT 1";
            $stmt = $pdo->query($sql);
            $taxConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    // Get regulatory fees
    $sql = "SELECT * FROM regulatory_fee_config 
            WHERE (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE() OR expiration_date = '')
            ORDER BY fee_name";
    $stmt = $pdo->query($sql);
    $regulatoryFees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total regulatory fees
    $totalRegulatoryFees = 0;
    foreach ($regulatoryFees as $fee) {
        $totalRegulatoryFees += floatval($fee['amount']);
    }
    
    // Calculate tax amount
    $taxAmount = floatval($permit['tax_amount']);
    $totalTax = floatval($permit['total_tax']);
    $calculatedTaxAmount = $taxAmount;
    $calculatedTotalTax = $totalTax;
    
    // Check if tax needs to be calculated (tax_amount is 0 and we have taxable amount and tax config)
    if ($taxAmount == 0 && $permit['taxable_amount'] > 0 && $taxConfig) {
        $taxRate = floatval($taxConfig['tax_percent']);
        $taxableAmount = floatval($permit['taxable_amount']);
        $calculatedTaxAmount = ($taxableAmount * $taxRate) / 100;
        $calculatedTotalTax = $calculatedTaxAmount + $totalRegulatoryFees;
    }
    
    // Prepare response
    $response = [
        'status' => 'success',
        'message' => 'Permit retrieved successfully',
        'permit' => $permit,
        'tax_config' => $taxConfig ?: null,
        'regulatory_fees' => $regulatoryFees,
        'total_regulatory_fees' => $totalRegulatoryFees,
        'calculated_tax_amount' => $calculatedTaxAmount,
        'calculated_total_tax' => $calculatedTotalTax,
        'calculation_date' => date('Y-m-d H:i:s'),
        'calculation_info' => [
            'tax_type' => $permit['tax_calculation_type'],
            'taxable_amount' => floatval($permit['taxable_amount']),
            'effective_tax_rate' => $taxConfig ? floatval($taxConfig['tax_percent']) : null
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'code' => 'DATABASE_ERROR'
    ]);
    error_log("BusinessValidation Error: " . $e->getMessage());
}
?>