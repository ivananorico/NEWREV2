<?php
// revenue2/citizen_dashboard/market/market_application/paid.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Include database connection
include_once '../../../db/Market/market_db.php';

// Function to get proper document URL
function getDocumentUrl(string $dbPath): string
{
    $clean = str_replace(['../', './'], '', $dbPath);
    $clean = ltrim($clean, '/');
    if (strpos($clean, 'revenue2/') === 0) {
        $clean = substr($clean, strlen('revenue2/'));
    }
    return '/revenue2/' . $clean;
}

// Fetch user's paid applications
$applications = [];
$total_applications = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            rr.*,
            ro.*,
            rs.business_name,
            rs.business_type,
            rs.start_date,
            rs.status as stall_status,
            s.name as stall_name,
            s.class_id,
            sr.class_name,
            sr.price as class_price,
            m.name as market_name,
            rr.payment_date,
            rr.reference_number,
            rr.stall_rights_no
        FROM rental_registration rr
        JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN maps m ON rr.map_id = m.id
        WHERE ro.user_id = ? 
          AND rr.application_status = 'paid'
        ORDER BY rr.payment_date DESC, rr.created_at DESC
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
<title>Paid Applications - Market Rent Services</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
.status-badge { 
    padding: 0.25rem 0.75rem; 
    border-radius: 9999px; 
    font-size: 0.75rem; 
    font-weight: 600; 
}
.status-paid { 
    background-color: #d1fae5; 
    color: #065f46; 
}
.status-active { 
    background-color: #dbeafe; 
    color: #1e40af; 
}
.status-pending { 
    background-color: #fef3c7; 
    color: #92400e; 
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
.document-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    transition: all 0.2s;
    background: white;
}
.document-card:hover {
    border-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    cursor: pointer;
}
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.9);
    padding: 20px;
}
.modal-content {
    margin: auto;
    display: block;
    max-width: 90%;
    max-height: 90vh;
    border-radius: 8px;
}
.modal-close {
    position: absolute;
    top: 20px;
    right: 35px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    z-index: 1001;
}
.modal-close:hover {
    color: #fbbf24;
}
.modal-caption {
    text-align: center;
    color: white;
    padding: 10px 20px;
    position: absolute;
    bottom: 0;
    width: 100%;
    background: rgba(0,0,0,0.7);
}
.urgent-notice {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-left: 4px solid #d97706;
}
.next-steps {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    border-left: 4px solid #0369a1;
}
</style>
</head>
<body class="bg-gray-50 min-h-screen">
<?php include '../../navbar.php'; ?>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="modal-close">&times;</span>
    <img class="modal-content" id="modalImage">
    <div id="modalCaption" class="modal-caption"></div>
</div>

