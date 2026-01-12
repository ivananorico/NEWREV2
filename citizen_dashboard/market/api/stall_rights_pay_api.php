<?php
// revenue2/citizen_dashboard/market/api/stall_rights_pay_api.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Create detailed log
$log_file = __DIR__ . '/market_payment_debug.log';
$log_message = "\n" . str_repeat("=", 60) . "\n";
$log_message .= "[" . date('Y-m-d H:i:s') . "] MARKET PAYMENT API\n";
$log_message .= str_repeat("-", 60) . "\n";

// Get ALL data
$log_message .= "REQUEST METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n\n";

// Get JSON input
$raw_input = file_get_contents('php://input');
$log_message .= "RAW JSON INPUT:\n" . $raw_input . "\n\n";

// Try to decode
$data = json_decode($raw_input, true);
if (!$data) {
    $data = $_POST;
    $log_message .= "USING POST DATA (JSON failed):\n";
}

$log_message .= "PARSED DATA:\n" . print_r($data, true) . "\n";

// Extract ALL possible ID fields
$possible_ids = [];

// Check all possible fields that could contain the ID
$id_fields = ['reference_id', 'ref', 'id', 'registration_id', 'client_reference', 'quarterly_id'];
foreach ($id_fields as $field) {
    if (isset($data[$field]) && !empty($data[$field])) {
        $possible_ids[$field] = $data[$field];
    }
}

// Also check GET parameters
foreach ($_GET as $key => $value) {
    if (in_array($key, $id_fields)) {
        $possible_ids["GET_$key"] = $value;
    }
}

$log_message .= "POSSIBLE IDs FOUND:\n" . print_r($possible_ids, true) . "\n";

// Try to determine the correct ID
$registration_id = null;

// Priority order for ID extraction
if (isset($data['reference_id'])) {
    $registration_id = $data['reference_id'];
    $log_message .= "Using reference_id: $registration_id\n";
} elseif (isset($data['ref'])) {
    $registration_id = $data['ref'];
    $log_message .= "Using ref: $registration_id\n";
} elseif (isset($data['id'])) {
    $registration_id = $data['id'];
    $log_message .= "Using id: $registration_id\n";
} elseif (isset($_GET['ref'])) {
    $registration_id = $_GET['ref'];
    $log_message .= "Using GET ref: $registration_id\n";
}

// Get other required fields
$receipt_number = $data['receipt_number'] ?? null;
$paid_at = $data['paid_at'] ?? date('Y-m-d H:i:s');
$client_system = $data['client_system'] ?? null;
$payment_id = $data['payment_id'] ?? null;
$amount = $data['amount'] ?? null;

$log_message .= "\nEXTRACTED VALUES:\n";
$log_message .= "- Registration ID: " . ($registration_id ?? 'NULL') . "\n";
$log_message .= "- Receipt: " . ($receipt_number ?? 'NULL') . "\n";
$log_message .= "- System: " . ($client_system ?? 'NULL') . "\n";
$log_message .= "- Payment ID: " . ($payment_id ?? 'NULL') . "\n";
$log_message .= "- Amount: " . ($amount ?? 'NULL') . "\n";

// Validation
if (!$registration_id || !$receipt_number) {
    $response = [
        'status' => 'error',
        'message' => 'Missing required data',
        'debug' => [
            'registration_id' => $registration_id,
            'receipt_number' => $receipt_number,
            'all_data' => $data,
            'possible_ids' => $possible_ids
        ]
    ];
    
    $log_message .= "\nERROR: Missing required data\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    echo json_encode($response);
    exit();
}

if ($client_system !== 'market') {
    $response = [
        'status' => 'error',
        'message' => 'This API is for market system only',
        'received_system' => $client_system
    ];
    
    $log_message .= "\nERROR: Wrong system - Expected: market, Got: $client_system\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    echo json_encode($response);
    exit();
}

// Include database
include_once __DIR__ . '/../../../db/Market/market_db.php';

if (!isset($pdo)) {
    $response = ['status' => 'error', 'message' => 'Database connection failed'];
    
    $log_message .= "\nERROR: Database connection failed\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    echo json_encode($response);
    exit();
}

