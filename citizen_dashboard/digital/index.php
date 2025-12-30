<?php
// revenue2/citizen_dashboard/digital/index.php
session_start();
require_once 'config.php';

// STEP 1: Get payment data from URL (from RPT system)
$payment_data = [];

if (!empty($_GET['amount'])) {
    $payment_data = [
        'amount' => floatval($_GET['amount']),
        'purpose' => clean_input($_GET['purpose'] ?? 'Property Tax Payment'),
        'reference' => clean_input($_GET['reference'] ?? ''),
        'client_system' => clean_input($_GET['client_system'] ?? 'RPT System'),
        'client_reference' => clean_input($_GET['client_reference'] ?? ''),
        'tax_id' => intval($_GET['tax_id'] ?? 0),
        'property_total_id' => intval($_GET['property_total_id'] ?? 0),
        'quarter' => clean_input($_GET['quarter'] ?? ''),
        'year' => intval($_GET['year'] ?? date('Y')),
        'is_annual' => isset($_GET['is_annual']) ? boolval($_GET['is_annual']) : false,
        'discount_percent' => floatval($_GET['discount_percent'] ?? 0),
        'description' => clean_input($_GET['description'] ?? '')
    ];
    
    // Save to session
    $_SESSION['payment_data'] = $payment_data;
    
    // STEP 2: Show method selection page
    $show_method_selection = true;
    
} elseif (isset($_SESSION['payment_data'])) {
    // Already have payment data
    $payment_data = $_SESSION['payment_data'];
    
    // Check if method is already selected
    $selected_method = $_GET['method'] ?? $_SESSION['payment_method'] ?? '';
    
    if (!empty($selected_method) && in_array($selected_method, ['gcash', 'paymaya'])) {
        $_SESSION['payment_method'] = $selected_method;
        $show_method_selection = false;
    } else {
        $show_method_selection = true;
    }
    
} else {
    // No payment data - show error
    die('<h2>Error: No payment data provided</h2>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Payment Portal - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        @import url("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css");
        
        .method-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .method-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .method-card.selected {
            border-width: 3px;
            transform: scale(1.02);
        }
        .gcash-theme {
            background: linear-gradient(135deg, #0070ba 0%, #005a9e 100%);
        }
        .paymaya-theme {
            background: linear-gradient(135deg, #00a859 0%, #008749 100%);
        }
        .hidden { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Payment Summary (Always Show) -->
        <div class="max-w-2xl mx-auto mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Payment Summary</h2>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                        <?php echo clean_input($payment_data['client_system']); ?>
                    </span>
                </div>
                
                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">Purpose:</span>
                        <span class="font-semibold text-gray-800"><?php echo clean_input($payment_data['purpose']); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">Amount:</span>
                        <span class="text-2xl font-bold text-blue-600">
                            ₱<?php echo number_format(floatval($payment_data['amount']), 2); ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($payment_data['reference'])): ?>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">Reference:</span>
                        <span class="font-medium"><?php echo clean_input($payment_data['reference']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($show_method_selection): ?>
        <!-- STEP 1: Payment Method Selection -->
        <div id="step1" class="max-w-4xl mx-auto">
            <div class="text-center mb-6">
                <div class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-800 rounded-full mb-2">
                    <span class="w-2 h-2 bg-blue-600 rounded-full mr-2"></span>
                    Step 1 of 2
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Choose Payment Method</h2>
                <p class="text-gray-600">Select how you want to pay</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- GCash Card -->
                <div class="method-card bg-white rounded-xl shadow-lg border-2 border-blue-300 overflow-hidden"
                     onclick="selectMethod('gcash')">
                    <div class="gcash-theme p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <div class="text-2xl font-bold mb-1">GCash</div>
                                <div class="text-sm opacity-90">Mobile Wallet</div>
                            </div>
                            <div class="w-16 h-16 bg-white rounded-xl flex items-center justify-center">
                                <i class="fas fa-mobile-alt text-blue-600 text-3xl"></i>
                            </div>
                        </div>
                        <div class="text-sm mb-4">
                            <i class="fas fa-bolt mr-2"></i>Instant payment via GCash app
                        </div>
                    </div>
                    <div class="p-6">
                        <ul class="space-y-2 mb-4">
                            <li class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                No transaction fees
                            </li>
                            <li class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                Pay via QR or mobile number
                            </li>
                        </ul>
                        <div class="text-center pt-4 border-t border-gray-200">
                            <span class="text-blue-600 font-semibold">Select GCash</span>
                            <i class="fas fa-arrow-right ml-2 text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <!-- PayMaya Card -->
                <div class="method-card bg-white rounded-xl shadow-lg border-2 border-green-300 overflow-hidden"
                     onclick="selectMethod('paymaya')">
                    <div class="paymaya-theme p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <div class="text-2xl font-bold mb-1">PayMaya</div>
                                <div class="text-sm opacity-90">Digital Wallet</div>
                            </div>
                            <div class="w-16 h-16 bg-white rounded-xl flex items-center justify-center">
                                <i class="fas fa-wallet text-green-600 text-3xl"></i>
                            </div>
                        </div>
                        <div class="text-sm mb-4">
                            <i class="fas fa-shield-alt mr-2"></i>Secure payments with PayMaya
                        </div>
                    </div>
                    <div class="p-6">
                        <ul class="space-y-2 mb-4">
                            <li class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                Link credit/debit cards
                            </li>
                            <li class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                Earn rewards points
                            </li>
                        </ul>
                        <div class="text-center pt-4 border-t border-gray-200">
                            <span class="text-green-600 font-semibold">Select PayMaya</span>
                            <i class="fas fa-arrow-right ml-2 text-green-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div id="continueSection" class="text-center hidden">
                <button onclick="proceedToPayment()" 
                        class="px-8 py-4 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-bold text-lg shadow-lg">
                    Continue with <span id="selectedMethodName"></span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- STEP 2: Payment Method Interface (Will be loaded via JS) -->
        <div id="step2" class="<?php echo $show_method_selection ? 'hidden' : ''; ?>">
            <?php if (!$show_method_selection): ?>
                <!-- Load the selected method interface -->
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        loadMethodInterface('<?php echo $_SESSION['payment_method']; ?>');
                    });
                </script>
            <?php endif; ?>
        </div>

        <!-- GCash Interface Template -->
        <template id="gcashTemplate">
            <div class="max-w-2xl mx-auto">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-800 rounded-full mb-2">
                        <span class="w-2 h-2 bg-blue-600 rounded-full mr-2"></span>
                        Step 2 of 2 - GCash
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">Complete GCash Payment</h2>
                    <p class="text-gray-600">Enter your GCash-registered mobile number</p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-center mb-3">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-mobile-alt text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <div class="font-bold text-blue-800">GCash Payment</div>
                                <div class="text-sm text-blue-600">Enter your registered GCash number</div>
                            </div>
                        </div>
                    </div>

                    <form id="gcashForm">
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                GCash Mobile Number *
                            </label>
                            <div class="flex">
                                <div class="px-4 py-3 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">
                                    +63
                                </div>
                                <input type="tel" 
                                       id="gcashPhone" 
                                       class="flex-1 px-4 py-3 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="912 345 6789"
                                       pattern="[0-9]{10}"
                                       maxlength="10"
                                       required>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Enter your 10-digit GCash number (without +63)
                            </p>
                            <div id="gcashPhoneError" class="text-red-500 text-sm mt-1 hidden"></div>
                        </div>

                        <div class="space-y-3">
                            <button type="button" 
                                    onclick="requestGCashOTP()" 
                                    class="w-full py-3 gcash-theme text-white rounded-lg font-bold hover:opacity-90">
                                <i class="fas fa-paper-plane mr-2"></i>Send OTP to GCash
                            </button>
                            
                            <button type="button" 
                                    onclick="goBackToStep1()"
                                    class="w-full py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">
                                <i class="fas fa-arrow-left mr-2"></i> Change Payment Method
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        <!-- PayMaya Interface Template -->
        <template id="paymayaTemplate">
            <div class="max-w-2xl mx-auto">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center px-4 py-2 bg-green-100 text-green-800 rounded-full mb-2">
                        <span class="w-2 h-2 bg-green-600 rounded-full mr-2"></span>
                        Step 2 of 2 - PayMaya
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">Complete PayMaya Payment</h2>
                    <p class="text-gray-600">Enter your PayMaya-registered details</p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center mb-3">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-wallet text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <div class="font-bold text-green-800">PayMaya Payment</div>
                                <div class="text-sm text-green-600">Enter your PayMaya details</div>
                            </div>
                        </div>
                    </div>

                    <form id="paymayaForm">
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                PayMaya Mobile Number *
                            </label>
                            <div class="flex">
                                <div class="px-4 py-3 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">
                                    +63
                                </div>
                                <input type="tel" 
                                       id="paymayaPhone" 
                                       class="flex-1 px-4 py-3 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                       placeholder="912 345 6789"
                                       pattern="[0-9]{10}"
                                       maxlength="10"
                                       required>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Enter your 10-digit PayMaya number (without +63)
                            </p>
                            <div id="paymayaError" class="text-red-500 text-sm mt-1 hidden"></div>
                        </div>

                        <div class="space-y-3">
                            <button type="button" 
                                    onclick="requestPayMayaOTP()" 
                                    class="w-full py-3 paymaya-theme text-white rounded-lg font-bold hover:opacity-90">
                                <i class="fas fa-lock mr-2"></i>Send OTP to PayMaya
                            </button>
                            
                            <button type="button" 
                                    onclick="goBackToStep1()"
                                    class="w-full py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">
                                <i class="fas fa-arrow-left mr-2"></i> Change Payment Method
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        <!-- OTP Template -->
        <template id="otpTemplate">
            <div class="max-w-md mx-auto">
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">OTP Verification</h2>
                    <p class="text-gray-600">Enter the 6-digit OTP sent to your phone</p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-gray-600">
                                Sent to: <span id="phoneDisplay" class="font-medium"></span>
                            </div>
                            <div class="text-sm text-gray-500">
                                <i class="fas fa-clock mr-1"></i>
                                Expires in: <span id="otpTimer" class="font-bold">05:00</span>
                            </div>
                        </div>
                        
                        <!-- OTP Input -->
                        <div class="mb-4">
                            <div class="flex justify-center space-x-2 mb-4">
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                <input type="text" 
                                       maxlength="1" 
                                       class="otp-digit w-12 h-12 text-center text-xl font-bold border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                       data-index="<?php echo $i - 1; ?>">
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" id="otp_code" name="otp_code" required>
                            <div id="otpError" class="text-red-500 text-sm text-center hidden"></div>
                        </div>
                        
                        <!-- Resend OTP -->
                        <div class="text-center mb-6">
                            <button type="button" 
                                    onclick="resendOTP()" 
                                    id="resendBtn"
                                    class="text-blue-600 hover:text-blue-800 disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled>
                                <i class="fas fa-redo mr-1"></i>
                                Resend OTP (<span id="resendTimer">60</span>s)
                            </button>
                        </div>

                        <div class="space-y-3">
                            <button type="button" 
                                    onclick="completePayment()" 
                                    id="completeBtn"
                                    class="w-full py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                                <i class="fas fa-lock mr-2"></i> Complete Payment
                            </button>
                            
                            <button type="button" 
                                    onclick="goBackToMethodInterface()"
                                    class="w-full py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">
                                <i class="fas fa-arrow-left mr-2"></i> Back
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Hidden Fields -->
        <input type="hidden" id="payment_id" value="">
        <input type="hidden" id="payment_data_json" value='<?php echo htmlspecialchars(json_encode($payment_data), ENT_QUOTES, 'UTF-8'); ?>'>

        <!-- Loading Modal -->
        <div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-xl p-6 max-w-sm w-full mx-4">
                <div class="text-center">
                    <div class="w-16 h-16 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-4"></div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2" id="loadingTitle">Processing</h3>
                    <p class="text-gray-600" id="loadingMessage">Please wait...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Global variables
    let selectedMethod = '';
    let paymentData = JSON.parse(document.getElementById('payment_data_json').value);
    let paymentId = '';
    let otpTimerInterval;
    let resendTimerInterval;
    let otpTimeLeft = 300;
    let resendTimeLeft = 60;

    // Method selection
    function selectMethod(method) {
        selectedMethod = method;
        
        // Update UI
        document.querySelectorAll('.method-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Highlight selected card
        event.currentTarget.classList.add('selected');
        
        // Show continue button
        const continueSection = document.getElementById('continueSection');
        const methodName = method === 'gcash' ? 'GCash' : 'PayMaya';
        document.getElementById('selectedMethodName').textContent = methodName;
        continueSection.classList.remove('hidden');
    }

    function proceedToPayment() {
        if (!selectedMethod) {
            alert('Please select a payment method');
            return;
        }
        
        // Save method to session and reload page
        window.location.href = window.location.pathname + '?method=' + selectedMethod;
    }

    function loadMethodInterface(method) {
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        
        if (step1) step1.classList.add('hidden');
        if (step2) step2.classList.remove('hidden');
        
        // Clear step2 content
        step2.innerHTML = '';
        
        // Load template based on method
        const templateId = method + 'Template';
        const template = document.getElementById(templateId);
        
        if (template) {
            const content = template.content.cloneNode(true);
            step2.appendChild(content);
        }
    }

    // OTP Functions
    function requestGCashOTP() {
    const phone = document.getElementById('gcashPhone').value;
    if (!phone.match(/^[0-9]{10}$/)) {
        document.getElementById('gcashPhoneError').textContent = 'Please enter a valid 10-digit number';
        document.getElementById('gcashPhoneError').classList.remove('hidden');
        return;
    }
    
    // Send as 10 digits (9123456789), API will convert to 09123456789
    requestOTP(phone, 'gcash');
}

function requestPayMayaOTP() {
    const phone = document.getElementById('paymayaPhone').value;
    if (!phone.match(/^[0-9]{10}$/)) {
        document.getElementById('paymayaError').textContent = 'Please enter a valid 10-digit number';
        document.getElementById('paymayaError').classList.remove('hidden');
        return;
    }
    
    // Send as 10 digits (9123456789), API will convert to 09123456789
    requestOTP(phone, 'paymaya');
}

    async function requestOTP(phone, method) {
        showLoading('Sending OTP', 'Please wait while we send OTP...');
        
        try {
            const response = await fetch('payment_api.php?action=request_otp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ...paymentData,
                    phone: phone,
                    payment_method: method
                })
            });
            
            const data = await response.json();
            hideLoading();
            
            if (data.success) {
                paymentId = data.payment_id;
                document.getElementById('payment_id').value = paymentId;
                
                // Show OTP step
                showOTPStep(phone);
                
                <?php if (ENVIRONMENT === 'development'): ?>
                if (data.test_otp) {
                    alert('Development Mode: OTP is ' + data.test_otp);
                }
                <?php endif; ?>
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            hideLoading();
            alert('Network error: ' + error.message);
        }
    }

    function showOTPStep(phone) {
        const step2 = document.getElementById('step2');
        step2.innerHTML = '';
        
        const template = document.getElementById('otpTemplate');
        const content = template.content.cloneNode(true);
        step2.appendChild(content);
        
        // Update phone display
        document.getElementById('phoneDisplay').textContent = formatPhone(phone);
        
        // Initialize OTP input
        initOTPInput();
        
        // Start timers
        startOTPTimer();
        startResendTimer();
    }

    function initOTPInput() {
        const otpDigits = document.querySelectorAll('.otp-digit');
        const otpInput = document.getElementById('otp_code');
        
        otpDigits.forEach((digit, index) => {
            digit.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < otpDigits.length - 1) {
                    otpDigits[index + 1].focus();
                }
                updateOTPValue();
            });
            
            digit.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    otpDigits[index - 1].focus();
                }
            });
        });
        
        function updateOTPValue() {
            let otp = '';
            otpDigits.forEach(digit => {
                otp += digit.value;
            });
            otpInput.value = otp;
        }
    }

    function startOTPTimer() {
        const otpTimer = document.getElementById('otpTimer');
        clearInterval(otpTimerInterval);
        otpTimeLeft = 300;
        
        otpTimerInterval = setInterval(() => {
            const minutes = Math.floor(otpTimeLeft / 60);
            const seconds = otpTimeLeft % 60;
            otpTimer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (otpTimeLeft <= 0) {
                clearInterval(otpTimerInterval);
                otpTimer.textContent = '00:00';
                document.getElementById('otpError').textContent = 'OTP has expired. Please request a new one.';
                document.getElementById('otpError').classList.remove('hidden');
            }
            otpTimeLeft--;
        }, 1000);
    }

    function startResendTimer() {
        const resendBtn = document.getElementById('resendBtn');
        const resendTimer = document.getElementById('resendTimer');
        clearInterval(resendTimerInterval);
        resendTimeLeft = 60;
        resendBtn.disabled = true;
        
        resendTimerInterval = setInterval(() => {
            resendTimer.textContent = resendTimeLeft;
            if (resendTimeLeft <= 0) {
                clearInterval(resendTimerInterval);
                resendBtn.disabled = false;
                resendTimer.textContent = '0';
            }
            resendTimeLeft--;
        }, 1000);
    }

    function resendOTP() {
        // Get phone from display
        const phoneDisplay = document.getElementById('phoneDisplay').textContent;
        const phone = phoneDisplay.replace(/\D/g, '');
        
        requestOTP('63' + phone.substring(1), selectedMethod);
        startResendTimer();
    }

    async function completePayment() {
        const otpCode = document.getElementById('otp_code').value;
        
        if (otpCode.length !== 6) {
            document.getElementById('otpError').textContent = 'Please enter 6-digit OTP';
            document.getElementById('otpError').classList.remove('hidden');
            return;
        }
        
        showLoading('Verifying Payment', 'Please wait while we verify your payment...');
        
        try {
            const response = await fetch('payment_api.php?action=verify_otp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    payment_id: paymentId,
                    otp_code: otpCode,
                    ...paymentData
                })
            });
            
            const data = await response.json();
            hideLoading();
            
            if (data.success) {
                window.location.href = `success.php?payment_id=${paymentId}&receipt=${encodeURIComponent(data.receipt_number)}`;
            } else {
                document.getElementById('otpError').textContent = data.message;
                document.getElementById('otpError').classList.remove('hidden');
            }
        } catch (error) {
            hideLoading();
            alert('Network error: ' + error.message);
        }
    }

    // Navigation
    function goBackToStep1() {
        // Clear method from session and reload
        window.location.href = window.location.pathname;
    }

    function goBackToMethodInterface() {
        loadMethodInterface(selectedMethod);
    }

    // Helper functions
    function formatPhone(phone) {
        if (phone.startsWith('63')) {
            return '0' + phone.substring(2);
        }
        return phone;
    }

    function showLoading(title, message) {
        document.getElementById('loadingTitle').textContent = title;
        document.getElementById('loadingMessage').textContent = message;
        document.getElementById('loadingModal').classList.remove('hidden');
    }

    function hideLoading() {
        document.getElementById('loadingModal').classList.add('hidden');
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!$show_method_selection && !empty($_SESSION['payment_method'])): ?>
        loadMethodInterface('<?php echo $_SESSION['payment_method']; ?>');
        <?php endif; ?>
    });
    </script>
</body>
</html>