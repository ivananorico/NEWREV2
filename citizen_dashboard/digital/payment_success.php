<?php
session_start();
require_once '../../db/Digital/digital_db.php';

$payment_id = $_GET['payment_id'] ?? '';

if (empty($payment_id)) {
    header('Location: payment_method.php');
    exit;
}

// Get payment details from DIGITAL database
$stmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE payment_id = ? AND payment_status = 'paid'");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: payment_method.php');
    exit;
}

// Clear session
unset($_SESSION['payment_data']);
unset($_SESSION['current_verification']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-2xl shadow-xl">
            <!-- Success Header -->
            <div class="bg-green-600 text-white p-6 rounded-t-2xl">
                <div class="text-center">
                    <div class="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check text-white text-3xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold">Payment Successful!</h1>
                    <p class="text-green-100 mt-2">Your payment has been processed</p>
                </div>
            </div>

            <div class="p-6">
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <div class="text-center mb-4">
                        <div class="text-3xl font-bold text-green-600">₱<?php echo number_format($payment['amount'], 2); ?></div>
                        <p class="text-gray-600">Amount Paid</p>
                    </div>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Purpose:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($payment['purpose']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Reference:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($payment['client_reference']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Receipt No:</span>
                            <span class="font-medium text-blue-600"><?php echo $payment['receipt_number']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Paid At:</span>
                            <span class="font-medium"><?php echo date('M d, Y h:i A', strtotime($payment['paid_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <p class="text-gray-600 mb-4">Thank you for your payment!</p>
                    <a href="/revenue/citizen_dashboard/" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-6 rounded-lg font-semibold transition-colors">
                        Return to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>