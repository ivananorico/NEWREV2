<?php
// market_services.php
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
    <title>Market Rent Services - GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
     <?php include '../../citizen_dashboard/navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">
        <!-- Page Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center mb-4">
                <a href="../citizen_dashboard.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Market Rent Services</h1>
                    <p class="text-gray-600">Manage your market stall rentals and payments</p>
                </div>
            </div>
        </div>

        <!-- Market Services Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Rent a Market Card -->
            <a href="market_portal_services/market_portal_services.php" class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-indigo-500 hover:scale-105 cursor-pointer block">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-store text-indigo-600 text-xl"></i>
                    </div>
                    <span class="bg-indigo-100 text-indigo-600 text-xs font-semibold px-2 py-1 rounded">Rental</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Rent a Market Stall</h3>
                <p class="text-gray-600 text-sm mb-4">Apply for market stall rental, choose your preferred location, and submit rental applications.</p>
                <div class="flex items-center justify-between mt-4">
                    <span class="text-indigo-600 text-sm font-medium">Apply Now →</span>
                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-right text-indigo-600 text-sm"></i>
                    </div>
                </div>
            </a>

            <!-- Application Status Card -->
            <a href="market_application_status.php" class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-amber-500 hover:scale-105 cursor-pointer block">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tasks text-amber-600 text-xl"></i>
                    </div>
                    <span class="bg-amber-100 text-amber-600 text-xs font-semibold px-2 py-1 rounded">Tracking</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Application Status</h3>
                <p class="text-gray-600 text-sm mb-4">Track your market stall applications, view status updates, and check approval progress.</p>
                <div class="flex items-center justify-between mt-4">
                    <span class="text-amber-600 text-sm font-medium">Check Status →</span>
                    <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-right text-amber-600 text-sm"></i>
                    </div>
                </div>
            </a>

            <!-- Market Rent Payment Card -->
            <a href="market_rent_payment.php" class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-orange-500 hover:scale-105 cursor-pointer block">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-credit-card text-orange-600 text-xl"></i>
                    </div>
                    <span class="bg-orange-100 text-orange-600 text-xs font-semibold px-2 py-1 rounded">Payment</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Market Rent Payment</h3>
                <p class="text-gray-600 text-sm mb-4">Pay your market stall rent online, view payment history, and download rent receipts.</p>
                <div class="flex items-center justify-between mt-4">
                    <span class="text-orange-600 text-sm font-medium">Pay Rent →</span>
                    <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-right text-orange-600 text-sm"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- Additional Information -->
        <div class="mt-12 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">About Market Rent Services</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">Market Stall Rental</h4>
                    <p class="text-gray-600 text-sm">Rent market stalls in public markets across the city. Choose from various stall sizes and locations to suit your business needs.</p>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">Need Help?</h4>
                    <p class="text-gray-600 text-sm">Contact our market services team at <span class="text-orange-600">market-support@goserveph.gov.ph</span> or call (02) 1234-5680.</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>