<?php
// revenue2/citizen_dashboard/digital/index.php
session_start();

// =====================================================
// SECURITY HEADERS
// =====================================================
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 1; mode=block');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com;");

// Secure session cookie parameters
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
}

// =====================================================
// INPUT VALIDATION & SANITIZATION FUNCTIONS
// =====================================================
function validateInput($input, $type = 'string', $maxLength = 255) {
    if ($input === null || $input === '') return null;
    
    $input = trim($input);
    
    switch ($type) {
        case 'alphanumeric':
            if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $input)) return null;
            return substr($input, 0, $maxLength);
            
        case 'numeric':
            if (!is_numeric($input)) return null;
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
        case 'amount':
            if (!is_numeric($input) || $input <= 0 || $input > 999999.99) return null;
            return number_format($input, 2, '.', '');
            
        case 'url':
            $filtered = filter_var($input, FILTER_SANITIZE_URL);
            // Allow only specific domains for callback URLs
            $allowed_domains = ['revenuetreasury.goserveph.com', 'localhost', '127.0.0.1'];
            $host = parse_url($filtered, PHP_URL_HOST);
            if (!in_array($host, $allowed_domains)) {
                error_log("Invalid callback domain: " . $host);
                return null;
            }
            return filter_var($filtered, FILTER_VALIDATE_URL) ? $filtered : null;
            
        case 'text':
            // Sanitize text input, allow basic punctuation
            $cleaned = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            return substr($cleaned, 0, $maxLength);
            
        default:
            return htmlspecialchars(substr($input, 0, $maxLength), ENT_QUOTES, 'UTF-8');
    }
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

// =====================================================
// INITIALIZE SESSION
// =====================================================
// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['session_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = time();
}

// Generate CSRF token for this session
$csrf_token = generateCSRFToken();

// =====================================================
// REQUEST VALIDATION
// =====================================================
// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Log the attempt
    error_log("Invalid access method from IP: " . $_SERVER['REMOTE_ADDR'] . " - Method: " . $_SERVER['REQUEST_METHOD']);
    
    http_response_code(405);
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

// =====================================================
// INPUT PROCESSING WITH VALIDATION
// =====================================================
// Get and validate all POST parameters
$client_system = validateInput($_POST['system'] ?? '', 'alphanumeric', 50);
$reference_id = validateInput($_POST['ref'] ?? '', 'alphanumeric', 100);
$amount = validateInput($_POST['amount'] ?? '', 'amount');
$purpose = validateInput($_POST['purpose'] ?? '', 'text', 500);
$callback_url = validateInput($_POST['callback'] ?? '', 'url');
$post_csrf_token = $_POST['csrf_token'] ?? '';

// =====================================================
// VALIDATE REQUIRED PARAMETERS FIRST (BEFORE CSRF)
// =====================================================
$errors = [];
if (!$client_system) $errors[] = "Invalid client system";
if (!$reference_id) $errors[] = "Invalid or missing reference ID";
if (!$amount) $errors[] = "Invalid amount (must be positive and less than 1,000,000)";
if (!$purpose) $errors[] = "Invalid or missing purpose";
if (!$callback_url) $errors[] = "Invalid callback URL";

