<?php
// revenue2/citizen_dashboard/digital/payment_api.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

$response = ['success' => false, 'message' => 'Invalid action'];

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'request_otp':
            $response = handleRequestOTP();
            break;
            
        case 'verify_otp':
            $response = handleVerifyOTP();
            break;
            
        case 'check_status':
            $response = handleCheckStatus();
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Unknown action'];
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Server error: ' . (ENVIRONMENT === 'development' ? $e->getMessage() : 'Please try again later')
    ];
    
    if (ENVIRONMENT === 'development') {
        error_log("API Error: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
    }
}

echo json_encode($response);

// ============ FUNCTIONS ============

function handleRequestOTP() {
    // Get POST data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        return ['success' => false, 'message' => 'Invalid request data'];
    }
    
    // DEBUG: Log incoming data
    error_log("=== OTP REQUEST DEBUG ===");
    error_log("Incoming data: " . json_encode($data));
    
    // Validate required fields
    $required = ['phone', 'payment_method', 'amount', 'purpose'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $error_msg = "Missing required field: $field";
            error_log("Validation error: $error_msg");
            return ['success' => false, 'message' => $error_msg];
        }
    }
    
    // Clean data
    $phone = clean_input($data['phone']);
    $payment_method = clean_input($data['payment_method']);
    $amount = floatval($data['amount']);
    $purpose = clean_input($data['purpose']);
    $client_system = clean_input($data['client_system'] ?? 'RPT System');
    $client_reference = clean_input($data['client_reference'] ?? '');
    $reference = clean_input($data['reference'] ?? '');
    
    // FIX: Proper phone number validation and formatting
    $phone = formatPhoneNumber($phone);
    
    if (!$phone) {
        $error_msg = 'Invalid phone number format. Please enter 10 or 11 digits.';
        error_log("Phone validation error: $error_msg");
        return ['success' => false, 'message' => $error_msg];
    }
    
    // Generate payment data
    $payment_id = generatePaymentId();
    $otp_code = generateOTP();
    $otp_expires = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    
    // Save to database
    $db = getDigitalDBConnection();
    if (!$db) {
        $error_msg = 'Database connection failed';
        error_log("Database error: $error_msg");
        return ['success' => false, 'message' => $error_msg];
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO payment_transactions (
                payment_id, client_system, client_reference, purpose, amount,
                phone, payment_method, otp_code, otp_expires_at, otp_attempts,
                tax_id, property_total_id, quarter, year, is_annual,
                sync_status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $tax_id = intval($data['tax_id'] ?? 0);
        $property_total_id = intval($data['property_total_id'] ?? 0);
        $quarter = clean_input($data['quarter'] ?? '');
        $year = intval($data['year'] ?? date('Y'));
        $is_annual = isset($data['is_annual']) ? (int)boolval($data['is_annual']) : 0;
        
        $stmt->execute([
            $payment_id,
            $client_system,
            $client_reference,
            $purpose,
            $amount,
            $phone,
            $payment_method,
            $otp_code,
            $otp_expires,
            0,
            $tax_id,
            $property_total_id,
            $quarter,
            $year,
            $is_annual
        ]);
        
        // Log successful save
        error_log("Payment saved to digital DB:");
        error_log("  Payment ID: $payment_id");
        error_log("  Amount: $amount");
        error_log("  Tax ID: $tax_id");
        error_log("  Property Total ID: $property_total_id");
        error_log("  Quarter: $quarter");
        error_log("  Year: $year");
        error_log("  Is Annual: $is_annual");
        error_log("  OTP: $otp_code");
        error_log("============================");
        
        // In development mode, we simulate OTP sending
        if (ENVIRONMENT === 'development') {
            $response = [
                'success' => true,
                'message' => 'OTP sent successfully (simulated)',
                'payment_id' => $payment_id,
                'test_otp' => $otp_code,
                'expires_at' => $otp_expires,
                'formatted_phone' => $phone
            ];
            
        } else {
            // In production, you would send actual SMS here
            $response = [
                'success' => true,
                'message' => 'OTP sent to your phone',
                'payment_id' => $payment_id,
                'expires_at' => $otp_expires
            ];
        }
        
        return $response;
        
    } catch (PDOException $e) {
        error_log("Database Error in handleRequestOTP: " . $e->getMessage());
        return [
            'success' => false, 
            'message' => 'Database error. ' . (ENVIRONMENT === 'development' ? $e->getMessage() : 'Please try again.')
        ];
    }
}

