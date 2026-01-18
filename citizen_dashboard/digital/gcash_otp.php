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
            <a href='gcash.php' style='display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>
                <i class='fas fa-arrow-left'></i> Go Back
            </a>
        </div>
    ");
}

// =====================================================
// GET OTP FROM DATABASE
// =====================================================
$generated_otp = '';
$otp_expires = $_SESSION['otp_expires'] ?? 0;

if ($pending_payment_id) {
    try {
        $query = "SELECT otp_code, created_at FROM payment_transactions WHERE payment_id = :payment_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':payment_id' => $pending_payment_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['otp_code'])) {
            $generated_otp = $result['otp_code'];
            // Calculate expiration based on created_at
            $created_at = strtotime($result['created_at']);
            if (!$otp_expires) {
                $otp_expires = $created_at + (5 * 60);
                $_SESSION['otp_expires'] = $otp_expires;
            }
            $_SESSION['generated_otp'] = $generated_otp;
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
    $_SESSION['otp_error'] = 'OTP has expired. Please request a new one.';
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
            $error = 'Please enter a valid 6-digit OTP';
        } elseif ($entered_otp !== $generated_otp) {
            $_SESSION['otp_attempts'] = $otp_attempts + 1;
            $attempts_left = 3 - ($otp_attempts + 1);
            
            // Record failed attempt in database
            try {
                $update_query = "
                    UPDATE payment_transactions 
                    SET callback_response = CONCAT(COALESCE(callback_response, ''), ' | Failed OTP attempt #" . ($otp_attempts + 1) . " at " . date('Y-m-d H:i:s') . "')
                    WHERE payment_id = :payment_id
                ";
                
                $stmt = $pdo->prepare($update_query);
                $stmt->execute([':payment_id' => $pending_payment_id]);
            } catch (PDOException $e) {
                // Silent fail
            }
            
            if ($attempts_left > 0) {
                $error = "Incorrect OTP. {$attempts_left} attempt(s) remaining.";
            } else {
                // Maximum attempts exceeded - MARK AS FAILED
                try {
                    $update_query = "
                        UPDATE payment_transactions 
                        SET payment_status = 'failed',
                            callback_response = CONCAT(COALESCE(callback_response, ''), ' | Payment failed - Maximum OTP attempts exceeded')
                        WHERE payment_id = :payment_id
                    ";
                    
                    $stmt = $pdo->prepare($update_query);
                    $stmt->execute([':payment_id' => $pending_payment_id]);
                } catch (PDOException $e) {
                    // Silent fail
                }
                
                // Clear session and redirect
                unset($_SESSION['generated_otp']);
                unset($_SESSION['otp_expires']);
                unset($_SESSION['otp_attempts']);
                unset($_SESSION['phone']);
                unset($_SESSION['payment_data']);
                unset($_SESSION['pending_payment_id']);
                
                $_SESSION['otp_error'] = 'Maximum OTP attempts exceeded. Please start over.';
                header('Location: gcash.php');
                exit();
            }
        } else {
            // OTP is correct - Process payment
            try {
                $receipt_number = 'RCPT-' . date('YmdHis') . '-' . rand(1000, 9999);
                $paid_at = date('Y-m-d H:i:s');
                
                // =====================================================
                // SEND CALLBACK TO ORIGINAL SYSTEM
                // =====================================================
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
                
                // =====================================================
                // STORE IN TREASURY DATABASE
                // =====================================================
                $treasury_stored = false;
                $treasury_error = '';
                $treasury_id = null;
                
                try {
                    // Determine revenue source
                    $revenue_source = 'General Payment';
                    if ($client_system === 'rpt') {
                        $revenue_source = 'Real Property Tax';
                    } elseif ($client_system === 'business') {
                        $revenue_source = 'Business Tax';
                    } elseif ($client_system === 'health') {
                        $revenue_source = 'Health Fee';
                    } elseif ($client_system === 'market') {
                        $revenue_source = 'Market Fee';
                    }
                    
                    // Prepare treasury data
                    $treasury_data = [
                        'or_number' => $receipt_number,
                        'payment_date' => $paid_at,
                        'amount' => $amount,
                        'revenue_source' => $revenue_source,
                        'reference_id' => $reference_id,
                        'payment_method' => 'gcash',
                        'transaction_data' => json_encode([
                            'client_system' => $client_system,
                            'purpose' => $purpose,
                            'phone' => $phone,
                            'callback_response' => $callback_response,
                            'original_data' => $payment_data
                        ])
                    ];
                    
                    // Send to Treasury API (relative path)
                    $treasury_api = $base_url . '/api/treasury/store_collection.php';
                    
                    $ch = curl_init($treasury_api);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($treasury_data));
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    
                    $treasury_response = curl_exec($ch);
                    $treasury_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($treasury_response !== false) {
                        $treasury_result = json_decode($treasury_response, true);
                        
                        if ($treasury_http_code === 200 && isset($treasury_result['success']) && $treasury_result['success']) {
                            $treasury_stored = true;
                            $treasury_id = $treasury_result['collection_id'] ?? null;
                        } else {
                            $treasury_error = $treasury_result['error'] ?? 'Unknown Treasury error (Code: ' . $treasury_http_code . ')';
                        }
                    } else {
                        $treasury_error = 'Failed to connect to Treasury API';
                    }
                    
                } catch (Exception $e) {
                    $treasury_error = $e->getMessage();
                }
                
                // =====================================================
                // UPDATE PAYMENT TO PAID IN DIGITAL DATABASE
                // =====================================================
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
                
                // Build callback response
                $callback_response_text = 'Payment successful at ' . date('Y-m-d H:i:s');
                $callback_response_text .= ' | Callback HTTP: ' . $http_code;
                $callback_response_text .= ' | Treasury stored: ' . ($treasury_stored ? 'YES' . ($treasury_id ? ' (ID: ' . $treasury_id . ')' : '') : 'NO');
                if ($treasury_error) {
                    $callback_response_text .= ' | Treasury error: ' . $treasury_error;
                }
                
                $stmt = $pdo->prepare($update_query);
                $stmt->execute([
                    ':payment_id' => $pending_payment_id,
                    ':receipt_number' => $receipt_number,
                    ':paid_at' => $paid_at,
                    ':callback_sent' => !empty($callback_url) ? 1 : 0,
                    ':callback_response' => $callback_response_text
                ]);
                
                // Store receipt data for success page
                $_SESSION['receipt_data'] = [
                    'payment_id' => $pending_payment_id,
                    'receipt_number' => $receipt_number,
                    'amount' => $amount,
                    'purpose' => $purpose,
                    'phone' => $phone,
                    'paid_at' => $paid_at,
                    'client_system' => $client_system,
                    'treasury_stored' => $treasury_stored,
                    'treasury_id' => $treasury_id,
                    'treasury_error' => $treasury_error
                ];
                
                // Clear sensitive session data
                unset($_SESSION['generated_otp']);
                unset($_SESSION['otp_expires']);
                unset($_SESSION['otp_attempts']);
                unset($_SESSION['phone']);
                unset($_SESSION['pending_payment_id']);
                // Don't unset payment_data yet - success page might need it
                
                // Redirect to success
                header('Location: success_gcash.php');
                exit();
                
            } catch (PDOException $e) {
                $error = 'Payment processing error. Please try again.';
                
                // Mark as failed in database
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
    } elseif ($action === 'resend_otp') {
        // Generate new OTP
        $new_otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['generated_otp'] = $new_otp;
        $_SESSION['otp_expires'] = time() + (5 * 60);
        $_SESSION['otp_attempts'] = 0;
        $generated_otp = $new_otp;
        $otp_expires = $_SESSION['otp_expires'];
        
        // Update OTP in database
        try {
            $update_query = "
                UPDATE payment_transactions 
                SET otp_code = :otp_code,
                    callback_response = CONCAT(COALESCE(callback_response, ''), ' | OTP resent at " . date('Y-m-d H:i:s') . "')
                WHERE payment_id = :payment_id
            ";
            
            $stmt = $pdo->prepare($update_query);
            $stmt->execute([
                ':payment_id' => $pending_payment_id,
                ':otp_code' => $new_otp
            ]);
        } catch (PDOException $e) {
            // Silent fail
        }
        
        // Redirect to refresh page
        header('Location: gcash_otp.php');
        exit();
    } elseif ($action === 'cancel') {
        // User explicitly cancelled - MARK AS CANCELLED
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
        
        // Clear session
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
// Calculate remaining time
$remaining_time = max(0, $otp_expires - time());
$minutes = floor($remaining_time / 60);
$seconds = $remaining_time % 60;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCash OTP Verification - LGU Digital Payment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gcash-bg { background: linear-gradient(135deg, #00a859 0%, #00b894 100%); }
        .otp-input { 
            letter-spacing: 10px; 
            font-size: 24px; 
            text-align: center;
            font-family: monospace;
        }
        .timer-expired { color: #ef4444; font-weight: bold; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-md mx-auto p-4">
        <!-- Back Button -->
        <a href="gcash.php" 
           class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-6">
            <i class="fas fa-arrow-left mr-2"></i> Back to Phone Entry
        </a>

        <!-- GCash Header -->
        <div class="gcash-bg text-white rounded-t-xl p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-key text-green-600 text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">GCash Payment</h1>
                    <p class="text-green-100">Step 2: OTP Verification</p>
                </div>
            </div>
        </div>

        <!-- OTP Section -->
        <div class="bg-white rounded-b-xl shadow-lg p-6">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Payment Details</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">System:</span>
                        <span class="font-bold text-blue-600"><?php echo strtoupper($client_system); ?></span>
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
                        <span class="text-gray-600">Payment ID:</span>
                        <span class="font-mono text-xs"><?php echo substr($pending_payment_id, 0, 20) . '...'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Demo OTP -->
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                <div class="text-center">
                    <p class="text-sm text-gray-600 mb-2"><i class="fas fa-info-circle mr-1"></i> For demo/testing, use this OTP:</p>
                    <div class="text-3xl font-bold text-blue-600 tracking-widest mb-2">
                        <?php echo $generated_otp; ?>
                    </div>
                    <p class="text-xs text-gray-500">
                        <i class="fas fa-clock mr-1"></i>
                        Expires in: <span id="otp-timer" class="font-medium"><?php echo sprintf('%02d:%02d', $minutes, $seconds); ?></span>
                    </p>
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

            <form method="POST" action="" id="otpForm">
                <input type="hidden" name="action" value="verify_otp">
                
                <!-- OTP Input -->
                <div class="mb-6">
                    <label for="otp" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-key mr-2"></i>Enter 6-digit OTP
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
                           class="otp-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors duration-200">
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-shield-alt mr-1"></i>
                        Enter the OTP sent to your GCash account
                    </p>
                </div>

                <!-- Buttons -->
                <div class="space-y-3">
                    <button type="submit" 
                            id="submitBtn"
                            class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 rounded-lg font-bold text-lg transition-all duration-300 flex items-center justify-center">
                        <i class="fas fa-check-circle mr-2"></i> Complete Payment
                    </button>
                    
                    <div class="flex space-x-3">
                        <button type="submit" name="action" value="resend_otp"
                                id="resendBtn"
                                class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-800 py-3 rounded-lg transition-colors duration-200">
                            <i class="fas fa-redo mr-2"></i> Resend OTP
                        </button>
                        
                        <button type="submit" name="action" value="cancel"
                                class="flex-1 border border-red-300 hover:bg-red-50 text-red-600 py-3 rounded-lg transition-colors duration-200">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Security Notice -->
            <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-xl">
                <div class="flex items-start">
                    <i class="fas fa-shield-alt text-green-600 mr-3 mt-1"></i>
                    <div>
                        <p class="text-sm text-green-700">
                            <strong>Secure Payment:</strong> The OTP is valid for 5 minutes only. 
                            Do not share this code with anyone.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Timer functionality
        let totalSeconds = <?php echo $remaining_time; ?>;
        const otpTimer = document.getElementById('otp-timer');
        const submitBtn = document.getElementById('submitBtn');
        const resendBtn = document.getElementById('resendBtn');
        const otpInput = document.getElementById('otp');
        
        function updateTimer() {
            if (totalSeconds <= 0) {
                otpTimer.textContent = "00:00";
                otpTimer.classList.add('timer-expired');
                otpInput.disabled = true;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-clock mr-2"></i> OTP Expired';
                submitBtn.classList.remove('from-green-500', 'to-green-600', 'hover:from-green-600', 'hover:to-green-700');
                submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                
                // Auto-redirect after 2 seconds
                setTimeout(() => {
                    window.location.href = 'gcash.php?expired=1';
                }, 2000);
                return;
            }
            
            const min = Math.floor(totalSeconds / 60);
            const sec = totalSeconds % 60;
            otpTimer.textContent = `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
            
            // Add warning when less than 60 seconds
            if (totalSeconds < 60) {
                otpTimer.classList.add('timer-expired');
            }
            
            totalSeconds--;
        }
        
        // Start timer
        setInterval(updateTimer, 1000);
        
        // Auto-focus and auto-tab OTP input
        document.addEventListener('DOMContentLoaded', function() {
            otpInput.focus();
            
            // Auto-tab between OTP digits (if using 6 separate inputs)
            otpInput.addEventListener('input', function(e) {
                if (this.value.length === 6) {
                    this.blur();
                }
            });
        });
        
        // Form submission handling
        document.getElementById('otpForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"][name="action"][value="verify_otp"]');
            if (btn) {
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                btn.disabled = true;
                
                // Auto-reset after 5 seconds in case of error
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 5000);
            }
        });
        
        // Resend button handling
        resendBtn.addEventListener('click', function(e) {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
            btn.disabled = true;
            
            // Auto-reset after 3 seconds
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 3000);
        });
        
        // Input validation
        otpInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 6) {
                this.value = this.value.substring(0, 6);
            }
        });
        
        // Allow paste
        otpInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedData = (e.clipboardData || window.clipboardData).getData('text');
            const numbers = pastedData.replace(/[^0-9]/g, '');
            this.value = numbers.substring(0, 6);
        });
    </script>
</body>
</html>