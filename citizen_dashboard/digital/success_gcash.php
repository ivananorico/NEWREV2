<?php
// revenue2/citizen_dashboard/digital/success_gcash.php
session_start();

// Check if receipt data exists
if (!isset($_SESSION['receipt_data'])) {
    header('Location: index.php');
    exit();
}

$receipt = $_SESSION['receipt_data'];
$api_error = $_SESSION['api_error'] ?? null;

// Clear session data after displaying
session_regenerate_id(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - LGU Digital Payment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .success-animation {
            animation: successPulse 2s ease-in-out;
        }
        @keyframes successPulse {
            0% { transform: scale(0.8); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-50 min-h-screen">
    <div class="max-w-lg mx-auto p-4">
        <!-- Success Animation -->
        <div class="text-center mb-8">
            <div class="w-24 h-24 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6 success-animation">
                <i class="fas fa-check text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Payment Successful!</h1>
            <p class="text-gray-600">Your payment has been processed successfully</p>
        </div>

        <?php if ($api_error): ?>
        <!-- API Warning -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-8">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mr-3 mt-1"></i>
                <div>
                    <h3 class="font-bold text-yellow-800 mb-2">Partial Success</h3>
                    <p class="text-yellow-700">Payment recorded but RPT database update may be delayed.</p>
                    <div class="mt-3 text-sm space-y-1">
                        <p class="text-gray-600">Status: <span class="font-medium text-yellow-600"><?php echo $api_error['status']; ?></span></p>
                        <p class="text-gray-600">Message: <span class="font-medium"><?php echo $api_error['message']; ?></span></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Receipt Card -->
        <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Payment Receipt</h2>
                <p class="text-gray-500">Keep this for your records</p>
            </div>
            
            <div class="space-y-6">
                <!-- Payment Details -->
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment ID:</span>
                        <span class="font-medium"><?php echo $receipt['payment_id']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Receipt Number:</span>
                        <span class="font-bold text-green-600"><?php echo $receipt['receipt_number']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Date & Time:</span>
                        <span class="font-medium"><?php echo date('M d, Y h:i A', strtotime($receipt['paid_at'])); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Purpose:</span>
                        <span class="font-medium text-right"><?php echo htmlspecialchars($receipt['purpose']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment Method:</span>
                        <span class="font-medium">GCash</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Mobile Number:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($receipt['phone']); ?></span>
                    </div>
                </div>

                <!-- Amount -->
                <div class="pt-6 border-t">
                    <div class="flex justify-between items-center">
                        <span class="text-lg text-gray-700">Total Amount</span>
                        <span class="text-3xl font-bold text-green-600">₱<?php echo number_format($receipt['amount'], 2); ?></span>
                    </div>
                </div>

                <!-- Status -->
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 mt-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-bold text-green-800">Payment Verified</p>
                            <p class="text-sm text-green-700">Transaction completed successfully</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            <button onclick="printReceipt()" 
                    class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 py-3 rounded-xl font-medium flex items-center justify-center">
                <i class="fas fa-print mr-3"></i> Print Receipt
            </button>
            <a href="../../services/rpt/rpt_tax_payment.php" 
               class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-3 rounded-xl font-medium flex items-center justify-center">
                <i class="fas fa-home mr-3"></i> Back to RPT Payment
            </a>
        </div>
        
        <!-- Auto-redirect timer -->
        <div class="text-center text-gray-500 text-sm">
            <p>You will be redirected to RPT Payment page in <span id="countdown">10</span> seconds...</p>
        </div>
    </div>

    <script>
        // Print receipt function
        function printReceipt() {
            const printContent = `
                <html>
                    <head>
                        <title>Payment Receipt</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; }
                            .receipt { max-width: 400px; margin: 0 auto; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .details { margin-bottom: 20px; }
                            .total { font-size: 24px; font-weight: bold; text-align: right; margin-top: 20px; }
                            .footer { margin-top: 30px; font-size: 12px; text-align: center; }
                            .border-top { border-top: 2px solid #000; padding-top: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class="receipt">
                            <div class="header">
                                <h2>Quezon City LGU</h2>
                                <h3>Payment Receipt</h3>
                                <p>Official Receipt</p>
                            </div>
                            <div class="details">
                                <p><strong>Receipt No:</strong> <?php echo $receipt['receipt_number']; ?></p>
                                <p><strong>Payment ID:</strong> <?php echo $receipt['payment_id']; ?></p>
                                <p><strong>Date:</strong> <?php echo date('F d, Y h:i A', strtotime($receipt['paid_at'])); ?></p>
                                <p><strong>Purpose:</strong> <?php echo htmlspecialchars($receipt['purpose']); ?></p>
                                <p><strong>Method:</strong> GCash</p>
                                <p><strong>Mobile:</strong> <?php echo htmlspecialchars($receipt['phone']); ?></p>
                            </div>
                            <div class="border-top">
                                <div class="total">
                                    <p>₱<?php echo number_format($receipt['amount'], 2); ?></p>
                                </div>
                            </div>
                            <div class="footer">
                                <p>*** OFFICIAL RECEIPT ***</p>
                                <p>Thank you for your payment!</p>
                                <p>Keep this receipt for your records</p>
                            </div>
                        </div>
                    </body>
                </html>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }
        
        // Auto-redirect after 10 seconds
        let seconds = 10;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = '../../services/rpt/rpt_tax_payment.php';
            }
        }, 1000);
    </script>
</body>
</html>
<?php
// Clear all session data
$_SESSION = array();
session_destroy();
?>