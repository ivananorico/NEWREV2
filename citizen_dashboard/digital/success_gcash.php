<?php
// revenue2/citizen_dashboard/digital/success_gcash.php
session_start();

if (!isset($_SESSION['receipt_data'])) {
    header('Location: index.php');
    exit();
}

$receipt = $_SESSION['receipt_data'];

// Add fallback for callback_success if not set
if (!isset($receipt['callback_success'])) {
    $receipt['callback_success'] = false;
}

// Get the return URL
$return_url = '';
if (isset($_SESSION['payment_data']['return_url']) && !empty($_SESSION['payment_data']['return_url'])) {
    $return_url = $_SESSION['payment_data']['return_url'];
} else {
    $redirect_urls = [
        'market' => '../../market/market_application/paying.php?payment_success=true',
        'rpt' => '../../services/rpt/rpt_tax_payment.php',
        'business' => '../../services/business/business_payment.php',
        'health' => '../../services/health/health_payment.php',
        'assets' => '../../services/assets/assets_payment.php',
        'others' => '../../services/dashboard.php'
    ];
    
    $return_url = $redirect_urls[$receipt['client_system']] ?? '../../market/market_application/paying.php?payment_success=true';
}

// Clear only payment-related session data
unset($_SESSION['payment_data']);
unset($_SESSION['receipt_data']);
unset($_SESSION['phone']);
unset($_SESSION['generated_otp']);
unset($_SESSION['otp_expires']);
unset($_SESSION['otp_attempts']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .success-checkmark {
            width: 80px;
            height: 115px;
            margin: 0 auto;
        }
        .check-icon {
            width: 80px;
            height: 80px;
            position: relative;
            border-radius: 50%;
            box-sizing: content-box;
            border: 4px solid #4CAF50;
        }
        .icon-line {
            height: 5px;
            background-color: #4CAF50;
            display: block;
            border-radius: 2px;
            position: absolute;
            z-index: 10;
        }
        .line-tip {
            top: 46px;
            left: 14px;
            width: 25px;
            transform: rotate(45deg);
            animation: icon-line-tip 0.75s;
        }
        .line-long {
            top: 38px;
            right: 8px;
            width: 47px;
            transform: rotate(-45deg);
            animation: icon-line-long 0.75s;
        }
        @keyframes icon-line-tip {
            0% { width: 0; left: 1px; top: 19px; }
            54% { width: 0; left: 1px; top: 19px; }
            70% { width: 50px; left: -8px; top: 37px; }
            84% { width: 17px; left: 21px; top: 48px; }
            100% { width: 25px; left: 14px; top: 45px; }
        }
        @keyframes icon-line-long {
            0% { width: 0; right: 46px; top: 54px; }
            65% { width: 0; right: 46px; top: 54px; }
            84% { width: 55px; right: 0px; top: 35px; }
            100% { width: 47px; right: 8px; top: 38px; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-50 min-h-screen">
    <div class="max-w-lg mx-auto p-4">
        <div class="text-center mb-8">
            <!-- Animated Checkmark -->
            <div class="success-checkmark mb-6">
                <div class="check-icon">
                    <span class="icon-line line-tip"></span>
                    <span class="icon-line line-long"></span>
                    <div class="icon-circle"></div>
                    <div class="icon-fix"></div>
                </div>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Payment Successful!</h1>
            <p class="text-gray-600">Your payment has been processed successfully</p>
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
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Payment Receipt</h2>
            
            <div class="space-y-4 mb-6">
                <div class="flex justify-between">
                    <span class="text-gray-600">Payment ID:</span>
                    <span class="font-bold text-green-600"><?php echo $receipt['payment_id']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Receipt No:</span>
                    <span class="font-medium"><?php echo $receipt['receipt_number']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Date:</span>
                    <span class="font-medium"><?php echo date('M d, Y h:i A', strtotime($receipt['paid_at'])); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">System:</span>
                    <span class="font-bold"><?php echo strtoupper($receipt['client_system']); ?></span>
                </div>
                <div class="pt-4 border-t">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold text-gray-800">Total Amount:</span>
                        <span class="text-3xl font-bold text-green-600">₱<?php echo number_format($receipt['amount'], 2); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 pt-6 border-t">
                <a href="<?php echo $return_url; ?>" 
                   class="block bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-3 rounded-xl font-medium text-center mb-3">
                    <i class="fas fa-arrow-left mr-3"></i>Return to Application
                </a>
                <button onclick="window.close();" 
                        class="block border border-gray-300 hover:bg-gray-50 text-gray-800 py-3 rounded-xl font-medium text-center w-full">
                    <i class="fas fa-times mr-3"></i>Close This Window
                </button>
            </div>
        </div>
        
        <div class="text-center text-gray-500 text-sm">
            <p>This window will close automatically in <span id="countdown">10</span> seconds...</p>
        </div>
    </div>

    <script>
        // Auto-redirect or close
        let seconds = 10;
        const countdown = document.getElementById('countdown');
        const returnUrl = '<?php echo $return_url; ?>';
        
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                // Try to close the window, if not possible redirect
                if (window.opener) {
                    window.close();
                } else {
                    window.location.href = returnUrl;
                }
            }
        }, 1000);
        
        // Close button functionality
        document.querySelector('button[onclick="window.close();"]').addEventListener('click', function() {
            if (window.opener) {
                // Refresh parent window
                window.opener.location.reload();
            }
        });
        
        // Redirect link also refreshes parent
        document.querySelector('a[href="<?php echo $return_url; ?>"]').addEventListener('click', function(e) {
            if (window.opener) {
                window.opener.location.reload();
            }
        });
    </script>
</body>
</html>