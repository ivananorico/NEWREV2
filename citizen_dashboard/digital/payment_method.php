<?php
// payment_method.php

// Get payment data from URL parameter
$encodedData = $_GET['data'] ?? '';
if (empty($encodedData)) {
    die("Invalid payment data");
}

$paymentData = json_decode(base64_decode($encodedData), true);
if (!$paymentData) {
    die("Invalid payment data");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Payment Method - GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="w-full max-w-2xl mx-4">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-2xl p-6 mb-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-credit-card text-blue-600 text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Select Payment Method</h1>
                <p class="text-gray-600">Choose how you want to pay</p>
            </div>
            
            <!-- Payment Details -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">Purpose:</span>
                        <p class="font-semibold"><?php echo htmlspecialchars($paymentData['purpose']); ?></p>
                    </div>
                    <div>
                        <span class="text-gray-600">Amount:</span>
                        <p class="font-semibold text-lg text-blue-600">₱<?php echo number_format($paymentData['amount'], 2); ?></p>
                    </div>
                    <div>
                        <span class="text-gray-600">Reference:</span>
                        <p class="font-semibold"><?php echo htmlspecialchars($paymentData['client_reference']); ?></p>
                    </div>
                    <div>
                        <span class="text-gray-600">System:</span>
                        <p class="font-semibold"><?php echo htmlspecialchars($paymentData['client_system']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- GCash -->
                <div class="bg-white rounded-xl shadow-lg p-6 border-2 border-transparent hover:border-green-500 transition-all cursor-pointer transform hover:scale-105" onclick="selectPaymentMethod('gcash')">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-mobile-alt text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">GCash</h3>
                            <p class="text-gray-600">Pay using GCash</p>
                        </div>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                        <p class="text-sm text-green-700">
                            <i class="fas fa-info-circle mr-1"></i>
                            Instant payment confirmation
                        </p>
                    </div>
                </div>

                <!-- PayMaya -->
                <div class="bg-white rounded-xl shadow-lg p-6 border-2 border-transparent hover:border-purple-500 transition-all cursor-pointer transform hover:scale-105" onclick="selectPaymentMethod('paymaya')">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-credit-card text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">PayMaya</h3>
                            <p class="text-gray-600">Pay using PayMaya</p>
                        </div>
                    </div>
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-3">
                        <p class="text-sm text-purple-700">
                            <i class="fas fa-info-circle mr-1"></i>
                            Secure card payments
                        </p>
                    </div>
                </div>
            </div>

            <!-- Back Button -->
            <div class="mt-6 text-center">
                <button onclick="goBack()" class="text-gray-600 hover:text-gray-800 font-medium">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Previous Page
                </button>
            </div>
        </div>
    </div>

    <script>
    function selectPaymentMethod(method) {
    const paymentData = <?php echo json_encode($paymentData); ?>;
    paymentData.payment_method = method;
    
    const encodedData = btoa(JSON.stringify(paymentData));
    
    // Redirect to specific pages based on payment method
    if (method === 'gcash') {
        window.location.href = 'gcash_verification.php?data=' + encodedData;
    } else if (method === 'paymaya') {
        window.location.href = 'paymaya_verification.php?data=' + encodedData;
    }
    // Add more methods as needed
}
    </script>
</body>
</html>