<?php
// revenue2/citizen_dashboard/digital/config.php

// Auto-detect environment
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_localhost = (strpos($host, 'localhost') !== false || 
                 $host === '127.0.0.1' ||
                 strpos($host, '.local') !== false ||
                 $host === '::1');

// Dynamic Configuration based on environment
if ($is_localhost) {
    // Local Development Configuration (XAMPP/WAMP/MAMP)
    define('DB_HOST', 'localhost:3307'); // XAMPP default with 3307
    define('DB_NAME', 'digital');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('ENVIRONMENT', 'development');
    define('BASE_PATH', '/revenue2/citizen_dashboard/digital/');
} else {
    // Production/Live Domain Configuration
    define('DB_HOST', 'localhost');      // Most hosting uses just 'localhost'
    define('DB_NAME', 'digital');        // Your actual database name
    define('DB_USER', 'root');           // Your hosting username
    define('DB_PASS', '');               // Your hosting password
    define('ENVIRONMENT', 'production');
    define('BASE_PATH', '/revenue2/citizen_dashboard/digital/');
}

// System Configuration (Same for all environments)
define('OTP_EXPIRY_MINUTES', 5);
define('MAX_OTP_ATTEMPTS', 3);
define('SITE_NAME', 'GoServePH Digital Payment');

// Auto-detect base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
define('BASE_URL', $protocol . $host . BASE_PATH);

// Payment Methods
$payment_methods = [
    'gcash' => [
        'name' => 'GCash',
        'icon' => 'fas fa-mobile-alt',
        'color' => 'bg-blue-600'
    ],
    'paymaya' => [
        'name' => 'PayMaya',
        'icon' => 'fas fa-wallet',
        'color' => 'bg-green-600'
    ]
];

// Database Connection with fallback options
function getDigitalDBConnection() {
    $attempts = [
        ['host' => DB_HOST, 'user' => DB_USER, 'pass' => DB_PASS],
        ['host' => 'localhost:3306', 'user' => 'root', 'pass' => ''], // XAMPP default
        ['host' => 'localhost', 'user' => 'root', 'pass' => ''],     // Standard MySQL
    ];
    
    foreach ($attempts as $attempt) {
        try {
            $dsn = "mysql:host={$attempt['host']};dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, $attempt['user'], $attempt['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            
            if (ENVIRONMENT === 'development') {
                error_log("Connected to database: {$attempt['host']}");
            }
            return $pdo;
        } catch (PDOException $e) {
            if (ENVIRONMENT === 'development') {
                error_log("Connection failed to {$attempt['host']}: " . $e->getMessage());
            }
            continue;
        }
    }
    
    if (ENVIRONMENT === 'development') {
        echo "<div style='background:#f00;color:#fff;padding:10px;margin:10px;border-radius:5px;'>";
        echo "<h3>Database Connection Error</h3>";
        echo "<p>Unable to connect to database. Please check:</p>";
        echo "<ol>";
        echo "<li>Is MySQL running? (Check XAMPP/WAMP control panel)</li>";
        echo "<li>Does database '".DB_NAME."' exist?</li>";
        echo "<li>Check credentials in config.php</li>";
        echo "<li>Tried: " . DB_HOST . " with user '" . DB_USER . "'</li>";
        echo "</ol>";
        echo "</div>";
    }
    
    error_log("All database connection attempts failed");
    return null;
}

// Helper Functions
function clean_input($data) {
    if ($data === null) {
        return '';
    }
    
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generatePaymentId() {
    return 'PAY-' . date('YmdHis') . '-' . rand(1000, 9999);
}

function generateOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function generateReceiptNumber() {
    return 'RCPT-' . date('YmdHis') . '-' . rand(100, 999);
}

// Helper function to decode payment data from RPT system
function decodePaymentData() {
    $payment_data = [];
    
    // Method 1: Try base64 encoded payment_data parameter (from RPT)
    if (!empty($_GET['payment_data'])) {
        try {
            $decoded = base64_decode($_GET['payment_data']);
            if ($decoded) {
                $data = json_decode($decoded, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payment_data = array_merge($payment_data, $data);
                }
            }
        } catch (Exception $e) {
            // Continue to other methods
            if (ENVIRONMENT === 'development') {
                error_log("Base64 decode error: " . $e->getMessage());
            }
        }
    }
    
    // Method 2: Check individual GET parameters (direct from RPT links)
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
    }
    
    // Method 3: Check session (for return visits)
    elseif (isset($_SESSION['payment_data'])) {
        $payment_data = $_SESSION['payment_data'];
    }
    
    // Method 4: Default test data (development only)
    else {
        $payment_data = [
            'amount' => 16800.00, // Changed from 1000.00 to match RPT data
            'purpose' => 'Real Property Tax Payment',
            'reference' => 'RPT-20251230-1109',
            'client_system' => 'RPT System',
            'client_reference' => 'TAX-Q1-2025-1',
            'description' => 'Quarterly property tax payment',
            'tax_id' => 1,
            'property_total_id' => 1,
            'quarter' => 'Q1',
            'year' => 2025,
            'is_annual' => false,
            'discount_percent' => 0
        ];
        
        // Only set in session if we're in development mode
        if (ENVIRONMENT === 'development') {
            $_SESSION['payment_data'] = $payment_data;
        }
    }
    
    // Save to session for return visits
    $_SESSION['payment_data'] = $payment_data;
    
    return $payment_data;
}

// Initialize payment data
function initPaymentData() {
    global $payment_data;
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $payment_data = decodePaymentData();
    
    // Debug output in development
    if (ENVIRONMENT === 'development') {
        error_log("Payment Data Initialized: " . json_encode($payment_data));
    }
    
    return $payment_data;
}

// For debugging
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/debug.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Initialize payment data
initPaymentData();
?>