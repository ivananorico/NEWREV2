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
    die("Database connection error");
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
    } elseif ($action === 'resend_otp') {
        $new_otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['generated_otp'] = $new_otp;
        $_SESSION['otp_expires'] = time() + (5 * 60);
        $_SESSION['otp_attempts'] = 0;
        $generated_otp = $new_otp;
        $otp_expires = $_SESSION['otp_expires'];
        
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
        
        header('Location: gcash_otp.php');
        exit();
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

$abandoned_url = $base_url . '/citizen_dashboard/digital/mark_abandoned.php';
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
        <a href="gcash.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-6">
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
                </div>

                <!-- Buttons -->
                <div class="space-y-3">
                    <button type="submit" id="submitBtn" class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 rounded-lg font-bold text-lg transition-all duration-300">
                        <i class="fas fa-check-circle mr-2"></i> Complete Payment
                    </button>
                    
                    <div class="flex space-x-3">
                        <button type="submit" name="action" value="resend_otp" id="resendBtn" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-800 py-3 rounded-lg">
                            <i class="fas fa-redo mr-2"></i> Resend OTP
                        </button>
                        
                        <button type="submit" name="action" value="cancel" id="cancelBtn" class="flex-1 border border-red-300 hover:bg-red-50 text-red-600 py-3 rounded-lg">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Debug Info -->
            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                <p class="text-sm text-yellow-700">
                    <i class="fas fa-bug mr-2"></i>
                    <strong>Debug:</strong> 
                    <span id="debug-status">Page loaded</span>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Abandonment detection
        const paymentId = '<?php echo $pending_payment_id; ?>';
        const abandonedUrl = '<?php echo $abandoned_url; ?>';
        let paymentCompleted = false;
        let userCancelled = false;
        let pageLoadedTime = Date.now();
        
        // Update debug status
        function updateDebugStatus(message) {
            document.getElementById('debug-status').textContent = message;
            console.log('Debug:', message);
        }
        
        // Mark as abandoned function
        function markAsAbandoned() {
            if (!paymentCompleted && !userCancelled && paymentId) {
                updateDebugStatus('Marking as abandoned...');
                
                // Use sendBeacon for reliable abandonment tracking
                const data = new Blob([JSON.stringify({payment_id: paymentId})], {type: 'application/json'});
                if (navigator.sendBeacon(abandonedUrl, data)) {
                    updateDebugStatus('Payment marked as abandoned');
                    console.log('Payment abandoned:', paymentId);
                } else {
                    // Fallback to fetch if sendBeacon fails
                    fetch(abandonedUrl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({payment_id: paymentId}),
                        keepalive: true
                    }).then(response => response.json())
                      .then(data => {
                          console.log('Abandonment response:', data);
                          updateDebugStatus('Abandonment marked: ' + data.status);
                      })
                      .catch(error => {
                          console.error('Abandonment error:', error);
                          updateDebugStatus('Abandonment failed');
                      });
                }
            }
        }
        
        // When user leaves the page
        window.addEventListener('beforeunload', function(e) {
            // Only mark as abandoned if user spent more than 5 seconds on page
            if (Date.now() - pageLoadedTime > 5000) {
                markAsAbandoned();
            }
        });
        
        // When user submits form (not cancelling)
        document.getElementById('otpForm').addEventListener('submit', function(e) {
            const submitter = e.submitter;
            if (submitter && submitter.value === 'cancel') {
                userCancelled = true;
                updateDebugStatus('User cancelled payment');
            } else {
                paymentCompleted = true;
                updateDebugStatus('Payment being processed...');
            }
        });
        
        // Timer functionality
        let totalSeconds = <?php echo $remaining_time; ?>;
        const otpTimer = document.getElementById('otp-timer');
        
        function updateTimer() {
            if (totalSeconds <= 0) {
                otpTimer.textContent = "00:00";
                otpTimer.classList.add('timer-expired');
                document.getElementById('otp').disabled = true;
                document.getElementById('submitBtn').disabled = true;
                document.getElementById('submitBtn').textContent = 'OTP Expired';
                document.getElementById('submitBtn').classList.add('bg-gray-400', 'cursor-not-allowed');
                
                setTimeout(() => {
                    window.location.href = 'gcash.php?expired=1';
                }, 2000);
                return;
            }
            
            const min = Math.floor(totalSeconds / 60);
            const sec = totalSeconds % 60;
            otpTimer.textContent = `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
            
            if (totalSeconds < 60) {
                otpTimer.classList.add('timer-expired');
            }
            
            totalSeconds--;
        }
        
        // Start timer
        setInterval(updateTimer, 1000);
        
        // Initial debug status
        updateDebugStatus('Abandonment detection active. Payment ID: ' + paymentId.substring(0, 20) + '...');
        
        // Focus on OTP input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('otp').focus();
        });
    </script>
</body>
</html>