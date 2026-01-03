<?php
// business_tax_payment.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Business Owner';

include_once '../../../db/Business/business_db.php';

function calculateBusinessPenalties($quarterly_taxes, $pdo) {
    $current_date = date('Y-m-d');
    $penalty_rate = 1.00;
    
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
            }
        }
    }
    
    return $quarterly_taxes;
}

function getBusinessDiscount($due_date_str) {
    $due_date = new DateTime($due_date_str);
    $current_date = new DateTime();
    
    if ($current_date < $due_date) {
        $days_before_due = $due_date->diff($current_date)->days;
        if ($days_before_due >= 15) {
            return 5.00; // 5% discount for early payment
        }
    }
    
    return 0.00;
}

function calculateAnnualTotal($quarterly_taxes) {
    $total_annual_tax = 0;
    $total_penalty = 0;
    $total_discount = 0;
    $has_discount = false;
    $discount_percent = 0;
    
    foreach ($quarterly_taxes as $quarter) {
        $quarter_total = $quarter['total_quarterly_tax'];
        $quarter_penalty = $quarter['penalty_amount'] ?? 0;
        
        // Check for early payment discount
        if ($quarter['payment_status'] != 'paid' && $quarter_penalty == 0) {
            $quarter_discount_percent = getBusinessDiscount($quarter['due_date']);
            if ($quarter_discount_percent > 0) {
                $quarter_discount = $quarter_total * ($quarter_discount_percent / 100);
                $quarter_total -= $quarter_discount;
                $total_discount += $quarter_discount;
                $has_discount = true;
                $discount_percent = max($discount_percent, $quarter_discount_percent);
            }
        }
        
        $total_annual_tax += $quarter_total;
        $total_penalty += $quarter_penalty;
    }
    
    $total_before_discount = $total_annual_tax + $total_discount + $total_penalty;
    $total_with_discount = $total_annual_tax + $total_penalty;
    
    return [
        'total_base_tax' => $total_annual_tax + $total_discount,
        'total_penalty' => $total_penalty,
        'total_before_discount' => $total_before_discount,
        'discount_percent' => $discount_percent,
        'discount_amount' => $total_discount,
        'total_with_discount' => $total_with_discount,
        'has_discount' => $has_discount
    ];
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

$current_year = date('Y');
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
        
        .status-badge { 
            display: inline-flex; 
            align-items: center; 
            padding: 0.25rem 0.6rem; 
            border-radius: 6px; 
            font-size: 0.7rem; 
            font-weight: 500; 
        }
        .status-paid { background-color: #d1fae5; color: #065f46; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        
        .business-badge {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
            display: inline-flex;
            align-items: center;
            padding: 0.1rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include '../../../citizen_dashboard/navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Back Button & Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <a href="../business_services.php" class="text-blue-600 hover:text-blue-800 inline-flex items-center text-sm mb-2">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Business Services
                    </a>
                    <h1 class="text-xl font-bold text-gray-800">Business Tax Payment</h1>
                    <p class="text-gray-600 text-sm">Pay your quarterly business taxes</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Welcome, <?php echo htmlspecialchars($user_name); ?></p>
                    <p class="text-sm text-gray-500">Current: <?php echo $current_quarter; ?> <?php echo $current_year; ?></p>
                </div>
            </div>
            
            <!-- Info Banner -->
            <div class="bg-blue-50 border border-blue-100 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                    <div>
                        <p class="text-blue-800 font-medium text-sm mb-2">Business Tax Information</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt text-blue-400 mr-2 text-sm"></i>
                                <span class="text-blue-700">Due Dates: Q1 Mar 31 • Q2 Jun 30 • Q3 Sep 30 • Q4 Dec 31</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-percent text-blue-400 mr-2 text-sm"></i>
                                <span class="text-blue-700">1% monthly penalty for late payments</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Early Payment Discount Banner -->
        <div class="bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <div class="bg-emerald-100 p-2 rounded-lg mr-3">
                    <i class="fas fa-gift text-emerald-600"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-emerald-800 text-sm">Early Payment Discount Available!</h3>
                    <p class="text-emerald-700 text-sm">Get <span class="font-bold">5% discount</span> when paying at least 15 days before the due date.</p>
                </div>
            </div>
        </div>

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
                <div class="card p-8 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-store text-gray-400 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">No Active Businesses</h3>
                    <p class="text-gray-600 mb-6 text-sm">You don\'t have any approved business permits yet.</p>
                    <a href="business_application_status/business_application_status.php" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-medium inline-flex items-center text-sm">
                        <i class="fas fa-plus-circle mr-2"></i> Apply for Business Permit
                    </a>
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="card p-4">
                        <div class="flex items-center">
                            <div class="bg-blue-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-store text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Businesses</p>
                                <p class="text-xl font-bold text-gray-800">' . $totalBusinesses . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-4">
                        <div class="flex items-center">
                            <div class="bg-green-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-file-invoice-dollar text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Total Annual Tax</p>
                                <p class="text-xl font-bold text-gray-800">' . formatCurrency($totalAnnualTax) . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-4">
                        <div class="flex items-center">
                            <div class="bg-yellow-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-calendar-alt text-yellow-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Current Quarter</p>
                                <p class="text-xl font-bold text-gray-800">' . $current_quarter . '</p>
                            </div>
                        </div>
                    </div>
                </div>';
                
                echo '<div class="space-y-5">';
                
                foreach ($businesses as $business) {
                    $taxQuery = "
                        SELECT 
                            bqt.*
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
                    
                    $annualPaymentInfo = calculateAnnualTotal($quarterlyTaxes);
                    
                    echo '
                    <!-- Business Card -->
                    <div class="card overflow-hidden">
                        <!-- Business Header -->
                        <div class="p-5 border-b border-gray-200 bg-gray-50">
                            <div class="flex flex-col md:flex-row md:items-center justify-between">
                                <div class="mb-3 md:mb-0">
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-briefcase text-blue-600"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-gray-800">' . htmlspecialchars($business['business_name']) . '</h3>
                                            <div class="flex items-center gap-2">
                                                <span class="business-badge">' . htmlspecialchars($business['business_type']) . '</span>
                                                <span class="text-gray-600 text-sm">' . htmlspecialchars($business['business_permit_id']) . '</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-gray-600 text-sm mt-1">
                                        ' . htmlspecialchars($business['barangay']) . ', ' . htmlspecialchars($business['city']) . '
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500">Annual Tax</p>
                                    <p class="text-lg font-bold text-blue-600">' . formatCurrency($business['total_tax']) . '</p>
                                </div>
                            </div>
                        </div>';
                        
                        if ($hasUnpaidQuarters) {
                            echo '
                        <!-- Annual Payment Option -->
                        <div class="p-4 border-b border-gray-200 bg-blue-50">
                            <div class="flex flex-col md:flex-row md:items-center justify-between">
                                <div class="mb-3 md:mb-0">
                                    <div class="flex items-center mb-1">
                                        <span class="status-badge ' . ($annualPaymentInfo['has_discount'] ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800') . ' mr-3">
                                            <i class="fas ' . ($annualPaymentInfo['has_discount'] ? 'fa-gift' : 'fa-calendar-check') . ' mr-1"></i>
                                            ' . ($annualPaymentInfo['has_discount'] ? 'DISCOUNT' : 'ANNUAL PAYMENT') . '
                                        </span>
                                        <h4 class="font-semibold text-gray-800 text-sm">Pay All Quarters at Once</h4>
                                    </div>
                                    <p class="text-gray-600 text-xs">
                                        ' . ($annualPaymentInfo['has_discount'] 
                                            ? 'Save ' . $annualPaymentInfo['discount_percent'] . '% with early payment' 
                                            : 'Convenient single payment for all quarters') . '
                                    </p>
                                </div>
                                
                                <div class="md:text-right">
                                    <div class="mb-2">
                                        <p class="text-lg font-bold text-gray-900">' . formatCurrency($annualPaymentInfo['total_with_discount']) . '</p>';
                                        if ($annualPaymentInfo['has_discount']) {
                                            echo '
                                        <p class="text-sm text-gray-600">
                                            <span class="line-through">' . formatCurrency($annualPaymentInfo['total_before_discount']) . '</span>
                                            <span class="ml-2 text-emerald-600 font-medium">
                                                <i class="fas fa-piggy-bank mr-1"></i>Save ' . formatCurrency($annualPaymentInfo['discount_amount']) . '
                                            </span>
                                        </p>';
                                        }
                                        echo '
                                    </div>
                                    <button onclick="payAllQuarters(' . $business['id'] . ', ' . $annualPaymentInfo['total_with_discount'] . ', \'Annual Business Tax - ' . htmlspecialchars($business['business_name']) . '\', ' . ($annualPaymentInfo['has_discount'] ? 'true' : 'false') . ', ' . $annualPaymentInfo['discount_percent'] . ', \'' . htmlspecialchars($business['business_permit_id']) . '\')" 
                                            class="' . ($annualPaymentInfo['has_discount'] ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-blue-600 hover:bg-blue-700') . ' text-white px-4 py-2 rounded-lg font-medium text-sm inline-flex items-center">
                                        <i class="fas fa-credit-card mr-2"></i> Pay Annually
                                    </button>
                                </div>
                            </div>
                        </div>';
                        }
                        
                        echo '
                        <!-- Quarterly Taxes Section -->
                        <div class="p-5">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-gray-800 text-sm section-header">Quarterly Tax Payments</h4>
                                <div class="text-xs text-gray-500">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded">' . $paidQuarters . ' paid</span>
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded mx-1">' . $pendingQuarters . ' pending</span>';
                                    if ($overdueQuarters > 0) {
                                        echo '<span class="bg-red-100 text-red-800 px-2 py-1 rounded">' . $overdueQuarters . ' overdue</span>';
                                    }
                                echo '
                                </div>
                            </div>';
                            
                            if (empty($quarterlyTaxes)) {
                                echo '
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                <p class="text-gray-600">No quarterly taxes generated yet.</p>
                            </div>';
                            } else {
                                echo '
                            <!-- Quarterly Table -->
                            <div class="overflow-x-auto mb-5">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quarter</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tax Amount</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penalty</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
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
                                        
                                        // Check for early payment discount
                                        $discountPercent = getBusinessDiscount($tax['due_date']);
                                        $hasDiscount = $discountPercent > 0 && $status == 'pending' && $penaltyAmount == 0;
                                        
                                        // Update totals
                                        $quarter_totals['tax_amount'] += $tax_amount;
                                        $quarter_totals['penalty'] += $penaltyAmount;
                                        $quarter_totals['total'] += $totalAmount;
                                        
                                        echo '
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-gray-900">' . $tax['quarter'] . ' ' . $tax['year'] . '</div>';
                                                if (ceil(date('n') / 3) == (int)substr($tax['quarter'], 1) && $tax['year'] == date('Y')) {
                                                    echo '<div class="text-xs text-blue-600 font-medium mt-1">Current</div>';
                                                }
                                                echo '
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="text-gray-900">' . $dueDate->format('M d, Y') . '</div>';
                                                if ($hasDiscount) {
                                                    echo '<div class="text-xs text-emerald-600 font-medium mt-1">5% discount available</div>';
                                                } elseif ($daysLate > 0) {
                                                    echo '<div class="text-xs text-red-600 font-medium mt-1">' . $daysLate . ' days late</div>';
                                                }
                                                echo '
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="font-semibold text-gray-900">' . formatCurrency($tax_amount) . '</div>
                                            </td>
                                            <td class="px-4 py-3">';
                                                if ($penaltyAmount > 0) {
                                                    echo '<div class="font-semibold text-red-600">' . formatCurrency($penaltyAmount) . '</div>';
                                                } else {
                                                    echo '<div class="text-gray-500">' . formatCurrency(0) . '</div>';
                                                }
                                                echo '
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="font-bold text-lg ' . ($hasDiscount ? 'text-emerald-700' : 'text-blue-700') . '">' . formatCurrency($totalAmount) . '</div>
                                            </td>
                                            <td class="px-4 py-3">';
                                                
                                                if ($status == 'paid') {
                                                    echo '<span class="status-badge status-paid">
                                                        <i class="fas fa-check mr-1.5"></i> Paid
                                                    </span>';
                                                } elseif ($status == 'overdue') {
                                                    echo '<span class="status-badge status-overdue">
                                                        <i class="fas fa-exclamation-triangle mr-1.5"></i> Overdue
                                                    </span>';
                                                } else {
                                                    echo '<span class="status-badge status-pending">
                                                        <i class="fas fa-clock mr-1.5"></i> Pending
                                                    </span>';
                                                }
                                                
                                                echo '
                                            </td>
                                            <td class="px-4 py-3">';
                                                
                                                if ($status == 'paid') {
                                                    echo '
                                                    <button class="bg-green-100 text-green-800 px-3 py-1.5 rounded text-sm font-medium cursor-not-allowed" disabled>
                                                        <i class="fas fa-check mr-1"></i> Paid
                                                    </button>';
                                                } else {
                                                    echo '
                                                    <button onclick="initiatePayment(' . $tax['id'] . ', \'' . $tax['quarter'] . '\', \'' . $tax['year'] . '\', ' . $totalAmount . ', \'Business Tax ' . $tax['quarter'] . ' ' . $tax['year'] . ' - ' . htmlspecialchars($business['business_name']) . '\', ' . ($hasDiscount ? 'true' : 'false') . ', \'' . htmlspecialchars($business['business_permit_id']) . '\')" 
                                                            class="' . ($isOverdue ? 'bg-red-600 hover:bg-red-700' : ($hasDiscount ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-blue-600 hover:bg-blue-700')) . ' text-white px-3 py-1.5 rounded text-sm font-medium inline-flex items-center">
                                                        <i class="fas fa-credit-card mr-1.5"></i> ' . ($hasDiscount ? 'Pay with Discount' : 'Pay Now') . '
                                                    </button>';
                                                }
                                                
                                                echo '
                                            </td>
                                        </tr>';
                                    }
                                    
                                    // Add footer with totals
                                    echo '
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="2" class="px-4 py-3 text-right font-bold text-gray-700">
                                                Totals:
                                            </td>
                                            <td class="px-4 py-3 font-bold text-gray-900">
                                                ' . formatCurrency($quarter_totals['tax_amount']) . '
                                            </td>
                                            <td class="px-4 py-3 font-bold ' . ($quarter_totals['penalty'] > 0 ? 'text-red-600' : 'text-gray-900') . '">
                                                ' . formatCurrency($quarter_totals['penalty']) . '
                                            </td>
                                            <td class="px-4 py-3 font-bold text-xl text-blue-700">
                                                ' . formatCurrency($quarter_totals['total']) . '
                                            </td>
                                            <td colspan="2" class="px-4 py-3"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>';
                            
                            // Payment Summary
                            echo '
                            <!-- Payment Summary -->
                            <div class="payment-summary-card p-4 rounded-lg">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <div class="text-sm text-gray-600">Total Annual Tax</div>
                                        <div class="text-lg font-bold text-gray-900">' . formatCurrency($business['total_tax']) . '</div>
                                    </div>
                                    
                                    <div>
                                        <div class="text-sm text-gray-600">Total Penalties</div>
                                        <div class="text-lg font-bold ' . ($businessPenalty > 0 ? 'text-red-600' : 'text-gray-900') . '">
                                            ' . formatCurrency($businessPenalty) . '
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            ' . ($businessPenalty > 0 ? '1% monthly penalty applied' : 'No penalties') . '
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="text-sm text-gray-600">Payment Status</div>
                                        <div class="text-lg font-bold ' . ($overdueQuarters > 0 ? 'text-red-600' : ($paidQuarters == 4 ? 'text-green-600' : 'text-yellow-600')) . '">
                                            ' . ($overdueQuarters > 0 ? 'Overdue' : ($paidQuarters == 4 ? 'Paid' : 'Pending')) . '
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            ' . $paidQuarters . ' paid, ' . $pendingQuarters . ' pending' . ($overdueQuarters > 0 ? ', ' . $overdueQuarters . ' overdue' : '') . '
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="text-sm text-gray-600">Total Amount Due</div>
                                        <div class="text-xl font-bold text-blue-700">
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
            <div class="card p-6 text-center">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <h3 class="font-semibold text-red-800 mb-2">Service Unavailable</h3>
                <p class="text-gray-600 text-sm mb-4">Please try again in a few minutes.</p>
                <button onclick="location.reload()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 inline-flex items-center text-sm">
                    <i class="fas fa-sync-alt mr-2"></i> Try Again
                </button>
            </div>';
        }
        ?>
        
        <!-- Help Section -->
        <div class="mt-6 card p-5">
            <h3 class="font-semibold text-gray-800 mb-3 flex items-center">
                <i class="fas fa-question-circle text-blue-500 mr-2"></i> Need Assistance?
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="border border-gray-200 rounded-lg p-3">
                    <p class="font-medium text-gray-800 mb-1">Payment Questions</p>
                    <p class="text-gray-600">Business Tax Division: (02) 8888-7777</p>
                    <p class="text-xs text-gray-500 mt-1">Weekdays, 8:00 AM - 5:00 PM</p>
                </div>
                <div class="border border-gray-200 rounded-lg p-3">
                    <p class="font-medium text-gray-800 mb-1">Payment Options</p>
                    <div class="text-gray-600 space-y-1">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2 text-xs"></i>
                            <span>Pay quarterly or annually</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2 text-xs"></i>
                            <span>5% discount for early payment</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function initiatePayment(taxId, quarter, year, amount, purpose, hasDiscount = false, businessPermitId = '') {
        const businessElement = document.querySelector('h3.font-bold.text-gray-800');
        const businessName = businessElement ? businessElement.textContent.trim() : 'Business';
        
        const paymentData = {
            amount: amount,
            purpose: purpose,
            tax_id: taxId,
            client_system: 'Business Tax System',
            client_reference: `BUS-TAX-${quarter}-${year}-${taxId}`,
            reference: businessName,
            description: `Business Tax Payment: ${quarter} ${year} - ${businessName}`,
            business_permit_id: businessPermitId
        };
        
        if (hasDiscount) {
            paymentData.discount_percent = 5.00;
        }
        
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        btn.disabled = true;
        
        setTimeout(() => {
            const urlParams = new URLSearchParams(paymentData);
            window.location.href = '../../digital/index.php?' + urlParams.toString();
        }, 400);
    }

    function payAllQuarters(businessId, totalAmount, purpose, hasDiscount, discountPercent = 0, businessPermitId = '') {
        const businessElement = document.querySelector('h3.font-bold.text-gray-800');
        const businessName = businessElement ? businessElement.textContent.trim() : 'Business';
        
        const paymentData = {
            amount: totalAmount,
            purpose: purpose,
            is_annual: true,
            client_system: 'Business Tax System',
            client_reference: `BUS-ANNUAL-${businessId}`,
            reference: businessName,
            description: `Annual Business Tax Payment - ${businessName}`,
            business_permit_id: businessPermitId
        };
        
        if (hasDiscount) {
            paymentData.discount_percent = discountPercent;
            paymentData.discount_amount = (totalAmount / (1 - discountPercent/100)) - totalAmount;
        }
        
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        btn.disabled = true;
        
        setTimeout(() => {
            const urlParams = new URLSearchParams(paymentData);
            window.location.href = '../../digital/index.php?' + urlParams.toString();
        }, 400);
    }
    </script>
</body>
</html>