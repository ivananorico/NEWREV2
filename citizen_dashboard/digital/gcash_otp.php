<?php
// revenue2/citizen_dashboard/digital/gcash_otp.php
session_start();

// Check if we have phone and OTP data
if (!isset($_SESSION['payment_data']) || !isset($_SESSION['phone']) || !isset($_SESSION['generated_otp'])) {
    header('Location: gcash.php');
    exit();
}

$payment_data = $_SESSION['payment_data'];
$quarterly_id = $payment_data['quarterly_id'];
$amount = $payment_data['amount'];
$purpose = $payment_data['purpose'];
$phone = $_SESSION['phone'];
$generated_otp = $_SESSION['generated_otp'];
$otp_expires = $_SESSION['otp_expires'] ?? 0;
$otp_attempts = $_SESSION['otp_attempts'] ?? 0;

// Check if OTP expired
if (time() > $otp_expires) {
    unset($_SESSION['generated_otp']);
    $_SESSION['otp_error'] = 'OTP has expired. Please request a new one.';
    header('Location: gcash.php');
    exit();
}

// Check if max attempts reached
if ($otp_attempts >= 3) {
    unset($_SESSION['generated_otp']);
    $_SESSION['otp_error'] = 'Maximum OTP attempts exceeded. Please start over.';
    header('Location: gcash.php');
    exit();
}

// Initialize variables
$error = '';
$success = false;
$receipt_number = '';
$payment_id = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = $_POST['otp'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify_otp') {
        if (strlen($entered_otp) !== 6 || !is_numeric($entered_otp)) {
            $error = 'Please enter a valid 6-digit OTP';
        } elseif ($entered_otp !== $generated_otp) {
            // Increment attempts
            $_SESSION['otp_attempts'] = $otp_attempts + 1;
            $attempts_left = 3 - ($otp_attempts + 1);
            
            if ($attempts_left > 0) {
                $error = "Incorrect OTP. {$attempts_left} attempt(s) remaining.";
            } else {
                unset($_SESSION['generated_otp']);
                $_SESSION['otp_error'] = 'Maximum OTP attempts exceeded. Please start over.';
                header('Location: gcash.php');
                exit();
            }
        } else {
            // OTP is correct - Process payment
            try {
                // Generate receipt and payment IDs
                $receipt_number = 'RCPT-' . date('YmdHis') . '-' . rand(1000, 9999);
                $payment_id = 'GCASH-' . date('YmdHis') . '-' . rand(1000, 9999);
                $paid_at = date('Y-m-d H:i:s');
                
                // 1. Store in digital database
                $digital_pdo = new PDO('mysql:host=localhost;port=3307;dbname=digital;charset=utf8mb4', 'root', '');
                $digital_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $insert_query = "
                    INSERT INTO payment_transactions 
                    (payment_id, client_system, client_reference, purpose, amount, phone, 
                     payment_method, payment_status, otp_code, otp_verified, receipt_number, 
                     created_at, paid_at)
                    VALUES 
                    (:payment_id, :client_system, :client_reference, :purpose, :amount, :phone,
                     :payment_method, :payment_status, :otp_code, :otp_verified, :receipt_number,
                     :created_at, :paid_at)
                ";
                
                $stmt = $digital_pdo->prepare($insert_query);
                $stmt->execute([
                    ':payment_id' => $payment_id,
                    ':client_system' => 'RPT System',
                    ':client_reference' => $quarterly_id,
                    ':purpose' => $purpose,
                    ':amount' => $amount,
                    ':phone' => $phone,
                    ':payment_method' => 'gcash',
                    ':payment_status' => 'paid',
                    ':otp_code' => $generated_otp,
                    ':otp_verified' => 1,
                    ':receipt_number' => $receipt_number,
                    ':created_at' => $paid_at,
                    ':paid_at' => $paid_at
                ]);
                
                // 2. Call RPT API to update RPT database
                $api_response = callRPTAPI($quarterly_id, $amount, $purpose, $receipt_number, $paid_at);
                
                // Check if API call was successful
                if ($api_response['status'] !== 'success') {
                    // Update status to 'pending_retry' if API failed
                    $update_stmt = $digital_pdo->prepare("
                        UPDATE payment_transactions 
                        SET payment_status = 'pending_retry'
                        WHERE payment_id = :payment_id
                    ");
                    
                    $update_stmt->execute([
                        ':payment_id' => $payment_id
                    ]);
                    
                    $_SESSION['api_error'] = $api_response;
                }
                
                // Store receipt data in session for success page
                $_SESSION['receipt_data'] = [
                    'payment_id' => $payment_id,
                    'receipt_number' => $receipt_number,
                    'amount' => $amount,
                    'purpose' => $purpose,
                    'phone' => $phone,
                    'paid_at' => $paid_at,
                    'api_status' => $api_response['status'] ?? 'unknown'
                ];
                
                // Clear OTP session data
                unset($_SESSION['generated_otp']);
                unset($_SESSION['otp_expires']);
                unset($_SESSION['otp_attempts']);
                unset($_SESSION['phone']);
                
                // Redirect to success page
                header('Location: success_gcash.php');
                exit();
                
            } catch (PDOException $e) {
                $error = 'Payment processing error: ' . $e->getMessage();
                error_log("Database error: " . $e->getMessage());
            }
        }
    } elseif ($action === 'resend_otp') {
        // Generate new OTP
        $new_otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['generated_otp'] = $new_otp;
        $_SESSION['otp_expires'] = time() + (5 * 60);
        $_SESSION['otp_attempts'] = 0;
        
        // Update variables
        $generated_otp = $new_otp;
        $otp_expires = $_SESSION['otp_expires'];
        $otp_attempts = 0;
    }
}

