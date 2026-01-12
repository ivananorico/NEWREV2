<?php
// revenue2/citizen_dashboard/market/market_application/interviewed.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Citizen';

// Include database connection - FIXED PATH
include_once '../../../db/Market/market_db.php';

// Function to get proper document URL (same as pending.php)
function getDocumentUrl(string $dbPath): string
{
    $clean = str_replace(['../', './'], '', $dbPath);
    $clean = ltrim($clean, '/');
    if (strpos($clean, 'revenue2/') === 0) {
        $clean = substr($clean, strlen('revenue2/'));
    }
    return '/revenue2/' . $clean;
}

// Function to format interview date
function formatInterviewDate($date) {
    if (!$date || $date == '0000-00-00 00:00:00') {
        return 'Not scheduled';
    }
    $dateTime = new DateTime($date);
    return $dateTime->format('F d, Y \a\t h:i A');
}

// Get applications with 'interviewed' status
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
          AND rr.application_status = 'interviewed'
        ORDER BY rr.interview_date DESC, rr.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = count($applications);
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Get count for other statuses for notification
$status_counts = [];
try {
    $stmt = $pdo->prepare("
        SELECT application_status, COUNT(*) as count
        FROM rental_registration
        WHERE user_id = ?
        GROUP BY application_status
    ");
    $stmt->execute([$user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $status_counts[$row['application_status']] = $row['count'];
    }
} catch(PDOException $e) {
    error_log("Status counts error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Interview Completed | Market Rent Services</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
/* Tailwind + custom classes */
.status-badge { display: inline-flex; align-items: center; padding: 0.375rem 0.875rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
.status-interviewed { background-color: #f0fdfa; color: #0d9488; border: 1px solid #5eead4; }
.info-card-header { display: flex; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 2px solid #f3f4f6; }
.icon-circle { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; }
.info-label { font-size: 0.875rem; color: #6b7280; font-weight: 500; margin-bottom: 0.25rem; }
.info-value { font-size: 1rem; color: #111827; font-weight: 500; }
.empty-state { text-align: center; padding: 3rem 1.5rem; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.empty-icon { font-size: 3.5rem; color: #d1d5db; margin-bottom: 1.5rem; }
.progress-bar { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin: 0.75rem 0; }
.progress-fill { height: 100%; border-radius: 3px; }
.document-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; text-align: center; transition: all 0.2s; background: white; }
.document-card:hover { border-color: #0d9488; transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); cursor: pointer; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); padding: 20px; }
.modal-content { margin: auto; display: block; max-width: 90%; max-height: 90vh; border-radius: 8px; }
.modal-close { position: absolute; top: 20px; right: 35px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 1001; }
.modal-close:hover { color: #fbbf24; }
.modal-caption { text-align: center; color: white; padding: 10px 20px; position: absolute; bottom: 0; width: 100%; background: rgba(0,0,0,0.7); }
.process-step { position: relative; padding-left: 2rem; }
.process-step:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    background-color: #f0fdfa;
    border: 2px solid #0d9488;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: bold;
    color: #0d9488;
}
.process-step.completed:before {
    content: '✓';
    background-color: #0d9488;
    color: white;
}
.process-step.current:before {
    content: '!';
    background-color: #fef3c7;
    border-color: #f59e0b;
    color: #d97706;
}
.interview-card { transition: all 0.3s ease; border-left: 4px solid #0d9488; }
.interview-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(13, 148, 136, 0.1); }
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
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <a href="../market_services.php" class="text-teal-600 hover:text-teal-800 mr-4 text-lg"><i class="fas fa-arrow-left"></i></a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Interview Completed</h1>
                        <p class="text-gray-600 mt-1">Your market stall interview has been completed. Waiting for next steps.</p>
                    </div>
                </div>
                <?php if ($total_applications > 0): ?>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Last updated</div>
                        <div class="font-medium text-gray-900"><?php echo date('M j, Y'); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_applications > 0): ?>
                <div class="mt-4 flex items-center">
                    <div class="mr-4">
                        <div class="text-2xl font-bold text-gray-900"><?php echo $total_applications; ?></div>
                        <div class="text-sm text-gray-500">Application<?php echo $total_applications > 1 ? 's' : ''; ?></div>
                    </div>
                    <div class="h-8 w-px bg-gray-300"></div>
                    <div class="ml-4">
                        <div class="status-badge status-interviewed"><i class="fas fa-user-check mr-2"></i>Interview Completed</div>
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

    <!-- Process Status -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-clipboard-check text-teal-600 mr-2"></i>
            Application Process Status
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="process-step completed">
                <span class="block text-sm font-medium text-gray-700">Application Submitted</span>
                <span class="text-xs text-gray-500">Documents uploaded</span>
            </div>
            <div class="process-step completed">
                <span class="block text-sm font-medium text-gray-700">Interview Scheduled</span>
                <span class="text-xs text-gray-500">Date & time set</span>
            </div>
            <div class="process-step current">
                <span class="block text-sm font-medium text-teal-700">Interview Completed</span>
                <span class="text-xs text-teal-600">Evaluation in progress</span>
            </div>
            <div class="process-step">
                <span class="block text-sm font-medium text-gray-700">Payment Required</span>
                <span class="text-xs text-gray-500">Pay fees</span>
            </div>
            <div class="process-step">
                <span class="block text-sm font-medium text-gray-700">Approved & Contract</span>
                <span class="text-xs text-gray-500">Stall assignment</span>
            </div>
        </div>
        
        <div class="bg-teal-50 border border-teal-200 rounded-lg p-4">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-teal-600 mt-1 mr-3"></i>
                <div>
                    <p class="text-teal-800 font-medium mb-1">What happens next?</p>
                    <p class="text-teal-700 text-sm">
                        Your interview has been completed successfully. The market administrator is now evaluating your application. 
                        You will be notified via email/SMS when your application moves to the next stage (payment or approval).
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($total_applications === 0): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-user-check text-teal-300"></i></div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Interviewed Applications</h3>
            <p class="text-gray-600 mb-6">You don't have any applications with completed interviews.</p>
            <div class="space-x-4">
                <a href="../market_services.php" 
                   class="inline-flex items-center px-5 py-3 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition">
                    <i class="fas fa-store mr-2"></i>View All Services
                </a>
                <a href="pending.php" 
                   class="inline-flex items-center px-5 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-list mr-2"></i>View All Applications
                </a>
            </div>
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
                ?>

                <div class="interview-card bg-white rounded-xl shadow-sm border border-gray-200">
                    <!-- Application Header -->
                    <div class="p-6 border-b border-gray-100 flex justify-between items-start">
                        <div>
                            <div class="flex items-center mb-3">
                                <span class="text-xl font-bold text-gray-900 mr-4"><?php echo $app['stall_rights_no']; ?></span>
                                <span class="status-badge status-interviewed"><i class="fas fa-user-check mr-1"></i>Interview Completed</span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-store mr-2"></i>
                                <span><?php echo $app['stall_name']; ?> - <?php echo $app['class_name']; ?> Class</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Interviewed on</div>
                            <div class="font-medium text-gray-900"><?php echo formatInterviewDate($app['interview_date']); ?></div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="px-6 py-4 bg-teal-50">
                        <div class="flex justify-between items-center mb-2">
                            <div class="text-sm font-medium text-teal-800">Application Progress</div>
                            <div class="text-sm text-teal-700">Step 3 of 5</div>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: 60%; background: #0d9488;"></div></div>
                        <div class="flex justify-between text-xs text-teal-600 mt-1">
                            <span>Application</span>
                            <span>Interview Scheduled</span>
                            <span>Interview Completed</span>
                            <span>Payment</span>
                            <span>Approval</span>
                        </div>
                    </div>

                    <!-- Interview Details -->
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-clipboard-list text-teal-600 mr-2"></i>
                            Interview Details
                        </h3>
                        
                        <div class="bg-teal-50 border border-teal-100 rounded-lg p-4 mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <div class="info-label">Interview Date & Time</div>
                                    <div class="info-value font-medium text-teal-700">
                                        <?php echo formatInterviewDate($app['interview_date']); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="info-label">Interviewer</div>
                                    <div class="info-value font-medium text-teal-700">
                                        <?php echo htmlspecialchars($app['interviewer'] ?? 'Market Administrator'); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="info-label">Application Date</div>
                                    <div class="info-value">
                                        <?php echo date('F d, Y', strtotime($app['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($app['interview_notes'])): ?>
                            <div class="mt-4 pt-4 border-t border-teal-200">
                                <div class="info-label">Interviewer Notes</div>
                                <div class="text-sm text-teal-700 bg-white p-3 rounded border border-teal-200 mt-1">
                                    <i class="fas fa-quote-left text-teal-300 mr-1"></i>
                                    <?php echo htmlspecialchars($app['interview_notes']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Main Content - Two Columns -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
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
                                        <div class="info-label">Mobile</div>
                                        <div class="info-value"><?php echo $app['mobile']; ?></div>
                                    </div>
                                    <div>
                                        <div class="info-label">Email</div>
                                        <div class="info-value"><?php echo $app['email']; ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Business Information Column -->
                            <div>
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
                                </div>
                            </div>
                        </div>

                        <!-- Documents Section -->
                        <div class="mt-8 pt-8 border-t border-gray-200">
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
                    </div>

                    <!-- Next Steps Section -->
                    <div class="px-6 py-4 bg-teal-50 border-t border-gray-200">
                        <div class="flex items-start">
                            <i class="fas fa-forward text-teal-600 mt-1 mr-3"></i>
                            <div class="flex-1">
                                <div class="font-medium text-teal-800 mb-2">Next Steps</div>
                                <ul class="space-y-1 text-sm text-teal-700">
                                    <li class="flex items-start">
                                        <i class="fas fa-hourglass-half mt-1 mr-2"></i>
                                        <span>Your application is being evaluated by the market committee</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-bell mt-1 mr-2"></i>
                                        <span>You will receive an email/SMS notification for the next step</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-phone mt-1 mr-2"></i>
                                        <span>Market office may contact you if additional information is needed</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex flex-wrap gap-3">
                            <a href="mailto:market@goserveph.gov.ph?subject=Question%20about%20Application%20<?php echo urlencode($app['stall_rights_no']); ?>" 
                               class="inline-flex items-center px-4 py-2 bg-teal-100 text-teal-700 rounded-lg hover:bg-teal-200 transition-colors text-sm">
                                <i class="fas fa-envelope mr-2"></i>
                                Contact Market Office
                            </a>
                            <a href="pending.php" 
                               class="inline-flex items-center px-4 py-2 bg-white text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                                <i class="fas fa-list mr-2"></i>
                                View All Applications
                            </a>
                            <a href="../market_services.php" 
                               class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Services
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Statistics -->
        <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h5 class="font-medium text-gray-700 mb-4">Your Application Status Summary</h5>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-3 bg-teal-50 rounded-lg border border-teal-100">
                    <div class="text-2xl font-bold text-teal-700"><?php echo $status_counts['interviewed'] ?? 0; ?></div>
                    <div class="text-xs text-teal-600 font-medium">Interview Completed</div>
                </div>
                <div class="text-center p-3 bg-blue-50 rounded-lg border border-blue-100">
                    <div class="text-2xl font-bold text-blue-700"><?php echo $status_counts['pending'] ?? 0; ?></div>
                    <div class="text-xs text-blue-600 font-medium">Pending</div>
                </div>
                <div class="text-center p-3 bg-purple-50 rounded-lg border border-purple-100">
                    <div class="text-2xl font-bold text-purple-700"><?php echo $status_counts['paying'] ?? 0; ?></div>
                    <div class="text-xs text-purple-600 font-medium">Payment Required</div>
                </div>
                <div class="text-center p-3 bg-green-50 rounded-lg border border-green-100">
                    <div class="text-2xl font-bold text-green-700"><?php echo $status_counts['approved'] ?? 0; ?></div>
                    <div class="text-xs text-green-600 font-medium">Approved</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Contact Information -->
    <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-headset text-teal-600 mr-2"></i>
            Need Help?
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="font-medium text-gray-700 mb-3">Market Office Contact</h4>
                <div class="space-y-3 text-gray-600">
                    <p class="flex items-center">
                        <i class="fas fa-phone mr-3 text-teal-500"></i>
                        (02) 8123-4567 (Market Office)
                    </p>
                    <p class="flex items-center">
                        <i class="fas fa-envelope mr-3 text-teal-500"></i>
                        market@goserveph.gov.ph
                    </p>
                    <p class="flex items-center">
                        <i class="fas fa-clock mr-3 text-teal-500"></i>
                        Mon-Fri: 8:00 AM - 5:00 PM
                    </p>
                    <p class="flex items-center">
                        <i class="fas fa-map-marker-alt mr-3 text-teal-500"></i>
                        Public Market Building, Ground Floor
                    </p>
                </div>
            </div>
            <div>
                <h4 class="font-medium text-gray-700 mb-3">Frequently Asked Questions</h4>
                <div class="space-y-3 text-sm text-gray-600">
                    <div class="border-l-2 border-teal-300 pl-3">
                        <p class="font-medium text-gray-700">How long does evaluation take?</p>
                        <p class="text-gray-600">Typically 3-5 business days after interview completion.</p>
                    </div>
                    <div class="border-l-2 border-teal-300 pl-3">
                        <p class="font-medium text-gray-700">What happens after evaluation?</p>
                        <p class="text-gray-600">You'll receive payment instructions or stall assignment details.</p>
                    </div>
                    <div class="border-l-2 border-teal-300 pl-3">
                        <p class="font-medium text-gray-700">Can I change stall preference?</p>
                        <p class="text-gray-600">Changes may be possible before final approval. Contact market office.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Modal functionality
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

// Auto-refresh every 30 minutes to check for status updates
setTimeout(function() {
    window.location.reload();
}, 30 * 60 * 1000); // 30 minutes

// Debug logging
console.log('Interviewed page loaded');
console.log('Total interviewed applications: <?php echo count($applications); ?>');
</script>
</body>
</html>