<?php
// revenue2/citizen_dashboard/digital/gcash.php
session_start();

// Check if payment data exists
if (!isset($_SESSION['payment_data'])) {
    header('Location: index.php');
    exit();
}

// =====================================================
// FIXED: DATABASE PATH - WORKS FOR BOTH LOCALHOST AND DOMAIN
// =====================================================
$database_loaded = false;
$db_error = '';

// Try multiple paths until one works
$possible_paths = [
    // Path 1: Localhost (2 levels up)
    __DIR__ . '/../../db/Digital/digital_db.php',
    
    // Path 2: Domain (3 levels up)
    __DIR__ . '/../../../db/Digital/digital_db.php',
    
    // Path 3: Using dirname function (2 levels)
    dirname(__DIR__, 2) . '/db/Digital/digital_db.php',
    
    // Path 4: Using dirname function (3 levels)
    dirname(__DIR__, 3) . '/db/Digital/digital_db.php',
    
    // Path 5: Document root approach
    $_SERVER['DOCUMENT_ROOT'] . '/db/Digital/digital_db.php',
    
    // Path 6: Alternative document root (for domain)
    isset($_SERVER['CONTEXT_DOCUMENT_ROOT']) ? $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/db/Digital/digital_db.php' : null,
];

foreach ($possible_paths as $path) {
    if ($path && file_exists($path)) {
        try {
            require_once $path;
            $database_loaded = true;
            break;
        } catch (Exception $e) {
            $db_error = $e->getMessage();
        }
    }
}

