<?php
// business_tax_payment.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Business Owner';

// Include database connection
include_once '../../../db/Business/business_db.php';

// Function to calculate business penalties
function calculateBusinessPenalties($quarterly_taxes, $pdo) {
    $current_date = date('Y-m-d');
    
    // Get current penalty rate from config
    try {
        $penalty_stmt = $pdo->prepare("
            SELECT penalty_percent 
            FROM business_penalty_config 
            WHERE expiration_date IS NULL OR expiration_date >= :current_date
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $penalty_stmt->execute(['current_date' => $current_date]);
        $penalty_config = $penalty_stmt->fetch(PDO::FETCH_ASSOC);
        $penalty_rate = $penalty_config['penalty_percent'] ?? 1.00;
    } catch(PDOException $e) {
        $penalty_rate = 1.00; // Default 1% penalty
    }
    
    foreach ($quarterly_taxes as &$quarter) {
        if ($quarter['payment_status'] == 'paid') continue;
        
        $due_date = new DateTime($quarter['due_date']);
        $today = new DateTime($current_date);
        
        if ($today > $due_date) {
            $interval = $due_date->diff($today);
            $days_late = $interval->days;
            
            if ($days_late > 0) {
                // Calculate monthly penalty (business penalty is monthly)
                $months_late = ceil($days_late / 30);
                $penalty_amount = $quarter['total_quarterly_tax'] * ($penalty_rate / 100) * $months_late;
                $penalty_amount = round($penalty_amount, 2);
                
                $quarter['penalty_amount'] = $penalty_amount;
                $quarter['days_late'] = $days_late;
                $quarter['actual_status'] = 'overdue';
                
                // Update database if penalty has changed
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
                    
                    // Log penalty calculation
                    $log_stmt = $pdo->prepare("
                        INSERT INTO business_penalty_log 
                        (calculated_date, updated_records, total_penalty, penalty_percent_used, created_at)
                        VALUES (CURDATE(), 1, ?, ?, NOW())
                    ");
                    $log_stmt->execute([$penalty_amount, $penalty_rate]);
                }
            }
        }
    }
    
    return $quarterly_taxes;
}

// Function to check if eligible for early payment discount
function isEligibleForEarlyDiscount($due_date_str, $pdo) {
    $due_date = new DateTime($due_date_str);
    $current_date = new DateTime();
    
    // Check if payment is at least 15 days before due date
    $days_before_due = $due_date->diff($current_date)->days;
    $is_before_due = $current_date < $due_date;
    
    if ($is_before_due && $days_before_due >= 15) {
        // Get discount rate from config
        try {
            $discount_stmt = $pdo->prepare("
                SELECT discount_percent 
                FROM business_discount_config 
                WHERE expiration_date IS NULL OR expiration_date >= :current_date
                ORDER BY effective_date DESC 
                LIMIT 1
            ");
            $discount_stmt->execute(['current_date' => date('Y-m-d')]);
            $discount_config = $discount_stmt->fetch(PDO::FETCH_ASSOC);
            return $discount_config['discount_percent'] ?? 5.00;
        } catch(PDOException $e) {
            return 5.00; // Default 5% discount
        }
    }
    
    return 0.00;
}

// Function to calculate annual payment total with discounts
function calculateBusinessAnnualTotal($quarterly_taxes, $pdo) {
    $total_annual_tax = 0;
    $total_penalty = 0;
    $total_discount = 0;
    $discount_applied = false;
    $discount_percent = 0;
    
    foreach ($quarterly_taxes as $quarter) {
        $quarter_total = $quarter['total_quarterly_tax'];
        $quarter_penalty = $quarter['penalty_amount'] ?? 0;
        
        // Check if eligible for early payment discount
        if ($quarter['payment_status'] != 'paid' && $quarter_penalty == 0) {
            $quarter_discount_percent = isEligibleForEarlyDiscount($quarter['due_date'], $pdo);
            if ($quarter_discount_percent > 0) {
                $quarter_discount = $quarter_total * ($quarter_discount_percent / 100);
                $quarter_total -= $quarter_discount;
                $total_discount += $quarter_discount;
                $discount_applied = true;
                $discount_percent = max($discount_percent, $quarter_discount_percent);
            }
        }
        
        $total_annual_tax += $quarter_total;
        $total_penalty += $quarter_penalty;
    }
    
    $total_before_discount = $total_annual_tax + $total_discount + $total_penalty;
    $total_with_discount = $total_annual_tax + $total_penalty;
    
    return [
        'total_base_tax' => $total_annual_tax + $total_discount, // Original tax before discount
        'total_penalty' => $total_penalty,
        'total_before_discount' => $total_before_discount,
        'discount_percent' => $discount_percent,
        'discount_amount' => $total_discount,
        'total_with_discount' => $total_with_discount,
        'has_discount' => $discount_applied
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Tax Payment - GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .discount-banner {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            animation: pulse 2s infinite;
        }
        .annual-banner {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.9; }
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
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
        .business-type-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        .retailer-badge {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }
        .wholesaler-badge {
            background-color: #f3e8ff;
            color: #6b21a8;
            border: 1px solid #8b5cf6;
        }
        .manufacturer-badge {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        .service-badge {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #22c55e;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include '../../../citizen_dashboard/navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">
        <!-- Page Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center mb-4">
                <a href="../business_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Business Tax Payment</h1>
                    <p class="text-gray-600">View and pay your quarterly business taxes</p>
                </div>
            </div>
        </div>

        <!-- Business Tax Payment Content -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <?php
            try {
                // Get active business permits for the user
                $query = "
                    SELECT 
                        bp.id,
                        bp.business_permit_id,
                        bp.business_name,
                        bp.owner_name,
                        bp.business_type,
                        bp.tax_calculation_type,
                        bp.taxable_amount,
                        bp.tax_amount,
                        bp.regulatory_fees,
                        bp.total_tax,
                        bp.issue_date,
                        bp.expiry_date,
                        bp.status,
                        bp.barangay,
                        bp.district,
                        bp.city,
                        bp.province
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
                    <div class="lg:col-span-4">
                        <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-store text-gray-400 text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-800 mb-2">No Active Businesses Found</h3>
                            <p class="text-gray-600 mb-4">You don\'t have any active business permits yet.</p>
                            <a href="business_application_status/business_application_status.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                                Apply for Business Permit
                            </a>
                        </div>
                    </div>';
                } else {
                    // Summary Statistics
                    $totalAnnualTax = 0;
                    $totalPaid = 0;
                    $totalPending = 0;
                    $totalBusinesses = count($businesses);
                    $overdueCount = 0;
                    $activeBusinesses = 0;
                    
                    echo '
                    <!-- Summary Stats -->
                    <div class="lg:col-span-4 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-white rounded-xl shadow p-5">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-store text-blue-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-800">' . $totalBusinesses . '</div>
                                        <div class="text-gray-500 text-sm">Businesses</div>
                                    </div>
                                </div>
                            </div>';
                    
                    foreach ($businesses as $business) {
                        if ($business['status'] == 'Active') {
                            $activeBusinesses++;
                        }
                        
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
                                END DESC
                        ";
                        
                        $taxStmt = $pdo->prepare($taxQuery);
                        $taxStmt->bindParam(':business_id', $business['id'], PDO::PARAM_INT);
                        $taxStmt->execute();
                        $quarterlyTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $quarterlyTaxes = calculateBusinessPenalties($quarterlyTaxes, $pdo);
                        
                        $businessPaid = 0;
                        $businessPending = 0;
                        $businessPenalty = 0;
                        
                        foreach ($quarterlyTaxes as $tax) {
                            $totalAmount = $tax['total_quarterly_tax'] + ($tax['penalty_amount'] ?? 0);
                            
                            if ($tax['payment_status'] == 'paid') {
                                $businessPaid += $totalAmount;
                            } else {
                                $businessPending += $totalAmount;
                                $businessPenalty += ($tax['penalty_amount'] ?? 0);
                                
                                if ($tax['actual_status'] ?? $tax['payment_status'] == 'overdue') {
                                    $overdueCount++;
                                }
                            }
                        }
                        
                        $totalAnnualTax += $business['total_tax'];
                        $totalPaid += $businessPaid;
                        $totalPending += $businessPending;
                    }
                    
                    echo '
                            <div class="bg-white rounded-xl shadow p-5">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-800">' . $activeBusinesses . '</div>
                                        <div class="text-gray-500 text-sm">Active</div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white rounded-xl shadow p-5">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-money-bill-wave text-yellow-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-800">₱' . number_format($totalPaid, 2) . '</div>
                                        <div class="text-gray-500 text-sm">Total Paid</div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white rounded-xl shadow p-5">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 ' . ($overdueCount > 0 ? 'bg-red-100' : 'bg-gray-100') . ' rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas ' . ($overdueCount > 0 ? 'fa-exclamation-triangle text-red-600' : 'fa-clock text-gray-600') . ' text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold ' . ($overdueCount > 0 ? 'text-red-600' : 'text-gray-800') . '">' . $overdueCount . '</div>
                                        <div class="text-gray-500 text-sm">Overdue Quarters</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
                    
                    // Display discount banner if early payment is possible
                    $hasEarlyDiscount = false;
                    foreach ($businesses as $business) {
                        $taxQuery = "
                            SELECT due_date, payment_status 
                            FROM business_quarterly_taxes 
                            WHERE business_permit_id = :business_id 
                            AND payment_status = 'pending'
                            AND penalty_amount = 0
                        ";
                        $taxStmt = $pdo->prepare($taxQuery);
                        $taxStmt->bindParam(':business_id', $business['id'], PDO::PARAM_INT);
                        $taxStmt->execute();
                        $pendingTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($pendingTaxes as $tax) {
                            if (isEligibleForEarlyDiscount($tax['due_date'], $pdo) > 0) {
                                $hasEarlyDiscount = true;
                                break 2;
                            }
                        }
                    }
                    
                    if ($hasEarlyDiscount) {
                        echo '
                        <div class="lg:col-span-4">
                            <div class="p-4 discount-banner rounded-lg text-white mb-6">
                                <div class="flex items-center">
                                    <i class="fas fa-gift text-2xl text-yellow-300 mr-3"></i>
                                    <div class="flex-1">
                                        <div class="font-bold text-lg">🎉 EARLY PAYMENT DISCOUNT AVAILABLE!</div>
                                        <div class="text-sm">
                                            Pay your <strong>business taxes at least 15 days before the due date</strong> 
                                            and get a discount on your payments! Check each quarter below.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
                    }
                    
                    foreach ($businesses as $business) {
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
                                END DESC
                        ";
                        
                        $taxStmt = $pdo->prepare($taxQuery);
                        $taxStmt->bindParam(':business_id', $business['id'], PDO::PARAM_INT);
                        $taxStmt->execute();
                        $quarterlyTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $quarterlyTaxes = calculateBusinessPenalties($quarterlyTaxes, $pdo);
                        
                        $businessPaid = 0;
                        $businessPending = 0;
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
                                $businessPending += $totalAmount;
                                $businessPenalty += ($tax['penalty_amount'] ?? 0);
                                
                                if ($status == 'overdue') {
                                    $overdueQuarters++;
                                } else {
                                    $pendingQuarters++;
                                }
                            }
                        }
                        
                        $paymentProgress = $business['total_tax'] > 0 ? ($businessPaid / $business['total_tax']) * 100 : 0;
                        
                        // Get business type badge class
                        $businessTypeClass = 'business-type-badge ';
                        switch($business['business_type']) {
                            case 'Retailer': $businessTypeClass .= 'retailer-badge'; break;
                            case 'Wholesaler': $businessTypeClass .= 'wholesaler-badge'; break;
                            case 'Manufacturer': $businessTypeClass .= 'manufacturer-badge'; break;
                            case 'Service': $businessTypeClass .= 'service-badge'; break;
                            default: $businessTypeClass .= 'bg-gray-100 text-gray-800 border-gray-300';
                        }
                        
                        // Get tax type info
                        $taxTypeInfo = $business['tax_calculation_type'] == 'capital_investment' 
                            ? 'Capital Investment Tax' 
                            : 'Gross Sales Tax';
                        
                        echo '
                        <div class="lg:col-span-4" data-business-id="' . $business['id'] . '" data-business-permit-id="' . $business['business_permit_id'] . '">
                            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6">
                                    <div class="mb-4 lg:mb-0">
                                        <div class="flex items-center mb-2">
                                            <h3 class="text-xl font-semibold text-gray-800">' . htmlspecialchars($business['business_name']) . '</h3>
                                            <span class="ml-2 ' . $businessTypeClass . '">' . htmlspecialchars($business['business_type']) . '</span>
                                        </div>
                                        <p class="text-gray-600">' . htmlspecialchars($business['owner_name']) . ' • ' . htmlspecialchars($business['business_permit_id']) . '</p>
                                        <p class="text-gray-500 text-sm mt-1">' . htmlspecialchars($business['barangay']) . ', ' . htmlspecialchars($business['city']) . ' • ' . htmlspecialchars($taxTypeInfo) . '</p>
                                    </div>
                                    <div class="flex flex-col md:flex-row items-center space-y-2 md:space-y-0 md:space-x-6">
                                        <div class="text-center">
                                            <div class="text-2xl font-bold text-blue-600">₱' . number_format($business['total_tax'], 2) . '</div>
                                            <div class="text-sm text-gray-500">Annual Tax</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-lg font-bold ' . ($paymentProgress >= 100 ? 'text-green-600' : 'text-blue-600') . '">
                                                ' . round($paymentProgress, 1) . '%
                                            </div>
                                            <div class="text-sm text-gray-500">Paid</div>
                                        </div>
                                    </div>
                                </div>';
                                
                                if ($hasUnpaidQuarters) {
                                    $annualPaymentInfo = calculateBusinessAnnualTotal($quarterlyTaxes, $pdo);
                                    
                                    echo '
                                <div class="mb-6 p-4 annual-banner rounded-lg text-white">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                                        <div class="mb-3 md:mb-0">
                                            <div class="font-bold text-lg flex items-center">
                                                <i class="fas fa-calendar-alt mr-2"></i> PAY ALL QUARTERLY TAXES
                                            </div>
                                            <div class="text-sm opacity-90">
                                                ' . ($annualPaymentInfo['has_discount'] 
                                                    ? 'Get <strong>early payment discounts</strong> when you pay all quarters at once!' 
                                                    : 'Pay all remaining quarters at once for convenience') . '
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-bold text-white">
                                                ₱' . number_format($annualPaymentInfo['total_with_discount'], 2) . '
                                            </div>
                                            <div class="text-sm">';
                                            
                                            if ($annualPaymentInfo['has_discount']) {
                                                echo '<span class="line-through opacity-75">₱' . number_format($annualPaymentInfo['total_before_discount'], 2) . '</span> ';
                                                echo '<span class="text-yellow-300 font-semibold">Save ₱' . number_format($annualPaymentInfo['discount_amount'], 2) . '</span>';
                                            } else {
                                                echo 'Total for ' . (4 - $paidQuarters) . ' unpaid quarter(s)';
                                            }
                                            
                                            echo '
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <button onclick="payAllQuarters(' . $business['id'] . ', ' . $annualPaymentInfo['total_with_discount'] . ', \'Business Annual Payment - ' . htmlspecialchars($business['business_name']) . '\', ' . ($annualPaymentInfo['has_discount'] ? 'true' : 'false') . ', ' . $annualPaymentInfo['discount_percent'] . ', \'' . htmlspecialchars($business['business_permit_id']) . '\')" 
                                                class="inline-flex items-center px-6 py-3 ' . ($annualPaymentInfo['has_discount'] ? 'bg-yellow-400 text-gray-900' : 'bg-white text-blue-600') . ' font-bold rounded-lg hover:opacity-90 transition shadow-lg">
                                            <i class="fas fa-credit-card mr-2"></i> ' . ($annualPaymentInfo['has_discount'] ? 'PAY ALL WITH DISCOUNT' : 'PAY ALL REMAINING QUARTERS') . '
                                        </button>';
                                        
                                        if ($annualPaymentInfo['has_discount']) {
                                            echo '
                                            <div class="mt-2 text-sm opacity-80">
                                                <i class="fas fa-clock mr-1"></i>Early payment discount applied
                                            </div>';
                                        }
                                        
                                        echo '
                                    </div>
                                </div>';
                                }
                                
                                echo '
                                <div class="mb-6">
                                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                                        <span>Payment Progress</span>
                                        <span>₱' . number_format($businessPaid, 2) . ' / ₱' . number_format($business['total_tax'], 2) . '</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="' . ($paymentProgress >= 100 ? 'bg-green-600' : 'bg-blue-600') . ' h-2.5 rounded-full" 
                                            style="width: ' . min($paymentProgress, 100) . '%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>' . $paidQuarters . ' paid</span>
                                        <span>' . $pendingQuarters . ' pending</span>
                                        <span>' . $overdueQuarters . ' overdue</span>
                                    </div>
                                </div>
                                
                                <h4 class="font-semibold text-gray-800 mb-4">Quarterly Taxes</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">';
                        
                        if (empty($quarterlyTaxes)) {
                            echo '
                                    <div class="md:col-span-2 lg:col-span-4">
                                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 text-center">
                                            <i class="fas fa-info-circle text-gray-400 text-3xl mb-3"></i>
                                            <p class="text-gray-600">No quarterly taxes generated yet for this business.</p>
                                            <p class="text-gray-500 text-sm mt-2">Taxes are generated quarterly. Please check back later.</p>
                                        </div>
                                    </div>';
                        } else {
                            foreach ($quarterlyTaxes as $tax) {
                                $status = $tax['actual_status'] ?? $tax['payment_status'];
                                $totalAmount = $tax['total_quarterly_tax'] + ($tax['penalty_amount'] ?? 0);
                                $penaltyAmount = $tax['penalty_amount'] ?? 0;
                                
                                // Check for early payment discount
                                $earlyDiscountPercent = isEligibleForEarlyDiscount($tax['due_date'], $pdo);
                                $showDiscount = $earlyDiscountPercent > 0 && $status == 'pending' && $penaltyAmount == 0;
                                $discountedAmount = $showDiscount ? $tax['total_quarterly_tax'] * (1 - $earlyDiscountPercent/100) : $tax['total_quarterly_tax'];
                                $finalAmount = $discountedAmount + $penaltyAmount;
                                
                                if ($status == 'paid') {
                                    $statusClass = 'status-paid';
                                    $statusIcon = 'fa-check-circle';
                                    $statusLabel = 'Paid';
                                    $cardBorder = 'border-green-200';
                                    $cardBg = 'bg-green-50';
                                } elseif ($status == 'overdue') {
                                    $statusClass = 'status-overdue';
                                    $statusIcon = 'fa-exclamation-triangle';
                                    $statusLabel = 'Overdue';
                                    $cardBorder = 'border-red-200';
                                    $cardBg = 'bg-red-50';
                                } else {
                                    $statusClass = 'status-pending';
                                    $statusIcon = 'fa-clock';
                                    $statusLabel = 'Pending';
                                    $cardBorder = $showDiscount ? 'border-green-200' : 'border-yellow-200';
                                    $cardBg = $showDiscount ? 'bg-green-50' : 'bg-white';
                                }
                                
                                $dueDate = new DateTime($tax['due_date']);
                                $currentDate = new DateTime();
                                $isOverdue = $status == 'overdue';
                                $isCurrentQuarter = false;
                                
                                // Determine if this is the current quarter
                                $currentMonth = date('n');
                                $currentQuarter = ceil($currentMonth / 3);
                                $taxQuarter = (int) substr($tax['quarter'], 1);
                                
                                if ($taxQuarter == $currentQuarter && $tax['year'] == date('Y')) {
                                    $isCurrentQuarter = true;
                                }
                                
                                echo '
                                    <div class="border-2 rounded-xl p-4 ' . $cardBg . ' ' . $cardBorder . '">
                                        <div class="flex items-center justify-between mb-3">
                                            <div>
                                                <span class="text-lg font-semibold text-gray-800">' . htmlspecialchars($tax['quarter']) . ' ' . htmlspecialchars($tax['year']) . '</span>';
                                                
                                                if ($isCurrentQuarter) {
                                                    echo '<span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Current</span>';
                                                }
                                                
                                                echo '
                                            </div>
                                            <span class="' . $statusClass . '">
                                                <i class="fas ' . $statusIcon . ' mr-1"></i>' . $statusLabel . '
                                            </span>
                                        </div>';
                                        
                                        if ($showDiscount) {
                                            echo '
                                        <div class="mb-3 bg-green-100 border border-green-300 rounded-lg p-2">
                                            <div class="flex items-center text-green-700 text-sm">
                                                <i class="fas fa-gift mr-2 text-green-600"></i>
                                                <div>
                                                    <div class="font-semibold">Early Payment Discount Available!</div>
                                                    <div class="text-xs">Pay ' . $earlyDiscountPercent . '% less if paid before ' . $dueDate->format('M d') . '</div>
                                                </div>
                                            </div>
                                        </div>';
                                        }
                                        
                                        echo '
                                        <div class="space-y-2 mb-4">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500">Due Date:</span>
                                                <span class="font-medium ' . ($isOverdue ? 'text-red-600' : ($showDiscount ? 'text-green-600' : 'text-gray-700')) . '">' . $dueDate->format('M d, Y') . '</span>
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
                                                <span class="text-gray-500">Base Tax:</span>
                                                <span class="font-medium">₱' . number_format($tax['total_quarterly_tax'], 2) . '</span>
                                            </div>';
                                            
                                            if ($showDiscount) {
                                                echo '
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500">Discount (' . $earlyDiscountPercent . '%):</span>
                                                <span class="font-semibold text-green-600">-₱' . number_format($tax['total_quarterly_tax'] - $discountedAmount, 2) . '</span>
                                            </div>';
                                            }
                                            
                                            if ($penaltyAmount > 0) {
                                                echo '
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500">Penalty:</span>
                                                <span class="font-semibold text-red-600">+₱' . number_format($penaltyAmount, 2) . '</span>
                                            </div>';
                                            }
                                            
                                            echo '
                                            <div class="pt-2 border-t border-gray-200">
                                                <div class="flex justify-between text-base">
                                                    <span class="text-gray-700 font-medium">Total:</span>
                                                    <span class="font-bold text-gray-800">₱' . number_format($finalAmount, 2) . '</span>
                                                </div>
                                            </div>
                                        </div>';
                                        
                                        if ($status == 'paid') {
                                            echo '
                                        <div class="space-y-2">
                                            <button class="w-full bg-green-600 text-white py-2 rounded-lg font-medium cursor-not-allowed" disabled>
                                                <i class="fas fa-check mr-2"></i>Paid on ' . (!empty($tax['payment_date']) ? date('M d, Y', strtotime($tax['payment_date'])) : '') . '
                                            </button>';
                                            
                                            if (!empty($tax['receipt_number'])) {
                                                echo '
                                            <button onclick="viewReceipt(\'' . htmlspecialchars($tax['receipt_number']) . '\')" 
                                                    class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 rounded-lg font-medium transition-colors text-sm">
                                                <i class="fas fa-receipt mr-2"></i>View Receipt
                                            </button>';
                                            }
                                            echo '
                                        </div>';
                                        } else {
                                            echo '
                                        <button onclick="initiateBusinessPayment(' . $tax['id'] . ', \'' . $tax['quarter'] . '\', \'' . $tax['year'] . '\', ' . $finalAmount . ', \'Business Tax: ' . $tax['quarter'] . ' ' . $tax['year'] . ' - ' . htmlspecialchars($business['business_name']) . '\', ' . ($showDiscount ? $earlyDiscountPercent : 0) . ', \'' . htmlspecialchars($business['business_permit_id']) . '\')" 
                                                class="w-full ' . ($isOverdue ? 'bg-red-600 hover:bg-red-700' : ($showDiscount ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700')) . ' text-white py-2.5 rounded-lg font-medium transition-colors flex items-center justify-center">
                                            <i class="fas fa-credit-card mr-2"></i>Pay ₱' . number_format($finalAmount, 2) . '
                                        </button>';
                                        }
                                        
                                        echo '
                                    </div>';
                            }
                        }
                        
                        echo '
                                </div>
                            </div>
                        </div>';
                    }
                }
            } catch (PDOException $e) {
                error_log("Business Tax Payment Error: " . $e->getMessage());
                echo '
                <div class="lg:col-span-4">
                    <div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-red-800 mb-2">Error Loading Business Taxes</h3>
                        <p class="text-red-600">Unable to load your business tax information. Please try again later.</p>
                    </div>
                </div>';
            }
            ?>
        </div>
    </main>
    
    <script>
    function initiateBusinessPayment(taxId, quarter, year, amount, purpose, discountPercent = 0, businessPermitId = '') {
        const businessElement = document.querySelector('h3.text-xl.font-semibold.text-gray-800');
        const businessName = businessElement ? businessElement.textContent.trim() : 'Business';
        
        const paymentData = {
            amount: amount,
            purpose: purpose,
            tax_id: taxId,  // This is the ID from business_quarterly_taxes table
            quarter: quarter,
            year: year,
            is_annual: false,
            client_system: 'Business Tax System',  // This identifies which system
            client_reference: `BUS-TAX-${quarter}-${year}-${taxId}`,
            reference: businessName,
            description: `Business Tax Payment: ${quarter} ${year} - ${businessName}`,
            business_permit_id: businessPermitId  // Added for tracking
        };
        
        if (discountPercent > 0) {
            paymentData.discount_percent = discountPercent;
        }
        
        // Redirect to digital payment portal
        const urlParams = new URLSearchParams(paymentData);
        window.location.href = '../../digital/index.php?' + urlParams.toString();
    }
    
    function payAllQuarters(businessId, totalAmount, purpose, hasDiscount, discountPercent = 0, businessPermitId = '') {
        const businessElement = document.querySelector('h3.text-xl.font-semibold.text-gray-800');
        const businessName = businessElement ? businessElement.textContent.trim() : 'Business';
        
        const paymentData = {
            amount: totalAmount,
            purpose: purpose,
            is_annual: true,
            client_system: 'Business Tax System',  // This identifies which system
            client_reference: `BUS-ANNUAL-${businessId}`,
            reference: businessName,
            description: `Annual Business Tax Payment - ${businessName}`,
            business_permit_id: businessPermitId  // Added for tracking
        };
        
        if (hasDiscount) {
            paymentData.discount_percent = discountPercent;
            paymentData.discount_amount = (totalAmount / (1 - discountPercent/100)) - totalAmount;
        }
        
        // Redirect to digital payment portal
        const urlParams = new URLSearchParams(paymentData);
        window.location.href = '../../digital/index.php?' + urlParams.toString();
    }
    
    function viewReceipt(receiptNumber) {
        // Open receipt in new window
        window.open(`business_receipt.php?receipt=${encodeURIComponent(receiptNumber)}`, '_blank');
    }
    </script>
</body>
</html>