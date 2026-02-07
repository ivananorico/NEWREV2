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
        <div style='padding: 20px; text-align: center; background: #fbfbfb; border-radius: 10px; max-width: 500px; margin: 50px auto; border: 1px solid #e1e5e9;'>
            <i class='fas fa-exclamation-triangle' style='font-size: 48px; color: #4a90e2;'></i>
            <h2 style='color: #2c3e50; margin: 20px 0 10px;'>Invalid Access Method</h2>
            <p style='color: #9aa5b1; margin-bottom: 20px;'>This page must be accessed via POST method for security.</p>
            <a href='javascript:history.back()' style='display: inline-block; background: #4a90e2; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>
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

// Image paths for payment methods
$gcash_image = 'images/gcash_img.jpg';
$maya_image = 'images/maya_logo.png';
$card_image = 'images/card_logo.png';
$background_image = 'images/gsmbg.png';

// Validate required parameters
if (empty($reference_id) || $amount <= 0 || empty($purpose) || empty($callback_url)) {
    die("
        <div style='padding: 20px; text-align: center; background: #fbfbfb; border-radius: 10px; max-width: 500px; margin: 50px auto; border: 1px solid #e1e5e9;'>
            <i class='fas fa-exclamation-circle' style='font-size: 48px; color: #4a90e2;'></i>
            <h2 style='color: #2c3e50; margin: 20px 0 10px;'>Invalid Payment Request</h2>
            <p style='color: #9aa5b1; margin-bottom: 15px;'>Missing required parameters:</p>
            <div style='background: #f5f7fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: left; border-left: 4px solid #4a90e2;'>
                <p><strong>Reference ID:</strong> " . (empty($reference_id) ? '<span style="color: #e74c3c;">Missing</span>' : htmlspecialchars($reference_id)) . "</p>
                <p><strong>Amount:</strong> " . ($amount <= 0 ? '<span style="color: #e74c3c;">Invalid</span>' : '₱' . number_format($amount, 2)) . "</p>
                <p><strong>Purpose:</strong> " . (empty($purpose) ? '<span style="color: #e74c3c;">Missing</span>' : htmlspecialchars($purpose)) . "</p>
                <p><strong>Callback URL:</strong> " . (empty($callback_url) ? '<span style="color: #e74c3c;">Missing</span>' : htmlspecialchars($callback_url)) . "</p>
            </div>
            <a href='javascript:history.back()' style='display: inline-block; background: #4a90e2; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>
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
        'rpt' => 'bg-blue-100 text-blue-800 border border-blue-200',
        'business' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
        'health' => 'bg-rose-100 text-rose-800 border border-rose-200',
        'assets' => 'bg-violet-100 text-violet-800 border border-violet-200',
        'market_rent' => 'bg-amber-100 text-amber-800 border border-amber-200',
        'general' => 'bg-gray-100 text-gray-800 border border-gray-200',
    ];
    
    return $colors[strtolower($system)] ?? $colors['general'];
}

