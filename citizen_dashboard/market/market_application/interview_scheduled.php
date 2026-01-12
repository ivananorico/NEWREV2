<?php
// revenue2/citizen_dashboard/market/market_application/interview_scheduled.php
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

// Fetch user's scheduled interview applications
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
          AND rr.application_status = 'interview_scheduled'
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
<title>Interview Scheduled - Market Rent Services</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
/* Tailwind + custom classes */
.status-badge { display: inline-flex; align-items: center; padding: 0.375rem 0.875rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
.status-scheduled { background-color: #f0f9ff; color: #0369a1; border: 1px solid #0ea5e9; }
.info-card-header { display: flex; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 2px solid #f3f4f6; }
.icon-circle { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; }
.info-label { font-size: 0.875rem; color: #6b7280; font-weight: 500; margin-bottom: 0.25rem; }
.info-value { font-size: 1rem; color: #111827; font-weight: 500; }
.empty-state { text-align: center; padding: 3rem 1.5rem; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.empty-icon { font-size: 3.5rem; color: #d1d5db; margin-bottom: 1.5rem; }
.progress-bar { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin: 0.75rem 0; }
.progress-fill { height: 100%; background: #3b82f6; border-radius: 3px; }
.document-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; text-align: center; transition: all 0.2s; background: white; }
.document-card:hover { border-color: #3b82f6; transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); cursor: pointer; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); padding: 20px; }
.modal-content { margin: auto; display: block; max-width: 90%; max-height: 90vh; border-radius: 8px; }
.modal-close { position: absolute; top: 20px; right: 35px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 1001; }
.modal-close:hover { color: #fbbf24; }
.modal-caption { text-align: center; color: white; padding: 10px 20px; position: absolute; bottom: 0; width: 100%; background: rgba(0,0,0,0.7); }
.interview-card { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 2px solid #0ea5e9; border-radius: 12px; }
.countdown-timer { font-family: monospace; font-size: 1.25rem; font-weight: bold; color: #0369a1; }
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
                    <h1 class="text-2xl font-bold text-gray-900">Scheduled Interviews</h1>
                    <p class="text-gray-600 mt-1">Your market stall application interviews are scheduled</p>
                </div>
            </div>

            <?php if ($total_applications > 0): ?>
                <div class="mt-4 flex items-center">
                    <div class="mr-4">
                        <div class="text-2xl font-bold text-gray-900"><?php echo $total_applications; ?></div>
                        <div class="text-sm text-gray-500">Interview<?php echo $total_applications > 1 ? 's' : ''; ?> Scheduled</div>
                    </div>
                    <div class="h-8 w-px bg-gray-300"></div>
                    <div class="ml-4">
                        <div class="status-badge status-scheduled"><i class="fas fa-calendar-check mr-2"></i>Interview Scheduled</div>
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
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-calendar-times"></i></div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Scheduled Interviews</h3>
            <p class="text-gray-600 mb-6">You don't have any scheduled interviews for market stall applications.</p>
            <a href="pending.php" 
               class="inline-flex items-center px-5 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-clock mr-2"></i>Check Pending Applications
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
                    
                    // Format interview date and time - use created_at as placeholder
                    $interview_date = !empty($app['created_at']) ? date('F j, Y', strtotime($app['created_at'] . ' + 7 days')) : date('F j, Y', strtotime('+7 days'));
                    $interview_time = '10:00 AM'; // Default time
                    $interview_datetime = $interview_date . ' at ' . $interview_time;
                    
                    // Calculate days until interview (using created_at + 7 days)
                    $today = new DateTime();
                    $interview_day = new DateTime($interview_date);
                    $days_until = $today->diff($interview_day)->days;
                    
                    // Calculate total amount (for information only)
                    $total_amount = ($app['stall_rights_amount'] ?? 0) + ($app['monthly_rent'] ?? 0) + ($app['security_bond'] ?? 0);
                ?>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <!-- Application Header -->
                    <div class="p-6 border-b border-gray-100 flex justify-between items-start">
                        <div>
                            <div class="flex items-center mb-3">
                                <span class="text-xl font-bold text-gray-900 mr-4"><?php echo $app['stall_rights_no'] ?? 'N/A'; ?></span>
                                <span class="status-badge status-scheduled"><i class="fas fa-calendar-check mr-1"></i>Interview Scheduled</span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-store mr-2"></i>
                                <span><?php echo $app['stall_name'] ?? 'N/A'; ?> - <?php echo $app['class_name'] ?? 'N/A'; ?> Class</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Application Date</div>
                            <div class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($app['created_at'])); ?></div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="px-6 py-4 bg-blue-50">
                        <div class="flex justify-between items-center mb-2">
                            <div class="text-sm font-medium text-blue-800">Application Progress</div>
                            <div class="text-sm text-blue-700">Step 1.5 of 4 - Interview Scheduled</div>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: 37.5%"></div></div>
                        <div class="flex justify-between text-xs text-blue-600 mt-1">
                            <span>Application</span>
                            <span>Interview</span>
                            <span>Payment</span>
                            <span>Approval</span>
                        </div>
                    </div>

                    <!-- Interview Details Card -->
                    <div class="mx-6 my-6 p-6 interview-card">
                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                            <div class="mb-4 md:mb-0">
                                <div class="flex items-center mb-3">
                                    <div class="icon-circle bg-white text-blue-600 mr-4">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-900">Interview Appointment</h3>
                                        <p class="text-gray-600">Please arrive 15 minutes before your scheduled time</p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                    <div>
                                        <div class="info-label">Interview Date & Time</div>
                                        <div class="info-value text-lg font-bold text-blue-700">
                                            <i class="fas fa-clock mr-2"></i><?php echo $interview_datetime; ?>
                                        </div>
                                        <?php if ($days_until !== null): ?>
                                            <div class="mt-2">
                                                <?php if ($days_until == 0): ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                                        <i class="fas fa-exclamation-circle mr-1"></i> Interview is TODAY
                                                    </span>
                                                <?php elseif ($days_until == 1): ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                                        <i class="fas fa-exclamation-circle mr-1"></i> Interview is TOMORROW
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-sm text-gray-600">
                                                        <i class="fas fa-calendar-day mr-1"></i> 
                                                        <?php echo $days_until; ?> day<?php echo $days_until > 1 ? 's' : ''; ?> from now
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div>
                                        <div class="info-label">Interview Location</div>
                                        <div class="info-value">
                                            <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
                                            Market Administration Office, 2nd Floor
                                        </div>
                                        
                                        <div class="mt-4">
                                            <div class="info-label">Contact Person</div>
                                            <div class="info-value">
                                                <i class="fas fa-user-tie mr-2 text-blue-600"></i>
                                                Market Administrator
                                                <span class="ml-2 text-sm text-gray-600">
                                                    <i class="fas fa-phone-alt mr-1"></i>(02) 1234-5680
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="md:pl-6 md:border-l md:border-blue-300">
                                <div class="text-center">
                                    <div class="text-sm font-medium text-gray-600 mb-2">Days Until Interview</div>
                                    <div class="countdown-timer" id="countdown-<?php echo $app['id']; ?>">
                                        <?php echo $days_until; ?> days
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php echo date('l', strtotime($interview_date)); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 pt-6 border-t border-blue-200">
                            <div class="info-label">Important Notes</div>
                            <div class="info-value text-sm bg-white p-4 rounded-lg border border-blue-100">
                                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                Please bring original copies of all uploaded documents for verification. 
                                Dress appropriately and arrive 15 minutes before your scheduled time.
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
                            </div>
                            
                            <!-- Address -->
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <h4 class="font-medium text-gray-800 mb-3">Complete Address</h4>
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

                        <!-- Business and Interview Preparation Column -->
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
                                        <div class="info-value"><?php echo htmlspecialchars($app['business_name'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <div class="info-label">Business Type</div>
                                        <div class="info-value"><?php echo ucfirst($app['business_type'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <div class="info-label">Stall Name</div>
                                        <div class="info-value"><?php echo $app['stall_name'] ?? 'N/A'; ?></div>
                                    </div>
                                    <div>
                                        <div class="info-label">Stall Class</div>
                                        <div class="info-value"><?php echo $app['class_name'] ?? 'N/A'; ?> Class</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Required Documents for Interview -->
                            <div>
                                <div class="info-card-header">
                                    <div class="icon-circle bg-yellow-100 text-yellow-600"><i class="fas fa-clipboard-check"></i></div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Required for Interview</h3>
                                        <p class="text-sm text-gray-500">Bring these documents to your interview</p>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 mt-1">
                                            <i class="fas fa-file-alt text-green-500"></i>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">Original Documents</div>
                                            <div class="text-xs text-gray-600">Bring original copies of all uploaded documents for verification</div>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 mt-1">
                                            <i class="fas fa-id-card text-blue-500"></i>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">Valid Government ID</div>
                                            <div class="text-xs text-gray-600">Any government-issued ID with photo</div>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 mt-1">
                                            <i class="fas fa-credit-card text-purple-500"></i>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">Payment Readiness</div>
                                            <div class="text-xs text-gray-600">Be prepared to discuss payment options if approved</div>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 mt-1">
                                            <i class="fas fa-business-time text-orange-500"></i>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">Business Plan (Optional)</div>
                                            <div class="text-xs text-gray-600">Brief description of your proposed business operations</div>
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

                    <!-- Interview Actions & Reminders -->
                    <div class="px-6 py-4 bg-blue-50 border-t border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                            <div class="mb-4 md:mb-0">
                                <div class="font-medium text-gray-900">Interview Preparation</div>
                                <div class="text-sm text-gray-600">
                                    <i class="fas fa-lightbulb mr-1 text-yellow-500"></i>
                                    Dress appropriately and arrive 15 minutes early
                                </div>
                            </div>
                            <div class="space-x-3">
                                <button onclick="addToCalendar('<?php echo $interview_datetime; ?>', 'Market Stall Interview - <?php echo $app['stall_name'] ?? ''; ?>', 'Market Administration Office, 2nd Floor')" 
                                        class="inline-flex items-center px-4 py-2 bg-white text-blue-600 border border-blue-600 rounded-lg hover:bg-blue-50 transition">
                                    <i class="fas fa-calendar-plus mr-2"></i> Add to Calendar
                                </button>
                                <button onclick="downloadInterviewSlip('<?php echo $app['stall_rights_no'] ?? ''; ?>', '<?php echo $interview_datetime; ?>', '<?php echo htmlspecialchars($full_name); ?>')" 
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                    <i class="fas fa-print mr-2"></i> Print Interview Slip
                                </button>
                                <button onclick="contactInterviewer('(02) 1234-5680')" 
                                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                    <i class="fas fa-phone-alt mr-2"></i> Contact Office
                                </button>
                            </div>
                        </div>
                        
                        <!-- Interview Reminders -->
                        <div class="mt-4 pt-4 border-t border-blue-200">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="flex items-start">
                                    <i class="fas fa-map-signs text-blue-500 mt-1 mr-2"></i>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">Location</div>
                                        <div class="text-xs text-gray-600">Market Administration Office, 2nd Floor, Public Market Building</div>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-folder-open text-green-500 mt-1 mr-2"></i>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">Documents</div>
                                        <div class="text-xs text-gray-600">Bring all original documents in a folder</div>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-question-circle text-purple-500 mt-1 mr-2"></i>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">Questions</div>
                                        <div class="text-xs text-gray-600">Prepare questions about the stall and terms</div>
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

// Interview-related functions
function addToCalendar(datetime, title, location) {
    // Simplified calendar add function
    const eventDetails = {
        title: title,
        start: datetime,
        location: location,
        description: 'Market Stall Rental Interview'
    };
    
    // Create .ics file or Google Calendar link
    const googleCalendarUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(title)}&dates=${getGoogleCalendarFormat(datetime)}&details=Market%20Stall%20Rental%20Interview&location=${encodeURIComponent(location)}`;
    
    window.open(googleCalendarUrl, '_blank');
    
    alert('Opening Google Calendar to add event. You can also add to other calendars manually.');
}

function getGoogleCalendarFormat(datetimeStr) {
    // Parse the datetime string (format: "F j, Y at h:i A")
    const date = new Date(datetimeStr.replace(' at ', ' '));
    const pad = (n) => n < 10 ? '0' + n : n;
    const year = date.getFullYear();
    const month = pad(date.getMonth() + 1);
    const day = pad(date.getDate());
    const hours = pad(date.getHours());
    const minutes = pad(date.getMinutes());
    
    // Create end time (1 hour after start)
    const endDate = new Date(date.getTime() + 60 * 60 * 1000);
    const endHours = pad(endDate.getHours());
    const endMinutes = pad(endDate.getMinutes());
    
    return `${year}${month}${day}T${hours}${minutes}00/${year}${month}${day}T${endHours}${endMinutes}00`;
}

function downloadInterviewSlip(stallRightsNo, interviewDatetime, applicantName) {
    // Create a printable interview slip
    const slipContent = `
        <html>
        <head>
            <title>Interview Slip - ${stallRightsNo}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #0ea5e9; padding-bottom: 20px; }
                .details { margin: 30px 0; }
                .detail-row { margin-bottom: 15px; }
                .label { font-weight: bold; color: #0369a1; }
                .footer { margin-top: 40px; font-size: 12px; color: #666; text-align: center; border-top: 1px solid #ddd; padding-top: 20px; }
                .instructions { background: #f0f9ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
                @media print {
                    body { padding: 0; }
                    button { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Market Stall Rental Interview Slip</h2>
                <h3>Stall Rights No: ${stallRightsNo}</h3>
            </div>
            
            <div class="details">
                <div class="detail-row">
                    <span class="label">Applicant Name:</span> ${applicantName}
                </div>
                <div class="detail-row">
                    <span class="label">Interview Date & Time:</span> ${interviewDatetime}
                </div>
                <div class="detail-row">
                    <span class="label">Interview Location:</span> Market Administration Office, 2nd Floor
                </div>
                <div class="detail-row">
                    <span class="label">Contact:</span> (02) 1234-5680
                </div>
            </div>
            
            <div class="instructions">
                <h4>Instructions:</h4>
                <ul>
                    <li>Present this slip at the interview location</li>
                    <li>Bring all original documents for verification</li>
                    <li>Arrive 15 minutes before your scheduled time</li>
                    <li>Dress appropriately for the interview</li>
                </ul>
            </div>
            
            <div class="footer">
                <p><strong>Generated on:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                <p>Market Administration Office • Public Market Building</p>
                <p>GoServePH Local Government Unit</p>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <button onclick="window.print()" style="background: #0ea5e9; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                    Print This Slip
                </button>
            </div>
        </body>
        </html>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(slipContent);
    printWindow.document.close();
}

function contactInterviewer(phoneNumber) {
    if (confirm(`Call market office at ${phoneNumber}?`)) {
        window.location.href = `tel:${phoneNumber}`;
    }
}
</script>
</body>
</html>