<main class="max-w-6xl mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="bg-white rounded border border-gray-200 p-4">
            <div class="flex items-center mb-3">
                <a href="../market_services.php" class="text-blue-600 hover:text-blue-800 mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Paid Applications</h1>
                    <p class="text-gray-600 text-sm">Applications with completed payment</p>
                </div>
            </div>

            <?php if ($total_applications > 0): ?>
                <div class="flex items-center mt-3">
                    <div class="mr-3">
                        <div class="text-xl font-bold text-gray-900"><?php echo $total_applications; ?></div>
                        <div class="text-sm text-gray-500">Application<?php echo $total_applications > 1 ? 's' : ''; ?></div>
                    </div>
                    <div class="h-6 w-px bg-gray-300"></div>
                    <div class="ml-3">
                        <div class="status-badge status-paid">
                            <i class="fas fa-check-circle mr-1"></i>Payment Completed
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Urgent Notice for All Paid Applications -->
    <?php if ($total_applications > 0): ?>
    <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded">
        <div class="flex items-start">
            <div class="mr-3 mt-1">
                <i class="fas fa-exclamation-triangle text-amber-600"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-amber-800 mb-2 text-sm">Important Notice for All Paid Applications</h3>
                <p class="text-amber-700 text-sm mb-2">
                    <strong>Please visit the Market Administration Office within 3 business days</strong> after payment 
                    to sign your Stall Rights Agreement and complete the final verification process.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-amber-800">
                    <div class="flex items-center">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        <span><strong>Location:</strong> Municipal Hall, Market Administration Office</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-clock mr-2"></i>
                        <span><strong>Hours:</strong> Mon-Fri, 8:00 AM to 5:00 PM</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
        <div class="mb-4 p-3 bg-red-50 text-red-700 rounded border border-red-200 text-sm">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="bg-white rounded border border-gray-200 p-6 text-center">
            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-receipt text-gray-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Paid Applications</h3>
            <p class="text-gray-600 mb-4">You don't have any applications with completed payment.</p>
            <a href="../market_portal_services/market_portal_services.php" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                <i class="fas fa-store mr-2"></i>Apply for a Stall
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
                    
                    // Build full address
                    $address_parts = [];
                    if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                    if (!empty($app['street'])) $address_parts[] = $app['street'];
                    if (!empty($app['barangay'])) $address_parts[] = 'Brgy. ' . $app['barangay'];
                    if (!empty($app['city'])) $address_parts[] = $app['city'];
                    if (!empty($app['province'])) $address_parts[] = $app['province'];
                    if (!empty($app['zip_code'])) $address_parts[] = $app['zip_code'];
                    $full_address = implode(', ', $address_parts);
                    
                    // Format dates
                    $birth_date = !empty($app['birth_date']) ? date('F j, Y', strtotime($app['birth_date'])) : 'N/A';
                    $payment_date = !empty($app['payment_date']) ? date('F j, Y g:i A', strtotime($app['payment_date'])) : 'N/A';
                    $start_date = !empty($app['start_date']) ? date('F j, Y', strtotime($app['start_date'])) : 'Not set';
                    
                    // Calculate amounts
                    $stall_rights_amount = $app['stall_rights_amount'] ?? 0;
                    $security_bond = $app['security_bond'] ?? 0;
                    $monthly_rent = $app['monthly_rent'] ?? 0;
                    $total_amount_paid = $stall_rights_amount + $security_bond;
                    
                    // Stall status
                    $stall_status = $app['stall_status'] ?? 'pending';
                    $status_badge_class = $stall_status === 'active' ? 'status-active' : 'status-pending';
                    $status_badge_text = $stall_status === 'active' ? 'Stall Active' : 'Awaiting Activation';
                    $status_icon = $stall_status === 'active' ? 'fa-store' : 'fa-clock';
                    
                    // Calculate deadline for office visit (3 business days from payment)
                    if (!empty($app['payment_date'])) {
                        $payment_date_obj = new DateTime($app['payment_date']);
                        $deadline_date = clone $payment_date_obj;
                        $business_days_added = 0;
                        while ($business_days_added < 3) {
                            $deadline_date->modify('+1 day');
                            // Skip Saturdays (6) and Sundays (7)
                            if ($deadline_date->format('N') < 6) {
                                $business_days_added++;
                            }
                        }
                        $deadline_formatted = $deadline_date->format('F j, Y');
                        
                        // Check if deadline has passed
                        $today = new DateTime();
                        $is_deadline_passed = $today > $deadline_date;
                        $days_remaining = $today->diff($deadline_date)->days;
                    }
                    
                    // Get document URLs
                    $documents = [
                        'Barangay Clearance' => $app['barangay_clearance'] ?? '',
                        '2x2 ID Photo' => $app['id_photo_2x2'] ?? '',
                        'Valid ID' => $app['valid_id'] ?? ''
                    ];
                ?>

                <!-- Urgent Warning if deadline passed -->
                <?php if (isset($is_deadline_passed) && $is_deadline_passed && $stall_status !== 'active'): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3 mt-1"></i>
                        <div>
                            <h4 class="font-bold text-red-700 mb-1 text-sm">⚠️ Deadline Missed!</h4>
                            <p class="text-red-600 text-sm">
                                You missed the deadline to sign your Stall Rights Agreement. 
                                <strong>Please visit the Market Administration Office immediately</strong> to avoid 
                                cancellation of your application and forfeiture of fees.
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card">
                    <!-- Header -->
                    <div class="p-4 border-b border-gray-100">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="flex items-center mb-1">
                                    <span class="text-lg font-bold text-gray-900 mr-3">
                                        #<?php echo $app['stall_rights_no']; ?>
                                    </span>
                                    <span class="status-badge status-paid">
                                        <i class="fas fa-check-circle mr-1"></i>Paid
                                    </span>
                                    <span class="status-badge <?php echo $status_badge_class; ?> ml-2">
                                        <i class="fas <?php echo $status_icon; ?> mr-1"></i>
                                        <?php echo $status_badge_text; ?>
                                    </span>
                                </div>
                                <div class="text-gray-600 text-sm">
                                    <i class="fas fa-store mr-1"></i>
                                    <?php echo $app['stall_name']; ?> • <?php echo $app['class_name']; ?> Class
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-500">Paid on</div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo date('M j, Y', strtotime($app['payment_date'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="px-4 py-3 bg-green-50 border-y border-gray-100">
                        <div class="mb-1">
                            <div class="flex justify-between text-xs text-green-700 mb-1">
                                <span><i class="fas fa-check-circle"></i> Application</span>
                                <span><i class="fas fa-check-circle"></i> Interview</span>
                                <span><i class="fas fa-check-circle"></i> Payment</span>
                                <span>Sign Agreement</span>
                            </div>
                            <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-green-500" style="width: 75%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Office Visit Notice -->
                    <div class="px-4 py-3 next-steps">
                        <div class="flex items-start">
                            <div class="mr-3 mt-1">
                                <i class="fas fa-clipboard-check text-blue-600"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-blue-800 mb-2 text-sm">Next Step: Sign Stall Rights Agreement</h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <div class="flex items-center text-blue-800 mb-1">
                                            <i class="fas fa-calendar-alt mr-2 text-xs"></i>
                                            <span class="font-medium text-sm">Deadline:</span>
                                        </div>
                                        <div class="text-blue-900 font-bold text-sm">
                                            <?php echo $deadline_formatted ?? 'Not set'; ?>
                                            <?php if (isset($is_deadline_passed) && !$is_deadline_passed): ?>
                                                <span class="text-xs font-normal ml-2">(<?php echo $days_remaining; ?> day<?php echo $days_remaining !== 1 ? 's' : ''; ?> remaining)</span>
                                            <?php elseif (isset($is_deadline_passed) && $is_deadline_passed): ?>
                                                <span class="text-red-600 text-xs font-normal ml-2">(Deadline passed!)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="flex items-center text-blue-800 mb-1">
                                            <i class="fas fa-file-signature mr-2 text-xs"></i>
                                            <span class="font-medium text-sm">To Bring:</span>
                                        </div>
                                        <div class="text-blue-900 text-sm">
                                            1. Original Valid ID<br>
                                            2. Payment Receipt (Ref: <?php echo $app['reference_number']; ?>)
                                        </div>
                                    </div>
                                </div>
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
                                                    <div class="info-label">Gender</div>
                                                    <div class="info-value">
                                                        <?php 
                                                        if (!empty($app['gender'])) {
                                                            echo ucfirst($app['gender']);
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="info-label">Birth Date</div>
                                                    <div class="info-value"><?php echo $birth_date; ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Contact Information -->
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2 text-sm">Contact Information</h4>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="info-label">Email Address</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($app['email']); ?></div>
                                                </div>
                                                <div>
                                                    <div class="info-label">Mobile Number</div>
                                                    <div class="info-value"><?php echo $app['mobile']; ?></div>
                                                </div>
                                                <?php if (!empty($app['telephone'])): ?>
                                                <div>
                                                    <div class="info-label">Telephone</div>
                                                    <div class="info-value"><?php echo $app['telephone']; ?></div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <!-- Address Information -->
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2 text-sm">Address</h4>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="info-label">Complete Address</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($full_address); ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Emergency Contact -->
                                        <?php if (!empty($app['emergency_name'])): ?>
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2 text-sm">Emergency Contact</h4>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="info-label">Contact Person</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($app['emergency_name']); ?></div>
                                                </div>
                                                <div>
                                                    <div class="info-label">Contact Number</div>
                                                    <div class="info-value"><?php echo $app['emergency_contact']; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Payment Information -->
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2 text-sm">Payment Details</h4>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="info-label">Reference Number</div>
                                                    <div class="info-value font-mono text-sm"><?php echo $app['reference_number']; ?></div>
                                                </div>
                                                <div>
                                                    <div class="info-label">Payment Date</div>
                                                    <div class="info-value"><?php echo $payment_date; ?></div>
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
                                            <div class="info-value font-bold text-blue-600"><?php echo $app['class_name']; ?> Class</div>
                                        </div>
                                        <?php if (!empty($app['market_name'])): ?>
                                        <div>
                                            <div class="info-label">Market Location</div>
                                            <div class="info-value"><?php echo htmlspecialchars($app['market_name']); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($app['start_date'])): ?>
                                        <div>
                                            <div class="info-label">Contract Start</div>
                                            <div class="info-value"><?php echo $start_date; ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Financial Information -->
                                <div>
                                    <h3 class="font-semibold text-gray-800 mb-3 border-b pb-2">Payment Summary</h3>
                                    <div class="space-y-4">
                                        <!-- Monthly Rent -->
                                        <div class="p-3 bg-blue-50 rounded border border-blue-100">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <div class="info-label">Monthly Rent</div>
                                                    <div class="text-lg font-bold text-blue-700">
                                                        ₱<?php echo number_format($monthly_rent, 2); ?>
                                                    </div>
                                                    <div class="text-xs text-blue-600 mt-1">
                                                        Will start after stall activation
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Paid Amounts -->
                                        <div class="p-3 bg-green-50 rounded border border-green-100">
                                            <h4 class="font-medium text-gray-800 mb-2">Paid Amounts</h4>
                                            <div class="space-y-2">
                                                <?php if ($stall_rights_amount > 0): ?>
                                                <div class="flex justify-between">
                                                    <div>
                                                        <div class="text-sm text-gray-800">Stall Rights Fee</div>
                                                        <div class="text-xs text-gray-600">Non-refundable</div>
                                                    </div>
                                                    <div class="text-sm font-bold text-red-600">
                                                        ₱<?php echo number_format($stall_rights_amount, 2); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($security_bond > 0): ?>
                                                <div class="flex justify-between">
                                                    <div>
                                                        <div class="text-sm text-gray-800">Security Bond</div>
                                                        <div class="text-xs text-gray-600">Refundable</div>
                                                    </div>
                                                    <div class="text-sm font-bold text-orange-600">
                                                        ₱<?php echo number_format($security_bond, 2); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="pt-2 mt-2 border-t border-green-200">
                                                    <div class="flex justify-between items-center">
                                                        <div>
                                                            <div class="font-bold text-gray-900">Total Paid</div>
                                                            <div class="text-xs text-green-600">
                                                                <i class="fas fa-check-circle mr-1"></i>Payment completed
                                                            </div>
                                                        </div>
                                                        <div class="text-lg font-bold text-green-700">
                                                            ₱<?php echo number_format($total_amount_paid, 2); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Documents Section -->
                            <div>
                                <h3 class="font-semibold text-gray-800 mb-3 border-b pb-2">Uploaded Documents</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <?php foreach ($documents as $label => $doc_path): ?>
                                        <?php if (!empty($doc_path)): ?>
                                            <?php
                                                $doc_url = getDocumentUrl($doc_path);
                                                $file_ext = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                                                $is_image = in_array($file_ext, ['jpg','jpeg','png','gif']);
                                                $file_name = basename($doc_path);
                                            ?>
                                            <div class="document-card" onclick="openModal('<?php echo $doc_url; ?>','<?php echo $label; ?>')">
                                                <div class="mb-2">
                                                    <?php if ($is_image): ?>
                                                        <i class="fas fa-image text-blue-500 text-2xl"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-file-pdf text-red-500 text-2xl"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-sm font-medium text-gray-900 truncate"><?php echo $label; ?></div>
                                                <div class="text-xs text-gray-500 mt-1 truncate" title="<?php echo htmlspecialchars($file_name); ?>">
                                                    <?php echo htmlspecialchars($file_name); ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">Click to view</span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="document-card opacity-50 cursor-not-allowed">
                                                <div class="mb-2">
                                                    <i class="fas fa-times-circle text-gray-300 text-2xl"></i>
                                                </div>
                                                <div class="text-sm font-medium text-gray-900"><?php echo $label; ?></div>
                                                <div class="text-xs text-gray-500 mt-1">Not uploaded</div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-3 bg-blue-50 border-t border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between">
                            <div class="mb-2 sm:mb-0">
                                <div class="font-medium text-gray-800 text-sm">Office Visit Instructions</div>
                                <div class="text-gray-600 text-xs">
                                    1. Present your valid ID and payment receipt<br>
                                    2. Sign the Stall Rights Agreement<br>
                                    3. Receive your official stall assignment<br>
                                    4. Get orientation about market rules
                                </div>
                            </div>
                            <div class="text-blue-700 text-xs">
                                <i class="fas fa-info-circle mr-1"></i>
                                After signing, stall will be activated within 24 hours
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Help Section -->
        <div class="mt-6 bg-white rounded border border-gray-200 p-4">
            <h3 class="font-semibold text-gray-800 mb-3 text-sm">Need Help?</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <div>
                        <div class="font-medium text-gray-700 text-sm">Contact Market Office</div>
                        <div class="text-gray-600 text-sm">(02) 1234-5678</div>
                    </div>
                    <div>
                        <div class="font-medium text-gray-700 text-sm">Email</div>
                        <div class="text-gray-600 text-sm">market@lgu.gov.ph</div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div>
                        <div class="font-medium text-gray-700 text-sm">Office Hours</div>
                        <div class="text-gray-600 text-sm">Mon-Fri: 8:00 AM - 5:00 PM</div>
                    </div>
                    <div>
                        <div class="font-medium text-gray-700 text-sm">Location</div>
                        <div class="text-gray-600 text-sm">Market Administration Office, Municipal Hall</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
const modal = document.getElementById("imageModal");
const modalImg = document.getElementById("modalImage");
const captionText = document.getElementById("modalCaption");

function openModal(imageSrc, caption) {
    if (imageSrc) {
        modal.style.display = "block";
        modalImg.src = imageSrc;
        captionText.innerHTML = caption;
    } else {
        alert("No document available to view.");
    }
}

document.getElementsByClassName("modal-close")[0].onclick = () => modal.style.display = "none";
window.onclick = (event) => { if(event.target==modal) modal.style.display="none"; }
document.addEventListener('keydown', (event) => { if(event.key==='Escape' && modal.style.display==='block') modal.style.display='none'; });
</script>
</body>
</html>