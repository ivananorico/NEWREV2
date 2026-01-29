<?php
// revenue2/citizen_dashboard/market/market_application/paid.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Include database connection
include_once '../../../db/Market/market_db.php';

// Function to get proper document URL
function getDocumentUrl(string $dbPath): string
{
    $clean = str_replace(['../', './'], '', $dbPath);
    $clean = ltrim($clean, '/');
    if (strpos($clean, 'revenue2/') === 0) {
        $clean = substr($clean, strlen('revenue2/'));
    }
    return '/revenue2/' . $clean;
}

// Fetch user's paid applications
$applications = [];
$total_applications = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            rr.*,
            ro.*,
            rs.business_name,
            rs.business_type,
            rs.start_date,
            rs.status as stall_status,
            s.name as stall_name,
            s.class_id,
            sr.class_name,
            sr.price as class_price,
            m.name as market_name,
            rr.payment_date,
            rr.reference_number,
            rr.stall_rights_no
        FROM rental_registration rr
        JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN maps m ON rr.map_id = m.id
        WHERE ro.user_id = ? 
          AND rr.application_status = 'paid'
        ORDER BY rr.payment_date DESC, rr.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = count($applications);
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Get the base URL for the background image - FIXED LINE 65
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url_bg = $protocol . '://' . $_SERVER['HTTP_HOST'];
$bg_image_path = $base_url_bg . '/revenue2/Login/images/gsmbg.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paid Applications - Market Rent Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #9aa5b1;
            --accent: #10b981;
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
                radial-gradient(circle at 20% 80%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
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

        .status-paid {
            background-color: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border-color: rgba(16, 185, 129, 0.2);
        }

        .status-active {
            background-color: rgba(37, 99, 235, 0.1);
            color: #1e40af;
            border-color: rgba(37, 99, 235, 0.2);
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
            background: linear-gradient(90deg, #10b981 0%, #10b981 85%, #3b82f6 85%, #3b82f6 100%);
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
            background-color: #10b981;
            color: white;
        }
        
        .step-number.completed {
            background-color: #10b981;
            color: white;
        }
        
        .step-label {
            font-size: 0.7rem;
            color: #6b7280;
            font-weight: 500;
            line-height: 1.2;
        }
        
        .step-label.active {
            color: #10b981;
            font-weight: 600;
        }
        
        .step-label.completed {
            color: #10b981;
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
            background-color: #10b981;
            z-index: 1;
            width: 85%;
        }

        .action-card {
            background: linear-gradient(135deg, rgba(236, 253, 245, 0.9) 0%, rgba(220, 252, 231, 0.9) 100%);
            border: 2px solid #10b981;
            border-radius: 0.75rem;
            padding: 1.25rem;
        }

        .warning-card {
            background: linear-gradient(135deg, rgba(254, 243, 199, 0.9) 0%, rgba(253, 230, 138, 0.9) 100%);
            border: 2px solid #f59e0b;
            border-radius: 0.75rem;
            padding: 1.25rem;
        }

        .critical-card {
            background: linear-gradient(135deg, rgba(254, 226, 226, 0.9) 0%, rgba(254, 202, 202, 0.9) 100%);
            border: 2px solid #dc2626;
            border-radius: 0.75rem;
            padding: 1.25rem;
        }

        .document-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(229, 231, 235, 0.8);
            border-radius: 0.75rem;
            padding: 1.25rem;
            text-align: center;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .document-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            padding: 20px;
        }
        
        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90vh;
            border-radius: 8px;
        }
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1001;
        }
        
        .modal-close:hover {
            color: #fbbf24;
        }
        
        .modal-caption {
            text-align: center;
            color: white;
            padding: 10px 20px;
            position: absolute;
            bottom: 0;
            width: 100%;
            background: rgba(0,0,0,0.7);
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
<?php include '../../navbar.php'; ?>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="modal-close">&times;</span>
    <img class="modal-content" id="modalImage">
    <div id="modalCaption" class="modal-caption"></div>
</div>

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
                    <h1 class="text-2xl font-bold text-gray-900">Paid Applications</h1>
                    <p class="text-gray-600">Applications with completed payment</p>
                </div>
            </div>
            <?php if ($total_applications > 0): ?>
            <div class="text-right">
                <div class="text-sm text-gray-500">Paid Applications</div>
                <div class="text-2xl font-bold text-green-600"><?php echo $total_applications; ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Progress Steps - Updated to show "Paid" as current step -->
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
                    <div class="step-number completed">
                        <i class="fas fa-check text-xs"></i>
                    </div>
                    <div class="step-label completed">Proceed Payment</div>
                </div>
                
                <div class="progress-step">
                    <div class="step-number active">
                        5
                    </div>
                    <div class="step-label active">Paid</div>
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
                <span>Current Status: <span class="font-bold text-green-600">Payment Completed</span></span>
                <span>Step 5 of 6</span>
            </div>
            
            <!-- Progress bar - 85% filled (5 out of 6 steps) -->
            <div class="progress-container">
                <div class="progress-fill" style="width: 85%"></div>
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
                <i class="fas fa-receipt text-gray-400 text-3xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">No Paid Applications</h3>
            <p class="text-gray-600 mb-8 max-w-md mx-auto">
                You don't have any applications with completed payment at this time. Check your applications requiring payment to proceed.
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
                    
                    // Format dates
                    $birth_date = !empty($app['birth_date']) ? date('F j, Y', strtotime($app['birth_date'])) : 'Not provided';
                    $payment_date = !empty($app['payment_date']) ? date('F j, Y g:i A', strtotime($app['payment_date'])) : 'Not provided';
                    $start_date = !empty($app['start_date']) ? date('F j, Y', strtotime($app['start_date'])) : 'Not set';
                    
                    // Calculate amounts
                    $stall_rights_amount = $app['stall_rights_amount'] ?? 0;
                    $security_bond = $app['security_bond'] ?? 0;
                    $monthly_rent = $app['monthly_rent'] ?? 0;
                    $total_amount_paid = $stall_rights_amount + $security_bond;
                    
                    // Stall status
                    $stall_status = $app['stall_status'] ?? 'pending';
                    $status_badge_text = $stall_status === 'active' ? 'Stall Active' : 'Awaiting Agreement Signing';
                    $status_icon = $stall_status === 'active' ? 'fa-store' : 'fa-file-signature';
                    
                    // Calculate deadline for office visit (3 business days from payment)
                    if (!empty($app['payment_date'])) {
                        $payment_date_obj = new DateTime($app['payment_date']);
                        $deadline_date = clone $payment_date_obj;
                        $business_days_added = 0;
                        while ($business_days_added < 3) {
                            $deadline_date->modify('+1 day');
                            // Skip Saturdays (6) and Sundays (7)
                            if ($deadline_date->format('N') < 6) {
                                $business_days_added++;
                            }
                        }
                        $deadline_formatted = $deadline_date->format('F j, Y');
                        
                        // Check if deadline has passed
                        $today = new DateTime();
                        $is_deadline_passed = $today > $deadline_date;
                        $days_remaining = $today->diff($deadline_date)->days;
                    }
                    
                    // Get document URLs
                    $documents = [
                        'Barangay Clearance' => $app['barangay_clearance'] ?? '',
                        '2x2 ID Photo' => $app['id_photo_2x2'] ?? '',
                        'Valid ID' => $app['valid_id'] ?? ''
                    ];
                    
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
                                    <span class="status-badge status-paid">
                                        <i class="fas fa-check-circle mr-1"></i>Payment Completed
                                    </span>
                                    <span class="status-badge <?php echo $stall_status === 'active' ? 'status-active' : 'status-pending'; ?> ml-2">
                                        <i class="fas <?php echo $status_icon; ?> mr-1"></i>
                                        <?php echo $status_badge_text; ?>
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
                                <div class="text-sm text-gray-500">Total Amount Paid</div>
                                <div class="text-2xl font-bold text-green-600">₱<?php echo number_format($total_amount_paid, 2); ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if ($stall_status !== 'active'): ?>
                        <!-- Single Action Card (No more redundancy) -->
                        <?php if (isset($is_deadline_passed) && $is_deadline_passed): ?>
                        <div class="critical-card mx-6 mt-6 mb-6">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                    <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">⚠️ Urgent: Sign Stall Rights Agreement</h3>
                                    <p class="text-red-600 mb-3">You missed the deadline to sign your Stall Rights Agreement. 
                                        <strong>Visit the Market Administration Office immediately</strong> to avoid 
                                        cancellation and forfeiture of fees.</p>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                        <div class="bg-red-50 p-3 rounded-lg border border-red-200">
                                            <div class="flex items-center mb-2">
                                                <i class="fas fa-calendar-alt text-red-600 mr-2"></i>
                                                <span class="font-medium text-red-800">Deadline Missed</span>
                                            </div>
                                            <div class="text-red-700 font-bold">
                                                <?php echo $deadline_formatted ?? 'Not set'; ?>
                                                <span class="text-sm font-normal ml-2">(Deadline passed!)</span>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-red-50 p-3 rounded-lg border border-red-200">
                                            <div class="flex items-center mb-2">
                                                <i class="fas fa-file-signature text-red-600 mr-2"></i>
                                                <span class="font-medium text-red-800">Required Documents</span>
                                            </div>
                                            <div class="text-red-700 text-sm">
                                                1. Original Valid ID<br>
                                                2. Payment Receipt (Ref: <?php echo $app['reference_number']; ?>)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="action-card mx-6 mt-6 mb-6">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                    <i class="fas fa-file-signature text-green-600 text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">Next Step: Sign Stall Rights Agreement</h3>
                                    <p class="text-green-600 mb-3">Visit the Market Administration Office to complete your application</p>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                        <div class="bg-green-50 p-3 rounded-lg border border-green-200">
                                            <div class="flex items-center mb-2">
                                                <i class="fas fa-calendar-alt text-green-600 mr-2"></i>
                                                <span class="font-medium text-green-800">Deadline to Sign</span>
                                            </div>
                                            <div class="text-green-700 font-bold">
                                                <?php echo $deadline_formatted ?? 'Not set'; ?>
                                                <?php if (isset($days_remaining)): ?>
                                                    <span class="text-sm font-normal ml-2">(<?php echo $days_remaining; ?> day<?php echo $days_remaining !== 1 ? 's' : ''; ?> remaining)</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-green-50 p-3 rounded-lg border border-green-200">
                                            <div class="flex items-center mb-2">
                                                <i class="fas fa-file-signature text-green-600 mr-2"></i>
                                                <span class="font-medium text-green-800">Required Documents</span>
                                            </div>
                                            <div class="text-green-700 text-sm">
                                                1. Original Valid ID<br>
                                                2. Payment Receipt (Ref: <?php echo $app['reference_number']; ?>)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

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
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="form-group">
                                            <label class="form-label">Payment Reference</label>
                                            <div class="form-value font-mono"><?php echo $app['reference_number']; ?></div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Payment Date</label>
                                            <div class="form-value"><?php echo $payment_date; ?></div>
                                        </div>
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
                                                    <div class="data-value text-blue-600 font-medium"><?php echo $app['class_name']; ?> Class</div>
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
                                                <div class="text-sm text-blue-600">Will start after stall activation</div>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-2xl font-bold text-blue-700">
                                                ₱<?php echo number_format($monthly_rent, 2); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">Per month</div>
                                        </div>
                                    </div>

                                    <!-- Payment Summary -->
                                    <div class="info-box border-l-4 border-green-500">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                                <i class="fas fa-receipt text-green-600"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium">Payment Summary</div>
                                                <div class="text-sm text-green-600">Completed payment details</div>
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
                                                    <div class="font-bold text-gray-900">Total Amount Paid</div>
                                                    <div class="text-lg font-bold text-green-600">
                                                        ₱<?php echo number_format($total_amount_paid, 2); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents Section -->
                        <div class="mt-8">
                            <h3 class="section-header text-lg font-semibold text-gray-800 mb-6">
                                <i class="fas fa-file-alt text-[var(--primary)] mr-2"></i> Uploaded Documents
                            </h3>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($documents as $label => $doc_path): ?>
                                    <?php if (!empty($doc_path)): ?>
                                        <?php
                                            $doc_url = getDocumentUrl($doc_path);
                                            $file_ext = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                                            $is_image = in_array($file_ext, ['jpg','jpeg','png','gif']);
                                            $file_name = basename($doc_path);
                                        ?>
                                        <div class="document-card" onclick="openModal('<?php echo $doc_url; ?>','<?php echo $label; ?>')">
                                            <div class="mb-4">
                                                <?php if ($is_image): ?>
                                                    <i class="fas fa-image text-blue-500 text-3xl"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file-pdf text-red-500 text-3xl"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm font-medium text-gray-900 truncate mb-1"><?php echo $label; ?></div>
                                            <div class="text-xs text-gray-500 truncate mb-3" title="<?php echo htmlspecialchars($file_name); ?>">
                                                <?php echo htmlspecialchars($file_name); ?>
                                            </div>
                                            <div class="mt-2">
                                                <span class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded-full font-medium">
                                                    <i class="fas fa-eye mr-1"></i>Click to view
                                                </span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="document-card opacity-50 cursor-not-allowed">
                                            <div class="mb-4">
                                                <i class="fas fa-times-circle text-gray-300 text-3xl"></i>
                                            </div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo $label; ?></div>
                                            <div class="text-xs text-gray-500 mt-1">Not uploaded</div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-6 border-t border-gray-200 bg-blue-50">
                        <h3 class="font-semibold text-gray-800 mb-4">Office Visit Instructions</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="flex items-center p-3 bg-white rounded-lg border border-gray-200">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-id-card text-blue-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-800">Step 1</div>
                                    <div class="text-xs text-blue-700">Present valid ID & receipt</div>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-3 bg-white rounded-lg border border-gray-200">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-file-signature text-blue-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-800">Step 2</div>
                                    <div class="text-xs text-blue-700">Sign Agreement</div>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-3 bg-white rounded-lg border border-gray-200">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-store text-blue-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-800">Step 3</div>
                                    <div class="text-xs text-blue-700">Get stall assignment</div>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-3 bg-white rounded-lg border border-gray-200">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-graduation-cap text-blue-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-800">Step 4</div>
                                    <div class="text-xs text-blue-700">Market orientation</div>
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
                    <li><a href="paying.php" class="hover:text-[#4a90e2] transition-colors">Pending Payments</a></li>
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
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-2 flex-shrink-0">
                            <i class="fas fa-clipboard-check text-green-600 text-sm"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700">Agreement Signing?</div>
                            <div class="text-xs text-gray-600">Visit Market Administration Office</div>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-2 flex-shrink-0">
                            <i class="fas fa-question-circle text-blue-600 text-sm"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700">General Questions</div>
                            <div class="text-xs text-gray-600">Email: support@goserveph.gov.ph</div>
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
                    <div class="text-sm font-medium text-gray-700 mb-1">What happens after I sign?</div>
                    <div class="text-xs text-gray-600">Your stall will be activated within 24 hours and you'll receive confirmation.</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-700 mb-1">Can I change stall location?</div>
                    <div class="text-xs text-gray-600">Changes may be requested at the Market Office subject to availability.</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-700 mb-1">When do I start paying rent?</div>
                    <div class="text-xs text-gray-600">Monthly rent starts after stall activation and signing of agreement.</div>
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
// Image Modal functionality
const modal = document.getElementById("imageModal");
const modalImg = document.getElementById("modalImage");
const captionText = document.getElementById("modalCaption");

function openModal(imageSrc, caption) {
    if (imageSrc) {
        modal.style.display = "block";
        modalImg.src = imageSrc;
        captionText.innerHTML = caption;
    } else {
        alert("No document available to view.");
    }
}

document.getElementsByClassName("modal-close")[0].onclick = () => modal.style.display = "none";
window.onclick = (event) => { if(event.target==modal) modal.style.display="none"; }
document.addEventListener('keydown', (event) => { 
    if(event.key==='Escape' && modal.style.display==='block') modal.style.display='none'; 
});

// Auto-refresh page every 5 minutes to check for status updates
setTimeout(() => {
    window.location.reload();
}, 300000);
</script>
</body>
</html>