<?php
// rpt_services.php
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
    <title>RPT Services - GoServePH</title>
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
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Real Property Tax Services</h1>
                    <p class="text-gray-600">Manage your property taxes and related services</p>
                </div>
            </div>
        </div>

        <!-- RPT Services Cards -->
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
            <a href="rpt_application/rpt_application.php" class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-yellow-500 hover:scale-105 cursor-pointer block">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tasks text-yellow-600 text-xl"></i>
                    </div>
                    <span class="bg-yellow-100 text-yellow-600 text-xs font-semibold px-2 py-1 rounded">Tracking</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Application Status</h3>
                <p class="text-gray-600 text-sm mb-4">Track your RPT applications, view status updates, and check processing times.</p>
                <div class="flex items-center justify-between mt-4">
                    <span class="text-yellow-600 text-sm font-medium">Check Status →</span>
                    <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-right text-yellow-600 text-sm"></i>
                    </div>
                </div>
            </a>

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