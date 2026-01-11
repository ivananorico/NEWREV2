<?php
// market_services.php - FIXED VERSION
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Citizen';
$user_id = $_SESSION['user_id'];

// Include database connection
include_once '../../db/Market/market_db.php';

// Status counters
$status_counts = [
    'pending' => 0,
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
        $status_counts[$row['application_status']] = $row['count'];
        $total_applications += $row['count'];
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

// Determine which page to use for "View All" based on priority
$view_all_page = 'pending.php'; // Default
$priority_order = [
    'need_correction' => 1,
    'paying' => 2,
    'pending' => 3,
    'interviewed' => 4,
    'resubmitted' => 5,
    'approved' => 6,
    'paid' => 7,
    'rejected' => 8
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
    <title>Market Rent Services | GoServePH</title>
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
            font-family: Inter, system-ui, sans-serif;
        }

        .service-card {
            transition: all 0.3s ease;
        }
        
        .service-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 28px rgba(74, 144, 226, 0.15);
        }
        
        .service-card:hover .service-arrow {
            transform: translateX(8px);
        }
        
        .service-arrow {
            transition: transform 0.3s ease;
        }

        .lgu-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: all 0.25s ease;
        }

        .lgu-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 18px rgba(0,0,0,0.08);
        }

        .section-header {
            border-left: 5px solid var(--primary);
            padding-left: 1rem;
        }

        .notification-slide {
            animation: slideIn .3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .urgent-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
    </style>
</head>

<body class="flex flex-col min-h-screen">
<?php include '../../citizen_dashboard/navbar.php'; ?>

<main class="container mx-auto px-6 py-10 flex-grow max-w-7xl">

    <!-- Welcome Back Section -->
    <div class="mb-8">
        <div class="flex items-center">
            <a href="../citizen_dashboard.php" 
               class="inline-flex items-center text-gray-600 hover:text-[var(--primary)] mr-6">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-4xl font-bold text-gray-900 mb-2">
                    Market Rent Services
                </h1>
                <p class="text-gray-600 text-lg">
                    Official market stall rental and payment services
                </p>
            </div>
        </div>
        <div class="h-1 w-20 rounded-full mt-4" style="background-color: #ff9800;"></div>
    </div>

    <!-- PRIORITY NOTIFICATIONS -->
    <?php if ($status_counts['need_correction'] > 0): ?>
    <div class="notification-slide lgu-card border-l-4 border-red-500 p-6 mb-6 bg-red-50 urgent-badge">
        <div class="flex items-start gap-4">
            <i class="fas fa-triangle-exclamation text-red-500 text-2xl mt-1"></i>
            <div class="flex-1">
                <h3 class="font-semibold text-red-800 mb-2 text-lg">🚨 ACTION REQUIRED</h3>
                <p class="text-red-700 mb-3">
                    You have <strong class="text-xl"><?php echo $status_counts['need_correction']; ?></strong> application(s) requiring correction.
                    <span class="block text-sm mt-1 text-red-600">
                        <i class="fas fa-info-circle"></i> Please fix these applications to proceed
                    </span>
                </p>
                <a href="market_application/need_correction.php"
                   class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-6 rounded-lg transition duration-300 flex items-center w-fit">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    Fix Applications Now
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($status_counts['paying'] > 0 && $status_counts['need_correction'] == 0): ?>
    <div class="notification-slide lgu-card border-l-4 border-purple-500 p-6 mb-6 bg-purple-50">
        <div class="flex items-start gap-4">
            <i class="fas fa-money-bill-wave text-purple-500 text-2xl mt-1"></i>
            <div class="flex-1">
                <h3 class="font-semibold text-purple-800 mb-2 text-lg">Payment Required</h3>
                <p class="text-purple-700 mb-3">
                    You have <strong><?php echo $status_counts['paying']; ?></strong> application(s) ready for payment.
                </p>
                <a href="market_application/paying.php"
                   class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-6 rounded-lg transition duration-300 flex items-center w-fit">
                    <i class="fas fa-credit-card mr-2"></i>
                    Proceed to Payment
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MAIN SERVICES GRID -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
        
        <!-- RENT STALL CARD -->
        <a href="market_portal_services/market_portal_services.php"
           class="service-card group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden block">
            <div class="h-48 overflow-hidden relative">
                <?php 
                $rent_image = 'images/market-portal.png';
                if (file_exists($rent_image)): ?>
                    <img src="<?php echo $rent_image; ?>" alt="Rent a Market Stall" 
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(74, 144, 226, 0.1);">
                        <i class="fas fa-store text-6xl" style="color: rgba(74, 144, 226, 0.3);"></i>
                    </div>
                <?php endif; ?>
                <div class="absolute top-4 right-4 px-3 py-1.5 rounded-lg shadow-sm" style="background-color: rgba(255, 255, 255, 0.95);">
                    <span class="text-xs font-semibold uppercase" style="color: #4a90e2;">Rental</span>
                </div>
            </div>
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: #4a90e2;">
                        <i class="fas fa-store text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Rent a Market Stall</h3>
                </div>
                <p class="text-gray-600 leading-relaxed mb-6">
                    Apply for public market stall rental and select your preferred location.
                </p>
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <span class="font-semibold" style="color: #4a90e2;">Apply Now</span>
                    <i class="fas fa-arrow-right service-arrow" style="color: #4a90e2;"></i>
                </div>
            </div>
        </a>

        <!-- APPLICATION STATUS CARD -->
        <div class="service-card group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="h-48 overflow-hidden relative">
                <?php 
                $status_image = 'images/application-status.png';
                if (file_exists($status_image)): ?>
                    <img src="<?php echo $status_image; ?>" alt="Application Status" 
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(154, 165, 177, 0.1);">
                        <i class="fas fa-chart-bar text-6xl" style="color: rgba(154, 165, 177, 0.3);"></i>
                    </div>
                <?php endif; ?>
                <div class="absolute top-4 right-4 px-3 py-1.5 rounded-lg shadow-sm" style="background-color: rgba(255, 255, 255, 0.95);">
                    <span class="text-xs font-semibold uppercase text-orange-600">Status</span>
                </div>
            </div>
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-bar text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Application Status</h3>
                </div>
                <p class="text-gray-600 leading-relaxed mb-4">Track your submitted applications.</p>
                
                <?php if ($total_applications > 0): ?>
                <div class="space-y-3 mt-4">
                    <?php
                    $status_labels = [
                        'need_correction' => ['Needs Correction', 'text-red-600', 'fas fa-exclamation-triangle'],
                        'paying' => ['Payment Required', 'text-purple-600', 'fas fa-money-bill-wave'],
                        'pending' => ['Pending Interview', 'text-yellow-600', 'fas fa-clock'],
                        'interviewed' => ['Interview Completed', 'text-yellow-600', 'fas fa-user-check'],
                        'paid' => ['Payment Completed', 'text-indigo-600', 'fas fa-check-circle'],
                        'resubmitted' => ['Resubmitted', 'text-orange-600', 'fas fa-redo'],
                        'approved' => ['Approved', 'text-green-600', 'fas fa-thumbs-up'],
                        'rejected' => ['Rejected', 'text-gray-600', 'fas fa-times-circle']
                    ];
                    
                    foreach ($status_labels as $key => $label_info):
                        if ($status_counts[$key] > 0):
                            $is_urgent = ($key == 'need_correction') ? 'border-l-4 border-red-500 bg-red-50' : '';
                    ?>
                    <a href="market_application/<?php echo $key; ?>.php"
                       class="flex justify-between items-center px-4 py-3 border rounded-lg hover:bg-gray-50 transition-colors <?php echo $is_urgent; ?>">
                        <div class="flex items-center">
                            <i class="<?php echo $label_info[2]; ?> <?php echo $label_info[1]; ?> mr-3"></i>
                            <span class="text-gray-700 <?php echo $key == 'need_correction' ? 'font-semibold' : ''; ?>">
                                <?php echo $label_info[0]; ?>
                            </span>
                        </div>
                        <div class="flex items-center">
                            <span class="font-semibold <?php echo $label_info[1]; ?> mr-2"><?php echo $status_counts[$key]; ?></span>
                            <i class="fas fa-chevron-right text-gray-400 text-sm"></i>
                        </div>
                    </a>
                    <?php endif; endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="text-center py-8 border-t border-gray-100 mt-4">
                        <i class="fas fa-inbox text-gray-300 text-4xl mb-3"></i>
                        <p class="text-gray-500">No applications yet</p>
                        <a href="market_portal_services/market_portal_services.php" 
                           class="inline-block mt-3 text-blue-600 hover:text-blue-800 font-medium">
                            <i class="fas fa-plus mr-1"></i> Apply for a stall
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- FIXED: This link now goes to the highest priority status page -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100 mt-4">
                    <a href="market_application/<?php echo $view_all_page; ?>" 
                       class="font-semibold text-orange-600 hover:underline flex items-center">
                        <i class="fas fa-list mr-2"></i>
                        View All Applications
                        <?php if ($status_counts['need_correction'] > 0): ?>
                        <span class="ml-2 bg-red-100 text-red-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                            <?php echo $status_counts['need_correction']; ?> need attention
                        </span>
                        <?php endif; ?>
                    </a>
                    <a href="market_application/<?php echo $view_all_page; ?>" class="flex items-center">
                        <i class="fas fa-arrow-right service-arrow text-orange-600"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- RENT PAYMENT CARD -->
        <a href="market_rent_payment.php"
           class="service-card group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden block">
            <div class="h-48 overflow-hidden relative">
                <?php 
                $payment_image = 'images/market-payment.png';
                if (file_exists($payment_image)): ?>
                    <img src="<?php echo $payment_image; ?>" alt="Market Rent Payment" 
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(76, 175, 80, 0.1);">
                        <i class="fas fa-credit-card text-6xl" style="color: rgba(76, 175, 80, 0.3);"></i>
                    </div>
                <?php endif; ?>
                <div class="absolute top-4 right-4 px-3 py-1.5 rounded-lg shadow-sm" style="background-color: rgba(255, 255, 255, 0.95);">
                    <span class="text-xs font-semibold uppercase" style="color: #4caf50;">Payment</span>
                </div>
            </div>
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: #4caf50;">
                        <i class="fas fa-credit-card text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Market Rent Payment</h3>
                </div>
                <p class="text-gray-600 leading-relaxed mb-6">
                    Pay monthly stall rental fees and download official receipts.
                </p>
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <span class="font-semibold" style="color: #4caf50;">Pay Rent</span>
                    <i class="fas fa-arrow-right service-arrow" style="color: #4caf50;"></i>
                </div>
            </div>
        </a>

    </div>

    <!-- APPLICATION SUMMARY -->
    <?php if ($total_applications > 0): ?>
    <div class="lgu-card p-8 mb-8">
        <h3 class="text-xl font-semibold text-gray-800 mb-6 section-header">
            Your Applications Summary
        </h3>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <div class="text-3xl font-bold text-blue-600"><?php echo $total_applications; ?></div>
                <div class="text-sm text-blue-800 font-medium">Total Applications</div>
            </div>
            
            <?php if ($status_counts['need_correction'] > 0): ?>
            <div class="text-center p-4 bg-red-50 rounded-lg">
                <div class="text-3xl font-bold text-red-600"><?php echo $status_counts['need_correction']; ?></div>
                <div class="text-sm text-red-800 font-medium">Need Correction</div>
            </div>
            <?php else: ?>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <div class="text-3xl font-bold text-green-600"><?php echo $status_counts['approved']; ?></div>
                <div class="text-sm text-green-800 font-medium">Approved</div>
            </div>
            <?php endif; ?>
            
            <?php if ($status_counts['paying'] > 0): ?>
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <div class="text-3xl font-bold text-purple-600"><?php echo $status_counts['paying']; ?></div>
                <div class="text-sm text-purple-800 font-medium">Payment Required</div>
            </div>
            <?php else: ?>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <div class="text-3xl font-bold text-yellow-600"><?php echo $status_counts['pending'] + $status_counts['interviewed']; ?></div>
                <div class="text-sm text-yellow-800 font-medium">In Progress</div>
            </div>
            <?php endif; ?>
            
            <div class="text-center p-4 bg-indigo-50 rounded-lg">
                <div class="text-3xl font-bold text-indigo-600"><?php echo $status_counts['paid']; ?></div>
                <div class="text-sm text-indigo-800 font-medium">Paid</div>
            </div>
        </div>
        
        <div class="mt-6 text-center">
            <!-- FIXED: This link now goes to the highest priority status page -->
            <a href="market_application/<?php echo $view_all_page; ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                <i class="fas fa-list mr-2"></i>
                View Detailed Application Status
                <?php if ($status_counts['need_correction'] > 0): ?>
                <span class="ml-2 bg-red-100 text-red-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                    Action Required
                </span>
                <?php endif; ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- INFORMATION SECTION -->
    <div class="lgu-card p-8 mb-8">
        <h3 class="text-xl font-semibold text-gray-800 mb-6 section-header">
            About Market Rent Services
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6">
            <div>
                <h4 class="font-medium text-gray-800 mb-2">
                    Market Stall Rental Process
                </h4>
                <ul class="space-y-2 text-gray-600">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Apply for stall and submit required documents</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Attend interview with market administrator</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Pay stall rights fee and security bond</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <span>Sign contract and receive stall assignment</span>
                    </li>
                </ul>
            </div>

            <div>
                <h4 class="font-medium text-gray-800 mb-2">
                    Need Assistance?
                </h4>
                <div class="space-y-3 text-gray-600">
                    <p><i class="fas fa-envelope mr-2 text-[var(--primary)]"></i> market-support@goserveph.gov.ph</p>
                    <p><i class="fas fa-phone mr-2 text-[var(--primary)]"></i> (02) 1234-5680</p>
                    <p><i class="fas fa-clock mr-2 text-[var(--primary)]"></i> Mon-Fri: 8AM - 5PM</p>
                    <p><i class="fas fa-map-marker-alt mr-2 text-[var(--primary)]"></i> Market Office, Public Market Building</p>
                </div>
            </div>
        </div>
    </div>

</main>

<!-- FOOTER -->
<footer class="bg-white border-t border-gray-200 mt-16">
    <div class="container mx-auto px-6 py-12 max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
            <!-- Brand -->
            <div class="col-span-1">
                <div class="flex items-center space-x-2 mb-4 text-2xl font-bold">
                    <span style="color: #4a90e2;">Go</span>
                    <span style="color: #4caf50;">Serve</span>
                    <span style="color: #4a90e2;">PH</span>
                </div>
                <p class="text-gray-600 leading-relaxed">
                    The official digital gateway of your Local Government Unit, providing efficient and transparent government services.
                </p>
            </div>
            
            <!-- Portal Links -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Portal</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><a href="../citizen_dashboard.php" class="hover:text-[#4a90e2] transition-colors">Dashboard</a></li>
                    <!-- FIXED: This link now goes to the highest priority status page -->
                    <li><a href="market_application/<?php echo $view_all_page; ?>" class="hover:text-[#4a90e2] transition-colors">My Applications</a></li>
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
                &copy; 2026 GoServePH Local Government Unit. Republic of the Philippines.
            </p>
        </div>
    </div>
</footer>

<script>
// Debug logging
console.log('Market Services Loaded');
console.log('View All Page: <?php echo $view_all_page; ?>');
console.log('Need Correction Count: <?php echo $status_counts['need_correction']; ?>');
console.log('Paying Count: <?php echo $status_counts['paying']; ?>');

// Track clicks for debugging
document.querySelectorAll('a[href*="market_application/"]').forEach(link => {
    link.addEventListener('click', function(e) {
        console.log('Clicked link to:', this.getAttribute('href'));
    });
});
</script>
</body>
</html>