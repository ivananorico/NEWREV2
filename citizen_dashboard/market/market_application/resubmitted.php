<?php
// revenue2/citizen_dashboard/market/market_application/resubmitted.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
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

// Check if we're showing a specific application or listing all
if (isset($_GET['ref']) && isset($_GET['id'])) {
    // Show success confirmation for specific application
    include 'resubmitted_success.php'; // Separate success page
    exit();
}

// List all resubmitted applications
try {
    $stmt = $pdo->prepare("
        SELECT 
            rr.*,
            ro.*,
            rs.business_name,
            rs.business_type,
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
          AND rr.application_status = 'resubmitted'
        ORDER BY rr.updated_at DESC
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
<title>Resubmitted Applications - Market Rent Services</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
/* Tailwind + custom classes */
.status-badge { display: inline-flex; align-items: center; padding: 0.375rem 0.875rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
.status-resubmitted { background-color: #dbeafe; color: #1e40af; border: 1px solid #3b82f6; }
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
                    <h1 class="text-2xl font-bold text-gray-900">Resubmitted Applications</h1>
                    <p class="text-gray-600 mt-1">Your corrected applications are being reviewed</p>
                </div>
            </div>

            <?php if ($total_applications > 0): ?>
                <div class="mt-4 flex items-center">
                    <div class="mr-4">
                        <div class="text-2xl font-bold text-gray-900"><?php echo $total_applications; ?></div>
                        <div class="text-sm text-gray-500">Application<?php echo $total_applications > 1 ? 's' : ''; ?> Resubmitted</div>
                    </div>
                    <div class="h-8 w-px bg-gray-300"></div>
                    <div class="ml-4">
                        <div class="status-badge status-resubmitted"><i class="fas fa-sync-alt mr-2"></i>Under Review</div>
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
            <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Resubmitted Applications</h3>
            <p class="text-gray-600 mb-6">You don't have any applications in resubmitted status.</p>
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
                    
                    // Format dates
                    $created_date = date('M j, Y', strtotime($app['created_at']));
                    $updated_date = date('M j, Y', strtotime($app['updated_at']));
                ?>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <!-- Application Header -->
                    <div class="p-6 border-b border-gray-100 flex justify-between items-start">
                        <div>
                            <div class="flex items-center mb-3">
                                <span class="text-xl font-bold text-gray-900 mr-4"><?php echo $app['stall_rights_no']; ?></span>
                                <span class="status-badge status-resubmitted">
                                    <i class="fas fa-sync-alt mr-1"></i>Resubmitted for Review
                                </span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-store mr-2"></i>
                                <span><?php echo $app['stall_name']; ?> - <?php echo $app['class_name']; ?> Class</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Resubmitted on</div>
                            <div class="font-medium text-gray-900"><?php echo $updated_date; ?></div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="px-6 py-4 bg-blue-50">
                        <div class="flex justify-between items-center mb-2">
                            <div class="text-sm font-medium text-blue-800">Application Progress</div>
                            <div class="text-sm text-blue-700">Step 2 of 4</div>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: 50%"></div></div>
                        <div class="flex justify-between text-xs text-blue-600 mt-1">
                            <span>Application</span>
                            <span>Corrections</span>
                            <span>Review</span>
                            <span>Approval</span>
                        </div>
                    </div>

                    <!-- Correction Notes -->
                    <?php if (!empty($app['correction_notes'])): ?>
                        <div class="px-6 py-4 bg-yellow-50 border-y border-yellow-200">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-yellow-500 text-lg mt-1 mr-3"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-yellow-800 mb-1">Previous Correction Notes:</h4>
                                    <p class="text-yellow-700 text-sm"><?php echo htmlspecialchars($app['correction_notes']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

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
                            
                            <!-- Emergency Contact -->
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <h4 class="font-medium text-gray-800 mb-3">Emergency Contact</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <?php if (!empty($app['emergency_name'])): ?>
                                    <div>
                                        <div class="info-label">Contact Name</div>
                                        <div class="info-value"><?php echo $app['emergency_name']; ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($app['emergency_contact'])): ?>
                                    <div>
                                        <div class="info-label">Contact Number</div>
                                        <div class="info-value"><?php echo $app['emergency_contact']; ?></div>
                                    </div>
                                    <?php endif; ?>
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
                                </div>
                            </div>

                            <!-- Application Timeline -->
                            <div class="mb-6">
                                <div class="info-card-header">
                                    <div class="icon-circle bg-purple-100 text-purple-600"><i class="fas fa-history"></i></div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Application Timeline</h3>
                                        <p class="text-sm text-gray-500">Submission history</p>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Original Application:</span>
                                        <span class="font-medium text-gray-900"><?php echo $created_date; ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Corrections Submitted:</span>
                                        <span class="font-medium text-gray-900"><?php echo $updated_date; ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Financial Details -->
                            <div>
                                <div class="info-card-header">
                                    <div class="icon-circle bg-purple-100 text-purple-600"><i class="fas fa-money-bill-wave"></i></div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Financial Details</h3>
                                        <p class="text-sm text-gray-500">Payment breakdown</p>
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
                                            <span class="text-lg font-bold text-gray-900">Total Amount Due:</span>
                                            <span class="text-xl font-bold text-blue-700">₱<?php echo number_format($total_amount, 2); ?></span>
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

                    <!-- Footer Section -->
                    <div class="px-6 py-4 bg-blue-50 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium text-gray-900">What's Next?</div>
                                <div class="text-sm text-gray-600">
                                    Your corrections have been submitted. The market administrator will review your application within 3-5 business days.
                                    You will be notified via email once a decision is made.
                                </div>
                            </div>
                            <div class="text-sm text-blue-700">
                                <i class="fas fa-clock mr-1"></i>
                                Estimated review time: 3-5 business days
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