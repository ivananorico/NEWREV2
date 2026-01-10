<?php
// revenue2/citizen_dashboard/digital/gcash_otp.php
session_start();

// Check if we have required session data
if (!isset($_SESSION['payment_data']) || !isset($_SESSION['phone']) || !isset($_SESSION['generated_otp'])) {
    header('Location: gcash.php');
    exit();
}

$payment_data = $_SESSION['payment_data'];
$client_system = $payment_data['client_system'];
$reference_id = $payment_data['reference_id'];
$amount = $payment_data['amount'];
$purpose = $payment_data['purpose'];
$callback_url = $payment_data['callback_url'];
$system_data = $payment_data['system_data'];

// Extract quarterly_id from system_data
$quarterly_id = $system_data['quarterly_id'] ?? $reference_id;

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

// Initialize variables
$error = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = $_POST['otp'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify_otp') {
        if (strlen($entered_otp) !== 6 || !is_numeric($entered_otp)) {
            $error = 'Please enter a valid 6-digit OTP';
        } elseif ($entered_otp !== $generated_otp) {
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
                // Connect to digital database
                $pdo = new PDO('mysql:host=localhost;port=3307;dbname=digital;charset=utf8mb4', 'root', '');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Generate IDs
                $payment_id = 'GCASH-' . date('YmdHis') . '-' . rand(1000, 9999);
                $receipt_number = 'RCPT-' . date('YmdHis') . '-' . rand(1000, 9999);
                $paid_at = date('Y-m-d H:i:s');
                
                // =====================================================
                // AUTOMATED CALLBACK SYSTEM - UNIVERSAL
                // =====================================================
                $callback_success = false;
                $callback_response = '';
                
                // Send callback to ANY system that provided a callback URL
                if (!empty($callback_url)) {
                    // Prepare callback data - UNIVERSAL for all systems
                    $callback_data = [
                        // COMMON FIELDS for ALL systems
                        'reference_id' => $reference_id,
                        'amount' => $amount,
                        'purpose' => $purpose,
                        'receipt_number' => $receipt_number,
                        'paid_at' => $paid_at,
                        'payment_id' => $payment_id,
                        'client_system' => $client_system,
                        'payment_status' => 'paid',
                        'payment_method' => 'gcash',
                        'phone' => $phone
                    ];
                    
                    // Add system-specific data
                    if ($client_system === 'rpt') {
                        // For RPT system
                        if (strpos($reference_id, 'ANNUAL-') === 0) {
                            // Annual payment for RPT
                            $callback_data['type'] = 'annual';
                            $callback_data['quarterly_ids'] = $system_data['quarterly_ids'] ?? [];
                            $callback_data['discount_applied'] = $system_data['discount_applied'] ?? false;
                            $callback_data['discount_percent'] = $system_data['discount_percent'] ?? 0;
                            $callback_data['discount_amount'] = $system_data['discount_amount'] ?? 0;
                        } else {
                            // Quarterly payment for RPT
                            $callback_data['type'] = 'quarterly';
                            $callback_data['quarterly_id'] = $quarterly_id;
                        }
                    } else if ($client_system === 'business') {
                        // For Business Permit system
                        $callback_data['business_id'] = $system_data['business_id'] ?? $reference_id;
                        $callback_data['permit_type'] = $system_data['permit_type'] ?? '';
                    } else if ($client_system === 'health') {
                        // For Health Services system
                        $callback_data['service_id'] = $system_data['service_id'] ?? $reference_id;
                        $callback_data['patient_id'] = $system_data['patient_id'] ?? '';
                    } else if ($client_system === 'assets') {
                        // For Public Assets system
                        $callback_data['asset_id'] = $system_data['asset_id'] ?? $reference_id;
                        $callback_data['asset_type'] = $system_data['asset_type'] ?? '';
                    }
                    // Add more systems as needed...
                    
                    error_log("=== SENDING UNIVERSAL CALLBACK TO: $callback_url ===");
                    error_log("Client System: " . $client_system);
                    error_log("Reference ID: " . $reference_id);
                    error_log("Callback Data: " . print_r($callback_data, true));
                    
                    $ch = curl_init($callback_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($callback_data));
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    
                    $callback_response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch);
                    
                    curl_close($ch);
                    
                    $callback_success = ($http_code === 200);
                    
                    error_log("Callback Response HTTP: $http_code");
                    error_log("Callback Response: $callback_response");
                    
                    if ($curl_error) {
                        error_log("cURL Error: $curl_error");
                    }
                }
                
                // =====================================================
                // SAVE TO DIGITAL DATABASE
                // =====================================================
                $insert_query = "
                    INSERT INTO payment_transactions 
                    (payment_id, client_system, client_reference, purpose, amount, phone, 
                     payment_method, payment_status, otp_code, otp_verified, receipt_number, 
                     created_at, paid_at, callback_url, callback_sent, callback_response)
                    VALUES 
                    (:payment_id, :client_system, :client_reference, :purpose, :amount, :phone,
                     :payment_method, :payment_status, :otp_code, :otp_verified, :receipt_number,
                     :created_at, :paid_at, :callback_url, :callback_sent, :callback_response)
                ";
                
                $stmt = $pdo->prepare($insert_query);
                $stmt->execute([
                    ':payment_id' => $payment_id,
                    ':client_system' => $client_system,
                    ':client_reference' => $reference_id,
                    ':purpose' => $purpose,
                    ':amount' => $amount,
                    ':phone' => $phone,
                    ':payment_method' => 'gcash',
                    ':payment_status' => 'paid',
                    ':otp_code' => $generated_otp,
                    ':otp_verified' => 1,
                    ':receipt_number' => $receipt_number,
                    ':created_at' => $paid_at,
                    ':paid_at' => $paid_at,
                    ':callback_url' => $callback_url,
                    ':callback_sent' => !empty($callback_url) ? 1 : 0,
                    ':callback_response' => $callback_response
                ]);
                
                // Store receipt data
                $_SESSION['receipt_data'] = [
                    'payment_id' => $payment_id,
                    'receipt_number' => $receipt_number,
                    'amount' => $amount,
                    'purpose' => $purpose,
                    'phone' => $phone,
                    'paid_at' => $paid_at,
                    'client_system' => $client_system,
                    'callback_url' => $callback_url,
                    'callback_success' => $callback_success,
                    'callback_response' => $callback_response,
                    'quarterly_id' => $quarterly_id
                ];
                
                // Clear session
                unset($_SESSION['generated_otp']);
                unset($_SESSION['otp_expires']);
                unset($_SESSION['otp_attempts']);
                unset($_SESSION['phone']);
                unset($_SESSION['payment_data']);
                
                // Redirect to success
                header('Location: success_gcash.php');
                exit();
                
            } catch (PDOException $e) {
                $error = 'Payment processing error. Please try again.';
                error_log("Payment Error: " . $e->getMessage());
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
    }
}

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
        <a href="gcash.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-6">
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
                        <span class="font-bold"><?php echo strtoupper($client_system); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Reference:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($reference_id); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Quarterly ID:</span>
                        <span class="font-medium text-blue-600"><?php echo $quarterly_id; ?></span>
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

            <form method="POST" action="">
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
                setTimeout(() => window.location.href = 'gcash.php?expired=1', 1000);
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