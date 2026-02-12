<?php
// revenue2/citizen_dashboard/market/market_application/approved.php
ob_start(); // MUST BE FIRST - prevents header errors

// Include navbar once - this handles session checking and provides build_url()
require_once '../../navbar.php';

// Now we can safely use session variables since navbar.php already checked
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Citizen';

// Include the database connection from market_db.php
include_once '../../../db/Market/market_db.php';

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Function to format date
function formatDate($date, $format = 'F j, Y') {
    return ($date && $date != '0000-00-00' && $date != '0000-00-00 00:00:00') ? date($format, strtotime($date)) : '-';
}

// Function to get document URL
function getDocumentUrl(string $dbPath): string
{
    $clean = str_replace(['../', './'], '', $dbPath);
    $clean = ltrim($clean, '/');
    if (strpos($clean, 'revenue2/') === 0) {
        $clean = substr($clean, strlen('revenue2/'));
    }
    return '/revenue2/' . $clean;
}

// Function to check if rent clearance exists for a year
function getMarketRentClearanceInfo($rent_stall_id, $rent_total_id, $year, $pdo) {
    try {
        $query = "
            SELECT mrc.*,
                   DATE_ADD(mrc.issue_date, INTERVAL 1 YEAR) as valid_until
            FROM market_rent_clearances mrc
            WHERE mrc.rent_stall_id = ? 
            AND mrc.rent_total_id = ?
            AND mrc.clearance_year = ?
            ORDER BY mrc.issue_date DESC
            LIMIT 1
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$rent_stall_id, $rent_total_id, $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return null;
    }
}

