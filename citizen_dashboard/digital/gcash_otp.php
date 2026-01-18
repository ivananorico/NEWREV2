<?php
// revenue2/citizen_dashboard/digital/gcash_otp.php
session_start();

// FIXED: Use absolute path that works on both localhost and server
$baseDir = dirname(__DIR__, 2); // Go up 2 levels from current directory
require_once $baseDir . '/db/Digital/digital_db.php';

// Alternative: If above doesn't work, try this instead:
// require_once $_SERVER['DOCUMENT_ROOT'] . '/db/Digital/digital_db.php';

// Check if we have required session data
if (!isset($_SESSION['payment_data']) || !isset($_SESSION['phone'])) {
    header('Location: gcash.php');
    exit();
}

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
    die("Database connection failed. Please try again.");
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
            SET payment_status = 'failed',
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
                
                // Clear session
                session_destroy();
                $_SESSION = array();
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
                    $revenue_source = 'Market Fees';
                    if ($client_system === 'rpt') {
                        $revenue_source = 'Real Property Tax';
                    } elseif ($client_system === 'business') {
                        $revenue_source = 'Business Tax';
                    } elseif ($client_system === 'fees') {
                        $revenue_source = 'Fees or Permits';
                    }else {
        $revenue_source = 'Unknown System / General Payment';
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
                    
                    // Send to Treasury API
                    $treasury_api = "http://localhost/revenue2/api/treasury/store_collection.php";
                    
                    $ch = curl_init($treasury_api);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($treasury_data));
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    
                    $treasury_response = curl_exec($ch);
                    $treasury_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    $treasury_result = json_decode($treasury_response, true);
                    
                    if ($treasury_http_code === 200 && $treasury_result['success']) {
                        $treasury_stored = true;
                        $treasury_id = $treasury_result['collection_id'];
                    } else {
                        $treasury_error = $treasury_result['error'] ?? 'Unknown Treasury error';
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
                        callback_response = CONCAT(COALESCE(callback_response, ''), 
                            ' | Payment successful at " . date('Y-m-d H:i:s') . "',
                            ' | Treasury stored: " . ($treasury_stored ? 'YES (ID: ' . $treasury_id . ')' : 'NO') . "',
                            ' | Treasury error: " . ($treasury_error ?: 'none') . "',
                            ' | Callback HTTP: " . $http_code . "')
                    WHERE payment_id = :payment_id
                ";
                
                $stmt = $pdo->prepare($update_query);
                $stmt->execute([
                    ':payment_id' => $pending_payment_id,
                    ':receipt_number' => $receipt_number,
                    ':paid_at' => $paid_at,
                    ':callback_sent' => !empty($callback_url) ? 1 : 0
                ]);
                
                // Store receipt data
                $_SESSION['receipt_data'] = [
                    'payment_id' => $pending_payment_id,
                    'receipt_number' => $receipt_number,
                    'amount' => $amount,
                    'purpose' => $purpose,
                    'phone' => $phone,
                    'paid_at' => $paid_at,
                    'client_system' => $client_system,
                    'callback_url' => $callback_url,
                    'callback_response' => $callback_response,
                    'treasury_stored' => $treasury_stored,
                    'treasury_id' => $treasury_id,
                    'treasury_error' => $treasury_error
                ];
                
                // Clear session
                unset($_SESSION['generated_otp']);
                unset($_SESSION['otp_expires']);
                unset($_SESSION['otp_attempts']);
                unset($_SESSION['phone']);
                unset($_SESSION['payment_data']);
                unset($_SESSION['pending_payment_id']);
                
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
    } elseif ($action === 'cancel') {
        // User explicitly cancelled - MARK AS FAILED
        try {
            $update_query = "
                UPDATE payment_transactions 
                SET payment_status = 'failed',
                    callback_response = CONCAT(COALESCE(callback_response, ''), ' | User cancelled payment at " . date('Y-m-d H:i:s') . "')
                WHERE payment_id = :payment_id
            ";
            
            $stmt = $pdo->prepare($update_query);
            $stmt->execute([':payment_id' => $pending_payment_id]);
        } catch (PDOException $e) {
            // Silent fail
        }
        
        // Clear session
        session_destroy();
        $_SESSION = array();
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
    <title>GCash OTP Verification</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gcash-bg { background: linear-gradient(135deg, #00a859 0%, #00b894 100%); }
        .otp-input { letter-spacing: 10px; font-size: 24px; text-align: center; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-md mx-auto p-4">
        <!-- Back Button -->
        <a href="gcash.php" 
           class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-6">
            <i class="fas fa-arrow-left mr-2"></i> Back
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
                    <p class="text-sm text-gray-600 mb-2">For demo, use this OTP:</p>
                    <div class="text-3xl font-bold text-blue-600 tracking-widest">
                        <?php echo $generated_otp; ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Expires in: <span id="otp-timer"><?php echo sprintf('%02d:%02d', $minutes, $seconds); ?></span></p>
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
                        Enter 6-digit OTP
                    </label>
                    <input type="text" 
                           id="otp" 
                           name="otp" 
                           placeholder="000000" 
                           maxlength="6"
                           pattern="[0-9]{6}"
                           required
                           class="otp-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>

                <!-- Buttons -->
                <div class="space-y-3">
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 rounded-lg font-bold text-lg">
                        <i class="fas fa-check-circle mr-2"></i> Complete Payment
                    </button>
                    
                    <button type="submit" name="action" value="resend_otp"
                            class="w-full border border-gray-300 hover:bg-gray-50 text-gray-800 py-3 rounded-lg">
                        <i class="fas fa-redo mr-2"></i> Resend OTP
                    </button>
                    
                    <button type="submit" name="action" value="cancel"
                            class="w-full border border-red-300 hover:bg-red-50 text-red-600 py-3 rounded-lg">
                        <i class="fas fa-times mr-2"></i> Cancel Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Timer
        let totalSeconds = <?php echo $remaining_time; ?>;
        const otpTimer = document.getElementById('otp-timer');
        
        function updateTimer() {
            if (totalSeconds <= 0) {
                otpTimer.textContent = "00:00";
                otpTimer.classList.add('text-red-600');
                setTimeout(() => {
                    window.location.href = 'gcash.php?expired=1';
                }, 1000);
                return;
            }
            const min = Math.floor(totalSeconds / 60);
            const sec = totalSeconds % 60;
            otpTimer.textContent = `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
            totalSeconds--;
        }
        
        setInterval(updateTimer, 1000);
        
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('otp').focus();
        });
    </script>
</body>
</html>