if (!empty($errors)) {
    error_log("Invalid payment parameters from IP: " . $_SERVER['REMOTE_ADDR'] . " - Errors: " . implode(', ', $errors));
    
    die("
        <div style='padding: 20px; text-align: center; background: white; border-radius: 10px; max-width: 500px; margin: 50px auto;'>
            <i class='fas fa-exclamation-circle' style='font-size: 48px; color: #ef4444;'></i>
            <h2 style='color: #1f2937; margin: 20px 0 10px;'>Invalid Payment Request</h2>
            <p style='color: #6b7280; margin-bottom: 15px;'>The following errors were found:</p>
            <div style='background: #f3f4f6; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: left;'>
                <ul style='color: #ef4444; list-style: disc; padding-left: 20px;'>");
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    die("
                </ul>
            </div>
            <a href='javascript:history.back()' style='display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>
                <i class='fas fa-arrow-left'></i> Go Back
            </a>
        </div>
    ");
}

// =====================================================
// CSRF VALIDATION (WITH EXCEPTION FOR FIRST REQUEST)
// =====================================================
// Check if this is the first request (no CSRF token yet)
if (empty($post_csrf_token)) {
    // This is the initial request from external system
    // Generate a new CSRF token for subsequent requests
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $csrf_token = $_SESSION['csrf_token'];
    
    // Log first request
    error_log("Initial payment request from IP: " . $_SERVER['REMOTE_ADDR'] . " - Reference: " . $reference_id);
} else {
    // Validate CSRF token for subsequent requests
    if (!validateCSRFToken($post_csrf_token)) {
        error_log("CSRF token validation failed from IP: " . $_SERVER['REMOTE_ADDR']);
        die("
            <div style='padding: 20px; text-align: center; background: white; border-radius: 10px; max-width: 500px; margin: 50px auto;'>
                <i class='fas fa-shield-alt' style='font-size: 48px; color: #ef4444;'></i>
                <h2 style='color: #1f2937; margin: 20px 0 10px;'>Security Validation Failed</h2>
                <p style='color: #6b7280; margin-bottom: 20px;'>Invalid security token. Please refresh the page and try again.</p>
                <a href='javascript:history.back()' style='display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>
                    <i class='fas fa-arrow-left'></i> Go Back
                </a>
            </div>
        ");
    }
}

// =====================================================
// ADDITIONAL SECURITY CHECKS
// =====================================================
// Rate limiting check
$rateLimitKey = 'rate_limit_' . md5($_SERVER['REMOTE_ADDR']);
$rateLimitCount = $_SESSION[$rateLimitKey] ?? 0;
$rateLimitTime = $_SESSION[$rateLimitKey . '_time'] ?? 0;

if (time() - $rateLimitTime < 60) { // 1 minute window
    if ($rateLimitCount > 10) { // Max 10 requests per minute
        error_log("Rate limit exceeded from IP: " . $_SERVER['REMOTE_ADDR']);
        http_response_code(429);
        die("
            <div style='padding: 20px; text-align: center; background: white; border-radius: 10px; max-width: 500px; margin: 50px auto;'>
                <i class='fas fa-hourglass-half' style='font-size: 48px; color: #f59e0b;'></i>
                <h2 style='color: #1f2937; margin: 20px 0 10px;'>Too Many Requests</h2>
                <p style='color: #6b7280; margin-bottom: 20px;'>Please wait a minute before trying again.</p>
                <a href='javascript:history.back()' style='display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>
                    <i class='fas fa-arrow-left'></i> Go Back
                </a>
            </div>
        ");
    }
    $_SESSION[$rateLimitKey] = $rateLimitCount + 1;
} else {
    $_SESSION[$rateLimitKey] = 1;
    $_SESSION[$rateLimitKey . '_time'] = time();
}

// Check for suspicious user agent
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (empty($userAgent) || strlen($userAgent) > 500) {
    error_log("Suspicious User-Agent detected: " . substr($userAgent, 0, 100) . " from IP: " . $_SERVER['REMOTE_ADDR']);
}

// =====================================================
// STORE IN SESSION WITH VALIDATION
// =====================================================
// Clear previous payment session data
unset($_SESSION['payment_data']);
unset($_SESSION['phone']);
unset($_SESSION['generated_otp']);
unset($_SESSION['receipt_data']);
unset($_SESSION['pending_payment_id']);
unset($_SESSION['otp_attempts']);
unset($_SESSION['otp_expires']);

// Generate unique payment ID
$payment_id = 'PAY-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));

// Store validated data in session
$_SESSION['payment_data'] = [
    'client_system' => $client_system,
    'reference_id' => $reference_id,
    'amount' => $amount,
    'purpose' => $purpose,
    'callback_url' => $callback_url,
    'payment_id' => $payment_id,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => substr($userAgent, 0, 255),
    'timestamp' => time()
];

// Set initial payment status
$_SESSION['payment_status'] = 'initiated';

// Log successful payment initiation
error_log("Payment initiated - Reference: $reference_id, Amount: $amount, System: $client_system, IP: " . $_SERVER['REMOTE_ADDR']);

// =====================================================
// DISPLAY FUNCTIONS
// =====================================================
// Generate a dynamic badge color based on system name
function getBadgeColor($system) {
    $colors = [
        'rpt' => 'bg-gradient-to-r from-blue-100 to-blue-200 text-blue-800',
        'business' => 'bg-gradient-to-r from-green-100 to-green-200 text-green-800',
        'health' => 'bg-gradient-to-r from-red-100 to-red-200 text-red-800',
        'assets' => 'bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800',
        'market_rent' => 'bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-800',
        'market' => 'bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-800',
        'general' => 'bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800',
    ];
    
    $system_lower = strtolower($system);
    return $colors[$system_lower] ?? $colors['general'];
}

// Define display system
$display_system = ucwords(str_replace('_', ' ', $client_system));
$badge_class = getBadgeColor($client_system);

// Generate new CSRF token for the next page
$page_csrf_token = bin2hex(random_bytes(32));
$_SESSION['page_csrf_token'] = $page_csrf_token;

// Store payment ID in hidden form
$payment_id_hash = hash_hmac('sha256', $payment_id, 'your_secret_key_here');
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
        .coming-soon {
            position: relative;
            opacity: 0.7;
        }
        .coming-soon::after {
            content: 'COMING SOON';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #6b7280;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }
        .payment-card.disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }
        .payment-card.disabled:hover {
            transform: none;
            box-shadow: none;
        }
        .security-badge {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Security Badge -->
    <div class="security-badge">
        <i class="fas fa-lock mr-2"></i>Secure Connection
    </div>

    <div class="max-w-4xl mx-auto p-4">
        <!-- Header -->
        <div class="mb-8">
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold <?php echo $badge_class; ?>">
                            <i class="fas fa-building mr-2"></i><?php echo htmlspecialchars($display_system); ?>
                        </span>
                        <h1 class="text-2xl font-bold text-gray-800 mt-2">Digital Payment</h1>
                        <div class="mt-2 space-y-1">
                            <p class="text-gray-600">
                                <i class="fas fa-hashtag mr-2"></i>Reference: 
                                <span class="font-mono"><?php echo htmlspecialchars($reference_id); ?></span>
                            </p>
                            <p class="text-gray-600 text-sm">
                                <i class="fas fa-fingerprint mr-2"></i>Payment ID: 
                                <span class="font-mono text-xs"><?php echo substr($payment_id, 0, 20) . '...'; ?></span>
                            </p>
                        </div>
                        <p class="text-xs text-green-600 mt-2">
                            <i class="fas fa-shield-alt mr-1"></i> Secure POST transmission | SSL/TLS Encrypted
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-blue-600">
                            ₱<?php echo number_format($amount, 2); ?>
                        </div>
                        <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($purpose); ?></p>
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="fas fa-clock mr-1"></i> Valid for: <span id="session-timer">30:00</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- GCash -->
            <form method="POST" action="gcash.php" id="gcash-form" class="h-full">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($page_csrf_token); ?>">
                <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($payment_id_hash); ?>">
                
                <button type="button" 
                        onclick="selectPayment('gcash')" 
                        id="gcash-card"
                        class="payment-card w-full h-full bg-white rounded-xl shadow-md p-6 text-center cursor-pointer hover:shadow-lg transition-all duration-300">
                    <div class="w-16 h-16 bg-gradient-to-br from-green-100 to-green-200 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-mobile-alt text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">GCash</h3>
                    <p class="text-gray-600 mb-4">Pay using your GCash account</p>
                    <div class="flex items-center justify-center text-green-600">
                        <i class="fas fa-bolt mr-2"></i>
                        <span class="font-medium">Instant Payment</span>
                    </div>
                    <div class="mt-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                            <i class="fas fa-check-circle mr-1"></i> AVAILABLE
                        </span>
                    </div>
                </button>
            </form>

            <!-- Maya -->
            <div class="payment-card bg-white rounded-xl shadow-md p-6 text-center coming-soon disabled" 
                 onclick="showComingSoon('Maya')" id="maya-card">
                <div class="w-16 h-16 bg-gradient-to-br from-purple-100 to-pink-100 rounded-full flex items-center justify-center mx-auto mb-4 opacity-60">
                    <i class="fas fa-wallet text-purple-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2 opacity-60">Maya</h3>
                <p class="text-gray-600 mb-4 opacity-60">Pay using Maya wallet</p>
                <div class="flex items-center justify-center text-purple-600 opacity-60">
                    <i class="fas fa-shield-alt mr-2"></i>
                    <span class="font-medium">Secure</span>
                </div>
                <div class="mt-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">
                        <i class="fas fa-clock mr-1"></i> COMING SOON
                    </span>
                </div>
            </div>

            <!-- Credit/Debit Card -->
            <div class="payment-card bg-white rounded-xl shadow-md p-6 text-center coming-soon disabled" 
                 onclick="showComingSoon('Credit/Debit Card')" id="card-card">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4 opacity-60">
                    <i class="fas fa-credit-card text-blue-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2 opacity-60">Credit/Debit Card</h3>
                <p class="text-gray-600 mb-4 opacity-60">Pay using your card</p>
                <div class="flex items-center justify-center text-blue-600 opacity-60">
                    <i class="fas fa-globe mr-2"></i>
                    <span class="font-medium">VISA/Mastercard</span>
                </div>
                <div class="mt-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">
                        <i class="fas fa-clock mr-1"></i> COMING SOON
                    </span>
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
                        <li><i class="fas fa-check-circle mr-2 text-green-500"></i> <strong>CSRF Protection</strong> - Token-based form validation</li>
                        <li><i class="fas fa-check-circle mr-2 text-green-500"></i> <strong>Input Validation</strong> - All inputs sanitized and validated</li>
                        <li><i class="fas fa-check-circle mr-2 text-green-500"></i> <strong>Rate Limiting</strong> - Prevents brute force attacks</li>
                        <li><i class="fas fa-check-circle mr-2 text-green-500"></i> <strong>Session Security</strong> - HTTPS-only cookies, session regeneration</li>
                        <li><i class="fas fa-check-circle mr-2 text-green-500"></i> <strong>Data Encryption</strong> - SSL/TLS encrypted connection</li>
                        <li><i class="fas fa-check-circle mr-2 text-green-500"></i> <strong>Audit Logging</strong> - All payment attempts logged</li>
                        <li><i class="fas fa-check-circle mr-2 text-green-500"></i> Callback to <code class="text-xs bg-white px-1 rounded"><?php echo htmlspecialchars(parse_url($callback_url, PHP_URL_HOST) ?? $callback_url); ?></code> after payment</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden security fields -->
    <input type="hidden" id="session-timeout" value="1800">
    <input type="hidden" id="payment-id" value="<?php echo htmlspecialchars($payment_id); ?>">

    <script>
        let selectedMethod = null;
        let sessionTimer = 1800; // 30 minutes in seconds

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

        function showComingSoon(methodName) {
            alert(`${methodName} payment is coming soon! Currently, only GCash is available.`);
        }

        function proceedToPayment() {
            if (!selectedMethod) {
                alert('Please select a payment method first.');
                return;
            }

            // Validate session is still valid
            if (sessionTimer <= 0) {
                alert('Your session has expired. Please refresh the page.');
                window.location.reload();
                return;
            }

            // Show loading
            const btn = document.getElementById('continue-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i> Redirecting...';
            btn.disabled = true;

            // Disable all payment cards
            document.querySelectorAll('.payment-card').forEach(card => {
                card.classList.add('disabled');
                card.style.cursor = 'not-allowed';
            });

            // Submit the form for the selected method
            if (selectedMethod === 'gcash') {
                document.getElementById('gcash-form').submit();
            } else {
                // For other methods, redirect directly
                setTimeout(() => {
                    window.location.href = selectedMethod + '.php';
                }, 500);
            }
        }

        function updateSessionTimer() {
            if (sessionTimer <= 0) {
                document.getElementById('session-timer').textContent = '00:00';
                document.getElementById('session-timer').classList.add('text-red-600');
                
                // Disable all buttons
                document.getElementById('continue-btn').disabled = true;
                document.querySelectorAll('.payment-card').forEach(card => {
                    card.classList.add('disabled');
                    card.style.cursor = 'not-allowed';
                    card.onclick = null;
                });
                
                alert('Your session has expired. Please refresh the page.');
                return;
            }
            
            const minutes = Math.floor(sessionTimer / 60);
            const seconds = sessionTimer % 60;
            document.getElementById('session-timer').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Change color when less than 5 minutes
            if (sessionTimer < 300) {
                document.getElementById('session-timer').classList.add('text-yellow-600');
            }
            if (sessionTimer < 60) {
                document.getElementById('session-timer').classList.remove('text-yellow-600');
                document.getElementById('session-timer').classList.add('text-red-600');
            }
            
            sessionTimer--;
        }

        // Auto-select GCash by default
        window.onload = function() {
            selectPayment('gcash');
            
            // Start session timer
            setInterval(updateSessionTimer, 1000);
            
            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Disable right-click on payment cards
            document.querySelectorAll('.payment-card').forEach(card => {
                card.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                });
            });
        };

        // Enter key to proceed
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && selectedMethod) {
                proceedToPayment();
            }
        });

        // Prevent leaving page without confirmation
        window.addEventListener('beforeunload', function(e) {
            if (selectedMethod && !document.getElementById('continue-btn').disabled) {
                e.preventDefault();
                e.returnValue = 'Are you sure you want to leave? Your payment selection will be lost.';
                return e.returnValue;
            }
        });
    </script>
</body>
</html>