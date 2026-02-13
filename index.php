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
    <link rel="icon" type="image/png" href="Login/images/GSM_logo.png">
    <style>
        /* Custom styles for better modal handling */
        .modal-container {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal-content {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 56rem;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .otp-modal {
            max-width: 28rem;
        }
        
        .terms-modal {
            max-width: 48rem;
        }
        
        /* Hide scrollbar when modal is open */
        body.modal-open {
            overflow: hidden;
        }
        
        /* Notification styles */
        .notification {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1001;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            color: white;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        /* Animation for OTP inputs */
        .otp-input {
            transition: all 0.2s ease;
        }
        
        .otp-input:focus {
            transform: scale(1.05);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* OTP input focus styling */
        .otp-input.filled {
            background-color: #f0f9ff;
            border-color: #3b82f6;
        }
        
        /* Password strength indicator */
        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
            margin-top: 4px;
        }
        
        .strength-weak { width: 25%; background-color: #ef4444; }
        .strength-fair { width: 50%; background-color: #f59e0b; }
        .strength-good { width: 75%; background-color: #3b82f6; }
        .strength-strong { width: 100%; background-color: #10b981; }
        
        .password-requirements {
            font-size: 0.75rem;
            margin-top: 4px;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 2px;
        }
        
        .requirement.met {
            color: #10b981;
        }
        
        .requirement.unmet {
            color: #6b7280;
        }
        
        .requirement i {
            font-size: 0.6rem;
        }
        
        /* Background image fix */
        .bg-custom-bg {
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
        }
        
        /* Custom scrollbar for terms modal */
        .terms-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .terms-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .terms-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        .terms-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Lockout modal styles */
        .lockout-modal {
            max-width: 24rem;
        }
        
        .lockout-timer {
            font-family: monospace;
            font-size: 2rem;
            font-weight: bold;
            color: #ef4444;
        }
        
        .attempts-counter {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .attempts-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .attempts-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .disabled-input {
            background-color: #f3f4f6;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        /* Session timeout indicator */
        .timeout-indicator {
            border-top: 3px solid #ef4444;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Attempts badge */
        .attempts-badge {
            position: fixed;
            top: 80px;
            right: 20px;
            background: white;
            padding: 10px 20px;
            border-radius: 50px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #3b82f6;
        }
        
        .attempts-badge.warning {
            border-left-color: #f59e0b;
        }
        
        .attempts-badge.danger {
            border-left-color: #ef4444;
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
                   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Abot-Kamay mo ang &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Serbisyong Publiko!
                </h2>
                
                <!-- Login Attempts Display - ALWAYS VISIBLE when attempts > 0 -->
                <div id="attemptsDisplay" class="mt-4 ml-4">
                    <div id="attemptsBadge" class="inline-flex items-center px-4 py-2 rounded-lg bg-white shadow-md border-l-4 border-blue-500 hidden">
                        <span class="text-sm font-medium text-gray-700 mr-2">⚠️ Failed Attempts:</span>
                        <span id="attemptCount" class="attempts-counter attempts-warning">0/3</span>
                        <span id="attemptsRemainingBadge" class="ml-2 text-xs text-gray-600"></span>
                    </div>
                </div>
            </div>

            <!-- Right Section - Login Form -->
            <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm mx-auto w-full glass-card glow-on-hover mt-8">
                <div class="text-center mb-4">
                    <span class="text-2xl font-bold text-custom-secondary border-b-2 border-custom-secondary pb-2">Login</span>
                </div>
                
                <!-- Lockout Message (shown when account is locked) -->
                <div id="lockoutMessage" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm hidden">
                    <div class="flex items-center">
                        <i class="fas fa-lock mr-2"></i>
                        <span id="lockoutText">Account temporarily locked. Please try again later.</span>
                    </div>
                    <div id="lockoutCountdown" class="mt-2 text-center font-bold text-red-600">
                        Time remaining: <span id="lockoutTimerDisplay">15:00</span>
                    </div>
                </div>
                
                <form id="loginForm" class="space-y-5">
                    <div>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Enter e-mail address"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent transition-all duration-200"
                            required
                            autocomplete="email"
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
                            autocomplete="current-password"
                        >
                    </div>
                    
                    <div id="attemptsLeft" class="text-xs text-right hidden">
                        <span id="attemptsLeftText" class="font-semibold"></span>
                    </div>
                    
                    <button 
                        type="submit" 
                        class="w-full bg-custom-secondary text-white py-3 px-6 rounded-lg font-semibold hover:bg-blue-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                        id="loginBtn"
                    >
                        Login
                    </button>
                    
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

    <!-- Registration Form Modal -->
    <div id="registerFormContainer" class="modal-container hidden">
        <div class="modal-content">
            <div class="p-6">
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">House Number/Unit *</label>
                                <input type="text" name="houseNumber" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent"
                                       placeholder="123">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Street *</label>
                                <input type="text" name="street" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent"
                                       placeholder="Main Street">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Barangay *</label>
                                <select name="barangay" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent">
                                    <option value="">Select Barangay</option>
                                    <option value="Barangay 1">Barangay 1</option>
                                    <option value="Barangay 2">Barangay 2</option>
                                    <option value="Barangay 3">Barangay 3</option>
                                    <option value="Barangay 4">Barangay 4</option>
                                    <option value="Barangay 5">Barangay 5</option>
                                    <option value="Barangay 6">Barangay 6</option>
                                    <option value="Barangay 7">Barangay 7</option>
                                    <option value="Barangay 8">Barangay 8</option>
                                    <option value="Barangay 9">Barangay 9</option>
                                    <option value="Barangay 10">Barangay 10</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">District *</label>
                                <select name="district" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent">
                                    <option value="">Select District</option>
                                    <option value="1">District 1</option>
                                    <option value="2">District 2</option>
                                    <option value="3">District 3</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">City/Municipality *</label>
                                <input type="text" name="city" value="Caloocan City" required 
                                       readonly
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                                <p class="text-xs text-gray-500 mt-1">Fixed to Caloocan City</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Province *</label>
                                <input type="text" name="province" value="Metro Manila" required 
                                       readonly
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                                <p class="text-xs text-gray-500 mt-1">Fixed to Metro Manila</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code *</label>
                                <input type="text" name="zipCode" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent"
                                       placeholder="1400" pattern="[0-9]{4}">
                                <p class="text-xs text-gray-500 mt-1">Caloocan City ZIP code: 1400 (North) / 1403 (South)</p>
                            </div>
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
                                       minlength="6" id="regPassword">
                                
                                <!-- Password strength indicator -->
                                <div id="passwordStrength" class="password-strength"></div>
                                
                                <!-- Password requirements -->
                                <div id="passwordRequirements" class="password-requirements hidden">
                                    <div class="requirement unmet" id="reqLength">
                                        <i class="fas fa-circle"></i>
                                        <span>At least 8 characters</span>
                                    </div>
                                    <div class="requirement unmet" id="reqUppercase">
                                        <i class="fas fa-circle"></i>
                                        <span>One uppercase letter</span>
                                    </div>
                                    <div class="requirement unmet" id="reqLowercase">
                                        <i class="fas fa-circle"></i>
                                        <span>One lowercase letter</span>
                                    </div>
                                    <div class="requirement unmet" id="reqNumber">
                                        <i class="fas fa-circle"></i>
                                        <span>One number</span>
                                    </div>
                                    <div class="requirement unmet" id="reqSpecial">
                                        <i class="fas fa-circle"></i>
                                        <span>One special character</span>
                                    </div>
                                </div>
                                
                                <p class="text-xs text-gray-500 mt-1">Password must be strong for security</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                                <input type="password" name="confirmPassword" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary focus:border-transparent"
                                       id="confirmPassword">
                                <div id="passwordMatch" class="text-sm mt-1 hidden">
                                    <i class="fas fa-check text-green-500"></i>
                                    <span class="text-green-600 ml-1">Passwords match</span>
                                </div>
                                <div id="passwordMismatch" class="text-sm mt-1 hidden">
                                    <i class="fas fa-times text-red-500"></i>
                                    <span class="text-red-600 ml-1">Passwords do not match</span>
                                </div>
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
                                    I agree to the <button type="button" class="text-custom-secondary hover:underline font-medium show-terms-modal">Terms of Service</button> *
                                </label>
                            </div>
                            <div class="flex items-start space-x-3">
                                <input type="checkbox" id="agreePrivacy" name="agreePrivacy" required 
                                       class="mt-1 w-4 h-4 text-custom-secondary focus:ring-custom-secondary border-gray-300 rounded">
                                <label for="agreePrivacy" class="text-sm text-gray-700">
                                    I agree to the <button type="button" class="text-custom-secondary hover:underline font-medium show-privacy-modal">Privacy Policy</button> *
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" id="cancelRegisterBtn" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors font-medium">
                            Cancel
                        </button>
                        <button type="submit" class="bg-custom-secondary text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium" id="registerSubmitBtn" disabled>
                            Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- OTP Verification Modal -->
    <div id="otpModal" class="modal-container hidden">
        <div class="modal-content otp-modal">
            <div class="p-6">
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
                    <div class="flex justify-center space-x-2 mb-4" id="otpContainer">
                        <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary" 
                               data-index="0" required>
                        <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary" 
                               data-index="1" required>
                        <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary" 
                               data-index="2" required>
                        <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary" 
                               data-index="3" required>
                        <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary" 
                               data-index="4" required>
                        <input type="text" maxlength="1" class="otp-input w-12 h-12 text-center text-xl border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-secondary" 
                               data-index="5" required>
                    </div>
                    
                    <div id="otpError" class="text-red-500 text-sm text-center hidden"></div>
                    <div id="otpAttempts" class="text-xs text-center text-gray-600 hidden">
                        Failed attempts: <span id="otpAttemptCount">0</span>/3
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <button type="button" id="resendOtp" class="text-custom-secondary hover:underline disabled:text-gray-400 disabled:cursor-not-allowed" disabled>
                            Resend OTP
                        </button>
                        <div class="flex space-x-2">
                            <button type="button" id="cancelOtp" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                                Cancel
                            </button>
                            <button type="button" id="submitOtp" class="bg-custom-secondary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                Verify
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Account Lockout Modal (shown after 3 failed attempts) -->
    <div id="lockoutModal" class="modal-container hidden">
        <div class="modal-content lockout-modal">
            <div class="p-6">
                <div class="text-center mb-4">
                    <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-lock text-red-600 text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-red-600 mb-2">Account Locked</h2>
                    <p class="text-gray-600 mb-2">Too many failed login attempts</p>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-2">Please wait before trying again</p>
                        <div id="lockoutTimer" class="lockout-timer">15:00</div>
                        <p class="text-xs text-gray-500 mt-2">This is a security measure to protect your account</p>
                    </div>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                    <div class="flex items-start">
                        <i class="fas fa-shield-alt text-yellow-600 mt-0.5 mr-2"></i>
                        <div class="text-xs text-yellow-800">
                            <p class="font-semibold mb-1">Security Recommendation:</p>
                            <p>If you've forgotten your password, please use the "Forgot Password" option or contact our support team at helpdesk@gov.ph</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-center">
                    <button type="button" id="closeLockoutModal" class="bg-custom-secondary text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms of Service Modal -->
    <div id="termsModal" class="modal-container hidden">
        <div class="modal-content terms-modal">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-custom-secondary">Terms of Service</h2>
                    <button type="button" id="closeTermsModal" class="text-gray-500 hover:text-gray-700 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="terms-content max-h-[60vh] overflow-y-auto pr-2">
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">1. Acceptance of Terms</h3>
                            <p class="text-gray-600">By accessing and using GoServePH, you accept and agree to be bound by the terms and provision of this agreement.</p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">2. Description of Service</h3>
                            <p class="text-gray-600">GoServePH provides a platform for citizens to access various government services including but not limited to:</p>
                            <ul class="list-disc pl-5 text-gray-600 mt-2 space-y-1">
                                <li>Business permit applications</li>
                                <li>Real property tax payments</li>
                                <li>Social service requests</li>
                                <li>Document processing</li>
                                <li>Appointment scheduling</li>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">3. User Responsibilities</h3>
                            <p class="text-gray-600">As a user of GoServePH, you agree to:</p>
                            <ul class="list-disc pl-5 text-gray-600 mt-2 space-y-1">
                                <li>Provide accurate and complete information</li>
                                <li>Maintain the confidentiality of your account</li>
                                <li>Report any unauthorized access immediately</li>
                                <li>Use the service only for lawful purposes</li>
                                <li>Not attempt to circumvent security measures</li>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">4. Account Security</h3>
                            <p class="text-gray-600">You are responsible for maintaining the security of your account. You must:</p>
                            <ul class="list-disc pl-5 text-gray-600 mt-2 space-y-1">
                                <li>Use a strong password</li>
                                <li>Not share your credentials</li>
                                <li>Log out after each session</li>
                                <li>Notify us of any security breach</li>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">5. Data Privacy</h3>
                            <p class="text-gray-600">We collect and process your personal data in accordance with the Data Privacy Act of 2012. All information is handled with strict confidentiality and used only for the purposes of providing government services.</p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">6. Service Availability</h3>
                            <p class="text-gray-600">We strive to maintain 24/7 service availability but reserve the right to suspend access for maintenance, upgrades, or security reasons without prior notice.</p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">7. Limitation of Liability</h3>
                            <p class="text-gray-600">The Government Services Management System shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of or inability to use the service.</p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">8. Changes to Terms</h3>
                            <p class="text-gray-600">We reserve the right to modify these terms at any time. Continued use of the service after changes constitutes acceptance of the new terms.</p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">9. Governing Law</h3>
                            <p class="text-gray-600">These terms shall be governed by and construed in accordance with the laws of the Republic of the Philippines.</p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">10. Contact Information</h3>
                            <p class="text-gray-600">For questions regarding these Terms of Service, please contact:</p>
                            <p class="text-gray-800 mt-1">
                                Government Services Management System<br>
                                Email: helpdesk@gov.ph<br>
                                Hotline: 122
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button type="button" id="agreeTermsModal" class="bg-custom-secondary text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        I Agree to Terms
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="modal-container hidden">
        <div class="modal-content terms-modal">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-custom-secondary">Privacy Policy</h2>
                    <button type="button" id="closePrivacyModal" class="text-gray-500 hover:text-gray-700 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="terms-content max-h-[60vh] overflow-y-auto pr-2">
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">1. Data Collection</h3>
                            <p class="text-gray-600">We collect the following information:</p>
                            <ul class="list-disc pl-5 text-gray-600 mt-2 space-y-1">
                                <li>Personal identification information</li>
                                <li>Contact details</li>
                                <li>Address information</li>
                                <li>Service usage data</li>
                                <li>Transaction records</li>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">2. Purpose of Data Collection</h3>
                            <p class="text-gray-600">Your data is collected for:</p>
                            <ul class="list-disc pl-5 text-gray-600 mt-2 space-y-1">
                                <li>Service provision and processing</li>
                                <li>Account management</li>
                                <li>Communication regarding services</li>
                                <li>Improvement of government services</li>
                                <li>Legal compliance and reporting</li>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">3. Data Protection</h3>
                            <p class="text-gray-600">We implement appropriate security measures including:</p>
                            <ul class="list-disc pl-5 text-gray-600 mt-2 space-y-1">
                                <li>Encryption of sensitive data</li>
                                <li>Regular security audits</li>
                                <li>Access control mechanisms</li>
                                <li>Secure data storage</li>
                                <li>Employee confidentiality agreements</li>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">4. Data Sharing</h3>
                            <p class="text-gray-600">We may share your data with:</p>
                            <ul class="list-disc pl-5 text-gray-600 mt-2 space-y-1">
                                <li>Other government agencies for service processing</li>
                                <li>Law enforcement when required by law</li>
                                <li>Service providers under strict confidentiality</li>
                            </ul>
                            <p class="text-gray-600 mt-2">We do not sell your personal information to third parties.</p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">5. Your Rights</h3>
                            <p class="text-gray-600">Under the Data Privacy Act, you have the right to:</p>
                            <ul class="list-disc pl-5 text-gray-600 mt-2 space-y-1">
                                <li>Access your personal data</li>
                                <li>Correct inaccurate information</li>
                                <li>Request data deletion</li>
                                <li>Object to data processing</li>
                                <li>Data portability</li>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">6. Cookies and Tracking</h3>
                            <p class="text-gray-600">We use cookies to enhance user experience. You can control cookie settings through your browser preferences.</p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">7. Data Retention</h3>
                            <p class="text-gray-600">We retain your data only for as long as necessary to fulfill the purposes outlined in this policy, unless a longer retention period is required by law.</p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">8. Children's Privacy</h3>
                            <p class="text-gray-600">We do not knowingly collect data from children under 18 without parental consent.</p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">9. Policy Updates</h3>
                            <p class="text-gray-600">We may update this policy periodically. Changes will be posted on this page with an updated effective date.</p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">10. Contact Us</h3>
                            <p class="text-gray-600">For privacy concerns, contact our Data Protection Officer:</p>
                            <p class="text-gray-800 mt-1">
                                Data Protection Office<br>
                                Government Services Management System<br>
                                Email: dpo@gov.ph<br>
                                Hotline: 122
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button type="button" id="agreePrivacyModal" class="bg-custom-secondary text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        I Agree to Privacy Policy
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // CONFIGURATION
        // ============================================
        const basePath = window.location.pathname.includes('/revenue2/') ? '/revenue2' : '';
        const API_ENDPOINT = 'Login/api/auth.php';
        
        let currentUserId = null;
        let otpTimer = null;
        let otpTimeLeft = 180;
        
        // ============================================
        // LOGIN ATTEMPT TRACKING - FIXED VERSION
        // ============================================
        const MAX_LOGIN_ATTEMPTS = 3;
        const LOCKOUT_DURATION = 15 * 60; // 15 minutes in seconds
        
        // Track attempts separately for login and OTP
        let loginAttempts = 0;
        let otpAttempts = 0;
        let isLockedOut = false;
        let lockoutTimer = null;
        let lockoutTimeLeft = LOCKOUT_DURATION;
        
        // Initialize application
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM loaded, initializing app...');
            
            updateDateTime();
            setInterval(updateDateTime, 1000);
            setupEventListeners();
            setupOTPInputs();
            setupPasswordValidation();
            fixBackgroundImage();
            
            // Load saved attempts from localStorage
            loadSavedAttempts();
            
            // TEST: Uncomment to test the lockout feature
            // testLockoutFeature();
        });

        // ============================================
        // ATTEMPT MANAGEMENT - FIXED
        // ============================================
        function loadSavedAttempts() {
            try {
                // Load login attempts
                const savedLoginAttempts = localStorage.getItem('loginAttempts');
                if (savedLoginAttempts) {
                    loginAttempts = parseInt(savedLoginAttempts);
                    console.log(`📊 Loaded ${loginAttempts} saved login attempt(s)`);
                }
                
                // Load OTP attempts
                const savedOtpAttempts = localStorage.getItem('otpAttempts');
                if (savedOtpAttempts) {
                    otpAttempts = parseInt(savedOtpAttempts);
                    console.log(`📊 Loaded ${otpAttempts} saved OTP attempt(s)`);
                }
                
                // Check lockout status
                const lockoutUntil = localStorage.getItem('lockoutUntil');
                if (lockoutUntil) {
                    const lockoutTime = parseInt(lockoutUntil);
                    const now = Date.now();
                    
                    if (now < lockoutTime) {
                        // Still locked out
                        const remainingSeconds = Math.ceil((lockoutTime - now) / 1000);
                        console.log(`🔒 Account is locked. ${remainingSeconds}s remaining`);
                        
                        loginAttempts = MAX_LOGIN_ATTEMPTS;
                        isLockedOut = true;
                        lockoutTimeLeft = remainingSeconds;
                        
                        disableLoginForm(true);
                        showLockoutMessage();
                        startLockoutTimer(remainingSeconds);
                    } else {
                        // Lockout expired
                        localStorage.removeItem('lockoutUntil');
                        resetAllAttempts();
                    }
                }
                
                // Update displays
                updateAttemptsDisplay();
                updateOtpAttemptsDisplay();
                
            } catch (e) {
                console.error('Error loading attempts:', e);
            }
        }

        function incrementLoginAttempts() {
            loginAttempts++;
            console.log(`⚠️ Failed login attempt #${loginAttempts} of ${MAX_LOGIN_ATTEMPTS}`);
            
            // Save to localStorage
            localStorage.setItem('loginAttempts', loginAttempts.toString());
            
            // Update display
            updateAttemptsDisplay();
            
            // Show warning
            const remaining = MAX_LOGIN_ATTEMPTS - loginAttempts;
            
            if (remaining === 1) {
                showNotification(`⚠️ WARNING: Last attempt before account lockout!`, 'warning');
            } else if (remaining === 2) {
                showNotification(`⚠️ ${remaining} attempts remaining before account lockout`, 'warning');
            }
            
            // Check if we've reached max attempts
            if (loginAttempts >= MAX_LOGIN_ATTEMPTS) {
                console.log('🔒 Max failed attempts reached! Locking account...');
                triggerAccountLockout();
            }
            
            return loginAttempts;
        }

        function incrementOtpAttempts() {
            otpAttempts++;
            console.log(`⚠️ Failed OTP attempt #${otpAttempts} of ${MAX_LOGIN_ATTEMPTS}`);
            
            // Save to localStorage
            localStorage.setItem('otpAttempts', otpAttempts.toString());
            
            // Update display
            updateOtpAttemptsDisplay();
            
            // Show warning
            const remaining = MAX_LOGIN_ATTEMPTS - otpAttempts;
            
            if (remaining === 1) {
                showNotification(`⚠️ WARNING: Last OTP attempt before session lock!`, 'warning');
            } else if (remaining === 2) {
                showNotification(`⚠️ ${remaining} OTP attempts remaining`, 'warning');
            }
            
            // Check if we've reached max attempts
            if (otpAttempts >= MAX_LOGIN_ATTEMPTS) {
                console.log('🔒 Max OTP attempts reached! Closing session...');
                handleMaxOtpAttempts();
            }
            
            return otpAttempts;
        }

        function handleMaxOtpAttempts() {
            showNotification('Too many invalid OTP attempts. Please login again.', 'error');
            
            // Close OTP modal
            closeOtpModalFunc();
            
            // Clear OTP attempts
            otpAttempts = 0;
            localStorage.removeItem('otpAttempts');
            
            // Clear current user ID
            currentUserId = null;
        }

        function resetAllAttempts() {
            console.log('🔄 Resetting all attempts');
            loginAttempts = 0;
            otpAttempts = 0;
            isLockedOut = false;
            
            localStorage.removeItem('loginAttempts');
            localStorage.removeItem('otpAttempts');
            localStorage.removeItem('lockoutUntil');
            
            updateAttemptsDisplay();
            updateOtpAttemptsDisplay();
            disableLoginForm(false);
            hideLockoutMessage();
            hideLockoutModal();
            
            if (lockoutTimer) {
                clearInterval(lockoutTimer);
                lockoutTimer = null;
            }
        }

        function triggerAccountLockout() {
            console.log('🔒 Triggering account lockout for 15 minutes');
            
            isLockedOut = true;
            lockoutTimeLeft = LOCKOUT_DURATION;
            
            const lockoutUntil = Date.now() + (LOCKOUT_DURATION * 1000);
            localStorage.setItem('lockoutUntil', lockoutUntil.toString());
            localStorage.setItem('loginAttempts', MAX_LOGIN_ATTEMPTS.toString());
            
            disableLoginForm(true);
            showLockoutMessage();
            showLockoutModal();
            startLockoutTimer(LOCKOUT_DURATION);
            
            const passwordInput = document.getElementById('password');
            if (passwordInput) passwordInput.value = '';
        }

        // ============================================
        // DISPLAY FUNCTIONS - FIXED
        // ============================================
        function updateAttemptsDisplay() {
            const attemptsBadge = document.getElementById('attemptsBadge');
            const attemptCount = document.getElementById('attemptCount');
            const attemptsLeft = document.getElementById('attemptsLeft');
            const attemptsLeftText = document.getElementById('attemptsLeftText');
            const attemptsRemainingBadge = document.getElementById('attemptsRemainingBadge');
            
            if (!attemptsBadge || !attemptCount) return;
            
            // ALWAYS show if there are attempts OR if we're testing
            if (loginAttempts > 0 && !isLockedOut) {
                attemptsBadge.classList.remove('hidden');
                
                // Update count
                attemptCount.textContent = `${loginAttempts}/${MAX_LOGIN_ATTEMPTS}`;
                
                // Update remaining attempts badge
                const remaining = MAX_LOGIN_ATTEMPTS - loginAttempts;
                if (attemptsRemainingBadge) {
                    attemptsRemainingBadge.textContent = `${remaining} attempt${remaining !== 1 ? 's' : ''} left`;
                }
                
                // Change color based on remaining attempts
                if (remaining <= 1) {
                    attemptsBadge.className = 'inline-flex items-center px-4 py-2 rounded-lg bg-white shadow-md border-l-4 border-red-500';
                    attemptCount.className = 'attempts-counter attempts-danger';
                } else {
                    attemptsBadge.className = 'inline-flex items-center px-4 py-2 rounded-lg bg-white shadow-md border-l-4 border-yellow-500';
                    attemptCount.className = 'attempts-counter attempts-warning';
                }
                
                // Show attempts left text
                if (attemptsLeft && attemptsLeftText) {
                    attemptsLeft.classList.remove('hidden');
                    attemptsLeftText.textContent = `${remaining} attempt${remaining !== 1 ? 's' : ''} remaining`;
                    attemptsLeftText.className = remaining <= 1 ? 'text-red-600 font-semibold' : 'text-orange-600';
                }
            } else {
                attemptsBadge.classList.add('hidden');
                if (attemptsLeft) attemptsLeft.classList.add('hidden');
            }
        }

        function updateOtpAttemptsDisplay() {
            const otpAttemptsElement = document.getElementById('otpAttempts');
            const otpAttemptCount = document.getElementById('otpAttemptCount');
            
            if (!otpAttemptsElement || !otpAttemptCount) return;
            
            if (otpAttempts > 0) {
                otpAttemptsElement.classList.remove('hidden');
                otpAttemptCount.textContent = otpAttempts;
                
                // Change color based on attempts
                const remaining = MAX_LOGIN_ATTEMPTS - otpAttempts;
                if (remaining <= 1) {
                    otpAttemptsElement.className = 'text-xs text-center text-red-600 font-semibold';
                } else {
                    otpAttemptsElement.className = 'text-xs text-center text-orange-600';
                }
            } else {
                otpAttemptsElement.classList.add('hidden');
            }
        }

        function disableLoginForm(disabled) {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const loginBtn = document.getElementById('loginBtn');
            const showRegisterBtn = document.getElementById('showRegister');
            
            if (disabled) {
                console.log('🔒 Disabling login form');
                
                if (emailInput) {
                    emailInput.disabled = true;
                    emailInput.classList.add('disabled-input');
                    emailInput.placeholder = 'Login disabled - account locked';
                }
                
                if (passwordInput) {
                    passwordInput.disabled = true;
                    passwordInput.classList.add('disabled-input');
                    passwordInput.placeholder = 'Login disabled - account locked';
                }
                
                if (loginBtn) {
                    loginBtn.disabled = true;
                    loginBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    loginBtn.innerHTML = '<i class="fas fa-lock mr-2"></i> Account Locked (15:00)';
                }
                
                if (showRegisterBtn) {
                    showRegisterBtn.disabled = true;
                    showRegisterBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            } else {
                console.log('🔓 Enabling login form');
                
                if (emailInput) {
                    emailInput.disabled = false;
                    emailInput.classList.remove('disabled-input');
                    emailInput.placeholder = 'Enter e-mail address';
                }
                
                if (passwordInput) {
                    passwordInput.disabled = false;
                    passwordInput.classList.remove('disabled-input');
                    passwordInput.placeholder = 'Enter password';
                }
                
                if (loginBtn) {
                    loginBtn.disabled = false;
                    loginBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    loginBtn.innerHTML = 'Login';
                }
                
                if (showRegisterBtn) {
                    showRegisterBtn.disabled = false;
                    showRegisterBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }
        }

        function showLockoutMessage() {
            const lockoutMessage = document.getElementById('lockoutMessage');
            const lockoutCountdown = document.getElementById('lockoutCountdown');
            
            if (lockoutMessage) {
                lockoutMessage.classList.remove('hidden');
            }
            if (lockoutCountdown) {
                lockoutCountdown.classList.remove('hidden');
            }
        }

        function hideLockoutMessage() {
            const lockoutMessage = document.getElementById('lockoutMessage');
            if (lockoutMessage) {
                lockoutMessage.classList.add('hidden');
            }
        }

        function showLockoutModal() {
            const modal = document.getElementById('lockoutModal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('modal-open');
            }
        }

        function hideLockoutModal() {
            const modal = document.getElementById('lockoutModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('modal-open');
            }
        }

        function startLockoutTimer(duration) {
            lockoutTimeLeft = duration;
            
            const timerElement = document.getElementById('lockoutTimer');
            const lockoutTimerDisplay = document.getElementById('lockoutTimerDisplay');
            const lockoutText = document.getElementById('lockoutText');
            const loginBtn = document.getElementById('loginBtn');
            
            // Update displays
            if (timerElement) timerElement.textContent = formatTime(lockoutTimeLeft);
            if (lockoutTimerDisplay) lockoutTimerDisplay.textContent = formatTime(lockoutTimeLeft);
            if (lockoutText) lockoutText.textContent = `Account locked. Please try again in ${formatTime(lockoutTimeLeft)}.`;
            if (loginBtn) loginBtn.innerHTML = `<i class="fas fa-lock mr-2"></i> Account Locked (${formatTime(lockoutTimeLeft)})`;
            
            if (lockoutTimer) clearInterval(lockoutTimer);
            
            lockoutTimer = setInterval(() => {
                lockoutTimeLeft--;
                
                // Update displays
                if (timerElement) timerElement.textContent = formatTime(lockoutTimeLeft);
                if (lockoutTimerDisplay) lockoutTimerDisplay.textContent = formatTime(lockoutTimeLeft);
                if (lockoutText) lockoutText.textContent = `Account locked. Please try again in ${formatTime(lockoutTimeLeft)}.`;
                if (loginBtn) loginBtn.innerHTML = `<i class="fas fa-lock mr-2"></i> Account Locked (${formatTime(lockoutTimeLeft)})`;
                
                if (lockoutTimeLeft <= 0) {
                    clearInterval(lockoutTimer);
                    console.log('🔓 Lockout expired, resetting account');
                    resetAllAttempts();
                    hideLockoutModal();
                    showNotification('Account unlocked. You may now try logging in again.', 'success');
                }
            }, 1000);
        }

        function formatTime(seconds) {
            if (seconds < 0) seconds = 0;
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
        }

        // ============================================
        // TEST FUNCTION
        // ============================================
        function testLockoutFeature() {
            console.log('🧪 TEST MODE: Setting login attempts to 2/3');
            loginAttempts = 2;
            localStorage.setItem('loginAttempts', '2');
            updateAttemptsDisplay();
            showNotification('TEST MODE: 2 failed attempts. One more to lock account.', 'warning');
        }

        // ============================================
        // BACKGROUND IMAGE
        // ============================================
        function fixBackgroundImage() {
            console.log('🖼️ Fixing background image...');
            const bgElement = document.querySelector('.bg-custom-bg');
            if (!bgElement) return;
            
            const imagePath = 'Login/images/bg.jpg';
            bgElement.style.backgroundImage = `url('${imagePath}')`;
            bgElement.style.backgroundSize = 'cover';
            bgElement.style.backgroundPosition = 'center';
            bgElement.style.backgroundRepeat = 'no-repeat';
            bgElement.style.backgroundAttachment = 'fixed';
            
            const testImage = new Image();
            testImage.onload = () => console.log('✅ Background image loaded successfully:', imagePath);
            testImage.onerror = () => console.warn('⚠️ Background image not found at:', imagePath);
            testImage.src = imagePath;
        }

        // ============================================
        // EVENT LISTENERS
        // ============================================
        function setupEventListeners() {
            // Login form
            const loginForm = document.getElementById('loginForm');
            if (loginForm) loginForm.addEventListener('submit', handleLoginSubmit);
            
            // Register form
            const registerForm = document.getElementById('registerForm');
            if (registerForm) registerForm.addEventListener('submit', handleRegisterSubmit);
            
            // Show register form
            const showRegister = document.getElementById('showRegister');
            if (showRegister) showRegister.addEventListener('click', showRegisterForm);
            
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
            
            if (cancelOtp) cancelOtp.addEventListener('click', closeOtpModalFunc);
            if (resendOtp) resendOtp.addEventListener('click', handleResendOtp);
            if (submitOtp) submitOtp.addEventListener('click', handleVerifyOtp);
            if (closeOtpModal) closeOtpModal.addEventListener('click', closeOtpModalFunc);
            
            // OTP form submit
            const otpForm = document.getElementById('otpForm');
            if (otpForm) {
                otpForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    handleVerifyOtp();
                });
            }
            
            // Lockout modal close button
            const closeLockoutModal = document.getElementById('closeLockoutModal');
            if (closeLockoutModal) {
                closeLockoutModal.addEventListener('click', hideLockoutModal);
            }
            
            // Terms and Privacy modals
            const closeTermsModal = document.getElementById('closeTermsModal');
            const closePrivacyModal = document.getElementById('closePrivacyModal');
            const agreeTermsModal = document.getElementById('agreeTermsModal');
            const agreePrivacyModal = document.getElementById('agreePrivacyModal');
            
            if (closeTermsModal) closeTermsModal.addEventListener('click', hideTermsModal);
            if (closePrivacyModal) closePrivacyModal.addEventListener('click', hidePrivacyModal);
            if (agreeTermsModal) agreeTermsModal.addEventListener('click', agreeToTerms);
            if (agreePrivacyModal) agreePrivacyModal.addEventListener('click', agreeToPrivacy);
            
            // Terms and Privacy buttons in register form
            document.querySelectorAll('.show-terms-modal').forEach(btn => {
                btn.addEventListener('click', showTermsModal);
            });
            
            document.querySelectorAll('.show-privacy-modal').forEach(btn => {
                btn.addEventListener('click', showPrivacyModal);
            });
            
            // Footer buttons
            const footerTerms = document.getElementById('footerTerms');
            const footerPrivacy = document.getElementById('footerPrivacy');
            
            if (footerTerms) footerTerms.addEventListener('click', showTermsModal);
            if (footerPrivacy) footerPrivacy.addEventListener('click', showPrivacyModal);
            
            // Modal background clicks
            const registerModal = document.getElementById('registerFormContainer');
            const otpModalElement = document.getElementById('otpModal');
            const termsModalElement = document.getElementById('termsModal');
            const privacyModalElement = document.getElementById('privacyModal');
            const lockoutModalElement = document.getElementById('lockoutModal');
            
            if (registerModal) {
                registerModal.addEventListener('click', (e) => {
                    if (e.target === this) hideRegisterForm();
                });
            }
            
            if (otpModalElement) {
                otpModalElement.addEventListener('click', (e) => {
                    if (e.target === this) closeOtpModalFunc();
                });
            }
            
            if (termsModalElement) {
                termsModalElement.addEventListener('click', (e) => {
                    if (e.target === this) hideTermsModal();
                });
            }
            
            if (privacyModalElement) {
                privacyModalElement.addEventListener('click', (e) => {
                    if (e.target === this) hidePrivacyModal();
                });
            }
            
            if (lockoutModalElement) {
                lockoutModalElement.addEventListener('click', (e) => {
                    if (e.target === this) hideLockoutModal();
                });
            }
        }

        // ============================================
        // PASSWORD VALIDATION
        // ============================================
        function setupPasswordValidation() {
            const passwordInput = document.getElementById('regPassword');
            const confirmInput = document.getElementById('confirmPassword');
            const registerSubmitBtn = document.getElementById('registerSubmitBtn');
            const requirementsDiv = document.getElementById('passwordRequirements');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                    validateRegisterForm();
                });
                
                passwordInput.addEventListener('focus', () => {
                    if (requirementsDiv) requirementsDiv.style.display = 'block';
                });
                
                passwordInput.addEventListener('blur', function() {
                    setTimeout(() => {
                        if (!this.matches(':focus') && requirementsDiv) {
                            requirementsDiv.style.display = 'none';
                        }
                    }, 200);
                });
            }
            
            if (confirmInput) {
                confirmInput.addEventListener('input', function() {
                    checkPasswordMatch();
                    validateRegisterForm();
                });
            }
        }

        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrength');
            if (!strengthBar) return;
            
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /\d/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };
            
            const reqLength = document.getElementById('reqLength');
            const reqUppercase = document.getElementById('reqUppercase');
            const reqLowercase = document.getElementById('reqLowercase');
            const reqNumber = document.getElementById('reqNumber');
            const reqSpecial = document.getElementById('reqSpecial');
            
            if (reqLength) reqLength.className = requirements.length ? 'requirement met' : 'requirement unmet';
            if (reqUppercase) reqUppercase.className = requirements.uppercase ? 'requirement met' : 'requirement unmet';
            if (reqLowercase) reqLowercase.className = requirements.lowercase ? 'requirement met' : 'requirement unmet';
            if (reqNumber) reqNumber.className = requirements.number ? 'requirement met' : 'requirement unmet';
            if (reqSpecial) reqSpecial.className = requirements.special ? 'requirement met' : 'requirement unmet';
            
            let score = Object.values(requirements).filter(Boolean).length;
            
            let strengthClass = '';
            if (password.length === 0) {
                strengthClass = '';
            } else if (password.length < 6) {
                strengthClass = 'strength-weak';
            } else if (score <= 2) {
                strengthClass = 'strength-fair';
            } else if (score <= 4) {
                strengthClass = 'strength-good';
            } else {
                strengthClass = 'strength-strong';
            }
            
            strengthBar.className = `password-strength ${strengthClass}`;
        }

        function checkPasswordMatch() {
            const password = document.getElementById('regPassword')?.value || '';
            const confirmPassword = document.getElementById('confirmPassword')?.value || '';
            const matchElement = document.getElementById('passwordMatch');
            const mismatchElement = document.getElementById('passwordMismatch');
            
            if (confirmPassword.length === 0) {
                if (matchElement) matchElement.classList.add('hidden');
                if (mismatchElement) mismatchElement.classList.add('hidden');
                return false;
            }
            
            if (password === confirmPassword) {
                if (matchElement) matchElement.classList.remove('hidden');
                if (mismatchElement) mismatchElement.classList.add('hidden');
                return true;
            } else {
                if (matchElement) matchElement.classList.add('hidden');
                if (mismatchElement) mismatchElement.classList.remove('hidden');
                return false;
            }
        }

        function validateRegisterForm() {
            const registerSubmitBtn = document.getElementById('registerSubmitBtn');
            const password = document.getElementById('regPassword')?.value || '';
            
            if (!registerSubmitBtn) return;
            
            const hasMinLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            const passwordsMatch = checkPasswordMatch();
            
            const isStrongPassword = hasMinLength && hasUppercase && hasLowercase && hasNumber && hasSpecial;
            
            registerSubmitBtn.disabled = !(isStrongPassword && passwordsMatch);
        }

        // ============================================
        // OTP SETUP
        // ============================================
        function setupOTPInputs() {
            const inputs = document.querySelectorAll('.otp-input');
            
            inputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    const value = e.target.value.replace(/[^0-9]/g, '');
                    
                    if (value) {
                        e.target.value = value.charAt(0);
                        e.target.classList.add('filled');
                        
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                            inputs[index + 1].select();
                        }
                    } else {
                        e.target.classList.remove('filled');
                    }
                    
                    const allFilled = Array.from(inputs).every(input => input.value.length === 1);
                    if (allFilled) {
                        setTimeout(() => handleVerifyOtp(), 100);
                    }
                });
                
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace') {
                        if (!e.target.value && index > 0) {
                            e.preventDefault();
                            inputs[index - 1].focus();
                            inputs[index - 1].select();
                            inputs[index - 1].classList.remove('filled');
                        }
                    }
                    
                    if (e.key === 'ArrowLeft' && index > 0) {
                        e.preventDefault();
                        inputs[index - 1].focus();
                        inputs[index - 1].select();
                    }
                    
                    if (e.key === 'ArrowRight' && index < inputs.length - 1) {
                        e.preventDefault();
                        inputs[index + 1].focus();
                        inputs[index + 1].select();
                    }
                    
                    if (e.key === 'Delete') {
                        e.target.value = '';
                        e.target.classList.remove('filled');
                    }
                });
                
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pasteData = e.clipboardData.getData('text').replace(/[^0-9]/g, '');
                    const digits = pasteData.split('').slice(0, 6);
                    
                    digits.forEach((digit, i) => {
                        if (inputs[i]) {
                            inputs[i].value = digit;
                            inputs[i].classList.add('filled');
                        }
                    });
                    
                    const nextIndex = digits.length < 6 ? digits.length : 5;
                    if (inputs[nextIndex]) {
                        inputs[nextIndex].focus();
                        inputs[nextIndex].select();
                    }
                    
                    if (digits.length === 6) {
                        setTimeout(() => handleVerifyOtp(), 100);
                    }
                });
                
                input.addEventListener('focus', function() { this.select(); });
                input.addEventListener('click', function() { this.select(); });
            });
        }

        // ============================================
        // LOGIN HANDLER - FIXED
        // ============================================
        async function handleLoginSubmit(e) {
            e.preventDefault();
            console.log('🔐 Login form submitted');
            
            if (isLockedOut) {
                showNotification('Account is temporarily locked. Please try again later.', 'error');
                return;
            }
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            const loginBtn = document.getElementById('loginBtn');
            
            if (!email || !password) {
                showNotification('Please enter both email and password', 'error');
                return;
            }
            
            if (!isValidEmail(email)) {
                showNotification('Please enter a valid email address', 'error');
                return;
            }
            
            setButtonLoading(loginBtn, true, 'Logging in...');
            
            try {
                const response = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'login',
                        email: email,
                        password: password
                    })
                });
                
                const responseText = await response.text();
                console.log('📥 Login response:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('❌ JSON Parse Error:', parseError);
                    showNotification('Server returned invalid response', 'error');
                    return;
                }
                
                if (data.success) {
                    console.log('✅ Login successful');
                    
                    // Reset attempts on successful login
                    resetAllAttempts();
                    
                    if (data.user_role === 'admin') {
                        showNotification('Admin login successful! Redirecting...', 'success');
                        setTimeout(() => {
                            window.location.href = (basePath ? basePath + '/' : '') + 'dist/index.html';
                        }, 1000);
                    } else {
                        currentUserId = data.user_id;
                        
                        // Reset OTP attempts for new session
                        otpAttempts = 0;
                        localStorage.removeItem('otpAttempts');
                        
                        if (data.debug_otp) {
                            console.log('🔑 DEBUG OTP:', data.debug_otp);
                            showNotification('Login successful! OTP: ' + data.debug_otp, 'success');
                        } else {
                            showNotification('Login successful! OTP sent to your email.', 'success');
                        }
                        
                        // Clear password field
                        document.getElementById('password').value = '';
                        
                        openOtpModal();
                    }
                } else {
                    console.log('❌ Login failed:', data.message);
                    
                    // INCREMENT ATTEMPTS ON FAILED LOGIN
                    incrementLoginAttempts();
                    
                    // Show error with remaining attempts
                    if (!isLockedOut) {
                        const remaining = MAX_LOGIN_ATTEMPTS - loginAttempts;
                        let errorMessage = data.message || 'Invalid email or password';
                        
                        if (remaining > 0) {
                            errorMessage += ` (${remaining} attempt${remaining !== 1 ? 's' : ''} remaining)`;
                        }
                        
                        showNotification(errorMessage, 'error');
                    }
                }
            } catch (error) {
                console.error('🚨 Login error:', error);
                showNotification('Network error. Please check if server is running.', 'error');
            } finally {
                setButtonLoading(loginBtn, false, 'Login');
            }
        }

        // ============================================
        // REGISTER HANDLER
        // ============================================
        async function handleRegisterSubmit(e) {
            e.preventDefault();
            console.log('📝 Register form submitted');
            
            const formData = new FormData(document.getElementById('registerForm'));
            const data = Object.fromEntries(formData.entries());
            const registerBtn = document.querySelector('#registerForm button[type="submit"]');
            
            // Validation
            const requiredFields = [
                'firstName', 'lastName', 'regEmail', 'regPassword', 
                'confirmPassword', 'birthdate', 'mobile', 'houseNumber', 
                'street', 'barangay', 'district', 'city', 'province', 'zipCode'
            ];
            
            for (const field of requiredFields) {
                if (!data[field] || data[field].trim() === '') {
                    showNotification('Please fill in all required fields', 'error');
                    return;
                }
            }
            
            if (!/^\d{4}$/.test(data.zipCode)) {
                showNotification('Please enter a valid 4-digit ZIP code', 'error');
                return;
            }
            
            if (!/^\d{11}$/.test(data.mobile.replace(/\D/g, ''))) {
                showNotification('Please enter a valid 11-digit mobile number', 'error');
                return;
            }
            
            if (!['1', '2', '3'].includes(data.district)) {
                showNotification('Please select a valid district (1, 2, or 3)', 'error');
                return;
            }
            
            if (data.city !== 'Caloocan City') {
                showNotification('City must be Caloocan City', 'error');
                return;
            }
            
            if (data.province !== 'Metro Manila') {
                showNotification('Province must be Metro Manila', 'error');
                return;
            }
            
            if (data.regPassword !== data.confirmPassword) {
                showNotification('Passwords do not match', 'error');
                return;
            }
            
            if (!isValidEmail(data.regEmail)) {
                showNotification('Please enter a valid email address', 'error');
                return;
            }
            
            if (!data.agreeTerms || !data.agreePrivacy) {
                showNotification('Please agree to the Terms of Service and Privacy Policy', 'error');
                return;
            }
            
            // Check password strength
            const hasMinLength = data.regPassword.length >= 8;
            const hasUppercase = /[A-Z]/.test(data.regPassword);
            const hasLowercase = /[a-z]/.test(data.regPassword);
            const hasNumber = /\d/.test(data.regPassword);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(data.regPassword);
            
            if (!hasMinLength || !hasUppercase || !hasLowercase || !hasNumber || !hasSpecial) {
                showNotification('Password must be strong. It needs at least 8 characters with uppercase, lowercase, number, and special character.', 'error');
                return;
            }
            
            setButtonLoading(registerBtn, true, 'Creating Account...');
            
            try {
                const response = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'register',
                        ...data
                    })
                });
                
                const responseText = await response.text();
                console.log('📥 Registration response:', responseText);
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('❌ JSON Parse Error:', parseError);
                    showNotification('Server returned invalid response', 'error');
                    return;
                }
                
                if (result.success) {
                    currentUserId = result.user_id;
                    
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

        // ============================================
        // OTP HANDLER - FIXED
        // ============================================
        async function handleVerifyOtp() {
            console.log('🔑 Verifying OTP...');
            
            const otpCode = getOtpCode();
            const submitBtn = document.getElementById('submitOtp');
            const errorElement = document.getElementById('otpError');
            
            if (!otpCode || otpCode.length !== 6) {
                showOtpError('Please enter the complete 6-digit OTP');
                return;
            }
            
            setButtonLoading(submitBtn, true, 'Verifying...');
            hideOtpError();
            
            try {
                const response = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'verify_otp',
                        user_id: currentUserId,
                        otp_code: otpCode
                    })
                });
                
                const responseText = await response.text();
                console.log('📥 OTP verification response:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('❌ JSON Parse Error:', parseError);
                    showOtpError('Server returned invalid response');
                    return;
                }
                
                if (data.success) {
                    console.log('✅ OTP verified successfully');
                    
                    // Reset OTP attempts on success
                    otpAttempts = 0;
                    localStorage.removeItem('otpAttempts');
                    
                    showNotification('OTP verified successfully!', 'success');
                    closeOtpModalFunc();
                    
                    setTimeout(() => {
                        if (data.redirect_url) {
                            window.location.href = (basePath ? basePath + '/' : '') + data.redirect_url;
                        } else {
                            window.location.href = (basePath ? basePath + '/' : '') + 'citizen_dashboard/citizen_dashboard.php';
                        }
                    }, 1500);
                } else {
                    console.log('❌ OTP verification failed:', data.message);
                    
                    // INCREMENT OTP ATTEMPTS ON FAILED VERIFICATION
                    incrementOtpAttempts();
                    
                    // Show error with remaining attempts
                    const remaining = MAX_LOGIN_ATTEMPTS - otpAttempts;
                    let errorMessage = data.message || 'Invalid OTP';
                    
                    if (remaining > 0 && otpAttempts < MAX_LOGIN_ATTEMPTS) {
                        errorMessage += ` (${remaining} attempt${remaining !== 1 ? 's' : ''} remaining)`;
                        showOtpError(errorMessage);
                    }
                }
            } catch (error) {
                console.error('OTP verification error:', error);
                showOtpError('Network error. Please try again.');
            } finally {
                setButtonLoading(submitBtn, false, 'Verify');
            }
        }

        async function handleResendOtp() {
            console.log('🔄 Resending OTP...');
            const resendBtn = document.getElementById('resendOtp');
            
            if (resendBtn.disabled) return;
            
            setButtonLoading(resendBtn, true, 'Sending...');
            
            try {
                const response = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'resend_otp',
                        user_id: currentUserId
                    })
                });
                
                const responseText = await response.text();
                console.log('📥 Resend OTP response:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('❌ JSON Parse Error:', parseError);
                    showNotification('Server returned invalid response', 'error');
                    return;
                }
                
                if (data.success) {
                    if (data.debug_otp) {
                        console.log('🔑 DEBUG OTP:', data.debug_otp);
                        showNotification('New OTP: ' + data.debug_otp, 'success');
                    } else {
                        showNotification('New OTP sent to your email', 'success');
                    }
                    
                    // Reset OTP attempts on resend
                    otpAttempts = 0;
                    localStorage.removeItem('otpAttempts');
                    updateOtpAttemptsDisplay();
                    
                    startOtpTimer();
                } else {
                    showNotification(data.message || 'Failed to resend OTP', 'error');
                }
            } catch (error) {
                console.error('Resend OTP error:', error);
                showNotification('Network error. Please try again.', 'error');
            } finally {
                setButtonLoading(resendBtn, false, 'Resend OTP');
            }
        }

        // ============================================
        // MODAL FUNCTIONS
        // ============================================
        function showRegisterForm() {
            const container = document.getElementById('registerFormContainer');
            if (container) {
                container.classList.remove('hidden');
                document.body.classList.add('modal-open');
                checkPasswordStrength('');
                checkPasswordMatch();
                validateRegisterForm();
            }
        }
        
        function hideRegisterForm() {
            const container = document.getElementById('registerFormContainer');
            if (container) {
                container.classList.add('hidden');
                document.body.classList.remove('modal-open');
                const form = container.querySelector('form');
                if (form) form.reset();
            }
        }
        
        function showTermsModal() {
            const modal = document.getElementById('termsModal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('modal-open');
            }
        }
        
        function hideTermsModal() {
            const modal = document.getElementById('termsModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('modal-open');
            }
        }
        
        function showPrivacyModal() {
            const modal = document.getElementById('privacyModal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('modal-open');
            }
        }
        
        function hidePrivacyModal() {
            const modal = document.getElementById('privacyModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('modal-open');
            }
        }
        
        function agreeToTerms() {
            const agreeCheckbox = document.getElementById('agreeTerms');
            if (agreeCheckbox) {
                agreeCheckbox.checked = true;
                hideTermsModal();
                showNotification('Terms of Service accepted', 'success');
                validateRegisterForm();
            }
        }
        
        function agreeToPrivacy() {
            const agreeCheckbox = document.getElementById('agreePrivacy');
            if (agreeCheckbox) {
                agreeCheckbox.checked = true;
                hidePrivacyModal();
                showNotification('Privacy Policy accepted', 'success');
                validateRegisterForm();
            }
        }
        
        function openOtpModal() {
            console.log('🔑 Opening OTP modal');
            const modal = document.getElementById('otpModal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('modal-open');
                resetOtpInputs();
                startOtpTimer();
                hideOtpError();
                
                // Update OTP attempts display
                updateOtpAttemptsDisplay();
                
                const firstInput = document.querySelector('.otp-input[data-index="0"]');
                if (firstInput) {
                    setTimeout(() => {
                        firstInput.focus();
                        firstInput.select();
                    }, 100);
                }
            }
        }
        
        function closeOtpModalFunc() {
            console.log('🔑 Closing OTP modal');
            const modal = document.getElementById('otpModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('modal-open');
                stopOtpTimer();
                hideOtpError();
            }
        }
        
        // ============================================
        // OTP FUNCTIONS
        // ============================================
        function getOtpCode() {
            const inputs = document.querySelectorAll('.otp-input');
            return Array.from(inputs).map(input => input.value).join('');
        }
        
        function resetOtpInputs() {
            const inputs = document.querySelectorAll('.otp-input');
            inputs.forEach(input => {
                input.value = '';
                input.classList.remove('filled', 'border-red-500');
            });
            if (inputs[0]) {
                inputs[0].focus();
                inputs[0].select();
            }
        }
        
        function startOtpTimer() {
            otpTimeLeft = 180;
            const timerElement = document.getElementById('otpTimer');
            const resendButton = document.getElementById('resendOtp');
            
            if (resendButton) {
                resendButton.disabled = true;
                resendButton.innerHTML = 'Resend OTP';
            }
            
            updateTimerDisplay();
            
            if (otpTimer) clearInterval(otpTimer);
            
            otpTimer = setInterval(() => {
                otpTimeLeft--;
                updateTimerDisplay();
                
                if (otpTimeLeft <= 0) {
                    stopOtpTimer();
                    if (resendButton) {
                        resendButton.disabled = false;
                        resendButton.innerHTML = '<i class="fas fa-redo-alt mr-1"></i> Resend OTP';
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
        
        function showOtpError(message) {
            const errorElement = document.getElementById('otpError');
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.classList.remove('hidden');
                
                const inputs = document.querySelectorAll('.otp-input');
                inputs.forEach(input => {
                    input.classList.add('border-red-500');
                });
            }
        }
        
        function hideOtpError() {
            const errorElement = document.getElementById('otpError');
            if (errorElement) {
                errorElement.classList.add('hidden');
                
                const inputs = document.querySelectorAll('.otp-input');
                inputs.forEach(input => {
                    input.classList.remove('border-red-500');
                });
            }
        }
        
        // ============================================
        // UTILITY FUNCTIONS
        // ============================================
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
            const existing = document.querySelectorAll('.notification');
            existing.forEach(notif => notif.remove());
            
            const notification = document.createElement('div');
            notification.className = `notification ${
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
            
            setTimeout(() => notification.classList.add('show'), 100);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (notification.parentNode) notification.remove();
                    }, 300);
                }
            }, 5000);
        }
        
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        // Make functions globally available
        window.showNotification = showNotification;
        window.closeOtpModalFunc = closeOtpModalFunc;
        window.resetAllAttempts = resetAllAttempts;
        window.testLockoutFeature = testLockoutFeature;
    </script>
</body>
</html>