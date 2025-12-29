<?php
// revenue2/citizen_dashboard/rpt/rpt_services.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../index.php');
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Citizen';
$user_id = $_SESSION['user_id'];

// Include database connection with correct relative path
require_once '../../db/RPT/rpt_db.php';
$pdo = getDatabaseConnection();

// Determine user's application status
$status_counts = [
    'pending' => 0,
    'for_inspection' => 0,
    'needs_correction' => 0,
    'resubmitted' => 0,
    'assessed' => 0,
    'approved' => 0,
    'rejected' => 0
];

$total_applications = 0;

try {
    // Get user's application status counts
    $stmt = $pdo->prepare("
        SELECT 
            pr.status,
            COUNT(*) as count
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE po.user_id = ?
        GROUP BY pr.status
    ");
    $stmt->execute([$user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $status = $row['status'];
        $count = $row['count'];
        $total_applications += $count;
        
        if (isset($status_counts[$status])) {
            $status_counts[$status] = $count;
        }
    }
    
} catch (PDOException $e) {
    // Silently handle error
    error_log("Database error in rpt_services.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPT Services - GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .notification {
            animation: slideDown 0.5s ease-out;
            position: relative;
        }
        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Navbar with correct path -->
    <?php include '../../citizen_dashboard/navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">
        <!-- Page Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center mb-4">
                <!-- Back to citizen dashboard -->
                <a href="../../citizen_dashboard/citizen_dashboard.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Real Property Tax Services</h1>
                    <p class="text-gray-600">Manage your property taxes and related services</p>
                </div>
            </div>
        </div>

        <!-- Status Notifications -->
        <?php if ($status_counts['needs_correction'] > 0): ?>
        <div class="notification mb-8 bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-red-800">
                        Action Required: You have <?php echo $status_counts['needs_correction']; ?> application(s) needing correction
                    </h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p>Please review the assessor's notes and make the required corrections.</p>
                    </div>
                    <div class="mt-3">
                        <a href="rpt_application/needs_correction.php" 
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <i class="fas fa-edit mr-2"></i>
                            Review Applications
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($status_counts['pending'] > 0): ?>
        <div class="notification mb-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-clock text-blue-500 text-xl"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-blue-800">
                        You have <?php echo $status_counts['pending']; ?> pending application(s)
                    </h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>Your applications are waiting for initial review.</p>
                    </div>
                    <div class="mt-3">
                        <a href="rpt_application/pending.php" 
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-eye mr-2"></i>
                            View Applications
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($status_counts['for_inspection'] > 0): ?>
        <div class="notification mb-8 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-search text-yellow-500 text-xl"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-yellow-800">
                        Inspection Scheduled: <?php echo $status_counts['for_inspection']; ?> application(s)
                    </h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Your properties are scheduled for inspection.</p>
                    </div>
                    <div class="mt-3">
                        <a href="rpt_application/inspection.php" 
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            View Inspection Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($status_counts['resubmitted'] > 0): ?>
        <div class="notification mb-8 bg-purple-50 border border-purple-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-redo text-purple-500 text-xl"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-purple-800">
                        Resubmitted: <?php echo $status_counts['resubmitted']; ?> application(s)
                    </h3>
                    <div class="mt-2 text-sm text-purple-700">
                        <p>Your corrected applications are under review.</p>
                    </div>
                    <div class="mt-3">
                        <a href="rpt_application/resubmitted.php" 
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            <i class="fas fa-sync-alt mr-2"></i>
                            View Resubmissions
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($status_counts['assessed'] > 0): ?>
        <div class="notification mb-8 bg-indigo-50 border border-indigo-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-calculator text-indigo-500 text-xl"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-indigo-800">
                        Assessed: <?php echo $status_counts['assessed']; ?> application(s)
                    </h3>
                    <div class="mt-2 text-sm text-indigo-700">
                        <p>Your properties have been assessed and are awaiting final approval.</p>
                    </div>
                    <div class="mt-3">
                        <a href="rpt_application/assessed.php" 
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-chart-bar mr-2"></i>
                            View Assessment
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($status_counts['approved'] > 0): ?>
        <div class="notification mb-8 bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-green-800">
                        Approved: <?php echo $status_counts['approved']; ?> application(s)
                    </h3>
                    <div class="mt-2 text-sm text-green-700">
                        <p>Your applications have been approved. You can now pay property taxes.</p>
                    </div>
                    <div class="mt-3">
                        <a href="rpt_tax_payment/rpt_tax_payment.php" 
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class="fas fa-credit-card mr-2"></i>
                            View Approved & Pay Taxes
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($status_counts['rejected'] > 0): ?>
        <div class="notification mb-8 bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-times-circle text-gray-500 text-xl"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-gray-800">
                        Rejected: <?php echo $status_counts['rejected']; ?> application(s)
                    </h3>
                    <div class="mt-2 text-sm text-gray-700">
                        <p>Some applications were rejected. Please review the reasons.</p>
                    </div>
                    <div class="mt-3">
                        <a href="rpt_application/rejected.php" 
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            <i class="fas fa-file-excel mr-2"></i>
                            View Rejected
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- RPT Services Cards - 3 cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Registration and Transfer Card -->
            <a href="rpt_registration/rpt_registration.php" class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-purple-500 hover:scale-105 cursor-pointer block">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-alt text-purple-600 text-xl"></i>
                    </div>
                    <span class="bg-purple-100 text-purple-600 text-xs font-semibold px-2 py-1 rounded">Registration</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Registration and Transfer</h3>
                <p class="text-gray-600 text-sm mb-4">Register new properties, transfer ownership, and update property records.</p>
                <div class="flex items-center justify-between mt-4">
                    <span class="text-purple-600 text-sm font-medium">Start Process →</span>
                    <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-right text-purple-600 text-sm"></i>
                    </div>
                </div>
            </a>

            <!-- Application Status Card -->
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tasks text-yellow-600 text-xl"></i>
                    </div>
                    <span class="bg-yellow-100 text-yellow-600 text-xs font-semibold px-2 py-1 rounded">View Applications</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Application Status</h3>
                <p class="text-gray-600 text-sm mb-4">View your applications by status. Click on any status below to see details.</p>
                
                <!-- Application Status Links -->
                <div class="space-y-3 mt-4">
                    <?php foreach ($status_counts as $status => $count): ?>
                        <?php if ($count > 0): ?>
                            <?php 
                            $colors = [
                                'pending' => 'blue',
                                'for_inspection' => 'yellow',
                                'needs_correction' => 'red',
                                'resubmitted' => 'purple',
                                'assessed' => 'indigo',
                                'approved' => 'green',
                                'rejected' => 'gray'
                            ];
                            $color = $colors[$status] ?? 'gray';
                            ?>
                            <a href="rpt_application/<?php echo $status; ?>.php" 
                               class="flex items-center justify-between p-3 bg-<?php echo $color; ?>-50 border border-<?php echo $color; ?>-200 rounded-lg hover:bg-<?php echo $color; ?>-100 transition-colors">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full bg-<?php echo $color; ?>-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-circle text-<?php echo $color; ?>-500 text-xs"></i>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 capitalize"><?php echo str_replace('_', ' ', $status); ?></span>
                                </div>
                                <div class="flex items-center">
                                    <span class="text-sm font-bold text-<?php echo $color; ?>-600 mr-2"><?php echo $count; ?></span>
                                    <i class="fas fa-chevron-right text-<?php echo $color; ?>-400 text-xs"></i>
                                </div>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <?php if ($total_applications === 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox text-gray-300 text-3xl mb-2"></i>
                            <p class="text-gray-500 text-sm">No applications yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RPT Tax Payment Card -->
            <a href="rpt_tax_payment/rpt_tax_payment.php" class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-blue-500 hover:scale-105 cursor-pointer block">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-credit-card text-blue-600 text-xl"></i>
                    </div>
                    <span class="bg-blue-100 text-blue-600 text-xs font-semibold px-2 py-1 rounded">Payment</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">RPT Tax Payment</h3>
                <p class="text-gray-600 text-sm mb-4">Pay your real property taxes online, view tax assessments, and download receipts.</p>
                <div class="flex items-center justify-between mt-4">
                    <span class="text-blue-600 text-sm font-medium">Make Payment →</span>
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-right text-blue-600 text-sm"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- Quick Stats -->
        <?php if ($total_applications > 0): ?>
        <div class="mt-12 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Your Applications Overview</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-gray-800"><?php echo $total_applications; ?></div>
                    <div class="text-sm text-gray-600">Total Applications</div>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo ($status_counts['pending'] + $status_counts['for_inspection'] + $status_counts['resubmitted'] + $status_counts['assessed']); ?></div>
                    <div class="text-sm text-blue-600">In Progress</div>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-red-600"><?php echo $status_counts['needs_correction']; ?></div>
                    <div class="text-sm text-red-600">Need Correction</div>
                </div>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600"><?php echo $status_counts['approved']; ?></div>
                    <div class="text-sm text-green-600">Approved</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- No Applications Message -->
        <?php if ($total_applications === 0): ?>
        <div class="mt-12 bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-400 mb-6">
                <i class="fas fa-home text-6xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-700 mb-3">No Property Applications Yet</h3>
            <p class="text-gray-600 mb-6">You haven't submitted any property tax applications yet.</p>
            <a href="rpt_registration/rpt_registration.php" 
               class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300">
                <i class="fas fa-plus mr-2"></i>
                Submit Your First Application
            </a>
        </div>
        <?php endif; ?>

        <!-- Additional Information -->
        <div class="mt-12 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">About Real Property Tax Services</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">What is Real Property Tax?</h4>
                    <p class="text-gray-600 text-sm">Real Property Tax (RPT) is a tax on real property such as lands, buildings, and other improvements. It is imposed by the local government unit where the property is located.</p>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">Need Help?</h4>
                    <p class="text-gray-600 text-sm">For assistance with RPT services, contact our support team at <span class="text-blue-600">rpt-support@goserveph.gov.ph</span> or call (02) 1234-5678.</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>