<?php
// revenue2/citizen_dashboard/digital/index.php
session_start();

// Check if parameters are passed
if (isset($_GET['id']) && isset($_GET['amount']) && isset($_GET['purpose'])) {
    // Store from GET parameters
    $_SESSION['payment_data'] = [
        'quarterly_id' => $_GET['id'],
        'amount' => $_GET['amount'],
        'purpose' => $_GET['purpose']
    ];
} elseif (!isset($_SESSION['payment_data'])) {
    die("Invalid request. Missing parameters.");
}

$payment_data = $_SESSION['payment_data'];
$quarterly_id = $payment_data['quarterly_id'];
$amount = $payment_data['amount'];
$purpose = $payment_data['purpose'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Payment Method - LGU Digital Payment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payment-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .payment-card:hover {
            transform: translateY(-5px);
            border-color: #3b82f6;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.15);
        }
        .payment-card.selected {
            border-color: #3b82f6;
            background-color: #f0f7ff;
        }
        .amount-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto p-4">
        <!-- Header -->
        <div class="mb-8">
            <a href="../../services/rpt/rpt_tax_payment.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
                <i class="fas fa-arrow-left mr-2"></i> Back to RPT Payment
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Choose Payment Method</h1>
            <p class="text-gray-600">Select your preferred payment method</p>
        </div>

        <!-- Payment Summary -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="mb-4 md:mb-0">
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Payment Details</h2>
                    <p class="text-gray-600"><?php echo htmlspecialchars($purpose); ?></p>
                </div>
                <div class="amount-display text-white px-6 py-4 rounded-xl">
                    <p class="text-sm font-medium mb-1">Amount to Pay</p>
                    <p class="text-3xl font-bold">₱<?php echo number_format($amount, 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- GCash -->
            <div class="payment-card bg-white rounded-xl shadow-md p-6 text-center cursor-pointer" onclick="selectPayment('gcash')" id="gcash-card">
                <div class="w-16 h-16 bg-gradient-to-br from-green-100 to-green-200 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-mobile-alt text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">GCash</h3>
                <p class="text-gray-600 mb-4">Pay using your GCash account</p>
                <div class="flex items-center justify-center text-green-600">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span class="font-medium">Instant Payment</span>
                </div>
            </div>

            <!-- Maya -->
            <div class="payment-card bg-white rounded-xl shadow-md p-6 text-center cursor-pointer" onclick="selectPayment('maya')" id="maya-card">
                <div class="w-16 h-16 bg-gradient-to-br from-purple-100 to-pink-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-wallet text-purple-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Maya</h3>
                <p class="text-gray-600 mb-4">Pay using Maya wallet</p>
                <div class="flex items-center justify-center text-purple-600">
                    <i class="fas fa-bolt mr-2"></i>
                    <span class="font-medium">Fast Processing</span>
                </div>
            </div>

            <!-- Credit/Debit Card -->
            <div class="payment-card bg-white rounded-xl shadow-md p-6 text-center cursor-pointer" onclick="selectPayment('card')" id="card-card">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-credit-card text-blue-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Credit/Debit Card</h3>
                <p class="text-gray-600 mb-4">Pay using your card</p>
                <div class="flex items-center justify-center text-blue-600">
                    <i class="fas fa-shield-alt mr-2"></i>
                    <span class="font-medium">Secure Payment</span>
                </div>
            </div>
        </div>

        <!-- Continue Button -->
        <div class="text-center">
            <button id="continue-btn" onclick="proceedToPayment()" 
                    class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-8 py-4 rounded-xl font-bold text-lg inline-flex items-center transition-all duration-300 opacity-50 cursor-not-allowed" disabled>
                <i class="fas fa-arrow-right mr-3"></i> Continue with Selected Method
            </button>
            <p class="text-gray-500 text-sm mt-3">Please select a payment method above</p>
        </div>

        <!-- Security Notice -->
        <div class="mt-12 bg-blue-50 border border-blue-200 rounded-xl p-6">
            <div class="flex items-start">
                <i class="fas fa-shield-alt text-blue-600 text-2xl mr-4 mt-1"></i>
                <div>
                    <h3 class="text-lg font-bold text-blue-800 mb-2">Secure Payment</h3>
                    <p class="text-blue-700">Your payment is secured with SSL encryption. We do not store your payment details.</p>
                    <div class="flex items-center mt-3">
                        <i class="fas fa-lock text-green-600 mr-2"></i>
                        <span class="text-sm text-gray-600">Secure Connection • PCI DSS Compliant</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedMethod = null;

        function selectPayment(method) {
            selectedMethod = method;
            
            // Remove selection from all cards
            document.querySelectorAll('.payment-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            document.getElementById(method + '-card').classList.add('selected');
            
            // Enable continue button
            const continueBtn = document.getElementById('continue-btn');
            continueBtn.disabled = false;
            continueBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            continueBtn.classList.add('cursor-pointer');
            
            // Update button text
            continueBtn.innerHTML = `<i class="fas fa-arrow-right mr-3"></i> Continue with ${method.charAt(0).toUpperCase() + method.slice(1)}`;
        }

        function proceedToPayment() {
            if (!selectedMethod) {
                alert('Please select a payment method first.');
                return;
            }

            // Show loading
            const btn = document.getElementById('continue-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i> Redirecting...';
            btn.disabled = true;

            // Redirect to selected payment method
            setTimeout(() => {
                window.location.href = selectedMethod + '.php';
            }, 1000);
        }
        
        // Enter key to proceed
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && selectedMethod) {
                proceedToPayment();
            }
        });
    </script>
</body>
</html>