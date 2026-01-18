<?php
// revenue2/citizen_dashboard/digital/index.php
session_start();

// Clear previous payment session data
unset($_SESSION['payment_data']);
unset($_SESSION['phone']);
unset($_SESSION['generated_otp']);
unset($_SESSION['receipt_data']);

// Accept only POST method for security
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("
        <div style='padding: 20px; text-align: center; background: white; border-radius: 10px; max-width: 500px; margin: 50px auto;'>
            <i class='fas fa-exclamation-triangle' style='font-size: 48px; color: #f59e0b;'></i>
            <h2 style='color: #1f2937; margin: 20px 0 10px;'>Invalid Access Method</h2>
            <p style='color: #6b7280; margin-bottom: 20px;'>This page must be accessed via POST method for security.</p>
            <a href='javascript:history.back()' style='display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>
                <i class='fas fa-arrow-left'></i> Go Back
            </a>
        </div>
    ");
}

// Get required parameters from POST
$client_system = $_POST['system'] ?? 'general';
$reference_id = $_POST['ref'] ?? '';
$amount = $_POST['amount'] ?? 0;
$purpose = $_POST['purpose'] ?? '';
$callback_url = $_POST['callback'] ?? '';

// Validate required parameters
if (empty($reference_id) || $amount <= 0 || empty($purpose) || empty($callback_url)) {
    die("
        <div style='padding: 20px; text-align: center; background: white; border-radius: 10px; max-width: 500px; margin: 50px auto;'>
            <i class='fas fa-exclamation-circle' style='font-size: 48px; color: #ef4444;'></i>
            <h2 style='color: #1f2937; margin: 20px 0 10px;'>Invalid Payment Request</h2>
            <p style='color: #6b7280; margin-bottom: 15px;'>Missing required parameters:</p>
            <div style='background: #f3f4f6; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: left;'>
                <p><strong>Reference ID:</strong> " . (empty($reference_id) ? '<span style="color: #ef4444;">Missing</span>' : htmlspecialchars($reference_id)) . "</p>
                <p><strong>Amount:</strong> " . ($amount <= 0 ? '<span style="color: #ef4444;">Invalid</span>' : '₱' . number_format($amount, 2)) . "</p>
                <p><strong>Purpose:</strong> " . (empty($purpose) ? '<span style="color: #ef4444;">Missing</span>' : htmlspecialchars($purpose)) . "</p>
                <p><strong>Callback URL:</strong> " . (empty($callback_url) ? '<span style="color: #ef4444;">Missing</span>' : htmlspecialchars($callback_url)) . "</p>
            </div>
            <a href='javascript:history.back()' style='display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>
                <i class='fas fa-arrow-left'></i> Go Back
            </a>
        </div>
    ");
}

// Store in session for the payment flow
$_SESSION['payment_data'] = [
    'client_system' => $client_system,
    'reference_id' => $reference_id,
    'amount' => $amount,
    'purpose' => $purpose,
    'callback_url' => $callback_url
];

// Generate a dynamic badge color based on system name
function getBadgeColor($system) {
    $colors = [
        'rpt' => 'bg-gradient-to-r from-blue-100 to-blue-200 text-blue-800',
        'business' => 'bg-gradient-to-r from-green-100 to-green-200 text-green-800',
        'health' => 'bg-gradient-to-r from-red-100 to-red-200 text-red-800',
        'assets' => 'bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800',
        'market' => 'bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-800',
        'general' => 'bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800',
    ];
    
    return $colors[strtolower($system)] ?? $colors['general'];
}

// Define display system
$display_system = ucfirst($client_system);
$badge_class = getBadgeColor($client_system);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Digital Payment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payment-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .payment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.15);
        }
        .payment-card.selected {
            border-color: #3b82f6;
            background-color: #f0f7ff;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto p-4">
        <!-- Header -->
        <div class="mb-8">
            <a href="javascript:history.back()" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
            
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold <?php echo $badge_class; ?>">
                            <i class="fas fa-building mr-2"></i><?php echo htmlspecialchars($display_system); ?>
                        </span>
                        <h1 class="text-2xl font-bold text-gray-800 mt-2">Digital Payment</h1>
                        <p class="text-gray-600">Reference: <?php echo htmlspecialchars($reference_id); ?></p>
                        <p class="text-xs text-green-600 mt-1">
                            <i class="fas fa-shield-alt mr-1"></i> Secure POST transmission
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-blue-600">
                            ₱<?php echo number_format($amount, 2); ?>
                        </div>
                        <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($purpose); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- GCash -->
            <div class="payment-card bg-white rounded-xl shadow-md p-6 text-center cursor-pointer" 
                 onclick="selectPayment('gcash')" id="gcash-card">
                <div class="w-16 h-16 bg-gradient-to-br from-green-100 to-green-200 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-mobile-alt text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">GCash</h3>
                <p class="text-gray-600 mb-4">Pay using your GCash account</p>
                <div class="flex items-center justify-center text-green-600">
                    <i class="fas fa-bolt mr-2"></i>
                    <span class="font-medium">Instant Payment</span>
                </div>
            </div>

            <!-- Maya -->
            <div class="payment-card bg-white rounded-xl shadow-md p-6 text-center cursor-pointer" 
                 onclick="selectPayment('maya')" id="maya-card">
                <div class="w-16 h-16 bg-gradient-to-br from-purple-100 to-pink-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-wallet text-purple-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Maya</h3>
                <p class="text-gray-600 mb-4">Pay using Maya wallet</p>
                <div class="flex items-center justify-center text-purple-600">
                    <i class="fas fa-shield-alt mr-2"></i>
                    <span class="font-medium">Secure</span>
                </div>
            </div>

            <!-- Credit/Debit Card -->
            <div class="payment-card bg-white rounded-xl shadow-md p-6 text-center cursor-pointer" 
                 onclick="selectPayment('card')" id="card-card">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-credit-card text-blue-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Credit/Debit Card</h3>
                <p class="text-gray-600 mb-4">Pay using your card</p>
                <div class="flex items-center justify-center text-blue-600">
                    <i class="fas fa-globe mr-2"></i>
                    <span class="font-medium">VISA/Mastercard</span>
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

        <!-- Security Info Section -->
        <div class="mt-12 bg-blue-50 border border-blue-200 rounded-xl p-6">
            <div class="flex items-start">
                <i class="fas fa-shield-alt text-blue-600 text-2xl mr-4 mt-1"></i>
                <div>
                    <h3 class="text-lg font-bold text-blue-800 mb-2">Secure Payment System</h3>
                    <ul class="text-sm text-blue-700 space-y-2">
                        <li><i class="fas fa-check-circle mr-2 text-green-500"></i> <strong>Secure POST Method</strong> - Data encrypted in request body</li>
                        <li><i class="fas fa-check-circle mr-2 text-green-500"></i> SSL/TLS encrypted connection</li>
                        <li><i class="fas fa-check-circle mr-2 text-green-500"></i> PCI DSS compliant payment processing</li>
                        <li><i class="fas fa-check-circle mr-2 text-green-500"></i> No card details stored on our servers</li>
                        <li><i class="fas fa-check-circle mr-2 text-green-500"></i> Callback to <code class="text-xs bg-white px-1 rounded"><?php echo htmlspecialchars(parse_url($callback_url, PHP_URL_HOST) ?? $callback_url); ?></code> after payment</li>
                    </ul>
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
            continueBtn.classList.add('cursor-pointer', 'hover:shadow-lg');
            
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
            }, 500);
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