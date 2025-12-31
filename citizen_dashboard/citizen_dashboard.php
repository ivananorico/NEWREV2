<?php
// citizen_dashboard.php
session_start();

// Check if user is logged in
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
</head>
<body class="bg-gray-50 flex flex-col min-h-screen">
    <!-- Include Navbar -->
    <?php include 'navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8 flex-grow">
        <!-- Page Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>
            <p class="text-gray-600">Access government services and manage your applications</p>
        </div>

       <!-- Services Grid -->
<h2 class="text-2xl font-bold text-gray-800 mb-6 font-poppins">Available Services</h2>

<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
    <!-- RPT Card -->
    <a href="rpt/rpt_services.php" class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 hover:scale-[1.02] cursor-pointer block group">
        <!-- Image Container -->
        <div class="h-48 overflow-hidden">
            <?php 
            $rpt_image = 'images/rpt-service.png';
            if (file_exists($rpt_image)): ?>
                <img src="<?php echo $rpt_image; ?>" alt="Real Property Tax Service" 
                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
            <?php else: ?>
                <div class="w-full h-full bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center">
                    <i class="fas fa-home text-blue-600 text-6xl"></i>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Card Content -->
        <div class="p-6">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-home text-blue-600 text-lg"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Real Property Tax</h3>
                </div>
                <span class="bg-blue-100 text-blue-600 text-xs font-semibold px-3 py-1 rounded-full">Tax</span>
            </div>
            <p class="text-gray-600 text-sm mb-4">Manage property taxes, view assessments, and make payments online.</p>
            <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                <span class="text-blue-600 text-sm font-medium">Access Service →</span>
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center group-hover:bg-blue-600 transition-colors">
                    <i class="fas fa-arrow-right text-blue-600 group-hover:text-white text-sm transition-colors"></i>
                </div>
            </div>
        </div>
    </a>

    <!-- Business Tax Card -->
    <a href="business/business_services.php" class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 hover:scale-[1.02] cursor-pointer block group">
        <!-- Image Container -->
        <div class="h-48 overflow-hidden">
            <?php 
            $business_image = 'images/business-service.png';
            if (file_exists($business_image)): ?>
                <img src="<?php echo $business_image; ?>" alt="Business Tax Service" 
                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
            <?php else: ?>
                <div class="w-full h-full bg-gradient-to-br from-green-50 to-green-100 flex items-center justify-center">
                    <i class="fas fa-briefcase text-green-600 text-6xl"></i>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Card Content -->
        <div class="p-6">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-briefcase text-green-600 text-lg"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Business Tax</h3>
                </div>
                <span class="bg-green-100 text-green-600 text-xs font-semibold px-3 py-1 rounded-full">Business</span>
            </div>
            <p class="text-gray-600 text-sm mb-4">File business returns, make payments, and manage business taxes.</p>
            <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                <span class="text-green-600 text-sm font-medium">Access Service →</span>
                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center group-hover:bg-green-600 transition-colors">
                    <i class="fas fa-arrow-right text-green-600 group-hover:text-white text-sm transition-colors"></i>
                </div>
            </div>
        </div>
    </a>

    <!-- Market Rent Card -->
    <a href="market/market_services.php" class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 hover:scale-[1.02] cursor-pointer block group">
        <!-- Image Container -->
        <div class="h-48 overflow-hidden">
            <?php 
            $market_image = 'images/market-service.png';
            if (file_exists($market_image)): ?>
                <img src="<?php echo $market_image; ?>" alt="Market Rent Service" 
                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
            <?php else: ?>
                <div class="w-full h-full bg-gradient-to-br from-orange-50 to-orange-100 flex items-center justify-center">
                    <i class="fas fa-store text-orange-600 text-6xl"></i>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Card Content -->
        <div class="p-6">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-store text-orange-600 text-lg"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Market Rent</h3>
                </div>
                <span class="bg-orange-100 text-orange-600 text-xs font-semibold px-3 py-1 rounded-full">Market</span>
            </div>
            <p class="text-gray-600 text-sm mb-4">Manage stall rentals, pay rent fees, and handle vendor allocations.</p>
            <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                <span class="text-orange-600 text-sm font-medium">Access Service →</span>
                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center group-hover:bg-orange-600 transition-colors">
                    <i class="fas fa-arrow-right text-orange-600 group-hover:text-white text-sm transition-colors"></i>
                </div>
            </div>
        </div>
    </a>
</div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="container mx-auto px-6 py-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Brand -->
                <div class="col-span-1">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: #4A90E2;">
                            <i class="fas fa-user-tie text-white text-sm"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold" style="word-spacing: -0.2em;">
                                <span style="color: #4A90E2;">Go</span><!--
                                --><span style="color: #4CAF50;">Serve</span><!--
                                --><span style="color: #4A90E2;">PH</span>
                            </h1>
                            <p class="text-xs text-gray-600">Citizen Dashboard</p>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm">
                        Streamlining government services for Filipino citizens.
                    </p>
                </div>

                <!-- Quick Links -->
                <div class="col-span-1">
                    <h3 class="font-semibold text-gray-800 mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="services.php" class="text-gray-600 hover:text-blue-600 text-sm transition-colors">Services</a></li>
                        <li><a href="applications.php" class="text-gray-600 hover:text-blue-600 text-sm transition-colors">My Applications</a></li>
                        <li><a href="documents.php" class="text-gray-600 hover:text-blue-600 text-sm transition-colors">Documents</a></li>
                        <li><a href="settings.php" class="text-gray-600 hover:text-blue-600 text-sm transition-colors">Settings</a></li>
                    </ul>
                </div>

                <!-- Services -->
                <div class="col-span-1">
                    <h3 class="font-semibold text-gray-800 mb-4">Services</h3>
                    <ul class="space-y-2">
                        <li><a href="rpt/rpt_services.php" class="text-gray-600 hover:text-blue-600 text-sm transition-colors">Real Property Tax</a></li>
                        <li><a href="business_tax_services.php" class="text-gray-600 hover:text-green-600 text-sm transition-colors">Business Tax</a></li>
                        <li><a href="market_rent_services.php" class="text-gray-600 hover:text-orange-600 text-sm transition-colors">Market Rent</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div class="col-span-1">
                    <h3 class="font-semibold text-gray-800 mb-4">Contact Info</h3>
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-phone text-gray-400 text-sm"></i>
                            <span class="text-gray-600 text-sm">(02) 1234-5678</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-envelope text-gray-400 text-sm"></i>
                            <span class="text-gray-600 text-sm">support@goserveph.gov.ph</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-clock text-gray-400 text-sm"></i>
                            <span class="text-gray-600 text-sm">Mon-Fri: 8:00 AM - 5:00 PM</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="border-t border-gray-200 mt-8 pt-6 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-500 text-sm mb-4 md:mb-0">
                    &copy; 2024 GoServePH. All rights reserved.
                </p>
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-red-600 transition-colors">
                        <i class="fab fa-youtube"></i>
                    </a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>