// Calculate remaining time
$remaining_time = max(0, $otp_expires - time());
$minutes = floor($remaining_time / 60);
$seconds = $remaining_time % 60;

// Function to call RPT API - CORRECTED URL
function callRPTAPI($quarterly_id, $amount, $purpose, $receipt_number, $paid_at) {
    // CORRECTED API URL - Use the right path
    $api_url = 'http://localhost/revenue2/citizen_dashboard/rpt/api/rpt_payment_api.php';
    
    $post_data = [
        'quarterly_id' => $quarterly_id,
        'amount' => $amount,
        'purpose' => $purpose,
        'receipt_number' => $receipt_number,
        'paid_at' => $paid_at
    ];
    
    // Log what we're sending
    error_log("=== RPT API CALL ===");
    error_log("URL: $api_url");
    error_log("Data: " . print_r($post_data, true));
    
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local testing
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // For local testing
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    // Log response
    error_log("HTTP Code: $http_code");
    error_log("Response: $response");
    error_log("cURL Error: $error");
    error_log("=== END API CALL ===");
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if ($result && isset($result['status'])) {
            return $result;
        } else {
            return [
                'status' => 'error',
                'message' => 'Invalid JSON response: ' . $response,
                'http_code' => $http_code
            ];
        }
    } else {
        return [
            'status' => 'error',
            'message' => 'API call failed. HTTP Code: ' . $http_code . ($error ? ' Error: ' . $error : ''),
            'http_code' => $http_code,
            'curl_error' => $error
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCash Payment - OTP Verification</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gcash-bg {
            background: linear-gradient(135deg, #00a859 0%, #00b894 100%);
        }
        .otp-input {
            letter-spacing: 10px;
            font-size: 24px;
            text-align: center;
        }
        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-md mx-auto p-4">
        <!-- Back Button -->
        <a href="gcash.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-6">
            <i class="fas fa-arrow-left mr-2"></i> Back to Phone Number
        </a>

        <!-- GCash Header -->
        <div class="gcash-bg text-white rounded-t-xl p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-mobile-alt text-green-600 text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">GCash Payment</h1>
                    <p class="text-green-100">Step 2: OTP Verification</p>
                </div>
            </div>
        </div>

        <!-- Payment Details -->
        <div class="bg-white rounded-b-xl shadow-lg p-6 mb-6">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Payment Summary</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Purpose:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($purpose); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Amount:</span>
                        <span class="font-bold text-lg text-green-600">₱<?php echo number_format($amount, 2); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Mobile Number:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($phone); ?></span>
                    </div>
                </div>
            </div>

            <!-- OTP Display -->
            <div class="mb-6 bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 rounded-xl p-5">
                <div class="flex items-center mb-3">
                    <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-key text-emerald-600"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-emerald-800">OTP Generated</h3>
                        <p class="text-sm text-emerald-700">Enter the OTP below to complete payment</p>
                    </div>
                </div>
                
                <div class="bg-white border border-emerald-300 rounded-lg p-4 mb-4">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-2">Your 6-digit OTP:</p>
                        <div class="flex items-center justify-center space-x-3">
                            <div id="otp-display" class="text-3xl font-bold text-emerald-600 tracking-widest pulse">
                                <?php echo $generated_otp; ?>
                            </div>
                            <button type="button" onclick="copyOTP('<?php echo $generated_otp; ?>')" class="text-emerald-600 hover:text-emerald-800">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-3">
                            <i class="fas fa-info-circle mr-1"></i>
                            For demo purposes, use this OTP
                        </p>
                    </div>
                </div>
                
                <div class="text-sm text-gray-600 flex justify-between">
                    <div>
                        <i class="fas fa-clock text-emerald-500 mr-1"></i>
                        Expires in: <span id="otp-timer" class="font-bold text-emerald-700"><?php echo sprintf('%02d:%02d', $minutes, $seconds); ?></span>
                    </div>
                    <div>
                        <i class="fas fa-exclamation-triangle text-orange-500 mr-1"></i>
                        Attempts: <span class="font-bold"><?php echo $otp_attempts; ?>/3</span>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-600 mr-3"></i>
                    <p class="text-red-700"><?php echo $error; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="verify_otp">
                
                <!-- OTP Input -->
                <div class="mb-6">
                    <label for="otp" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-key mr-2"></i>Enter OTP
                    </label>
                    <input type="text" 
                           id="otp" 
                           name="otp" 
                           value=""
                           placeholder="Enter 6-digit OTP" 
                           maxlength="6"
                           pattern="[0-9]{6}"
                           class="otp-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <p class="text-xs text-gray-500 mt-2">Enter the 6-digit OTP shown above</p>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-3">
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 rounded-lg font-bold text-lg">
                        <i class="fas fa-check-circle mr-2"></i> Verify & Complete Payment
                    </button>
                    
                    <button type="submit" name="action" value="resend_otp"
                            class="w-full bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 py-3 rounded-lg font-medium">
                        <i class="fas fa-redo mr-2"></i> Resend New OTP
                    </button>
                </div>
            </form>
        </div>

        <!-- Security Info -->
        <div class="bg-green-50 border border-green-200 rounded-xl p-6">
            <div class="flex items-start">
                <i class="fas fa-shield-alt text-green-600 text-xl mr-3 mt-1"></i>
                <div>
                    <h3 class="font-bold text-green-800 mb-2">Secure OTP Verification</h3>
                    <ul class="text-sm text-green-700 space-y-1">
                        <li><i class="fas fa-check-circle mr-2"></i> OTP is randomly generated for security</li>
                        <li><i class="fas fa-check-circle mr-2"></i> Expires in 5 minutes</li>
                        <li><i class="fas fa-check-circle mr-2"></i> Maximum 3 attempts allowed</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // OTP Timer
        let totalSeconds = <?php echo $remaining_time; ?>;
        const otpTimerElement = document.getElementById('otp-timer');
        
        function updateOTPTimer() {
            if (totalSeconds <= 0) {
                otpTimerElement.textContent = "00:00";
                otpTimerElement.classList.add('text-red-600');
                
                // Auto-submit form to go back when expired
                setTimeout(() => {
                    window.location.href = 'gcash.php?expired=1';
                }, 1000);
                return;
            }
            
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            otpTimerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (minutes === 0) {
                otpTimerElement.classList.add('text-red-600');
            }
            
            totalSeconds--;
        }
        
        updateOTPTimer();
        setInterval(updateOTPTimer, 1000);
        
        function copyOTP(otp) {
            navigator.clipboard.writeText(otp).then(() => {
                const btn = event.target.closest('button');
                const originalIcon = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check text-green-600"></i>';
                
                // Show copied message
                const otpDisplay = document.getElementById('otp-display');
                const originalText = otpDisplay.textContent;
                otpDisplay.textContent = 'COPIED!';
                otpDisplay.classList.remove('pulse');
                
                setTimeout(() => {
                    btn.innerHTML = originalIcon;
                    otpDisplay.textContent = originalText;
                    otpDisplay.classList.add('pulse');
                }, 2000);
            });
        }
        
        // Auto-focus OTP input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('otp').focus();
        });
        
        // Auto-submit when 6 digits entered
        document.getElementById('otp').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
            if (this.value.length === 6) {
                document.querySelector('button[type="submit"]').focus();
            }
        });
        
        // Enter key to submit
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('button[type="submit"]').click();
            }
        });
    </script>
</body>
</html>