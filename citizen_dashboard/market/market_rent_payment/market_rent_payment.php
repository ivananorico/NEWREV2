<?php
// revenue2/citizen_dashboard/market/market_rent_payment/market_rent_payment.php

// Remove session_start() and include navbar.php directly
// The navbar.php already handles session checking
require_once '../../../citizen_dashboard/navbar.php';

$user_id = $_SESSION['user_id'];

include_once '../../../db/Market/market_db.php';

if (!$pdo) {
    die("Database connection failed");
}

// Determine base URL based on whether we're on localhost or live domain
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
$base_url = $is_localhost 
    ? 'http://localhost/revenue2' 
    : 'https://revenuetreasury.goserveph.com';

// Use your existing market_payment_api.php for callbacks
$market_callback_url = $base_url . '/citizen_dashboard/market/api/market_payment_api.php';

// Function to get current penalty rate from config
function getMarketPenaltyRate($pdo) {
    try {
        $penalty_stmt = $pdo->prepare("
            SELECT penalty_percent 
            FROM market_penalty_config 
            WHERE expiration_date IS NULL OR expiration_date >= CURDATE()
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $penalty_stmt->execute();
        $penalty_config = $penalty_stmt->fetch(PDO::FETCH_ASSOC);
        
        return $penalty_config['penalty_percent'] ?? 5.00;
    } catch(PDOException $e) {
        return 5.00;
    }
}

// Function to get current discount rate from config
function getMarketDiscountRate($pdo) {
    try {
        $discount_stmt = $pdo->prepare("
            SELECT discount_percent 
            FROM market_discount_config 
            WHERE expiration_date IS NULL OR expiration_date >= CURDATE()
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $discount_stmt->execute();
        $discount_config = $discount_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($discount_config) {
            return (float)$discount_config['discount_percent'];
        }
        
        return 10.00;
    } catch(PDOException $e) {
        return 10.00;
    }
}

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Get config values from DATABASE
$penalty_rate = getMarketPenaltyRate($pdo);
$discount_rate = getMarketDiscountRate($pdo);

$current_year = date('Y');
$is_january = date('n') == 1;
$current_month = date('n');
$current_month_name = date('F');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Rent Payment - LGU System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../images/GSM_logo.png">
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
    <!-- No need to include navbar.php again since it's already included at the top -->
    <!-- The navbar will be displayed automatically -->
    
    <div class="max-w-6xl mx-auto px-4 py-6">
        <!-- Page Header with Simple Title Only -->
        <div class="header-box mb-6">
            <div class="flex items-center mb-4">
                <a href="../market_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Market Rent Payment</h1>
                    <p class="text-gray-600 mt-1">Online payment portal for your market stall rentals</p>
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
            <p class="mt-1 text-sm">Your market rent payment has been processed successfully.</p>
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
                    <strong><?php echo $penalty_rate; ?>% monthly penalty</strong> applies to late payments after due date.
                    <br>
                    <strong><?php echo $discount_rate; ?>% discount</strong> available for payments made in January.
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

        <!-- Market Stalls Section -->
        <?php
        try {
            $query = "
                SELECT 
                    rs.*,
                    ro.renter_code,
                    s.name as stall_name,
                    sr.class_name,
                    sr.price as class_price,
                    m.name as market_name,
                    rt.monthly_rent,
                    rt.id as rent_total_id
                FROM rent_stall rs
                JOIN renter_owner ro ON rs.renter_id = ro.id
                JOIN stalls s ON rs.stall_id = s.id
                JOIN stall_rights sr ON s.class_id = sr.class_id
                JOIN maps m ON rs.map_id = m.id
                LEFT JOIN rent_totals rt ON rs.registration_id = rt.registration_id AND rt.status = 'active'
                WHERE ro.user_id = :user_id 
                AND rs.status = 'active'
                ORDER BY rs.created_at DESC
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $stalls = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($stalls)) {
                echo '
                <div class="simple-card p-8 text-center">
                    <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-store text-gray-500 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">No Market Stalls Found</h3>
                    <p class="text-gray-600 mb-6">You don\'t have any active market stalls yet.</p>
                    <div class="space-y-3">
                        <a href="../market_portal_services/market_portal_services.php" class="btn btn-primary w-full">
                            <i class="fas fa-plus-circle mr-2"></i> Apply for a Stall
                        </a>
                        <a href="../market_services.php" class="btn btn-secondary w-full">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Services
                        </a>
                    </div>
                </div>';
            } else {
                echo '<div class="space-y-6">';
                
                foreach ($stalls as $stall) {
                    // Get monthly rent billing
                    $billingQuery = "
                        SELECT 
                            mrb.*,
                            DATE_FORMAT(mrb.due_date, '%M %d, %Y') as formatted_due_date
                        FROM monthly_rent_billing mrb
                        WHERE mrb.rent_total_id = :rent_total_id
                        ORDER BY mrb.billing_year, mrb.billing_month
                    ";
                    
                    $billingStmt = $pdo->prepare($billingQuery);
                    $billingStmt->bindParam(':rent_total_id', $stall['rent_total_id'], PDO::PARAM_INT);
                    $billingStmt->execute();
                    $monthly_bills = $billingStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calculate statistics
                    $paidMonths = 0;
                    $overdueMonths = 0;
                    $pendingMonths = 0;
                    $totalPenalty = 0;
                    $totalRent = 0;
                    
                    foreach ($monthly_bills as $bill) {
                        $totalRent += $bill['base_rent'] ?? 0;
                        $totalPenalty += ($bill['penalty_amount'] ?? 0);
                        
                        if ($bill['payment_status'] == 'paid') {
                            $paidMonths++;
                        } elseif ($bill['payment_status'] == 'overdue') {
                            $overdueMonths++;
                        } else {
                            $pendingMonths++;
                        }
                    }
                    
                    // Check annual payment
                    $annual_payment_query = "
                        SELECT 
                            ap.*,
                            ap.final_amount as total_to_pay
                        FROM market_annual_payments ap
                        WHERE ap.registration_id = :registration_id 
                        AND ap.payment_year = :current_year
                        AND ap.status = 'active'
                        LIMIT 1
                    ";
                    
                    $annual_stmt = $pdo->prepare($annual_payment_query);
                    $annual_stmt->execute([
                        ':registration_id' => $stall['registration_id'],
                        ':current_year' => $current_year
                    ]);
                    $annualPayment = $annual_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo '
                    <!-- Stall Card -->
                    <div class="simple-card">
                        <!-- Stall Header -->
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="font-semibold text-gray-900">' . htmlspecialchars($stall['business_name']) . '</span>
                                    </div>
                                    <div class="text-gray-600 text-sm">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        ' . htmlspecialchars($stall['market_name']) . ' • ' . htmlspecialchars($stall['stall_name']) . '
                                    </div>
                                    <div class="text-gray-600 text-sm mt-1">
                                        <i class="fas fa-id-card mr-1"></i>
                                        Renter Code: ' . htmlspecialchars($stall['renter_code']) . ' • ' . htmlspecialchars($stall['class_name']) . ' Class
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600 mb-1">Monthly Rent:</div>
                                    <div class="text-xl font-bold text-blue-700">' . formatCurrency($stall['monthly_rent']) . '</div>
                                </div>
                            </div>
                        </div>';
                        
                        // Annual Payment Option
                        if ($annualPayment) {
                            $showDiscount = ($is_january && $discount_rate > 0);
                            echo '
                        <div class="p-6 border-b border-gray-100 ' . ($showDiscount ? 'bg-green-50' : '') . '">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                                <div class="mb-4 lg:mb-0">
                                    <div class="flex items-center mb-2">
                                        <h4 class="font-medium text-gray-900">
                                            <i class="fas fa-calendar-check text-green-600 mr-2"></i>
                                            Annual Payment
                                        </h4>';
                                        if ($showDiscount) {
                                            echo '
                                        <span class="discount-badge ml-3">
                                            <i class="fas fa-gift mr-1"></i>Save ' . number_format($discount_rate, 2) . '%
                                        </span>';
                                        }
                                        echo '
                                    </div>
                                    <p class="text-sm text-gray-600">
                                        Pay all 12 months in one payment' . ($showDiscount ? ' with discount' : '') . '
                                    </p>';
                                    if ($showDiscount) {
                                        echo '<p class="text-xs text-green-600 mt-1">
                                            <i class="fas fa-calendar-alt mr-1"></i> Valid in January
                                        </p>';
                                    }
                                    echo '
                                </div>
                                
                                <div class="lg:text-right">
                                    <div class="mb-3">
                                        <div class="text-xl font-bold text-gray-900">' . formatCurrency($annualPayment['final_amount']) . '</div>';
                                        if ($annualPayment['discount_amount'] > 0) {
                                            $total_before_discount = $annualPayment['base_amount'] + $annualPayment['penalty_amount'];
                                            echo '
                                        <div class="text-sm text-gray-600 mt-1">
                                            <span class="line-through">' . formatCurrency($total_before_discount) . '</span>
                                            <span class="text-green-700 font-medium ml-2">
                                                <i class="fas fa-piggy-bank mr-1"></i>Save ' . formatCurrency($annualPayment['discount_amount']) . '
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
                                        $purpose_text = 'MARKET ANNUAL ' . $current_year . ' - ' . $stall['business_name'];
                                        $purpose_text = htmlspecialchars($purpose_text, ENT_QUOTES);
                                        
                                        echo '
                                        <form method="POST" 
                                              action="../../digital/index.php" 
                                              target="_blank">
                                            <input type="hidden" name="system" value="market_rent">
                                            <input type="hidden" name="ref" value="ANNUAL-' . $annualPayment['reference_number'] . '">
                                            <input type="hidden" name="amount" value="' . $annualPayment['final_amount'] . '">
                                            <input type="hidden" name="purpose" value="' . $purpose_text . '">
                                            <input type="hidden" name="callback" value="' . $market_callback_url . '">
                                            <input type="hidden" name="registration_id" value="' . $stall['registration_id'] . '">
                                            
                                            <button type="submit" 
                                                    class="btn btn-success">
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
                        <!-- Monthly Payments -->
                        <div class="p-6">
                            <h4 class="section-title">
                                <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>
                                Monthly Rent Payments
                            </h4>
                            
                            <!-- Summary -->
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex space-x-4">
                                    <div class="text-center">
                                        <div class="text-xs text-gray-600">Paid</div>
                                        <div class="font-bold text-green-600">' . $paidMonths . '</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-xs text-gray-600">Pending</div>
                                        <div class="font-bold text-yellow-600">' . $pendingMonths . '</div>
                                    </div>';
                                    if ($overdueMonths > 0) {
                                        echo '
                                    <div class="text-center">
                                        <div class="text-xs text-gray-600">Overdue</div>
                                        <div class="font-bold text-red-600">' . $overdueMonths . '</div>
                                    </div>';
                                    }
                                    echo '
                                </div>
                            </div>';
                            
                            if (empty($monthly_bills)) {
                                echo '
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-file-invoice text-2xl mb-2 text-gray-300"></i>
                                <p>No monthly rent bills generated yet</p>
                            </div>';
                            } else {
                                echo '
                            <div class="overflow-x-auto">
                                <table class="tax-table">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Due Date</th>
                                            <th>Rent Amount</th>
                                            <th>Penalty</th>
                                            <th>Total Due</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                    
                                    $month_names = [
                                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                    ];
                                    
                                    foreach ($monthly_bills as $bill) {
                                        $status = $bill['payment_status'];
                                        $rent_amount = $bill['base_rent'] ?? 0;
                                        $penaltyAmount = $bill['penalty_amount'] ?? 0;
                                        $totalAmount = $bill['total_amount_due'] ?? 0;
                                        $daysLate = $bill['days_late'] ?? 0;
                                        
                                        $dueDate = new DateTime($bill['due_date']);
                                        $isCurrentMonth = ($bill['billing_month'] == $current_month && $bill['billing_year'] == $current_year);
                                        $month_name = $month_names[$bill['billing_month']] ?? 'Month ' . $bill['billing_month'];
                                        
                                        echo '
                                        <tr>
                                            <td>
                                                <div class="font-medium">' . $month_name . ' ' . $bill['billing_year'] . '</div>';
                                                if ($isCurrentMonth) {
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
                                                ' . formatCurrency($rent_amount) . '
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
                                                    $purpose_text = 'MARKET RENT ' . $month_name . ' ' . $bill['billing_year'] . ' - ' . $stall['business_name'];
                                                    $purpose_text = htmlspecialchars($purpose_text, ENT_QUOTES);
                                                    
                                                    echo '
                                                    <form class="inline-block" 
                                                          method="POST" 
                                                          action="../../digital/index.php" 
                                                          target="_blank">
                                                        <input type="hidden" name="system" value="market_rent">
                                                        <input type="hidden" name="ref" value="' . $bill['id'] . '">
                                                        <input type="hidden" name="amount" value="' . $totalAmount . '">
                                                        <input type="hidden" name="purpose" value="' . $purpose_text . '">
                                                        <input type="hidden" name="callback" value="' . $market_callback_url . '">
                                                        
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
                                            <td>' . formatCurrency($totalRent) . '</td>
                                            <td class="' . ($totalPenalty > 0 ? 'text-red-600' : '') . '">' . formatCurrency($totalPenalty) . '</td>
                                            <td class="text-blue-700">' . formatCurrency($totalRent + $totalPenalty) . '</td>
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
                <p class="text-gray-600 mb-6">We cannot load your market stall information right now. Please try again later.</p>
                <div class="space-y-3 max-w-sm mx-auto">
                    <button onclick="location.reload()" class="btn btn-primary w-full">
                        Try Again
                    </button>
                    <a href="../market_services.php" class="btn btn-secondary w-full">
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
                        <li><a href="../market_services.php" class="hover:text-[#4a90e2] transition-colors">Services</a></li>
                        <li><a href="#" class="hover:text-[#4a90e2] transition-colors">My Applications</a></li>
                        <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Settings</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                    <ul class="space-y-3 text-gray-600">
                        <li><i class="fas fa-phone mr-2 text-gray-400"></i> (02) 8123 4567</li>
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