// Fetch user's approved applications
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
            rs.end_date as stall_end_date,
            rs.id as rent_stall_id,
            s.name as stall_name,
            s.class_id,
            sr.class_name,
            sr.price as class_price,
            m.name as market_name,
            rr.payment_date,
            rr.reference_number,
            rr.stall_rights_no,
            rt.id as rent_total_id,
            rt.monthly_rent as contract_monthly_rent,
            rt.start_date as contract_start,
            rt.end_date as contract_end,
            rt.status as contract_status
        FROM rental_registration rr
        JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN maps m ON rr.map_id = m.id
        LEFT JOIN rent_totals rt ON rr.id = rt.registration_id
        WHERE ro.user_id = ? 
          AND rr.application_status = 'approved'
        ORDER BY rr.payment_date DESC, rr.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = count($applications);
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Get the base URL for the background image using the build_url function from navbar.php
$bg_image_path = build_url('/revenue2/Login/images/gsmbg.png');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Applications - Market Rent Services - GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../images/GSM_logo.png">
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
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
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
            border-left: 5px solid var(--primary);
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

        .status-approved {
            background-color: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border-color: rgba(16, 185, 129, 0.2);
        }

        .status-overdue {
            background-color: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border-color: rgba(239, 68, 68, 0.2);
        }

        .status-pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border-color: rgba(245, 158, 11, 0.2);
        }

        .status-paid {
            background-color: rgba(59, 130, 246, 0.1);
            color: #1e40af;
            border-color: rgba(59, 130, 246, 0.2);
        }

        .status-active {
            background-color: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border-color: rgba(16, 185, 129, 0.2);
        }

        .status-inactive {
            background-color: rgba(156, 163, 175, 0.1);
            color: #374151;
            border-color: rgba(156, 163, 175, 0.2);
        }

        .info-box {
            background: linear-gradient(135deg, rgba(249, 250, 251, 0.9) 0%, rgba(243, 244, 246, 0.9) 100%);
            border: 1px solid rgba(229, 231, 235, 0.5);
            border-radius: 0.75rem;
            padding: 1.25rem;
            backdrop-filter: blur(10px);
        }

        /* Compact Clearance Card */
        .clearance-card-compact {
            background: #fff;
            border: 1px solid rgba(14, 165, 233, 0.2);
            border-radius: 6px;
            padding: 0.75rem;
            transition: all 0.2s ease;
            border-left: 3px solid #0ea5e9;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .clearance-card-compact:hover {
            background: #f0f9ff;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.1);
        }

        .download-btn-compact {
            background: #0ea5e9;
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        
        .download-btn-compact:hover {
            background: #0284c7;
            transform: translateY(-1px);
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
            background-color: #0ea5e9;
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
            color: #0ea5e9;
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
            width: 100%;
        }

        /* Monthly Rent Table Styles */
        .monthly-rent-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .monthly-rent-table th {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: white;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .monthly-rent-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
        }
        
        .monthly-rent-table tr:last-child td {
            border-bottom: none;
        }
        
        .monthly-rent-table tr:hover {
            background-color: #f9fafb;
        }
        
        .payment-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .payment-paid {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .payment-overdue {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .payment-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        /* Logout Modal Styles - Matching market_portal_services.php */
        .logout-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(74, 144, 226, 0.7);
            z-index: 1001;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }

        .logout-modal.active {
            display: flex;
        }

        .logout-modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 400px;
            width: 90%;
            animation: modalSlideIn 0.3s ease-out;
            border: 2px solid #4a90e2;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .logout-modal-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            margin: 0 auto 1rem;
            background-color: rgba(239, 68, 68, 0.1);
            border: 2px solid #ef4444;
        }

        .logout-modal-icon i {
            font-size: 1.5rem;
            color: #ef4444;
        }

        .logout-modal-title {
            text-align: center;
            font-size: 1.25rem;
            font-weight: 600;
            color: #4a90e2;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .logout-modal-message {
            text-align: center;
            color: #9aa5b1;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .logout-modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
        }

        .logout-modal-btn {
            padding: 0.625rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.875rem;
            min-width: 100px;
            letter-spacing: 0.3px;
        }

        .logout-modal-btn.cancel {
            background-color: #9aa5b1;
            color: white;
            border: 1px solid #9aa5b1;
        }

        .logout-modal-btn.cancel:hover {
            background-color: #7b8794;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(154, 165, 177, 0.3);
        }

        .logout-modal-btn.confirm {
            background-color: #4caf50;
            color: white;
            border: 1px solid #4caf50;
        }

        .logout-modal-btn.confirm:hover {
            background-color: #3d8b40;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(76, 175, 80, 0.3);
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <!-- Logout Confirmation Modal -->
    <div class="logout-modal" id="logoutModal">
        <div class="logout-modal-content">
            <div class="logout-modal-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <h3 class="logout-modal-title">Logout Confirmation</h3>
            <p class="logout-modal-message">Are you sure you want to logout from your account? You'll need to sign in again to access your dashboard.</p>
            <div class="logout-modal-actions">
                <button class="logout-modal-btn cancel" onclick="hideLogoutModal()">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button class="logout-modal-btn confirm" onclick="performLogout()">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </button>
            </div>
        </div>
    </div>

    <!-- Navbar is already included via require_once at the top -->
    <!-- No need to include it again here -->

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
                        <h1 class="text-2xl font-bold text-gray-900">Approved Applications</h1>
                        <p class="text-gray-600">Your approved market stall rental applications</p>
                    </div>
                </div>
                <?php if ($total_applications > 0): ?>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Active Stall<?php echo $total_applications > 1 ? 's' : ''; ?></div>
                    <div class="text-2xl font-bold text-green-600"><?php echo $total_applications; ?></div>
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
                        <div class="step-label completed">Interview</div>
                    </div>
                    
                    <div class="progress-step">
                        <div class="step-number completed">
                            <i class="fas fa-check text-xs"></i>
                        </div>
                        <div class="step-label completed">Payment</div>
                    </div>
                    
                    <div class="progress-step">
                        <div class="step-number active">
                            <i class="fas fa-check text-xs"></i>
                        </div>
                        <div class="step-label active">Approved</div>
                    </div>
                </div>
                
                <!-- Progress line -->
                <div class="relative">
                    <div class="progress-line"></div>
                    <div class="progress-line-fill"></div>
                </div>
                
                <!-- Current status -->
                <div class="flex items-center justify-between text-sm text-gray-600 mt-4">
                    <span>Current Status: <span class="font-bold text-green-600">Approved & Active</span></span>
                    <span>Contract Approved</span>
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
                    <i class="fas fa-check text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-3">No Approved Applications</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto">
                    You don't have any approved applications yet. Check your pending applications to track progress.
                </p>
                <div class="space-x-4">
                    <a href="pending.php" 
                       class="inline-flex items-center px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-clock mr-2"></i>Check Pending Applications
                    </a>
                    <a href="paid.php" 
                       class="inline-flex items-center px-5 py-2.5 bg-[var(--primary)] text-white rounded-lg hover:opacity-90 transition-colors">
                        <i class="fas fa-receipt mr-2"></i>Check Paid Applications
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Applications List -->
            <div class="space-y-8">
                <?php foreach ($applications as $app): ?>
                    <?php
                        $full_name = trim($app['first_name'] . ' ' . 
                            (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . 
                            $app['last_name'] . 
                            (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
                        
                        // Format dates
                        $birth_date = !empty($app['birth_date']) ? date('M j, Y', strtotime($app['birth_date'])) : 'N/A';
                        $payment_date = !empty($app['payment_date']) ? date('F j, Y g:i A', strtotime($app['payment_date'])) : 'N/A';
                        $start_date = !empty($app['start_date']) ? date('F j, Y', strtotime($app['start_date'])) : 'Not set';
                        $contract_start = !empty($app['contract_start']) ? date('F j, Y', strtotime($app['contract_start'])) : 'Not set';
                        $contract_end = !empty($app['contract_end']) ? date('F j, Y', strtotime($app['contract_end'])) : 'Not set';
                        
                        // Build full address
                        $address_parts = [];
                        if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                        if (!empty($app['street'])) $address_parts[] = $app['street'];
                        if (!empty($app['barangay'])) $address_parts[] = 'Brgy. ' . $app['barangay'];
                        if (!empty($app['city'])) $address_parts[] = $app['city'];
                        if (!empty($app['province'])) $address_parts[] = $app['province'];
                        if (!empty($app['zip_code'])) $address_parts[] = $app['zip_code'];
                        $full_address = implode(', ', $address_parts);
                        
                        // Calculate amounts
                        $stall_rights_amount = $app['stall_rights_amount'] ?? 0;
                        $security_bond = $app['security_bond'] ?? 0;
                        $monthly_rent = $app['monthly_rent'] ?? 0;
                        $total_amount_paid = $stall_rights_amount + $security_bond;
                        $contract_monthly_rent = $app['contract_monthly_rent'] ?? $monthly_rent;
                        
                        // Get monthly rent billing
                        $monthly_bills = [];
                        $total_penalty = 0;
                        $paid_count = 0;
                        $pending_count = 0;
                        $overdue_count = 0;
                        $total_amount_due = 0;
                        $billing_years = [];
                        
                        try {
                            if (!empty($app['rent_total_id'])) {
                                $billing_stmt = $pdo->prepare("
                                    SELECT 
                                        mb.*,
                                        DATE_FORMAT(mb.due_date, '%M %d, %Y') as formatted_due_date,
                                        DATE_FORMAT(mb.payment_date, '%M %d, %Y') as formatted_payment_date
                                    FROM monthly_rent_billing mb
                                    WHERE mb.rent_total_id = ?
                                    ORDER BY mb.billing_year, mb.billing_month
                                ");
                                $billing_stmt->execute([$app['rent_total_id']]);
                                $monthly_bills = $billing_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($monthly_bills as $bill) {
                                    $total_penalty += $bill['penalty_amount'] ?? 0;
                                    $total_amount_due += $bill['total_amount_due'] ?? 0;
                                    
                                    if ($bill['payment_status'] == 'paid') $paid_count++;
                                    elseif ($bill['payment_status'] == 'overdue') $overdue_count++;
                                    else $pending_count++;
                                    
                                    // Collect unique years
                                    $year = $bill['billing_year'];
                                    if (!in_array($year, $billing_years)) {
                                        $billing_years[] = $year;
                                    }
                                }
                            }
                        } catch(PDOException $e) {
                            // Continue without billing data
                        }
                        
                        // Get rent clearances for each year
                        $rent_clearances = [];
                        if (!empty($app['rent_stall_id']) && !empty($app['rent_total_id'])) {
                            foreach ($billing_years as $year) {
                                $clearance = getMarketRentClearanceInfo($app['rent_stall_id'], $app['rent_total_id'], $year, $pdo);
                                if ($clearance) {
                                    $rent_clearances[$year] = $clearance;
                                }
                            }
                        }
                        
                        // Stall status
                        $stall_status = $app['stall_status'] ?? 'pending';
                        $status_badge_class = 'status-active';
                        $status_badge_text = 'Active';
                        $status_icon = 'fa-store';
                        
                        if ($stall_status === 'inactive') {
                            $status_badge_class = 'status-inactive';
                            $status_badge_text = 'Inactive';
                            $status_icon = 'fa-store-slash';
                        } elseif ($stall_status === 'pending') {
                            $status_badge_class = 'status-pending';
                            $status_badge_text = 'Pending';
                            $status_icon = 'fa-clock';
                        } elseif ($stall_status === 'expired') {
                            $status_badge_class = 'status-inactive';
                            $status_badge_text = 'Expired';
                            $status_icon = 'fa-calendar-times';
                        } elseif ($stall_status === 'terminated') {
                            $status_badge_class = 'status-inactive';
                            $status_badge_text = 'Terminated';
                            $status_icon = 'fa-ban';
                        }
                        
                        // Month names for display
                        $month_names = [
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                        ];
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
                                        <span class="status-badge status-approved">
                                            <i class="fas fa-check-circle mr-1"></i>Approved
                                        </span>
                                        <span class="status-badge <?php echo $status_badge_class; ?> ml-2">
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
                                    <div class="text-sm text-gray-500">Payment Completed</div>
                                    <div class="font-medium text-gray-900">
                                        <?php echo $payment_date; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- COMPACT RENT CLEARANCE SECTION -->
                        <?php if (!empty($rent_clearances)): ?>
                        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50/30">
                            <h3 class="font-medium text-gray-900 mb-3 flex items-center text-sm">
                                <i class="fas fa-file-certificate text-blue-600 mr-2 text-sm"></i> Available Rent Clearance Certificates
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php foreach ($rent_clearances as $year => $clearance): 
                                    $valid_until = $clearance['valid_until'] ?? date('Y-12-31', strtotime("+1 year"));
                                    $is_valid = strtotime($valid_until) >= time();
                                ?>
                                    <div class="clearance-card-compact">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center space-x-2">
                                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-award text-blue-600 text-xs"></i>
                                                </div>
                                                <div>
                                                    <div class="text-xs font-medium text-gray-900"><?php echo $year; ?> Clearance</div>
                                                    <div class="text-[10px] text-gray-500 font-mono"><?php echo substr($clearance['certificate_number'], -8); ?></div>
                                                </div>
                                            </div>
                                            <div>
                                                <?php if ($is_valid): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                        <i class="fas fa-check mr-1 text-[10px]"></i>Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                        <i class="fas fa-exclamation mr-1 text-[10px]"></i>Expired
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center justify-between text-xs text-gray-600 mb-3">
                                            <div class="space-y-1">
                                                <div class="flex items-center">
                                                    <i class="fas fa-calendar-alt mr-1 text-[10px] text-gray-400"></i>
                                                    <span>Issued: <?php echo date('M j, Y', strtotime($clearance['issue_date'])); ?></span>
                                                </div>
                                                <div class="flex items-center">
                                                    <i class="fas fa-calendar-check mr-1 text-[10px] text-gray-400"></i>
                                                    <span>Valid: <?php echo date('M j, Y', strtotime($valid_until)); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center justify-between">
                                            <a href="market_rent_clearance_certificate.php?rent_stall_id=<?php echo $app['rent_stall_id']; ?>&rent_total_id=<?php echo $app['rent_total_id']; ?>&year=<?php echo $year; ?>" 
                                               target="_blank"
                                               class="download-btn-compact">
                                                <i class="fas fa-download mr-1 text-[10px]"></i> Download
                                            </a>
                                            <span class="text-[10px] text-gray-500">
                                                <i class="fas fa-external-link-alt mr-1"></i> New window
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

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
                                                <div class="form-value">
                                                    <?php 
                                                    if (!empty($app['gender'])) {
                                                        echo ucfirst($app['gender']);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </div>
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
                                                <div class="form-value"><?php echo !empty($app['telephone']) ? $app['telephone'] : 'N/A'; ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Complete Address</label>
                                            <div class="form-value"><?php echo htmlspecialchars($full_address); ?></div>
                                        </div>
                                        
                                        <?php if (!empty($app['emergency_name']) && !empty($app['emergency_contact'])): ?>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div class="form-group">
                                                <label class="form-label">Emergency Contact Person</label>
                                                <div class="form-value"><?php echo htmlspecialchars($app['emergency_name']); ?></div>
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Emergency Contact Number</label>
                                                <div class="form-value"><?php echo $app['emergency_contact']; ?></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Stall Information -->
                                <div>
                                    <h3 class="section-header text-lg font-semibold text-gray-800 mb-6">
                                        <i class="fas fa-store text-[var(--primary)] mr-2"></i> Stall Information
                                    </h3>
                                    
                                    <div class="space-y-6">
                                        <!-- Stall Information Card -->
                                        <div class="info-box">
                                            <h4 class="font-medium text-gray-800 mb-4">Stall Details</h4>
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
                                                        <div class="<?php echo empty($app['business_name']) ? 'data-empty' : 'data-value'; ?>">
                                                            <?php echo !empty($app['business_name']) ? htmlspecialchars($app['business_name']) : 'Not provided'; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="flex justify-between items-center">
                                                        <label class="form-label text-sm">Business Type:</label>
                                                        <div class="<?php echo empty($app['business_type']) ? 'data-empty' : 'data-value'; ?>">
                                                            <?php echo !empty($app['business_type']) ? htmlspecialchars($app['business_type']) : 'Not provided'; ?>
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
                                                        <div class="<?php echo empty($app['market_name']) ? 'data-empty' : 'data-value'; ?>">
                                                            <?php echo !empty($app['market_name']) ? htmlspecialchars($app['market_name']) : 'Not provided'; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="flex justify-between items-center">
                                                        <label class="form-label text-sm">Stall Rights No:</label>
                                                        <div class="data-value font-mono"><?php echo $app['stall_rights_no']; ?></div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="flex justify-between items-center">
                                                        <label class="form-label text-sm">Contract Period:</label>
                                                        <div class="data-value">
                                                            <?php echo formatDate($contract_start, 'M d, Y'); ?> - <?php echo formatDate($contract_end, 'M d, Y'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Contract Summary -->
                                        <div class="info-box border-l-4 border-blue-500">
                                            <div class="flex items-center mb-3">
                                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                                    <i class="fas fa-file-contract text-blue-600"></i>
                                                </div>
                                                <div>
                                                    <div class="font-medium">Contract Summary</div>
                                                    <div class="text-sm text-blue-600">Rental agreement details</div>
                                                </div>
                                            </div>
                                            <div class="space-y-3">
                                                <div class="flex justify-between items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">Contract Duration</div>
                                                        <div class="text-xs text-gray-500">
                                                            <?php 
                                                            $start = new DateTime($contract_start);
                                                            $end = new DateTime($contract_end);
                                                            $interval = $start->diff($end);
                                                            $years = $interval->y;
                                                            $months = $interval->m;
                                                            echo ($years > 0 ? $years . ' year' . ($years > 1 ? 's' : '') . ' ' : '') . 
                                                                 ($months > 0 ? $months . ' month' . ($months > 1 ? 's' : '') : '');
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">Monthly Rent</div>
                                                        <div class="text-xs text-gray-500">Payable monthly</div>
                                                    </div>
                                                    <div class="text-lg font-bold text-blue-700">
                                                        <?php echo formatCurrency($contract_monthly_rent); ?>
                                                    </div>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">Annual Rent</div>
                                                        <div class="text-xs text-gray-500">Yearly total</div>
                                                    </div>
                                                    <div class="text-sm font-bold text-gray-900">
                                                        <?php echo formatCurrency($contract_monthly_rent * 12); ?>
                                                    </div>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">Security Bond</div>
                                                        <div class="text-xs text-gray-500">Refundable</div>
                                                    </div>
                                                    <div class="text-sm font-bold text-gray-900">
                                                        <?php echo formatCurrency($security_bond); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MONTHLY RENT TABLE -->
                            <?php if (!empty($monthly_bills)): ?>
                                <div class="mt-8 pt-8 border-t border-gray-200">
                                    <h3 class="section-header text-lg font-semibold text-gray-800 mb-6">
                                        <i class="fas fa-calendar-alt text-[var(--primary)] mr-2"></i> Monthly Rent Billing (<?php echo $monthly_bills[0]['billing_year'] ?? date('Y'); ?>)
                                    </h3>
                                    
                                    <!-- Monthly Rent Table -->
                                    <div class="overflow-x-auto mb-6">
                                        <table class="monthly-rent-table">
                                            <thead>
                                                <tr>
                                                    <th>Month</th>
                                                    <th>Due Date</th>
                                                    <th>Base Rent</th>
                                                    <th>Penalty</th>
                                                    <th>Discount</th>
                                                    <th>Total Due</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($monthly_bills as $bill): 
                                                    $days_late = $bill['days_late'] ?? 0;
                                                    $penalty_amount = $bill['penalty_amount'] ?? 0;
                                                    $discount_amount = $bill['discount_amount'] ?? 0;
                                                    $base_rent = $bill['base_rent'] ?? 0;
                                                    $total_due = $bill['total_amount_due'] ?? 0;
                                                    $due_date = $bill['formatted_due_date'] ?? date('M d, Y', strtotime($bill['due_date']));
                                                    $month_name = $month_names[$bill['billing_month']] ?? $bill['billing_month'];
                                                ?>
                                                    <tr>
                                                        <td class="month-year-cell">
                                                            <?php echo $month_name; ?>
                                                            <div class="text-xs text-gray-500">
                                                                <?php echo $bill['billing_year']; ?>
                                                            </div>
                                                        </td>
                                                        <td class="due-date-cell">
                                                            <?php echo $due_date; ?>
                                                            <?php if ($days_late > 0): ?>
                                                                <div class="text-xs text-red-600 mt-1">
                                                                    (<?php echo $days_late; ?> days late)
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="amount-cell">
                                                            <?php echo formatCurrency($base_rent); ?>
                                                        </td>
                                                        <td class="penalty-cell">
                                                            <?php if ($penalty_amount > 0): ?>
                                                                <?php echo formatCurrency($penalty_amount); ?>
                                                            <?php else: ?>
                                                                ₱0.00
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="discount-cell">
                                                            <?php if ($discount_amount > 0): ?>
                                                                -<?php echo formatCurrency($discount_amount); ?>
                                                            <?php else: ?>
                                                                ₱0.00
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="total-cell">
                                                            <?php echo formatCurrency($total_due); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($bill['payment_status'] == 'paid'): ?>
                                                                <span class="payment-badge payment-paid">
                                                                    <i class="fas fa-check-circle mr-1"></i> Paid
                                                                </span>
                                                                <?php if ($bill['payment_date']): ?>
                                                                <div class="text-xs text-gray-500 mt-1">
                                                                    <?php echo date('M d', strtotime($bill['payment_date'])); ?>
                                                                </div>
                                                                <?php endif; ?>
                                                            <?php elseif ($bill['payment_status'] == 'overdue'): ?>
                                                                <span class="payment-badge payment-overdue">
                                                                    <i class="fas fa-exclamation-circle mr-1"></i> Overdue
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="payment-badge payment-pending">
                                                                    <i class="fas fa-clock mr-1"></i> Pending
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot class="bg-gray-50">
                                                <tr>
                                                    <td colspan="2" class="font-bold text-right text-gray-900 p-4">
                                                        Totals:
                                                    </td>
                                                    <td class="p-4 font-bold text-gray-900">
                                                        <?php echo formatCurrency(array_sum(array_column($monthly_bills, 'base_rent'))); ?>
                                                    </td>
                                                    <td class="p-4 font-bold <?php echo $total_penalty > 0 ? 'text-red-600' : 'text-gray-900'; ?>">
                                                        <?php echo formatCurrency($total_penalty); ?>
                                                    </td>
                                                    <td class="p-4 font-bold <?php echo array_sum(array_column($monthly_bills, 'discount_amount')) > 0 ? 'text-green-600' : 'text-gray-900'; ?>">
                                                        -<?php echo formatCurrency(array_sum(array_column($monthly_bills, 'discount_amount'))); ?>
                                                    </td>
                                                    <td class="p-4 font-bold text-xl text-blue-700">
                                                        <?php echo formatCurrency($total_amount_due); ?>
                                                    </td>
                                                    <td class="p-4">
                                                        <?php if ($overdue_count > 0): ?>
                                                            <span class="payment-badge payment-overdue">
                                                                <i class="fas fa-exclamation-circle mr-1"></i> Overdue
                                                            </span>
                                                        <?php elseif ($paid_count == count($monthly_bills)): ?>
                                                            <span class="payment-badge payment-paid">
                                                                <i class="fas fa-check-circle mr-1"></i> Fully Paid
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="payment-badge payment-pending">
                                                                <i class="fas fa-clock mr-1"></i> Pending
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <!-- Payment Summary -->
                                    <div class="info-box">
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                            <div>
                                                <div class="text-sm text-gray-600">Total Base Rent</div>
                                                <div class="text-xl font-bold text-gray-900">
                                                    <?php echo formatCurrency(array_sum(array_column($monthly_bills, 'base_rent'))); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">For <?php echo count($monthly_bills); ?> months</div>
                                            </div>
                                            
                                            <div>
                                                <div class="text-sm text-gray-600">Total Penalties</div>
                                                <div class="text-xl font-bold <?php echo $total_penalty > 0 ? 'text-red-600' : 'text-gray-900'; ?>">
                                                    <?php echo formatCurrency($total_penalty); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php if ($total_penalty > 0): ?>
                                                        Penalty applied to overdue payments
                                                    <?php else: ?>
                                                        No penalties
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <div class="text-sm text-gray-600">Payment Status</div>
                                                <div class="text-xl font-bold <?php echo $overdue_count > 0 ? 'text-red-600' : ($paid_count == count($monthly_bills) ? 'text-green-600' : 'text-yellow-600'); ?>">
                                                    <?php echo $overdue_count > 0 ? 'Overdue' : ($paid_count == count($monthly_bills) ? 'Paid' : 'Pending'); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo $paid_count; ?> paid, 
                                                    <?php echo $pending_count; ?> pending, 
                                                    <?php echo $overdue_count; ?> overdue
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <div class="text-sm text-gray-600">Total Amount Due</div>
                                                <div class="text-2xl font-bold text-blue-700">
                                                    <?php echo formatCurrency($total_amount_due); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Footer -->
                        <div class="p-6 bg-gray-50 border-t border-gray-200">
                            <div class="flex flex-col md:flex-row md:items-center justify-between">
                                <div class="mb-4 md:mb-0">
                                    <div class="font-medium text-gray-900">Stall Rental Agreement</div>
                                    <div class="text-sm text-gray-600">
                                        <?php if (!empty($rent_clearances)): ?>
                                            <span class="text-green-700 font-medium">
                                                <i class="fas fa-file-certificate mr-1"></i> Rent clearance certificate(s) available for download
                                            </span>
                                        <?php elseif ($paid_count == count($monthly_bills) && !empty($monthly_bills)): ?>
                                            <span class="text-green-700">
                                                <i class="fas fa-check-circle mr-1"></i> All rent payments are up to date. Rent clearance will be generated automatically.
                                            </span>
                                        <?php elseif ($overdue_count > 0): ?>
                                            <span class="text-red-700 font-medium">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> 
                                                <?php echo $overdue_count; ?> overdue payment<?php echo $overdue_count > 1 ? 's' : ''; ?>
                                            </span>
                                        <?php else: ?>
                                            <i class="fas fa-info-circle mr-1"></i> 
                                            Your stall rental contract is active. Monthly payments are due as per schedule.
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">
                                        Stall Rights No: <span class="font-mono font-medium text-gray-700"><?php echo $app['stall_rights_no']; ?></span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Renter Code: <span class="font-mono font-medium text-gray-700"><?php echo $app['renter_code']; ?></span>
                                    </div>
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
                                    <div class="text-gray-600">(02) 8123-4567</div>
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
                                    <div class="text-gray-600">Market Office, Public Market Building</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Consistent Footer -->
    <footer class="bg-white border-t border-gray-200 mt-16">
        <div class="container mx-auto px-6 py-12 max-w-7xl">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
                <!-- Brand -->
                <div class="col-span-1">
                    <div class="flex items-center mb-4 text-2xl font-bold">
                        <span style="color: #4a90e2;">Go</span><span style="color: #4caf50;">Serve</span><span style="color: #4a90e2;">PH</span>
                    </div>
                    <p class="text-gray-600 leading-relaxed">
                        The official digital gateway of your Local Government Unit, providing efficient and transparent government services.
                    </p>
                </div>
                
                <!-- Portal Links -->
                <div>
                    <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Portal</h4>
                    <ul class="space-y-3 text-gray-600">
                        <li><a href="../../citizen_dashboard.php" class="hover:text-[#4a90e2] transition-colors">Dashboard</a></li>
                        <li><a href="../market_services.php" class="hover:text-[#4a90e2] transition-colors">Market Services</a></li>
                        <li><a href="#" class="hover:text-[#4a90e2] transition-colors">My Rentals</a></li>
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
                    &copy; <?php echo date('Y'); ?> GoServePH Local Government Unit. Republic of the Philippines.
                </p>
            </div>
        </div>
    </footer>

    <!-- JavaScript for interactive features -->
    <script>
        // Logout Modal Functions
        const logoutModal = document.getElementById('logoutModal');
        
        function showLogoutModal() {
            if (document.getElementById('logoutModal')) {
                document.getElementById('logoutModal').classList.add('active');
            }
        }

        function hideLogoutModal() {
            if (document.getElementById('logoutModal')) {
                document.getElementById('logoutModal').classList.remove('active');
            }
        }

        function performLogout() {
            <?php 
            $logout_handler_url = build_url('/citizen_dashboard/logout_handler.php');
            echo "window.location.href = '$logout_handler_url';";
            ?>
        }

        // Close logout modal when clicking outside
        if (logoutModal) {
            logoutModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    hideLogoutModal();
                }
            });
        }

        // Close logout modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && logoutModal && logoutModal.classList.contains('active')) {
                hideLogoutModal();
            }
        });

        // Handle broken images with fallback
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('img').forEach(img => {
                img.addEventListener('error', function() {
                    this.src = 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80';
                    this.classList.add('object-cover');
                });
            });
        });
    </script>
    <?php ob_end_flush(); ?>
</body>
</html>