<?php
// revenue2/citizen_dashboard/business/business_tax_payment/business_tax_payment.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Business Owner';

include_once '../../../db/Business/business_db.php';
$pdo = getDatabaseConnection();

if (!$pdo) {
    die("Database connection failed");
}

// Determine base URL based on whether we're on localhost or live domain
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
$base_url = $is_localhost 
    ? 'http://localhost/revenue2' 
    : 'https://revenuetreasury.goserveph.com';

// Business payment callback URL - dynamically generated
$business_callback_url = $base_url . '/citizen_dashboard/business/api/business_payment_api.php';

// Get current penalty rate from config
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
        
        return $penalty_config['penalty_percent'] ?? 1.00; // Default to 1%
    } catch(PDOException $e) {
        return 1.00; // Default fallback
    }
}

// Get current discount rate from config (for annual payments)
function getBusinessAnnualDiscountRate($pdo) {
    $current_month = date('n');
    $current_day = date('j');
    
    // Only eligible in January
    if ($current_month != 1 || $current_day > 31) {
        return 0.00;
    }
    
    try {
        $discount_stmt = $pdo->prepare("
            SELECT discount_percent 
            FROM business_discount_config 
            WHERE expiration_date IS NULL OR expiration_date >= CURDATE()
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $discount_stmt->execute();
        $discount_config = $discount_stmt->fetch(PDO::FETCH_ASSOC);
        
        return $discount_config['discount_percent'] ?? 5.00; // Default to 5% for January
    } catch(PDOException $e) {
        return $current_month == 1 ? 5.00 : 0.00;
    }
}

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

// Check if eligible for annual discount (like RPT system)
function isEligibleForAnnualDiscount($quarterly_taxes, $pdo) {
    $current_month = date('n');
    $current_day = date('j');
    
    // Only eligible in January
    if ($current_month != 1 || $current_day > 31) {
        return false;
    }
    
    // Check if all quarters are unpaid and no penalties
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

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

$current_year = date('Y');
$is_january = date('n') == 1;
$current_quarter = 'Q' . ceil(date('n') / 3);

// Get config values for display
$penalty_rate = getBusinessPenaltyRate($pdo);
$discount_rate = getBusinessAnnualDiscountRate($pdo);
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
        .status-badge { 
            display: inline-flex; 
            align-items: center; 
            padding: 0.4rem 0.8rem; 
            border-radius: 6px; 
            font-size: 0.8rem; 
            font-weight: 500; 
        }
        .status-paid { background-color: #d1fae5; color: #065f46; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        
        .card {
            background: white;
            border-radius: 0.625rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .section-header {
            border-left: 4px solid #4a90e2;
            padding-left: 1rem;
            margin-bottom: 1.25rem;
        }
        
        .payment-summary-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-left: 4px solid #4a90e2;
        }
        
        .glow-button {
            transition: all 0.3s ease;
        }
        .glow-button:hover {
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
            transform: translateY(-2px);
        }
        
        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include '../../../citizen_dashboard/navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Payment Success Message -->
        <?php if (isset($_GET['payment_success']) && $_GET['payment_success'] === 'true'): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                <div>
                    <h3 class="font-bold text-green-800 mb-1">Payment Successful!</h3>
                    <p class="text-green-700 text-sm">Your business tax payment has been processed. Your payment status will update shortly.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Back Button & Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <a href="../business_services.php" class="text-blue-600 hover:text-blue-800 inline-flex items-center text-sm mb-2">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Business Services
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800">Business Tax Payment</h1>
                    <p class="text-gray-600 text-sm">Manage and pay your business taxes online</p>
                </div>
                <div class="text-right">
                    <div class="flex items-center justify-end space-x-4">
                        <div class="text-right">
                            <p class="text-sm text-gray-500">Welcome, <?php echo htmlspecialchars($user_name); ?></p>
                            <p class="text-sm text-gray-500">Current: <?php echo $current_quarter; ?> <?php echo $current_year; ?></p>
                        </div>
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user text-blue-600"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Info Banner -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-xl p-5 mb-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <div class="flex items-center mb-2">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                                <i class="fas fa-info-circle text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-blue-800">Payment Information</h3>
                                <p class="text-blue-700">Important dates and reminders</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        <div class="flex items-center bg-white px-3 py-2 rounded-lg">
                            <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
                            <span class="font-medium">Due Dates:</span>
                            <span class="ml-2 text-gray-700">Q1 Mar 31 • Q2 Jun 30 • Q3 Sep 30 • Q4 Dec 31</span>
                        </div>
                        <div class="flex items-center bg-white px-3 py-2 rounded-lg">
                            <i class="fas fa-percent text-red-500 mr-2"></i>
                            <span class="font-medium">Late Payment:</span>
                            <span class="ml-2 text-gray-700"><?php echo $penalty_rate; ?>% monthly penalty applies</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_january && $discount_rate > 0): ?>
        <!-- January Discount Banner -->
        <div class="bg-gradient-to-r from-emerald-400 to-green-500 rounded-xl p-5 mb-6 shadow-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="bg-white p-3 rounded-xl mr-4">
                        <i class="fas fa-gift text-emerald-600 text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white mb-1">January Annual Payment Discount Available!</h3>
                        <p class="text-emerald-100">Get <span class="font-bold text-yellow-300"><?php echo $discount_rate; ?>% discount</span> when paying full annual tax in January.</p>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="bg-white/20 backdrop-blur-sm px-4 py-2 rounded-lg">
                        <p class="text-white text-sm">Valid until January 31</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Businesses Section -->
        <?php
        try {
            $query = "
                SELECT 
                    bp.id,
                    bp.business_permit_id,
                    bp.business_name,
                    bp.full_name as owner_name,
                    bp.business_type,
                    bp.tax_calculation_type,
                    bp.total_tax,
                    bp.status,
                    bp.business_barangay as barangay,
                    bp.business_city as city
                FROM business_permits bp
                WHERE bp.user_id = :user_id 
                AND bp.status IN ('Active', 'Approved')
                ORDER BY bp.created_at DESC
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($businesses)) {
                echo '
                <div class="card p-10 text-center max-w-lg mx-auto">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-store text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">No Active Businesses</h3>
                    <p class="text-gray-600 mb-8 text-sm leading-relaxed">You don\'t have any approved business permits yet. Apply for a business permit to start paying taxes online.</p>
                    <div class="space-y-3">
                        <a href="business_application_status/business_application_status.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center justify-center w-full glow-button">
                            <i class="fas fa-plus-circle mr-3"></i> Apply for Business Permit
                        </a>
                        <a href="../business_services.php" class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-6 py-3 rounded-lg font-medium inline-flex items-center justify-center w-full">
                            <i class="fas fa-question-circle mr-3"></i> Learn About Business Taxes
                        </a>
                    </div>
                </div>';
            } else {
                // Stats Summary
                $totalBusinesses = count($businesses);
                $totalAnnualTax = 0;
                
                foreach ($businesses as $business) {
                    $totalAnnualTax += $business['total_tax'];
                }
                
                echo '
                <!-- Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="card p-5 hover:shadow-lg transition-shadow duration-300">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-3 rounded-xl mr-4">
                                <i class="fas fa-store text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Businesses</p>
                                <p class="text-2xl font-bold text-gray-800">' . $totalBusinesses . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-5 hover:shadow-lg transition-shadow duration-300">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-br from-green-500 to-green-600 p-3 rounded-xl mr-4">
                                <i class="fas fa-file-invoice-dollar text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Total Annual Tax</p>
                                <p class="text-2xl font-bold text-gray-800">' . formatCurrency($totalAnnualTax) . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-5 hover:shadow-lg transition-shadow duration-300">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-br from-yellow-500 to-orange-500 p-3 rounded-xl mr-4">
                                <i class="fas fa-calendar-alt text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Current Quarter</p>
                                <p class="text-2xl font-bold text-gray-800">' . $current_quarter . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-5 hover:shadow-lg transition-shadow duration-300">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-br from-purple-500 to-purple-600 p-3 rounded-xl mr-4">
                                <i class="fas fa-credit-card text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Payment Status</p>
                                <p class="text-2xl font-bold text-gray-800">Online</p>
                            </div>
                        </div>
                    </div>
                </div>';
                
                echo '<div class="space-y-8">';
                
                foreach ($businesses as $business) {
                    $taxQuery = "
                        SELECT 
                            bqt.*,
                            bqt.penalty_amount as penalty_amount_db
                        FROM business_quarterly_taxes bqt
                        WHERE bqt.business_permit_id = :business_id
                        ORDER BY bqt.year ASC, 
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
                    
                    $businessPaid = 0;
                    $businessPenalty = 0;
                    $paidQuarters = 0;
                    $overdueQuarters = 0;
                    $pendingQuarters = 0;
                    
                    $hasUnpaidQuarters = false;
                    foreach ($quarterlyTaxes as $tax) {
                        if ($tax['payment_status'] != 'paid') {
                            $hasUnpaidQuarters = true;
                            break;
                        }
                    }
                    
                    foreach ($quarterlyTaxes as $tax) {
                        $status = $tax['actual_status'] ?? $tax['payment_status'];
                        $totalAmount = $tax['total_quarterly_tax'] + ($tax['penalty_amount'] ?? 0);
                        
                        if ($status == 'paid') {
                            $businessPaid += $totalAmount;
                            $paidQuarters++;
                        } else {
                            $businessPenalty += ($tax['penalty_amount'] ?? 0);
                            
                            if ($status == 'overdue') {
                                $overdueQuarters++;
                            } else {
                                $pendingQuarters++;
                            }
                        }
                    }
                    
                    $eligibleForDiscount = isEligibleForAnnualDiscount($quarterlyTaxes, $pdo);
                    $annualPaymentInfo = calculateAnnualTotal($quarterlyTaxes, $eligibleForDiscount ? $discount_rate : 0);
                    
                    echo '
                    <!-- Business Card -->
                    <div class="card overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <!-- Business Header -->
                        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-blue-50">
                            <div class="flex flex-col md:flex-row md:items-center justify-between">
                                <div class="mb-4 md:mb-0">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mr-4">
                                            <i class="fas fa-store text-white text-lg"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-bold text-gray-800">' . htmlspecialchars($business['business_name']) . '</h3>
                                            <div class="flex items-center gap-3 mt-2">
                                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                                    ' . htmlspecialchars($business['business_type']) . '
                                                </span>
                                                <span class="text-gray-600 text-sm">
                                                    ' . htmlspecialchars($business['business_permit_id']) . '
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-gray-600 text-sm mt-2">
                                        <i class="fas fa-map-marker-alt text-gray-400 mr-1 text-sm"></i>
                                        ' . htmlspecialchars($business['barangay']) . ', ' . htmlspecialchars($business['city']) . '
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500 font-medium">Annual Tax</p>
                                    <p class="text-2xl font-bold text-blue-600">' . formatCurrency($business['total_tax']) . '</p>
                                </div>
                            </div>
                        </div>';
                        
                        if ($hasUnpaidQuarters) {
                            echo '
                        <!-- Annual Payment Option (Display Only) -->
                        <div class="p-5 border-b border-gray-200 bg-gradient-to-r from-emerald-50 to-green-50">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                                <div class="mb-4 lg:mb-0">
                                    <div class="flex items-center mb-2">
                                        <span class="status-badge ' . ($eligibleForDiscount ? 'bg-gradient-to-r from-emerald-500 to-green-500 text-white border-0 shadow-md' : 'bg-gradient-to-r from-blue-500 to-blue-600 text-white border-0 shadow-md') . ' mr-3">
                                            <i class="fas ' . ($eligibleForDiscount ? 'fa-gift' : 'fa-calendar-check') . ' mr-1.5"></i>
                                            ' . ($eligibleForDiscount ? 'DISCOUNT AVAILABLE' : 'ANNUAL PAYMENT') . '
                                        </span>
                                        <h4 class="font-bold text-gray-800 text-base">' . ($eligibleForDiscount ? 'Save ' . $discount_rate . '% This Month' : 'Pay All Quarters Together') . '</h4>
                                    </div>
                                    <p class="text-gray-600 text-sm">
                                        ' . ($eligibleForDiscount 
                                            ? 'Valid until January 31. Pay your annual tax in January and save money!' 
                                            : 'Convenient single payment for all quarters. Avoid multiple transactions.') . '
                                    </p>
                                </div>
                                
                                <div class="lg:text-right">
                                    <div class="mb-3">
                                        <p class="text-2xl font-bold text-gray-900">' . formatCurrency($annualPaymentInfo['total_with_discount']) . '</p>';
                                        if ($annualPaymentInfo['has_discount']) {
                                            echo '
                                        <p class="text-sm text-gray-600 flex items-center justify-center lg:justify-end">
                                            <span class="line-through mr-3">' . formatCurrency($annualPaymentInfo['total_before_discount']) . '</span>
                                            <span class="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full font-medium">
                                                <i class="fas fa-piggy-bank mr-1"></i>Save ' . formatCurrency($annualPaymentInfo['discount_amount']) . '
                                            </span>
                                        </p>';
                                        }
                                        echo '
                                    </div>
                                    <button onclick="payAnnual(' . $business['id'] . ', ' . $annualPaymentInfo['total_with_discount'] . ', \'Annual Business Tax - ' . htmlspecialchars($business['business_name']) . '\', ' . ($eligibleForDiscount ? 'true' : 'false') . ', ' . $discount_rate . ', \'' . htmlspecialchars($business['business_permit_id']) . '\')" 
                                            class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-3 rounded-lg font-semibold opacity-75 cursor-not-allowed" disabled>
                                        <i class="fas fa-credit-card mr-3"></i> Coming Soon
                                    </button>
                                </div>
                            </div>
                        </div>';
                        }
                        
                        echo '
                        <!-- Quarterly Taxes Section -->
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-r from-indigo-100 to-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-calendar-check text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-800 text-lg">Quarterly Tax Payments</h4>
                                        <p class="text-gray-500 text-sm">Pay your taxes by quarter</p>
                                    </div>
                                </div>
                                <div class="text-xs font-medium">
                                    <span class="bg-green-100 text-green-800 px-3 py-1.5 rounded-full mr-2">' . $paidQuarters . ' paid</span>
                                    <span class="bg-yellow-100 text-yellow-800 px-3 py-1.5 rounded-full mr-2">' . $pendingQuarters . ' pending</span>';
                                    if ($overdueQuarters > 0) {
                                        echo '<span class="bg-red-100 text-red-800 px-3 py-1.5 rounded-full pulse">' . $overdueQuarters . ' overdue</span>';
                                    }
                                echo '
                                </div>
                            </div>';
                            
                            if (empty($quarterlyTaxes)) {
                                echo '
                            <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl p-8 text-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-file-invoice text-gray-400 text-2xl"></i>
                                </div>
                                <p class="text-gray-600 text-lg mb-2">No quarterly taxes generated yet</p>
                                <p class="text-gray-500 text-sm">Tax bills will be generated automatically</p>
                            </div>';
                            } else {
                                echo '
                            <!-- Quarterly Table -->
                            <div class="overflow-x-auto rounded-xl border border-gray-200 mb-8">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gradient-to-r from-gray-50 to-blue-50">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Quarter</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Due Date</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Tax Amount</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Penalty</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Total</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">';
                                    
                                    $quarter_totals = [
                                        'tax_amount' => 0,
                                        'penalty' => 0,
                                        'total' => 0
                                    ];
                                    
                                    foreach ($quarterlyTaxes as $tax) {
                                        $status = $tax['actual_status'] ?? $tax['payment_status'];
                                        $tax_amount = $tax['total_quarterly_tax'] ?? 0;
                                        $penaltyAmount = $tax['penalty_amount'] ?? 0;
                                        $totalAmount = $tax_amount + $penaltyAmount;
                                        $daysLate = $tax['days_late'] ?? 0;
                                        
                                        $dueDate = new DateTime($tax['due_date']);
                                        $isOverdue = $status == 'overdue';
                                        $isCurrentQuarter = (ceil(date('n') / 3) == (int)substr($tax['quarter'], 1) && $tax['year'] == date('Y'));
                                        
                                        // Update totals
                                        $quarter_totals['tax_amount'] += $tax_amount;
                                        $quarter_totals['penalty'] += $penaltyAmount;
                                        $quarter_totals['total'] += $totalAmount;
                                        
                                        echo '
                                        <tr class="hover:bg-blue-50 transition-colors duration-150">
                                            <td class="px-6 py-4">
                                                <div class="font-medium text-gray-900 text-base">' . $tax['quarter'] . ' ' . $tax['year'] . '</div>';
                                                if ($isCurrentQuarter) {
                                                    echo '<div class="text-xs font-medium bg-blue-100 text-blue-800 px-2 py-1 rounded inline-block mt-1">
                                                        <i class="fas fa-star mr-1"></i> Current Quarter
                                                    </div>';
                                                }
                                                echo '
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-medium text-gray-900">' . $dueDate->format('M d, Y') . '</div>';
                                                if ($daysLate > 0) {
                                                    echo '<div class="text-xs font-medium bg-red-100 text-red-800 px-2 py-1 rounded inline-block mt-1">
                                                        <i class="fas fa-clock mr-1"></i>' . $daysLate . ' days late
                                                    </div>';
                                                } elseif ($status == 'pending' && !$isOverdue) {
                                                    echo '<div class="text-xs font-medium text-gray-500 mt-1">
                                                        <i class="fas fa-hourglass-half mr-1"></i> Upcoming
                                                    </div>';
                                                }
                                                echo '
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-semibold text-gray-900 text-base">' . formatCurrency($tax_amount) . '</div>
                                            </td>
                                            <td class="px-6 py-4">';
                                                if ($penaltyAmount > 0) {
                                                    echo '<div class="font-bold text-red-600 text-base">' . formatCurrency($penaltyAmount) . '</div>';
                                                } else {
                                                    echo '<div class="text-gray-400">' . formatCurrency(0) . '</div>';
                                                }
                                                echo '
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-bold text-xl text-blue-700">' . formatCurrency($totalAmount) . '</div>
                                            </td>
                                            <td class="px-6 py-4">';
                                                
                                                if ($status == 'paid') {
                                                    echo '<span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200">
                                                        <i class="fas fa-check-circle mr-2"></i> Paid
                                                    </span>';
                                                } elseif ($status == 'overdue') {
                                                    echo '<span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-gradient-to-r from-red-100 to-pink-100 text-red-800 border border-red-200 pulse">
                                                        <i class="fas fa-exclamation-triangle mr-2"></i> Overdue
                                                    </span>';
                                                } else {
                                                    echo '<span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-gradient-to-r from-yellow-100 to-orange-100 text-yellow-800 border border-yellow-200">
                                                        <i class="fas fa-clock mr-2"></i> Pending
                                                    </span>';
                                                }
                                                
                                                echo '
                                            </td>
                                            <td class="px-6 py-4">';
                                                
                                                if ($status == 'paid') {
                                                    echo '
                                                    <button onclick="viewReceipt(\'' . ($tax['receipt_number'] ?? '') . '\')" 
                                                            class="bg-gradient-to-r from-green-500 to-green-600 text-white px-4 py-2.5 rounded-lg font-medium inline-flex items-center glow-button">
                                                        <i class="fas fa-receipt mr-2"></i> View Receipt
                                                    </button>';
                                                } else {
                                                    // Create purpose string
                                                    $purpose_text = 'Business Tax ' . $tax['quarter'] . ' ' . $tax['year'] . ' - ' . $business['business_name'];
                                                    $purpose_text = htmlspecialchars($purpose_text, ENT_QUOTES);
                                                    
                                                    // FIXED: Create a form to open payment in new tab
                                                    echo '
                                                    <form id="paymentForm_' . $tax['id'] . '" 
                                                          method="GET" 
                                                          action="../../digital/index.php" 
                                                          target="_blank" 
                                                          onsubmit="openPaymentWindow(this); return false;">
                                                        <input type="hidden" name="system" value="business">
                                                        <input type="hidden" name="ref" value="' . $tax['id'] . '">
                                                        <input type="hidden" name="amount" value="' . $totalAmount . '">
                                                        <input type="hidden" name="purpose" value="' . $purpose_text . '">
                                                        <input type="hidden" name="callback" value="' . $business_callback_url . '">
                                                        <input type="hidden" name="return_url" value="' . $base_url . '/citizen_dashboard/business/business_tax_payment/business_tax_payment.php?payment_success=true">
                                                        
                                                        <button type="submit" 
                                                                class="' . ($isOverdue ? 'bg-gradient-to-r from-red-500 to-pink-500 hover:from-red-600 hover:to-pink-600' : 'bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700') . ' text-white px-5 py-2.5 rounded-lg font-semibold glow-button inline-flex items-center transform transition-transform duration-200 hover:scale-105">
                                                            <i class="fas fa-credit-card mr-2"></i> Pay Now
                                                        </button>
                                                    </form>';
                                                }
                                                
                                                echo '
                                            </td>
                                        </tr>';
                                    }
                                    
                                    // Add footer with totals
                                    echo '
                                    </tbody>
                                    <tfoot class="bg-gradient-to-r from-gray-50 to-blue-50">
                                        <tr>
                                            <td colspan="2" class="px-6 py-5 text-right font-bold text-gray-700 text-lg">
                                                Totals:
                                            </td>
                                            <td class="px-6 py-5 font-bold text-gray-900 text-lg">
                                                ' . formatCurrency($quarter_totals['tax_amount']) . '
                                            </td>
                                            <td class="px-6 py-5 font-bold ' . ($quarter_totals['penalty'] > 0 ? 'text-red-600' : 'text-gray-900') . ' text-lg">
                                                ' . formatCurrency($quarter_totals['penalty']) . '
                                            </td>
                                            <td class="px-6 py-5 font-bold text-2xl text-blue-700">
                                                ' . formatCurrency($quarter_totals['total']) . '
                                            </td>
                                            <td colspan="2" class="px-6 py-5"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>';
                            
                            // Payment Summary
                            echo '
                            <!-- Payment Summary -->
                            <div class="payment-summary-card p-6 rounded-xl">
                                <h5 class="font-bold text-gray-800 mb-4 text-lg flex items-center">
                                    <i class="fas fa-chart-pie text-blue-600 mr-3"></i> Payment Summary
                                </h5>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                    <div class="text-center md:text-left">
                                        <div class="text-sm text-gray-600 font-medium mb-2">Total Annual Tax</div>
                                        <div class="text-2xl font-bold text-gray-900">' . formatCurrency($business['total_tax']) . '</div>
                                    </div>
                                    
                                    <div class="text-center md:text-left">
                                        <div class="text-sm text-gray-600 font-medium mb-2">Total Penalties</div>
                                        <div class="text-2xl font-bold ' . ($businessPenalty > 0 ? 'text-red-600' : 'text-gray-900') . '">
                                            ' . formatCurrency($businessPenalty) . '
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            ' . ($businessPenalty > 0 ? '<i class="fas fa-exclamation-circle mr-1"></i> ' . $penalty_rate . '% monthly penalty applied' : '<i class="fas fa-check-circle mr-1 text-green-500"></i> No penalties') . '
                                        </div>
                                    </div>
                                    
                                    <div class="text-center md:text-left">
                                        <div class="text-sm text-gray-600 font-medium mb-2">Payment Status</div>
                                        <div class="text-2xl font-bold ' . ($overdueQuarters > 0 ? 'text-red-600' : ($paidQuarters == 4 ? 'text-green-600' : 'text-yellow-600')) . '">
                                            ' . ($overdueQuarters > 0 ? 'Overdue' : ($paidQuarters == 4 ? 'Paid' : 'Pending')) . '
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <span class="font-medium">' . $paidQuarters . ' paid</span> • 
                                            <span class="font-medium">' . $pendingQuarters . ' pending</span>' . 
                                            ($overdueQuarters > 0 ? ' • <span class="font-medium text-red-600">' . $overdueQuarters . ' overdue</span>' : '') . '
                                        </div>
                                    </div>
                                    
                                    <div class="text-center md:text-left">
                                        <div class="text-sm text-gray-600 font-medium mb-2">Total Amount Due</div>
                                        <div class="text-3xl font-bold text-blue-700">
                                            ' . formatCurrency($business['total_tax'] + $businessPenalty) . '
                                        </div>
                                    </div>
                                </div>
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
            <div class="card p-8 text-center max-w-md mx-auto">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-red-800 mb-3">Service Unavailable</h3>
                <p class="text-gray-600 mb-6 text-sm">We\'re experiencing technical difficulties. Please try again in a few minutes.</p>
                <div class="space-y-3">
                    <button onclick="location.reload()" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 inline-flex items-center justify-center w-full glow-button">
                        <i class="fas fa-sync-alt mr-3"></i> Try Again
                    </button>
                    <a href="../business_services.php" class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-6 py-3 rounded-lg font-medium inline-flex items-center justify-center w-full">
                        <i class="fas fa-arrow-left mr-3"></i> Back to Services
                    </a>
                </div>
            </div>';
        }
        ?>
        
        <!-- Help Section -->
        <div class="mt-10 card p-8">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-question-circle text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800 text-xl">Need Assistance?</h3>
                    <p class="text-gray-600">Get help with your business tax payments</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="border border-gray-200 rounded-xl p-5 hover:shadow-lg transition-shadow duration-300">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-phone text-blue-600"></i>
                        </div>
                        <p class="font-semibold text-gray-800">Business Tax Division</p>
                    </div>
                    <p class="text-gray-600 mb-2">Hotline:</p>
                    <p class="text-blue-600 font-bold text-lg">(02) 8888-7777</p>
                    <p class="text-xs text-gray-500 mt-2">Weekdays, 8:00 AM - 5:00 PM</p>
                </div>
                
                <div class="border border-gray-200 rounded-xl p-5 hover:shadow-lg transition-shadow duration-300">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-gift text-green-600"></i>
                        </div>
                        <p class="font-semibold text-gray-800">Discount Policy</p>
                    </div>
                    <ul class="space-y-2">
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            <span><?php echo $discount_rate; ?>% discount for annual payment</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            <span>Valid only in January</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            <span>All quarters must be unpaid</span>
                        </li>
                    </ul>
                </div>
                
                <div class="border border-gray-200 rounded-xl p-5 hover:shadow-lg transition-shadow duration-300">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-file-alt text-purple-600"></i>
                        </div>
                        <p class="font-semibold text-gray-800">Quick Links</p>
                    </div>
                    <div class="space-y-2">
                        <a href="#" class="block text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-download mr-2"></i> Download Business Forms
                        </a>
                        <a href="#" class="block text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-book mr-2"></i> Tax Regulations
                        </a>
                        <a href="#" class="block text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-calculator mr-2"></i> Tax Calculator
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
// Function to open payment in new window
function openPaymentWindow(form) {
    // Open payment in new tab
    const paymentUrl = form.action + '?' + new URLSearchParams(new FormData(form)).toString();
    const paymentWindow = window.open(paymentUrl, 'paymentWindow', 'width=800,height=700,scrollbars=yes');
    
    // Check if payment window was closed
    const checkWindowClosed = setInterval(() => {
        if (paymentWindow.closed) {
            clearInterval(checkWindowClosed);
            // When payment window is closed, refresh the page to check for updates
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    }, 1000);
}

// Old makePayment function (keep for compatibility if needed elsewhere)
function makePayment(taxId, amount, purpose, hasPenalty = false) {
    console.log('Initiating payment for Business Tax ID:', taxId, 'Amount:', amount);
    
    const btn = event.target;
    
    // Show loading state
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    btn.disabled = true;
    
    // Build SIMPLIFIED payment parameters
    const params = new URLSearchParams();
    params.append('system', 'business');
    params.append('ref', taxId.toString());
    params.append('amount', amount.toString());
    params.append('purpose', purpose);
    params.append('callback', '<?php echo $business_callback_url; ?>');
    params.append('return_url', '<?php echo $base_url; ?>/citizen_dashboard/business/business_tax_payment/business_tax_payment.php?payment_success=true');
    
    // Open in new window
    const paymentUrl = '../../digital/index.php?' + params.toString();
    const paymentWindow = window.open(paymentUrl, 'paymentWindow', 'width=800,height=700,scrollbars=yes');
    
    // Check if payment window was closed
    const checkWindowClosed = setInterval(() => {
        if (paymentWindow.closed) {
            clearInterval(checkWindowClosed);
            // Restore button
            btn.innerHTML = originalText;
            btn.disabled = false;
            // Refresh page
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    }, 1000);
}

// View receipt function
function viewReceipt(receiptNumber) {
    alert('View receipt: ' + receiptNumber);
    // Implement receipt viewing logic here
}

// Pay annual function (coming soon)
function payAnnual(businessId, amount, purpose, hasDiscount, discountRate, permitId) {
    alert('Annual payment feature coming soon!');
}
</script>
</body>
</html>