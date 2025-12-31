<?php
// business_services.php
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
    <title>Business Tax Services - GoServePH</title>
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
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Business Tax Services</h1>
                    <p class="text-gray-600">Manage your business tax applications, payments, and renewals</p>
                </div>
            </div>
        </div>

        <!-- Business Services Cards - Updated to 3 columns -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
            <!-- Application Status Card -->
            <a href="business_application_status/business_application_status.php" class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-purple-500 hover:scale-105 cursor-pointer block">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tasks text-purple-600 text-xl"></i>
                    </div>
                    <span class="bg-purple-100 text-purple-600 text-xs font-semibold px-2 py-1 rounded">Tracking</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Application Status</h3>
                <p class="text-gray-600 text-sm mb-4">Track your business tax applications, view status updates, and check processing times.</p>
                <div class="flex items-center justify-between mt-4">
                    <span class="text-purple-600 text-sm font-medium">Check Status →</span>
                    <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-right text-purple-600 text-sm"></i>
                    </div>
                </div>
            </a>

            <!-- Business Tax Payment Card -->
            <a href="business_tax_payment/business_tax_payment.php" class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 border-l-4 border-green-500 hover:scale-105 cursor-pointer block">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-credit-card text-green-600 text-xl"></i>
                    </div>
                    <span class="bg-green-100 text-green-600 text-xs font-semibold px-2 py-1 rounded">Payment</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Business Tax Payment</h3>
                <p class="text-gray-600 text-sm mb-4">Pay your business taxes online, view tax assessments, and download payment receipts.</p>
                <div class="flex items-center justify-between mt-4">
                    <span class="text-green-600 text-sm font-medium">Make Payment →</span>
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-right text-green-600 text-sm"></i>
                    </div>
                </div>
            </a>

            
        </div>


        <!-- Additional Information -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6 max-w-6xl mx-auto">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">About Business Tax Services</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-file-alt text-purple-600 mr-2"></i>
                        Application Process
                    </h4>
                    <p class="text-gray-600 text-sm">Track your application status from submission to approval. Receive notifications for each milestone.</p>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-money-check-alt text-green-600 mr-2"></i>
                        Tax Payments
                    </h4>
                    <p class="text-gray-600 text-sm">Convenient online payment system with instant receipt generation and payment history tracking.</p>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-sync-alt text-blue-600 mr-2"></i>
                        Permit Renewal
                    </h4>
                    <p class="text-gray-600 text-sm">Annual renewal process including gross income declaration, tax assessment, and permit issuance.</p>
                </div>
            </div>
            
            <!-- Support Information -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h4 class="font-semibold text-gray-700 mb-2">Need Assistance?</h4>
                <p class="text-gray-600 text-sm">Contact our business tax support team:</p>
                <div class="flex flex-wrap gap-4 mt-2">
                    <div class="flex items-center">
                        <i class="fas fa-envelope text-green-600 mr-2"></i>
                        <span class="text-sm">business-support@goserveph.gov.ph</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-phone text-green-600 mr-2"></i>
                        <span class="text-sm">(02) 1234-5679</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-clock text-green-600 mr-2"></i>
                        <span class="text-sm">Mon-Fri: 8:00 AM - 5:00 PM</span>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>