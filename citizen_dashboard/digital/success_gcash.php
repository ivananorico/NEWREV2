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

// Format receipt data
$receipt_number = $receipt['receipt_number'];
$receipt_date = date('M d, Y h:i A', strtotime($receipt['paid_at']));
$receipt_amount = number_format($receipt['amount'], 2);
$payment_id = $receipt['payment_id'];
$system = strtoupper($receipt['client_system']);
$reference_no = isset($receipt['reference_no']) ? $receipt['reference_no'] : '';

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
    <title>GCash Payment Successful</title>
    <!-- Add Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Add html2canvas for image generation -->
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

    <style>
        body {
            background: #f1f6ff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .gcash-card {
            background: linear-gradient(135deg, #0074E4 0%, #00A9FF 100%);
        }

        .gcash-circle {
            width: 85px;
            height: 85px;
            background: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .gcash-check {
            font-size: 45px;
            color: #0074E4;
        }
        
        .action-btn {
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Hidden receipt for image capture */
        #receipt-image-container {
            position: fixed;
            left: -9999px;
            top: -9999px;
            width: 400px;
            background: white;
            padding: 0;
        }
        
        /* GCash receipt styles for image */
        .gcash-receipt-img {
            width: 400px;
            min-height: 450px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 116, 228, 0.15);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .receipt-header-img {
            background: linear-gradient(135deg, #0074E4 0%, #00A9FF 100%);
            padding: 25px 20px;
            text-align: center;
            color: white;
        }
        
        .gcash-logo-img {
            height: 40px;
            margin-bottom: 10px;
        }
        
        .status-text-img {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .receipt-title-img {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .amount-section-img {
            padding: 25px 20px;
            text-align: center;
        }
        
        .amount-label-img {
            font-size: 16px;
            color: #64748b;
            margin-bottom: 10px;
        }
        
        .amount-number-img {
            font-size: 40px;
            font-weight: 800;
            color: #0074E4;
            margin-bottom: 15px;
        }
        
        .verified-badge-img {
            display: inline-flex;
            align-items: center;
            background: #d1fae5;
            color: #065f46;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            gap: 8px;
        }
        
        .details-section-img {
            padding: 20px;
            margin: 0 20px 15px;
            background: #f8fafc;
            border-radius: 12px;
        }
        
        .section-title-img {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .detail-row-img {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        
        .detail-row-img:last-child {
            border-bottom: none;
        }
        
        .detail-label-img {
            color: #64748b;
        }
        
        .detail-value-img {
            color: #1e293b;
            font-weight: 600;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }
        
        .footer-img {
            text-align: center;
            color: #64748b;
            font-size: 12px;
            margin: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            line-height: 1.5;
        }
        
        .security-note-img {
            background: #fff7ed;
            padding: 15px;
            border-radius: 10px;
            margin: 20px;
            border-left: 4px solid #f97316;
        }
        
        .security-title-img {
            color: #9a3412;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .security-text-img {
            color: #9a3412;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .date-time-img {
            font-size: 12px;
            margin-top: 10px;
            opacity: 0.9;
        }
        
        .thankyou-img {
            color: #0074E4;
            font-weight: 700;
            font-size: 16px;
            margin-top: 15px;
        }
    </style>
</head>

<body class="min-h-screen flex justify-center items-center p-4">

<div class="w-full max-w-lg bg-white shadow-2xl rounded-3xl overflow-hidden">

    <!-- GCash Header -->
    <div class="gcash-card p-6 text-center text-white">
        <img src="images/gcash_img.jpg" class="w-24 mx-auto mb-3">
        <h1 class="text-2xl font-bold">Payment Successful</h1>
        <p class="opacity-90 text-sm mt-1">Your GCash payment has been processed</p>
    </div>

    <!-- Check icon -->
    <div class="gcash-circle -mt-10 mb-4">
        <i class="gcash-check fas fa-check-circle"></i>
    </div>

    <!-- Payment receipt box -->
    <div class="px-8 pb-8">
        <div class="bg-white rounded-2xl shadow-md p-6">

            <h2 class="text-xl font-bold text-gray-800 text-center mb-4">GCash Receipt</h2>

            <div class="space-y-3">

                <div class="flex justify-between">
                    <span class="text-gray-600">Payment ID</span>
                    <span class="font-semibold"><?php echo $receipt['payment_id']; ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Receipt No.</span>
                    <span class="font-semibold"><?php echo $receipt['receipt_number']; ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">Date</span>
                    <span class="font-medium"><?php echo date('M d, Y h:i A', strtotime($receipt['paid_at'])); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600">System</span>
                    <span class="font-semibold"><?php echo strtoupper($receipt['client_system']); ?></span>
                </div>

                <?php if (isset($receipt['reference_no'])): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">Reference No.</span>
                    <span class="font-semibold"><?php echo $receipt['reference_no']; ?></span>
                </div>
                <?php endif; ?>

                <div class="pt-4 border-t mt-3">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold text-gray-700">Total Amount</span>
                        <span class="text-3xl font-bold text-blue-600">₱<?php echo number_format($receipt['amount'], 2); ?></span>
                    </div>
                </div>

            </div>

            <!-- Action Buttons -->
            <div class="mt-8 space-y-3">
                <button onclick="downloadReceiptImage()" 
                    class="action-btn w-full py-3 rounded-xl text-white font-medium 
                    bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 flex items-center justify-center gap-2">
                    <i class="fas fa-download"></i>
                    Download Receipt (Image)
                </button>
                
                <button onclick="window.close();" 
                    class="action-btn w-full py-3 rounded-xl text-white font-medium 
                    bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 flex items-center justify-center gap-2">
                    <i class="fas fa-times"></i>
                    Close Windows
                </button>
            </div>

        </div>
    </div>

    <!-- Auto Close Notice -->
    <div class="text-center text-gray-500 text-sm pb-6">
        This window will close automatically in <span id="countdown">30</span> seconds...
    </div>

</div>

<!-- Hidden receipt container for image generation -->
<div id="receipt-image-container">
    <div class="gcash-receipt-img">
        <!-- Header -->
        <div class="receipt-header-img">
            <img src="images/gcash_img.jpg" class="gcash-logo-img">
            <div class="status-text-img">PAYMENT SUCCESSFUL</div>
            <div class="receipt-title-img">Transaction Receipt</div>
            <div class="date-time-img">
                <?php echo date('M d, Y | h:i A'); ?>
            </div>
        </div>
        
        <!-- Amount -->
        <div class="amount-section-img">
            <div class="amount-label-img">Amount Paid</div>
            <div class="amount-number-img">₱<?php echo $receipt_amount; ?></div>
            <div class="verified-badge-img">
                <i class="fas fa-check-circle"></i>
                PAYMENT COMPLETED
            </div>
        </div>
        
        <!-- Transaction Details -->
        <div class="details-section-img">
            <div class="section-title-img">Transaction Details</div>
            
            <div class="detail-row-img">
                <div class="detail-label-img">Payment ID</div>
                <div class="detail-value-img"><?php echo $payment_id; ?></div>
            </div>
            
            <div class="detail-row-img">
                <div class="detail-label-img">Receipt No.</div>
                <div class="detail-value-img"><?php echo $receipt_number; ?></div>
            </div>
            
            <div class="detail-row-img">
                <div class="detail-label-img">Date & Time</div>
                <div class="detail-value-img"><?php echo $receipt_date; ?></div>
            </div>
            
            <div class="detail-row-img">
                <div class="detail-label-img">System</div>
                <div class="detail-value-img"><?php echo $system; ?></div>
            </div>
            
            <?php if ($reference_no): ?>
            <div class="detail-row-img">
                <div class="detail-label-img">Reference No.</div>
                <div class="detail-value-img"><?php echo $reference_no; ?></div>
            </div>
            <?php endif; ?>
            
            <div class="detail-row-img">
                <div class="detail-label-img">Status</div>
                <div class="detail-value-img" style="color: #10B981;">✓ Completed</div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer-img">
            <p>This is an electronic receipt. No signature required.</p>
            <p>For inquiries, contact: support@gcash.com</p>
            <p>Transaction ID: <?php echo $payment_id; ?></p>
            <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
            <div class="thankyou-img">
                Thank you for using GCash!
            </div>
        </div>
        
        <!-- Security Note -->
        <div class="security-note-img">
            <div class="security-title-img">
                <i class="fas fa-shield-alt"></i>
                Security Note
            </div>
            <div class="security-text-img">
                This receipt is valid for reference. Keep it for your records. 
                Do not share with unauthorized persons.
            </div>
        </div>
    </div>
</div>

<script>
    // Download receipt as image
    function downloadReceiptImage() {
        // Show loading state
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Image...';
        btn.disabled = true;
        
        // Get the receipt container
        const receiptContainer = document.getElementById('receipt-image-container');
        
        // Generate image using html2canvas with proper settings
        html2canvas(receiptContainer, {
            scale: 3,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
            width: 400,
            height: receiptContainer.scrollHeight,
            windowWidth: 400,
            onclone: function(clonedDoc) {
                // Ensure all styles are loaded in the cloned document
                const clonedContainer = clonedDoc.getElementById('receipt-image-container');
                clonedContainer.style.position = 'absolute';
                clonedContainer.style.left = '0';
                clonedContainer.style.top = '0';
                clonedContainer.style.width = '400px';
                clonedContainer.style.background = 'white';
            }
        }).then(canvas => {
            // Convert canvas to image
            const image = canvas.toDataURL('image/png', 1.0);
            
            // Create download link
            const link = document.createElement('a');
            link.download = 'GCash_Receipt_<?php echo $receipt_number; ?>.png';
            link.href = image;
            
            // Trigger download
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Restore button
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            // Reset countdown
            clearInterval(autoCloseTimer);
            seconds = 30;
            countdown.textContent = seconds;
            startAutoClose();
            
        }).catch(error => {
            console.error('Error generating image:', error);
            alert('Error generating receipt image. Please try again.');
            
            // Restore button
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
    
    // Auto-close countdown
    let seconds = 30;
    const countdown = document.getElementById('countdown');
    let autoCloseTimer;

    function startAutoClose() {
        autoCloseTimer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(autoCloseTimer);
                if (window.opener) {
                    window.close();
                }
            }
        }, 1000);
    }
    
    // Start countdown
    startAutoClose();
    
    // Reset timer on user interaction
    document.addEventListener('click', function() {
        clearInterval(autoCloseTimer);
        seconds = 30;
        countdown.textContent = seconds;
        startAutoClose();
    });
    
    // Also reset on keypress
    document.addEventListener('keypress', function() {
        clearInterval(autoCloseTimer);
        seconds = 30;
        countdown.textContent = seconds;
        startAutoClose();
    });
</script>

</body>
</html>