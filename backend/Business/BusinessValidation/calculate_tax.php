<?php
// revenue/backend/Business/BusinessValidation/calculate_tax.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Get parameters
    $permit_id = $_GET['permit_id'] ?? null;
    $tax_type = $_GET['tax_type'] ?? 'capital_investment';
    $taxable_amount = floatval($_GET['taxable_amount'] ?? 10000);
    $business_type = $_GET['business_type'] ?? 'Retailer';
    
    // Fixed calculation - NO DATABASE QUERIES
    $tax_rate = 0.25; // Default rate for capital investment
    $tax_amount = $taxable_amount * ($tax_rate / 100);
    
    // Fixed fees (from your database)
    $fees = [
        ['name' => 'Mayor Permit Fee', 'amount' => 499.98, 'remarks' => 'Mayor\'s permit fee'],
        ['name' => 'Sanitary Fee', 'amount' => 500.00, 'remarks' => 'Sanitary permit fee'],
        ['name' => 'Registration Fee', 'amount' => 300.00, 'remarks' => 'New business registration']
    ];
    
    $total_fees = 499.98 + 500.00 + 300.00;
    $total_tax = $tax_amount + $total_fees;
    
    // Fixed capital investment brackets (from your database)
    $available_configs = [
        [
            'id' => 1,
            'min_amount' => '1.00',
            'max_amount' => '5000.00',
            'tax_percent' => '0.20',
            'remarks' => 'For capital up to 5,000'
        ],
        [
            'id' => 2,
            'min_amount' => '5000.00',
            'max_amount' => '10000.00',
            'tax_percent' => '0.25',
            'remarks' => 'For capital 5,000.01 to 10,000'
        ],
        [
            'id' => 3,
            'min_amount' => '10000.01',
            'max_amount' => '15000.00',
            'tax_percent' => '0.25',
            'remarks' => 'For capital 10,000.01 to 15,000'
        ]
    ];
    
    // Find matching bracket
    $config_used = null;
    foreach ($available_configs as $config) {
        if ($taxable_amount >= floatval($config['min_amount']) && $taxable_amount <= floatval($config['max_amount'])) {
            $config_used = $config;
            $tax_rate = floatval($config['tax_percent']);
            $tax_amount = $taxable_amount * ($tax_rate / 100);
            break;
        }
    }
    
    if (!$config_used) {
        $config_used = [
            'id' => 'default',
            'tax_percent' => $tax_rate,
            'min_amount' => 'Not found',
            'max_amount' => 'Not found',
            'remarks' => 'No matching bracket found'
        ];
    }
    
    // Calculation steps
    $calculation_steps = [
        [
            'step' => 1,
            'description' => 'Taxable Amount',
            'formula' => 'Base amount to calculate tax',
            'calculation' => "Amount: ₱" . number_format($taxable_amount, 2),
            'result' => "₱" . number_format($taxable_amount, 2)
        ],
        [
            'step' => 2,
            'description' => 'Tax Bracket Selection',
            'formula' => 'Find matching tax bracket',
            'calculation' => "Amount ₱" . number_format($taxable_amount, 2) . " in bracket: ₱{$config_used['min_amount']} - ₱{$config_used['max_amount']}",
            'result' => "Tax Rate: {$tax_rate}%"
        ],
        [
            'step' => 3,
            'description' => 'Calculate Tax Amount',
            'formula' => 'Tax Amount = Taxable Amount × (Tax Rate ÷ 100)',
            'calculation' => "₱" . number_format($taxable_amount, 2) . " × ({$tax_rate} ÷ 100)",
            'result' => "₱" . number_format($tax_amount, 2)
        ],
        [
            'step' => 4,
            'description' => 'Regulatory Fees',
            'formula' => 'Sum of all regulatory fees',
            'calculation' => "Mayor Permit (₱499.98) + Sanitary (₱500) + Registration (₱300)",
            'result' => "₱" . number_format($total_fees, 2)
        ],
        [
            'step' => 5,
            'description' => 'Total Annual Tax',
            'formula' => 'Total Tax = Tax Amount + Regulatory Fees',
            'calculation' => "₱" . number_format($tax_amount, 2) . " + ₱" . number_format($total_fees, 2),
            'result' => "₱" . number_format($total_tax, 2)
        ]
    ];
    
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
            'business_type' => $business_type
        ],
        'config_used' => $config_used,
        'available_configs' => $available_configs,
        'fee_breakdown' => $fees,
        'calculation_steps' => $calculation_steps,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'permit_id' => $permit_id,
            'tax_type' => $tax_type,
            'taxable_amount' => $taxable_amount,
            'business_type' => $business_type
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Calculation error: ' . $e->getMessage()
    ]);
}