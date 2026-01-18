<?php
// revenue2/citizen_dashboard/market/market_rent_payment/market_rent_payment.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Market Renter';

include_once '../../../db/Market/market_db.php';

if (!$pdo) {
    die("Database connection failed");
}

// Determine base URL based on whether we're on localhost or live domain
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
$base_url = $is_localhost 
    ? 'http://localhost/revenue2' 
    : 'https://revenuetreasury.goserveph.com';

// Market payment callback URL - dynamically generated
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
        
        return $discount_config['discount_percent'] ?? 10.00;
    } catch(PDOException $e) {
        return 10.00;
    }
}

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Get config values for display
$penalty_rate = getMarketPenaltyRate($pdo);
$discount_rate = getMarketDiscountRate($pdo);

$current_year = date('Y');
$current_month = date('n');
$current_month_name = date('F');
$is_january = $current_month == 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Rent Payment - LGU Market Services</title>
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
        .status-active { background-color: #dcfce7; color: #166534; }
        .status-inactive { background-color: #f3f4f6; color: #374151; }
        
        .card {
            background: white;
            border-radius: 0.625rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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
        
        .month-badge {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 auto;
        }
        
        .month-paid {
            background-color: #dcfce7;
            color: #166534;
            border: 2px solid #86efac;
        }
        
        .month-overdue {
            background-color: #fee2e2;
            color: #991b1b;
            border: 2px solid #fca5a5;
        }
        
        .month-pending {
            background-color: #fef3c7;
            color: #92400e;
            border: 2px solid #fde68a;
        }
        
        /* POST method indicator */
        .post-method-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            margin-left: 0.5rem;
        }
        
        .new-tab-indicator {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Payment Success Message -->
        <?php if (isset($_GET['payment_success']) && $_GET['payment_success'] === 'true'): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                <div>
                    <h3 class="font-bold text-green-800 mb-1">Payment Successful!</h3>
                    <p class="text-green-700 text-sm">Your market rent payment has been processed. Your payment status will update shortly.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Back Button & Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <a href="../market_services.php" class="text-blue-600 hover:text-blue-800 inline-flex items-center text-sm mb-2">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Market Services
                    </a>
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold text-gray-800">Market Rent Payment</h1>
                        <span class="post-method-badge">
                            <i class="fas fa-shield-alt mr-1"></i> Secure POST
                        </span>
                        <span class="new-tab-indicator">
                            <i class="fas fa-external-link-alt mr-1"></i> New Tab
                        </span>
                    </div>
                    <p class="text-gray-600 text-sm">Manage and pay your market stall rentals online</p>
                </div>
                <div class="text-right">
                    <div class="flex items-center justify-end space-x-4">
                        <div class="text-right">
                            <p class="text-sm text-gray-500">Welcome, <?php echo htmlspecialchars($user_name); ?></p>
                            <p class="text-sm text-gray-500">Current Month: <?php echo $current_month_name . ' ' . $current_year; ?></p>
                        </div>
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user text-blue-600"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Security Info Banner -->
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-100 rounded-xl p-5 mb-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-shield-alt text-green-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-green-800">Secure Payment Transmission</h3>
                        <p class="text-green-700">Your payment data is transmitted securely using POST method and opens in new tab.</p>
                    </div>
                    <div class="hidden md:flex items-center space-x-3">
                        <div class="bg-white px-3 py-1 rounded-lg border border-green-200">
                            <span class="text-green-700 font-medium text-sm">
                                <i class="fas fa-lock mr-1"></i> Data Encrypted
                            </span>
                        </div>
                        <div class="bg-white px-3 py-1 rounded-lg border border-green-200">
                            <span class="text-green-700 font-medium text-sm">
                                <i class="fas fa-external-link-alt mr-1"></i> New Tab
                            </span>
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
                                <h3 class="text-lg font-bold text-blue-800">Rent Payment Information</h3>
                                <p class="text-blue-700">Important dates and reminders</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        <div class="flex items-center bg-white px-3 py-2 rounded-lg">
                            <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
                            <span class="font-medium">Due Date:</span>
                            <span class="ml-2 text-gray-700">15th of each month</span>
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
                        <h3 class="text-xl font-bold text-white mb-1">January Discount Available!</h3>
                        <p class="text-emerald-100">Get <span class="font-bold text-yellow-300"><?php echo $discount_rate; ?>% discount</span> when paying rent on or before January 15.</p>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="bg-white/20 backdrop-blur-sm px-4 py-2 rounded-lg">
                        <p class="text-white text-sm">Valid until January 15</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Stalls Section -->
        <?php
        try {
            $query = "
                SELECT 
                    rs.*,
                    ro.renter_code,
                    ro.first_name,
                    ro.last_name,
                    ro.mobile,
                    ro.email,
                    s.name as stall_name,
                    sr.class_name,
                    sr.price as class_price,
                    m.name as market_name,
                    rt.monthly_rent,
                    rt.start_date as contract_start,
                    rt.end_date as contract_end
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
                <div class="card p-10 text-center max-w-lg mx-auto">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-store text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">No Active Stalls</h3>
                    <p class="text-gray-600 mb-8 text-sm leading-relaxed">You don\'t have any active market stalls yet. Apply for a stall to start paying rent online.</p>
                    <div class="space-y-3">
                        <a href="market_portal_services/market_portal_services.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium inline-flex items-center justify-center w-full glow-button">
                            <i class="fas fa-plus-circle mr-3"></i> Apply for a Stall
                        </a>
                        <a href="market_application/pending.php" class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-6 py-3 rounded-lg font-medium inline-flex items-center justify-center w-full">
                            <i class="fas fa-question-circle mr-3"></i> Check Application Status
                        </a>
                    </div>
                </div>';
            } else {
                // Stats Summary
                $totalStalls = count($stalls);
                
                echo '
                <!-- Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="card p-5 hover:shadow-lg transition-shadow duration-300">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-3 rounded-xl mr-4">
                                <i class="fas fa-store text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Active Stalls</p>
                                <p class="text-2xl font-bold text-gray-800">' . $totalStalls . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-5 hover:shadow-lg transition-shadow duration-300">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-br from-green-500 to-green-600 p-3 rounded-xl mr-4">
                                <i class="fas fa-shield-alt text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Security Level</p>
                                <p class="text-2xl font-bold text-green-600">POST</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-5 hover:shadow-lg transition-shadow duration-300">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-br from-yellow-500 to-orange-500 p-3 rounded-xl mr-4">
                                <i class="fas fa-calendar-alt text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Current Month</p>
                                <p class="text-2xl font-bold text-gray-800">' . $current_month_name . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-5 hover:shadow-lg transition-shadow duration-300">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-br from-purple-500 to-purple-600 p-3 rounded-xl mr-4">
                                <i class="fas fa-external-link-alt text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Opens In</p>
                                <p class="text-2xl font-bold text-purple-600">New Tab</p>
                            </div>
                        </div>
                    </div>
                </div>';
                
                echo '<div class="space-y-8">';
                
                foreach ($stalls as $stall) {
                    // Get monthly rent billing
                    $billingQuery = "
                        SELECT 
                            mrb.*
                        FROM monthly_rent_billing mrb
                        JOIN rent_totals rt ON mrb.rent_total_id = rt.id
                        WHERE rt.registration_id = :registration_id
                        ORDER BY mrb.billing_year, mrb.billing_month
                    ";
                    
                    $billingStmt = $pdo->prepare($billingQuery);
                    $billingStmt->bindParam(':registration_id', $stall['registration_id'], PDO::PARAM_INT);
                    $billingStmt->execute();
                    $monthly_bills = $billingStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calculate statistics
                    $paidMonths = 0;
                    $pendingMonths = 0;
                    $overdueMonths = 0;
                    $totalPaidAmount = 0;
                    $totalPenalty = 0;
                    $totalDue = 0;
                    $hasCurrentMonth = false;
                    $currentMonthBill = null;
                    
                    $month_names = [
                        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
                    ];
                    
                    foreach ($monthly_bills as $bill) {
                        $totalDue += $bill['total_amount_due'];
                        
                        if ($bill['billing_month'] == $current_month && $bill['billing_year'] == $current_year) {
                            $hasCurrentMonth = true;
                            $currentMonthBill = $bill;
                        }
                        
                        if ($bill['payment_status'] == 'paid') {
                            $paidMonths++;
                            $totalPaidAmount += $bill['total_amount_due'];
                        } elseif ($bill['payment_status'] == 'overdue') {
                            $overdueMonths++;
                            $totalPenalty += $bill['penalty_amount'];
                        } else {
                            $pendingMonths++;
                            $totalPenalty += $bill['penalty_amount'];
                        }
                    }
                    
                    echo '
                    <!-- Stall Card -->
                    <div class="card overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <!-- Stall Header -->
                        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-blue-50">
                            <div class="flex flex-col md:flex-row md:items-center justify-between">
                                <div class="mb-4 md:mb-0">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mr-4">
                                            <i class="fas fa-store text-white text-lg"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-bold text-gray-800">' . htmlspecialchars($stall['stall_name']) . '</h3>
                                            <div class="flex items-center gap-3 mt-2">
                                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                                    ' . htmlspecialchars($stall['class_name']) . ' Class
                                                </span>
                                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                                    ' . htmlspecialchars($stall['business_type'] ?? 'Retail') . '
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-3 mt-3">
                                        <p class="text-gray-600 text-sm flex items-center">
                                            <i class="fas fa-id-card text-gray-400 mr-1 text-sm"></i>
                                            <span class="font-medium mr-1">Renter Code:</span> ' . htmlspecialchars($stall['renter_code']) . '
                                        </p>
                                        <p class="text-gray-600 text-sm flex items-center">
                                            <i class="fas fa-map-marker-alt text-gray-400 mr-1 text-sm"></i>
                                            ' . htmlspecialchars($stall['market_name']) . '
                                        </p>
                                        <p class="text-gray-600 text-sm flex items-center">
                                            <i class="fas fa-calendar text-gray-400 mr-1 text-sm"></i>
                                            Contract: ' . date('M Y', strtotime($stall['contract_start'])) . ' - ' . date('M Y', strtotime($stall['contract_end'])) . '
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500 font-medium">Monthly Rent</p>
                                    <p class="text-2xl font-bold text-blue-600">' . formatCurrency($stall['monthly_rent']) . '</p>
                                    <p class="text-xs text-green-600 mt-1">
                                        <i class="fas fa-shield-alt mr-1"></i> Secure POST • 
                                        <i class="fas fa-external-link-alt ml-1"></i> New Tab
                                    </p>
                                </div>
                            </div>
                        </div>';
                        
                        // Current Month Payment
                        if ($hasCurrentMonth && $currentMonthBill && $currentMonthBill['payment_status'] != 'paid') {
                            $isOverdue = $currentMonthBill['payment_status'] == 'overdue';
                            $days_late = $currentMonthBill['days_late'] ?? 0;
                            
                            echo '
                        <!-- Current Month Payment -->
                        <div class="p-5 border-b border-gray-200 bg-gradient-to-r from-' . ($isOverdue ? 'red' : 'blue') . '-50 to-' . ($isOverdue ? 'orange' : 'indigo') . '-50">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                                <div class="mb-4 lg:mb-0">
                                    <div class="flex items-center mb-2">
                                        <span class="status-badge ' . ($isOverdue ? 'bg-gradient-to-r from-red-500 to-red-600 text-white border-0 shadow-md pulse' : 'bg-gradient-to-r from-blue-500 to-blue-600 text-white border-0 shadow-md') . ' mr-3">
                                            <i class="fas ' . ($isOverdue ? 'fa-exclamation-triangle' : 'fa-calendar-check') . ' mr-1.5"></i>
                                            ' . ($isOverdue ? 'OVERDUE PAYMENT' : 'CURRENT MONTH') . '
                                        </span>
                                        <h4 class="font-bold text-gray-800 text-base">' . $current_month_name . ' ' . $current_year . ' Rent</h4>
                                    </div>
                                    <p class="text-gray-600 text-sm">
                                        ' . ($isOverdue 
                                            ? 'Payment is ' . $days_late . ' days late. Penalty has been applied.' 
                                            : ($is_january ? 'January discount available if paid before 15th!' : 'Pay on or before 15th to avoid penalty')) . '
                                    </p>';
                                    if ($days_late > 0) {
                                        echo '
                                    <div class="mt-2 text-sm text-red-600 font-medium">
                                        <i class="fas fa-clock mr-1"></i>' . $days_late . ' days overdue • Penalty: ' . formatCurrency($currentMonthBill['penalty_amount'] ?? 0) . '
                                    </div>';
                                    }
                                    echo '
                                </div>
                                
                                <div class="lg:text-right">
                                    <div class="mb-3">
                                        <p class="text-2xl font-bold text-gray-900">' . formatCurrency($currentMonthBill['total_amount_due']) . '</p>';
                                        if ($currentMonthBill['discount_amount'] > 0) {
                                            echo '
                                        <p class="text-sm text-gray-600 flex items-center justify-center lg:justify-end">
                                            <span class="line-through mr-3">' . formatCurrency($currentMonthBill['base_rent']) . '</span>
                                            <span class="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full font-medium">
                                                <i class="fas fa-piggy-bank mr-1"></i>Save ' . formatCurrency($currentMonthBill['discount_amount']) . '
                                            </span>
                                        </p>';
                                        } elseif ($is_january && !$isOverdue) {
                                            echo '
                                        <p class="text-sm text-green-600 flex items-center justify-center lg:justify-end">
                                            <i class="fas fa-gift mr-1"></i> ' . $discount_rate . '% discount if paid before Jan 15
                                        </p>';
                                        }
                                        echo '
                                    </div>
                                    
                                    <!-- UPDATED: Simplified POST Form -->
                                    <form id="paymentForm_' . $currentMonthBill['id'] . '" 
                                          method="POST" 
                                          action="../../digital/index.php" 
                                          target="_blank">
                                        <input type="hidden" name="system" value="market_rent">
                                        <input type="hidden" name="ref" value="' . $currentMonthBill['id'] . '">
                                        <input type="hidden" name="amount" value="' . $currentMonthBill['total_amount_due'] . '">
                                        <input type="hidden" name="purpose" value="' . htmlspecialchars('Market Rent ' . $current_month_name . ' ' . $current_year . ' - ' . $stall['stall_name']) . '">
                                        <input type="hidden" name="callback" value="' . $market_callback_url . '">
                                        
                                        <button type="submit" 
                                                class="' . ($isOverdue ? 'bg-gradient-to-r from-red-500 to-pink-500 hover:from-red-600 hover:to-pink-600 pulse' : 'bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700') . ' text-white px-5 py-2.5 rounded-lg font-semibold glow-button inline-flex items-center transform transition-transform duration-200 hover:scale-105">
                                            <i class="fas fa-external-link-alt mr-2"></i> Pay in New Tab
                                        </button>
                                        <p class="text-xs text-green-600 mt-2">
                                            <i class="fas fa-shield-alt mr-1"></i> Secure POST • Opens in new tab
                                        </p>
                                    </form>
                                </div>
                            </div>
                        </div>';
                        }
                        
                        echo '
                        <!-- Monthly Rent Billing Section -->
                        <div class="p-6">';
                            
                            if (empty($monthly_bills)) {
                                echo '
                            <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl p-8 text-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-file-invoice text-gray-400 text-2xl"></i>
                                </div>
                                <p class="text-gray-600 text-lg mb-2">No rent bills generated yet</p>
                                <p class="text-gray-500 text-sm">Rent bills will be generated automatically each month</p>
                            </div>';
                            } else {
                                // Monthly Grid View
                                echo '
                            <div class="mb-8">
                                <h5 class="font-medium text-gray-700 mb-4 text-sm">Monthly Overview</h5>
                                <div class="grid grid-cols-3 sm:grid-cols-6 lg:grid-cols-12 gap-2 mb-6">';
                                
                                foreach ($month_names as $month_num => $month_abbr) {
                                    $month_bill = null;
                                    foreach ($monthly_bills as $bill) {
                                        if ($bill['billing_month'] == $month_num && $bill['billing_year'] == $current_year) {
                                            $month_bill = $bill;
                                            break;
                                        }
                                    }
                                    
                                    $status_class = 'month-pending';
                                    $status_text = 'Pending';
                                    
                                    if ($month_bill) {
                                        if ($month_bill['payment_status'] == 'paid') {
                                            $status_class = 'month-paid';
                                            $status_text = 'Paid';
                                        } elseif ($month_bill['payment_status'] == 'overdue') {
                                            $status_class = 'month-overdue';
                                            $status_text = 'Overdue';
                                        }
                                    }
                                    
                                    $is_current = ($month_num == $current_month);
                                    $current_class = $is_current ? 'ring-2 ring-blue-500 ring-offset-1' : '';
                                    
                                    echo '
                                    <div class="text-center">
                                        <div class="month-badge ' . $status_class . ' ' . $current_class . '" title="' . $status_text . '">
                                            ' . $month_abbr . '
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">' . $month_num . '</div>
                                    </div>';
                                }
                                
                                echo '
                                </div>
                            </div>
                            
                            <!-- Monthly Table -->
                            <div class="overflow-x-auto rounded-xl border border-gray-200 mb-8">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gradient-to-r from-gray-50 to-blue-50">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Month</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Due Date</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Base Rent</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Penalty</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Discount</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Total Due</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">';
                                    
                                    $month_totals = [
                                        'base_rent' => 0,
                                        'penalty' => 0,
                                        'discount' => 0,
                                        'total_due' => 0
                                    ];
                                    
                                    foreach ($monthly_bills as $bill) {
                                        $days_late = $bill['days_late'] ?? 0;
                                        $penalty_amount = $bill['penalty_amount'] ?? 0;
                                        $discount_amount = $bill['discount_amount'] ?? 0;
                                        $base_rent = $bill['base_rent'] ?? 0;
                                        $total_due = $bill['total_amount_due'] ?? 0;
                                        $due_date = date('M d, Y', strtotime($bill['due_date']));
                                        $full_month_name = date('F', mktime(0, 0, 0, $bill['billing_month'], 1));
                                        
                                        // Update totals
                                        $month_totals['base_rent'] += $base_rent;
                                        $month_totals['penalty'] += $penalty_amount;
                                        $month_totals['discount'] += $discount_amount;
                                        $month_totals['total_due'] += $total_due;
                                        
                                        echo '
                                        <tr class="hover:bg-blue-50 transition-colors duration-150">
                                            <td class="px-6 py-4">
                                                <div class="font-medium text-gray-900 text-base">' . $full_month_name . '</div>
                                                <div class="text-gray-500 text-sm">' . $bill['billing_year'] . '</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-medium text-gray-900">' . $due_date . '</div>';
                                                if ($days_late > 0) {
                                                    echo '<div class="text-xs font-medium bg-red-100 text-red-800 px-2 py-1 rounded inline-block mt-1">
                                                        <i class="fas fa-clock mr-1"></i>' . $days_late . ' days late
                                                    </div>';
                                                }
                                                echo '
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-semibold text-gray-900 text-base">' . formatCurrency($base_rent) . '</div>
                                            </td>
                                            <td class="px-6 py-4">';
                                                if ($penalty_amount > 0) {
                                                    echo '<div class="font-semibold text-red-600">' . formatCurrency($penalty_amount) . '</div>';
                                                } else {
                                                    echo '<div class="text-gray-400">-</div>';
                                                }
                                                echo '
                                            </td>
                                            <td class="px-6 py-4">';
                                                if ($discount_amount > 0) {
                                                    echo '<div class="font-semibold text-green-600">' . formatCurrency($discount_amount) . '</div>';
                                                } else {
                                                    echo '<div class="text-gray-400">-</div>';
                                                }
                                                echo '
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-bold text-gray-900 text-base">' . formatCurrency($total_due) . '</div>
                                            </td>
                                            <td class="px-6 py-4">';
                                                $status_class = 'status-pending';
                                                if ($bill['payment_status'] == 'paid') {
                                                    $status_class = 'status-paid';
                                                } elseif ($bill['payment_status'] == 'overdue') {
                                                    $status_class = 'status-overdue';
                                                }
                                                echo '<span class="status-badge ' . $status_class . '">
                                                    <i class="fas ' . ($bill['payment_status'] == 'paid' ? 'fa-check-circle' : ($bill['payment_status'] == 'overdue' ? 'fa-exclamation-triangle' : 'fa-clock')) . ' mr-1"></i>
                                                    ' . ucfirst($bill['payment_status']) . '
                                                </span>';
                                                echo '
                                            </td>
                                            <td class="px-6 py-4">';
                                                if ($bill['payment_status'] != 'paid') {
                                                    echo '
                                                <form method="POST" action="../../digital/index.php" target="_blank" class="inline-block">
                                                    <input type="hidden" name="system" value="market_rent">
                                                    <input type="hidden" name="ref" value="' . $bill['id'] . '">
                                                    <input type="hidden" name="amount" value="' . $total_due . '">
                                                    <input type="hidden" name="purpose" value="' . htmlspecialchars('Market Rent ' . $full_month_name . ' ' . $bill['billing_year'] . ' - ' . $stall['stall_name']) . '">
                                                    <input type="hidden" name="callback" value="' . $market_callback_url . '">
                                                    <button type="submit" class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg font-medium inline-flex items-center">
                                                        <i class="fas fa-external-link-alt mr-1.5 text-xs"></i> Pay
                                                    </button>
                                                </form>';
                                                } else {
                                                    echo '
                                                <div class="inline-flex items-center text-green-600 font-medium">
                                                    <i class="fas fa-check-circle mr-1.5"></i> Paid
                                                </div>';
                                                }
                                                echo '
                                            </td>
                                        </tr>';
                                    }
                                    
                                    echo '
                                    </tbody>
                                    <tfoot class="bg-gradient-to-r from-gray-50 to-blue-50">
                                        <tr>
                                            <td colspan="2" class="px-6 py-4 font-bold text-gray-900 text-right">TOTALS:</td>
                                            <td class="px-6 py-4 font-bold text-gray-900">' . formatCurrency($month_totals['base_rent']) . '</td>
                                            <td class="px-6 py-4 font-bold text-red-600">' . formatCurrency($month_totals['penalty']) . '</td>
                                            <td class="px-6 py-4 font-bold text-green-600">' . formatCurrency($month_totals['discount']) . '</td>
                                            <td class="px-6 py-4 font-bold text-gray-900">' . formatCurrency($month_totals['total_due']) . '</td>
                                            <td colspan="2" class="px-6 py-4"></td>
                                        </tr>
                                    </tfoot>
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
            <div class="card p-8">
                <div class="bg-red-50 border border-red-200 rounded-xl p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-red-800">Database Error</h3>
                            <p class="text-red-700">Unable to load stall information. Please try again later.</p>
                        </div>
                    </div>
                </div>
            </div>';
        }
        ?>
    </div>

    <!-- Footer -->
    <div class="mt-12 pt-8 border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <p class="text-gray-600 text-sm">&copy; ' . date('Y') . ' LGU Market Services. All rights reserved.</p>
                    <p class="text-gray-500 text-xs mt-1">Secure payment portal with POST method transmission</p>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center text-gray-600 text-sm">
                            <i class="fas fa-shield-alt text-green-600 mr-2"></i>
                            <span>Secure POST</span>
                        </div>
                        <div class="flex items-center text-gray-600 text-sm">
                            <i class="fas fa-external-link-alt text-blue-600 mr-2"></i>
                            <span>New Tab</span>
                        </div>
                        <div class="flex items-center text-gray-600 text-sm">
                            <i class="fas fa-lock text-gray-600 mr-2"></i>
                            <span>Encrypted</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Auto-close success message after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const successMessage = document.querySelector('[id*="payment_success"]');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.transition = 'opacity 0.5s';
                successMessage.style.opacity = '0';
                setTimeout(() => successMessage.remove(), 500);
            }, 5000);
        }
        
        // Auto-submit payment forms when buttons are clicked
        const paymentButtons = document.querySelectorAll('form[target="_blank"] button[type="submit"]');
        paymentButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                // Prevent default browser handling
                e.preventDefault();
                
                // Get the form
                const form = this.closest('form');
                
                // Submit the form immediately
                form.submit();
            });
        });
    });
</script>
</body>
</html>