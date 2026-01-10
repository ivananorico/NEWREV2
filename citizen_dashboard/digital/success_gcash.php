<?php
// revenue2/citizen_dashboard/digital/success_gcash.php
session_start();

if (!isset($_SESSION['receipt_data'])) {
    header('Location: index.php');
    exit();
}

$receipt = $_SESSION['receipt_data'];

// Map system names to redirect URLs
$redirect_urls = [
    'rpt' => '../../services/rpt/rpt_tax_payment.php',
    'business' => '../../services/business/business_payment.php',
    'health' => '../../services/health/health_payment.php',
    'assets' => '../../services/assets/assets_payment.php',
    'others' => '../../services/dashboard.php'
];

$redirect_url = $redirect_urls[$receipt['client_system']] ?? '../../services/dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-50 min-h-screen">
    <div class="max-w-lg mx-auto p-4">
        <div class="text-center mb-8">
            <div class="w-24 h-24 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Payment Successful!</h1>
            <p class="text-gray-600">Your payment has been processed</p>
        </div>

        <?php if (!empty($receipt['callback_url'])): ?>
        <div class="mb-6 p-4 <?php echo $receipt['callback_success'] ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200'; ?> border rounded-xl">
            <div class="flex items-start">
                <i class="fas <?php echo $receipt['callback_success'] ? 'fa-check-circle text-green-600' : 'fa-exclamation-triangle text-yellow-600'; ?> text-xl mr-3 mt-1"></i>
                <div>
                    <h3 class="font-bold <?php echo $receipt['callback_success'] ? 'text-green-800' : 'text-yellow-800'; ?> mb-2">
                        <?php echo $receipt['callback_success'] ? 'System Updated' : 'System Update Status'; ?>
                    </h3>
                    <p class="text-sm <?php echo $receipt['callback_success'] ? 'text-green-700' : 'text-yellow-700'; ?>">
                        <?php echo $receipt['callback_success'] 
                            ? 'Payment recorded in ' . ucfirst($receipt['client_system']) . ' system' 
                            : 'Payment recorded. ' . ucfirst($receipt['client_system']) . ' system may update later.'; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Receipt</h2>
            
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-600">System:</span>
                    <span class="font-bold"><?php echo strtoupper($receipt['client_system']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Receipt No:</span>
                    <span class="font-bold text-green-600"><?php echo $receipt['receipt_number']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Payment ID:</span>
                    <span class="font-medium"><?php echo $receipt['payment_id']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Date:</span>
                    <span class="font-medium"><?php echo date('M d, Y h:i A', strtotime($receipt['paid_at'])); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Amount:</span>
                    <span class="font-bold text-2xl text-green-600">₱<?php echo number_format($receipt['amount'], 2); ?></span>
                </div>
            </div>
            
            <div class="mt-8 pt-6 border-t">
                <a href="<?php echo $redirect_url; ?>" 
                   class="block bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-3 rounded-xl font-medium text-center">
                    <i class="fas fa-home mr-3"></i>Back to <?php echo ucfirst($receipt['client_system']); ?> System
                </a>
            </div>
        </div>
        
        <p class="text-center text-gray-500 text-sm">
            You will be redirected in <span id="countdown">10</span> seconds...
        </p>
    </div>

    <script>
        // Auto-redirect
        let seconds = 10;
        const countdown = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = '<?php echo $redirect_url; ?>';
            }
        }, 1000);
    </script>
</body>
</html>
<?php
// Clear session
session_regenerate_id(true);
$_SESSION = array();
session_destroy();
?>