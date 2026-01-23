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

// Include database connection
require_once '../../db/RPT/rpt_db.php';
$pdo = getDatabaseConnection();

// Status counters
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
$has_active_property = false;

try {
    // Get all application status counts
    $stmt = $pdo->prepare("
        SELECT pr.status, COUNT(*) as count
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE po.user_id = ?
        GROUP BY pr.status
    ");
    $stmt->execute([$user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $status_counts[$row['status']] = $row['count'];
        $total_applications += $row['count'];
    }
    
    // Check if user has any APPROVED property (active property)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE po.user_id = ? AND pr.status = 'approved'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['count'] > 0) {
        $has_active_property = true;
    }
    
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Get the base URL for the background image
$base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
$bg_image_path = $base_url . '/revenue2/Login/images/gsmbg.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Real Property Tax Services | GoServePH</title>
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
            background: linear-gradient(135deg, rgba(240, 240, 240, 0.4) 0%, rgba(230, 230, 230, 0.4) 50%, rgba(220, 220, 220, 0.3) 100%);
            position: relative;
            overflow-x: hidden;
            min-height: 100vh;
            font-family: Inter, system-ui, sans-serif;
        }

        /* Background image with blur - ABSOLUTE PATH */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('<?php echo $bg_image_path; ?>') center/cover no-repeat;
            opacity: 0.08;
            pointer-events: none;
            z-index: -2;
            filter: blur(1px);
        }
        
        /* Animated background particles */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(76, 175, 80, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(74, 144, 226, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(253, 168, 17, 0.05) 0%, transparent 50%);
            animation: backgroundFloat 20s ease-in-out infinite;
            z-index: -1;
        }
        
        @keyframes backgroundFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(1deg); }
            66% { transform: translateY(10px) rotate(-1deg); }
        }

        .service-card {
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
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

        .header-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border-radius: 20px;
            padding: 1.5rem;
        }

        .lgu-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 0.75rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: all .25s ease;
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

        .status-list-container {
            flex-grow: 1;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body class="flex flex-col min-h-screen">
<?php include '../../citizen_dashboard/navbar.php'; ?>

<main class="container mx-auto px-6 py-10 flex-grow max-w-7xl">

    <!-- Welcome Section in Box -->
    <div class="header-box mb-12">
        <div class="flex items-center">
            <a href="../../citizen_dashboard/citizen_dashboard.php" 
               class="inline-flex items-center text-gray-600 hover:text-[var(--primary)] mr-6">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-4xl font-bold text-gray-900 mb-2">
                    Real Property Tax Services
                </h1>
                <p class="text-gray-600 text-lg">
                    Official property registration and tax payment services
                </p>
            </div>
        </div>
        <div class="h-1 w-20 rounded-full mt-4" style="background-color: #4a90e2;"></div>
    </div>

    <!-- PRIORITY NOTIFICATIONS -->
    <?php if ($status_counts['needs_correction'] > 0): ?>
    <div class="notification-slide lgu-card border-l-4 border-red-500 p-6 mb-6 bg-red-50">
        <div class="flex items-start gap-4">
            <i class="fas fa-triangle-exclamation text-red-500 text-2xl mt-1"></i>
            <div class="flex-1">
                <h3 class="font-semibold text-red-800 mb-2 text-lg">Action Required</h3>
                <p class="text-red-700 mb-3">
                    You have <strong><?= $status_counts['needs_correction'] ?></strong> application(s) requiring correction.
                </p>
                <a href="rpt_application/needs_correction.php"
                   class="font-medium text-red-700 hover:underline flex items-center">
                    Review Applications
                    <i class="fas fa-arrow-right ml-2 service-arrow"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($status_counts['approved'] > 0): ?>
    <div class="notification-slide lgu-card border-l-4 border-[var(--accent)] p-6 mb-8 bg-green-50">
        <div class="flex items-start gap-4">
            <i class="fas fa-circle-check text-[var(--accent)] text-2xl mt-1"></i>
            <div class="flex-1">
                <h3 class="font-semibold text-green-800 mb-2 text-lg">Approved Applications</h3>
                <p class="text-green-700 mb-3">
                    <strong><?= $status_counts['approved'] ?></strong> property application(s) are approved and ready for payment.
                </p>
                <a href="rpt_tax_payment/rpt_tax_payment.php"
                   class="font-medium text-green-700 hover:underline flex items-center">
                    Proceed to Payment
                    <i class="fas fa-arrow-right ml-2 service-arrow"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MAIN SERVICES GRID -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
        
        <!-- REGISTRATION CARD -->
        <?php if ($has_active_property): ?>
        <!-- Registration card with alert on click -->
        <a href="javascript:void(0)" 
           onclick="alert('You already have a registered property.\\n\\nPlease go to Tax Payment to pay your property taxes.');"
           class="service-card group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <?php else: ?>
        <!-- Normal registration card -->
        <a href="rpt_registration/rpt_registration.php" 
           class="service-card group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <?php endif; ?>
            <div class="h-48 overflow-hidden relative">
                <?php 
                $reg_image = 'images/rpt-registration.png';
                if (file_exists($reg_image)): ?>
                    <img src="<?php echo $reg_image; ?>" alt="Property Registration" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(74, 144, 226, 0.1);">
                        <i class="fas fa-file-alt text-6xl" style="color: rgba(74, 144, 226, 0.3);"></i>
                    </div>
                <?php endif; ?>
                <div class="absolute top-4 right-4 px-3 py-1.5 rounded-lg shadow-sm" style="background-color: rgba(255, 255, 255, 0.95);">
                    <span class="text-xs font-semibold uppercase" style="color: #4a90e2;">Registration</span>
                </div>
            </div>
            <div class="p-6 flex flex-col flex-grow">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: #4a90e2;">
                        <i class="fas fa-edit text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Property Registration</h3>
                </div>
                <p class="text-gray-600 leading-relaxed mb-6 flex-grow">
                    Register new properties or update existing records for assessment.
                </p>
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <?php if ($has_active_property): ?>
                        <span class="font-semibold text-gray-400">Already Registered</span>
                        <i class="fas fa-arrow-right service-arrow text-gray-400"></i>
                    <?php else: ?>
                        <span class="font-semibold" style="color: #4a90e2;">Start Registration</span>
                        <i class="fas fa-arrow-right service-arrow" style="color: #4a90e2;"></i>
                    <?php endif; ?>
                </div>
            </div>
        </a>

        <!-- STATUS CARD -->
        <div class="service-card group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="h-48 overflow-hidden relative">
                <?php 
                $status_image = 'images/application-status.png';
                if (file_exists($status_image)): ?>
                    <img src="<?php echo $status_image; ?>" alt="Application Status" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(255, 152, 0, 0.1);">
                        <i class="fas fa-chart-line text-6xl" style="color: rgba(255, 152, 0, 0.3);"></i>
                    </div>
                <?php endif; ?>
                <div class="absolute top-4 right-4 px-3 py-1.5 rounded-lg shadow-sm" style="background-color: rgba(255, 255, 255, 0.95);">
                    <span class="text-xs font-semibold uppercase text-orange-600">Status</span>
                </div>
            </div>
            <div class="p-6 flex flex-col flex-grow">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-line text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Application Status</h3>
                </div>
                <p class="text-gray-600 leading-relaxed mb-4">Track your submitted applications.</p>
                
                <div class="status-list-container">
                    <?php if ($total_applications > 0): ?>
                    <div class="space-y-3">
                        <?php
                        $labels = [
                            'pending' => 'Pending Review',
                            'for_inspection' => 'For Inspection',
                            'needs_correction' => 'Needs Correction',
                            'resubmitted' => 'Resubmitted',
                            'assessed' => 'Assessed',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected'
                        ];
                        foreach ($status_counts as $key => $count):
                            if ($count > 0):
                                $color_class = '';
                                if ($key === 'approved') $color_class = 'text-green-600';
                                elseif ($key === 'needs_correction') $color_class = 'text-red-600';
                                elseif ($key === 'pending') $color_class = 'text-yellow-600';
                                else $color_class = 'text-gray-600';
                        ?>
                        <a href="rpt_application/<?= $key ?>.php"
                           class="flex justify-between items-center px-4 py-3 border rounded-lg hover:bg-gray-50 transition-colors">
                            <span class="text-gray-700"><?= $labels[$key] ?></span>
                            <span class="font-semibold <?= $color_class ?>"><?= $count ?></span>
                        </a>
                        <?php endif; endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex items-center justify-between pt-4 border-t border-gray-100 mt-auto">
                    <span class="font-semibold text-orange-600">View All</span>
                    <i class="fas fa-arrow-right service-arrow text-orange-600"></i>
                </div>
            </div>
        </div>

        <!-- PAYMENT CARD -->
        <a href="rpt_tax_payment/rpt_tax_payment.php" class="service-card group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="h-48 overflow-hidden relative">
                <?php 
                $payment_image = 'images/rpt-payment.png';
                if (file_exists($payment_image)): ?>
                    <img src="<?php echo $payment_image; ?>" alt="Tax Payment" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(76, 175, 80, 0.1);">
                        <i class="fas fa-credit-card text-6xl" style="color: rgba(76, 175, 80, 0.3);"></i>
                    </div>
                <?php endif; ?>
                <div class="absolute top-4 right-4 px-3 py-1.5 rounded-lg shadow-sm" style="background-color: rgba(255, 255, 255, 0.95);">
                    <span class="text-xs font-semibold uppercase" style="color: #4caf50;">Payment</span>
                </div>
            </div>
            <div class="p-6 flex flex-col flex-grow">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: #4caf50;">
                        <i class="fas fa-credit-card text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Tax Payment</h3>
                </div>
                <p class="text-gray-600 leading-relaxed mb-6 flex-grow">
                    Pay approved real property taxes securely online with instant digital receipts.
                </p>
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <span class="font-semibold" style="color: #4caf50;">Pay Now</span>
                    <i class="fas fa-arrow-right service-arrow" style="color: #4caf50;"></i>
                </div>
            </div>
        </a>

    </div>

    <!-- HELP SECTION -->
    <div class="lgu-card p-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 section-header">
            Need Assistance?
        </h3>
        <div class="grid md:grid-cols-2 gap-8 text-gray-600 mt-6">
            <div>
                <p class="mb-3"><i class="fas fa-envelope mr-3 text-[var(--primary)]"></i> rpt-support@goserveph.gov.ph</p>
                <p class="mb-3"><i class="fas fa-phone mr-3 text-[var(--primary)]"></i> (02) 1234-5678</p>
                <p><i class="fas fa-location-dot mr-3 text-[var(--primary)]"></i> RPT Office, 2nd Floor, Municipal Hall</p>
            </div>
            <div>
                <p class="font-semibold text-gray-700 mb-2">Office Hours</p>
                <p class="mb-1">Monday – Friday</p>
                <p class="mb-1">8:00 AM – 5:00 PM</p>
                <p class="text-sm text-gray-500 mt-3">Closed on weekends and holidays</p>
            </div>
        </div>
    </div>

</main>

<footer class="bg-white border-t border-gray-200 mt-16">
    <div class="container mx-auto px-6 py-12 max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
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
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Services</a></li>
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">My Applications</a></li>
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Settings</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><i class="fas fa-phone mr-2 text-gray-400"></i> (02) 8123 4567</li>
                    <li><i class="fas fa-envelope mr-2 text-gray-400"></i> support@goserveph.gov.ph</li>
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

</body>
</html>