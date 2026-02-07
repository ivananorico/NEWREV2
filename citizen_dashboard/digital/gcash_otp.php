<?php
// revenue2/citizen_dashboard/digital/gcash_otp.php
session_start();

// Determine base URL
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
$base_url = $is_localhost 
    ? 'http://localhost/revenue2' 
    : 'https://revenuetreasury.goserveph.com';

// Check if we have required session data
if (!isset($_SESSION['payment_data']) || !isset($_SESSION['phone'])) {
    header('Location: gcash.php');
    exit();
}

// Use absolute path that works on both localhost and server
$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/db/Digital/digital_db.php';

$payment_data = $_SESSION['payment_data'];
$client_system = $payment_data['client_system'];
$reference_id = $payment_data['reference_id'];
$amount = $payment_data['amount'];
$purpose = $payment_data['purpose'];
$callback_url = $payment_data['callback_url'];

$phone = $_SESSION['phone'];
$pending_payment_id = $_SESSION['pending_payment_id'] ?? '';
$otp_attempts = $_SESSION['otp_attempts'] ?? 0;

// Get Digital DB connection
$pdo = getDigitalDB();
if (!$pdo) {
    die("
        <div style='padding: 20px; text-align: center; background: white; border-radius: 10px; max-width: 500px; margin: 50px auto;'>
            <i class='fas fa-exclamation-triangle' style='font-size: 48px; color: #f59e0b;'></i>
            <h2 style='color: #1f2937; margin: 20px 0 10px;'>Database Connection Error</h2>
            <p style='color: #6b7280; margin-bottom: 20px;'>Please try again in a few minutes.</p>
            <a href='index.php' style='display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>
                <i class='fas fa-arrow-left'></i> Go Back
            </a>
        </div>
    ");
}

// =====================================================
// IPROGSMS API CONFIGURATION
// =====================================================
$sms_api_url = 'https://www.iprogsms.com/api/v1/sms_messages';
// REPLACE THIS WITH YOUR ACTUAL IPROGSMS API TOKEN FROM YOUR DASHBOARD
$sms_api_token = '6385447a579621033dea98f3667fb6d2eeba8cb0'; // Example - USE YOUR OWN TOKEN        

// Function to send SMS via iProgSMS API
function sendOTPviaSMS($phone, $otp, $amount, $reference_id) {
    global $sms_api_url, $sms_api_token;
    
    // Format phone number - iProgSMS expects 639XXXXXXXXX format
    $formatted_phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert to 639 format if needed
    if (strlen($formatted_phone) === 11 && substr($formatted_phone, 0, 2) === '09') {
        // 09171234567 -> 639171234567
        $formatted_phone = '63' . substr($formatted_phone, 1);
    } elseif (strlen($formatted_phone) === 10 && substr($formatted_phone, 0, 1) === '9') {
        // 9171234567 -> 639171234567
        $formatted_phone = '63' . $formatted_phone;
    } elseif (strlen($formatted_phone) === 13 && substr($formatted_phone, 0, 4) === '+639') {
        // +639171234567 -> 639171234567
        $formatted_phone = substr($formatted_phone, 1);
    }
    
    // Verify phone format is correct
    if (strlen($formatted_phone) !== 12 || substr($formatted_phone, 0, 3) !== '639') {
        return [
            'success' => false,
            'error' => "Invalid phone format. Expected 639XXXXXXXXX, got $formatted_phone"
        ];
    }
    
    // Prepare message - AVOID TRIGGER WORDS LIKE "OTP", "PAYMENT", "BANK", "VERIFICATION"
    $message = "Your LGU payment code: $otp\n";
    $message .= "Use within 2 minutes\n"; // CHANGED TO 2 MINUTES
    $message .= "Amt: ₱" . number_format($amount, 2) . "\n";
    $message .= "ID: $reference_id\n";
    $message .= "Do not share";
    
    // Prepare API data exactly as shown in iProgSMS documentation
    $data = [
        'api_token' => $sms_api_token,
        'message' => $message,
        'phone_number' => $formatted_phone
    ];
    
    // Send SMS via cURL
    $ch = curl_init($sms_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Debug: Log what happened
    error_log("iProgSMS API Response: HTTP $http_code - " . substr($response, 0, 200));
    
    // Check for cURL errors
    if ($curl_error) {
        return [
            'success' => false,
            'error' => "Network error: $curl_error",
            'http_code' => $http_code,
            'raw_response' => $response
        ];
    }
    
    // Try to parse JSON response
    $response_data = json_decode($response, true);
    
    if ($http_code === 200) {
        if (is_array($response_data)) {
            // Check for success
            if (isset($response_data['status']) && $response_data['status'] === 'success') {
                return [
                    'success' => true,
                    'response' => $response_data,
                    'formatted_phone' => $formatted_phone,
                    'message_sent' => $message
                ];
            } elseif (isset($response_data['success']) && $response_data['success'] === true) {
                return [
                    'success' => true,
                    'response' => $response_data,
                    'formatted_phone' => $formatted_phone,
                    'message_sent' => $message
                ];
            } elseif (isset($response_data['message_id'])) {
                return [
                    'success' => true,
                    'response' => $response_data,
                    'formatted_phone' => $formatted_phone,
                    'message_sent' => $message
                ];
            } else {
                // Check for error messages
                $error_msg = "API Error";
                if (isset($response_data['message'])) {
                    if (is_array($response_data['message'])) {
                        $error_msg .= ": " . implode(', ', $response_data['message']);
                    } else {
                        $error_msg .= ": " . $response_data['message'];
                    }
                }
                return [
                    'success' => false,
                    'error' => $error_msg,
                    'http_code' => $http_code,
                    'response' => $response_data,
                    'formatted_phone' => $formatted_phone
                ];
            }
        } else {
            // If response is not JSON but HTTP 200, assume success
            return [
                'success' => true,
                'response' => $response,
                'formatted_phone' => $formatted_phone,
                'message_sent' => $message
            ];
        }
    } else {
        // HTTP error
        $error_msg = "HTTP Error $http_code";
        if (is_array($response_data) && isset($response_data['message'])) {
            if (is_array($response_data['message'])) {
                $error_msg .= ": " . implode(', ', $response_data['message']);
            } else {
                $error_msg .= ": " . $response_data['message'];
            }
        }
        return [
            'success' => false,
            'error' => $error_msg,
            'http_code' => $http_code,
            'response' => $response_data ?? $response,
            'formatted_phone' => $formatted_phone
        ];
    }
}

// =====================================================
// GET OTP FROM DATABASE AND SEND SMS
// =====================================================
$generated_otp = '';
$otp_expires = $_SESSION['otp_expires'] ?? 0;
$otp_sent_status = false;

if ($pending_payment_id) {
    try {
        $query = "SELECT otp_code, created_at, sms_sent FROM payment_transactions WHERE payment_id = :payment_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':payment_id' => $pending_payment_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['otp_code'])) {
            $generated_otp = $result['otp_code'];
            $_SESSION['generated_otp'] = $generated_otp;
            
            // FIX: Always reset OTP expiry to 2 minutes from NOW when page loads
            $otp_expires = time() + (2 * 60); // 2 minutes from current time
            $_SESSION['otp_expires'] = $otp_expires;
            
            // Send SMS if not already sent
            if (empty($result['sms_sent'])) {
                $sms_result = sendOTPviaSMS($phone, $generated_otp, $amount, $reference_id);
                $otp_sent_status = $sms_result['success'];
                
                // Update SMS status in database
                $update_sms = "
                    UPDATE payment_transactions 
                    SET sms_sent = :sms_sent,
                        sms_response = :sms_response,
                        callback_response = CONCAT(COALESCE(callback_response, ''), ' | SMS sent at " . date('Y-m-d H:i:s') . " - Status: ', :sms_status)
                    WHERE payment_id = :payment_id
                ";
                
                $sms_status = $sms_result['success'] ? 'sent' : 'failed';
                $stmt_sms = $pdo->prepare($update_sms);
                $stmt_sms->execute([
                    ':payment_id' => $pending_payment_id,
                    ':sms_sent' => $sms_result['success'] ? 1 : 0,
                    ':sms_response' => json_encode($sms_result),
                    ':sms_status' => $sms_status
                ]);
                
                // Store SMS result in session for display
                if ($sms_result['success']) {
                    $_SESSION['sms_message'] = 'Verification code sent to ' . $phone;
                }
                // No error message shown for failed SMS
            } else {
                $otp_sent_status = true;
            }
        } else {
            header('Location: gcash.php');
            exit();
        }
    } catch (PDOException $e) {
        header('Location: gcash.php');
        exit();
    }
} else {
    header('Location: gcash.php');
    exit();
}

// Check if OTP already expired
if (time() > $otp_expires) {
    try {
        $update_query = "
            UPDATE payment_transactions 
            SET payment_status = 'expired',
                callback_response = :response
            WHERE payment_id = :payment_id AND payment_status = 'pending'
        ";
        
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([
            ':payment_id' => $pending_payment_id,
            ':response' => 'OTP expired at ' . date('Y-m-d H:i:s')
        ]);
    } catch (PDOException $e) {
        // Silent fail
    }
    
    unset($_SESSION['generated_otp']);
    $_SESSION['otp_error'] = 'Verification code has expired. Please request a new one.';
    header('Location: gcash.php');
    exit();
}

// =====================================================
// HANDLE FORM SUBMISSION
// =====================================================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = $_POST['otp'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify_otp') {
        if (strlen($entered_otp) !== 6 || !is_numeric($entered_otp)) {
            $error = 'Please enter a valid 6-digit verification code';
        } elseif ($entered_otp !== $generated_otp) {
            $_SESSION['otp_attempts'] = $otp_attempts + 1;
            $attempts_left = 3 - ($otp_attempts + 1);
            
            try {
                $update_query = "
                    UPDATE payment_transactions 
                    SET callback_response = CONCAT(COALESCE(callback_response, ''), ' | Failed verification attempt #" . ($otp_attempts + 1) . " at " . date('Y-m-d H:i:s') . "')
                    WHERE payment_id = :payment_id
                ";
                
                $stmt = $pdo->prepare($update_query);
                $stmt->execute([':payment_id' => $pending_payment_id]);
            } catch (PDOException $e) {
                // Silent fail
            }
            
            if ($attempts_left > 0) {
                $error = "Incorrect code. {$attempts_left} attempt(s) remaining.";
            } else {
                try {
                    $update_query = "
                        UPDATE payment_transactions 
                        SET payment_status = 'failed',
                            callback_response = CONCAT(COALESCE(callback_response, ''), ' | Payment failed - Maximum verification attempts exceeded')
                        WHERE payment_id = :payment_id
                    ";
                    
                    $stmt = $pdo->prepare($update_query);
                    $stmt->execute([':payment_id' => $pending_payment_id]);
                } catch (PDOException $e) {
                    // Silent fail
                }
                
                unset($_SESSION['generated_otp']);
                unset($_SESSION['otp_expires']);
                unset($_SESSION['otp_attempts']);
                unset($_SESSION['phone']);
                unset($_SESSION['payment_data']);
                unset($_SESSION['pending_payment_id']);
                
                $_SESSION['otp_error'] = 'Maximum verification attempts exceeded. Please start over.';
                header('Location: gcash.php');
                exit();
            }
        } else {
            // OTP is correct - Process payment
            try {
                $receipt_number = 'RCPT-' . date('YmdHis') . '-' . rand(1000, 9999);
                $paid_at = date('Y-m-d H:i:s');
                
                // SEND CALLBACK
                $callback_response = '';
                $http_code = 0;
                
                if (!empty($callback_url)) {
                    $callback_data = [
                        'reference_id' => $reference_id,
                        'amount' => $amount,
                        'purpose' => $purpose,
                        'receipt_number' => $receipt_number,
                        'paid_at' => $paid_at,
                        'payment_id' => $pending_payment_id,
                        'client_system' => $client_system,
                        'payment_status' => 'paid',
                        'payment_method' => 'gcash',
                        'phone' => $phone
                    ];
                    
                    $ch = curl_init($callback_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($callback_data));
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    
                    $callback_response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                }
                
                // UPDATE PAYMENT TO PAID
                $update_query = "
                    UPDATE payment_transactions 
                    SET payment_status = 'paid',
                        otp_verified = 1,
                        receipt_number = :receipt_number,
                        paid_at = :paid_at,
                        callback_sent = :callback_sent,
                        callback_response = :callback_response
                    WHERE payment_id = :payment_id
                ";
                
                $callback_response_text = 'Payment successful at ' . date('Y-m-d H:i:s');
                $callback_response_text .= ' | Callback HTTP: ' . $http_code;
                
                $stmt = $pdo->prepare($update_query);
                $stmt->execute([
                    ':payment_id' => $pending_payment_id,
                    ':receipt_number' => $receipt_number,
                    ':paid_at' => $paid_at,
                    ':callback_sent' => !empty($callback_url) ? 1 : 0,
                    ':callback_response' => $callback_response_text
                ]);
                
                // Store receipt data
                $_SESSION['receipt_data'] = [
                    'payment_id' => $pending_payment_id,
                    'receipt_number' => $receipt_number,
                    'amount' => $amount,
                    'purpose' => $purpose,
                    'phone' => $phone,
                    'paid_at' => $paid_at,
                    'client_system' => $client_system
                ];
                
                // Clear session data
                unset($_SESSION['generated_otp']);
                unset($_SESSION['otp_expires']);
                unset($_SESSION['otp_attempts']);
                unset($_SESSION['phone']);
                unset($_SESSION['pending_payment_id']);
                
                // Redirect to success
                header('Location: success_gcash.php');
                exit();
                
            } catch (PDOException $e) {
                $error = 'Payment processing error. Please try again.';
                
                try {
                    $update_query = "
                        UPDATE payment_transactions 
                        SET payment_status = 'failed',
                            callback_response = CONCAT(COALESCE(callback_response, ''), ' | Payment processing error: ', :error)
                        WHERE payment_id = :payment_id
                    ";
                    
                    $stmt = $pdo->prepare($update_query);
                    $stmt->execute([
                        ':payment_id' => $pending_payment_id,
                        ':error' => $e->getMessage()
                    ]);
                } catch (PDOException $update_error) {
                    // Silent fail
                }
            }
        }
    } elseif ($action === 'cancel') {
        try {
            $update_query = "
                UPDATE payment_transactions 
                SET payment_status = 'cancelled',
                    callback_response = CONCAT(COALESCE(callback_response, ''), ' | User cancelled payment at " . date('Y-m-d H:i:s') . "')
                WHERE payment_id = :payment_id
            ";
            
            $stmt = $pdo->prepare($update_query);
            $stmt->execute([':payment_id' => $pending_payment_id]);
        } catch (PDOException $e) {
            // Silent fail
        }
        
        unset($_SESSION['payment_data']);
        unset($_SESSION['phone']);
        unset($_SESSION['generated_otp']);
        unset($_SESSION['otp_expires']);
        unset($_SESSION['otp_attempts']);
        unset($_SESSION['pending_payment_id']);
        
        header('Location: index.php');
        exit();
    }
}

