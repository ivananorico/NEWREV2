<?php
// revenue2/citizen_dashboard/market/market_application/paying.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Include database connection
include_once '../../../db/Market/market_db.php';

// Determine base URL based on whether we're on localhost or live domain
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
$base_url = $is_localhost 
    ? 'http://localhost/revenue2' 
    : 'https://revenuetreasury.goserveph.com';

// Market payment callback URL - dynamically generated
$market_callback_url = $base_url . '/citizen_dashboard/market/api/stall_rights_pay_api.php';

// Get applications with 'paying' status
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
<title>Payment Required | Market Rent Services</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
.status-badge { 
    padding: 0.25rem 0.75rem; 
    border-radius: 9999px; 
    font-size: 0.75rem; 
    font-weight: 600; 
}
.status-paying { 
    background-color: #8b5cf6; 
    color: white; 
}
.card {
    background: white;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}
.info-label {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}
.info-value {
    font-size: 0.875rem;
    color: #111827;
    font-weight: 500;
}
.glow-button {
    transition: all 0.3s ease;
}
.glow-button:hover {
    box-shadow: 0 4px 20px rgba(139, 92, 246, 0.3);
    transform: translateY(-2px);
}
.post-method-badge {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    margin-left: 0.5rem;
}
.new-tab-indicator {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.6rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    margin-left: 0.5rem;
}
.pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
</style>
</head>
<body class="bg-gray-50 min-h-screen">
<?php include '../../navbar.php'; ?>

