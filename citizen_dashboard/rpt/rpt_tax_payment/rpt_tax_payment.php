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
        .status-paid { background-color: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .status-pending { background-color: #fef3c7; color: #92400e; border: 1px solid #f59e0b; }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .payment-card {
            transition: all 0.2s ease;
        }
        .payment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-6">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to RPT Services
            </a>
        </div>

        <!-- Page Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-1">Real Property Tax Payment</h1>
                    <p class="text-gray-600">Pay your property taxes for registered properties</p>
                </div>
                <div class="mt-3 md:mt-0 text-right">
                    <p class="text-sm text-gray-500">Welcome, <?php echo htmlspecialchars($user_name); ?></p>
                    <p class="text-sm text-gray-500">Current Quarter: <?php echo $current_quarter; ?> <?php echo $current_year; ?></p>
                </div>
            </div>
            
            <!-- Tax Payment Info - MOVED TO TOP -->
            <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 mt-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                    <div>
                        <p class="text-blue-800 font-medium mb-2">Important Tax Payment Information:</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="flex items-start">
                                <i class="fas fa-calendar-alt text-blue-400 text-xs mt-1 mr-2"></i>
                                <div>
                                    <span class="font-medium">Quarterly Due Dates:</span>
                                    <p class="text-blue-700">Q1: Mar 31 • Q2: Jun 30 • Q3: Sep 30 • Q4: Dec 31</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-blue-400 text-xs mt-1 mr-2"></i>
                                <div>
                                    <span class="font-medium">Penalty:</span>
                                    <p class="text-blue-700">2% monthly penalty for late payments</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-credit-card text-blue-400 text-xs mt-1 mr-2"></i>
                                <div>
                                    <span class="font-medium">Payment Options:</span>
                                    <p class="text-blue-700">Pay quarterly or pay all remaining quarters at once</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-file-invoice text-blue-400 text-xs mt-1 mr-2"></i>
                                <div>
                                    <span class="font-medium">Receipts:</span>
                                    <p class="text-blue-700">Keep receipts for tax deduction claims</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_january): ?>
        <!-- January Discount Banner -->
        <div class="bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 rounded-lg p-5 mb-6">
            <div class="flex items-center">
                <div class="bg-emerald-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-gift text-emerald-600 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-emerald-800 text-lg">January Early Payment Discount Available!</h3>
                    <p class="text-emerald-700">Get <span class="font-bold"><?php echo getDiscountPercentage($pdo); ?>% discount</span> when you pay your full annual tax during January.</p>
                    <p class="text-sm text-emerald-600 mt-1">✓ No penalties allowed for discount eligibility • ✓ Valid until January 31, <?php echo $current_year; ?></p>
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
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-8 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-home text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">No Properties Registered</h3>
                    <p class="text-gray-600 mb-6">You don\'t have any approved properties yet. Register your property to start paying taxes.</p>
                    <a href="rpt_registration/rpt_registration.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-plus-circle mr-2"></i> Register New Property
                    </a>
                </div>';
            } else {
                // Stats Summary
                $totalProperties = count($properties);
                $totalAnnualTax = 0;
                $totalUnpaid = 0;
                
                foreach ($properties as $property) {
                    $totalAnnualTax += $property['total_annual_tax'];
                }
                
                echo '
                <!-- Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white rounded-lg border border-gray-200 p-5">
                        <div class="flex items-center">
                            <div class="bg-blue-100 p-3 rounded-lg mr-4">
                                <i class="fas fa-home text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Properties</p>
                                <p class="text-2xl font-bold text-gray-800">' . $totalProperties . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg border border-gray-200 p-5">
                        <div class="flex items-center">
                            <div class="bg-green-100 p-3 rounded-lg mr-4">
                                <i class="fas fa-file-invoice-dollar text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Total Annual Tax</p>
                                <p class="text-2xl font-bold text-gray-800">₱' . number_format($totalAnnualTax, 2) . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg border border-gray-200 p-5">
                        <div class="flex items-center">
                            <div class="bg-yellow-100 p-3 rounded-lg mr-4">
                                <i class="fas fa-calendar-alt text-yellow-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Current Quarter</p>
                                <p class="text-2xl font-bold text-gray-800">' . $current_quarter . '</p>
                            </div>
                        </div>
                    </div>
                </div>';
                
                echo '<div class="space-y-6">';
                
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
                    $propertyPending = 0;
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
                            $propertyPending += $totalAmount;
                            $propertyPenalty += ($tax['penalty_amount'] ?? 0);
                            
                            if ($status == 'overdue') {
                                $overdueQuarters++;
                            } else {
                                $pendingQuarters++;
                            }
                        }
                    }
                    
                    $paymentProgress = $property['total_annual_tax'] > 0 ? ($propertyPaid / $property['total_annual_tax']) * 100 : 0;
                    $fullName = trim($property['first_name'] . ' ' . (!empty($property['middle_name']) ? substr($property['middle_name'], 0, 1) . '. ' : '') . $property['last_name']);
                    
                    $eligibleForDiscount = isEligibleForAnnualDiscount($quarterlyTaxes, $pdo);
                    $discountPercent = getDiscountPercentage($pdo);
                    $annualPaymentInfo = calculateAnnualTotal($quarterlyTaxes, $eligibleForDiscount ? $discountPercent : 0);
                    
                    echo '
                    <!-- Property Card -->
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                        <!-- Property Header -->
                        <div class="p-5 border-b border-gray-200 bg-gray-50">
                            <div class="flex flex-col md:flex-row md:items-center justify-between">
                                <div class="mb-3 md:mb-0">
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-home text-blue-600"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-800">' . htmlspecialchars($property['reference_number']) . '</h3>
                                            <p class="text-gray-600 text-sm flex items-center">
                                                <i class="fas fa-map-marker-alt text-gray-400 text-xs mr-1"></i>
                                                ' . htmlspecialchars($property['lot_location']) . ', ' . htmlspecialchars($property['barangay']) . '
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500">Annual Tax Amount</p>
                                    <p class="text-xl font-bold text-blue-600">₱' . number_format($property['total_annual_tax'], 2) . '</p>
                                    <p class="text-xs text-gray-500 mt-1">Quarterly: ₱' . number_format($property['total_annual_tax'] / 4, 2) . '</p>
                                </div>
                            </div>
                        </div>';
                        
                        if ($hasUnpaidQuarters) {
                            echo '
                        <!-- Annual Payment Option - ALWAYS SHOWN IF UNPAID QUARTERS EXIST -->
                        <div class="p-5 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                                <div class="mb-3 lg:mb-0">
                                    <div class="flex items-center mb-2">
                                        <span class="badge ' . ($eligibleForDiscount ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-blue-100 text-blue-800 border border-blue-200') . ' mr-3">
                                            <i class="fas ' . ($eligibleForDiscount ? 'fa-gift' : 'fa-calendar-check') . ' mr-1"></i>
                                            ' . ($eligibleForDiscount ? 'JANUARY DISCOUNT' : 'ANNUAL PAYMENT') . '
                                        </span>
                                        <h4 class="font-semibold text-gray-800">' . ($eligibleForDiscount ? 'Save ' . $discountPercent . '% on Annual Payment' : 'Pay All Quarters Together') . '</h4>
                                    </div>
                                    <p class="text-gray-600 text-sm">
                                        ' . ($eligibleForDiscount 
                                            ? 'Pay your entire annual tax in January to get discount. Valid until Jan 31.' 
                                            : 'Convenient single payment for all remaining quarters. Available anytime.') . '
                                    </p>
                                </div>
                                
                                <div class="lg:text-right">
                                    <div class="mb-3">
                                        <p class="text-2xl font-bold text-gray-900">₱' . number_format($annualPaymentInfo['total_with_discount'], 2) . '</p>';
                                        if ($annualPaymentInfo['has_discount']) {
                                            echo '
                                        <p class="text-sm text-gray-600">
                                            <span class="line-through">₱' . number_format($annualPaymentInfo['total_before_discount'], 2) . '</span>
                                            <span class="ml-2 text-emerald-600 font-medium">
                                                <i class="fas fa-piggy-bank mr-1"></i>Save ₱' . number_format($annualPaymentInfo['discount_amount'], 2) . '
                                            </span>
                                        </p>';
                                        } else {
                                            echo '
                                        <p class="text-sm text-gray-600">
                                            Total for ' . (4 - $paidQuarters) . ' unpaid quarter' . ((4 - $paidQuarters) > 1 ? 's' : '') . '
                                        </p>';
                                        }
                                        echo '
                                    </div>
                                    <button onclick="payAnnual(' . $property['property_total_id'] . ', ' . $annualPaymentInfo['total_with_discount'] . ', \'Annual RPT Payment - ' . htmlspecialchars($property['reference_number']) . '\', ' . ($annualPaymentInfo['has_discount'] ? 'true' : 'false') . ', ' . $discountPercent . ')" 
                                            class="' . ($eligibleForDiscount ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-blue-600 hover:bg-blue-700') . ' text-white px-5 py-2.5 rounded-lg font-medium transition-colors inline-flex items-center">
                                        <i class="fas fa-credit-card mr-2"></i> ' . ($eligibleForDiscount ? 'PAY WITH DISCOUNT' : 'PAY ANNUALLY') . '
                                    </button>
                                </div>
                            </div>
                        </div>';
                        }
                        
                        echo '
                        <!-- Quarterly Taxes Section -->
                        <div class="p-5">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-gray-800 flex items-center">
                                    <i class="fas fa-calendar-week text-blue-500 mr-2"></i> Quarterly Taxes
                                </h4>
                                <div class="text-sm text-gray-500">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded">' . $paidQuarters . ' paid</span>
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded mx-1">' . $pendingQuarters . ' pending</span>
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded">' . $overdueQuarters . ' overdue</span>
                                </div>
                            </div>';
                            
                            if (empty($quarterlyTaxes)) {
                                echo '
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                <i class="fas fa-calendar-plus text-gray-400 text-2xl mb-3"></i>
                                <p class="text-gray-600">No quarterly taxes generated for this property yet.</p>
                                <p class="text-sm text-gray-500 mt-1">Taxes are generated after property assessment</p>
                            </div>';
                            } else {
                                echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">';
                                
                                // Display quarters in order: Q1, Q2, Q3, Q4 from left to right
                                foreach ($quarterlyTaxes as $tax) {
                                    $status = $tax['actual_status'] ?? $tax['payment_status'];
                                    $totalAmount = $tax['total_quarterly_tax'] + ($tax['penalty_amount'] ?? 0);
                                    $penaltyAmount = $tax['penalty_amount'] ?? 0;
                                    
                                    $dueDate = new DateTime($tax['due_date']);
                                    $currentDate = new DateTime();
                                    $isOverdue = $status == 'overdue';
                                    $isCurrentQuarter = false;
                                    
                                    $currentMonth = date('n');
                                    $currentQuarterNum = ceil($currentMonth / 3);
                                    $taxQuarter = (int) substr($tax['quarter'], 1);
                                    
                                    if ($taxQuarter == $currentQuarterNum && $tax['year'] == date('Y')) {
                                        $isCurrentQuarter = true;
                                    }
                                    
                                    echo '
                                    <div class="payment-card border border-gray-200 rounded-lg p-4 bg-white">
                                        <div class="flex justify-between items-center mb-3">
                                            <div>
                                                <span class="font-bold text-gray-800">' . htmlspecialchars($tax['quarter']) . ' ' . htmlspecialchars($tax['year']) . '</span>';
                                                if ($isCurrentQuarter) {
                                                    echo '<span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">Current Quarter</span>';
                                                }
                                                echo '
                                            </div>
                                            <span class="badge status-' . $status . ' text-xs">
                                                <i class="fas ' . ($status == 'paid' ? 'fa-check-circle' : ($status == 'overdue' ? 'fa-exclamation-triangle' : 'fa-clock')) . ' mr-1"></i>
                                                ' . ucfirst($status) . '
                                            </span>
                                        </div>
                                        
                                        <div class="space-y-3 mb-4">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500">Due Date:</span>
                                                <span class="font-medium ' . ($isOverdue ? 'text-red-600' : 'text-gray-700') . '">' . $dueDate->format('M d, Y') . '</span>
                                            </div>';
                                            
                                            if ($isOverdue && ($tax['days_late'] ?? 0) > 0) {
                                                echo '
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500">Days Late:</span>
                                                <span class="font-medium text-red-600">' . ($tax['days_late'] ?? 0) . ' days</span>
                                            </div>';
                                            }
                                            
                                            echo '
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500">Base Amount:</span>
                                                <span class="font-medium">₱' . number_format($tax['total_quarterly_tax'], 2) . '</span>
                                            </div>';
                                            
                                            if ($penaltyAmount > 0) {
                                                echo '
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500">Penalty:</span>
                                                <span class="font-semibold text-red-600">+₱' . number_format($penaltyAmount, 2) . '</span>
                                            </div>';
                                            }
                                            
                                            echo '
                                            <div class="pt-3 border-t border-gray-200">
                                                <div class="flex justify-between">
                                                    <span class="font-medium text-gray-800">Total:</span>
                                                    <span class="font-bold text-gray-900">₱' . number_format($totalAmount, 2) . '</span>
                                                </div>
                                            </div>
                                        </div>';
                                        
                                        if ($status == 'paid') {
                                            echo '
                                        <div class="space-y-2">
                                            <button class="w-full bg-green-100 text-green-800 py-2 rounded-lg font-medium cursor-not-allowed flex items-center justify-center" disabled>
                                                <i class="fas fa-check-circle mr-2"></i>Paid';
                                                if (!empty($tax['payment_date'])) {
                                                    echo ' on ' . date('M d, Y', strtotime($tax['payment_date']));
                                                }
                                                echo '
                                            </button>';
                                            
                                            if (!empty($tax['receipt_number'])) {
                                                echo '
                                            <button onclick="viewReceipt(\'' . htmlspecialchars($tax['receipt_number']) . '\')" 
                                                    class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                                <i class="fas fa-receipt mr-2"></i>View Receipt
                                            </button>';
                                            }
                                            echo '
                                        </div>';
                                        } else {
                                            echo '
                                        <button onclick="initiatePayment(' . $tax['id'] . ', \'' . $tax['quarter'] . '\', \'' . $tax['year'] . '\', ' . $totalAmount . ', \'RPT ' . $tax['quarter'] . ' ' . $tax['year'] . ' - ' . htmlspecialchars($property['reference_number']) . '\')" 
                                                class="w-full ' . ($isOverdue ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700') . ' text-white py-3 rounded-lg font-medium transition-colors flex items-center justify-center">
                                            <i class="fas fa-credit-card mr-2"></i>Pay ₱' . number_format($totalAmount, 2) . '
                                        </button>';
                                        }
                                        
                                        echo '
                                    </div>';
                                }
                                
                                echo '</div>';
                            }
                            echo '
                        </div>
                    </div>';
                }
                
                echo '</div>';
            }
        } catch (PDOException $e) {
            echo '
            <div class="bg-white rounded-lg border border-red-200 p-8 text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-red-800 mb-2">Service Temporarily Unavailable</h3>
                <p class="text-gray-600 mb-4">We\'re experiencing technical difficulties. Please try again in a few minutes.</p>
                <button onclick="location.reload()" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 inline-flex items-center">
                    <i class="fas fa-sync-alt mr-2"></i> Try Again
                </button>
            </div>';
        }
        ?>
        
        <!-- Help Section -->
        <div class="mt-8 bg-white rounded-lg border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-question-circle text-blue-500 mr-2"></i> Need Assistance?
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border border-gray-200 rounded-lg p-4">
                    <p class="font-medium text-gray-800 mb-2">Payment Questions</p>
                    <p class="text-sm text-gray-600">For payment inquiries, call Treasury Office: (02) 8888-9999</p>
                    <p class="text-xs text-gray-500 mt-1">Weekdays, 8:00 AM - 5:00 PM</p>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <p class="font-medium text-gray-800 mb-2">Payment Options</p>
                    <p class="text-sm text-gray-600">• Pay quarterly or annually • 2% monthly penalty for late payments</p>
                    <p class="text-sm text-gray-600">• January: Annual payment discount available</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    function initiatePayment(taxId, quarter, year, amount, purpose) {
        const propertyElement = document.querySelector('h3.text-lg.font-bold.text-gray-800');
        const propertyRef = propertyElement ? propertyElement.textContent.trim() : 'RPT-PROPERTY';
        
        const propertyCard = document.querySelector('.bg-white.rounded-lg.border.border-gray-200.shadow-sm');
        const propertyTotalId = propertyCard ? propertyCard.dataset.propertyTotalId : 1;
        
        const paymentData = {
            amount: amount,
            purpose: purpose,
            tax_id: taxId,
            property_total_id: propertyTotalId,
            quarter: quarter,
            year: year,
            is_annual: false,
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
        }, 500);
    }

    function payAnnual(propertyTotalId, totalAmount, purpose, hasDiscount, discountPercent = 0) {
        const propertyElement = document.querySelector('h3.text-lg.font-bold.text-gray-800');
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
            paymentData.discount_amount = (totalAmount / (1 - discountPercent/100)) - totalAmount;
        }
        
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        btn.disabled = true;
        
        setTimeout(() => {
            const urlParams = new URLSearchParams(paymentData);
            window.location.href = '../../digital/index.php?' + urlParams.toString();
        }, 500);
    }

    function viewReceipt(receiptNumber) {
        alert('Receipt Number: ' + receiptNumber + '\n\nReceipt viewing feature will be available soon.');
    }
    </script>
</body>
</html>