$display_system = ucfirst(str_replace('_', ' ', $client_system));
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
        :root {
            --primary: #4a90e2;
            --secondary: #9aa5b1;
            --accent: #4caf50;
        }
        
        body {
            background: linear-gradient(135deg, rgba(240, 240, 240, 0.4) 0%, rgba(230, 230, 230, 0.4) 50%, rgba(220, 220, 220, 0.3) 100%);
            position: relative;
            overflow-x: hidden;
            min-height: 100vh;
        }
        
        /* Background image */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('<?php echo $background_image; ?>') center/cover no-repeat;
            opacity: 0.08;
            pointer-events: none;
            z-index: -2;
            filter: blur(1px);
        }
        
        /* Main container */
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border-radius: 20px;
            overflow: hidden;
        }
        
        /* Payment card */
        .payment-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
            border-radius: 16px;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .payment-card:hover:not(.disabled) {
            transform: translateY(-4px);
            box-shadow: 0 12px 48px rgba(74, 144, 226, 0.15);
            border-color: rgba(74, 144, 226, 0.2);
        }
        
        .payment-card.selected {
            border-color: #4a90e2;
            background: linear-gradient(145deg, rgba(248, 251, 255, 0.95), rgba(255, 255, 255, 0.95));
            box-shadow: 0 12px 40px rgba(74, 144, 226, 0.2);
        }
        
        .payment-card.selected::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4a90e2, #4caf50);
            border-radius: 16px 16px 0 0;
        }
        
        .coming-soon {
            position: relative;
        }
        
        .coming-soon::after {
            content: 'COMING SOON';
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(154, 165, 177, 0.95);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.5px;
            z-index: 10;
        }
        
        .payment-card.disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        /* Button */
        .btn-primary {
            background: linear-gradient(135deg, #4a90e2, #357abd);
            color: white;
            padding: 14px 36px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 24px rgba(74, 144, 226, 0.3);
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(74, 144, 226, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .available-badge {
            background: rgba(76, 175, 80, 0.95);
            color: white;
        }
        
        .info-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-4xl">
        <!-- Main Container -->
        <div class="main-container p-6 md:p-8">
            <!-- Header Section - Improved Alignment -->
            <div class="mb-8">
                <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 mb-6">
                    <!-- Left Column: Payment Info -->
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-4">
                            <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold <?php echo $badge_class; ?>">
                                <i class="fas fa-building mr-2"></i><?php echo htmlspecialchars($display_system); ?> Payment
                            </span>
                            <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-green-100 text-green-800 border border-green-200">
                                <i class="fas fa-lock mr-2"></i>Secure Transaction
                            </span>
                        </div>
                        
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">Digital Payment Gateway</h1>
                        
                        <div class="flex items-center text-gray-600 mb-1">
                            <i class="fas fa-hashtag text-[#4a90e2] mr-2"></i>
                            <span class="font-mono font-medium">Reference: </span>
                            <span class="font-mono font-bold text-gray-800 ml-1"><?php echo htmlspecialchars($reference_id); ?></span>
                        </div>
                        
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-file-invoice text-[#4a90e2] mr-2"></i>
                            <span class="font-medium">Purpose: </span>
                            <span class="font-semibold text-gray-800 ml-1"><?php echo htmlspecialchars($purpose); ?></span>
                        </div>
                    </div>
                    
                    <!-- Right Column: Amount -->
                    <div class="text-center md:text-right">
                        <div class="inline-flex flex-col items-center">
                            <div class="text-gray-500 text-sm mb-2">Total Amount</div>
                            <div class="bg-gradient-to-r from-[#4a90e2] to-[#4caf50] text-white px-6 py-4 rounded-2xl shadow-lg">
                                <div class="text-4xl font-bold">₱<?php echo number_format($amount, 2); ?></div>
                                <div class="text-sm mt-1 opacity-90">To be paid</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Methods Section -->
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Select Payment Method</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- GCash -->
                    <div class="payment-card p-5 text-center relative" onclick="selectPayment('gcash')" id="gcash-card">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-xl flex items-center justify-center bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-100">
                            <img src="<?php echo $gcash_image; ?>" 
                                 alt="GCash" 
                                 class="w-12 h-12 object-contain"
                                 onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-mobile-alt text-[#4a90e2] text-2xl\'></i>';">
                        </div>
                        <h3 class="font-bold text-gray-800 mb-2">GCash</h3>
                        <p class="text-gray-600 text-sm mb-3">Pay using GCash wallet</p>
                        <div class="space-y-1 mb-3">
                            <div class="flex items-center justify-center text-[#4a90e2] text-xs">
                                <i class="fas fa-bolt mr-1"></i>
                                <span>Instant</span>
                            </div>
                            <div class="flex items-center justify-center text-[#4a90e2] text-xs">
                                <i class="fas fa-percentage mr-1"></i>
                                <span>No Fees</span>
                            </div>
                        </div>
                        <div>
                            <span class="status-badge available-badge">
                                <i class="fas fa-check-circle mr-1.5"></i> RECOMMENDED
                            </span>
                        </div>
                    </div>

                    <!-- Maya -->
                    <div class="payment-card p-5 text-center coming-soon disabled" onclick="showComingSoon('Maya')" id="maya-card">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-xl flex items-center justify-center bg-gradient-to-br from-green-50 to-green-100 border border-green-100 opacity-60">
                            <?php if (file_exists($maya_image)): ?>
                                <img src="<?php echo $maya_image; ?>" alt="Maya" class="w-12 h-12 object-contain">
                            <?php else: ?>
                                <i class="fas fa-wallet text-[#4caf50] text-2xl"></i>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-bold text-gray-800 mb-2 opacity-60">Maya</h3>
                        <p class="text-gray-600 text-sm mb-3 opacity-60">Pay using Maya wallet</p>
                    </div>

                    <!-- Credit/Debit Card -->
                    <div class="payment-card p-5 text-center coming-soon disabled" onclick="showComingSoon('Credit/Debit Card')" id="card-card">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-xl flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 border border-gray-100 opacity-60">
                            <?php if (file_exists($card_image)): ?>
                                <img src="<?php echo $card_image; ?>" alt="Credit/Debit Card" class="w-12 h-12 object-contain">
                            <?php else: ?>
                                <i class="fas fa-credit-card text-gray-600 text-2xl"></i>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-bold text-gray-800 mb-2 opacity-60">Card</h3>
                        <p class="text-gray-600 text-sm mb-3 opacity-60">Visa/Mastercard</p>
                    </div>
                </div>
            </div>

            <!-- Information Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="info-card p-5 border-l-4 border-green-500">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center mr-3">
                            <i class="fas fa-shield-alt text-green-600"></i>
                        </div>
                        <h3 class="font-semibold text-gray-800">Secure Payment</h3>
                    </div>
                    <ul class="space-y-2">
                        <li class="flex items-start text-gray-700 text-sm">
                            <i class="fas fa-check text-green-500 mr-2 mt-0.5"></i>
                            <span>End-to-end encryption</span>
                        </li>
                        <li class="flex items-start text-gray-700 text-sm">
                            <i class="fas fa-check text-green-500 mr-2 mt-0.5"></i>
                            <span>PCI DSS compliant</span>
                        </li>
                        <li class="flex items-start text-gray-700 text-sm">
                            <i class="fas fa-check text-green-500 mr-2 mt-0.5"></i>
                            <span>Official LGU gateway</span>
                        </li>
                    </ul>
                </div>
                
                <div class="info-card p-5 border-l-4 border-blue-500">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center mr-3">
                            <i class="fas fa-info-circle text-blue-600"></i>
                        </div>
                        <h3 class="font-semibold text-gray-800">Important Notes</h3>
                    </div>
                    <ul class="space-y-2">
                        <li class="flex items-start text-gray-700 text-sm">
                            <i class="fas fa-clock text-blue-500 mr-2 mt-0.5"></i>
                            <span>Session expires in 15 minutes</span>
                        </li>
                        <li class="flex items-start text-gray-700 text-sm">
                            <i class="fas fa-receipt text-blue-500 mr-2 mt-0.5"></i>
                            <span>Digital receipt will be provided</span>
                        </li>
                        <li class="flex items-start text-gray-700 text-sm">
                            <i class="fas fa-headset text-blue-500 mr-2 mt-0.5"></i>
                            <span>Support: support@lgu.gov.ph</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Action Button -->
            <div class="text-center pt-4 border-t border-gray-100">
                <button id="continue-btn" onclick="proceedToPayment()" 
                        class="btn-primary text-base inline-flex items-center justify-center px-12 py-3.5 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    <i class="fas fa-arrow-right mr-3"></i> 
                    <span id="btn-text">Select Payment Method to Continue</span>
                </button>
                <p class="text-gray-500 text-sm mt-3">
                    <i class="fas fa-info-circle mr-1"></i>
                    GCash is recommended for instant confirmation
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-6 text-center">
            <div class="text-gray-500 text-sm">
                <div class="mb-2">
                    <i class="fas fa-university mr-2 text-[#4a90e2]"></i>
                    Local Government Unit Digital Payment System
                </div>
                <div class="flex justify-center space-x-6">
                    <span>© <?php echo date('Y'); ?> All rights reserved</span>
                    <a href="#" class="hover:text-[#4a90e2]">Terms</a>
                    <a href="#" class="hover:text-[#4a90e2]">Privacy</a>
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
            const selectedCard = document.getElementById(method + '-card');
            selectedCard.classList.add('selected');
            
            // Enable continue button
            const continueBtn = document.getElementById('continue-btn');
            const btnText = document.getElementById('btn-text');
            continueBtn.disabled = false;
            
            // Update button text
            btnText.textContent = `Continue with ${method === 'gcash' ? 'GCash' : method} Payment`;
        }

        function showComingSoon(methodName) {
            alert(`${methodName} payment is coming soon! Currently, only GCash is available.`);
        }

        function proceedToPayment() {
            if (!selectedMethod) {
                alert('Please select a payment method first.');
                return;
            }

            // Show loading
            const btn = document.getElementById('continue-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i> Processing...';
            btn.disabled = true;

            // Redirect
            setTimeout(() => {
                window.location.href = selectedMethod + '.php';
            }, 500);
        }

        // Auto-select GCash by default
        window.onload = function() {
            selectPayment('gcash');
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && selectedMethod) {
                    proceedToPayment();
                }
            });
        };
    </script>
</body>
</html>