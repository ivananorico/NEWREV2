<?php
// revenue2/citizen_dashboard/digital/callback_handler.php
require_once 'config.php';

// This file receives callbacks from the digital payment system
// and updates the external system's database

// Get callback data
$input = $_POST;

$payment_id = $input['payment_id'] ?? '';
$receipt_number = $input['receipt_number'] ?? '';
$is_annual = isset($input['is_annual']) ? (int)$input['is_annual'] : 0;
$tax_id = $input['tax_id'] ?? null;
$property_total_id = $input['property_total_id'] ?? null;
$quarter = $input['quarter'] ?? null;
$year = $input['year'] ?? null;

if (empty($payment_id) || empty($receipt_number)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid callback data']);
    exit();
}

// Log the callback
error_log("Callback received: payment_id=$payment_id, receipt=$receipt_number, annual=$is_annual");

// Determine which system to update based on client_system
$client_system = $input['client_system'] ?? '';

switch ($client_system) {
    case 'RPT':
        updateRPTDatabase($input);
        break;
    case 'TEST':
        // For testing purposes
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Test callback received',
            'data' => $input
        ]);
        break;
    default:
        // Generic success response for unknown systems
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Callback received. External system must handle update.',
            'payment_id' => $payment_id,
            'receipt_number' => $receipt_number,
            'is_annual' => $is_annual
        ]);
}

function updateRPTDatabase($data) {
    // Include RPT database connection
    $rpt_db_path = dirname(dirname(__DIR__)) . '/db/RPT/rpt_db.php';
    
    if (!file_exists($rpt_db_path)) {
        error_log("RPT database file not found: $rpt_db_path");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'RPT system not configured']);
        return;
    }
    
    include_once $rpt_db_path;
    
    try {
        $pdo_rpt = getDatabaseConnection();
        if (!$pdo_rpt) {
            throw new Exception("RPT database connection failed");
        }
        
        $pdo_rpt->beginTransaction();
        
        if ($data['is_annual']) {
            // For annual payment: mark all unpaid quarters as paid
            $stmt = $pdo_rpt->prepare("
                UPDATE quarterly_taxes 
                SET payment_status = 'paid', 
                    payment_date = NOW(),
                    receipt_number = :receipt_number
                WHERE property_total_id = :property_total_id 
                AND payment_status != 'paid'
                AND year = :year
            ");
            $stmt->execute([
                'property_total_id' => $data['property_total_id'],
                'receipt_number' => $data['receipt_number'],
                'year' => $data['year'] ?? date('Y')
            ]);
            
            $affected_rows = $stmt->rowCount();
            error_log("Annual payment: Marked $affected_rows quarters as paid for property_total_id: " . $data['property_total_id']);
            
        } else {
            // For quarterly payment: mark specific quarter as paid
            if (!empty($data['tax_id'])) {
                $stmt = $pdo_rpt->prepare("
                    UPDATE quarterly_taxes 
                    SET payment_status = 'paid', 
                        payment_date = NOW(),
                        receipt_number = :receipt_number
                    WHERE id = :tax_id
                ");
                $stmt->execute([
                    'tax_id' => $data['tax_id'],
                    'receipt_number' => $data['receipt_number']
                ]);
                
                error_log("Quarterly payment: Marked tax_id " . $data['tax_id'] . " as paid");
            }
        }
        
        $pdo_rpt->commit();
        
        // Return success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'RPT database updated successfully',
            'is_annual' => $data['is_annual'],
            'property_total_id' => $data['property_total_id'] ?? null,
            'tax_id' => $data['tax_id'] ?? null
        ]);
        
    } catch (Exception $e) {
        if (isset($pdo_rpt)) {
            $pdo_rpt->rollBack();
        }
        error_log("RPT Update Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update RPT database: ' . $e->getMessage()]);
    }
}
?>