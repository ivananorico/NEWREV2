<?php
session_start();

// Enable ALL error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers FIRST
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Default response
$response = ['success' => false, 'message' => 'System error. Please try again.'];

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    
    if (!$json) {
        $response['message'] = 'No data received';
        echo json_encode($response);
        exit;
    }
    
    $input = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $response['message'] = 'Invalid JSON data';
        echo json_encode($response);
        exit;
    }

    if (!isset($input['action'])) {
        $response['message'] = 'Action not specified';
        echo json_encode($response);
        exit;
    }

    // Define base path
    define('BASE_PATH', dirname(__DIR__));
    
    // Include required files
    require_once BASE_PATH . '/config/database.php';
    require_once BASE_PATH . '/models/User.php';
    require_once BASE_PATH . '/models/OTP.php';
    require_once BASE_PATH . '/services/EmailService.php';

    $database = new Database();
    $db = $database->getConnection();

    switch ($input['action']) {
        case 'test':
            $response['success'] = true;
            $response['message'] = 'API is working!';
            $response['timestamp'] = date('Y-m-d H:i:s');
            break;
            
        case 'login':
            handleLogin($db, $input, $response);
            break;
            
        case 'register':
            handleRegister($db, $input, $response);
            break;
            
        case 'verify_otp':
            handleOTPVerification($db, $input, $response);
            break;
            
        case 'resend_otp':
            handleResendOTP($db, $input, $response);
            break;
            
        default:
            $response['message'] = 'Invalid action';
    }

} catch (Exception $e) {
    error_log("Auth Exception: " . $e->getMessage());
    $response['message'] = 'System error: ' . $e->getMessage();
    $response['error_details'] = $e->getMessage();
}

// Always return valid JSON
echo json_encode($response);
exit;

// ============================================
// Handler Functions
// ============================================

function handleLogin($db, $input, &$response) {
    if (!isset($input['email']) || !isset($input['password'])) {
        $response['message'] = 'Email and password are required';
        return;
    }

    $user = new User($db);
    $user->email = trim($input['email']);

    if (!$user->emailExists()) {
        $response['message'] = 'Invalid email or password';
        return;
    }

    if (!$user->verifyPassword($input['password'])) {
        $response['message'] = 'Invalid email or password';
        return;
    }

    // Admin users bypass OTP
    if ($user->role === 'admin') {
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_name'] = $user->first_name . ' ' . $user->last_name;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_role'] = 'admin';
        $_SESSION['logged_in'] = true;
        
        $response['success'] = true;
        $response['message'] = 'Admin login successful';
        $response['user_role'] = 'admin';
        $response['user_id'] = $user->id;
        return;
    }

    // Regular users need OTP
    $otp = new OTP($db);
    $otp_code = $otp->generateOTP();
    $otp->user_id = $user->id;
    $otp->otp_code = $otp_code;

    if ($otp->create()) {
        $emailService = new EmailService();
        $emailSent = $emailService->sendOTP(
            $user->email, 
            $user->first_name . ' ' . $user->last_name, 
            $otp_code
        );

        if ($emailSent) {
            $response['success'] = true;
            $response['message'] = 'OTP sent to your email';
            $response['user_id'] = $user->id;
            $response['user_role'] = 'user';
        } else {
            $response['success'] = true;
            $response['message'] = 'OTP: ' . $otp_code . ' (Email failed)';
            $response['user_id'] = $user->id;
            $response['user_role'] = 'user';
            $response['debug_otp'] = $otp_code;
        }
    } else {
        $response['message'] = 'Failed to generate OTP';
    }
}

