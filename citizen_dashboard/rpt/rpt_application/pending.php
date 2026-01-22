<?php
// revenue2/citizen_dashboard/rpt/rpt_application/pending.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Include database connection
require_once '../../../db/RPT/rpt_db.php';
$pdo = getDatabaseConnection();

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

// Fetch user's pending applications with all owner info and property registration info
$applications = [];
$total_applications = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            pr.*,
            po.* 
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE po.user_id = ? 
          AND pr.status = 'pending'
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = count($applications);
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Get base URL for background image
$base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$base_url .= $_SERVER['HTTP_HOST'];
$bg_image_url = $base_url . '/revenue2/Login/images/gsmbg.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pending Applications - RPT Services</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
/* Clean, Simple Styles */
body {
    background-color: #f9fafb;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Simple Card */
.simple-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

/* Status Badge */
.status-badge {
    padding: 0.375rem 0.875rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-pending {
    background-color: #fef3c7;
    color: #92400e;
    border: 1px solid #fbbf24;
}

/* Info Box */
.info-box {
    background: #f8fafc;
    border-left: 3px solid #3b82f6;
    padding: 1rem;
    margin-bottom: 0.5rem;
}

/* Progress Bar */
.simple-progress {
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
}

.simple-progress-fill {
    height: 100%;
    background: #3b82f6;
    border-radius: 3px;
}

/* Document Card */
.simple-doc-card {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 1rem;
    background: white;
    transition: border-color 0.2s;
}

.simple-doc-card:hover {
    border-color: #3b82f6;
    cursor: pointer;
}

/* Background */
body::after {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('<?php echo $bg_image_url; ?>') center/cover no-repeat;
    opacity: 0.03;
    pointer-events: none;
    z-index: -1;
}

/* Responsive */
@media (max-width: 768px) {
    .responsive-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body class="min-h-screen">
<?php include '../../navbar.php'; ?>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="modal-close">&times;</span>
    <img class="modal-content" id="modalImage">
    <div id="modalCaption" class="modal-caption"></div>
</div>

<main class="max-w-6xl mx-auto px-4 py-8">

    <!-- Page Header -->
    <div class="mb-8">
        <div class="simple-card p-6">
            <div class="flex items-center mb-4">
                <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Pending Applications</h1>
                    <p class="text-gray-600 mt-1">Applications currently under review</p>
                </div>
            </div>
            
            <?php if ($total_applications > 0): ?>
            <div class="mt-4 text-sm text-gray-600">
                <i class="fas fa-info-circle mr-1"></i>
                You have <?php echo $total_applications; ?> application<?php echo $total_applications > 1 ? 's' : ''; ?> in process
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 rounded border border-red-200 text-sm">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="simple-card p-12 text-center">
            <div class="text-gray-400 mb-4">
                <i class="fas fa-inbox text-5xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Pending Applications</h3>
            <p class="text-gray-600 mb-6">You don't have any applications pending review.</p>
            <div>
                <a href="../rpt_services.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                    <i class="fas fa-plus mr-2"></i>New Application
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Applications List -->
        <div class="space-y-6">
            <?php foreach ($applications as $app): ?>
                <?php
                    $full_name = trim($app['first_name'] . ' ' . (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . $app['last_name'] . (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));

                    $documents = [];
                    try {
                        $doc_stmt = $pdo->prepare("SELECT document_type, file_name, file_path FROM property_documents WHERE registration_id = ?");
                        $doc_stmt->execute([$app['id']]);
                        $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch(PDOException $e) {}

                    $doc_labels = [
                        'barangay_certificate' => 'Barangay Certificate',
                        'ownership_proof' => 'Proof of Ownership',
                        'valid_id' => 'Valid ID',
                        'survey_plan' => 'Survey Plan'
                    ];

                    $docs_by_type = [];
                    foreach ($documents as $doc) $docs_by_type[$doc['document_type']] = $doc;
                ?>

                <!-- Application Card -->
                <div class="simple-card">
                    <!-- Header -->
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="font-semibold text-gray-900"><?php echo $app['reference_number']; ?></span>
                                    <span class="status-badge status-pending">
                                        <i class="fas fa-clock mr-1"></i>Pending
                                    </span>
                                </div>
                                <div class="text-gray-600 text-sm">
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    <?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?>
                                </div>
                            </div>
                            <div class="text-gray-600 text-sm">
                                Submitted: <?php echo date('M j, Y', strtotime($app['created_at'])); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Progress -->
                    <div class="p-6 bg-gray-50 border-b border-gray-100">
                        <div class="mb-2 text-sm text-gray-700">
                            Application Progress: <span class="font-medium">Step 1 of 4</span>
                        </div>
                        <div class="simple-progress mb-2">
                            <div class="simple-progress-fill" style="width: 25%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-600">
                            <span>Pending</span>
                            <span>Inspection</span>
                            <span>Assessed</span>
                            <span>Approved</span>
                        </div>
                    </div>

                    <!-- Information Sections -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Applicant Info -->
                            <div>
                                <h3 class="font-medium text-gray-900 mb-4 pb-2 border-b border-gray-200">
                                    <i class="fas fa-user text-blue-600 mr-2"></i>Applicant Information
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Full Name</div>
                                            <div class="text-sm font-medium"><?php echo $full_name; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Contact</div>
                                            <div class="text-sm font-medium"><?php echo $app['phone']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Email</div>
                                            <div class="text-sm font-medium"><?php echo $app['email']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Birthdate</div>
                                            <div class="text-sm font-medium"><?php echo isset($app['birthdate']) ? date('M j, Y', strtotime($app['birthdate'])) : '-'; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Gender</div>
                                            <div class="text-sm font-medium"><?php echo ucfirst($app['sex']); ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Marital Status</div>
                                            <div class="text-sm font-medium"><?php echo ucfirst($app['marital_status']); ?></div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($app['tin_number'])): ?>
                                    <div class="info-box">
                                        <div class="text-xs text-gray-500 mb-1">Tax Identification Number</div>
                                        <div class="text-sm font-medium"><?php echo $app['tin_number']; ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="info-box">
                                        <div class="text-xs text-gray-500 mb-1">Address</div>
                                        <div class="text-sm">
                                            <?php 
                                            $address_parts = [];
                                            if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                                            if (!empty($app['street'])) $address_parts[] = $app['street'];
                                            if (!empty($app['barangay'])) $address_parts[] = 'Brgy. ' . $app['barangay'];
                                            if (!empty($app['district'])) $address_parts[] = 'Dist. ' . $app['district'];
                                            echo implode(', ', $address_parts);
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Property Info -->
                            <div>
                                <h3 class="font-medium text-gray-900 mb-4 pb-2 border-b border-gray-200">
                                    <i class="fas fa-home text-green-600 mr-2"></i>Property Information
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Location</div>
                                            <div class="text-sm font-medium"><?php echo $app['lot_location']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Barangay</div>
                                            <div class="text-sm font-medium">Brgy. <?php echo $app['barangay']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">District</div>
                                            <div class="text-sm font-medium"><?php echo $app['district']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">City</div>
                                            <div class="text-sm font-medium"><?php echo $app['city']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Province</div>
                                            <div class="text-sm font-medium"><?php echo $app['province']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Zip Code</div>
                                            <div class="text-sm font-medium"><?php echo $app['zip_code']; ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-box">
                                        <div class="text-xs text-gray-500 mb-1">Property Type</div>
                                        <div class="text-sm font-medium"><?php echo $app['has_building'] == 'yes' ? 'With Building' : 'Vacant Land'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents Section -->
                        <?php if (!empty($documents)): ?>
                        <div class="mt-8 pt-8 border-t border-gray-200">
                            <h3 class="font-medium text-gray-900 mb-4">
                                <i class="fas fa-file-alt text-gray-600 mr-2"></i>Uploaded Documents
                            </h3>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <?php foreach ($doc_labels as $type => $label): ?>
                                    <?php
                                        $doc_url = isset($docs_by_type[$type]) ? getDocumentUrl($docs_by_type[$type]['file_path']) : '';
                                    ?>
                                    <div class="simple-doc-card" onclick="openModal('<?php echo $doc_url; ?>','<?php echo $label; ?>')">
                                        <?php if ($doc_url): ?>
                                            <?php 
                                            $file_ext = strtolower(pathinfo($docs_by_type[$type]['file_name'], PATHINFO_EXTENSION));
                                            $is_image = in_array($file_ext, ['jpg','jpeg','png','gif']);
                                            ?>
                                            <div class="mb-2 text-gray-400">
                                                <?php if ($is_image): ?>
                                                    <i class="fas fa-image text-lg"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file-pdf text-lg"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm font-medium text-gray-900 mb-1"><?php echo $label; ?></div>
                                            <div class="text-xs text-gray-500 truncate" title="<?php echo htmlspecialchars($docs_by_type[$type]['file_name']); ?>">
                                                <?php echo htmlspecialchars($docs_by_type[$type]['file_name']); ?>
                                            </div>
                                            <div class="mt-2 text-xs text-blue-600">
                                                Click to view
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-2 text-gray-300">
                                                <i class="fas fa-question-circle text-lg"></i>
                                            </div>
                                            <div class="text-sm font-medium text-gray-900 mb-1"><?php echo $label; ?></div>
                                            <div class="text-xs text-gray-500">Not uploaded</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Next Steps -->
                        <div class="mt-6 p-4 bg-blue-50 border border-blue-100 rounded">
                            <div class="flex">
                                <div class="mr-3 text-blue-500">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900 mb-1">What's Next?</div>
                                    <div class="text-sm text-gray-700">
                                        Your application is in queue for review. You'll be notified via email at <span class="font-medium"><?php echo $app['email']; ?></span> once there are updates.
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