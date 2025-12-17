<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Government Services Management System - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="Login/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Ensure modal is perfectly centered */
        .modal-center {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-custom-bg min-h-screen flex flex-col">
    <!-- Header Section -->
    <header class="py-2">
        <div class="container mx-auto px-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-lg">
                        <img src="Login/images/GSM_logo.png" alt="GSM Logo" class="h-10 w-auto">
                    </div>
                    <h1 class="text-3xl lg:text-4xl font-bold" style="font-weight: 700;">
                        <span class="brand-go">Go</span><span class="brand-serve">Serve</span><span class="brand-ph">PH</span>
                    </h1>
                </div>
                <div class="text-right">
                    <div class="text-sm">
                        <div id="currentDateTime" class="font-semibold"></div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-6 pt-4 pb-12 flex-1">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <!-- Left Section - Features -->
            <div class="text-center lg:text-left mt-2">
                <h2 class="text-4xl lg:text-5xl font-bold mb-4 animated-gradient ml-2 lg:ml-4">
                    Abot-Kamay mo ang Serbisyong Publiko!
                </h2>
            </div>

            <!-- Right Section - Login Form -->
            <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm mx-auto w-full glass-card glow-on-hover mt-8">
                <form id="loginForm" class="space-y-5">
                    <div>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Enter e-mail address"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent transition-all duration-200"
                            required
                        >
                    </div>
                    
                    <div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Enter password"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent transition-all duration-200"
                            required
                        >
                    </div>
                    
                    <button 
                        type="submit" 
                        class="w-full bg-custom-secondary text-white py-3 px-6 rounded-lg font-semibold hover:bg-blue-700 transition-colors"
                        id="loginBtn"
                    >
                        Login
                    </button>
                    
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">OR</span>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <button 
                            type="button" 
                            class="w-full bg-white border border-gray-300 text-gray-700 py-3 px-6 rounded-lg font-semibold hover:bg-gray-50 transition-colors flex items-center justify-center space-x-2"
                            onclick="showNotification('Google login is currently unavailable', 'warning')"
                        >
                            <i class="fab fa-google text-red-500"></i>
                            <span>Continue with Google</span>
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-gray-600">
                            No account yet? 
                            <button type="button" id="showRegister" class="text-custom-secondary hover:underline font-semibold">Register here</button>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-custom-primary text-white py-4 mt-8">
        <div class="container mx-auto px-6">
            <div class="flex flex-col lg:flex-row justify-between items-center">
                <div class="text-center lg:text-left mb-2 lg:mb-0">
                    <h3 class="text-lg font-bold mb-1">Government Services Management System</h3>
                    <p class="text-xs opacity-90">
                        For any inquiries, please call 122 or email helpdesk@gov.ph
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex space-x-3">
                        <button type="button" id="footerTerms" class="text-xs hover:underline">TERMS OF SERVICE</button>
                        <span>|</span>
                        <button type="button" id="footerPrivacy" class="text-xs hover:underline">PRIVACY POLICY</button>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Registration Form - HIDDEN BY DEFAULT AND CENTERED -->
    <div id="registerFormContainer" class="fixed inset-0 bg-black/40 hidden p-4 z-50">
        <div class="flex items-center justify-center min-h-full">
            <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-custom-secondary">Create your GoServePH Account</h2>
                    <button type="button" id="cancelRegister" class="text-gray-500 hover:text-gray-700 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="registerForm" class="space-y-6">
                    <!-- Personal Information -->
                    <div class="border-b border-gray-200 pb-4">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">Personal Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                                <input type="text" name="firstName" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                                <input type="text" name="lastName" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                <input type="text" name="middleName" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                                <select name="suffix" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent">
                                    <option value="">Select Suffix</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="II">II</option>
                                    <option value="III">III</option>
                                    <option value="IV">IV</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate *</label>
                                <input type="date" name="birthdate" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="border-b border-gray-200 pb-4">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">Contact Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                <input type="email" name="regEmail" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number *</label>
                                <input type="tel" name="mobile" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent" 
                                       placeholder="0912 345 6789" pattern="[0-9]{11}">
                            </div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="border-b border-gray-200 pb-4">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">Address Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">House Number *</label>
                                <input type="text" name="houseNumber" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Street *</label>
                                <input type="text" name="street" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Barangay *</label>
                                <input type="text" name="barangay" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent">
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Complete Address</label>
                            <textarea name="address" rows="2" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent"
                                      placeholder="Full address including city and province"></textarea>
                        </div>
                    </div>

                    <!-- Account Security -->
                    <div class="border-b border-gray-200 pb-4">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">Account Security</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                                <input type="password" name="regPassword" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent"
                                       minlength="6">
                                <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                                <input type="password" name="confirmPassword" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="space-y-3">
                            <div class="flex items-start space-x-3">
                                <input type="checkbox" id="agreeTerms" name="agreeTerms" required 
                                       class="mt-1 w-4 h-4 text-custom-secondary focus:ring-custom-secondary border-gray-300 rounded">
                                <label for="agreeTerms" class="text-sm text-gray-700">
                                    I agree to the <button type="button" class="text-custom-secondary hover:underline font-medium">Terms of Service</button> *
                                </label>
                            </div>
                            <div class="flex items-start space-x-3">
                                <input type="checkbox" id="agreePrivacy" name="agreePrivacy" required 
                                       class="mt-1 w-4 h-4 text-custom-secondary focus:ring-custom-secondary border-gray-300 rounded">
                                <label for="agreePrivacy" class="text-sm text-gray-700">
                                    I agree to the <button type="button" class="text-custom-secondary hover:underline font-medium">Privacy Policy</button> *
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" id="cancelRegisterBtn" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors font-medium">
                            Cancel
                        </button>
                        <button type="submit" class="bg-custom-secondary text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                            Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- OTP Verification Modal -->
    <div id="otpModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-custom-secondary">Enter OTP Verification</h2>
                <button type="button" id="closeOtpModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <div class="text-center mb-6">
                <p class="text-gray-600">We've sent a 6-digit OTP to your email</p>
                <p id="otpEmail" class="font-semibold text-custom-secondary mt-1"></p>
                <p id="otpTimer" class="text-sm text-gray-500 mt-2">03:00</p>
            </div>
            
            <form id="otpForm" class="space-y-4">
                <div class="flex justify-center space-x-2 mb-4">
                    <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent" required>
                    <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent" required>
                    <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent" required>
                    <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent" required>
                    <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent" required>
                    <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent" required>
                </div>
                
                <div id="otpError" class="text-red-500 text-sm text-center hidden"></div>
                
                <div class="flex justify-between items-center">
                    <button type="button" id="resendOtp" class="text-custom-secondary hover:underline disabled:text-gray-400" disabled>
                        Resend OTP
                    </button>
                    <div class="flex space-x-2">
                        <button type="button" id="cancelOtp" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" id="submitOtp" class="bg-custom-secondary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            Verify
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Updated JavaScript -->
    <script>
        // Adjust API path for localhost vs domain
const API_BASE = window.location.hostname === 'localhost'
    ? '/revenue/Login/api/'  // localhost path
    : '/Login/api/';          // domain path


        let currentUserId = null;
        let otpTimer = null;
        let otpTimeLeft = 180;

        // Initialize application
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM loaded, initializing app...');
            updateDateTime();
            setInterval(updateDateTime, 1000);
            setupEventListeners();
            setupOTPInputs();
        });

        function setupEventListeners() {
            // Login form
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', handleLoginSubmit);
            }

            // Register form
            const registerForm = document.getElementById('registerForm');
            if (registerForm) {
                registerForm.addEventListener('submit', handleRegisterSubmit);
            }

            // Show register form
            const showRegister = document.getElementById('showRegister');
            if (showRegister) {
                showRegister.addEventListener('click', showRegisterForm);
            }

            // Cancel register buttons
            const cancelRegister = document.getElementById('cancelRegister');
            const cancelRegisterBtn = document.getElementById('cancelRegisterBtn');
            if (cancelRegister) cancelRegister.addEventListener('click', hideRegisterForm);
            if (cancelRegisterBtn) cancelRegisterBtn.addEventListener('click', hideRegisterForm);

            // OTP buttons
            const cancelOtp = document.getElementById('cancelOtp');
            const resendOtp = document.getElementById('resendOtp');
            const submitOtp = document.getElementById('submitOtp');
            const closeOtpModal = document.getElementById('closeOtpModal');
            
            if (cancelOtp) cancelOtp.addEventListener('click', closeOtpModal);
            if (resendOtp) resendOtp.addEventListener('click', handleResendOtp);
            if (submitOtp) submitOtp.addEventListener('click', handleVerifyOtp);
            if (closeOtpModal) closeOtpModal.addEventListener('click', closeOtpModal);

            // OTP form submit
            const otpForm = document.getElementById('otpForm');
            if (otpForm) {
                otpForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleVerifyOtp();
                });
            }

            // Modal background clicks
            const registerModal = document.getElementById('registerFormContainer');
            const otpModal = document.getElementById('otpModal');
            
            if (registerModal) {
                registerModal.addEventListener('click', function(e) {
                    if (e.target === this) hideRegisterForm();
                });
            }
            
            if (otpModal) {
                otpModal.addEventListener('click', function(e) {
                    if (e.target === this) closeOtpModal();
                });
            }

            // Terms and Privacy buttons
            const footerTerms = document.getElementById('footerTerms');
            const footerPrivacy = document.getElementById('footerPrivacy');
            
            if (footerTerms) {
                footerTerms.addEventListener('click', function() {
                    showNotification('Terms of Service will be available soon', 'info');
                });
            }
            
            if (footerPrivacy) {
                footerPrivacy.addEventListener('click', function() {
                    showNotification('Privacy Policy will be available soon', 'info');
                });
            }
        }

        function setupOTPInputs() {
            const inputs = document.querySelectorAll('.otp-input');
            
            inputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    const value = e.target.value.replace(/[^0-9]/g, '');
                    e.target.value = value;
                    
                    if (value && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                });
                
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                });
                
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pasteData = e.clipboardData.getData('text').replace(/[^0-9]/g, '');
                    const digits = pasteData.split('').slice(0, 6);
                    
                    digits.forEach((digit, i) => {
                        if (inputs[i]) {
                            inputs[i].value = digit;
                        }
                    });
                    
                    if (digits.length < 6 && inputs[digits.length]) {
                        inputs[digits.length].focus();
                    }
                });
            });
        }

        // Main handler functions
        async function handleLoginSubmit(e) {
            e.preventDefault();
            console.log('🔐 Login form submitted');
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            const loginBtn = document.getElementById('loginBtn');

            // Basic validation
            if (!email || !password) {
                showNotification('Please enter both email and password', 'error');
                return;
            }

            if (!isValidEmail(email)) {
                showNotification('Please enter a valid email address', 'error');
                return;
            }

            // Show loading state
            setButtonLoading(loginBtn, true, 'Logging in...');

            try {
                console.log('🔐 Attempting login for:', email);
                
                const response = await fetch(API_BASE + 'auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'login',
                        email: email,
                        password: password
                    })
                });

                const data = await response.json();
                console.log('📨 Login response:', data);

                if (data.success) {
                    console.log('✅ Login successful');
                    
                    // Check if user is admin
                   if (data.user_role === 'admin') {
    console.log('👑 Admin user detected, redirecting to admin panel');
    showNotification('Admin login successful! Redirecting...', 'success');

    // Use relative path to automatically find index.html
    setTimeout(() => {
    window.location.href = 'dist/index.html';
}, 1000);
} else {
    // Regular user - proceed with OTP
    console.log('👤 Regular user, opening OTP modal');
    currentUserId = data.user_id;
    if (data.debug_otp) {
        console.log('🔑 DEBUG OTP:', data.debug_otp);
        showNotification('Login successful! OTP: ' + data.debug_otp, 'success');
    } else {
        showNotification('Login successful! OTP sent to your email.', 'success');
    }
    openOtpModal();
}

                } else {
                    console.log('❌ Login failed:', data.message);
                    showNotification(data.message || 'Invalid email or password', 'error');
                }
            } catch (error) {
                console.error('🚨 Login error:', error);
                showNotification('Network error. Please try again.', 'error');
            } finally {
                setButtonLoading(loginBtn, false, 'Login');
            }
        }

        async function handleRegisterSubmit(e) {
            e.preventDefault();
            console.log('📝 Register form submitted');
            
            const formData = new FormData(document.getElementById('registerForm'));
            const data = Object.fromEntries(formData.entries());
            const registerBtn = document.querySelector('#registerForm button[type="submit"]');

            // Basic validation
            const requiredFields = ['firstName', 'lastName', 'regEmail', 'regPassword', 'confirmPassword', 'birthdate', 'mobile', 'houseNumber', 'street', 'barangay'];
            for (const field of requiredFields) {
                if (!data[field] || data[field].trim() === '') {
                    showNotification('Please fill in all required fields', 'error');
                    return;
                }
            }

            if (data.regPassword !== data.confirmPassword) {
                showNotification('Passwords do not match', 'error');
                return;
            }

            if (!isValidEmail(data.regEmail)) {
                showNotification('Please enter a valid email address', 'error');
                return;
            }

            // Check if terms are agreed
            if (!data.agreeTerms || !data.agreePrivacy) {
                showNotification('Please agree to the Terms of Service and Privacy Policy', 'error');
                return;
            }

            setButtonLoading(registerBtn, true, 'Creating Account...');

            try {
                const response = await fetch(API_BASE + 'auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'register',
                        ...data
                    })
                });

                const result = await response.json();
                console.log('📨 Register response:', result);
                
                if (result.success) {
                    currentUserId = result.user_id;
                    
                    // Show debug OTP if available
                    if (result.debug_otp) {
                        console.log('🔑 DEBUG OTP:', result.debug_otp);
                        showNotification('Registration successful! OTP: ' + result.debug_otp, 'success');
                    } else {
                        showNotification('Registration successful! OTP sent to your email.', 'success');
                    }
                    
                    hideRegisterForm();
                    openOtpModal();
                } else {
                    showNotification(result.message || 'Registration failed', 'error');
                }
            } catch (error) {
                console.error('Registration error:', error);
                showNotification('Network error. Please try again.', 'error');
            } finally {
                setButtonLoading(registerBtn, false, 'Create Account');
            }
        }

        async function handleVerifyOtp() {
            console.log('🔑 Verifying OTP...');
            const otpCode = getOtpCode();
            const submitBtn = document.getElementById('submitOtp');
            const errorElement = document.getElementById('otpError');

            if (!otpCode || otpCode.length !== 6) {
                showError('Please enter the complete 6-digit OTP');
                return;
            }

            setButtonLoading(submitBtn, true, 'Verifying...');
            hideError();

            try {
                const response = await fetch(API_BASE + 'auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'verify_otp',
                        user_id: currentUserId,
                        otp_code: otpCode
                    })
                });

                const data = await response.json();
                console.log('📨 OTP verification response:', data);
                
                if (data.success) {
                    showNotification('OTP verified successfully!', 'success');
                    closeOtpModal();
                    // Redirect to citizen dashboard
                    setTimeout(() => {
                       window.location.href = './citizen_dashboard/citizen_dashboard.php';
                    }, 1500);
                } else {
                    showError(data.message || 'Invalid OTP');
                }
            } catch (error) {
                console.error('OTP verification error:', error);
                showError('Network error. Please try again.');
            } finally {
                setButtonLoading(submitBtn, false, 'Verify');
            }
        }

        async function handleResendOtp() {
            console.log('🔄 Resending OTP...');
            const resendBtn = document.getElementById('resendOtp');
            
            setButtonLoading(resendBtn, true, 'Sending...');

            try {
                const response = await fetch(API_BASE + 'auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'resend_otp',
                        user_id: currentUserId
                    })
                });

                const data = await response.json();
                console.log('📨 Resend OTP response:', data);
                
                if (data.success) {
                    // Show debug OTP if available
                    if (data.debug_otp) {
                        console.log('🔑 DEBUG OTP:', data.debug_otp);
                        showNotification('New OTP: ' + data.debug_otp, 'success');
                    } else {
                        showNotification('New OTP sent to your email', 'success');
                    }
                    startOtpTimer();
                } else {
                    showNotification(data.message || 'Failed to resend OTP', 'error');
                }
            } catch (error) {
                console.error('Resend OTP error:', error);
                showNotification('Network error. Please try again.', 'error');
            } finally {
                setButtonLoading(resendBtn, false, 'Resend OTP');
                resendBtn.disabled = true;
            }
        }

        // UI Functions
        function showRegisterForm() {
            const container = document.getElementById('registerFormContainer');
            if (container) {
                container.classList.remove('hidden');
                container.classList.add('modal-center');
                document.body.style.overflow = 'hidden';
            }
        }

        function hideRegisterForm() {
            const container = document.getElementById('registerFormContainer');
            if (container) {
                container.classList.add('hidden');
                container.classList.remove('modal-center');
                document.body.style.overflow = 'auto';
                container.querySelector('form').reset();
            }
        }

        function openOtpModal() {
            console.log('🔑 Opening OTP modal');
            const modal = document.getElementById('otpModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.style.overflow = 'hidden';
                resetOtpInputs();
                startOtpTimer();
                hideError();
                console.log('✅ OTP modal opened successfully');
            } else {
                console.error('❌ OTP modal element not found!');
            }
        }

        function closeOtpModal() {
            console.log('🔑 Closing OTP modal');
            const modal = document.getElementById('otpModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = 'auto';
                stopOtpTimer();
                hideError();
            }
        }

        // OTP Functions
        function getOtpCode() {
            const inputs = document.querySelectorAll('.otp-input');
            const code = Array.from(inputs).map(input => input.value).join('');
            console.log('🔑 OTP code entered:', code);
            return code;
        }

        function resetOtpInputs() {
            const inputs = document.querySelectorAll('.otp-input');
            inputs.forEach(input => input.value = '');
            if (inputs[0]) inputs[0].focus();
        }

        function startOtpTimer() {
            otpTimeLeft = 180;
            const timerElement = document.getElementById('otpTimer');
            const resendButton = document.getElementById('resendOtp');
            
            if (resendButton) {
                resendButton.disabled = true;
            }
            
            updateTimerDisplay();
            
            if (otpTimer) {
                clearInterval(otpTimer);
            }
            
            otpTimer = setInterval(() => {
                otpTimeLeft--;
                updateTimerDisplay();
                
                if (otpTimeLeft <= 0) {
                    stopOtpTimer();
                    if (resendButton) {
                        resendButton.disabled = false;
                    }
                }
            }, 1000);
        }

        function stopOtpTimer() {
            if (otpTimer) {
                clearInterval(otpTimer);
                otpTimer = null;
            }
        }

        function updateTimerDisplay() {
            const timerElement = document.getElementById('otpTimer');
            if (timerElement) {
                const minutes = Math.floor(otpTimeLeft / 60);
                const seconds = otpTimeLeft % 60;
                timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
        }

        // Utility Functions
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true 
            };
            
            const dateTimeString = now.toLocaleDateString('en-US', options).toUpperCase();
            const dateTimeElement = document.getElementById('currentDateTime');
            
            if (dateTimeElement) {
                dateTimeElement.textContent = dateTimeString;
            }
        }

        function setButtonLoading(button, isLoading, text = '') {
            if (!button) return;
            
            if (isLoading) {
                button.disabled = true;
                button.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i> ${text}`;
            } else {
                button.disabled = false;
                button.textContent = text;
            }
        }

        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existing = document.querySelectorAll('.notification');
            existing.forEach(notif => notif.remove());

            const notification = document.createElement('div');
            notification.className = `notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg text-white transform transition-all duration-300 translate-x-full ${
                type === 'success' ? 'bg-green-500' :
                type === 'error' ? 'bg-red-500' :
                type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
            }`;
            
            const icon = type === 'success' ? 'fa-check-circle' :
                         type === 'error' ? 'fa-exclamation-circle' :
                         type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
            
            notification.innerHTML = `
                <div class="flex items-center space-x-2">
                    <i class="fas ${icon}"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 hover:opacity-70">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.classList.add('translate-x-full');
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 300);
                }
            }, 5000);
        }

        function showError(message) {
            const errorElement = document.getElementById('otpError');
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.classList.remove('hidden');
            }
        }

        function hideError() {
            const errorElement = document.getElementById('otpError');
            if (errorElement) {
                errorElement.classList.add('hidden');
            }
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Make functions globally available
        window.showNotification = showNotification;
        window.handleSocialLogin = function(provider) {
            showNotification(`${provider} login is currently unavailable`, 'warning');
        };
    </script>
</body>
</html>