<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Test endpoint
if (isset($_GET['test'])) {
    echo json_encode([
        'status' => 'success',
        'message' => 'API is working',
        'test' => true,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

try {
    // Include database
    require_once '../../../db/Business/business_db.php';
    
    // Test database connection
    if (!isset($pdo)) {
        throw new Exception("Database connection not established");
    }
    
    // Test query
    $testStmt = $pdo->query("SELECT 1");
    if (!$testStmt) {
        throw new Exception("Database test query failed");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit();
}

try {
    // Get ID from GET parameter
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    // Debug: Log what we received
    error_log("Permit ID requested: " . $id);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Valid Permit ID is required',
            'received_id' => $_GET['id'] ?? 'none',
            'usage' => 'Use: get_permit_details.php?id=1'
        ]);
        exit();
    }
    
    // 1. Get permit details - SIMPLIFIED QUERY
    $sql = "SELECT * FROM business_permits WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare SQL statement");
    }
    
    $stmt->execute([$id]);
    $permit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permit) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Permit with ID $id not found in database"
        ]);
        exit();
    }
    
    // 2. Calculate simple tax breakdown (without complex queries first)
    $taxBreakdown = [
        'base_amount' => floatval($permit['taxable_amount']),
        'tax_type' => $permit['tax_calculation_type'],
        'business_type' => $permit['business_type'],
        'tax_rate_used' => floatval($permit['tax_rate']),
        'tax_amount' => floatval($permit['tax_amount']),
        'regulatory_fees' => floatval($permit['regulatory_fees']),
        'total_tax' => floatval($permit['total_tax']),
        'calculation_steps' => [
            [
                'step' => 1,
                'description' => 'Tax Calculation',
                'formula' => 'Taxable Amount × Tax Rate',
                'calculation' => number_format($permit['taxable_amount'], 2) . ' × ' . $permit['tax_rate'] . '%',
                'result' => number_format($permit['tax_amount'], 2)
            ],
            [
                'step' => 2,
                'description' => 'Regulatory Fees',
                'formula' => 'Fixed Fees',
                'calculation' => 'Included regulatory charges',
                'result' => number_format($permit['regulatory_fees'], 2)
            ],
            [
                'step' => 3,
                'description' => 'Total Tax',
                'formula' => 'Tax + Fees',
                'calculation' => number_format($permit['tax_amount'], 2) . ' + ' . number_format($permit['regulatory_fees'], 2),
                'result' => number_format($permit['total_tax'], 2)
            ]
        ]
    ];
    
    // 3. Try to get configuration details (but don't fail if it doesn't work)
    $configUsed = [
        'tax_config' => null,
        'regulatory_fees' => [],
        'calculation_date' => date('Y-m-d')
    ];
    
    try {
        // Try to get tax config
        if ($permit['tax_calculation_type'] === 'capital_investment') {
            $sql = "SELECT * FROM capital_investment_tax_config 
                    WHERE ? >= min_amount AND ? <= max_amount
                    AND (expiration_date IS NULL OR expiration_date = '' OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$permit['taxable_amount'], $permit['taxable_amount']]);
            $configUsed['tax_config'] = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT * FROM gross_sales_tax_config 
                    WHERE business_type = ?
                    AND (expiration_date IS NULL OR expiration_date = '' OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$permit['business_type']]);
            $configUsed['tax_config'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Try to get regulatory fees
        $sql = "SELECT * FROM regulatory_fee_config 
                WHERE (expiration_date IS NULL OR expiration_date = '' OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())";
        $stmt = $pdo->query($sql);
        $configUsed['regulatory_fees'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        // If config queries fail, just continue without them
        error_log("Configuration query failed: " . $e->getMessage());
    }
    
    // Return success response
    $response = [
        'status' => 'success',
        'message' => 'Permit details retrieved successfully',
        'permit' => $permit,
        'tax_breakdown' => $taxBreakdown,
        'config_used' => $configUsed,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database query error: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
    error_log("PDO Error: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    error_log("General Error: " . $e->getMessage());
}
?>