function handleRegister($db, $input, &$response) {
    // Define required fields (with district)
    $required_fields = [
        'firstName', 'lastName', 'regEmail', 'regPassword', 'confirmPassword',
        'birthdate', 'mobile', 'houseNumber', 'street', 'barangay',
        'district', 'city', 'province', 'zipCode'
    ];
    
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            $response['message'] = ucfirst($field) . ' is required';
            return;
        }
    }

    // Validate email
    if (!filter_var($input['regEmail'], FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format';
        return;
    }

    // Check if passwords match
    if ($input['regPassword'] !== $input['confirmPassword']) {
        $response['message'] = 'Passwords do not match';
        return;
    }

    // Validate password length
    if (strlen($input['regPassword']) < 6) {
        $response['message'] = 'Password must be at least 6 characters';
        return;
    }

    // Validate mobile number
    if (!preg_match('/^09[0-9]{9}$/', $input['mobile'])) {
        $response['message'] = 'Please enter a valid 11-digit mobile number (09XXXXXXXXX)';
        return;
    }

    // Validate ZIP code
    if (!preg_match('/^\d{4}$/', $input['zipCode'])) {
        $response['message'] = 'Please enter a valid 4-digit ZIP code';
        return;
    }

    // Validate district
    if (!in_array($input['district'], ['1', '2', '3', '4', '5', '6'])) {
        $response['message'] = 'Please select a valid district';
        return;
    }

    // Validate fixed city and province
    if ($input['city'] !== 'Quezon City') {
        $response['message'] = 'City must be Quezon City';
        return;
    }

    if ($input['province'] !== 'Metro Manila') {
        $response['message'] = 'Province must be Metro Manila';
        return;
    }

    $user = new User($db);
    $user->email = trim($input['regEmail']);

    // Check if email already exists
    if ($user->emailExists()) {
        $response['message'] = 'Email already registered';
        return;
    }

    // Set user properties
    $user->first_name = trim($input['firstName']);
    $user->last_name = trim($input['lastName']);
    $user->middle_name = isset($input['middleName']) ? trim($input['middleName']) : null;
    $user->suffix = isset($input['suffix']) ? trim($input['suffix']) : null;
    $user->birthdate = trim($input['birthdate']);
    $user->mobile = trim($input['mobile']);
    $user->house_number = trim($input['houseNumber']);
    $user->street = trim($input['street']);
    $user->barangay = trim($input['barangay']);
    $user->district = trim($input['district']);
    $user->city = trim($input['city']);
    $user->province = trim($input['province']);
    $user->zip_code = trim($input['zipCode']);
    $user->password_hash = $input['regPassword'];

    if ($user->create()) {
        $otp = new OTP($db);
        $otp_code = $otp->generateOTP();
        $otp->user_id = $user->id;
        $otp->otp_code = $otp_code;

        if ($otp->create()) {
            $emailService = new EmailService();
            $emailSent = $emailService->sendOTP(
                $user->email, 
                $user->first_name . ' ' . $user->last_name, 
                $otp_code
            );

            if ($emailSent) {
                $response['success'] = true;
                $response['message'] = 'Registration successful! OTP sent to your email.';
                $response['user_id'] = $user->id;
            } else {
                $response['success'] = true;
                $response['message'] = 'Registration successful! OTP: ' . $otp_code . ' (Email failed)';
                $response['user_id'] = $user->id;
                $response['debug_otp'] = $otp_code;
            }
        } else {
            $response['message'] = 'Registration successful but failed to generate OTP';
        }
    } else {
        $response['message'] = 'Registration failed. Please try again.';
    }
}

function handleOTPVerification($db, $input, &$response) {
    if (!isset($input['user_id']) || !isset($input['otp_code'])) {
        $response['message'] = 'User ID and OTP code are required';
        return;
    }

    $otp = new OTP($db);
    
    if ($otp->verify($input['user_id'], $input['otp_code'])) {
        $user = new User($db);
        
        if ($user->getUserById($input['user_id'])) {
            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_name'] = $user->first_name . ' ' . $user->last_name;
            $_SESSION['user_email'] = $user->email;
            $_SESSION['user_role'] = 'citizen';
            $_SESSION['logged_in'] = true;
            
            if ($user->activateAccount()) {
                $response['success'] = true;
                $response['message'] = 'OTP verified successfully!';
                $response['redirect_url'] = 'citizen_dashboard/citizen_dashboard.php';
            } else {
                $response['message'] = 'Failed to activate account';
            }
        } else {
            $response['message'] = 'User not found';
        }
    } else {
        $response['message'] = 'Invalid or expired OTP';
    }
}

function handleResendOTP($db, $input, &$response) {
    if (!isset($input['user_id'])) {
        $response['message'] = 'User ID is required';
        return;
    }

    $user = new User($db);
    if (!$user->getUserById($input['user_id'])) {
        $response['message'] = 'User not found';
        return;
    }

    $otp = new OTP($db);
    $otp_code = $otp->generateOTP();
    $otp->user_id = $input['user_id'];
    $otp->otp_code = $otp_code;

    if ($otp->create()) {
        $emailService = new EmailService();
        $emailSent = $emailService->sendOTP(
            $user->email, 
            $user->first_name . ' ' . $user->last_name, 
            $otp_code
        );

        if ($emailSent) {
            $response['success'] = true;
            $response['message'] = 'New OTP sent to your email';
        } else {
            $response['success'] = true;
            $response['message'] = 'New OTP: ' . $otp_code . ' (Email failed)';
            $response['debug_otp'] = $otp_code;
        }
    } else {
        $response['message'] = 'Failed to generate new OTP';
    }
}
?>