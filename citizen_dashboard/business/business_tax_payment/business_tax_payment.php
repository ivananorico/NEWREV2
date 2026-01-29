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
        $total_annual_tax += $quarter['total_quarterly_tax'];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Tax Payment - LGU System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Clean, Simple Styles - Same as RPT */
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            position: relative;
            min-height: 100vh;
        }

        /* Animated background particles - same as login page */
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
            z-index: -2;
        }

        @keyframes backgroundFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(1deg); }
            66% { transform: translateY(10px) rotate(-1deg); }
        }

        /* Background image with subtle opacity - same as login page */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('/revenue2/Login/images/gsmbg.png') center/cover no-repeat;
            opacity: 0.08;
            pointer-events: none;
            z-index: -1;
            filter: blur(1px);
        }
        
        .simple-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-paid {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .status-overdue {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        
        /* Header box */
        .header-box {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
        }
        
        .info-box {
            background: #f8fafc;
            border-left: 3px solid #3b82f6;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 6px;
        }
        
        .tax-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .tax-table th {
            background: #f9fafb;
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .tax-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .tax-table tr:hover {
            background: #f9fafb;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .btn-primary:hover {
            background-color: #2563eb;
        }
        
        .btn-success {
            background-color: #10b981;
            color: white;
            border-color: #10b981;
        }
        
        .btn-success:hover {
            background-color: #059669;
        }
        
        .btn-secondary {
            background-color: #6b7280;
            color: white;
            border-color: #6b7280;
        }
        
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        
        .btn-disabled {
            background-color: #9ca3af;
            color: white;
            border-color: #9ca3af;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .section-title {
            color: #1f2937;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .payment-option {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .payment-option.recommended {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .discount-badge {
            background-color: #10b981;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }
        
        /* Footer styles */
        .footer-bg {
            background: white;
            border-top: 1px solid #e5e7eb;
        }
        
        .responsive-grid {
            display: grid;
            gap: 3rem;
        }
        
        @media (max-width: 768px) {
            .responsive-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Paid indicator style */
        .paid-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
            border-radius: 6px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include '../../navbar.php'; ?>
    
    <div class="max-w-6xl mx-auto px-4 py-6">
        <!-- Page Header with Simple Title Only -->
        <div class="header-box mb-6">
            <div class="flex items-center mb-4">
                <a href="../business_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Business Tax Payment</h1>
                    <p class="text-gray-600 mt-1">Online payment portal for your business taxes</p>
                </div>
            </div>
        </div>

        <!-- Payment Success Message -->
        <?php if (isset($_GET['payment_success']) && $_GET['payment_success'] === 'true'): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span class="font-medium">Payment Successful!</span>
            </div>
            <p class="mt-1 text-sm">Your business tax payment has been processed successfully.</p>
        </div>
        <?php endif; ?>

        <!-- Simplified Important Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <!-- Only keep the penalty info box -->
            <div class="info-box" style="background-color: #fffbeb; border-left-color: #f59e0b;">
                <div class="flex items-center mb-2">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                    <h3 class="font-medium text-gray-800">Important Notice</h3>
                </div>
                <p class="text-sm text-gray-700">
                    <strong><?php echo $penalty_rate; ?>% monthly penalty</strong> applies to late payments from the due date.
                    <br>
                    <strong><?php echo $discount_rate; ?>% discount</strong> available for annual payments made in January.
                </p>
            </div>
            
            <!-- Add a new box for payment security -->
            <div class="info-box">
                <div class="flex items-center mb-2">
                    <i class="fas fa-shield-alt text-blue-600 mr-2"></i>
                    <h3 class="font-medium text-gray-800">Secure Payment</h3>
                </div>
                <p class="text-sm text-gray-700">All transactions are secured with SSL encryption. Your payment information is protected.</p>
            </div>
        </div>

        <!-- Businesses Section -->
        <?php
        try {
            // FIXED: Use correct field names from your database schema
            $query = "
                SELECT 
                    bp.id,
                    bp.applicant_id as business_permit_id,  -- FIXED: Changed from business_permit_id to applicant_id
                    bp.business_name,
                    bp.business_nature as business_type,    -- FIXED: Changed from business_type to business_nature
                    bp.tax_calculation_type,
                    bp.total_tax,
                    bp.permit_status as status,             -- FIXED: Changed from status to permit_status
                    bp.business_barangay as barangay,
                    bp.business_city as city
                FROM business_permits bp
                WHERE bp.user_id = :user_id 
                AND bp.permit_status IN ('ACTIVE', 'APPROVED')  -- FIXED: Match database values
                ORDER BY bp.created_at DESC
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($businesses)) {
                echo '
                <div class="simple-card p-8 text-center">
                    <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-store text-gray-500 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">No Businesses Found</h3>
                    <p class="text-gray-600 mb-6">You don\'t have any active business permits yet.</p>
                    <div class="space-y-3">
                        <a href="https://e-plms.goserveph.com" class="btn btn-primary w-full">
                            <i class="fas fa-plus-circle mr-2"></i> Apply for Business Permit
                        </a>
                        <a href="../business_services.php" class="btn btn-secondary w-full">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Services
                        </a>
                    </div>
                </div>';
            } else {
                echo '<div class="space-y-6">';
                
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
                    <div class="simple-card">
                        <!-- Business Header -->
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="font-semibold text-gray-900">' . htmlspecialchars($business['business_name']) . '</span>
                                    </div>
                                    <div class="text-gray-600 text-sm">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        ' . htmlspecialchars($business['barangay']) . ', ' . htmlspecialchars($business['city']) . '
                                    </div>
                                    <div class="text-gray-600 text-sm mt-1">
                                        <i class="fas fa-id-card mr-1"></i>
                                        Permit ID: ' . htmlspecialchars($business['business_permit_id']) . ' • ' . htmlspecialchars($business['business_type']) . '
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600 mb-1">Annual Tax:</div>
                                    <div class="text-xl font-bold text-blue-700">' . formatCurrency($business['total_tax']) . '</div>
                                </div>
                            </div>
                        </div>';
                        
                        // Annual Payment Option
                        if ($annualPayment && $canPayAnnual) {
                            $isRecommended = $eligibleForDiscount;
                            echo '
                        <div class="p-6 border-b border-gray-100 ' . ($isRecommended ? 'bg-green-50' : '') . '">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                                <div class="mb-4 lg:mb-0">
                                    <div class="flex items-center mb-2">
                                        <h4 class="font-medium text-gray-900">
                                            <i class="fas fa-calendar-check text-green-600 mr-2"></i>
                                            Annual Payment
                                        </h4>';
                                        if ($eligibleForDiscount) {
                                            echo '
                                        <span class="discount-badge ml-3">
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
                                        <div class="text-xl font-bold text-gray-900">' . formatCurrency($annualPayment['final_amount']) . '</div>';
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
                                                    class="btn ' . ($canPayAnnual ? 'btn-success' : 'btn-disabled') . '" ' . ($canPayAnnual ? '' : 'disabled') . '>
                                                <i class="fas fa-external-link-alt mr-2"></i> Pay Annual
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
                        <div class="p-6">
                            <h4 class="section-title">
                                <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>
                                Quarterly Payments
                            </h4>
                            
                            <!-- Summary -->
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex space-x-4">
                                    <div class="text-center">
                                        <div class="text-xs text-gray-600">Paid</div>
                                        <div class="font-bold text-green-600">' . $paidQuarters . '</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-xs text-gray-600">Pending</div>
                                        <div class="font-bold text-yellow-600">' . $pendingQuarters . '</div>
                                    </div>';
                                    if ($overdueQuarters > 0) {
                                        echo '
                                    <div class="text-center">
                                        <div class="text-xs text-gray-600">Overdue</div>
                                        <div class="font-bold text-red-600">' . $overdueQuarters . '</div>
                                    </div>';
                                    }
                                    echo '
                                </div>
                            </div>';
                            
                            if (empty($quarterlyTaxes)) {
                                echo '
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-file-invoice text-2xl mb-2 text-gray-300"></i>
                                <p>No quarterly taxes generated yet</p>
                            </div>';
                            } else {
                                echo '
                            <div class="overflow-x-auto">
                                <table class="tax-table">
                                    <thead>
                                        <tr>
                                            <th>Quarter</th>
                                            <th>Due Date</th>
                                            <th>Tax Amount</th>
                                            <th>Penalty</th>
                                            <th>Total Due</th>
                                            <th>Status</th>
                                            <th>Action</th>
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
                                        <tr>
                                            <td>
                                                <div class="font-medium">' . $tax['quarter'] . ' ' . $tax['year'] . '</div>';
                                                if ($isCurrentQuarter) {
                                                    echo '<div class="text-xs text-blue-600 font-medium">
                                                        Current
                                                    </div>';
                                                }
                                                echo '
                                            </td>
                                            <td>
                                                <div>' . $dueDate->format('M d, Y') . '</div>';
                                                if ($daysLate > 0) {
                                                    echo '<div class="text-xs text-red-600 font-medium">
                                                        ' . $daysLate . ' days late
                                                    </div>';
                                                }
                                                echo '
                                            </td>
                                            <td>
                                                ' . formatCurrency($tax_amount) . '
                                            </td>
                                            <td>';
                                                if ($penaltyAmount > 0) {
                                                    echo '<span class="text-red-600 font-medium">' . formatCurrency($penaltyAmount) . '</span>';
                                                } else {
                                                    echo '<span class="text-gray-400">₱0.00</span>';
                                                }
                                                echo '
                                            </td>
                                            <td>
                                                <span class="font-bold">' . formatCurrency($totalAmount) . '</span>
                                            </td>
                                            <td>';
                                                if ($status == 'paid') {
                                                    echo '<span class="status-badge status-paid">
                                                        <i class="fas fa-check-circle mr-1"></i> Paid
                                                    </span>';
                                                } elseif ($status == 'overdue') {
                                                    echo '<span class="status-badge status-overdue">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                                                    </span>';
                                                } else {
                                                    echo '<span class="status-badge status-pending">
                                                        <i class="fas fa-clock mr-1"></i> Pending
                                                    </span>';
                                                }
                                                echo '
                                            </td>
                                            <td>';
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
                                                                class="btn btn-primary text-sm">
                                                            <i class="fas fa-external-link-alt mr-1"></i> Pay Now
                                                        </button>
                                                    </form>';
                                                }
                                                echo '
                                            </td>
                                        </tr>';
                                    }
                                    
                                    // Summary Row
                                    echo '
                                        <tr class="bg-gray-50 font-semibold">
                                            <td colspan="2" class="text-right">Totals:</td>
                                            <td>' . formatCurrency($totalTax) . '</td>
                                            <td class="' . ($totalPenalty > 0 ? 'text-red-600' : '') . '">' . formatCurrency($totalPenalty) . '</td>
                                            <td class="text-blue-700">' . formatCurrency($totalTax + $totalPenalty) . '</td>
                                            <td colspan="2"></td>
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
            <div class="simple-card p-8 text-center">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Service Unavailable</h3>
                <p class="text-gray-600 mb-6">We cannot load your business information right now. Please try again later.</p>
                <div class="space-y-3 max-w-sm mx-auto">
                    <button onclick="location.reload()" class="btn btn-primary w-full">
                        Try Again
                    </button>
                    <a href="../business_services.php" class="btn btn-secondary w-full">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Services
                    </a>
                </div>
            </div>';
        }
        ?>
    </div>

    <!-- Footer - Same as RPT -->
    <footer class="footer-bg mt-16">
        <div class="container mx-auto px-6 py-12 max-w-7xl">
            <div class="responsive-grid grid-cols-1 md:grid-cols-4 gap-12">
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
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                    submitBtn.disabled = true;
                    
                    setTimeout(() => {
                        submitBtn.innerHTML = submitBtn.getAttribute('data-original-text') || 'Pay Now';
                        submitBtn.disabled = false;
                    }, 3000);
                }
            });
        });
    });
    </script>
</body>
</html>