if (!$database_loaded) {
    die("
        <div style='padding: 20px; text-align: center; background: white; border-radius: 10px; max-width: 500px; margin: 50px auto;'>
            <i class='fas fa-exclamation-triangle' style='font-size: 48px; color: #f59e0b;'></i>
            <h2 style='color: #1f2937; margin: 20px 0 10px;'>Database Configuration Error</h2>
            <p style='color: #6b7280; margin-bottom: 10px;'>Unable to locate database configuration file.</p>
            <p style='color: #6b7280; margin-bottom: 20px; font-size: 12px;'>Please check file permissions and paths.</p>
            <a href='index.php' style='display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>
                <i class='fas fa-arrow-left'></i> Go Back
            </a>
        </div>
    ");
}

$payment_data = $_SESSION['payment_data'];
$client_system = $payment_data['client_system'];
$reference_id = $payment_data['reference_id'];
$amount = $payment_data['amount'];
$purpose = $payment_data['purpose'];
$callback_url = $payment_data['callback_url'];

// =====================================================
// IPROGSMS API CONFIGURATION
// =====================================================
$sms_api_url = 'https://www.iprogsms.com/api/v1/sms_messages';
$sms_api_token = 'test'; // USE YOUR OWN TOKEN

// Function to send SMS via iProgSMS API
function sendOTPviaSMS($phone, $otp, $amount, $reference_id) {
    global $sms_api_url, $sms_api_token;
    
    // Format phone number - iProgSMS expects 639XXXXXXXXX format
    $formatted_phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert to 639 format if needed
    if (strlen($formatted_phone) === 11 && substr($formatted_phone, 0, 2) === '09') {
        // 09171234567 -> 639171234567
        $formatted_phone = '63' . substr($formatted_phone, 1);
    } elseif (strlen($formatted_phone) === 10 && substr($formatted_phone, 0, 1) === '9') {
        // 9171234567 -> 639171234567
        $formatted_phone = '63' . $formatted_phone;
    } elseif (strlen($formatted_phone) === 13 && substr($formatted_phone, 0, 4) === '+639') {
        // +639171234567 -> 639171234567
        $formatted_phone = substr($formatted_phone, 1);
    }
    
    // Verify phone format is correct
    if (strlen($formatted_phone) !== 12 || substr($formatted_phone, 0, 3) !== '639') {
        return [
            'success' => false,
            'error' => "Invalid phone format. Expected 639XXXXXXXXX, got $formatted_phone"
        ];
    }
    
    // Prepare message
    $message = "Your LGU payment code: $otp\n";
    $message .= "Use within 2 minutes\n";
    $message .= "Amt: ₱" . number_format($amount, 2) . "\n";
    $message .= "ID: $reference_id\n";
    $message .= "Do not share";
    
    // Prepare API data exactly as shown in iProgSMS documentation
    $data = [
        'api_token' => $sms_api_token,
        'message' => $message,
        'phone_number' => $formatted_phone
    ];
    
    // Send SMS via cURL
    $ch = curl_init($sms_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Debug: Log what happened
    error_log("iProgSMS API Response: HTTP $http_code - " . substr($response, 0, 200));
    
    // Check for cURL errors
    if ($curl_error) {
        return [
            'success' => false,
            'error' => "Network error: $curl_error",
            'http_code' => $http_code,
            'raw_response' => $response
        ];
    }
    
    // Try to parse JSON response
    $response_data = json_decode($response, true);
    
    if ($http_code === 200) {
        if (is_array($response_data)) {
            // Check for success
            if (isset($response_data['status']) && $response_data['status'] === 'success') {
                return [
                    'success' => true,
                    'response' => $response_data,
                    'formatted_phone' => $formatted_phone,
                    'message_sent' => $message
                ];
            } elseif (isset($response_data['success']) && $response_data['success'] === true) {
                return [
                    'success' => true,
                    'response' => $response_data,
                    'formatted_phone' => $formatted_phone,
                    'message_sent' => $message
                ];
            } elseif (isset($response_data['message_id'])) {
                return [
                    'success' => true,
                    'response' => $response_data,
                    'formatted_phone' => $formatted_phone,
                    'message_sent' => $message
                ];
            } else {
                // Check for error messages
                $error_msg = "API Error";
                if (isset($response_data['message'])) {
                    if (is_array($response_data['message'])) {
                        $error_msg .= ": " . implode(', ', $response_data['message']);
                    } else {
                        $error_msg .= ": " . $response_data['message'];
                    }
                }
                return [
                    'success' => false,
                    'error' => $error_msg,
                    'http_code' => $http_code,
                    'response' => $response_data,
                    'formatted_phone' => $formatted_phone
                ];
            }
        } else {
            // If response is not JSON but HTTP 200, assume success
            return [
                'success' => true,
                'response' => $response,
                'formatted_phone' => $formatted_phone,
                'message_sent' => $message
            ];
        }
    } else {
        // HTTP error
        $error_msg = "HTTP Error $http_code";
        if (is_array($response_data) && isset($response_data['message'])) {
            if (is_array($response_data['message'])) {
                $error_msg .= ": " . implode(', ', $response_data['message']);
            } else {
                $error_msg .= ": " . $response_data['message'];
            }
        }
        return [
            'success' => false,
            'error' => $error_msg,
            'http_code' => $http_code,
            'response' => $response_data ?? $response,
            'formatted_phone' => $formatted_phone
        ];
    }
}

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
                error_log("========================================");
                error_log("GCASH.PHP DEBUG START");
                error_log("Phone submitted: " . $clean_phone);

                // Generate OTP
                $generated_otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

                // Generate payment ID
                $payment_id = 'GCASH-' . date('YmdHis') . '-' . rand(1000, 9999);
                $created_at = date('Y-m-d H:i:s');

                // SEND SMS FIRST
                $sms_result = sendOTPviaSMS($clean_phone, $generated_otp, $amount, $reference_id);
                $otp_sent_status = $sms_result['success'];

                // INSERT PENDING TRANSACTION
                $insert_query = "
                    INSERT INTO payment_transactions 
                    (payment_id, client_system, client_reference, purpose, amount, phone, 
                    payment_method, payment_status, otp_code, otp_verified, receipt_number, 
                    created_at, callback_url, callback_sent, sms_sent, sms_response)
                    VALUES 
                    (:payment_id, :client_system, :client_reference, :purpose, :amount, :phone,
                    :payment_method, :payment_status, :otp_code, :otp_verified, :receipt_number,
                    :created_at, :callback_url, :callback_sent, :sms_sent, :sms_response)
                ";
                
                $stmt = $pdo->prepare($insert_query);

                $params = [
                    ':payment_id' => $payment_id,
                    ':client_system' => $client_system,
                    ':client_reference' => $reference_id,
                    ':purpose' => $purpose,
                    ':amount' => $amount,
                    ':phone' => $clean_phone,
                    ':payment_method' => 'gcash',
                    ':payment_status' => 'pending',
                    ':otp_code' => $generated_otp, 
                    ':otp_verified' => 0,
                    ':receipt_number' => NULL,
                    ':created_at' => $created_at,
                    ':callback_url' => $callback_url,
                    ':callback_sent' => 0,
                    ':sms_sent' => $sms_result['success'] ? 1 : 0,
                    ':sms_response' => json_encode($sms_result)
                ];
                
                $result = $stmt->execute($params);

                // Verify stored OTP
                $check_query = "SELECT otp_code FROM payment_transactions WHERE payment_id = :payment_id";
                $check_stmt = $pdo->prepare($check_query);
                $check_stmt->execute([':payment_id' => $payment_id]);
                $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);

                // Store session data
                $_SESSION['phone'] = $clean_phone;
                $_SESSION['generated_otp'] = $generated_otp;
                $_SESSION['otp_expires'] = time() + (2 * 60); // 2 minutes from current time
                $_SESSION['otp_attempts'] = 0;
                $_SESSION['pending_payment_id'] = $payment_id;
                
                // Store SMS message in session
                if ($sms_result['success']) {
                    $_SESSION['sms_message'] = 'Verification code sent to ' . $clean_phone;
                }

                header('Location: gcash_otp.php');
                exit();
                
            } catch (Exception $e) {
                $error = 'System error. Please try again. Error: ' . $e->getMessage();
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
        .gcash-header {
            background: linear-gradient(135deg, #0074E4 0%, #00A9FF 100%);
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">

    <div class="max-w-md mx-auto p-4">

        <!-- HEADER TOP -->
        <div class="gcash-header text-white rounded-t-2xl p-6 shadow-lg">
            <div class="flex items-center gap-3">
                <img src="images/gcash_img.jpg" class="w-14 h-14 rounded-lg shadow-md bg-white p-1" />
                <div>
                    <h1 class="text-2xl font-bold">Pay with GCash</h1>
                    <p class="text-blue-100 text-sm">Step 1: Enter your mobile number</p>
                </div>
            </div>
        </div>

        <!-- CONTENT CARD -->
        <div class="bg-white rounded-b-2xl shadow-lg p-6">

            <!-- PAYMENT DETAILS -->
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Payment Details</h2>

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

            <!-- ERROR -->
            <?php if ($error): ?>
                <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- FORM -->
            <form method="POST" class="mt-6">
                <input type="hidden" name="action" value="verify_phone">

                <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-mobile-alt mr-1"></i> GCash Mobile Number
                </label>

                <input type="tel"
                       id="phone"
                       name="phone"
                       value="<?php echo htmlspecialchars($phone); ?>"
                       placeholder="09123456789"
                       required
                       class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       oninput="formatPhoneNumber(this)">

                <button type="submit"
                        class="w-full mt-5 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-bold text-lg shadow-md transition">
                    <i class="fas fa-arrow-right mr-2"></i> Continue to OTP
                </button>
            </form>

            <!-- DEMO INFO -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-xl text-blue-700 text-sm">
                <i class="fas fa-info-circle mr-2"></i>
                Use your real phone number to receive OTP at sms
            </div>

            <!-- SECURITY -->
            <div class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 text-sm">
                <i class="fas fa-shield-alt mr-2 text-blue-600"></i>
                Your number is used for OTP verification only.
            </div>
        </div>

    </div>

<script>
    function formatPhoneNumber(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length > 11) value = value.substring(0, 11);
        input.value = value;
    }
</script>

</body> 
</html>