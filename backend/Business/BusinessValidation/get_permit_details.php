<?php
// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, expires, Cache-Control, Pragma");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database
require_once '../../../db/Business/business_db.php';

try {
    // Get permit ID from query parameter
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Permit ID is required'
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
            'message' => 'Permit not found'
        ]);
        exit();
    }
    
    // Also get tax configuration for calculation
    $taxConfig = [];
    $regulatoryFees = [];
    
    // Get tax config based on tax type
    if ($permit['tax_calculation_type'] === 'capital_investment') {
        $sql = "SELECT * FROM capital_investment_tax_config 
                WHERE :amount BETWEEN min_amount AND max_amount 
                AND (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE()) 
                ORDER BY min_amount LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['amount' => $permit['taxable_amount']]);
        $taxConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // gross_sales
        $sql = "SELECT * FROM gross_sales_tax_config 
                WHERE business_type = :business_type 
                AND (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE()) 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['business_type' => $permit['business_type']]);
        $taxConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get regulatory fees
    $sql = "SELECT * FROM regulatory_fee_config 
            WHERE (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())";
    $stmt = $pdo->query($sql);
    $regulatoryFees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total regulatory fees
    $totalRegulatoryFees = 0;
    foreach ($regulatoryFees as $fee) {
        $totalRegulatoryFees += $fee['amount'];
    }
    
    // Calculate tax amount if tax amount is 0 (not yet calculated)
    $taxAmount = $permit['tax_amount'];
    $totalTax = $permit['total_tax'];
    
    // Check if tax needs to be calculated (tax_amount is 0 and we have taxable amount)
    if ($permit['tax_amount'] == 0 && $permit['taxable_amount'] > 0 && $taxConfig) {
        $taxRate = $taxConfig['tax_percent'];
        $taxableAmount = $permit['taxable_amount'];
        $taxAmount = ($taxableAmount * $taxRate) / 100;
        $totalTax = $taxAmount + $totalRegulatoryFees;
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Permit retrieved successfully',
        'permit' => $permit,
        'tax_config' => $taxConfig,
        'regulatory_fees' => $regulatoryFees,
        'total_regulatory_fees' => $totalRegulatoryFees,
        'calculated_tax_amount' => $taxAmount,
        'calculated_total_tax' => $totalTax,
        'calculation_date' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>