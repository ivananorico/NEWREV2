<?php
// revenue2/backend/Business/BusinessValidation/calculate_tax.php

// Enable CORS with proper headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, expires, Cache-Control, Pragma");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Get parameters
    $permit_id = $_GET['permit_id'] ?? null;
    $tax_type = $_GET['tax_type'] ?? 'capital_investment';
    $taxable_amount = floatval($_GET['taxable_amount'] ?? 0);
    $business_type = $_GET['business_type'] ?? 'Retailer';
    $selected_config_id = $_GET['selected_config_id'] ?? null;
    $override_tax_rate = $_GET['override_tax_rate'] ?? null;
    
    // Validate input
    if ($taxable_amount <= 0) {
        throw new Exception("Taxable amount must be greater than 0");
    }
    
    // Include database connection
    require_once '../../../db/Business/business_db.php';
    
    // Check if connection was successful
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("Database connection failed - PDO object not created");
    }
    
    // Initialize variables
    $tax_rate = 0;
    $tax_amount = 0;
    $config_used = null;
    $available_configs = [];
    
    // Get tax configuration based on tax type
    if ($tax_type === 'capital_investment') {
        // Get capital investment tax brackets
        if ($selected_config_id && $override_tax_rate === null) {
            // Use selected config ID
            $stmt = $pdo->prepare("
                SELECT id, min_amount, max_amount, tax_percent, remarks 
                FROM capital_investment_tax_config 
                WHERE id = ? 
                AND (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
                LIMIT 1
            ");
            $stmt->execute([$selected_config_id]);
            $config_used = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Find matching bracket based on amount
            $stmt = $pdo->prepare("
                SELECT id, min_amount, max_amount, tax_percent, remarks 
                FROM capital_investment_tax_config 
                WHERE ? BETWEEN min_amount AND max_amount
                AND (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
                ORDER BY min_amount
                LIMIT 1
            ");
            $stmt->execute([$taxable_amount]);
            $config_used = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Get all available configs for reference
        $all_stmt = $pdo->prepare("
            SELECT id, min_amount, max_amount, tax_percent, remarks 
            FROM capital_investment_tax_config 
            WHERE (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
            ORDER BY min_amount
        ");
        $all_stmt->execute();
        $available_configs = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Determine tax rate
        if ($override_tax_rate !== null) {
            // Use custom override rate
            $tax_rate = floatval($override_tax_rate);
            $tax_amount = $taxable_amount * ($tax_rate / 100);
            $config_used = [
                'id' => 'custom',
                'tax_percent' => $tax_rate,
                'min_amount' => 'Custom',
                'max_amount' => 'Custom',
                'remarks' => 'Custom tax rate applied'
            ];
        } elseif ($config_used) {
            // Use selected or matched config
            $tax_rate = floatval($config_used['tax_percent']);
            $tax_amount = $taxable_amount * ($tax_rate / 100);
        } else {
            // Default rate if no bracket found
            $tax_rate = 0.25; // Default rate from your data
            $tax_amount = $taxable_amount * ($tax_rate / 100);
            $config_used = [
                'id' => 'default',
                'tax_percent' => $tax_rate,
                'min_amount' => 0,
                'max_amount' => 0,
                'remarks' => 'No matching bracket found, using default rate'
            ];
        }
        
    } elseif ($tax_type === 'gross_sales') {
        // Get gross sales tax rate for business type
        if ($override_tax_rate !== null) {
            // Use custom override rate
            $tax_rate = floatval($override_tax_rate);
            $tax_amount = $taxable_amount * ($tax_rate / 100);
            $config_used = [
                'id' => 'custom',
                'business_type' => $business_type,
                'tax_percent' => $tax_rate,
                'remarks' => 'Custom tax rate applied'
            ];
        } elseif ($selected_config_id) {
            // Use selected config ID
            $stmt = $pdo->prepare("
                SELECT id, business_type, tax_percent, remarks 
                FROM gross_sales_tax_config 
                WHERE id = ? 
                AND (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
                LIMIT 1
            ");
            $stmt->execute([$selected_config_id]);
            $config_used = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($config_used) {
                $tax_rate = floatval($config_used['tax_percent']);
                $tax_amount = $taxable_amount * ($tax_rate / 100);
            }
        } else {
            // Find rate for business type
            $stmt = $pdo->prepare("
                SELECT id, business_type, tax_percent, remarks 
                FROM gross_sales_tax_config 
                WHERE business_type = ? 
                AND (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
                LIMIT 1
            ");
            $stmt->execute([$business_type]);
            $config_used = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($config_used) {
                $tax_rate = floatval($config_used['tax_percent']);
                $tax_amount = $taxable_amount * ($tax_rate / 100);
            }
        }
        
        // Get all available configs
        $all_stmt = $pdo->prepare("
            SELECT id, business_type, tax_percent, remarks 
            FROM gross_sales_tax_config 
            WHERE (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
            ORDER BY business_type
        ");
        $all_stmt->execute();
        $available_configs = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no config found, use default
        if (!$config_used || $tax_rate === 0) {
            // Default rates based on your data
            $default_rates = [
                'Retailer' => 2.00,
                'Wholesaler' => 1.50,
                'Manufacturer' => 1.75,
                'Service' => 1.25
            ];
            
            $tax_rate = $default_rates[$business_type] ?? 2.00;
            $tax_amount = $taxable_amount * ($tax_rate / 100);
            $config_used = [
                'id' => 'default',
                'business_type' => $business_type,
                'tax_percent' => $tax_rate,
                'remarks' => 'Using default rate for ' . $business_type
            ];
        }
        
    } else {
        throw new Exception("Invalid tax type. Must be 'capital_investment' or 'gross_sales'");
    }
    
    // Get regulatory fees (active fees only)
    $stmt = $pdo->prepare("
        SELECT id, fee_name, amount, remarks 
        FROM regulatory_fee_config 
        WHERE (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
        ORDER BY id
    ");
    $stmt->execute();
    $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total fees
    $total_fees = 0;
    foreach ($fees as $fee) {
        $total_fees += floatval($fee['amount']);
    }
    
    // Calculate total tax
    $total_tax = $tax_amount + $total_fees;
    
    // Get discount configuration (optional)
    $discount_stmt = $pdo->prepare("
        SELECT id, discount_percent, remarks 
        FROM business_discount_config 
        WHERE (expiration_date IS NULL OR expiration_date >= CURDATE())
        ORDER BY effective_date DESC 
        LIMIT 1
    ");
    $discount_stmt->execute();
    $discount_config = $discount_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get penalty configuration (optional)
    $penalty_stmt = $pdo->prepare("
        SELECT id, penalty_percent, remarks 
        FROM business_penalty_config 
        WHERE (expiration_date IS NULL OR expiration_date >= CURDATE())
        ORDER BY effective_date DESC 
        LIMIT 1
    ");
    $penalty_stmt->execute();
    $penalty_config = $penalty_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Prepare calculation steps
    $calculation_steps = [];
    
    if ($tax_type === 'capital_investment') {
        $calculation_steps = [
            [
                'step' => 1,
                'description' => 'Taxable Capital Amount',
                'formula' => 'Base amount to calculate tax',
                'calculation' => "Amount: ₱" . number_format($taxable_amount, 2),
                'result' => "₱" . number_format($taxable_amount, 2)
            ],
            [
                'step' => 2,
                'description' => 'Tax Bracket Selection',
                'formula' => 'Find matching tax bracket',
                'calculation' => "Amount ₱" . number_format($taxable_amount, 2) . 
                    " in bracket: ₱" . number_format(floatval($config_used['min_amount'] ?? 0), 2) . 
                    " - ₱" . number_format(floatval($config_used['max_amount'] ?? 0), 2),
                'result' => "Tax Rate: " . number_format($tax_rate, 2) . "%"
            ],
            [
                'step' => 3,
                'description' => 'Calculate Tax Amount',
                'formula' => 'Tax Amount = Taxable Amount × (Tax Rate ÷ 100)',
                'calculation' => "₱" . number_format($taxable_amount, 2) . " × (" . number_format($tax_rate, 2) . " ÷ 100)",
                'result' => "₱" . number_format($tax_amount, 2)
            ]
        ];
    } else {
        $calculation_steps = [
            [
                'step' => 1,
                'description' => 'Gross Sales Amount',
                'formula' => 'Base amount to calculate tax',
                'calculation' => "Amount: ₱" . number_format($taxable_amount, 2),
                'result' => "₱" . number_format($taxable_amount, 2)
            ],
            [
                'step' => 2,
                'description' => 'Business Type Tax Rate',
                'formula' => 'Tax rate for ' . $business_type,
                'calculation' => $business_type . " tax rate: " . number_format($tax_rate, 2) . "%",
                'result' => "Tax Rate: " . number_format($tax_rate, 2) . "%"
            ],
            [
                'step' => 3,
                'description' => 'Calculate Tax Amount',
                'formula' => 'Tax Amount = Gross Sales × (Tax Rate ÷ 100)',
                'calculation' => "₱" . number_format($taxable_amount, 2) . " × (" . number_format($tax_rate, 2) . " ÷ 100)",
                'result' => "₱" . number_format($tax_amount, 2)
            ]
        ];
    }
    
    // Add regulatory fees step
    $fee_calculation = [];
    foreach ($fees as $fee) {
        $fee_calculation[] = $fee['fee_name'] . " (₱" . number_format(floatval($fee['amount']), 2) . ")";
    }
    
    $calculation_steps[] = [
        'step' => 4,
        'description' => 'Regulatory Fees',
        'formula' => 'Sum of all regulatory fees',
        'calculation' => implode(' + ', $fee_calculation),
        'result' => "₱" . number_format($total_fees, 2)
    ];
    
    // Add total tax step
    $calculation_steps[] = [
        'step' => 5,
        'description' => 'Total Annual Tax',
        'formula' => 'Total Tax = Tax Amount + Regulatory Fees',
        'calculation' => "₱" . number_format($tax_amount, 2) . " + ₱" . number_format($total_fees, 2),
        'result' => "₱" . number_format($total_tax, 2)
    ];
    
    // If discount available, add discount step
    if ($discount_config) {
        $discount_percent = floatval($discount_config['discount_percent']);
        $discount_amount = $total_tax * ($discount_percent / 100);
        $total_after_discount = $total_tax - $discount_amount;
        
        $calculation_steps[] = [
            'step' => 6,
            'description' => 'Early Payment Discount (' . $discount_percent . '%)',
            'formula' => 'Discount = Total Tax × (Discount Rate ÷ 100)',
            'calculation' => "₱" . number_format($total_tax, 2) . " × (" . $discount_percent . " ÷ 100)",
            'result' => "Discount: ₱" . number_format($discount_amount, 2) . 
                      ", Total After Discount: ₱" . number_format($total_after_discount, 2)
        ];
    }
    
    // Success response
    echo json_encode([
        'status' => 'success',
        'calculation' => [
            'tax_type' => $tax_type,
            'taxable_amount' => $taxable_amount,
            'tax_rate' => $tax_rate,
            'tax_amount' => $tax_amount,
            'regulatory_fees' => $total_fees,
            'total_tax' => $total_tax,
            'business_type' => $business_type,
            'permit_id' => $permit_id
        ],
        'config_used' => $config_used,
        'available_configs' => $available_configs,
        'fee_breakdown' => array_map(function($fee) {
            return [
                'name' => $fee['fee_name'],
                'amount' => floatval($fee['amount']),
                'remarks' => $fee['remarks']
            ];
        }, $fees),
        'discount_config' => $discount_config,
        'penalty_config' => $penalty_config,
        'calculation_steps' => $calculation_steps,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Calculation error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>