<main class="max-w-6xl mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="bg-white rounded border border-gray-200 p-4">
            <div class="flex items-center mb-3">
                <a href="../market_services.php" class="text-purple-600 hover:text-purple-800 mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Payment Required</h1>
                    <p class="text-gray-600 text-sm">Complete payment to finalize your application</p>
                </div>
            </div>

            <?php if ($total_applications > 0): ?>
                <div class="flex items-center mt-3">
                    <div class="mr-3">
                        <div class="text-xl font-bold text-gray-900"><?php echo $total_applications; ?></div>
                        <div class="text-sm text-gray-500">Application<?php echo $total_applications > 1 ? 's' : ''; ?> requiring payment</div>
                    </div>
                    <div class="h-6 w-px bg-gray-300"></div>
                    <div class="ml-3">
                        <div class="status-badge status-paying">
                            <i class="fas fa-credit-card mr-1"></i>Payment Required
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
        <div class="mb-4 p-3 bg-red-50 text-red-700 rounded border border-red-200 text-sm">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Payment Success Message -->
    <?php if (isset($_GET['payment_success']) && $_GET['payment_success'] === 'true'): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 text-2xl mr-3"></i>
                <div>
                    <h3 class="font-bold text-green-800 mb-1">Payment Successful!</h3>
                    <p class="text-green-700 text-sm">Your payment has been processed. Your application status will update shortly.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="bg-white rounded border border-gray-200 p-6 text-center">
            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-credit-card text-purple-500 text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Pending Payments</h3>
            <p class="text-gray-600 mb-4">You don't have any applications requiring payment at this time.</p>
            <a href="../market_services.php" 
               class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 text-sm">
                <i class="fas fa-store mr-2"></i>Back to Market Services
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-4">
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
                    $monthly_rent = $app['monthly_rent'] ?? 0;
                    $total_amount_due = $app['total_amount_due'] ?? ($stall_rights_amount + $security_bond);
                    
                    // Get the registration ID
                    $registration_id = $app['registration_id'];
                    
                    // Create payment description
                    $payment_description = "Market Stall: {$app['stall_name']} - {$app['class_name']} Class";
                ?>

                <div class="card">
                    <!-- Header -->
                    <div class="p-4 border-b border-gray-100">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="flex items-center mb-1">
                                    <span class="text-lg font-bold text-gray-900 mr-3">
                                        #<?php echo $app['stall_rights_no']; ?>
                                    </span>
                                    <span class="status-badge status-paying">
                                        <i class="fas fa-credit-card mr-1"></i>Payment Required
                                    </span>
                                </div>
                                <div class="text-gray-600 text-sm">
                                    <i class="fas fa-store mr-1"></i>
                                    <?php echo $app['stall_name']; ?> • <?php echo $app['class_name']; ?> Class
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-500">Total Amount Due</div>
                                <div class="text-lg font-bold text-purple-600">
                                    ₱<?php echo number_format($total_amount_due, 2); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="px-4 py-3 bg-gray-50 border-y border-gray-100">
                        <div class="mb-1">
                            <div class="flex justify-between text-xs text-gray-600 mb-1">
                                <span>Application</span>
                                <span>Interview</span>
                                <span>Payment</span>
                                <span>Approval</span>
                            </div>
                            <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-purple-500" style="width: 75%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-4">
                        <div class="space-y-4">
                            <!-- Applicant Information Section -->
                            <div>
                                <h3 class="font-semibold text-gray-800 mb-3 border-b pb-2">Applicant Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-3">
                                        <!-- Personal Information -->
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2 text-sm">Personal Information</h4>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="info-label">Full Name</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($full_name); ?></div>
                                                </div>
                                                <div>
                                                    <div class="info-label">Email Address</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($app['email']); ?></div>
                                                </div>
                                                <div>
                                                    <div class="info-label">Mobile Number</div>
                                                    <div class="info-value"><?php echo $app['mobile']; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <!-- Application Information -->
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2 text-sm">Application Details</h4>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="info-label">Renter Code</div>
                                                    <div class="info-value font-bold text-purple-600"><?php echo $app['renter_code']; ?></div>
                                                </div>
                                                <div>
                                                    <div class="info-label">Stall Rights No.</div>
                                                    <div class="info-value font-bold"><?php echo $app['stall_rights_no']; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stall and Financial Information Section -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Stall Information -->
                                <div>
                                    <h3 class="font-semibold text-gray-800 mb-3 border-b pb-2">Stall Information</h3>
                                    <div class="space-y-3">
                                        <div>
                                            <div class="info-label">Stall Name</div>
                                            <div class="info-value"><?php echo htmlspecialchars($app['stall_name']); ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Business Name</div>
                                            <div class="info-value"><?php echo !empty($app['business_name']) ? htmlspecialchars($app['business_name']) : 'N/A'; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Business Type</div>
                                            <div class="info-value"><?php echo !empty($app['business_type']) ? htmlspecialchars($app['business_type']) : 'N/A'; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Stall Class</div>
                                            <div class="info-value font-bold text-purple-600"><?php echo $app['class_name']; ?> Class</div>
                                        </div>
                                        <?php if (!empty($app['market_name'])): ?>
                                        <div>
                                            <div class="info-label">Market Location</div>
                                            <div class="info-value"><?php echo htmlspecialchars($app['market_name']); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Financial Information -->
                                <div>
                                    <h3 class="font-semibold text-gray-800 mb-3 border-b pb-2">Payment Details</h3>
                                    <div class="space-y-4">
                                        <!-- One-time Payments -->
                                        <div class="p-3 bg-purple-50 rounded border border-purple-100">
                                            <h4 class="font-medium text-gray-800 mb-3">One-time Payments</h4>
                                            <div class="space-y-3">
                                                <?php if ($stall_rights_amount > 0): ?>
                                                <div class="flex justify-between items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-800">Stall Rights Fee</div>
                                                        <div class="text-xs text-gray-600">Non-refundable</div>
                                                    </div>
                                                    <div class="text-sm font-bold text-gray-900">
                                                        ₱<?php echo number_format($stall_rights_amount, 2); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($security_bond > 0): ?>
                                                <div class="flex justify-between items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-800">Security Bond</div>
                                                        <div class="text-xs text-gray-600">Refundable upon termination</div>
                                                    </div>
                                                    <div class="text-sm font-bold text-gray-900">
                                                        ₱<?php echo number_format($security_bond, 2); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="pt-3 mt-3 border-t border-purple-200">
                                                    <div class="flex justify-between items-center">
                                                        <div>
                                                            <div class="font-bold text-gray-900">Total Amount Due</div>
                                                            <div class="text-xs text-gray-600">Pay now to complete application</div>
                                                        </div>
                                                        <div class="text-lg font-bold text-purple-600">
                                                            ₱<?php echo number_format($total_amount_due, 2); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Monthly Rent -->
                                        <div class="p-3 bg-blue-50 rounded border border-blue-100">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <div class="info-label">Monthly Rent</div>
                                                    <div class="text-lg font-bold text-blue-700">
                                                        ₱<?php echo number_format($monthly_rent, 2); ?>
                                                    </div>
                                                    <div class="text-xs text-blue-600 mt-1">
                                                        Payable monthly after stall approval
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Payment Instructions -->
                                        <div class="p-3 bg-yellow-50 rounded border border-yellow-100">
                                            <h4 class="font-medium text-gray-800 mb-2">Payment Instructions</h4>
                                            <ul class="text-xs text-yellow-800 space-y-1">
                                                <li class="flex items-start">
                                                    <i class="fas fa-clock mt-1 mr-2"></i>
                                                    <span>Payment must be completed within 7 days</span>
                                                </li>
                                                <li class="flex items-start">
                                                    <i class="fas fa-check-circle mt-1 mr-2"></i>
                                                    <span>Stall assignment after payment confirmation</span>
                                                </li>
                                                <li class="flex items-start">
                                                    <i class="fas fa-file-contract mt-1 mr-2"></i>
                                                    <span>Contract will be issued after payment</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Action Section -->
                            <div class="pt-6 border-t border-gray-200">
                                <!-- CHANGED: POST with target="_blank" opens in new tab -->
                                <form id="paymentForm<?php echo $registration_id; ?>" 
                                      method="POST" 
                                      action="../../digital/index.php" 
                                      target="_blank">
                                    <!-- UNIVERSAL PAYMENT PARAMETERS -->
                                    <input type="hidden" name="system" value="market">
                                    <input type="hidden" name="ref" value="<?php echo $registration_id; ?>">
                                    <input type="hidden" name="amount" value="<?php echo $total_amount_due; ?>">
                                    <input type="hidden" name="purpose" value="<?php echo htmlspecialchars($payment_description); ?>">
                                    <input type="hidden" name="callback" value="<?php echo $market_callback_url; ?>">
                                    <input type="hidden" name="applicant_name" value="<?php echo htmlspecialchars($full_name); ?>">
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($app['email']); ?>">
                                    <input type="hidden" name="mobile" value="<?php echo htmlspecialchars($app['mobile']); ?>">
                                    
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between bg-purple-50 rounded-lg p-4">
                                        <div class="mb-3 sm:mb-0">
                                            <div class="font-medium text-purple-800 mb-1">Ready to Complete Payment?</div>
                                            <div class="text-sm text-purple-700">
                                                Click "Pay Now" to proceed with secure payment in new tab
                                            </div>
                                        </div>
                                        <button type="submit" 
                                                class="inline-flex items-center justify-center px-5 py-3 bg-purple-600 text-white rounded-lg 
                                                hover:bg-purple-700 transition-colors font-medium glow-button transform hover:scale-105">
                                            <i class="fas fa-external-link-alt mr-2"></i>
                                            Pay Now - ₱<?php echo number_format($total_amount_due, 2); ?>
                                        </button>
                                    </div>
                                    
                                    <div class="mt-3 text-center text-xs text-gray-500">
                                        <i class="fas fa-lock mr-1"></i>
                                        Secure POST transmission • Opens in new tab
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-3 bg-purple-50 border-t border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between">
                            <div class="mb-2 sm:mb-0">
                                <div class="font-medium text-purple-800">What happens after payment?</div>
                                <div class="text-purple-700 text-sm">
                                    Your application will be approved and you'll receive your stall contract via email.
                                </div>
                            </div>
                            <div class="text-purple-700 text-xs">
                                <i class="fas fa-clock mr-1"></i>
                                Payment valid for 7 days
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Statistics -->
        <div class="mt-6 bg-white rounded border border-gray-200 p-4">
            <h3 class="font-semibold text-gray-800 mb-3 text-sm">Payment Status Summary</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-3 bg-purple-50 rounded-lg border border-purple-100">
                    <div class="text-2xl font-bold text-purple-700"><?php echo $total_applications; ?></div>
                    <div class="text-xs text-purple-600 font-medium">Payment Required</div>
                </div>
                <div class="text-center p-3 bg-green-50 rounded-lg border border-green-100">
                    <div class="text-2xl font-bold text-green-700"><?php 
                        // Get count of approved applications
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rental_registration rr JOIN renter_owner ro ON rr.id = ro.registration_id WHERE ro.user_id = ? AND rr.application_status = 'approved'");
                        $stmt->execute([$user_id]);
                        $approved_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                        echo $approved_count;
                    ?></div>
                    <div class="text-xs text-green-600 font-medium">Approved</div>
                </div>
                <div class="text-center p-3 bg-yellow-50 rounded-lg border border-yellow-100">
                    <div class="text-2xl font-bold text-yellow-700"><?php 
                        // Get count of pending applications
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rental_registration rr JOIN renter_owner ro ON rr.id = ro.registration_id WHERE ro.user_id = ? AND rr.application_status = 'pending'");
                        $stmt->execute([$user_id]);
                        $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                        echo $pending_count;
                    ?></div>
                    <div class="text-xs text-yellow-600 font-medium">Pending</div>
                </div>
                <div class="text-center p-3 bg-blue-50 rounded-lg border border-blue-100">
                    <div class="text-2xl font-bold text-blue-700"><?php 
                        // Get count of interviewed applications
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rental_registration rr JOIN renter_owner ro ON rr.id = ro.registration_id WHERE ro.user_id = ? AND rr.application_status = 'interviewed'");
                        $stmt->execute([$user_id]);
                        $interviewed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                        echo $interviewed_count;
                    ?></div>
                    <div class="text-xs text-blue-600 font-medium">Interviewed</div>
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="mt-6 bg-white rounded border border-gray-200 p-4">
            <h3 class="font-semibold text-gray-800 mb-3 text-sm">Need Help?</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <div>
                        <div class="font-medium text-gray-700 text-sm">Contact Market Office</div>
                        <div class="text-gray-600 text-sm">(02) 8123-4567</div>
                    </div>
                    <div>
                        <div class="font-medium text-gray-700 text-sm">Email</div>
                        <div class="text-gray-600 text-sm">market-payments@goserveph.gov.ph</div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div>
                        <div class="font-medium text-gray-700 text-sm">Office Hours</div>
                        <div class="text-gray-600 text-sm">Mon-Fri: 8:00 AM - 5:00 PM</div>
                    </div>
                    <div>
                        <div class="font-medium text-gray-700 text-sm">Location</div>
                        <div class="text-gray-600 text-sm">Market Office, Public Market Building</div>
                    </div>
                </div>
            </div>
            
            <!-- FAQ Section -->
            <div class="mt-4 pt-4 border-t border-gray-200">
                <h4 class="font-medium text-gray-700 mb-2 text-sm">Frequently Asked Questions</h4>
                <div class="space-y-3 text-sm text-gray-600">
                    <div class="border-l-2 border-purple-300 pl-3">
                        <p class="font-medium text-gray-700">Will payment open in new window?</p>
                        <p class="text-gray-600">Yes, payment opens in secure new tab using POST method.</p>
                    </div>
                    <div class="border-l-2 border-purple-300 pl-3">
                        <p class="font-medium text-gray-700">Is my data secure?</p>
                        <p class="text-gray-600">Yes, POST method encrypts data in request body, not URL.</p>
                    </div>
                    <div class="border-l-2 border-purple-300 pl-3">
                        <p class="font-medium text-gray-700">What payment methods are accepted?</p>
                        <p class="text-gray-600">Online payment (credit/debit card, e-wallets).</p>
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

// Simple check for payment tab closure
document.addEventListener('DOMContentLoaded', function() {
    // Listen for form submissions
    document.querySelectorAll('form[target="_blank"]').forEach(form => {
        form.addEventListener('submit', function() {
            // Store the time when payment tab was opened
            localStorage.setItem('paymentTabOpened', Date.now());
        });
    });
    
    // Check if we should refresh (payment might have completed)
    if (localStorage.getItem('paymentTabOpened')) {
        const openedTime = parseInt(localStorage.getItem('paymentTabOpened'));
        const currentTime = Date.now();
        const fiveMinutes = 5 * 60 * 1000; // 5 minutes in milliseconds
        
        // If payment tab was opened more than 5 minutes ago, refresh
        if (currentTime - openedTime > fiveMinutes) {
            localStorage.removeItem('paymentTabOpened');
            window.location.reload();
        }
    }
});
</script>
</body>
</html>