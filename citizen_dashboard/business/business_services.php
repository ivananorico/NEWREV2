<?php
// business_services.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Citizen';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Tax Services | GoServePH</title>
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
                    Business Tax Services
                </h1>
                <p class="text-gray-600 text-lg">
                    Official business permit and tax payment services
                </p>
            </div>
        </div>
        <div class="h-1 w-20 rounded-full mt-4" style="background-color: #4caf50;"></div>
    </div>

    <!-- MAIN SERVICES GRID - Centered 2 cards with proper spacing -->
    <div class="flex justify-center mb-12">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 w-full max-w-4xl">
            
            <!-- APPLICATION STATUS CARD -->
            <a href="business_application_status/business_billing.php" 
               class="service-card group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden block">
                <div class="h-48 overflow-hidden relative">
                    <?php 
                    $status_image = 'images/business-billing.png';
                    if (file_exists($status_image)): ?>
                        <img src="<?php echo $status_image; ?>" alt="Business Billing" 
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(74, 144, 226, 0.1);">
                            <i class="fas fa-clipboard-check text-6xl" style="color: rgba(74, 144, 226, 0.3);"></i>
                        </div>
                    <?php endif; ?>
                    <div class="absolute top-4 right-4 px-3 py-1.5 rounded-lg shadow-sm" style="background-color: rgba(255, 255, 255, 0.95);">
                        <span class="text-xs font-semibold uppercase" style="color: #4a90e2;">Tracking</span>
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: #4a90e2;">
                            <i class="fas fa-clipboard-check text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800">Application Status</h3>
                    </div>
                    <p class="text-gray-600 leading-relaxed mb-6">
                        Track approval status and assessment progress of business permits.
                    </p>
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <span class="font-semibold" style="color: #4a90e2;">View Status</span>
                        <i class="fas fa-arrow-right service-arrow" style="color: #4a90e2;"></i>
                    </div>
                </div>
            </a>

            <!-- TAX PAYMENT CARD -->
            <a href="business_tax_payment/business_tax_payment.php" 
               class="service-card group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden block">
                <div class="h-48 overflow-hidden relative">
                    <?php 
                    $payment_image = 'images/business-payment.png';
                    if (file_exists($payment_image)): ?>
                        <img src="<?php echo $payment_image; ?>" alt="Tax Payment" 
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(76, 175, 80, 0.1);">
                            <i class="fas fa-file-invoice-dollar text-6xl" style="color: rgba(76, 175, 80, 0.3);"></i>
                        </div>
                    <?php endif; ?>
                    <div class="absolute top-4 right-4 px-3 py-1.5 rounded-lg shadow-sm" style="background-color: rgba(255, 255, 255, 0.95);">
                        <span class="text-xs font-semibold uppercase" style="color: #4caf50;">Payment</span>
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: #4caf50;">
                            <i class="fas fa-file-invoice-dollar text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800">Tax Payment</h3>
                    </div>
                    <p class="text-gray-600 leading-relaxed mb-6">
                        Pay business taxes and regulatory fees and download receipts.
                    </p>
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <span class="font-semibold" style="color: #4caf50;">Make Payment</span>
                        <i class="fas fa-arrow-right service-arrow" style="color: #4caf50;"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- INFORMATION SECTION -->
    <div class="lgu-card p-8 mb-8">
        <h3 class="text-xl font-semibold text-gray-800 mb-6 section-header">
            About Business Tax Services
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6">
            <div>
                <h4 class="font-medium text-gray-800 mb-2">
                    Business Permit & Tax Processing
                </h4>
                <p class="text-gray-600">
                    Apply for business permits, monitor application status,
                    and comply with local business tax and regulatory requirements
                    through the official LGU portal.
                </p>
            </div>

            <div>
                <h4 class="font-medium text-gray-800 mb-2">
                    Need Assistance?
                </h4>
                <div class="space-y-2 text-gray-600">
                    <p><i class="fas fa-envelope mr-2 text-[var(--primary)]"></i> business@goserveph.gov.ph</p>
                    <p><i class="fas fa-phone mr-2 text-[var(--primary)]"></i> (02) 1234-5679</p>
                    <p><i class="fas fa-clock mr-2 text-[var(--primary)]"></i> Mon-Fri: 8AM - 5PM</p>
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
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">My Applications</a></li>
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Settings</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><i class="fas fa-phone mr-2 text-gray-400"></i> (02) 8123 4567</li>
                    <li><i class="fas fa-envelope mr-2 text-gray-400"></i> business@goserveph.gov.ph</li>
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