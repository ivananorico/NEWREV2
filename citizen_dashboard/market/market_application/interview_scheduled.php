<?php
// revenue2/citizen_dashboard/market/market_application/interview_scheduled.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Include database connection
include_once '../../../db/Market/market_db.php';

// Fetch user's scheduled interview applications
$applications = [];
$total_applications = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            rr.*,
            ro.*,
            s.name as stall_name,
            sr.class_name,
            sr.price as class_price,
            m.name as market_name
        FROM rental_registration rr
        JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN maps m ON rr.map_id = m.id
        WHERE ro.user_id = ? 
          AND rr.application_status = 'interview_scheduled'
        ORDER BY rr.interview_date ASC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = count($applications);
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Get the base URL for the background image
$base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
$bg_image_path = $base_url . '/revenue2/Login/images/gsmbg.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduled Interviews - Market Rent Services</title>
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
                radial-gradient(circle at 20% 80%, rgba(76, 175, 80, 0.1) 0%, transparent 50%),
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

        .status-scheduled {
            background-color: rgba(59, 130, 246, 0.1);
            color: #1e40af;
            border-color: rgba(59, 130, 246, 0.2);
        }

        .status-pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border-color: rgba(245, 158, 11, 0.2);
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
            background: linear-gradient(90deg, #f59e0b 0%, #f59e0b 25%, #3b82f6 25%, #3b82f6 50%, #e5e7eb 50%, #e5e7eb 100%);
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

        .help-section {
            background: linear-gradient(135deg, rgba(249, 250, 251, 0.9) 0%, rgba(243, 244, 246, 0.9) 100%);
            border: 1px solid rgba(209, 213, 219, 0.5);
            backdrop-filter: blur(10px);
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
            background-color: #3b82f6;
            color: white;
        }
        
        .step-number.completed {
            background-color: #f59e0b;
            color: white;
        }
        
        .step-label {
            font-size: 0.7rem;
            color: #6b7280;
            font-weight: 500;
            line-height: 1.2;
        }
        
        .step-label.active {
            color: #3b82f6;
            font-weight: 600;
        }
        
        .step-label.completed {
            color: #f59e0b;
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
            background-color: #3b82f6;
            z-index: 1;
            width: 50%;
        }

        .interview-card {
            background: linear-gradient(135deg, rgba(224, 242, 254, 0.9) 0%, rgba(240, 249, 255, 0.9) 100%);
            border: 2px solid #0ea5e9;
            border-radius: 0.75rem;
            padding: 1.25rem;
        }

        .reminder-box {
            background: linear-gradient(135deg, rgba(254, 252, 232, 0.9) 0%, rgba(254, 249, 195, 0.9) 100%);
            border: 1px solid rgba(253, 224, 71, 0.5);
            border-radius: 0.75rem;
            padding: 1rem;
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
                    <h1 class="text-2xl font-bold text-gray-900">Scheduled Interviews</h1>
                    <p class="text-gray-600">Your market stall application interviews</p>
                </div>
            </div>
            <?php if ($total_applications > 0): ?>
            <div class="text-right">
                <div class="text-sm text-gray-500">Interview<?php echo $total_applications > 1 ? 's' : ''; ?> Scheduled</div>
                <div class="text-2xl font-bold text-blue-600"><?php echo $total_applications; ?></div>
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
                    <div class="step-number active">
                        2
                    </div>
                    <div class="step-label active">Sched Interview</div>
                </div>
                
                <div class="progress-step">
                    <div class="step-number">
                        3
                    </div>
                    <div class="step-label">Interviewed</div>
                </div>
                
                <div class="progress-step">
                    <div class="step-number">
                        4
                    </div>
                    <div class="step-label">Proceed Payment</div>
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
                <span>Current Status: <span class="font-bold text-blue-600">Interview Scheduled</span></span>
                <span>Step 2 of 6</span>
            </div>
            
            <!-- Progress bar -->
            <div class="progress-container">
                <div class="progress-fill" style="width: 50%"></div>
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

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="form-card p-12 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-calendar-times text-gray-400 text-3xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">No Scheduled Interviews</h3>
            <p class="text-gray-600 mb-8 max-w-md mx-auto">
                You don't have any scheduled interviews. Check your pending applications for interview updates.
            </p>
            <a href="pending.php" 
               class="inline-flex items-center px-5 py-2.5 bg-[var(--primary)] text-white rounded-lg hover:opacity-90 transition-colors">
                <i class="fas fa-clock mr-2"></i>Check Pending Applications
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
                    
                    // Format dates
                    $birth_date = !empty($app['birth_date']) ? date('F j, Y', strtotime($app['birth_date'])) : 'Not provided';
                    $app_date = !empty($app['created_at']) ? date('F j, Y', strtotime($app['created_at'])) : 'Not provided';
                    $app_datetime = !empty($app['created_at']) ? date('F j, Y g:i A', strtotime($app['created_at'])) : 'Not provided';
                    
                    // Format interview date
                    $interview_date = !empty($app['interview_date']) ? date('F j, Y g:i A', strtotime($app['interview_date'])) : 'To be scheduled';
                    $interview_date_short = !empty($app['interview_date']) ? date('M j, Y', strtotime($app['interview_date'])) : 'TBD';
                    
                    // Calculate amounts
                    $stall_rights_amount = $app['stall_rights_amount'] ?? 0;
                    $security_bond = $app['security_bond'] ?? 0;
                    $monthly_rent = $app['monthly_rent'] ?? 0;
                    $total_amount_due = $stall_rights_amount + $security_bond;
                    
                    // Calculate days until interview
                    $days_until = '';
                    if (!empty($app['interview_date'])) {
                        $interview_day = new DateTime($app['interview_date']);
                        $today = new DateTime();
                        $interval = $today->diff($interview_day);
                        $days_until = $interval->days;
                    }
                    
                    // Get business name and type from rent_stall table (if exists)
                    $business_name = !empty($app['business_name']) ? $app['business_name'] : 'Not provided';
                    $business_type = !empty($app['business_type']) ? $app['business_type'] : 'Not provided';
                    $gender = !empty($app['gender']) ? ucfirst($app['gender']) : 'Not provided';
                    $telephone = !empty($app['telephone']) ? $app['telephone'] : 'Not provided';
                    $emergency_name = !empty($app['emergency_name']) ? $app['emergency_name'] : null;
                    $emergency_contact = !empty($app['emergency_contact']) ? $app['emergency_contact'] : null;
                    $market_name = !empty($app['market_name']) ? $app['market_name'] : 'Not provided';
                ?>

                <!-- Application Form Card -->
                <div class="form-card">
                    <!-- Header -->
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                            <div>
                                <div class="flex items-center mb-2">
                                    <h2 class="text-xl font-bold text-gray-900 mr-4">
                                        Application #<?php echo $app['stall_rights_no'] ?? $app['id']; ?>
                                    </h2>
                                    <span class="status-badge status-scheduled">
                                        <i class="fas fa-calendar-check mr-1"></i>Interview Scheduled
                                    </span>
                                </div>
                                <p class="text-gray-600">
                                    <i class="fas fa-store mr-2"></i>
                                    <?php echo $app['stall_name']; ?> • <?php echo $app['class_name']; ?> Class
                                    <?php if ($market_name !== 'Not provided'): ?>
                                    • <?php echo $market_name; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="mt-3 md:mt-0">
                                <div class="text-sm text-gray-500">Interview Date</div>
                                <div class="font-medium text-gray-900"><?php echo $interview_date_short; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Interview Notice -->
                    <div class="interview-card mx-6 mt-6 mb-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-calendar-alt text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Interview Scheduled!</h3>
                                <p class="text-blue-600">Your interview has been scheduled. Please arrive 15 minutes early.</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-3">
                                <div>
                                    <label class="form-label">Date & Time</label>
                                    <div class="form-value bg-blue-50 border-blue-100">
                                        <i class="fas fa-clock text-blue-500 mr-2"></i>
                                        <span class="font-bold text-blue-700"><?php echo $interview_date; ?></span>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="form-label">Location</label>
                                    <div class="form-value">
                                        <i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>
                                        Market Administration Office, 2nd Floor
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-3">
                                <div>
                                    <label class="form-label">Interviewer</label>
                                    <div class="form-value">
                                        <i class="fas fa-user-tie text-blue-500 mr-2"></i>
                                        <?php echo !empty($app['interviewer']) ? htmlspecialchars($app['interviewer']) : 'Market Administrator'; ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="form-label">Office Contact</label>
                                    <div class="form-value">
                                        <i class="fas fa-phone-alt text-blue-500 mr-2"></i>
                                        (02) 1234-5680
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($days_until !== ''): ?>
                        <div class="mt-6 pt-6 border-t border-blue-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="form-label">Days Until Interview</label>
                                    <div class="form-value">
                                        <?php if ($days_until == 0): ?>
                                            <span class="font-bold text-green-600">Interview is TODAY</span>
                                        <?php elseif ($days_until == 1): ?>
                                            <span class="font-bold text-yellow-600">Interview is TOMORROW</span>
                                        <?php else: ?>
                                            <span class="font-bold text-blue-600"><?php echo $days_until; ?> day<?php echo $days_until > 1 ? 's' : ''; ?> from now</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button onclick="printInterviewSlip('<?php echo $app['stall_rights_no']; ?>', '<?php echo $interview_date; ?>', '<?php echo htmlspecialchars($full_name); ?>')" 
                                        class="inline-flex items-center px-4 py-2 bg-white text-blue-600 border border-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                    <i class="fas fa-print mr-2"></i>Print Interview Slip
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Application Details -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Personal Information -->
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
                                    
                                    <div class="form-group">
                                        <label class="form-label">Date Applied</label>
                                        <div class="form-value"><?php echo $app_datetime; ?></div>
                                    </div>
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
                                                    <div class="<?php echo $business_name === 'Not provided' ? 'data-empty' : 'data-value'; ?>">
                                                        <?php echo htmlspecialchars($business_name); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="flex justify-between items-center">
                                                    <label class="form-label text-sm">Business Type:</label>
                                                    <div class="<?php echo $business_type === 'Not provided' ? 'data-empty' : 'data-value'; ?>">
                                                        <?php echo htmlspecialchars($business_type); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="flex justify-between items-center">
                                                    <label class="form-label text-sm">Stall Class:</label>
                                                    <div class="data-value text-[var(--primary)] font-medium"><?php echo $app['class_name']; ?> Class</div>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="flex justify-between items-center">
                                                    <label class="form-label text-sm">Market Location:</label>
                                                    <div class="<?php echo $market_name === 'Not provided' ? 'data-empty' : 'data-value'; ?>">
                                                        <?php echo htmlspecialchars($market_name); ?>
                                                    </div>
                                                </div>
                                            </div>
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
                                                <div class="text-sm text-purple-600">Payable after interview approval</div>
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
                                                    <div class="text-xs text-gray-500">Refundable</div>
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

                        <!-- Interview Requirements -->
                        <div class="reminder-box mt-8">
                            <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-clipboard-check text-yellow-600 mr-2"></i>
                                What to Bring to Your Interview
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex items-start">
                                    <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                        <i class="fas fa-file-alt text-green-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-800">Original Documents</div>
                                        <div class="text-sm text-gray-600 mt-1">All uploaded documents in original form for verification</div>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                        <i class="fas fa-id-card text-blue-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-800">Valid ID</div>
                                        <div class="text-sm text-gray-600 mt-1">Government-issued photo identification</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Important Notes -->
                    <div class="p-6 bg-blue-50 border-t border-blue-100">
                        <div class="flex items-start">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-600"></i>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">Interview Status & Next Steps</h4>
                                <p class="text-sm text-gray-700 mb-2">
                                    Your application is currently on <span class="font-bold text-blue-600">Step 2: Interview Scheduled</span>. 
                                    You have been scheduled for an interview.
                                </p>
                                <ul class="text-sm text-gray-700 space-y-1">
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-yellow-500 mr-2 mt-0.5"></i>
                                        <span><strong>Step 1 (Completed):</strong> Application submitted and reviewed</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-calendar-check text-blue-500 mr-2 mt-0.5"></i>
                                        <span><strong>Step 2 (Current):</strong> Interview scheduled for <?php echo $interview_date; ?></span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-arrow-right text-gray-400 mr-2 mt-0.5"></i>
                                        <span><strong>Step 3:</strong> Attend scheduled interview</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-arrow-right text-gray-400 mr-2 mt-0.5"></i>
                                        <span><strong>Step 4:</strong> Proceed with payment if interview is successful</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-arrow-right text-gray-400 mr-2 mt-0.5"></i>
                                        <span><strong>Step 5:</strong> Complete payment process</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-arrow-right text-gray-400 mr-2 mt-0.5"></i>
                                        <span><strong>Step 6:</strong> Final approval and stall assignment</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Help Section -->
        <div class="form-card p-6 mt-8">
            <h3 class="section-header text-lg font-semibold text-gray-800 mb-6">
                <i class="fas fa-question-circle text-[var(--primary)] mr-2"></i> Need Assistance?
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h4 class="font-medium text-gray-800 mb-4">Contact Information</h4>
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-phone text-blue-600"></i>
                            </div>
                            <div>
                                <div class="font-medium text-gray-700">Phone</div>
                                <div class="text-gray-600">(02) 1234-5680</div>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-envelope text-green-600"></i>
                            </div>
                            <div>
                                <div class="font-medium text-gray-700">Email</div>
                                <div class="text-gray-600">market@goserveph.gov.ph</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="font-medium text-gray-800 mb-4">Office Details</h4>
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-clock text-orange-600"></i>
                            </div>
                            <div>
                                <div class="font-medium text-gray-700">Office Hours</div>
                                <div class="text-gray-600">Monday to Friday: 8:00 AM - 5:00 PM</div>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-map-marker-alt text-purple-600"></i>
                            </div>
                            <div>
                                <div class="font-medium text-gray-700">Location</div>
                                <div class="text-gray-600">Market Administration Office, 2nd Floor</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Settings</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><i class="fas fa-phone mr-2 text-gray-400"></i> (02) 1234-5680</li>
                    <li><i class="fas fa-envelope mr-2 text-gray-400"></i> market@goserveph.gov.ph</li>
                    <li><i class="fas fa-clock mr-2 text-gray-400"></i> Mon-Fri: 8AM - 5PM</li>
                </ul>
            </div>

            <!-- Social -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Connect</h4>
                <div class="flex space-x-4 text-2xl">
                    <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors">
                        <i class="fab fa-twitter"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Copyright -->
        <div class="border-t border-gray-200 mt-10 pt-8">
            <p class="text-sm text-gray-500 text-center">
                &copy; 2026 GoServePH Local Government Unit. Republic of the Philippines.
            </p>
        </div>
    </div>
</footer>

<script>
function printInterviewSlip(stallRightsNo, interviewDatetime, applicantName) {
    const slipContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Interview Slip - ${stallRightsNo}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    padding: 20px; 
                    max-width: 600px; 
                    margin: 0 auto;
                    line-height: 1.6;
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #1e40af;
                }
                .details { 
                    margin: 25px 0;
                }
                .detail-row { 
                    margin-bottom: 12px;
                    padding-bottom: 12px;
                    border-bottom: 1px solid #e5e7eb;
                }
                .label { 
                    font-weight: bold; 
                    color: #1e40af;
                    display: block;
                    margin-bottom: 4px;
                }
                .footer { 
                    margin-top: 30px; 
                    font-size: 11px; 
                    color: #6b7280; 
                    text-align: center; 
                    padding-top: 15px; 
                    border-top: 1px solid #e5e7eb;
                }
                .instructions { 
                    background: #f3f4f6; 
                    padding: 15px; 
                    border-radius: 6px; 
                    margin: 20px 0; 
                    font-size: 13px;
                }
                .qr-section {
                    text-align: center;
                    margin: 20px 0;
                    padding: 15px;
                    border: 1px dashed #9ca3af;
                    border-radius: 6px;
                }
                @media print {
                    body { padding: 15px; }
                    button { display: none !important; }
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2 style="color: #1e40af; margin-bottom: 5px;">Market Stall Rental Interview</h2>
                <h3 style="color: #374151; font-weight: normal;">GoServePH Local Government Unit</h3>
            </div>
            
            <div class="details">
                <div class="detail-row">
                    <span class="label">Application Reference:</span>
                    <div style="font-size: 16px; font-weight: bold;">${stallRightsNo}</div>
                </div>
                <div class="detail-row">
                    <span class="label">Applicant Name:</span>
                    ${applicantName}
                </div>
                <div class="detail-row">
                    <span class="label">Interview Schedule:</span>
                    ${interviewDatetime}
                </div>
                <div class="detail-row">
                    <span class="label">Interview Location:</span>
                    Market Administration Office<br>
                    2nd Floor, Public Market Building
                </div>
                <div class="detail-row">
                    <span class="label">Office Contact:</span>
                    (02) 1234-5680
                </div>
            </div>
            
            <div class="instructions">
                <h4 style="color: #374151; margin-bottom: 10px;">Important Instructions:</h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <li style="margin-bottom: 5px;">Bring this slip to your interview</li>
                    <li style="margin-bottom: 5px;">Arrive 15 minutes before scheduled time</li>
                    <li style="margin-bottom: 5px;">Bring all original documents for verification</li>
                    <li style="margin-bottom: 5px;">Dress in business casual attire</li>
                    <li>Parking available at the market parking area</li>
                </ul>
            </div>
            
            <div class="qr-section">
                <p style="margin-bottom: 10px; color: #6b7280;">Scan this code at the reception:</p>
                <div style="background: #f3f4f6; padding: 20px; display: inline-block; border-radius: 8px;">
                    <div style="width: 100px; height: 100px; background: #9ca3af; margin: 0 auto; border-radius: 4px;"></div>
                </div>
                <p style="margin-top: 10px; font-size: 10px; color: #9ca3af;">QR Code for quick check-in</p>
            </div>
            
            <div class="footer">
                <p><strong>Generated on:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                <p>Market Administration Office • Public Market Building</p>
                <p>This slip is required for entry to the interview</p>
            </div>
            
            <div class="no-print" style="text-align: center; margin-top: 25px;">
                <button onclick="window.print()" style="background: #1e40af; color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold;">
                    <i class="fas fa-print" style="margin-right: 8px;"></i>Print Interview Slip
                </button>
                <p style="margin-top: 10px; font-size: 12px; color: #6b7280;">Print this slip and bring it to your interview</p>
            </div>
        </body>
        </html>
    `;
    
    const printWindow = window.open('', '_blank', 'width=700,height=800');
    printWindow.document.write(slipContent);
    printWindow.document.close();
    
    // Auto-print after a short delay
    setTimeout(() => {
        printWindow.print();
    }, 500);
}
</script>
</body>
</html>