<?php
// revenue2/citizen_dashboard/digital/gcash.php
session_start();

// Check if payment data exists
if (!isset($_SESSION['payment_data'])) {
    header('Location: index.php');
    exit();
}

// Use absolute path that works on both localhost and server
$baseDir = dirname(__DIR__, 2); // Go up 2 levels from current directory
require_once $baseDir . '/db/Digital/digital_db.php';

$payment_data = $_SESSION['payment_data'];
$client_system = $payment_data['client_system'];
$reference_id = $payment_data['reference_id'];
$amount = $payment_data['amount'];
$purpose = $payment_data['purpose'];
$callback_url = $payment_data['callback_url'];

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

// Initialize variables
$error = '';
$phone = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify_phone') {
        // Validate phone number
        $clean_phone = preg_replace('/\D/', '', $phone);
        
        if (strlen($clean_phone) === 11 && strpos($clean_phone, '09') === 0) {
            try {
                // Generate payment ID and OTP
                $payment_id = 'GCASH-' . date('YmdHis') . '-' . rand(1000, 9999);
                $generated_otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $created_at = date('Y-m-d H:i:s');
                
                // =====================================================
                // CREATE PENDING TRANSACTION WITH OTP IN DATABASE
                // =====================================================
                $insert_query = "
                    INSERT INTO payment_transactions 
                    (payment_id, client_system, client_reference, purpose, amount, phone, 
                     payment_method, payment_status, otp_code, otp_verified, receipt_number, 
                     created_at, callback_url, callback_sent)
                    VALUES 
                    (:payment_id, :client_system, :client_reference, :purpose, :amount, :phone,
                     :payment_method, :payment_status, :otp_code, :otp_verified, :receipt_number,
                     :created_at, :callback_url, :callback_sent)
                ";
                
                $stmt = $pdo->prepare($insert_query);
                $stmt->execute([
                    ':payment_id' => $payment_id,
                    ':client_system' => $client_system,
                    ':client_reference' => $reference_id,
                    ':purpose' => $purpose,
                    ':amount' => $amount,
                    ':phone' => $clean_phone,
                    ':payment_method' => 'gcash',
                    ':payment_status' => 'pending',
                    ':otp_code' => $generated_otp, // STORE OTP IN DATABASE
                    ':otp_verified' => 0,
                    ':receipt_number' => NULL,
                    ':created_at' => $created_at,
                    ':callback_url' => $callback_url,
                    ':callback_sent' => 0
                ]);
                
                // Store in session
                $_SESSION['phone'] = $clean_phone;
                $_SESSION['generated_otp'] = $generated_otp;
                $_SESSION['otp_expires'] = time() + (5 * 60);
                $_SESSION['otp_attempts'] = 0;
                $_SESSION['pending_payment_id'] = $payment_id;
                
                // Redirect to OTP page
                header('Location: gcash_otp.php');
                exit();
                
            } catch (PDOException $e) {
                $error = 'System error. Please try again.';
                error_log("GCash Pending Transaction Error: " . $e->getMessage());
            }
        } else {
            $error = 'Please enter a valid 11-digit mobile number starting with 09';
        }
    }
}
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
        .gcash-bg { background: linear-gradient(135deg, #00a859 0%, #00b894 100%); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-md mx-auto p-4">
        <!-- Back Button -->
        <a href="index.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-6">
            <i class="fas fa-arrow-left mr-2"></i> Back to Payment Methods
        </a>

        <!-- GCash Header -->
        <div class="gcash-bg text-white rounded-t-xl p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-mobile-alt text-green-600 text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">GCash Payment</h1>
                    <p class="text-green-100">Step 1: Enter mobile number</p>
                </div>
            </div>
        </div>

        <!-- Payment Details -->
        <div class="bg-white rounded-b-xl shadow-lg p-6 mb-6">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Payment Details</h2>
                <div class="space-y-3 text-sm">
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
                        <span class="text-gray-600">Purpose:</span>
                        <span class="font-medium text-right"><?php echo htmlspecialchars($purpose); ?></span>
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
                <input type="hidden" name="action" value="verify_phone">
                
                <!-- Phone Input -->
                <div class="mb-6">
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-mobile-alt mr-2"></i>GCash Mobile Number
                    </label>
                    <div class="relative">
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               value="<?php echo htmlspecialchars($phone); ?>"
                               placeholder="09123456789"
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               oninput="formatPhoneNumber(this)">
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Enter your 11-digit GCash mobile number
                    </p>
                </div>

                <!-- Continue Button -->
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 rounded-lg font-bold text-lg transition-all duration-300">
                    <i class="fas fa-arrow-right mr-2"></i> Continue to OTP Verification
                </button>
            </form>
            
            <!-- Demo Info -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 mr-3 mt-1"></i>
                    <div>
                        <p class="text-sm text-blue-700">
                            <strong>For demo:</strong> Enter <span class="font-bold">09123456789</span> or any 11-digit number starting with 09
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Security Notice -->
            <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-xl">
                <div class="flex items-start">
                    <i class="fas fa-shield-alt text-green-600 mr-3 mt-1"></i>
                    <div>
                        <p class="text-sm text-green-700">
                            <strong>Secure Payment:</strong> Your phone number is only used for OTP verification and is not stored permanently.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function formatPhoneNumber(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length > 11) value = value.substring(0, 11);
            input.value = value;
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('phone');
            if (phoneInput.value === '') {
                phoneInput.value = '09';
                phoneInput.focus();
            }
            
            // Form submission handling
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                submitBtn.disabled = true;
                
                // Auto-enable after 5 seconds in case of error
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 5000);
            });
        });
    </script>
</body>
</html>