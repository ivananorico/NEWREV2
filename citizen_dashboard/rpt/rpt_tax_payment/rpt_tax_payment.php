<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Citizen';

include_once '../../../db/RPT/rpt_db.php';
$pdo = getDatabaseConnection();

if (!$pdo) {
    die("Database connection failed");
}

function calculatePenalties($quarterly_taxes, $pdo) {
    $current_date = date('Y-m-d');
    $penalty_rate = 2.00;
    
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
                        UPDATE quarterly_taxes 
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

function isEligibleForAnnualDiscount($quarterly_taxes, $pdo) {
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

function getDiscountPercentage($pdo) {
    $current_month = date('n');
    
    if ($current_month != 1) {
        return 0.00;
    }
    
    try {
        $discount_stmt = $pdo->prepare("
            SELECT discount_percent 
            FROM discount_configurations 
            WHERE status = 'active' 
            LIMIT 1
        ");
        $discount_stmt->execute();
        $discount = $discount_stmt->fetch(PDO::FETCH_ASSOC);
        
        return $discount['discount_percent'] ?? 10.00;
    } catch(PDOException $e) {
        return $current_month == 1 ? 10.00 : 0.00;
    }
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPT Tax Payment - LGU System</title>
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
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-paid { background-color: #dbeafe; color: #1e40af; }
        
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
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Back Button & Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 inline-flex items-center text-sm mb-2">
                        <i class="fas fa-arrow-left mr-2"></i> Back to RPT Services
                    </a>
                    <h1 class="text-xl font-bold text-gray-800">Real Property Tax Payment</h1>
                    <p class="text-gray-600 text-sm">Manage and pay your property taxes</p>
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
                        <p class="text-blue-800 font-medium text-sm mb-2">Payment Information</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt text-blue-400 mr-2 text-sm"></i>
                                <span class="text-blue-700">Due Dates: Q1 Mar 31 • Q2 Jun 30 • Q3 Sep 30 • Q4 Dec 31</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-blue-400 mr-2 text-sm"></i>
                                <span class="text-blue-700">2% monthly penalty for late payments</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_january): ?>
        <!-- January Discount Banner -->
        <div class="bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <div class="bg-emerald-100 p-2 rounded-lg mr-3">
                    <i class="fas fa-gift text-emerald-600"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-emerald-800 text-sm">January Early Payment Discount Available!</h3>
                    <p class="text-emerald-700 text-sm">Get <span class="font-bold"><?php echo getDiscountPercentage($pdo); ?>% discount</span> when paying full annual tax in January.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Properties Section -->
        <?php
        try {
            $query = "
                SELECT 
                    pr.id,
                    pr.reference_number,
                    pr.lot_location,
                    pr.barangay,
                    pr.district,
                    po.first_name,
                    po.last_name,
                    po.middle_name,
                    pr.status,
                    pt.total_annual_tax,
                    pt.id as property_total_id
                FROM property_registrations pr
                INNER JOIN property_owners po ON pr.owner_id = po.id
                INNER JOIN property_totals pt ON pr.id = pt.registration_id
                WHERE po.user_id = :user_id 
                AND pr.status = 'approved'
                ORDER BY pr.created_at DESC
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($properties)) {
                echo '
                <div class="card p-8 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-home text-gray-400 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">No Properties Registered</h3>
                    <p class="text-gray-600 mb-6 text-sm">You don\'t have any approved properties yet.</p>
                    <a href="rpt_registration/rpt_registration.php" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-medium inline-flex items-center text-sm">
                        <i class="fas fa-plus-circle mr-2"></i> Register New Property
                    </a>
                </div>';
            } else {
                // Stats Summary
                $totalProperties = count($properties);
                $totalAnnualTax = 0;
                
                foreach ($properties as $property) {
                    $totalAnnualTax += $property['total_annual_tax'];
                }
                
                echo '
                <!-- Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="card p-4">
                        <div class="flex items-center">
                            <div class="bg-blue-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-home text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Properties</p>
                                <p class="text-xl font-bold text-gray-800">' . $totalProperties . '</p>
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
                
                foreach ($properties as $property) {
                    $taxQuery = "
                        SELECT 
                            qt.*,
                            qt.penalty_amount as penalty_amount_db
                        FROM quarterly_taxes qt
                        WHERE qt.property_total_id = :property_total_id
                        ORDER BY qt.year ASC, 
                            CASE qt.quarter 
                                WHEN 'Q1' THEN 1
                                WHEN 'Q2' THEN 2
                                WHEN 'Q3' THEN 3
                                WHEN 'Q4' THEN 4
                            END ASC
                    ";
                    
                    $taxStmt = $pdo->prepare($taxQuery);
                    $taxStmt->bindParam(':property_total_id', $property['property_total_id'], PDO::PARAM_INT);
                    $taxStmt->execute();
                    $quarterlyTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $quarterlyTaxes = calculatePenalties($quarterlyTaxes, $pdo);
                    
                    $propertyPaid = 0;
                    $propertyPenalty = 0;
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
                            $propertyPaid += $totalAmount;
                            $paidQuarters++;
                        } else {
                            $propertyPenalty += ($tax['penalty_amount'] ?? 0);
                            
                            if ($status == 'overdue') {
                                $overdueQuarters++;
                            } else {
                                $pendingQuarters++;
                            }
                        }
                    }
                    
                    $fullName = trim($property['first_name'] . ' ' . (!empty($property['middle_name']) ? substr($property['middle_name'], 0, 1) . '. ' : '') . $property['last_name']);
                    
                    $eligibleForDiscount = isEligibleForAnnualDiscount($quarterlyTaxes, $pdo);
                    $discountPercent = getDiscountPercentage($pdo);
                    $annualPaymentInfo = calculateAnnualTotal($quarterlyTaxes, $eligibleForDiscount ? $discountPercent : 0);
                    
                    echo '
                    <!-- Property Card -->
                    <div class="card overflow-hidden">
                        <!-- Property Header -->
                        <div class="p-5 border-b border-gray-200 bg-gray-50">
                            <div class="flex flex-col md:flex-row md:items-center justify-between">
                                <div class="mb-3 md:mb-0">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-home text-blue-600"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-gray-800">' . htmlspecialchars($property['reference_number']) . '</h3>
                                            <p class="text-gray-600 text-sm">
                                                ' . htmlspecialchars($property['lot_location']) . ', ' . htmlspecialchars($property['barangay']) . '
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500">Annual Tax</p>
                                    <p class="text-lg font-bold text-blue-600">' . formatCurrency($property['total_annual_tax']) . '</p>
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
                                        <span class="status-badge ' . ($eligibleForDiscount ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-blue-100 text-blue-800 border border-blue-200') . ' mr-3">
                                            <i class="fas ' . ($eligibleForDiscount ? 'fa-gift' : 'fa-calendar-check') . ' mr-1"></i>
                                            ' . ($eligibleForDiscount ? 'DISCOUNT' : 'ANNUAL PAYMENT') . '
                                        </span>
                                        <h4 class="font-semibold text-gray-800 text-sm">' . ($eligibleForDiscount ? 'Save ' . $discountPercent . '% This Month' : 'Pay All Quarters Together') . '</h4>
                                    </div>
                                    <p class="text-gray-600 text-xs">
                                        ' . ($eligibleForDiscount 
                                            ? 'Valid until January 31' 
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
                                    <button onclick="payAnnual(' . $property['property_total_id'] . ', ' . $annualPaymentInfo['total_with_discount'] . ', \'Annual RPT Payment - ' . htmlspecialchars($property['reference_number']) . '\', ' . ($annualPaymentInfo['has_discount'] ? 'true' : 'false') . ', ' . $discountPercent . ')" 
                                            class="' . ($eligibleForDiscount ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-blue-600 hover:bg-blue-700') . ' text-white px-4 py-2 rounded-lg font-medium text-sm inline-flex items-center">
                                        <i class="fas fa-credit-card mr-2"></i> ' . ($eligibleForDiscount ? 'Pay with Discount' : 'Pay Annually') . '
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
                                                if ($daysLate > 0) {
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
                                                <div class="font-bold text-lg text-blue-700">' . formatCurrency($totalAmount) . '</div>
                                            </td>
                                            <td class="px-4 py-3">';
                                                
                                                if ($status == 'paid') {
                                                    echo '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <i class="fas fa-check mr-1.5"></i> Paid
                                                    </span>';
                                                } elseif ($status == 'overdue') {
                                                    echo '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        <i class="fas fa-exclamation-triangle mr-1.5"></i> Overdue
                                                    </span>';
                                                } else {
                                                    echo '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
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
                                                    <button onclick="initiatePayment(' . $tax['id'] . ', \'' . $tax['quarter'] . '\', \'' . $tax['year'] . '\', ' . $totalAmount . ', \'RPT ' . $tax['quarter'] . ' ' . $tax['year'] . ' - ' . htmlspecialchars($property['reference_number']) . '\')" 
                                                            class="' . ($isOverdue ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700') . ' text-white px-3 py-1.5 rounded text-sm font-medium inline-flex items-center">
                                                        <i class="fas fa-credit-card mr-1.5"></i> Pay
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
                                        <div class="text-lg font-bold text-gray-900">' . formatCurrency($property['total_annual_tax']) . '</div>
                                    </div>
                                    
                                    <div>
                                        <div class="text-sm text-gray-600">Total Penalties</div>
                                        <div class="text-lg font-bold ' . ($propertyPenalty > 0 ? 'text-red-600' : 'text-gray-900') . '">
                                            ' . formatCurrency($propertyPenalty) . '
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            ' . ($propertyPenalty > 0 ? '2% monthly penalty applied' : 'No penalties') . '
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
                                            ' . formatCurrency($property['total_annual_tax'] + $propertyPenalty) . '
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
                    <p class="text-gray-600">Treasury Office: (02) 8888-9999</p>
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
                            <span>January discount available</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function initiatePayment(taxId, quarter, year, amount, purpose) {
        const propertyElement = document.querySelector('h3.font-bold.text-gray-800');
        const propertyRef = propertyElement ? propertyElement.textContent.trim() : 'RPT-PROPERTY';
        
        const paymentData = {
            amount: amount,
            purpose: purpose,
            tax_id: taxId,
            client_system: 'RPT System',
            client_reference: `TAX-${quarter}-${year}-${taxId}`,
            reference: propertyRef,
            description: `RPT Tax Payment: ${quarter} ${year} - ${propertyRef}`
        };
        
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        btn.disabled = true;
        
        setTimeout(() => {
            const urlParams = new URLSearchParams(paymentData);
            window.location.href = '../../digital/index.php?' + urlParams.toString();
        }, 400);
    }

    function payAnnual(propertyTotalId, totalAmount, purpose, hasDiscount, discountPercent = 0) {
        const propertyElement = document.querySelector('h3.font-bold.text-gray-800');
        const propertyRef = propertyElement ? propertyElement.textContent.trim() : 'RPT-PROPERTY';
        
        const paymentData = {
            amount: totalAmount,
            purpose: purpose,
            property_total_id: propertyTotalId,
            is_annual: true,
            client_system: 'RPT System',
            client_reference: `ANNUAL-${propertyTotalId}`,
            reference: propertyRef,
            description: `Annual RPT Tax Payment - ${propertyRef}`
        };
        
        if (hasDiscount) {
            paymentData.discount_percent = discountPercent;
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

    function viewReceipt(receiptNumber) {
        alert('Receipt Number: ' + receiptNumber + '\n\nReceipt viewing feature will be available soon.');
    }
    </script>
</body>
</html>