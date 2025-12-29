<?php
    // rpt_tax_payment.php
    session_start();

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['user_name'] ?? 'Citizen';

    // Include database connection
    include_once '../../../db/RPT/rpt_db.php';

    // Get database connection
    $pdo = getDatabaseConnection();

    if (!$pdo) {
        die("Database connection failed");
    }

    // Function to calculate penalties
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
                    $penalty_amount = $quarter['total_quarterly_tax'] * ($penalty_rate / 100) * ($days_late / 30);
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

    // Function to check if eligible for annual discount
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

    // Function to get discount percentage
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

    // Function to calculate annual payment total
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
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>RPT Tax Payment - GoServePH</title>
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
        </style>
    </head>
    <body class="bg-gray-50">
        <!-- Include Navbar -->
        <?php include '../../navbar.php'; ?>
        
        <!-- Main Content -->
        <main class="container mx-auto px-6 py-8">
            <!-- Page Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="flex items-center mb-4">
                    <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">RPT Tax Payment</h1>
                        <p class="text-gray-600">View and pay your quarterly or annual property taxes</p>
                    </div>
                </div>
                
                <?php if (date('n') == 1): ?>
                    <div class="mt-4 p-4 discount-banner rounded-lg text-white">
                        <div class="flex items-center">
                            <i class="fas fa-gift text-2xl text-yellow-300 mr-3"></i>
                            <div class="flex-1">
                                <div class="font-bold text-lg">🎉 JANUARY SPECIAL OFFER!</div>
                                <div class="text-sm">
                                    Pay your <strong>entire annual RPT tax</strong> between <strong>January 1-31</strong> 
                                    and get a special discount! Offer ends on January 31st.
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm opacity-90">Valid until</div>
                                <div class="font-bold text-yellow-300">Jan 31, <?php echo date('Y'); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tax Payment Content -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <?php
                try {
                    // Get approved properties
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
                        <div class="lg:col-span-4">
                            <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-home text-gray-400 text-2xl"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">No Properties Found</h3>
                                <p class="text-gray-600 mb-4">You don\'t have any approved properties yet.</p>
                                <a href="rpt_registration/rpt_registration.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                                    Register Property
                                </a>
                            </div>
                        </div>';
                    } else {
                        // Summary Statistics
                        $totalAnnualTax = 0;
                        $totalPaid = 0;
                        $totalPending = 0;
                        $totalProperties = count($properties);
                        $overdueCount = 0;
                        
                        echo '
                        <!-- Summary Stats -->
                        <div class="lg:col-span-4 mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div class="bg-white rounded-xl shadow p-5">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                            <i class="fas fa-home text-blue-600 text-xl"></i>
                                        </div>
                                        <div>
                                            <div class="text-2xl font-bold text-gray-800">' . $totalProperties . '</div>
                                            <div class="text-gray-500 text-sm">Properties</div>
                                        </div>
                                    </div>
                                </div>';
                        
                        foreach ($properties as $property) {
                            $taxQuery = "
                                SELECT 
                                    qt.*,
                                    qt.penalty_amount as penalty_amount_db
                                FROM quarterly_taxes qt
                                WHERE qt.property_total_id = :property_total_id
                                ORDER BY qt.year DESC, 
                                    CASE qt.quarter 
                                        WHEN 'Q1' THEN 1
                                        WHEN 'Q2' THEN 2
                                        WHEN 'Q3' THEN 3
                                        WHEN 'Q4' THEN 4
                                    END DESC
                            ";
                            
                            $taxStmt = $pdo->prepare($taxQuery);
                            $taxStmt->bindParam(':property_total_id', $property['property_total_id'], PDO::PARAM_INT);
                            $taxStmt->execute();
                            $quarterlyTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            $quarterlyTaxes = calculatePenalties($quarterlyTaxes, $pdo);
                            
                            $propertyPaid = 0;
                            $propertyPending = 0;
                            $propertyPenalty = 0;
                            
                            foreach ($quarterlyTaxes as $tax) {
                                $totalAmount = $tax['total_quarterly_tax'] + ($tax['penalty_amount'] ?? 0);
                                
                                if ($tax['payment_status'] == 'paid') {
                                    $propertyPaid += $totalAmount;
                                } else {
                                    $propertyPending += $totalAmount;
                                    $propertyPenalty += ($tax['penalty_amount'] ?? 0);
                                    
                                    if ($tax['actual_status'] ?? $tax['payment_status'] == 'overdue') {
                                        $overdueCount++;
                                    }
                                }
                            }
                            
                            $totalAnnualTax += $property['total_annual_tax'];
                            $totalPaid += $propertyPaid;
                            $totalPending += $propertyPending;
                        }
                        
                        echo '
                                <div class="bg-white rounded-xl shadow p-5">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                                        </div>
                                        <div>
                                            <div class="text-2xl font-bold text-gray-800">₱' . number_format($totalPaid, 2) . '</div>
                                            <div class="text-gray-500 text-sm">Total Paid</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white rounded-xl shadow p-5">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                                        </div>
                                        <div>
                                            <div class="text-2xl font-bold text-gray-800">₱' . number_format($totalPending, 2) . '</div>
                                            <div class="text-gray-500 text-sm">Pending Payment</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white rounded-xl shadow p-5">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 ' . ($overdueCount > 0 ? 'bg-red-100' : 'bg-gray-100') . ' rounded-lg flex items-center justify-center mr-4">
                                            <i class="fas ' . ($overdueCount > 0 ? 'fa-exclamation-triangle text-red-600' : 'fa-calendar-check text-gray-600') . ' text-xl"></i>
                                        </div>
                                        <div>
                                            <div class="text-2xl font-bold ' . ($overdueCount > 0 ? 'text-red-600' : 'text-gray-800') . '">' . $overdueCount . '</div>
                                            <div class="text-gray-500 text-sm">Overdue Quarters</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
                        
                        foreach ($properties as $property) {
                            $taxQuery = "
                                SELECT 
                                    qt.*,
                                    qt.penalty_amount as penalty_amount_db
                                FROM quarterly_taxes qt
                                WHERE qt.property_total_id = :property_total_id
                                ORDER BY qt.year DESC, 
                                    CASE qt.quarter 
                                        WHEN 'Q1' THEN 1
                                        WHEN 'Q2' THEN 2
                                        WHEN 'Q3' THEN 3
                                        WHEN 'Q4' THEN 4
                                    END DESC
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
                            $fullName = trim($property['first_name'] . ' ' . (!empty($property['middle_name']) ? $property['middle_name'] . ' ' : '') . $property['last_name']);
                            
                            $eligibleForDiscount = isEligibleForAnnualDiscount($quarterlyTaxes, $pdo);
                            $discountPercent = getDiscountPercentage($pdo);
                            $annualPaymentInfo = calculateAnnualTotal($quarterlyTaxes, $eligibleForDiscount ? $discountPercent : 0);
                            
                            echo '
                            <div class="lg:col-span-4">
                                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6">
                                        <div class="mb-4 lg:mb-0">
                                            <h3 class="text-xl font-semibold text-gray-800">' . htmlspecialchars($property['reference_number']) . '</h3>
                                            <p class="text-gray-600">' . htmlspecialchars($property['lot_location']) . ', ' . htmlspecialchars($property['barangay']) . ', ' . htmlspecialchars($property['district']) . '</p>
                                            <p class="text-gray-500 text-sm mt-1">Owner: ' . htmlspecialchars($fullName) . '</p>
                                        </div>
                                        <div class="flex flex-col md:flex-row items-center space-y-2 md:space-y-0 md:space-x-6">
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-blue-600">₱' . number_format($property['total_annual_tax'], 2) . '</div>
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
                                        echo '
                                    <div class="mb-6 p-4 annual-banner rounded-lg text-white">
                                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                                            <div class="mb-3 md:mb-0">
                                                <div class="font-bold text-lg flex items-center">
                                                    <i class="fas fa-calendar-alt mr-2"></i> PAY ANNUAL TAXES
                                                </div>
                                                <div class="text-sm opacity-90">
                                                    ' . ($annualPaymentInfo['has_discount'] 
                                                        ? 'Get <strong>' . $discountPercent . '% discount</strong> when you pay all quarters at once!' 
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
                                            <button onclick="payAnnual(' . $property['property_total_id'] . ', ' . $annualPaymentInfo['total_with_discount'] . ', \'RPT Annual Payment - ' . htmlspecialchars($property['reference_number']) . '\', ' . ($annualPaymentInfo['has_discount'] ? 'true' : 'false') . ', ' . $discountPercent . ')" 
                                                    class="inline-flex items-center px-6 py-3 ' . ($annualPaymentInfo['has_discount'] ? 'bg-yellow-400 text-gray-900' : 'bg-white text-blue-600') . ' font-bold rounded-lg hover:opacity-90 transition shadow-lg">
                                                <i class="fas fa-credit-card mr-2"></i> ' . ($annualPaymentInfo['has_discount'] ? 'PAY ANNUAL WITH ' . $discountPercent . '% DISCOUNT' : 'PAY ALL REMAINING QUARTERS') . '
                                            </button>';
                                            
                                            if ($annualPaymentInfo['has_discount']) {
                                                echo '
                                                <div class="mt-2 text-sm opacity-80">
                                                    <i class="fas fa-clock mr-1"></i>Discount valid until January 31, ' . date('Y') . ' only
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
                                            <span>₱' . number_format($propertyPaid, 2) . ' / ₱' . number_format($property['total_annual_tax'], 2) . '</span>
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
                                                <p class="text-gray-600">No quarterly taxes generated yet for this property.</p>
                                            </div>
                                        </div>';
                            } else {
                                foreach ($quarterlyTaxes as $tax) {
                                    $status = $tax['actual_status'] ?? $tax['payment_status'];
                                    $totalAmount = $tax['total_quarterly_tax'] + ($tax['penalty_amount'] ?? 0);
                                    $penaltyAmount = $tax['penalty_amount'] ?? 0;
                                    
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
                                        $cardBorder = 'border-yellow-200';
                                        $cardBg = 'bg-white';
                                    }
                                    
                                    $dueDate = new DateTime($tax['due_date']);
                                    $currentDate = new DateTime();
                                    $isOverdue = $status == 'overdue';
                                    $isCurrentQuarter = false;
                                    
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
                                            </div>
                                            
                                            <div class="space-y-2 mb-4">
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
                                                    <span class="text-gray-500">Penalty (2% monthly):</span>
                                                    <span class="font-semibold text-red-600">+₱' . number_format($penaltyAmount, 2) . '</span>
                                                </div>';
                                                }
                                                
                                                echo '
                                                <div class="pt-2 border-t border-gray-200">
                                                    <div class="flex justify-between text-base">
                                                        <span class="text-gray-700 font-medium">Total:</span>
                                                        <span class="font-bold text-gray-800">₱' . number_format($totalAmount, 2) . '</span>
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
                                            <button onclick="initiatePayment(' . $tax['id'] . ', \'' . $tax['quarter'] . '\', \'' . $tax['year'] . '\', ' . $totalAmount . ', \'RPT Tax: ' . $tax['quarter'] . ' ' . $tax['year'] . ' - ' . htmlspecialchars($property['reference_number']) . '\')" 
                                                    class="w-full ' . ($isOverdue ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700') . ' text-white py-2.5 rounded-lg font-medium transition-colors flex items-center justify-center">
                                                <i class="fas fa-credit-card mr-2"></i>Pay ₱' . number_format($totalAmount, 2) . '
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
                    error_log("RPT Tax Payment Error: " . $e->getMessage());
                    echo '
                    <div class="lg:col-span-4">
                        <div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
                            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-red-800 mb-2">Error Loading Taxes</h3>
                            <p class="text-red-600">Unable to load your tax information. Please try again later.</p>
                        </div>
                    </div>';
                }
                ?>
            </div>
        </main>
        
        <script>
        function initiatePayment(taxId, quarter, year, amount, purpose) {
            alert('Payment Button Clicked!\n\n' +
                  'Quarterly Payment\n' +
                  'Quarter: ' + quarter + ' ' + year + '\n' +
                  'Amount: ₱' + parseFloat(amount).toFixed(2) + '\n' +
                  'Purpose: ' + purpose + '\n\n' +
                  'This is a demonstration button. Actual payment functionality will be implemented later.');
        }
        
        function payAnnual(propertyTotalId, totalAmount, purpose, hasDiscount, discountPercent = 0) {
            const discountText = hasDiscount ? ' (with ' + discountPercent + '% discount)' : '';
            alert('Annual Payment Button Clicked!\n\n' +
                  'Annual Payment' + discountText + '\n' +
                  'Property Total ID: ' + propertyTotalId + '\n' +
                  'Total Amount: ₱' + parseFloat(totalAmount).toFixed(2) + '\n' +
                  'Purpose: ' + purpose + '\n\n' +
                  'This is a demonstration button. Actual payment functionality will be implemented later.');
        }
        
        function viewReceipt(receiptNumber) {
            alert('View Receipt Button Clicked!\n\n' +
                  'Receipt Number: ' + receiptNumber + '\n' +
                  'Receipt viewing functionality will be implemented later.');
        }
        </script>
    </body>
    </html>