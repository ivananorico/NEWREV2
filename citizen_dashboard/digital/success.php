<?php
// success.php

$payment_id = $_GET['payment_id'] ?? '';
$receipt_number = $_GET['receipt_number'] ?? '';

if (empty($payment_id) || empty($receipt_number)) {
    die("Invalid payment data");
}

// Include database connection
include_once '../../db/Digital/digital_db.php';

// Get payment details
try {
    $stmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE payment_id = :payment_id");
    $stmt->execute([':payment_id' => $payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        die("Payment not found");
    }
} catch (Exception $e) {
    die("Error loading payment details");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - GoServePH</title>
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
        <!-- Success Message -->
        <div class="bg-white rounded-xl shadow-2xl p-8 text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check text-green-600 text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-4">Payment Successful!</h1>
            <p class="text-gray-600 text-lg mb-6">
                Your payment has been processed successfully.
            </p>
            
            <!-- Receipt -->
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 max-w-md mx-auto mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Receipt</h3>
                <div class="space-y-3 text-left">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Receipt Number:</span>
                        <span class="font-semibold"><?php echo htmlspecialchars($receipt_number); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Amount Paid:</span>
                        <span class="font-semibold text-green-600">₱<?php echo number_format($payment['amount'], 2); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment Method:</span>
                        <span class="font-semibold capitalize"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Date & Time:</span>
                        <span class="font-semibold"><?php echo date('M d, Y h:i A', strtotime($payment['paid_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <button onclick="window.print()" 
                        class="bg-gray-600 hover:bg-gray-700 text-white py-3 px-6 rounded-lg font-semibold transition-colors">
                    <i class="fas fa-print mr-2"></i>Print Receipt
                </button>
                <button onclick="closeWindow()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white py-3 px-6 rounded-lg font-semibold transition-colors">
                    <i class="fas fa-times mr-2"></i>Close
                </button>
            </div>
        </div>
    </div>

    <script>
    function closeWindow() {
        window.close();
    }

    // Auto-close after 10 seconds
    setTimeout(() => {
        closeWindow();
    }, 10000);
    </script>
</body>
</html>