<?php
// verify_otp.php

$payment_id = $_GET['payment_id'] ?? '';
if (empty($payment_id)) {
    die("Invalid payment ID");
}

// Include database connection
include_once '../../db/Digital/digital_db.php';

// Get payment details
try {
    $stmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE payment_id = :payment_id");
    $stmt->execute([':payment_id' => $payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        die("Payment not found");
    }
} catch (Exception $e) {
    die("Error loading payment details");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="w-full max-w-md mx-4">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-2xl p-6 mb-6 text-center">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-sms text-blue-600 text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Enter OTP Code</h1>
            <p class="text-gray-600 mb-4">
                We sent a 6-digit code to <span class="font-semibold"><?php echo htmlspecialchars($payment['phone']); ?></span>
            </p>
            
            <!-- Payment Summary -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                <div class="text-center">
                    <div class="text-lg font-semibold text-blue-600">₱<?php echo number_format($payment['amount'], 2); ?></div>
                    <div class="text-sm text-gray-600"><?php echo htmlspecialchars($payment['purpose']); ?></div>
                    <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($payment['client_reference']); ?></div>
                </div>
            </div>
        </div>

        <!-- OTP Form -->
        <div class="bg-white rounded-xl shadow-2xl p-6">
            <form id="otpForm" class="space-y-6">
                <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($payment_id); ?>">
                
                <!-- OTP Input -->
                <div>
                    <label for="otp" class="block text-sm font-medium text-gray-700 mb-2">
                        6-Digit OTP Code <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="otp" 
                        name="otp" 
                        class="block w-full rounded-lg border border-gray-300 px-4 py-3 text-center text-2xl font-semibold tracking-widest focus:border-blue-500 focus:ring-blue-500" 
                        placeholder="000000" 
                        pattern="[0-9]{6}" 
                        maxlength="6"
                        required
                        autocomplete="off"
                        autofocus
                    >
                    <p class="mt-2 text-sm text-gray-500 text-center">
                        Enter the 6-digit code sent to your phone
                    </p>
                </div>

                <!-- Demo OTP Hint -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <p class="text-sm text-yellow-700 text-center">
                        <i class="fas fa-info-circle mr-1"></i>
                        Demo OTP: <strong><?php echo htmlspecialchars($payment['otp_code']); ?></strong>
                    </p>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white py-4 px-6 rounded-lg font-semibold text-lg transition-colors flex items-center justify-center shadow-lg"
                >
                    <i class="fas fa-check mr-3"></i>
                    Verify & Complete Payment
                </button>

                <!-- Resend OTP -->
                <div class="text-center">
                    <button type="button" onclick="resendOTP()" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        <i class="fas fa-redo mr-1"></i>
                        Resend OTP Code
                    </button>
                </div>
            </form>

            <!-- Loading Overlay -->
            <div id="loadingOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-xl p-6 text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-spinner fa-spin text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Processing Payment</h3>
                    <p class="text-gray-600">Please wait...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('otpForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const otp = document.getElementById('otp').value;
        if (!otp || otp.length !== 6) {
            alert('Please enter a valid 6-digit OTP code');
            return;
        }

        // Show loading overlay
        document.getElementById('loadingOverlay').classList.remove('hidden');

        // Submit OTP for verification
        fetch('process_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                payment_id: '<?php echo $payment_id; ?>',
                otp_code: otp
            })
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    throw new Error('Server returned: ' + text.substring(0, 200));
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Payment response:', data);
            
            if (data.status === 'success') {
                // Payment successful
                window.location.href = 'success.php?payment_id=<?php echo $payment_id; ?>&receipt_number=' + data.receipt_number;
            } else {
                throw new Error(data.message || 'Payment failed');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
            document.getElementById('loadingOverlay').classList.add('hidden');
        });
    });

    function resendOTP() {
        alert('OTP code resent to your phone');
        // In production, call an API to resend OTP
    }

    // Auto-focus OTP input
    document.getElementById('otp').focus();
    
    // Auto-submit when 6 digits are entered
    document.getElementById('otp').addEventListener('input', function(e) {
        if (e.target.value.length === 6) {
            document.getElementById('otpForm').dispatchEvent(new Event('submit'));
        }
    });

    // Allow back navigation
    function goBack() {
        window.history.back();
    }
    </script>
</body>
</html>