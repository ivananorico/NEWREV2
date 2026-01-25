<?php
// revenue2/citizen_dashboard/market/market_application/paying.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Include database connection
include_once '../../../db/Market/market_db.php';

// Determine base URL based on whether we're on localhost or live domain
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
$base_url = $is_localhost 
    ? 'http://localhost/revenue2' 
    : 'https://revenuetreasury.goserveph.com';

// Market payment callback URL - dynamically generated
$market_callback_url = $base_url . '/citizen_dashboard/market/api/stall_rights_pay_api.php';

// Get applications with 'paying' status
$applications = [];
$total_applications = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            rr.id as registration_id,
            rr.stall_rights_no,
            rr.stall_rights_amount,
            rr.monthly_rent,
            rr.security_bond,
            rr.total_amount_due,
            rr.application_status,
            rr.stall_id,
            rr.map_id,
            ro.first_name,
            ro.middle_name,
            ro.last_name,
            ro.suffix,
            ro.email,
            ro.mobile,
            ro.telephone,
            ro.renter_code,
            ro.gender,
            ro.birth_date,
            ro.house_number,
            ro.street,
            ro.barangay,
            ro.city,
            ro.province,
            ro.zip_code,
            ro.emergency_name,
            ro.emergency_contact,
            rs.business_name,
            rs.business_type,
            rs.start_date,
            s.name as stall_name,
            s.class_id,
            sr.class_name,
            sr.price as class_price,
            m.name as market_name
        FROM rental_registration rr
        JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN maps m ON rr.map_id = m.id
        WHERE ro.user_id = ? 
          AND rr.application_status = 'paying'
        ORDER BY rr.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = count($applications);
    
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Get the base URL for the background image
$base_url_bg = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
$bg_image_path = $base_url_bg . '/revenue2/Login/images/gsmbg.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Required - Market Rent Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #9aa5b1;
            --accent: #4caf50;
            --background: #fbfbfb;
        }

        body {
            background: linear-gradient(135deg, rgba(240, 240, 240, 0.4) 0%, rgba(230, 230, 230, 0.4) 50%, rgba(220, 220, 220, 0.3) 100%);
            position: relative;
            overflow-x: hidden;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* Background image with blur */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('<?php echo $bg_image_path; ?>') center/cover no-repeat;
            opacity: 0.08;
            pointer-events: none;
            z-index: -2;
            filter: blur(1px);
        }
        
        /* Animated background particles */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(74, 144, 226, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(253, 168, 17, 0.05) 0%, transparent 50%);
            animation: backgroundFloat 20s ease-in-out infinite;
            z-index: -1;
        }
        
        @keyframes backgroundFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(1deg); }
            66% { transform: translateY(10px) rotate(-1deg); }
        }

        .header-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border-radius: 20px;
            padding: 1.5rem;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 231, 235, 0.8);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            overflow: hidden;
        }

        .section-header {
            position: relative;
            padding-left: 1rem;
            margin-bottom: 1.5rem;
        }

        .section-header::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: var(--primary);
            border-radius: 2px;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.375rem;
        }

        .form-value {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.9375rem;
            color: #111827;
            min-height: 2.75rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid;
        }

        .status-paying {
            background-color: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
            border-color: rgba(139, 92, 246, 0.2);
        }

        .info-box {
            background: linear-gradient(135deg, rgba(249, 250, 251, 0.9) 0%, rgba(243, 244, 246, 0.9) 100%);
            border: 1px solid rgba(229, 231, 235, 0.5);
            border-radius: 0.75rem;
            padding: 1.25rem;
            backdrop-filter: blur(10px);
        }

        .progress-container {
            height: 8px;
            background: rgba(229, 231, 235, 0.8);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 1rem;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            background: linear-gradient(90deg, #f59e0b 0%, #f59e0b 25%, #3b82f6 25%, #3b82f6 50%, #10b981 50%, #10b981 75%, #8b5cf6 75%, #8b5cf6 100%);
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(209, 213, 219, 0.5);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 1);
            border-color: var(--primary);
        }

        .data-value {
            font-weight: 500;
            color: #111827;
        }
        
        .data-empty {
            font-style: italic;
            color: #6b7280;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 0.5rem;
        }
        
        .progress-step {
            text-align: center;
            position: relative;
            flex: 1;
        }
        
        .step-number {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e5e7eb;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            margin: 0 auto 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .step-number.active {
            background-color: #8b5cf6;
            color: white;
        }
        
        .step-number.completed {
            background-color: #8b5cf6;
            color: white;
        }
        
        .step-label {
            font-size: 0.7rem;
            color: #6b7280;
            font-weight: 500;
            line-height: 1.2;
        }
        
        .step-label.active {
            color: #8b5cf6;
            font-weight: 600;
        }
        
        .step-label.completed {
            color: #8b5cf6;
        }
        
        .progress-line {
            position: absolute;
            top: 12px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #e5e7eb;
            z-index: 1;
        }
        
        .progress-line-fill {
            position: absolute;
            top: 12px;
            left: 0;
            height: 2px;
            background-color: #8b5cf6;
            z-index: 1;
            width: 100%;
        }

        .payment-card {
            background: linear-gradient(135deg, rgba(245, 243, 255, 0.9) 0%, rgba(237, 233, 254, 0.9) 100%);
            border: 2px solid #8b5cf6;
            border-radius: 0.75rem;
            padding: 1.25rem;
        }

        .glow-button {
            transition: all 0.3s ease;
        }
        
        .glow-button:hover {
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.4);
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
<?php include '../../navbar.php'; ?>

<main class="container mx-auto px-4 sm:px-6 py-8 max-w-6xl flex-grow">

    <!-- Page Header -->
    <div class="header-box mb-8">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center">
                <a href="../market_services.php" 
                   class="back-btn inline-flex items-center px-4 py-2 rounded-lg text-gray-700 hover:text-[var(--primary)] mr-4">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Services
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Payment Required</h1>
                    <p class="text-gray-600">Complete payment to finalize your stall rental application</p>
                </div>
            </div>
            <?php if ($total_applications > 0): ?>
            <div class="text-right">
                <div class="text-sm text-gray-500">Applications Requiring Payment</div>
                <div class="text-2xl font-bold text-purple-600"><?php echo $total_applications; ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Progress Steps -->
        <div class="mt-6">
            <div class="progress-steps">
                <div class="progress-step">
                    <div class="step-number completed">
                        <i class="fas fa-check text-xs"></i>
                    </div>
                    <div class="step-label completed">Pending</div>
                </div>
                
                <div class="progress-step">
                    <div class="step-number completed">
                        <i class="fas fa-check text-xs"></i>
                    </div>
                    <div class="step-label completed">Sched Interview</div>
                </div>
                
                <div class="progress-step">
                    <div class="step-number completed">
                        <i class="fas fa-check text-xs"></i>
                    </div>
                    <div class="step-label completed">Interviewed</div>
                </div>
                
                <div class="progress-step">
                    <div class="step-number active">
                        4
                    </div>
                    <div class="step-label active">Proceed Payment</div>
                </div>
                
                <div class="progress-step">
                    <div class="step-number">
                        5
                    </div>
                    <div class="step-label">Paid</div>
                </div>
                
                <div class="progress-step">
                    <div class="step-number">
                        6
                    </div>
                    <div class="step-label">Approve</div>
                </div>
            </div>
            
            <!-- Progress line -->
            <div class="relative">
                <div class="progress-line"></div>
                <div class="progress-line-fill"></div>
            </div>
            
            <!-- Current status -->
            <div class="flex items-center justify-between text-sm text-gray-600 mt-4">
                <span>Current Status: <span class="font-bold text-purple-600">Payment Required</span></span>
                <span>Step 4 of 6</span>
            </div>
            
            <!-- Progress bar -->
            <div class="progress-container">
                <div class="progress-fill" style="width: 100%"></div>
            </div>
        </div>
    </div>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
        <div class="form-card p-4 mb-6 border-l-4 border-red-500 bg-red-50">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <div>
                    <h4 class="font-medium text-red-800">Error</h4>
                    <p class="text-red-700 text-sm"><?php echo $error_message; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Payment Success Message -->
    <?php if (isset($_GET['payment_success']) && $_GET['payment_success'] === 'true'): ?>
        <div class="form-card p-4 mb-6 border-l-4 border-green-500 bg-green-50">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3 text-xl"></i>
                <div>
                    <h4 class="font-medium text-green-800">Payment Successful!</h4>
                    <p class="text-green-700 text-sm">Your payment has been processed. Your application status will update shortly.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="form-card p-12 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-credit-card text-gray-400 text-3xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">No Pending Payments</h3>
            <p class="text-gray-600 mb-8 max-w-md mx-auto">
                You don't have any applications requiring payment at this time. Check your interviewed applications for payment updates.
            </p>
            <a href="../market_services.php" 
               class="inline-flex items-center px-5 py-2.5 bg-[var(--primary)] text-white rounded-lg hover:opacity-90 transition-colors">
                <i class="fas fa-store mr-2"></i>Back to Market Services
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($applications as $app): ?>
                <?php
                    // Prepare full name
                    $full_name = trim($app['first_name'] . ' ' . 
                        (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . 
                        $app['last_name'] . 
                        (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
                    
                    // Build full address
                    $address_parts = [];
                    if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                    if (!empty($app['street'])) $address_parts[] = $app['street'];
                    if (!empty($app['barangay'])) $address_parts[] = 'Brgy. ' . $app['barangay'];
                    if (!empty($app['city'])) $address_parts[] = $app['city'];
                    if (!empty($app['province'])) $address_parts[] = $app['province'];
                    if (!empty($app['zip_code'])) $address_parts[] = $app['zip_code'];
                    $full_address = implode(', ', $address_parts);
                    
                    // Format birth date
                    $birth_date = !empty($app['birth_date']) ? date('F j, Y', strtotime($app['birth_date'])) : 'Not provided';
                    
                    // Get payment breakdown
                    $stall_rights_amount = $app['stall_rights_amount'] ?? 0;
                    $security_bond = $app['security_bond'] ?? 0;
                    $monthly_rent = $app['monthly_rent'] ?? 0;
                    $total_amount_due = $app['total_amount_due'] ?? ($stall_rights_amount + $security_bond);
                    
                    // Get the registration ID
                    $registration_id = $app['registration_id'];
                    
                    // Create payment description
                    $payment_description = "Market Stall: {$app['stall_name']} - {$app['class_name']} Class";
                    
                    // Get gender
                    $gender = !empty($app['gender']) ? ucfirst($app['gender']) : 'Not provided';
                    $telephone = !empty($app['telephone']) ? $app['telephone'] : 'Not provided';
                    $emergency_name = !empty($app['emergency_name']) ? $app['emergency_name'] : null;
                    $emergency_contact = !empty($app['emergency_contact']) ? $app['emergency_contact'] : null;
                ?>

                <!-- Application Form Card -->
                <div class="form-card">
                    <!-- Header -->
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                            <div>
                                <div class="flex items-center mb-2">
                                    <h2 class="text-xl font-bold text-gray-900 mr-4">
                                        Application #<?php echo $app['stall_rights_no']; ?>
                                    </h2>
                                    <span class="status-badge status-paying">
                                        <i class="fas fa-credit-card mr-1"></i>Payment Required
                                    </span>
                                </div>
                                <p class="text-gray-600">
                                    <i class="fas fa-store mr-2"></i>
                                    <?php echo $app['stall_name']; ?> • <?php echo $app['class_name']; ?> Class
                                    <?php if (!empty($app['market_name'])): ?>
                                    • <?php echo $app['market_name']; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="mt-3 md:mt-0">
                                <div class="text-sm text-gray-500">Total Amount Due</div>
                                <div class="text-2xl font-bold text-purple-600">₱<?php echo number_format($total_amount_due, 2); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Action Card -->
                    <div class="payment-card mx-6 mt-6 mb-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-credit-card text-purple-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Payment Required</h3>
                                <p class="text-purple-600">Complete payment to finalize your stall rental application.</p>
                            </div>
                        </div>
                        
                        <!-- CHANGED: POST with target="_blank" opens in new tab -->
                        <form id="paymentForm<?php echo $registration_id; ?>" 
                              method="POST" 
                              action="../../digital/index.php" 
                              target="_blank">
                            <!-- UNIVERSAL PAYMENT PARAMETERS -->
                            <input type="hidden" name="system" value="market">
                            <input type="hidden" name="ref" value="<?php echo $registration_id; ?>">
                            <input type="hidden" name="amount" value="<?php echo $total_amount_due; ?>">
                            <input type="hidden" name="purpose" value="<?php echo htmlspecialchars($payment_description); ?>">
                            <input type="hidden" name="callback" value="<?php echo $market_callback_url; ?>">
                            <input type="hidden" name="applicant_name" value="<?php echo htmlspecialchars($full_name); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($app['email']); ?>">
                            <input type="hidden" name="mobile" value="<?php echo htmlspecialchars($app['mobile']); ?>">
                            
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between">
                                <div class="mb-4 sm:mb-0">
                                    <div class="font-medium text-purple-800 mb-1">Ready to Complete Payment?</div>
                                    <div class="text-sm text-purple-700">
                                        Click "Pay Now" to proceed with secure payment in new tab
                                        <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded ml-2">
                                            <i class="fas fa-external-link-alt text-xs mr-1"></i>New Tab
                                        </span>
                                    </div>
                                </div>
                                <button type="submit" 
                                        class="inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-lg 
                                        hover:opacity-90 transition-all font-medium glow-button transform hover:scale-105 shadow-lg">
                                    <i class="fas fa-lock mr-2"></i>
                                    Pay Now - ₱<?php echo number_format($total_amount_due, 2); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Application Details -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Applicant Information -->
                            <div>
                                <h3 class="section-header text-lg font-semibold text-gray-800 mb-6">
                                    <i class="fas fa-user text-[var(--primary)] mr-2"></i> Applicant Information
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="form-group">
                                        <label class="form-label">Full Name</label>
                                        <div class="form-value"><?php echo htmlspecialchars($full_name); ?></div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="form-group">
                                            <label class="form-label">Gender</label>
                                            <div class="form-value"><?php echo $gender; ?></div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Birth Date</label>
                                            <div class="form-value"><?php echo $birth_date; ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Renter Code</label>
                                        <div class="form-value font-medium text-[var(--primary)]"><?php echo $app['renter_code']; ?></div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Email Address</label>
                                        <div class="form-value"><?php echo htmlspecialchars($app['email']); ?></div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="form-group">
                                            <label class="form-label">Mobile Number</label>
                                            <div class="form-value"><?php echo $app['mobile']; ?></div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Telephone</label>
                                            <div class="form-value"><?php echo $telephone; ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Complete Address</label>
                                        <div class="form-value"><?php echo htmlspecialchars($full_address); ?></div>
                                    </div>
                                    
                                    <?php if (!empty($emergency_name) && !empty($emergency_contact)): ?>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="form-group">
                                            <label class="form-label">Emergency Contact Person</label>
                                            <div class="form-value"><?php echo htmlspecialchars($emergency_name); ?></div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Emergency Contact Number</label>
                                            <div class="form-value"><?php echo $emergency_contact; ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Stall & Financial Information -->
                            <div>
                                <h3 class="section-header text-lg font-semibold text-gray-800 mb-6">
                                    <i class="fas fa-store text-[var(--primary)] mr-2"></i> Stall & Financial Details
                                </h3>
                                
                                <div class="space-y-6">
                                    <!-- Stall Information -->
                                    <div class="info-box">
                                        <h4 class="font-medium text-gray-800 mb-4">Stall Information</h4>
                                        <div class="space-y-3">
                                            <div>
                                                <div class="flex justify-between items-center">
                                                    <label class="form-label text-sm">Stall Name:</label>
                                                    <div class="data-value"><?php echo htmlspecialchars($app['stall_name']); ?></div>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="flex justify-between items-center">
                                                    <label class="form-label text-sm">Business Name:</label>
                                                    <div class="<?php echo !empty($app['business_name']) ? 'data-value' : 'data-empty'; ?>">
                                                        <?php echo !empty($app['business_name']) ? htmlspecialchars($app['business_name']) : 'Not provided'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="flex justify-between items-center">
                                                    <label class="form-label text-sm">Business Type:</label>
                                                    <div class="<?php echo !empty($app['business_type']) ? 'data-value' : 'data-empty'; ?>">
                                                        <?php echo !empty($app['business_type']) ? htmlspecialchars($app['business_type']) : 'Not provided'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="flex justify-between items-center">
                                                    <label class="form-label text-sm">Stall Class:</label>
                                                    <div class="data-value text-purple-600 font-medium"><?php echo $app['class_name']; ?> Class</div>
                                                </div>
                                            </div>
                                            <?php if (!empty($app['market_name'])): ?>
                                            <div>
                                                <div class="flex justify-between items-center">
                                                    <label class="form-label text-sm">Market Location:</label>
                                                    <div class="data-value"><?php echo htmlspecialchars($app['market_name']); ?></div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="flex justify-between items-center">
                                                    <label class="form-label text-sm">Stall Rights Number:</label>
                                                    <div class="data-value font-mono"><?php echo $app['stall_rights_no']; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Monthly Rent -->
                                    <div class="info-box border-l-4 border-blue-500">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                                <i class="fas fa-calendar-alt text-blue-600"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium">Monthly Rent</div>
                                                <div class="text-sm text-blue-600">Payable monthly after approval</div>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-2xl font-bold text-blue-700">
                                                ₱<?php echo number_format($monthly_rent, 2); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">Per month</div>
                                        </div>
                                    </div>

                                    <!-- One-time Payments -->
                                    <div class="info-box border-l-4 border-purple-500">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                                <i class="fas fa-receipt text-purple-600"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium">One-time Payments</div>
                                                <div class="text-sm text-purple-600">Pay now to complete application</div>
                                            </div>
                                        </div>
                                        
                                        <div class="space-y-3">
                                            <?php if ($stall_rights_amount > 0): ?>
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">Stall Rights Fee</div>
                                                    <div class="text-xs text-gray-500">Non-refundable</div>
                                                </div>
                                                <div class="text-sm font-bold text-gray-900">
                                                    ₱<?php echo number_format($stall_rights_amount, 2); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($security_bond > 0): ?>
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">Security Bond</div>
                                                    <div class="text-xs text-gray-500">Refundable upon termination</div>
                                                </div>
                                                <div class="text-sm font-bold text-gray-900">
                                                    ₱<?php echo number_format($security_bond, 2); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="pt-3 mt-3 border-t border-gray-200">
                                                <div class="flex justify-between items-center">
                                                    <div class="font-bold text-gray-900">Total Amount Due</div>
                                                    <div class="text-lg font-bold text-purple-600">
                                                        ₱<?php echo number_format($total_amount_due, 2); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Important Notes -->
                        <div class="mt-8 p-6 bg-purple-50 rounded-lg border border-purple-200">
                            <div class="flex items-start">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-info-circle text-purple-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 mb-2">Important Information</h4>
                                    <div class="text-sm text-gray-700 space-y-2">
                                        <p>
                                            Your application is currently on <span class="font-bold text-purple-600">Step 4: Payment Required</span>. 
                                            Complete payment of <span class="font-bold">₱<?php echo number_format($total_amount_due, 2); ?></span> to proceed with final approval.
                                        </p>
                                        <div class="flex items-start mt-2">
                                            <i class="fas fa-exclamation-triangle text-purple-500 mt-0.5 mr-2"></i>
                                            <span><strong>Important:</strong> Your stall assignment is reserved pending payment completion. Failure to pay within 7 days may result in cancellation.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Instructions -->
                    <div class="p-6 border-t border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-4">Payment Instructions</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="flex items-center p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-clock text-yellow-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-800">Payment Deadline</div>
                                    <div class="text-xs text-yellow-700">Within 7 days</div>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-3 bg-green-50 rounded-lg border border-green-200">
                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-shield-alt text-green-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-800">Secure Payment</div>
                                    <div class="text-xs text-green-700">256-bit SSL encryption</div>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-3 bg-blue-50 rounded-lg border border-blue-200">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-sync-alt text-blue-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-800">Real-time Status</div>
                                    <div class="text-xs text-blue-700">Auto-update after payment</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 text-sm text-gray-600 bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <div class="flex items-start">
                                <i class="fas fa-lightbulb text-purple-500 mt-0.5 mr-2"></i>
                                <div>
                                    <p class="font-medium text-gray-800 mb-1">Payment Process:</p>
                                    <ol class="list-decimal pl-5 space-y-1">
                                        <li>Click "Pay Now" button above</li>
                                        <li>Payment gateway opens in new tab</li>
                                        <li>Complete payment using your preferred method</li>
                                        <li>Return to this page - status will auto-refresh</li>
                                        <li>Receive confirmation via email/SMS</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- FOOTER -->
<footer class="bg-white border-t border-gray-200 mt-16">
    <div class="container mx-auto px-6 py-12 max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
            <!-- Brand -->
            <div class="col-span-1">
                <div class="flex items-center space-x-2 mb-4 text-2xl font-bold">
                    <span style="color: #4a90e2;">Go</span>
                    <span style="color: #4caf50;">Serve</span>
                    <span style="color: #4a90e2;">PH</span>
                </div>
                <p class="text-gray-600 leading-relaxed">
                    The official digital gateway of your Local Government Unit, providing efficient and transparent government services.
                </p>
            </div>
            
            <!-- Portal Links -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Portal</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><a href="../citizen_dashboard.php" class="hover:text-[#4a90e2] transition-colors">Dashboard</a></li>
                    <li><a href="../market_services.php" class="hover:text-[#4a90e2] transition-colors">Market Services</a></li>
                    <li><a href="pending.php" class="hover:text-[#4a90e2] transition-colors">Pending Applications</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><i class="fas fa-phone mr-2 text-gray-400"></i> (02) 8123-4567</li>
                    <li><i class="fas fa-envelope mr-2 text-gray-400"></i> market@goserveph.gov.ph</li>
                    <li><i class="fas fa-clock mr-2 text-gray-400"></i> Mon-Fri: 8AM - 5PM</li>
                </ul>
            </div>

            <!-- Help Section -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Need Assistance?</h4>
                <div class="space-y-3">
                    <div class="flex items-start">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-2 flex-shrink-0">
                            <i class="fas fa-credit-card text-purple-600 text-sm"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700">Payment Issues?</div>
                            <div class="text-xs text-gray-600">Contact: market-payments@goserveph.gov.ph</div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-2 flex-shrink-0">
                            <i class="fas fa-question-circle text-blue-600 text-sm"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700">General Questions</div>
                            <div class="text-xs text-gray-600">Visit Market Office, Public Market Building</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- FAQ Section -->
        <div class="mt-8 pt-8 border-t border-gray-200">
            <h4 class="font-medium text-gray-800 mb-4 text-sm">Frequently Asked Questions</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <div class="text-sm font-medium text-gray-700 mb-1">Will payment open in new window?</div>
                    <div class="text-xs text-gray-600">Yes, payment opens in secure new tab for enhanced security.</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-700 mb-1">Is my payment information secure?</div>
                    <div class="text-xs text-gray-600">Yes, all transactions use SSL encryption to protect your data.</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-700 mb-1">What happens after payment?</div>
                    <div class="text-xs text-gray-600">Your application moves to approval stage and contract is issued via email.</div>
                </div>
            </div>
        </div>
        
        <!-- Copyright -->
        <div class="border-t border-gray-200 mt-8 pt-8">
            <p class="text-sm text-gray-500 text-center">
                &copy; 2026 GoServePH Local Government Unit. Republic of the Philippines.
            </p>
        </div>
    </div>
</footer>

<script>
// Auto-refresh page every 2 minutes to check for status updates
setTimeout(() => {
    window.location.reload();
}, 120000);

// Simple check for payment tab closure
document.addEventListener('DOMContentLoaded', function() {
    // Listen for form submissions
    document.querySelectorAll('form[target="_blank"]').forEach(form => {
        form.addEventListener('submit', function() {
            // Store the time when payment tab was opened
            localStorage.setItem('paymentTabOpened', Date.now());
        });
    });
    
    // Check if we should refresh (payment might have completed)
    if (localStorage.getItem('paymentTabOpened')) {
        const openedTime = parseInt(localStorage.getItem('paymentTabOpened'));
        const currentTime = Date.now();
        const fiveMinutes = 5 * 60 * 1000; // 5 minutes in milliseconds
        
        // If payment tab was opened more than 5 minutes ago, refresh
        if (currentTime - openedTime > fiveMinutes) {
            localStorage.removeItem('paymentTabOpened');
            window.location.reload();
        }
    }
});
</script>
</body>
</html>