// =====================================================
// DISPLAY PAGE
// =====================================================
$remaining_time = max(0, $otp_expires - time());
$minutes = floor($remaining_time / 60);
$seconds = $remaining_time % 60;

// Check for SMS messages
$sms_message = $_SESSION['sms_message'] ?? '';
unset($_SESSION['sms_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCash Payment - LGU Digital Payment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gcash-header {
            background: linear-gradient(135deg, #0074E4 0%, #00A9FF 100%);
        }
        .otp-input { 
            letter-spacing: 10px; 
            font-size: 24px; 
            text-align: center;
            font-family: monospace;
        }
        .timer-expired { color: #ef4444; font-weight: bold; }
        
        /* Minimal OTP button styles */
        .otp-mini-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.1);
            border: 2px solid rgba(59, 130, 246, 0.3);
            color: #3b82f6;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .otp-mini-btn:hover {
            background: rgba(59, 130, 246, 0.2);
            border-color: rgba(59, 130, 246, 0.5);
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .otp-mini-btn:active {
            transform: scale(0.95);
        }
        
        /* OTP Modal Styles */
        .otp-modal-container {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .otp-modal-content {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 28rem;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .hidden {
            display: none !important;
        }
        
        /* Animation for modal */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .otp-modal-content {
            animation: fadeIn 0.3s ease;
        }
        
        /* Disabled button style */
        .btn-disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Minimal OTP Button (Small circle, no text) -->
    <div id="otpMiniBtn" class="otp-mini-btn" title="Show OTP">
        <i class="fas fa-key"></i>
    </div>

    <!-- OTP Modal -->
    <div id="otpModal" class="otp-modal-container hidden">
        <div class="otp-modal-content">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-key mr-2 text-blue-600"></i> Verification Code
                    </h2>
                    <button type="button" id="closeOtpModal" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                
                <div class="text-center mb-6">
                    <div class="text-4xl font-bold text-blue-600 tracking-widest mb-4 p-4 bg-blue-50 rounded-lg">
                        <?php echo $generated_otp; ?>
                    </div>
                    
                    <!-- Copy Button -->
                    <button id="copyOtpBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-medium mb-4 transition">
                        <i class="far fa-copy mr-2"></i> Copy OTP to Clipboard
                    </button>
                    
                    <!-- Timer -->
                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-clock mr-1"></i>
                            Code expires in: <span id="modal-otp-timer" class="font-medium text-blue-600"><?php echo sprintf('%02d:%02d', $minutes, $seconds); ?></span>
                        </p>
                    </div>
                </div>
                
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-lightbulb text-blue-600 mt-1 mr-2"></i>
                        <div class="text-sm text-blue-700">
                            <p>This OTP is for testing purposes. Copy and paste it into the verification field.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-md mx-auto p-4">
        <!-- GCASH HEADER -->
        <div class="gcash-header text-white rounded-t-2xl p-6 shadow-lg">
            <div class="flex items-center gap-3">
                <img src="images/gcash_img.jpg" class="w-14 h-14 rounded-lg shadow-md bg-white p-1" />
                <div>
                    <h1 class="text-2xl font-bold">Pay with GCash</h1>
                    <p class="text-blue-100 text-sm">Step 2: Enter Verification Code</p>
                </div>
            </div>

            <!-- SMS Status - Always show success message -->
            <div class="mt-4 bg-blue-900/30 rounded-lg p-3">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2 text-blue-300"></i>
                    <span class="text-blue-100">Enter the 6-digit verification code</span>
                </div>
            </div>
        </div>

        <!-- CONTENT CARD -->
        <div class="bg-white rounded-b-2xl shadow-lg p-6">
            <!-- PAYMENT DETAILS -->
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Payment Details</h2>

            <div class="space-y-3 text-sm mb-6">
                <div class="flex justify-between">
                    <span class="text-gray-600">System:</span>
                    <span class="font-bold text-blue-600"><?php echo strtoupper(htmlspecialchars($client_system)); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Reference:</span>
                    <span class="font-medium"><?php echo htmlspecialchars($reference_id); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Amount:</span>
                    <span class="font-bold text-green-600">₱<?php echo number_format($amount, 2); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Mobile:</span>
                    <span class="font-medium"><?php echo htmlspecialchars($phone); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Purpose:</span>
                    <span class="font-medium text-right"><?php echo htmlspecialchars($purpose); ?></span>
                </div>
            </div>

            <!-- SMS MESSAGES - Only show success -->
            <?php if ($sms_message): ?>
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-3"></i>
                        <p class="text-green-700"><?php echo $sms_message; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- OTP TIMER -->
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl text-center">
                <p class="text-sm text-gray-600 mb-2">
                    <i class="fas fa-clock mr-1"></i>
                    Code expires in: <span id="otp-timer" class="font-medium text-blue-600"><?php echo sprintf('%02d:%02d', $minutes, $seconds); ?></span>
                </p>
                <p class="text-xs text-gray-500">Code valid for 2 minutes only</p> <!-- CHANGED TO 2 MINUTES -->
            </div>

            <!-- ERROR -->
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- OTP FORM -->
            <form method="POST" action="" id="otpForm">
                <input type="hidden" name="action" value="verify_otp">
                
                <!-- OTP Input -->
                <label for="otp" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-key mr-1"></i> Enter 6-digit Verification Code
                </label>

                <input type="text" 
                       id="otp" 
                       name="otp" 
                       placeholder="000000" 
                       maxlength="6"
                       pattern="[0-9]{6}"
                       required
                       autocomplete="off"
                       inputmode="numeric"
                       class="otp-input w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-2">
                
                <p class="text-xs text-gray-500 mb-6">
                    <i class="fas fa-mobile-alt mr-1"></i>
                    Enter the verification code
                </p>

                <!-- Buttons -->
                <div class="space-y-3">
                    <button type="submit" id="submitBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-bold text-lg shadow-md transition">
                        <i class="fas fa-check-circle mr-2"></i> Complete Payment
                    </button>
                    
                    <div class="flex space-x-3">
                        <!-- No Resend button - removed -->
                        <button type="submit" name="action" value="cancel" id="cancelBtn" class="w-full border border-red-300 hover:bg-red-50 text-red-600 py-3 rounded-lg">
                            <i class="fas fa-times mr-2"></i> Cancel Payment
                        </button>
                    </div>
                </div>
            </form>

            <!-- SECURITY -->
            <div class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 text-sm">
                <i class="fas fa-shield-alt mr-2 text-blue-600"></i>
                Do not share this code with anyone. GCash will never ask for this code.
            </div>
        </div>
    </div>

    <script>
        // Timer functionality - ALWAYS START FROM 2:00
        let totalSeconds = 120; // Fixed 2 minutes (120 seconds)
        const otpTimer = document.getElementById('otp-timer');
        const modalOtpTimer = document.getElementById('modal-otp-timer');
        
        function updateTimer() {
            if (totalSeconds <= 0) {
                otpTimer.textContent = "00:00";
                otpTimer.classList.add('timer-expired');
                if (modalOtpTimer) modalOtpTimer.textContent = "00:00";
                document.getElementById('otp').disabled = true;
                document.getElementById('submitBtn').disabled = true;
                document.getElementById('submitBtn').textContent = 'Code Expired';
                document.getElementById('submitBtn').classList.add('bg-gray-400', 'cursor-not-allowed', 'btn-disabled');
                
                setTimeout(() => {
                    window.location.href = 'gcash.php?expired=1';
                }, 2000);
                return;
            }
            
            const min = Math.floor(totalSeconds / 60);
            const sec = totalSeconds % 60;
            const timerText = `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
            
            otpTimer.textContent = timerText;
            if (modalOtpTimer) modalOtpTimer.textContent = timerText;
            
            if (totalSeconds < 60) {
                otpTimer.classList.add('timer-expired');
            }
            
            totalSeconds--;
        }
        
        // Start timer
        setInterval(updateTimer, 1000);
        
        // Focus on OTP input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('otp').focus();
        });
        
        // Auto-submit when 6 digits entered
        document.getElementById('otp').addEventListener('input', function(e) {
            if (this.value.length === 6) {
                document.getElementById('submitBtn').click();
            }
        });

        // Format OTP input (numbers only)
        document.getElementById('otp').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });
        
        // ============================================
        // OTP MODAL FUNCTIONS
        // ============================================
        const otpMiniBtn = document.getElementById('otpMiniBtn');
        const otpModal = document.getElementById('otpModal');
        const closeOtpModalBtn = document.getElementById('closeOtpModal');
        const copyOtpBtn = document.getElementById('copyOtpBtn');
        
        if (otpMiniBtn) {
            otpMiniBtn.addEventListener('click', function() {
                if (otpModal) {
                    otpModal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }
            });
        }
        
        if (closeOtpModalBtn) {
            closeOtpModalBtn.addEventListener('click', function() {
                if (otpModal) {
                    otpModal.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            });
        }
        
        // Close modal when clicking outside
        if (otpModal) {
            otpModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            });
        }
        
        // Copy OTP to clipboard
        if (copyOtpBtn) {
            copyOtpBtn.addEventListener('click', function() {
                const otpCode = '<?php echo $generated_otp; ?>';
                
                navigator.clipboard.writeText(otpCode).then(function() {
                    // Show success message
                    const originalText = copyOtpBtn.innerHTML;
                    copyOtpBtn.innerHTML = '<i class="fas fa-check mr-2"></i> Copied!';
                    copyOtpBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                    copyOtpBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                    
                    setTimeout(function() {
                        copyOtpBtn.innerHTML = originalText;
                        copyOtpBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                        copyOtpBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    }, 2000);
                    
                    // Auto-fill OTP input
                    const otpInput = document.getElementById('otp');
                    if (otpInput) {
                        otpInput.value = otpCode;
                        // Auto-focus on OTP input after copying
                        setTimeout(() => {
                            otpInput.focus();
                            otpInput.select();
                        }, 100);
                    }
                    
                    // Auto-close modal after 1 second
                    setTimeout(() => {
                        if (otpModal && !otpModal.classList.contains('hidden')) {
                            otpModal.classList.add('hidden');
                            document.body.style.overflow = '';
                        }
                    }, 1000);
                    
                }).catch(function(err) {
                    console.error('Failed to copy OTP: ', err);
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = otpCode;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    
                    // Show copied message anyway
                    const originalText = copyOtpBtn.innerHTML;
                    copyOtpBtn.innerHTML = '<i class="fas fa-check mr-2"></i> Copied!';
                    copyOtpBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                    copyOtpBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                    
                    setTimeout(function() {
                        copyOtpBtn.innerHTML = originalText;
                        copyOtpBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                        copyOtpBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    }, 2000);
                });
            });
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && otpModal && !otpModal.classList.contains('hidden')) {
                otpModal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });
        
        // Auto-fill OTP from modal when opened
        if (otpMiniBtn) {
            otpMiniBtn.addEventListener('click', function() {
                setTimeout(() => {
                    const otpInput = document.getElementById('otp');
                    if (otpInput) {
                        // Don't auto-fill, but focus on input
                        otpInput.focus();
                        otpInput.select();
                    }
                }, 300);
            });
        }
    </script>
</body>
</html>