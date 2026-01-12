<?php
// market_services.php - UPDATED VERSION (with interviewed status)
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Citizen';
$user_id = $_SESSION['user_id'];

// Include database connection
include_once '../../db/Market/market_db.php';

// Status counters - UPDATED: Added 'interviewed' status
$status_counts = [
    'pending' => 0,
    'interview_scheduled' => 0,
    'interviewed' => 0,
    'paying' => 0,
    'paid' => 0,
    'need_correction' => 0,
    'resubmitted' => 0,
    'approved' => 0,
    'rejected' => 0
];

$total_applications = 0;
$latest_application = null;

try {
    // Get status counts
    $stmt = $pdo->prepare("
        SELECT application_status, COUNT(*) as count
        FROM rental_registration
        WHERE user_id = ?
        GROUP BY application_status
    ");
    $stmt->execute([$user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $status = $row['application_status'];
        if (isset($status_counts[$status])) {
            $status_counts[$status] = $row['count'];
            $total_applications += $row['count'];
        }
    }

    // Get latest application for notification
    $latest_stmt = $pdo->prepare("
        SELECT rr.*, s.name as stall_name, ro.renter_code
        FROM rental_registration rr
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN renter_owner ro ON rr.id = ro.registration_id
        WHERE rr.user_id = ?
        ORDER BY rr.created_at DESC
        LIMIT 1
    ");
    $latest_stmt->execute([$user_id]);
    $latest_application = $latest_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Market services error: " . $e->getMessage());
}

// Determine which page to use for "View All" based on priority - UPDATED
$view_all_page = 'pending.php'; // Default
$priority_order = [
    'need_correction' => 1,
    'paying' => 2,
    'interviewed' => 3,
    'interview_scheduled' => 4,
    'pending' => 5,
    'resubmitted' => 6,
    'approved' => 7,
    'paid' => 8,
    'rejected' => 9
];

foreach ($priority_order as $status => $priority) {
    if ($status_counts[$status] > 0) {
        $view_all_page = $status . '.php';
        break; // Stop at first found status
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Market Rent Services | LGU System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #9aa5b1;
            --accent: #4caf50;
            --background: #fbfbfb;
        }

        body {
            background-color: var(--background);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .service-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(229, 231, 235, 0.8);
        }

        .service-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 28px rgba(74, 144, 226, 0.15);
        }
        
        .service-arrow {
            transition: transform 0.3s ease;
        }

        .status-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid rgba(229, 231, 235, 0.8);
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .section-header {
            position: relative;
            padding-left: 1rem;
        }

        .section-header::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: var(--primary);
            border-radius: 2px;
        }
        
        .status-item {
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 8px;
            border: 1px solid rgba(229, 231, 235, 0.8);
            transition: all 0.2s ease;
        }

        .status-item:hover {
            background-color: rgba(249, 250, 251, 0.8);
            transform: translateX(4px);
        }

        .stats-box {
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stats-value {
            font-size: 2.25rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stats-label {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            color: #4b5563;
        }

        .info-item i {
            width: 20px;
            margin-right: 12px;
            color: var(--primary);
        }

        .process-list li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
            color: #4b5563;
        }

        .process-list li i {
            margin-top: 2px;
            margin-right: 12px;
            color: var(--accent);
        }
    </style>
</head>

<body class="flex flex-col min-h-screen">
<?php include '../../citizen_dashboard/navbar.php'; ?>

<main class="container mx-auto px-4 sm:px-6 py-8 max-w-7xl">

    <!-- Welcome Back Section -->
    <div class="mb-10">
        <div class="flex items-start space-x-4">
            <a href="../citizen_dashboard.php" 
               class="inline-flex items-center text-gray-600 hover:text-[var(--primary)] mt-1">
                <i class="fas fa-arrow-left text-lg"></i>
            </a>
            <div class="flex-1">
                <h1 class="text-3xl font-bold text-gray-900 mb-3">
                    Market Rent Services
                </h1>
                <p class="text-gray-600">
                    Manage your market stall rentals, track applications, and make payments
                </p>
                <!-- Divider -->
                <div class="h-1 w-20 rounded-full mt-4" style="background-color: var(--primary);"></div>
            </div>
        </div>
    </div>

    <!-- PRIORITY NOTIFICATIONS -->
    <?php if ($status_counts['need_correction'] > 0): ?>
    <div class="status-card border-l-4 border-red-500 p-5 mb-6 bg-red-50">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-semibold text-red-800 mb-2 text-lg">Action Required</h3>
                <p class="text-red-700 mb-4">
                    You have <strong class="text-xl"><?php echo $status_counts['need_correction']; ?></strong> application(s) that need correction.
                </p>
                <a href="market_application/need_correction.php"
                   class="inline-flex items-center px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    Review Applications
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($status_counts['paying'] > 0 && $status_counts['need_correction'] == 0): ?>
    <div class="status-card border-l-4 border-purple-500 p-5 mb-6 bg-purple-50">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas fa-money-bill-wave text-purple-500 text-xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-semibold text-purple-800 mb-2 text-lg">Payment Required</h3>
                <p class="text-purple-700 mb-4">
                    You have <strong class="text-xl"><?php echo $status_counts['paying']; ?></strong> application(s) ready for payment.
                </p>
                <a href="market_application/paying.php"
                   class="inline-flex items-center px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg">
                    <i class="fas fa-credit-card mr-2"></i>
                    Proceed to Payment
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($status_counts['interviewed'] > 0 && $status_counts['need_correction'] == 0 && $status_counts['paying'] == 0): ?>
    <div class="status-card border-l-4 border-teal-500 p-5 mb-6 bg-teal-50">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas fa-user-check text-teal-500 text-xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-semibold text-teal-800 mb-2 text-lg">Interview Completed</h3>
                <p class="text-teal-700 mb-4">
                    You have <strong class="text-xl"><?php echo $status_counts['interviewed']; ?></strong> application(s) with completed interviews.
                </p>
                <a href="market_application/interviewed.php"
                   class="inline-flex items-center px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-lg">
                    <i class="fas fa-clipboard-check mr-2"></i>
                    View Details
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($status_counts['interview_scheduled'] > 0 && $status_counts['need_correction'] == 0 && $status_counts['paying'] == 0 && $status_counts['interviewed'] == 0): ?>
    <div class="status-card border-l-4 border-blue-500 p-5 mb-6 bg-blue-50">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas fa-calendar-check text-blue-500 text-xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-semibold text-blue-800 mb-2 text-lg">Interview Scheduled</h3>
                <p class="text-blue-700 mb-4">
                    You have <strong class="text-xl"><?php echo $status_counts['interview_scheduled']; ?></strong> application(s) with scheduled interviews.
                </p>
                <a href="market_application/interview_scheduled.php"
                   class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg">
                    <i class="fas fa-calendar-alt mr-2"></i>
                    View Schedule
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MAIN SERVICES GRID - UPDATED TO h-48 (SAME AS RPT) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        
        <!-- RENT STALL CARD - CHANGED FROM h-40 TO h-48 -->
        <a href="market_portal_services/market_portal_services.php"
           class="service-card group bg-white block">
            <div class="h-48 overflow-hidden relative">
                <?php 
                $rent_image = 'images/market-portal.png';
                if (file_exists($rent_image)): ?>
                    <img src="<?php echo $rent_image; ?>" alt="Rent a Market Stall" 
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(74, 144, 226, 0.1);">
                        <i class="fas fa-store text-5xl" style="color: rgba(74, 144, 226, 0.3);"></i>
                    </div>
                <?php endif; ?>
                <div class="absolute top-4 right-4 px-3 py-1 rounded-lg bg-white/90">
                    <span class="text-xs font-semibold uppercase" style="color: var(--primary);">Rental</span>
                </div>
            </div>
            <div class="p-5">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: var(--primary);">
                        <i class="fas fa-store text-white"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Rent a Market Stall</h3>
                </div>
                <p class="text-gray-600 text-sm mb-6 leading-relaxed">
                    Browse market maps and apply for stall rental with complete online application.
                </p>
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <span class="font-medium" style="color: var(--primary);">Apply Now</span>
                    <i class="fas fa-arrow-right service-arrow" style="color: var(--primary);"></i>
                </div>
            </div>
        </a>

        <!-- APPLICATION STATUS CARD - CHANGED FROM h-40 TO h-48 -->
        <div class="service-card bg-white">
            <div class="h-48 overflow-hidden relative">
                <?php 
                $status_image = 'images/application-status.png';
                if (file_exists($status_image)): ?>
                    <img src="<?php echo $status_image; ?>" alt="Application Status" 
                         class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(154, 165, 177, 0.1);">
                        <i class="fas fa-chart-bar text-5xl" style="color: rgba(154, 165, 177, 0.3);"></i>
                    </div>
                <?php endif; ?>
                <div class="absolute top-4 right-4 px-3 py-1 rounded-lg bg-white/90">
                    <span class="text-xs font-semibold uppercase text-orange-600">Status</span>
                </div>
            </div>
            <div class="p-5">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-bar text-white"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Application Status</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">Track your submitted applications.</p>
                
                <!-- Status list area -->
                <div class="space-y-2 mb-4 max-h-60 overflow-y-auto pr-2">
                    <?php if ($total_applications > 0): ?>
                        <?php
                        $status_labels = [
                            'need_correction' => ['Needs Correction', 'text-red-600', 'fas fa-exclamation-triangle'],
                            'paying' => ['Payment Required', 'text-purple-600', 'fas fa-money-bill-wave'],
                            'interviewed' => ['Interview Completed', 'text-teal-600', 'fas fa-user-check'],
                            'interview_scheduled' => ['Interview Scheduled', 'text-blue-600', 'fas fa-calendar-check'],
                            'pending' => ['Pending Review', 'text-yellow-600', 'fas fa-clock'],
                            'paid' => ['Payment Completed', 'text-indigo-600', 'fas fa-check-circle'],
                            'resubmitted' => ['Resubmitted', 'text-orange-600', 'fas fa-redo'],
                            'approved' => ['Approved', 'text-green-600', 'fas fa-thumbs-up'],
                            'rejected' => ['Rejected', 'text-gray-600', 'fas fa-times-circle']
                        ];
                        
                        foreach ($status_labels as $key => $label_info):
                            if ($status_counts[$key] > 0):
                        ?>
                        <a href="market_application/<?php echo $key; ?>.php"
                           class="flex justify-between items-center status-item">
                            <div class="flex items-center">
                                <i class="<?php echo $label_info[2]; ?> <?php echo $label_info[1]; ?> mr-3 text-sm"></i>
                                <span class="text-sm font-medium <?php echo $label_info[1]; ?>">
                                    <?php echo $label_info[0]; ?>
                                </span>
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-xs mr-2 <?php echo $label_info[1]; ?>"><?php echo $status_counts[$key]; ?></span>
                                <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                            </div>
                        </a>
                        <?php endif; endforeach; ?>
                    <?php else: ?>
                    <!-- No applications yet -->
                    <div class="flex justify-between items-center status-item">
                        <div class="flex items-center">
                            <i class="fas fa-inbox text-gray-400 mr-3 text-sm"></i>
                            <span class="text-sm font-medium text-gray-500">No applications yet</span>
                        </div>
                        <div class="flex items-center">
                            <span class="font-semibold text-xs text-gray-400 mr-2">0</span>
                            <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- "Check Status" link -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <span class="font-medium text-orange-600">Check Status</span>
                    <a href="market_application/<?php echo $view_all_page; ?>" class="flex items-center">
                        <i class="fas fa-arrow-right service-arrow text-orange-600"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- RENT PAYMENT CARD - CHANGED FROM h-40 TO h-48 -->
        <a href="market_rent_payment.php"
           class="service-card group bg-white block">
            <div class="h-48 overflow-hidden relative">
                <?php 
                $payment_image = 'images/market-payment.png';
                if (file_exists($payment_image)): ?>
                    <img src="<?php echo $payment_image; ?>" alt="Market Rent Payment" 
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(76, 175, 80, 0.1);">
                        <i class="fas fa-credit-card text-5xl" style="color: rgba(76, 175, 80, 0.3);"></i>
                    </div>
                <?php endif; ?>
                <div class="absolute top-4 right-4 px-3 py-1 rounded-lg bg-white/90">
                    <span class="text-xs font-semibold uppercase" style="color: var(--accent);">Payment</span>
                </div>
            </div>
            <div class="p-5">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: var(--accent);">
                        <i class="fas fa-credit-card text-white"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Market Rent Payment</h3>
                </div>
                <p class="text-gray-600 text-sm mb-6 leading-relaxed">
                    Pay monthly rental fees and download official receipts online.
                </p>
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <span class="font-medium" style="color: var(--accent);">Pay Rent</span>
                    <i class="fas fa-arrow-right service-arrow" style="color: var(--accent);"></i>
                </div>
            </div>
        </a>

    </div>

    <!-- APPLICATION SUMMARY -->
    <?php if ($total_applications > 0): ?>
    <div class="status-card p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-6 section-header">
            Your Applications Summary
        </h3>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="stats-box" style="background-color: rgba(74, 144, 226, 0.08);">
                <div class="stats-value" style="color: var(--primary);"><?php echo $total_applications; ?></div>
                <div class="stats-label" style="color: var(--primary);">Total Applications</div>
            </div>
            
            <?php if ($status_counts['need_correction'] > 0): ?>
            <div class="stats-box" style="background-color: rgba(239, 68, 68, 0.08);">
                <div class="stats-value text-red-600"><?php echo $status_counts['need_correction']; ?></div>
                <div class="stats-label text-red-600">Need Correction</div>
            </div>
            <?php elseif ($status_counts['interviewed'] > 0): ?>
            <div class="stats-box" style="background-color: rgba(20, 184, 166, 0.08);">
                <div class="stats-value text-teal-600"><?php echo $status_counts['interviewed']; ?></div>
                <div class="stats-label text-teal-600">Interview Completed</div>
            </div>
            <?php else: ?>
            <div class="stats-box" style="background-color: rgba(34, 197, 94, 0.08);">
                <div class="stats-value text-green-600"><?php echo $status_counts['approved']; ?></div>
                <div class="stats-label text-green-600">Approved</div>
            </div>
            <?php endif; ?>
            
            <?php if ($status_counts['paying'] > 0): ?>
            <div class="stats-box" style="background-color: rgba(168, 85, 247, 0.08);">
                <div class="stats-value text-purple-600"><?php echo $status_counts['paying']; ?></div>
                <div class="stats-label text-purple-600">Payment Required</div>
            </div>
            <?php elseif ($status_counts['interview_scheduled'] > 0): ?>
            <div class="stats-box" style="background-color: rgba(59, 130, 246, 0.08);">
                <div class="stats-value text-blue-600"><?php echo $status_counts['interview_scheduled']; ?></div>
                <div class="stats-label text-blue-600">Interview Scheduled</div>
            </div>
            <?php else: ?>
            <div class="stats-box" style="background-color: rgba(234, 179, 8, 0.08);">
                <div class="stats-value text-yellow-600"><?php echo $status_counts['pending']; ?></div>
                <div class="stats-label text-yellow-600">In Progress</div>
            </div>
            <?php endif; ?>
            
            <div class="stats-box" style="background-color: rgba(99, 102, 241, 0.08);">
                <div class="stats-value text-indigo-600"><?php echo $status_counts['paid']; ?></div>
                <div class="stats-label text-indigo-600">Paid</div>
            </div>
        </div>
        
        <div class="mt-6 text-center">
            <a href="market_application/<?php echo $view_all_page; ?>" 
               class="inline-flex items-center px-5 py-2.5 rounded-lg font-medium"
               style="background-color: rgba(74, 144, 226, 0.1); color: var(--primary);">
                <i class="fas fa-list mr-2"></i>
                View Detailed Application Status
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- INFORMATION SECTION -->
    <div class="status-card p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-6 section-header">
            About Market Rent Services
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <h4 class="font-medium text-gray-800 mb-4">Market Stall Rental Process</h4>
                <ul class="process-list">
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Apply for stall and submit required documents</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span><strong>Interview Scheduled:</strong> Attend interview with market administrator</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span><strong>Interview Completed:</strong> Wait for evaluation and next steps</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Pay stall rights fee and security bond</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Sign contract and receive stall assignment</span>
                    </li>
                </ul>
            </div>

            <div>
                <h4 class="font-medium text-gray-800 mb-4">Need Assistance?</h4>
                <div>
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <span>market@lgu.gov.ph</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <span>(02) 1234-5680</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <span>Mon-Fri: 8AM - 5PM</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Market Office, Public Market Building</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>

<!-- SIMPLE FOOTER -->
<footer class="bg-white border-t border-gray-200 mt-12">
    <div class="container mx-auto px-6 py-8 max-w-7xl">
        <div class="flex flex-col md:flex-row justify-between items-start gap-8">
            <!-- Brand -->
            <div class="flex-1">
                <div class="mb-4">
                    <span class="text-xl font-semibold" style="color: var(--primary);">
                        LGU Market Services
                    </span>
                </div>
                <p class="text-gray-600 text-sm leading-relaxed">
                    Official market stall rental and management system.
                </p>
            </div>
            
            <!-- Contact -->
            <div class="flex-1">
                <h4 class="font-medium text-gray-800 mb-3">Contact Information</h4>
                <div class="space-y-2 text-sm text-gray-600">
                    <div><i class="fas fa-phone text-gray-400 mr-2"></i> (02) 1234-5678</div>
                    <div><i class="fas fa-envelope text-gray-400 mr-2"></i> market@lgu.gov.ph</div>
                    <div><i class="fas fa-clock text-gray-400 mr-2"></i> Mon-Fri: 8AM - 5PM</div>
                    <div><i class="fas fa-map-marker-alt text-gray-400 mr-2"></i> Public Market Building</div>
                </div>
            </div>
        </div>
        
        <!-- Copyright -->
        <div class="border-t border-gray-200 mt-6 pt-6">
            <p class="text-xs text-gray-500 text-center">
                &copy; <?php echo date('Y'); ?> Local Government Unit. All rights reserved.
            </p>
        </div>
    </div>
</footer>

<script>
// Debug logging
console.log('Market Services Loaded');
console.log('View All Page: <?php echo $view_all_page; ?>');
console.log('Total Applications: <?php echo $total_applications; ?>');
</script>
</body>
</html>