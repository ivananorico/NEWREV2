<?php
// revenue2/citizen_dashboard/digital/success.php
session_start();
require_once 'config.php';

$payment_id = $_GET['payment_id'] ?? '';
$receipt_number = $_GET['receipt'] ?? '';

if (empty($payment_id)) {
    header('Location: index.php');
    exit();
}

// Get payment details
$pdo = getDigitalDBConnection();
$payment = null;
if ($pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM payment_transactions 
        WHERE payment_id = :payment_id 
        AND payment_status = 'paid'
    ");
    $stmt->execute(['payment_id' => $payment_id]);
    $payment = $stmt->fetch();
}

// Clear session
unset($_SESSION['payment_data']);
unset($_SESSION['current_payment']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-lg mx-auto">
            <!-- Success Icon -->
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check-circle text-green-600 text-3xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Payment Successful!</h1>
                <p class="text-gray-600">Your payment has been processed successfully.</p>
            </div>

            <!-- Receipt -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="text-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Official Receipt</h2>
                    <div class="text-sm text-gray-500"><?php echo SITE_NAME; ?></div>
                </div>
                
                <div class="space-y-3">
                    <?php if ($payment): ?>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Receipt No:</span>
                        <span class="font-bold"><?php echo $payment['receipt_number']; ?></span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Payment ID:</span>
                        <span class="font-mono text-sm"><?php echo $payment['payment_id']; ?></span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Date & Time:</span>
                        <span><?php echo date('M d, Y h:i A', strtotime($payment['paid_at'])); ?></span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Purpose:</span>
                        <span><?php echo htmlspecialchars($payment['purpose']); ?></span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Payment Method:</span>
                        <span class="uppercase font-medium"><?php echo $payment['payment_method']; ?></span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Mobile Number:</span>
                        <span><?php echo $payment['phone']; ?></span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Amount Paid:</span>
                        <span class="text-2xl font-bold text-blue-600">₱<?php echo number_format($payment['amount'], 2); ?></span>
                    </div>
                    
                    <?php if ($payment['is_annual']): ?>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Payment Type:</span>
                        <span class="font-medium text-green-600">Annual Payment</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="text-center p-4 bg-yellow-50 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl mb-2"></i>
                        <p class="text-yellow-800">Payment details not found</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sync Status -->
                <?php if ($payment && isset($payment['sync_status'])): ?>
                <div class="mt-6 p-3 rounded-lg <?php echo $payment['sync_status'] == 'synced' ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'; ?>">
                    <div class="flex items-center">
                        <i class="fas <?php echo $payment['sync_status'] == 'synced' ? 'fa-check-circle text-green-600' : 'fa-clock text-yellow-600'; ?> mr-2"></i>
                        <div class="text-sm">
                            <span class="font-medium">
                                <?php echo $payment['sync_status'] == 'synced' ? 'System Updated' : 'Updating System'; ?>
                            </span>
                            <div class="text-xs opacity-75">
                                <?php if ($payment['sync_status'] == 'synced'): ?>
                                Your payment has been recorded in the <?php echo $payment['client_system']; ?> system.
                                <?php else: ?>
                                Your payment is being processed in the <?php echo $payment['client_system']; ?> system.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row justify-center gap-3">
                <button onclick="printReceipt()" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                    <i class="fas fa-print mr-2"></i> Print Receipt
                </button>
                
                <button onclick="goBackToSystem()" 
                        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                    <i class="fas fa-home mr-2"></i> Return to System
                </button>
            </div>

            <!-- Note -->
            <div class="mt-8 text-center text-sm text-gray-500">
                <p>This receipt is your proof of payment. Please keep it for your records.</p>
                <p class="mt-1">You will receive a confirmation SMS within 24 hours.</p>
            </div>
        </div>
    </div>

    <script>
    function printReceipt() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Payment Receipt</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; max-width: 400px; margin: 0 auto; }
                    .header { text-align: center; margin-bottom: 20px; }
                    .details { margin: 20px 0; }
                    .footer { margin-top: 20px; font-size: 12px; color: #666; text-align: center; }
                    .total { font-size: 18px; font-weight: bold; margin-top: 10px; }
                    @media print {
                        button { display: none; }
                    }
                </style>
            </head>
            <body>
                ${document.getElementById('receiptModalBody') ? document.getElementById('receiptModalBody').innerHTML : 'No receipt data'}
                <script>
                    window.print();
                    setTimeout(() => window.close(), 1000);
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }
    
    function goBackToSystem() {
        <?php if (isset($payment['client_system']) && $payment['client_system'] === 'RPT'): ?>
        window.location.href = '../../rpt/rpt_tax_payment/rpt_tax_payment.php?payment_success=1';
        <?php else: ?>
        window.history.back();
        <?php endif; ?>
    }
    </script>
</body>
</html>