try {
    $log_message .= "\nDATABASE OPERATIONS:\n";
    
    // Start transaction
    $pdo->beginTransaction();
    $log_message .= "Transaction started\n";
    
    // 1. Check current status
    $log_message .= "Checking rental_registration WHERE id = $registration_id\n";
    
    $check_query = "SELECT id, application_status, stall_rights_no FROM rental_registration WHERE id = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$registration_id]);
    $application = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        // Check if ANY record exists with this ID
        $log_message .= "No record found with ID: $registration_id\n";
        
        // List all rental_registration IDs for debugging
        $all_ids = $pdo->query("SELECT id, application_status, stall_rights_no FROM rental_registration ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $log_message .= "All rental_registration records:\n" . print_r($all_ids, true) . "\n";
        
        throw new Exception("No market application found with ID: $registration_id");
    }
    
    $current_status = $application['application_status'];
    $log_message .= "Found application: ID={$application['id']}, Status=$current_status, Rights No={$application['stall_rights_no']}\n";
    
    // 2. Update if status is 'paying'
    if ($current_status === 'paying') {
        $update_query = "
            UPDATE rental_registration 
            SET application_status = 'paid',  -- ONLY change this to 'paid'
                payment_date = ?,
                reference_number = ?,
                updated_at = NOW()
            WHERE id = ?
            AND application_status = 'paying'
        ";
        
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([$paid_at, $receipt_number, $registration_id]);
        $rows_updated = $update_stmt->rowCount();
        
        $log_message .= "Update executed. Rows affected: $rows_updated\n";
        $log_message .= "ONLY updated rental_registration to 'paid' status\n";
        $log_message .= "NOT activating renter_owner, rent_stall, or stalls tables - keeping them as 'pending'\n";
        
        if ($rows_updated === 0) {
            throw new Exception("Failed to update application. Status may have changed from 'paying'.");
        }
        
        // DO NOT activate related tables - keep them as 'pending'
        // The activation will happen in a separate approval process
        
        // Commit transaction
        $pdo->commit();
        $log_message .= "Transaction committed\n";
        
        // 4. Verify update
        $verify_query = "SELECT id, application_status, reference_number FROM rental_registration WHERE id = ?";
        $verify_stmt = $pdo->prepare($verify_query);
        $verify_stmt->execute([$registration_id]);
        $result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['application_status'] === 'paid') {
            $response = [
                'status' => 'success',
                'message' => 'Market stall payment successful! Application will be reviewed.',
                'note' => 'Renter and stall remain pending until approved by market administrator.',
                'data' => [
                    'registration_id' => $registration_id,
                    'stall_rights_no' => $application['stall_rights_no'],
                    'receipt_number' => $receipt_number,
                    'payment_date' => $paid_at,
                    'old_status' => 'paying',
                    'new_status' => 'paid',
                    'renter_status' => 'pending', // Still pending
                    'stall_status' => 'pending', // Still pending
                    'payment_id' => $payment_id,
                    'amount_paid' => $amount
                ]
            ];
            
            $log_message .= "SUCCESS: Application updated to 'paid'\n";
            $log_message .= "Related tables (renter_owner, rent_stall, stalls) remain as 'pending'\n";
            $log_message .= "Verification: ID={$result['id']}, Status={$result['application_status']}, Receipt={$result['reference_number']}\n";
            
        } else {
            throw new Exception("Verification failed. Status: " . ($result['application_status'] ?? 'unknown'));
        }
        
    } else {
        throw new Exception("Application is not in 'paying' status. Current status: $current_status");
    }
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        $log_message .= "Transaction rolled back due to PDOException\n";
    }
    
    $log_message .= "PDO ERROR: " . $e->getMessage() . "\n";
    $log_message .= "Error Code: " . $e->getCode() . "\n";
    
    $response = [
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'registration_id' => $registration_id
    ];
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        $log_message .= "Transaction rolled back due to Exception\n";
    }
    
    $log_message .= "ERROR: " . $e->getMessage() . "\n";
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'registration_id' => $registration_id
    ];
}

// Log the response
$log_message .= "\nRESPONSE SENT:\n" . json_encode($response, JSON_PRETTY_PRINT) . "\n";
$log_message .= str_repeat("=", 60) . "\n\n";

// Save log
file_put_contents($log_file, $log_message, FILE_APPEND);

// Send response
echo json_encode($response);
?>