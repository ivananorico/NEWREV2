<?php
// revenue2/citizen_dashboard/market/market_application/paying.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Citizen';

// Include database connection
include_once '../../../db/Market/market_db.php';

// Market payment callback URL
$market_callback_url = 'http://localhost/revenue2/citizen_dashboard/market/api/stall_rights_pay_api.php';

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Get applications with 'paying' status - FIXED QUERY
$applications = [];
$total_applications = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            rr.id as registration_id,
            rr.stall_rights_no,
            rr.stall_rights_amount,
            rr.monthly_rent,
            rr.security_bond,
            rr.total_amount_due,
            rr.application_status,
            rr.stall_id,
            rr.map_id,
            ro.first_name,
            ro.middle_name,
            ro.last_name,
            ro.suffix,
            ro.email,
            ro.mobile,
            ro.renter_code,
            rs.business_name,
            rs.business_type,
            rs.start_date,
            s.name as stall_name,
            s.class_id,
            sr.class_name,
            sr.price as class_price,
            m.name as market_name
        FROM rental_registration rr
        JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN maps m ON rr.map_id = m.id
        WHERE ro.user_id = ? 
          AND rr.application_status = 'paying'
        ORDER BY rr.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = count($applications);
    
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Market Stall Payment | LGU System</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
.status-badge { 
    display: inline-flex; 
    align-items: center; 
    padding: 0.4rem 0.8rem; 
    border-radius: 6px; 
    font-size: 0.8rem; 
    font-weight: 500; 
}
.status-paying { background-color: #8b5cf6; color: white; }
.card {
    background: white;
    border-radius: 0.625rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.glow-button {
    transition: all 0.3s ease;
}
.glow-button:hover {
    box-shadow: 0 4px 20px rgba(139, 92, 246, 0.3);
    transform: translateY(-2px);
}
.pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
.payment-breakdown {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-left: 4px solid #8b5cf6;
}
</style>
</head>
<body class="bg-gray-50">
<?php include '../../navbar.php'; ?>

<main class="max-w-6xl mx-auto px-4 py-8">

    <!-- Page Header -->
    <div class="mb-8">
        <div class="bg-white shadow-md rounded-xl p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <a href="../market_services.php" class="text-purple-600 hover:text-purple-800 mr-4 text-lg"><i class="fas fa-arrow-left"></i></a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Market Stall Payment</h1>
                        <p class="text-gray-600 mt-1">Pay stall rights and security bond to complete your application</p>
                    </div>
                </div>
                <?php if ($total_applications > 0): ?>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Payment Required</div>
                        <div class="font-medium text-gray-900"><?php echo $total_applications; ?> application(s)</div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_applications > 0): ?>
                <div class="mt-4 flex items-center">
                    <div class="mr-4">
                        <div class="text-2xl font-bold text-gray-900">Ready to Pay</div>
                        <div class="text-sm text-gray-500">Complete your stall application</div>
                    </div>
                    <div class="h-8 w-px bg-gray-300"></div>
                    <div class="ml-4">
                        <div class="status-badge status-paying pulse"><i class="fas fa-credit-card mr-2"></i>Payment Required</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($error_message)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 rounded-lg border border-red-200">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-credit-card text-purple-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-800 mb-3">No Pending Payments</h3>
            <p class="text-gray-600 mb-8 text-sm leading-relaxed">
                You don't have any market stall applications requiring payment at the moment.
            </p>
            <div class="space-y-3">
                <a href="../market_services.php" 
                   class="inline-flex items-center px-5 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-store mr-2"></i>Back to Market Services
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($applications as $app): ?>
                <?php
                    // Prepare full name
                    $full_name = trim($app['first_name'] . ' ' . 
                        (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . 
                        $app['last_name'] . 
                        (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
                    
                    // Get payment breakdown
                    $stall_rights_amount = $app['stall_rights_amount'] ?? 0;
                    $security_bond = $app['security_bond'] ?? 0;
                    $total_amount_due = $app['total_amount_due'] ?? ($stall_rights_amount + $security_bond);
                    
                    // Get the registration ID
                    $registration_id = $app['registration_id'];
                    
                    // Create payment description
                    $payment_description = "Market Stall: {$app['stall_name']} - {$app['class_name']} Class";
                ?>

                <!-- Payment Card -->
                <div class="card overflow-hidden">
                    <!-- Header -->
                    <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-indigo-50">
                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                            <div class="mb-4 md:mb-0">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mr-4">
                                        <i class="fas fa-store text-white text-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($app['business_name']); ?></h3>
                                        <p class="text-gray-600">
                                            <i class="fas fa-hashtag text-gray-400 mr-1 text-sm"></i>
                                            Application #: <?php echo $app['stall_rights_no']; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500 font-medium">Total Amount Due</p>
                                <p class="text-3xl font-bold text-purple-600"><?php echo formatCurrency($total_amount_due); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Left Column: Application Details -->
                            <div>
                                <h4 class="font-semibold text-gray-800 mb-4 text-lg flex items-center">
                                    <i class="fas fa-clipboard-list text-purple-600 mr-2"></i>
                                    Application Details
                                </h4>
                                
                                <div class="space-y-4">
                                    <div>
                                        <div class="text-sm text-gray-500 font-medium mb-1">Applicant Name</div>
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($full_name); ?></div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <div class="text-sm text-gray-500 font-medium mb-1">Stall Name</div>
                                            <div class="text-gray-900 font-medium"><?php echo $app['stall_name']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-sm text-gray-500 font-medium mb-1">Stall Class</div>
                                            <div class="text-gray-900 font-medium"><?php echo $app['class_name']; ?> Class</div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="text-sm text-gray-500 font-medium mb-1">Renter Code</div>
                                        <div class="text-gray-900 font-medium"><?php echo $app['renter_code']; ?></div>
                                    </div>
                                    
                                    <div>
                                        <div class="text-sm text-gray-500 font-medium mb-1">Business Type</div>
                                        <div class="text-gray-900 font-medium"><?php echo ucfirst($app['business_type']); ?></div>
                                    </div>
                                    
                                    <?php if (!empty($app['market_name'])): ?>
                                    <div>
                                        <div class="text-sm text-gray-500 font-medium mb-1">Market</div>
                                        <div class="text-gray-900 font-medium"><?php echo $app['market_name']; ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Status Timeline -->
                                <div class="mt-8 pt-8 border-t border-gray-200">
                                    <h5 class="font-medium text-gray-700 mb-4">Application Progress</h5>
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-1">
                                            <div class="flex justify-between text-sm text-gray-600 mb-2">
                                                <span>Application</span>
                                                <span>Interview</span>
                                                <span class="font-bold text-purple-600">Payment</span>
                                                <span>Contract</span>
                                            </div>
                                            <div class="relative h-2 bg-gray-200 rounded-full overflow-hidden">
                                                <div class="absolute h-full bg-purple-600" style="width: 75%"></div>
                                            </div>
                                        </div>
                                        <div class="text-sm font-medium text-purple-600">Step 3 of 4</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Payment Breakdown -->
                            <div>
                                <h4 class="font-semibold text-gray-800 mb-4 text-lg flex items-center">
                                    <i class="fas fa-money-bill-wave text-green-600 mr-2"></i>
                                    Payment Breakdown
                                </h4>
                                
                                <div class="payment-breakdown p-6 rounded-xl">
                                    <div class="space-y-4">
                                        <!-- Stall Rights Fee -->
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="font-medium text-gray-800">Stall Rights Fee</div>
                                                <div class="text-sm text-gray-600">One-time fee for stall rights</div>
                                            </div>
                                            <div class="text-xl font-bold text-gray-900"><?php echo formatCurrency($stall_rights_amount); ?></div>
                                        </div>
                                        
                                        <!-- Security Bond -->
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="font-medium text-gray-800">Security Bond</div>
                                                <div class="text-sm text-gray-600">Refundable security deposit</div>
                                            </div>
                                            <div class="text-xl font-bold text-gray-900"><?php echo formatCurrency($security_bond); ?></div>
                                        </div>
                                        
                                        <!-- Divider -->
                                        <div class="border-t border-gray-300 pt-4 mt-2">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <div class="font-bold text-lg text-gray-800">Total Amount Due</div>
                                                    <div class="text-sm text-gray-600">Pay now to complete application</div>
                                                </div>
                                                <div class="text-3xl font-bold text-purple-600"><?php echo formatCurrency($total_amount_due); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Important Notes -->
                                    <div class="mt-6 pt-6 border-t border-gray-300">
                                        <h5 class="font-medium text-gray-700 mb-2 flex items-center">
                                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                            Important Notes
                                        </h5>
                                        <ul class="text-sm text-gray-600 space-y-1">
                                            <li class="flex items-start">
                                                <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                                <span>Stall rights fee is non-refundable</span>
                                            </li>
                                            <li class="flex items-start">
                                                <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                                <span>Security bond is refundable upon contract termination</span>
                                            </li>
                                            <li class="flex items-start">
                                                <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                                <span>Payment must be completed within 7 days</span>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Payment Button -->
                                    <div class="mt-8">
                                        <form id="paymentForm<?php echo $registration_id; ?>" method="GET" action="../../digital/index.php">
                                            <!-- UNIVERSAL PAYMENT PARAMETERS -->
                                            <input type="hidden" name="system" value="market">
                                            <input type="hidden" name="ref" value="<?php echo $registration_id; ?>">
                                            <input type="hidden" name="amount" value="<?php echo $total_amount_due; ?>">
                                            <input type="hidden" name="purpose" value="<?php echo htmlspecialchars($payment_description); ?>">
                                            <input type="hidden" name="callback" value="<?php echo $market_callback_url; ?>">
                                            
                                            <button type="submit" 
                                                    class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-4 rounded-lg font-bold text-lg glow-button flex items-center justify-center hover:from-purple-700 hover:to-indigo-700">
                                                <i class="fas fa-credit-card mr-3 text-xl"></i>
                                                Pay Now - <?php echo formatCurrency($total_amount_due); ?>
                                            </button>
                                        </form>
                                        
                                        <p class="text-center text-sm text-gray-500 mt-3">
                                            <i class="fas fa-lock mr-1"></i>
                                            Secure SSL encrypted payment
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                            <div>
                                <div class="font-medium text-gray-900">What happens after payment?</div>
                                <div class="text-sm text-gray-600">
                                    After successful payment, your application will be approved and you'll receive your stall contract.
                                </div>
                            </div>
                            <div class="text-sm text-purple-700 mt-2 md:mt-0">
                                <i class="fas fa-clock mr-1"></i>
                                Payment valid for 7 days
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Help Section -->
        <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-question-circle text-purple-600 mr-2"></i>
                Need Assistance?
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-700 mb-3">Payment Questions</h4>
                    <div class="space-y-3 text-gray-600">
                        <p class="flex items-center">
                            <i class="fas fa-phone mr-3 text-purple-500"></i>
                            (02) 8123-4567 (Market Office)
                        </p>
                        <p class="flex items-center">
                            <i class="fas fa-envelope mr-3 text-purple-500"></i>
                            market-payments@goserveph.gov.ph
                        </p>
                        <p class="flex items-center">
                            <i class="fas fa-clock mr-3 text-purple-500"></i>
                            Mon-Fri: 8:00 AM - 5:00 PM
                        </p>
                    </div>
                </div>
                <div>
                    <h4 class="font-medium text-gray-700 mb-3">Frequently Asked Questions</h4>
                    <div class="space-y-3 text-sm text-gray-600">
                        <div class="border-l-2 border-purple-300 pl-3">
                            <p class="font-medium text-gray-700">When will I get my stall?</p>
                            <p class="text-gray-600">Within 3 business days after payment confirmation.</p>
                        </div>
                        <div class="border-l-2 border-purple-300 pl-3">
                            <p class="font-medium text-gray-700">Is the security bond refundable?</p>
                            <p class="text-gray-600">Yes, upon proper termination of your contract.</p>
                        </div>
                        <div class="border-l-2 border-purple-300 pl-3">
                            <p class="font-medium text-gray-700">What payment methods are accepted?</p>
                            <p class="text-gray-600">Online payment (credit/debit card, e-wallets).</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
// Auto-refresh page every 2 minutes to check for status updates
setTimeout(() => {
    window.location.reload();
}, 120000);
</script>
</body>
</html>