<?php
// revenue2/citizen_dashboard/business/business_tax_payment/business_tax_payment.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

include_once '../../../db/Business/business_db.php';
$pdo = getDatabaseConnection();

if (!$pdo) {
    die("Database connection failed");
}

// Determine base URL
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
$base_url = $is_localhost 
    ? 'http://localhost/revenue2' 
    : 'https://revenuetreasury.goserveph.com';

$business_callback_url = $base_url . '/citizen_dashboard/business/api/business_payment_api.php';

// Function to get current penalty rate
function getBusinessPenaltyRate($pdo) {
    try {
        $penalty_stmt = $pdo->prepare("
            SELECT penalty_percent 
            FROM business_penalty_config 
            WHERE expiration_date IS NULL OR expiration_date >= CURDATE()
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $penalty_stmt->execute();
        $penalty_config = $penalty_stmt->fetch(PDO::FETCH_ASSOC);
        
        return $penalty_config['penalty_percent'] ?? 1.00;
    } catch(PDOException $e) {
        return 1.00;
    }
}

// Function to get current annual discount rate FROM DATABASE
function getBusinessAnnualDiscountRate($pdo) {
    $current_month = date('n');
    
    if ($current_month != 1) {
        return 0.00;
    }
    
    try {
        $discount_stmt = $pdo->prepare("
            SELECT discount_percent 
            FROM business_discount_config 
            WHERE (expiration_date IS NULL OR expiration_date >= CURDATE())
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $discount_stmt->execute();
        $discount_config = $discount_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($discount_config) {
            return (float)$discount_config['discount_percent'];
        }
        
        // Default to 5% if no active discount config found
        return 5.00;
    } catch(PDOException $e) {
        error_log("Discount config error: " . $e->getMessage());
        return $current_month == 1 ? 5.00 : 0.00;
    }
}

// Function to calculate penalties
function calculateBusinessPenalties($quarterly_taxes, $pdo) {
    $current_date = date('Y-m-d');
    $penalty_rate = getBusinessPenaltyRate($pdo);
    
    foreach ($quarterly_taxes as &$quarter) {
        if ($quarter['payment_status'] == 'paid') continue;
        
        $due_date = new DateTime($quarter['due_date']);
        $today = new DateTime($current_date);
        
        if ($today > $due_date) {
            $interval = $due_date->diff($today);
            $days_late = $interval->days;
            
            if ($days_late > 0) {
                $penalty_amount = $quarter['total_quarterly_tax'] * ($penalty_rate / 100) * ceil($days_late / 30);
                $penalty_amount = round($penalty_amount, 2);
                
                $quarter['penalty_amount'] = $penalty_amount;
                $quarter['days_late'] = $days_late;
                $quarter['actual_status'] = 'overdue';
                
                if (($quarter['penalty_amount_db'] ?? 0) != $penalty_amount) {
                    $update_stmt = $pdo->prepare("
                        UPDATE business_quarterly_taxes 
                        SET penalty_amount = ?, 
                            days_late = ?,
                            payment_status = 'overdue',
                            penalty_percent_used = ?
                        WHERE id = ?
                    ");
                    $update_stmt->execute([
                        $penalty_amount, 
                        $days_late, 
                        $penalty_rate,
                        $quarter['id']
                    ]);
                }
            }
        }
    }
    
    return $quarterly_taxes;
}

// Check eligibility for annual discount
function isEligibleForAnnualDiscount($quarterly_taxes) {
    $current_month = date('n');
    $current_day = date('j');
    
    if ($current_month != 1 || $current_day > 31) {
        return false;
    }
    
    foreach ($quarterly_taxes as $quarter) {
        if ($quarter['payment_status'] == 'paid') {
            return false;
        }
        
        if (($quarter['penalty_amount'] ?? 0) > 0) {
            return false;
        }
    }
    
    return true;
}

// Check if can pay annual (no quarters already paid)
function canPayAnnual($quarterly_taxes) {
    foreach ($quarterly_taxes as $quarter) {
        if ($quarter['payment_status'] == 'paid') {
            return false;
        }
    }
    return true;
}

// Calculate annual total
function calculateAnnualTotal($quarterly_taxes, $discount_percent = 0) {
    $total_annual_tax = 0;
    $total_penalty = 0;
    
    foreach ($quarterly_taxes as $quarter) {
        $total_annual_tax += $quarter['total_quarterly_tax'] ?? 0;
        $total_penalty += ($quarter['penalty_amount'] ?? 0);
    }
    
    $total_before_discount = $total_annual_tax + $total_penalty;
    
    if ($discount_percent > 0) {
        $discount_amount = $total_annual_tax * ($discount_percent / 100);
        $discounted_total = ($total_annual_tax - $discount_amount) + $total_penalty;
        
        return [
            'total_base_tax' => $total_annual_tax,
            'total_penalty' => $total_penalty,
            'total_before_discount' => $total_before_discount,
            'discount_percent' => $discount_percent,
            'discount_amount' => $discount_amount,
            'total_with_discount' => $discounted_total,
            'has_discount' => true
        ];
    } else {
        return [
            'total_base_tax' => $total_annual_tax,
            'total_penalty' => $total_penalty,
            'total_before_discount' => $total_before_discount,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'total_with_discount' => $total_before_discount,
            'has_discount' => false
        ];
    }
}

// Format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Get config values FROM DATABASE
$penalty_rate = getBusinessPenaltyRate($pdo);
$discount_rate = getBusinessAnnualDiscountRate($pdo);

$current_year = date('Y');
$is_january = date('n') == 1;
$current_quarter = 'Q' . ceil(date('n') / 3);

// Get base URL for background image
$scheme = 'http';
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $scheme = 'https';
} elseif (isset($_SERVER['REQUEST_SCHEME'])) {
    $scheme = $_SERVER['REQUEST_SCHEME'];
}

$base_url = $scheme . '://' . $_SERVER['HTTP_HOST'];
$bg_image_path = $base_url . '/revenue2/Login/images/gsmbg.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Tax Payment | GoServePH</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #9aa5b1;
            --accent: #10b981;
            --background: #fbfbfb;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
        }

        body {
            background: linear-gradient(135deg, rgba(240, 240, 240, 0.4) 0%, rgba(230, 230, 230, 0.4) 50%, rgba(220, 220, 220, 0.3) 100%);
            position: relative;
            overflow-x: hidden;
            min-height: 100vh;
            font-family: Inter, system-ui, sans-serif;
            font-size: 16px;
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

        .glass-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-radius: 16px;
        }

        .business-card {
            background: white;
            border: 1px solid #e5e5e5;
            border-radius: 0.75rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: all .25s ease;
        }
        
        .business-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(74, 144, 226, 0.15);
        }

        .section-header {
            border-left: 5px solid var(--primary);
            padding-left: 1rem;
        }

        .notification-slide {
            animation: slideIn .3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 500;
            line-height: 1;
        }
        
        .badge-primary {
            background-color: rgba(74, 144, 226, 0.1);
            color: var(--primary);
        }
        
        .badge-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .badge-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .badge-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1.2rem;
            border-radius: 7px;
            font-weight: 500;
            font-size: 0.95rem;
            line-height: 1.4;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            gap: 0.4rem;
        }
        
        .btn-sm {
            padding: 0.45rem 0.9rem;
            font-size: 0.85rem;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #3b82c8;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background-color: var(--accent);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #0da271;
            transform: translateY(-1px);
        }
        
        .btn-outline {
            background-color: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .btn-outline:hover {
            background-color: #f9fafb;
            transform: translateY(-1px);
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 7px;
        }
        
        .status-paid { background-color: var(--success); }
        .status-pending { background-color: var(--warning); }
        .status-overdue { background-color: var(--error); }

        .discount-tag {
            background: linear-gradient(135deg, var(--accent), #0da271);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .penalty-amount {
            color: var(--error);
            font-weight: 600;
            font-size: 1.05rem;
        }
        
        .total-amount {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.05rem;
        }

        .table-header {
            background-color: #f8fafc;
            font-weight: 600;
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .annual-payment-card {
            background: linear-gradient(135deg, #f0f9ff 0%, #f0fdf4 100%);
            border: 1px solid #bfdbfe;
            border-radius: 0.75rem;
        }
        
        /* Arrow button styling */
        .arrow-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            color: var(--primary);
            background: rgba(74, 144, 226, 0.1);
            border-radius: 11px;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid rgba(74, 144, 226, 0.2);
        }
        
        .arrow-button:hover {
            background: rgba(74, 144, 226, 0.2);
            transform: translateX(-2px);
            border-color: rgba(74, 144, 226, 0.3);
        }
        
        /* Paid indicator */
        .paid-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.8rem;
            background-color: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        /* Payment button */
        .pay-button {
            background: linear-gradient(135deg, var(--primary), #3b82c8);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .pay-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
        }
        
        .pay-button.success {
            background: linear-gradient(135deg, var(--accent), #0da271);
        }
        
        .pay-button.success:hover {
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
    </style>
</head>

<body class="flex flex-col min-h-screen">
<?php include '../../navbar.php'; ?>

<main class="container mx-auto px-6 py-10 flex-grow max-w-7xl">

    <!-- Page Header - Centered Layout -->
    <div class="glass-header p-7 mb-12">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
            <!-- Arrow button on left -->
            <a href="../business_services.php" class="arrow-button self-start">
                <i class="fas fa-arrow-left text-lg"></i>
            </a>
            
            <!-- Centered title -->
            <div class="text-center flex-1">
                <h1 class="text-3xl font-bold text-gray-900 mb-3">
                    Business Tax Payment
                </h1>
                <p class="text-gray-600 text-lg">
                    Manage and pay your business taxes online
                </p>
            </div>
            
            <!-- Empty div for balance -->
            <div class="w-10"></div>
        </div>
        
        <!-- Info Box -->
        <div class="mt-5 p-4 bg-blue-50 border border-blue-100 rounded-lg">
            <div class="flex items-center gap-2 mb-2">
                <i class="fas fa-info-circle text-blue-500"></i>
                <span class="font-semibold text-blue-800">Payment Information</span>
            </div>
            <p class="text-blue-700"><?php echo $penalty_rate; ?>% monthly penalty for late payments • <?php echo $discount_rate; ?>% discount for annual payments in January</p>
        </div>
        
        <div class="h-1.5 w-22 rounded-full mt-5 mx-auto" style="background-color: #4a90e2;"></div>
    </div>

    <!-- Payment Success Message -->
    <?php if (isset($_GET['payment_success']) && $_GET['payment_success'] === 'true'): ?>
    <div class="notification-slide mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-medium">Payment Successful!</span>
        </div>
        <p class="mt-1 text-sm">Your business tax payment has been processed successfully.</p>
    </div>
    <?php endif; ?>

    <!-- Businesses Section -->
    <?php
    try {
        // FIXED: Use correct field names from your database schema
        $query = "
            SELECT 
                bp.id,
                bp.applicant_id as business_permit_id,
                bp.business_name,
                bp.business_nature as business_type,
                bp.tax_calculation_type,
                bp.total_tax,
                bp.permit_status as status,
                bp.business_barangay as barangay,
                bp.business_city as city
            FROM business_permits bp
            WHERE bp.user_id = :user_id 
            AND bp.permit_status IN ('ACTIVE', 'APPROVED')
            ORDER BY bp.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($businesses)) {
            echo '
            <div class="business-card p-8 text-center">
                <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-store text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-3">No Businesses Found</h3>
                <p class="text-gray-600 mb-6">You don\'t have any active business permits yet.</p>
                <div class="flex gap-3 justify-center">
                    <a href="https://e-plms.goserveph.com" class="btn btn-primary">
                        <i class="fas fa-plus mr-1"></i> Apply for Business Permit
                    </a>
                    <a href="../business_services.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Services
                    </a>
                </div>
            </div>';
        } else {
            echo '<div class="space-y-7">';
            
            foreach ($businesses as $business) {
                // Get quarterly taxes
                $taxQuery = "
                    SELECT 
                        bqt.*,
                        bqt.penalty_amount as penalty_amount_db
                    FROM business_quarterly_taxes bqt
                    WHERE bqt.business_permit_id = :business_id
                    ORDER BY bqt.year DESC, 
                        CASE bqt.quarter 
                            WHEN 'Q1' THEN 1
                            WHEN 'Q2' THEN 2
                            WHEN 'Q3' THEN 3
                            WHEN 'Q4' THEN 4
                        END ASC
                ";
                
                $taxStmt = $pdo->prepare($taxQuery);
                $taxStmt->bindParam(':business_id', $business['id'], PDO::PARAM_INT);
                $taxStmt->execute();
                $quarterlyTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $quarterlyTaxes = calculateBusinessPenalties($quarterlyTaxes, $pdo);
                
                // Calculate summary
                $paidQuarters = 0;
                $overdueQuarters = 0;
                $pendingQuarters = 0;
                $totalPenalty = 0;
                $totalTax = 0;
                
                foreach ($quarterlyTaxes as $tax) {
                    $status = $tax['actual_status'] ?? $tax['payment_status'];
                    $totalTax += $tax['total_quarterly_tax'] ?? 0;
                    $totalPenalty += ($tax['penalty_amount'] ?? 0);
                    
                    if ($status == 'paid') {
                        $paidQuarters++;
                    } elseif ($status == 'overdue') {
                        $overdueQuarters++;
                    } else {
                        $pendingQuarters++;
                    }
                }
                
                $eligibleForDiscount = isEligibleForAnnualDiscount($quarterlyTaxes);
                $canPayAnnual = canPayAnnual($quarterlyTaxes);
                $annualPaymentInfo = calculateAnnualTotal($quarterlyTaxes, $eligibleForDiscount ? $discount_rate : 0);
                
                // Check if annual payment exists
                $annual_payment_query = "
                    SELECT 
                        ap.*,
                        ap.final_amount as total_to_pay
                    FROM annual_payments ap
                    WHERE ap.business_permit_id = :business_id 
                    AND ap.payment_year = :current_year
                    AND ap.status = 'active'
                    LIMIT 1
                ";
                
                $annual_stmt = $pdo->prepare($annual_payment_query);
                $annual_stmt->execute([
                    ':business_id' => $business['id'],
                    ':current_year' => $current_year
                ]);
                $annualPayment = $annual_stmt->fetch(PDO::FETCH_ASSOC);
                
                echo '
                <!-- Business Card -->
                <div class="business-card overflow-hidden">
                    <!-- Business Header -->
                    <div class="p-7 border-b border-gray-100">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <h3 class="text-2xl font-bold text-gray-900">' . htmlspecialchars($business['business_name']) . '</h3>
                                    <span class="badge badge-primary">
                                        <i class="fas fa-map-marker-alt text-xs mr-1"></i>
                                        ' . htmlspecialchars($business['barangay']) . ', ' . htmlspecialchars($business['city']) . '
                                    </span>
                                </div>
                                <div class="text-gray-600">
                                    <i class="fas fa-id-card text-sm mr-1"></i>
                                    Permit ID: ' . htmlspecialchars($business['business_permit_id']) . ' • ' . htmlspecialchars($business['business_type']) . '
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <div class="text-gray-500 mb-1">Annual Tax</div>
                                <div class="text-2xl font-bold" style="color: var(--primary)">' . formatCurrency($business['total_tax']) . '</div>
                            </div>
                        </div>
                        
                        <!-- Summary Stats -->
                        <div class="flex flex-wrap gap-5 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="status-indicator status-paid"></span>
                                <span class="text-gray-700">Paid:</span>
                                <span class="font-semibold">' . $paidQuarters . '</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="status-indicator status-pending"></span>
                                <span class="text-gray-700">Pending:</span>
                                <span class="font-semibold">' . $pendingQuarters . '</span>
                            </div>';
                            if ($overdueQuarters > 0) {
                                echo '
                            <div class="flex items-center gap-2">
                                <span class="status-indicator status-overdue"></span>
                                <span class="text-gray-700">Overdue:</span>
                                <span class="font-semibold">' . $overdueQuarters . '</span>
                            </div>';
                            }
                        echo '
                        </div>';
                        
                    echo '
                    </div>';
                    
                    // Annual Payment Option
                    if ($annualPayment && $canPayAnnual) {
                        $isRecommended = $eligibleForDiscount;
                        echo '
                    <div class="p-7 border-b border-gray-100 ' . ($isRecommended ? 'bg-green-50' : '') . '">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                            <div class="mb-4 lg:mb-0">
                                <div class="flex items-center mb-2">
                                    <h4 class="font-medium text-gray-900 text-lg">
                                        <i class="fas fa-calendar-check text-green-600 mr-2"></i>
                                        Annual Payment
                                    </h4>';
                                    if ($eligibleForDiscount) {
                                        echo '
                                    <span class="discount-tag ml-3">
                                        <i class="fas fa-gift mr-1"></i>Save ' . number_format($discount_rate, 2) . '%
                                    </span>';
                                    }
                                    echo '
                                </div>
                                <p class="text-sm text-gray-600">
                                    Pay all 4 quarters in one payment' . ($eligibleForDiscount ? ' with discount' : '') . '
                                </p>';
                                if ($eligibleForDiscount) {
                                    echo '<p class="text-xs text-green-600 mt-1">
                                        <i class="fas fa-calendar-alt mr-1"></i> Valid until January 31
                                    </p>';
                                }
                                echo '
                            </div>
                            
                            <div class="lg:text-right">
                                <div class="mb-3">
                                    <div class="text-2xl font-bold text-gray-900">' . formatCurrency($annualPayment['final_amount']) . '</div>';
                                    if ($annualPaymentInfo['has_discount']) {
                                        echo '
                                    <div class="text-sm text-gray-600 mt-1">
                                        <span class="line-through">' . formatCurrency($annualPaymentInfo['total_before_discount']) . '</span>
                                        <span class="text-green-700 font-medium ml-2">
                                            <i class="fas fa-piggy-bank mr-1"></i>Save ' . formatCurrency($annualPaymentInfo['discount_amount']) . '
                                        </span>
                                    </div>';
                                    }
                                    echo '
                                </div>';
                                
                                if ($annualPayment['payment_status'] == 'paid') {
                                    echo '
                                    <div class="paid-indicator">
                                        <i class="fas fa-check-circle"></i>
                                        Paid
                                    </div>';
                                } else {
                                    $purpose_text = 'BUSINESS ANNUAL ' . $current_year . ' - ' . $business['business_name'];
                                    $purpose_text = htmlspecialchars($purpose_text, ENT_QUOTES);
                                    
                                    echo '
                                    <form method="POST" 
                                          action="../../digital/index.php" 
                                          target="_blank">
                                        <input type="hidden" name="system" value="business">
                                        <input type="hidden" name="ref" value="ANNUAL-' . $annualPayment['reference_number'] . '">
                                        <input type="hidden" name="amount" value="' . $annualPayment['final_amount'] . '">
                                        <input type="hidden" name="purpose" value="' . $purpose_text . '">
                                        <input type="hidden" name="callback" value="' . $business_callback_url . '">
                                        <input type="hidden" name="business_permit_id" value="' . $business['id'] . '">
                                        
                                        <button type="submit" 
                                                class="pay-button success"' . ($canPayAnnual ? '' : 'disabled style="opacity:0.5;cursor:not-allowed;"') . '>
                                            <i class="fas fa-external-link-alt"></i> Pay Annual
                                        </button>
                                    </form>';
                                }
                                echo '
                            </div>
                        </div>
                    </div>';
                    }
                    
                    echo '
                    <!-- Quarterly Payments -->
                    <div class="p-7">
                        <h4 class="font-semibold text-gray-900 text-xl mb-5 section-header">Quarterly Payments</h4>';
                        
                        if (empty($quarterlyTaxes)) {
                            echo '
                        <div class="text-center py-10 text-gray-500">
                            <i class="fas fa-file-invoice mb-3 text-gray-300 text-3xl"></i>
                            <p class="text-lg">No quarterly taxes generated yet</p>
                        </div>';
                        } else {
                            echo '
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="w-full">
                                <thead>
                                    <tr class="table-header">
                                        <th class="py-3.5 px-5 text-left">Quarter</th>
                                        <th class="py-3.5 px-5 text-left">Due Date</th>
                                        <th class="py-3.5 px-5 text-left">Base Tax</th>
                                        <th class="py-3.5 px-5 text-left">Penalty</th>
                                        <th class="py-3.5 px-5 text-left">Total</th>
                                        <th class="py-3.5 px-5 text-left">Status</th>
                                        <th class="py-3.5 px-5 text-left">Action</th>
                                    </tr>
                                </thead>
                                <tbody>';
                                
                                foreach ($quarterlyTaxes as $tax) {
                                    $status = $tax['actual_status'] ?? $tax['payment_status'];
                                    $tax_amount = $tax['total_quarterly_tax'] ?? 0;
                                    $penaltyAmount = $tax['penalty_amount'] ?? 0;
                                    $totalAmount = $tax_amount + $penaltyAmount;
                                    $daysLate = $tax['days_late'] ?? 0;
                                    
                                    $dueDate = new DateTime($tax['due_date']);
                                    $isCurrentQuarter = (ceil(date('n') / 3) == (int)substr($tax['quarter'], 1) && $tax['year'] == date('Y'));
                                    
                                    echo '
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-4 px-5">
                                            <div class="font-medium">' . $tax['quarter'] . ' ' . $tax['year'] . '</div>';
                                            if ($isCurrentQuarter) {
                                                echo '<div class="text-sm text-blue-600 font-medium mt-0.5">Current Quarter</div>';
                                            }
                                            echo '
                                        </td>
                                        <td class="py-4 px-5">
                                            <div>' . $dueDate->format('M d, Y') . '</div>';
                                            if ($daysLate > 0) {
                                                echo '<div class="text-sm text-red-600 mt-0.5">' . $daysLate . ' days late</div>';
                                            }
                                            echo '
                                        </td>
                                        <td class="py-4 px-5 font-medium">' . formatCurrency($tax_amount) . '</td>
                                        <td class="py-4 px-5 penalty-amount">';
                                            if ($penaltyAmount > 0) {
                                                echo '+' . formatCurrency($penaltyAmount);
                                            } else {
                                                echo formatCurrency(0);
                                            }
                                        echo '</td>
                                        <td class="py-4 px-5 total-amount">' . formatCurrency($totalAmount) . '</td>
                                        <td class="py-4 px-5">';
                                            if ($status == 'paid') {
                                                echo '<span class="badge badge-success">
                                                    <i class="fas fa-check text-xs mr-1"></i> Paid
                                                </span>';
                                            } elseif ($status == 'overdue') {
                                                echo '<span class="badge badge-error">
                                                    <i class="fas fa-exclamation-triangle text-xs mr-1"></i> Overdue
                                                </span>';
                                            } else {
                                                echo '<span class="badge badge-warning">
                                                    <i class="fas fa-clock text-xs mr-1"></i> Pending
                                                </span>';
                                            }
                                            echo '
                                        </td>
                                        <td class="py-4 px-5">';
                                            if ($status == 'paid') {
                                                echo '
                                                <div class="paid-indicator">
                                                    <i class="fas fa-check-circle"></i>
                                                    Paid
                                                </div>';
                                            } else {
                                                $purpose_text = 'BUSINESS ' . $tax['quarter'] . ' ' . $tax['year'] . ' - ' . $business['business_name'];
                                                $purpose_text = htmlspecialchars($purpose_text, ENT_QUOTES);
                                                
                                                echo '
                                                <form class="inline-block" 
                                                      method="POST" 
                                                      action="../../digital/index.php" 
                                                      target="_blank">
                                                    <input type="hidden" name="system" value="business">
                                                    <input type="hidden" name="ref" value="' . $tax['id'] . '">
                                                    <input type="hidden" name="amount" value="' . $totalAmount . '">
                                                    <input type="hidden" name="purpose" value="' . $purpose_text . '">
                                                    <input type="hidden" name="callback" value="' . $business_callback_url . '">
                                                    
                                                    <button type="submit" 
                                                            class="pay-button btn-sm">
                                                        <i class="fas fa-external-link-alt"></i> Pay Now
                                                    </button>
                                                </form>';
                                            }
                                            echo '
                                        </td>
                                    </tr>';
                                }
                                
                                // Summary Row
                                echo '
                                    <tr class="font-semibold bg-gray-50">
                                        <td colspan="2" class="py-4 px-5">Total</td>
                                        <td class="py-4 px-5 font-bold">' . formatCurrency($totalTax) . '</td>
                                        <td class="py-4 px-5 penalty-amount font-bold">' . formatCurrency($totalPenalty) . '</td>
                                        <td class="py-4 px-5 total-amount font-bold" style="color: var(--primary)">' . formatCurrency($totalTax + $totalPenalty) . '</td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>';
                        }
                        echo '
                    </div>
                </div>';
            }
            
            echo '</div>';
        }
    } catch (PDOException $e) {
        echo '
        <div class="business-card p-7 text-center">
            <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-800 mb-3">Service Unavailable</h3>
            <p class="text-gray-600 mb-6">Unable to load business information</p>
            <div class="flex gap-3 justify-center">
                <button onclick="location.reload()" class="btn btn-primary">
                    Try Again
                </button>
                <a href="../business_services.php" class="btn btn-outline">
                    Back to Services
                </a>
            </div>
        </div>';
    }
    ?>

</main>

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
                    <li><a href="../business_services.php" class="hover:text-[#4a90e2] transition-colors">Services</a></li>
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">My Applications</a></li>
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Settings</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><i class="fas fa-phone mr-2 text-gray-400"></i> (02) 8123 4567</li>
                    <li><i class="fas fa-envelope mr-2 text-gray-400"></i> support@goserveph.gov.ph</li>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form[target="_blank"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                const originalHTML = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
                
                // Store original HTML for recovery
                submitBtn.setAttribute('data-original', originalHTML);
                
                // Re-enable after 5 seconds if still on page
                setTimeout(() => {
                    if (submitBtn) {
                        submitBtn.innerHTML = originalHTML;
                        submitBtn.disabled = false;
                    }
                }, 5000);
            }
        });
    });
});
</script>
</body>
</html>