function handleVerifyOTP() {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        error_log("Invalid OTP verification data");
        return ['success' => false, 'message' => 'Invalid request data'];
    }
    
    $payment_id = clean_input($data['payment_id'] ?? '');
    $otp_code = clean_input($data['otp_code'] ?? '');
    
    if (empty($payment_id) || empty($otp_code)) {
        error_log("Missing payment_id or otp_code");
        return ['success' => false, 'message' => 'Payment ID and OTP code are required'];
    }
    
    error_log("=== OTP VERIFICATION DEBUG ===");
    error_log("Payment ID: $payment_id");
    error_log("OTP Code: $otp_code");
    
    $db = getDigitalDBConnection();
    if (!$db) {
        error_log("Digital DB connection failed");
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        // Get transaction
        $stmt = $db->prepare("
            SELECT * FROM payment_transactions 
            WHERE payment_id = ? 
            AND payment_status = 'pending'
        ");
        $stmt->execute([$payment_id]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            error_log("Transaction not found or already processed: $payment_id");
            return ['success' => false, 'message' => 'Transaction not found or already processed'];
        }
        
        // Log transaction details
        error_log("Transaction found:");
        error_log("  Tax ID: " . $transaction['tax_id']);
        error_log("  Property Total ID: " . $transaction['property_total_id']);
        error_log("  Is Annual: " . $transaction['is_annual']);
        error_log("  Quarter: " . $transaction['quarter']);
        error_log("  Year: " . $transaction['year']);
        error_log("  Amount: " . $transaction['amount']);
        
        // Check if OTP is locked
        if ($transaction['otp_locked'] == 1) {
            error_log("OTP locked for payment: $payment_id");
            return ['success' => false, 'message' => 'OTP verification locked. Too many failed attempts.'];
        }
        
        // Check OTP expiry
        $now = date('Y-m-d H:i:s');
        if ($now > $transaction['otp_expires_at']) {
            error_log("OTP expired for payment: $payment_id");
            return ['success' => false, 'message' => 'OTP has expired. Please request a new one.'];
        }
        
        // Verify OTP
        if ($transaction['otp_code'] === $otp_code) {
            // OTP is correct
            $receipt_number = generateReceiptNumber();
            
            error_log("OTP correct! Generating receipt: $receipt_number");
            
            // Update transaction
            $updateStmt = $db->prepare("
                UPDATE payment_transactions 
                SET otp_verified = 1,
                    payment_status = 'paid',
                    receipt_number = ?,
                    paid_at = NOW(),
                    sync_status = 'pending',
                    system_processed = 0
                WHERE payment_id = ?
            ");
            $updateStmt->execute([$receipt_number, $payment_id]);
            
            error_log("Digital payment marked as paid: $payment_id");
            
            // Update RPT quarterly_taxes if tax_id exists (for individual quarter)
            if ($transaction['tax_id'] > 0 && !$transaction['is_annual']) {
                error_log("Attempting to update RPT quarterly tax ID: " . $transaction['tax_id']);
                $result = updateQuarterlyTaxPayment($transaction['tax_id'], $receipt_number, $transaction);
                error_log("Quarterly update result: " . ($result ? "SUCCESS" : "FAILED"));
            } else {
                error_log("No tax_id or is_annual, skipping quarterly update");
            }
            
            // If annual payment, update all UNPAID quarters for the year
            if ($transaction['is_annual'] && $transaction['property_total_id'] > 0) {
                error_log("Attempting annual RPT update:");
                error_log("  Property Total ID: " . $transaction['property_total_id']);
                error_log("  Year: " . $transaction['year']);
                $result = updateAnnualRPTPayment($transaction['property_total_id'], $transaction['year'], $receipt_number, $transaction);
                error_log("Annual update result: " . ($result ? "SUCCESS" : "FAILED"));
            } else {
                error_log("Not an annual payment, skipping annual update");
            }
            
            error_log("=== PAYMENT COMPLETE ===");
            
            return [
                'success' => true,
                'message' => 'Payment successful!',
                'receipt_number' => $receipt_number,
                'payment_id' => $payment_id,
                'amount' => $transaction['amount'],
                'tax_id' => $transaction['tax_id'],
                'is_annual' => $transaction['is_annual']
            ];
            
        } else {
            // OTP is incorrect
            $attempts = $transaction['otp_attempts'] + 1;
            
            error_log("Incorrect OTP. Attempt $attempts of " . MAX_OTP_ATTEMPTS);
            
            $updateStmt = $db->prepare("
                UPDATE payment_transactions 
                SET otp_attempts = ?,
                    last_otp_attempt_at = NOW(),
                    otp_locked = ?
                WHERE payment_id = ?
            ");
            
            // Lock if max attempts reached
            $locked = ($attempts >= MAX_OTP_ATTEMPTS) ? 1 : 0;
            $updateStmt->execute([$attempts, $locked, $payment_id]);
            
            $attempts_left = MAX_OTP_ATTEMPTS - $attempts;
            $message = "Invalid OTP. ";
            $message .= $attempts_left > 0 ? 
                "You have $attempts_left attempt(s) left." : 
                "Account locked due to too many failed attempts.";
            
            error_log("OTP verification failed: $message");
            
            return ['success' => false, 'message' => $message];
        }
        
    } catch (PDOException $e) {
        error_log("Database Error in handleVerifyOTP: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

// NEW FUNCTION: Format phone number properly
function formatPhoneNumber($phone) {
    // Remove all non-digits
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check length
    $length = strlen($phone);
    
    // Handle different formats
    if ($length === 10) {
        // Format: 9123456789 → Convert to 09123456789
        return '0' . $phone;
    } 
    elseif ($length === 11 && $phone[0] === '0') {
        // Format: 09123456789 (already correct)
        return $phone;
    }
    elseif ($length === 12 && substr($phone, 0, 2) === '63') {
        // Format: 639123456789 → Convert to 09123456789
        return '0' . substr($phone, 2);
    }
    elseif ($length === 13 && substr($phone, 0, 3) === '+63') {
        // Format: +639123456789 → Convert to 09123456789
        return '0' . substr($phone, 3);
    }
    
    // Invalid format
    error_log("Invalid phone format: $phone (length: $length)");
    return false;
}

function updateQuarterlyTaxPayment($tax_id, $receipt_number, $transaction) {
    error_log("=== RPT QUARTERLY UPDATE DEBUG ===");
    error_log("Tax ID to update: $tax_id");
    error_log("Receipt Number: $receipt_number");
    
    try {
        // Connect to RPT database
        $db_rpt = new PDO(
            "mysql:host=localhost:3307;dbname=rpt;charset=utf8mb4",
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        // First check if the tax_id exists in quarterly_taxes
        $checkStmt = $db_rpt->prepare("
            SELECT id, quarter, year, payment_status 
            FROM quarterly_taxes 
            WHERE id = ?
        ");
        $checkStmt->execute([$tax_id]);
        $taxRecord = $checkStmt->fetch();
        
        if (!$taxRecord) {
            error_log("ERROR: Tax ID $tax_id not found in quarterly_taxes table!");
            return false;
        }
        
        error_log("Found tax record:");
        error_log("  ID: " . $taxRecord['id']);
        error_log("  Quarter: " . $taxRecord['quarter']);
        error_log("  Year: " . $taxRecord['year']);
        error_log("  Current Status: " . $taxRecord['payment_status']);
        
        // For quarterly payment - mark specific quarter as paid
        $stmt = $db_rpt->prepare("
            UPDATE quarterly_taxes 
            SET payment_status = 'paid',
                payment_date = CURDATE(),
                receipt_number = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$receipt_number, $tax_id]);
        $rowCount = $stmt->rowCount();
        
        error_log("Rows affected: $rowCount");
        
        if ($rowCount > 0) {
            error_log("SUCCESS: RPT Quarterly Tax Updated: tax_id=$tax_id, receipt=$receipt_number");
            
            // Verify the update
            $verifyStmt = $db_rpt->prepare("
                SELECT payment_status, receipt_number, payment_date 
                FROM quarterly_taxes 
                WHERE id = ?
            ");
            $verifyStmt->execute([$tax_id]);
            $updatedRecord = $verifyStmt->fetch();
            
            error_log("Verification:");
            error_log("  New Status: " . $updatedRecord['payment_status']);
            error_log("  Receipt: " . $updatedRecord['receipt_number']);
            error_log("  Date: " . $updatedRecord['payment_date']);
            error_log("===============================");
            
            return true;
        } else {
            error_log("ERROR: No rows updated for tax_id=$tax_id");
            error_log("===============================");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("RPT Database Error (Quarterly): " . $e->getMessage());
        error_log("Error Code: " . $e->getCode());
        logSyncError($transaction['payment_id'], "RPT Quarterly Update Failed: " . $e->getMessage());
        return false;
    }
}

function updateAnnualRPTPayment($property_total_id, $year, $receipt_number, $transaction) {
    error_log("=== RPT ANNUAL UPDATE DEBUG ===");
    error_log("Property Total ID: $property_total_id");
    error_log("Year: $year");
    error_log("Receipt: $receipt_number");
    
    try {
        // Connect to RPT database
        $db_rpt = new PDO(
            "mysql:host=localhost:3307;dbname=rpt;charset=utf8mb4",
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        // First check if property_total_id exists
        $checkPropStmt = $db_rpt->prepare("
            SELECT id FROM property_totals WHERE id = ?
        ");
        $checkPropStmt->execute([$property_total_id]);
        $propExists = $checkPropStmt->fetch();
        
        if (!$propExists) {
            error_log("ERROR: Property Total ID $property_total_id not found!");
            return false;
        }
        
        // For annual payment - mark ONLY UNPAID quarters as paid
        $checkStmt = $db_rpt->prepare("
            SELECT id, quarter, total_quarterly_tax, penalty_amount, payment_status
            FROM quarterly_taxes 
            WHERE property_total_id = ? 
            AND year = ?
            ORDER BY 
                CASE quarter 
                    WHEN 'Q1' THEN 1
                    WHEN 'Q2' THEN 2
                    WHEN 'Q3' THEN 3
                    WHEN 'Q4' THEN 4
                END
        ");
        
        $checkStmt->execute([$property_total_id, $year]);
        $allQuarters = $checkStmt->fetchAll();
        
        if (empty($allQuarters)) {
            error_log("ERROR: No quarterly taxes found for property_total_id=$property_total_id, year=$year");
            return false;
        }
        
        error_log("Found " . count($allQuarters) . " quarters:");
        foreach ($allQuarters as $quarter) {
            error_log("  " . $quarter['quarter'] . " " . $year . 
                     " (ID: " . $quarter['id'] . ") - Status: " . $quarter['payment_status']);
        }
        
        $unpaidQuarters = array_filter($allQuarters, function($q) {
            return $q['payment_status'] != 'paid';
        });
        
        error_log("Unpaid quarters: " . count($unpaidQuarters));
        
        if (empty($unpaidQuarters)) {
            error_log("No unpaid quarters found for annual payment");
            return true;
        }
        
        // Update all unpaid quarters
        $updateStmt = $db_rpt->prepare("
            UPDATE quarterly_taxes 
            SET payment_status = 'paid',
                payment_date = CURDATE(),
                receipt_number = ?,
                discount_applied = ?,
                discount_percent_used = ?,
                discount_amount = ?
            WHERE property_total_id = ? 
            AND year = ? 
            AND payment_status != 'paid'
        ");
        
        $discount_applied = ($transaction['discount_percent'] ?? 0) > 0 ? 1 : 0;
        $updateStmt->execute([
            $receipt_number,
            $discount_applied,
            $transaction['discount_percent'] ?? 0,
            $transaction['discount_amount'] ?? 0,
            $property_total_id,
            $year
        ]);
        
        $updatedCount = $updateStmt->rowCount();
        
        error_log("SUCCESS: Updated $updatedCount quarter(s)");
        error_log("===============================");
        
        return true;
        
    } catch (Exception $e) {
        error_log("RPT Database Error (Annual): " . $e->getMessage());
        logSyncError($transaction['payment_id'], "RPT Annual Update Failed: " . $e->getMessage());
        return false;
    }
}

function logSyncError($payment_id, $error_message) {
    try {
        $db_digital = getDigitalDBConnection();
        if ($db_digital) {
            $error_stmt = $db_digital->prepare("
                UPDATE payment_transactions 
                SET sync_status = 'failed',
                    system_error = ?
                WHERE payment_id = ?
            ");
            $error_stmt->execute([$error_message, $payment_id]);
            error_log("Sync error logged for payment: $payment_id");
        }
    } catch (Exception $e2) {
        error_log("Error logging sync failure: " . $e2->getMessage());
    }
}

function handleCheckStatus() {
    $payment_id = clean_input($_GET['payment_id'] ?? '');
    
    if (empty($payment_id)) {
        return ['success' => false, 'message' => 'Payment ID required'];
    }
    
    $db = getDigitalDBConnection();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        $stmt = $db->prepare("
            SELECT 
                payment_status, 
                receipt_number, 
                amount, 
                paid_at,
                tax_id,
                property_total_id,
                is_annual,
                year,
                quarter,
                sync_status,
                system_error
            FROM payment_transactions 
            WHERE payment_id = ?
        ");
        $stmt->execute([$payment_id]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            return ['success' => false, 'message' => 'Transaction not found'];
        }
        
        // If payment is paid, also check RPT status
        $rpt_status = null;
        if ($transaction['payment_status'] === 'paid') {
            try {
                $db_rpt = new PDO(
                    "mysql:host=localhost:3307;dbname=rpt;charset=utf8mb4",
                    'root',
                    '',
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]
                );
                
                if ($transaction['is_annual']) {
                    // Check annual payment status
                    $rpt_stmt = $db_rpt->prepare("
                        SELECT COUNT(*) as total, 
                               SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count
                        FROM quarterly_taxes 
                        WHERE property_total_id = ? 
                        AND year = ?
                        AND receipt_number = ?
                    ");
                    $rpt_stmt->execute([
                        $transaction['property_total_id'],
                        $transaction['year'],
                        $transaction['receipt_number']
                    ]);
                    $rpt_result = $rpt_stmt->fetch();
                    
                    if ($rpt_result) {
                        $rpt_status = [
                            'type' => 'annual',
                            'updated_quarters' => $rpt_result['paid_count'],
                            'total_quarters' => $rpt_result['total']
                        ];
                    }
                } else if ($transaction['tax_id'] > 0) {
                    // Check quarterly payment status
                    $rpt_stmt = $db_rpt->prepare("
                        SELECT quarter, year, payment_status 
                        FROM quarterly_taxes 
                        WHERE id = ?
                    ");
                    $rpt_stmt->execute([$transaction['tax_id']]);
                    $quarter_result = $rpt_stmt->fetch();
                    
                    if ($quarter_result) {
                        $rpt_status = [
                            'type' => 'quarterly',
                            'quarter' => $quarter_result['quarter'],
                            'year' => $quarter_result['year'],
                            'payment_status' => $quarter_result['payment_status']
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("RPT Status Check Error: " . $e->getMessage());
            }
        }
        
        return [
            'success' => true,
            'payment_status' => $transaction['payment_status'],
            'receipt_number' => $transaction['receipt_number'],
            'amount' => $transaction['amount'],
            'paid_at' => $transaction['paid_at'],
            'tax_id' => $transaction['tax_id'],
            'is_annual' => $transaction['is_annual'],
            'sync_status' => $transaction['sync_status'],
            'system_error' => $transaction['system_error'],
            'rpt_status' => $rpt_status
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error'];
    }
}
?>