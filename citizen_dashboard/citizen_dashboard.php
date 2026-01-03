<?php
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #9aa5b1;
            --accent: #4caf50;
            --background: #fbfbfb;
        }
        
        body {
            background-color: var(--background);
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
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <?php include 'navbar.php'; ?>
    
    <main class="container mx-auto px-6 py-10 flex-grow max-w-7xl">
        <!-- Welcome Section -->
        <div class="mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-3">
                Mabuhay, <span style="color: #4a90e2;"><?php echo htmlspecialchars($user_name); ?></span>!
            </h1>
            <p class="text-gray-600 text-lg">Access and manage your government services online.</p>
        </div>

        <!-- Services Section Header -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Available Services</h2>
            <div class="h-1 w-20 rounded-full" style="background-color: #4a90e2;"></div>
        </div>

        <!-- Services Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            
            <!-- Real Property Tax Service -->
            <a href="rpt/rpt_services.php" class="service-card group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden block">
                <div class="h-48 overflow-hidden relative">
                    <?php 
                    $rpt_image = 'images/rpt-service.png';
                    if (file_exists($rpt_image)): ?>
                        <img src="<?php echo $rpt_image; ?>" alt="Real Property Tax" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(74, 144, 226, 0.1);">
                            <i class="fas fa-home text-6xl" style="color: rgba(74, 144, 226, 0.3);"></i>
                        </div>
                    <?php endif; ?>
                    <div class="absolute top-4 right-4 px-3 py-1.5 rounded-lg shadow-sm" style="background-color: rgba(255, 255, 255, 0.95);">
                        <span class="text-xs font-semibold uppercase" style="color: #4a90e2;">Property</span>
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: #4a90e2;">
                            <i class="fas fa-landmark text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800">Real Property Tax</h3>
                    </div>
                    <p class="text-gray-600 leading-relaxed mb-6">
                        Register properties, view assessments, and pay your real property taxes online.
                    </p>
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <span class="font-semibold" style="color: #4a90e2;">Open Service</span>
                        <i class="fas fa-arrow-right service-arrow" style="color: #4a90e2;"></i>
                    </div>
                </div>
            </a>

            <!-- Business Tax Service -->
            <a href="business/business_services.php" class="service-card group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden block">
                <div class="h-48 overflow-hidden relative">
                    <?php 
                    $business_image = 'images/business-service.png';
                    if (file_exists($business_image)): ?>
                        <img src="<?php echo $business_image; ?>" alt="Business Tax" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(76, 175, 80, 0.1);">
                            <i class="fas fa-briefcase text-6xl" style="color: rgba(76, 175, 80, 0.3);"></i>
                        </div>
                    <?php endif; ?>
                    <div class="absolute top-4 right-4 px-3 py-1.5 rounded-lg shadow-sm" style="background-color: rgba(255, 255, 255, 0.95);">
                        <span class="text-xs font-semibold uppercase" style="color: #4caf50;">Business</span>
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: #4caf50;">
                            <i class="fas fa-file-invoice text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800">Business Tax</h3>
                    </div>
                    <p class="text-gray-600 leading-relaxed mb-6">
                        Track permit applications and manage your business tax payments online.
                    </p>
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <span class="font-semibold" style="color: #4caf50;">Open Service</span>
                        <i class="fas fa-arrow-right service-arrow" style="color: #4caf50;"></i>
                    </div>
                </div>
            </a>

            <!-- Market Rent Service -->
            <a href="market/market_services.php" class="service-card group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden block">
                <div class="h-48 overflow-hidden relative">
                    <?php 
                    $market_image = 'images/market-service.png';
                    if (file_exists($market_image)): ?>
                        <img src="<?php echo $market_image; ?>" alt="Market Rent" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center" style="background-color: rgba(255, 152, 0, 0.1);">
                            <i class="fas fa-store text-6xl" style="color: rgba(255, 152, 0, 0.3);"></i>
                        </div>
                    <?php endif; ?>
                    <div class="absolute top-4 right-4 px-3 py-1.5 rounded-lg shadow-sm" style="background-color: rgba(255, 255, 255, 0.95);">
                        <span class="text-xs font-semibold text-orange-600 uppercase">Market</span>
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-shop text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800">Market Rent</h3>
                    </div>
                    <p class="text-gray-600 leading-relaxed mb-6">
                        Manage market stall rentals, payments, and vendor applications.
                    </p>
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <span class="font-semibold text-orange-600">Open Service</span>
                        <i class="fas fa-arrow-right service-arrow text-orange-600"></i>
                    </div>
                </div>
            </a>

        </div>

        <!-- Quick Info Section -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-2xl" style="color: #4a90e2;"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Welcome to GoServePH</h3>
                    <p class="text-gray-600 mb-3">
                        Our online portal makes it easy to access government services from anywhere. Select a service above to get started.
                    </p>
                    <ul class="text-gray-600 space-y-2">
                        <li>• All transactions are secure and officially recorded</li>
                        <li>• Track your applications in real-time</li>
                        <li>• Pay online and receive instant digital receipts</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
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