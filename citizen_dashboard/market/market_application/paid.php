<?php
// revenue2/citizen_dashboard/market/market_application/paid.php
session_start();

// Check if user is logged in
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
/* Tailwind + custom classes */
.status-badge { display: inline-flex; align-items: center; padding: 0.375rem 0.875rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
.status-paid { background-color: #d1fae5; color: #065f46; border: 1px solid #10b981; }
.status-active { background-color: #dbeafe; color: #1e40af; border: 1px solid #3b82f6; }
.info-card-header { display: flex; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 2px solid #f3f4f6; }
.icon-circle { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; }
.info-label { font-size: 0.875rem; color: #6b7280; font-weight: 500; margin-bottom: 0.25rem; }
.info-value { font-size: 1rem; color: #111827; font-weight: 500; }
.empty-state { text-align: center; padding: 3rem 1.5rem; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.empty-icon { font-size: 3.5rem; color: #d1d5db; margin-bottom: 1.5rem; }
.progress-bar { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin: 0.75rem 0; }
.progress-fill { height: 100%; background: #10b981; border-radius: 3px; }
.document-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; text-align: center; transition: all 0.2s; background: white; }
.document-card:hover { border-color: #3b82f6; transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); cursor: pointer; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); padding: 20px; }
.modal-content { margin: auto; display: block; max-width: 90%; max-height: 90vh; border-radius: 8px; }
.modal-close { position: absolute; top: 20px; right: 35px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 1001; }
.modal-close:hover { color: #fbbf24; }
.modal-caption { text-align: center; color: white; padding: 10px 20px; position: absolute; bottom: 0; width: 100%; background: rgba(0,0,0,0.7); }
.urgent-notice { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 4px solid #d97706; }
.next-steps { background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); border-left: 4px solid #0369a1; }
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

<main class="max-w-6xl mx-auto px-4 py-8">

    <!-- Page Header Box -->
    <div class="mb-8">
        <div class="bg-white shadow-md rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-4">
                <a href="../market_services.php" class="text-blue-600 hover:text-blue-800 mr-4 text-lg"><i class="fas fa-arrow-left"></i></a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Paid Applications</h1>
                    <p class="text-gray-600 mt-1">Your stall rental applications with completed payment</p>
                </div>
            </div>

            <?php if ($total_applications > 0): ?>
                <div class="mt-4 flex items-center">
                    <div class="mr-4">
                        <div class="text-2xl font-bold text-gray-900"><?php echo $total_applications; ?></div>
                        <div class="text-sm text-gray-500">Paid Application<?php echo $total_applications > 1 ? 's' : ''; ?></div>
                    </div>
                    <div class="h-8 w-px bg-gray-300"></div>
                    <div class="ml-4">
                        <div class="status-badge status-paid"><i class="fas fa-check-circle mr-2"></i>Payment Completed</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Urgent Notice Box -->
    <?php if ($total_applications > 0): ?>
    <div class="mb-6 p-5 rounded-lg urgent-notice">
        <div class="flex items-start">
            <div class="mr-4 mt-1">
                <i class="fas fa-exclamation-triangle text-amber-600 text-xl"></i>
            </div>
            <div>
                <h3 class="font-bold text-amber-800 mb-2">Important Notice for All Paid Applications</h3>
                <p class="text-amber-700">
                    <strong>Please visit the Market Administration Office within 3 business days</strong> after payment 
                    to sign your Stall Rights Agreement and complete the final verification process. 
                    Bring your original valid ID and payment receipt.
                </p>
                <div class="mt-3 flex items-center text-amber-800">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    <span><strong>Office Location:</strong> Municipal Hall, Market Administration Office, Ground Floor</span>
                </div>
                <div class="mt-2 flex items-center text-amber-800">
                    <i class="fas fa-clock mr-2"></i>
                    <span><strong>Office Hours:</strong> Monday to Friday, 8:00 AM to 5:00 PM</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Messages -->
    <?php if (isset($error_message)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 rounded-lg border border-red-200">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-receipt"></i></div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Paid Applications</h3>
            <p class="text-gray-600 mb-6">You don't have any market stall applications with completed payment.</p>
            <a href="../market_portal_services/market_portal_services.php" 
               class="inline-flex items-center px-5 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-store mr-2"></i>Apply for a Stall
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($applications as $app): ?>
                <?php
                    // Prepare full name
                    $full_name = trim($app['first_name'] . ' ' . 
                        (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . 
                        $app['last_name'] . 
                        (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
                    
                    // Get document URLs
                    $documents = [
                        'Barangay Clearance' => $app['barangay_clearance'] ?? '',
                        '2x2 ID Photo' => $app['id_photo_2x2'] ?? '',
                        'Valid ID' => $app['valid_id'] ?? ''
                    ];
                    
                    // Calculate total amount
                    $total_amount = $app['stall_rights_amount'] + $app['monthly_rent'] + $app['security_bond'];
                    
                    // Calculate deadline for office visit (3 business days from payment)
                    $payment_date = new DateTime($app['payment_date']);
                    $deadline_date = clone $payment_date;
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
                    
                    // Stall status
                    $stall_status = $app['stall_status'] ?? 'pending';
                    $status_badge_class = $stall_status === 'active' ? 'status-active' : 'status-paid';
                    $status_badge_text = $stall_status === 'active' ? 'Stall Active' : 'Awaiting Activation';
                ?>
                
                <!-- Urgent Warning if deadline passed -->
                <?php if ($is_deadline_passed && $stall_status !== 'active'): ?>
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                        <div>
                            <h4 class="font-bold text-red-700">⚠️ Deadline Missed!</h4>
                            <p class="text-red-600">
                                You missed the deadline to sign your Stall Rights Agreement. 
                                <strong>Please visit the Market Administration Office immediately</strong> to avoid 
                                cancellation of your application and forfeiture of fees.
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <!-- Application Header -->
                    <div class="p-6 border-b border-gray-100 flex justify-between items-start">
                        <div>
                            <div class="flex items-center mb-3">
                                <span class="text-xl font-bold text-gray-900 mr-4"><?php echo $app['stall_rights_no']; ?></span>
                                <span class="status-badge status-paid"><i class="fas fa-check-circle mr-1"></i>Payment Completed</span>
                                <span class="status-badge <?php echo $status_badge_class; ?> ml-2">
                                    <i class="fas <?php echo $stall_status === 'active' ? 'fa-store' : 'fa-clock'; ?> mr-1"></i>
                                    <?php echo $status_badge_text; ?>
                                </span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-store mr-2"></i>
                                <span><?php echo $app['stall_name']; ?> - <?php echo $app['class_name']; ?> Class</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Paid on</div>
                            <div class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($app['payment_date'])); ?></div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="px-6 py-4 bg-green-50">
                        <div class="flex justify-between items-center mb-2">
                            <div class="text-sm font-medium text-green-800">Application Progress</div>
                            <div class="text-sm text-green-700">Step 3 of 4</div>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: 75%"></div></div>
                        <div class="flex justify-between text-xs text-green-600 mt-1">
                            <span><i class="fas fa-check-circle"></i> Application</span>
                            <span><i class="fas fa-check-circle"></i> Interview</span>
                            <span><i class="fas fa-check-circle"></i> Payment</span>
                            <span>Sign Agreement</span>
                        </div>
                    </div>

                    <!-- Office Visit Notice -->
                    <div class="px-6 py-4 next-steps">
                        <div class="flex items-start">
                            <div class="mr-4">
                                <i class="fas fa-clipboard-check text-blue-600 text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-blue-800 mb-2">Next Step: Sign Stall Rights Agreement</h4>
                                <p class="text-blue-700">
                                    <strong>Please visit the Market Administration Office to sign your Stall Rights Agreement.</strong>
                                </p>
                                
                                <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="flex items-center text-blue-800 mb-1">
                                            <i class="fas fa-calendar-alt mr-2"></i>
                                            <span class="font-medium">Deadline:</span>
                                        </div>
                                        <div class="text-blue-900 font-bold">
                                            <?php echo $deadline_formatted; ?>
                                            <?php if (!$is_deadline_passed): ?>
                                                <span class="text-sm font-normal ml-2">(<?php echo $days_remaining; ?> day<?php echo $days_remaining !== 1 ? 's' : ''; ?> remaining)</span>
                                            <?php else: ?>
                                                <span class="text-red-600 text-sm font-normal ml-2">(Deadline passed!)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="flex items-center text-blue-800 mb-1">
                                            <i class="fas fa-file-signature mr-2"></i>
                                            <span class="font-medium">To Bring:</span>
                                        </div>
                                        <div class="text-blue-900">
                                            1. Original Valid ID<br>
                                            2. Payment Receipt (Ref: <?php echo $app['reference_number']; ?>)
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content - Two Columns -->
                    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Personal Information Column -->
                        <div>
                            <div class="info-card-header">
                                <div class="icon-circle bg-blue-100 text-blue-600"><i class="fas fa-user"></i></div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Applicant Information</h3>
                                    <p class="text-sm text-gray-500">Renter details and contact information</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($full_name); ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Renter Code</div>
                                    <div class="info-value"><?php echo $app['renter_code']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Birth Date</div>
                                    <div class="info-value"><?php echo date('M j, Y', strtotime($app['birth_date'])); ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Gender</div>
                                    <div class="info-value"><?php echo ucfirst($app['gender'] ?? 'Not specified'); ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Mobile</div>
                                    <div class="info-value"><?php echo $app['mobile']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo $app['email']; ?></div>
                                </div>
                                <?php if (!empty($app['telephone'])): ?>
                                <div>
                                    <div class="info-label">Telephone</div>
                                    <div class="info-value"><?php echo $app['telephone']; ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Payment Details -->
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <h4 class="font-medium text-gray-800 mb-3 flex items-center">
                                    <i class="fas fa-receipt text-green-600 mr-2"></i>Payment Details
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <div class="info-label">Payment Date</div>
                                        <div class="info-value"><?php echo date('M j, Y h:i A', strtotime($app['payment_date'])); ?></div>
                                    </div>
                                    <div>
                                        <div class="info-label">Reference Number</div>
                                        <div class="info-value font-mono"><?php echo $app['reference_number']; ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Address -->
                            <div class="mt-4">
                                <div class="info-label">Complete Address</div>
                                <div class="info-value text-sm">
                                    <?php 
                                    $address_parts = [];
                                    if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                                    if (!empty($app['street'])) $address_parts[] = $app['street'];
                                    if (!empty($app['barangay'])) $address_parts[] = 'Brgy. ' . $app['barangay'];
                                    if (!empty($app['city'])) $address_parts[] = $app['city'];
                                    if (!empty($app['province'])) $address_parts[] = $app['province'];
                                    if (!empty($app['zip_code'])) $address_parts[] = $app['zip_code'];
                                    echo implode(', ', $address_parts);
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Business and Financial Information Column -->
                        <div>
                            <!-- Business Information -->
                            <div class="mb-8">
                                <div class="info-card-header">
                                    <div class="icon-circle bg-green-100 text-green-600"><i class="fas fa-briefcase"></i></div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Business Information</h3>
                                        <p class="text-sm text-gray-500">Stall and business details</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <div class="info-label">Business Name</div>
                                        <div class="info-value"><?php echo htmlspecialchars($app['business_name']); ?></div>
                                    </div>
                                    <div>
                                        <div class="info-label">Business Type</div>
                                        <div class="info-value"><?php echo ucfirst($app['business_type']); ?></div>
                                    </div>
                                    <div>
                                        <div class="info-label">Stall Name</div>
                                        <div class="info-value"><?php echo $app['stall_name']; ?></div>
                                    </div>
                                    <div>
                                        <div class="info-label">Stall Class</div>
                                        <div class="info-value"><?php echo $app['class_name']; ?> Class</div>
                                    </div>
                                    <?php if (!empty($app['market_name'])): ?>
                                    <div>
                                        <div class="info-label">Market</div>
                                        <div class="info-value"><?php echo $app['market_name']; ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($app['start_date'])): ?>
                                    <div>
                                        <div class="info-label">Contract Start</div>
                                        <div class="info-value"><?php echo date('M j, Y', strtotime($app['start_date'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Financial Details -->
                            <div>
                                <div class="info-card-header">
                                    <div class="icon-circle bg-purple-100 text-purple-600"><i class="fas fa-money-bill-wave"></i></div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Payment Summary</h3>
                                        <p class="text-sm text-gray-500">Paid amounts and fees</p>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Monthly Rent:</span>
                                        <span class="font-semibold text-green-600">₱<?php echo number_format($app['monthly_rent'], 2); ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Stall Rights Fee:</span>
                                        <span class="font-semibold text-red-600">₱<?php echo number_format($app['stall_rights_amount'], 2); ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Security Bond:</span>
                                        <span class="font-semibold text-orange-600">₱<?php echo number_format($app['security_bond'], 2); ?></span>
                                    </div>
                                    <div class="pt-3 border-t border-gray-200">
                                        <div class="flex justify-between items-center">
                                            <span class="text-lg font-bold text-gray-900">Total Amount Paid:</span>
                                            <span class="text-xl font-bold text-green-700">₱<?php echo number_format($total_amount, 2); ?></span>
                                        </div>
                                        <div class="mt-2 text-sm text-green-600 flex items-center">
                                            <i class="fas fa-check-circle mr-2"></i>
                                            <span>Payment completed on <?php echo date('M j, Y', strtotime($app['payment_date'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Documents Section -->
                    <div class="mt-8 pt-8 border-t border-gray-200 px-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-file-alt text-purple-600 mr-2"></i>Uploaded Documents
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($documents as $label => $doc_path): ?>
                                <?php if (!empty($doc_path)): ?>
                                    <?php
                                        $doc_url = getDocumentUrl($doc_path);
                                        $file_ext = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                                        $is_image = in_array($file_ext, ['jpg','jpeg','png','gif']);
                                        $file_name = basename($doc_path);
                                    ?>
                                    <div class="document-card" onclick="openModal('<?php echo $doc_url; ?>','<?php echo $label; ?>')">
                                        <div class="mb-3">
                                            <?php if ($is_image): ?>
                                                <i class="fas fa-image text-blue-500 text-3xl"></i>
                                            <?php else: ?>
                                                <i class="fas fa-file-pdf text-red-500 text-3xl"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo $label; ?></div>
                                        <div class="text-xs text-gray-500 mt-1 truncate" title="<?php echo htmlspecialchars($file_name); ?>">
                                            <?php echo htmlspecialchars($file_name); ?>
                                        </div>
                                        <div class="mt-2">
                                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">Click to view</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="document-card opacity-50 cursor-not-allowed">
                                        <div class="mb-3">
                                            <i class="fas fa-times-circle text-gray-300 text-3xl"></i>
                                        </div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo $label; ?></div>
                                        <div class="text-xs text-gray-500 mt-1">Not uploaded</div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Office Instructions -->
                    <div class="px-6 py-4 bg-blue-50 border-t border-gray-200">
                        <div class="flex items-start">
                            <div class="mr-4 mt-1">
                                <i class="fas fa-info-circle text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-blue-800 mb-2">Office Visit Instructions</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="font-medium text-blue-700 mb-1">What to do at the office:</div>
                                        <ul class="text-blue-600 text-sm list-disc pl-5 space-y-1">
                                            <li>Present your valid ID and payment receipt</li>
                                            <li>Sign the Stall Rights Agreement</li>
                                            <li>Receive your official stall assignment</li>
                                            <li>Get orientation about market rules</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <div class="font-medium text-blue-700 mb-1">After signing:</div>
                                        <ul class="text-blue-600 text-sm list-disc pl-5 space-y-1">
                                            <li>Your stall will be activated within 24 hours</li>
                                            <li>You'll receive market access credentials</li>
                                            <li>Monthly rent billing will start from contract date</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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