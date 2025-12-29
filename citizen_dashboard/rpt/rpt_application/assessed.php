<?php
// revenue2/citizen_dashboard/rpt/rpt_application/assessed.php
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
    // Remove ../ and ./
    $clean = str_replace(['../', './'], '', $dbPath);

    // Remove leading slash
    $clean = ltrim($clean, '/');

    // If DB path already has revenue2/, remove it
    if (strpos($clean, 'revenue2/') === 0) {
        $clean = substr($clean, strlen('revenue2/'));
    }

    // Final browser-safe URL
    return '/revenue2/' . $clean;
}

// Fetch user's assessed applications
$applications = [];
$total_applications = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            pr.id,
            pr.reference_number,
            pr.lot_location,
            pr.barangay,
            pr.district,
            pr.has_building,
            pr.status,
            DATE(pr.created_at) as application_date,
            DATE(pr.updated_at) as assessment_date,
            
            po.first_name,
            po.last_name,
            po.middle_name,
            po.suffix,
            po.email,
            po.phone,
            po.tin_number,
            po.house_number,
            po.street,
            po.barangay as owner_barangay,
            po.district as owner_district,
            po.city as owner_city,
            po.province as owner_province,
            po.zip_code as owner_zip_code
            
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE po.user_id = ? 
          AND pr.status = 'assessed'
        ORDER BY pr.updated_at DESC
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
    <title>Assessed Applications - RPT Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge { display: inline-flex; align-items: center; padding: 0.375rem 0.875rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
        .status-assessed { background-color: #d1fae5; color: #065f46; border: 1px solid #10b981; }
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
        .value-card { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 12px; padding: 1.5rem; text-align: center; }
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
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center mb-3">
            <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-4"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Assessed Applications</h1>
                <p class="text-gray-600">Applications with completed property assessment</p>
            </div>
        </div>

        <!-- Status Summary -->
        <?php if ($total_applications > 0): ?>
            <div class="mt-6 flex items-center">
                <div class="mr-4">
                    <div class="text-2xl font-bold text-gray-900"><?php echo $total_applications; ?></div>
                    <div class="text-sm text-gray-500">Assessed Application<?php echo $total_applications > 1 ? 's' : ''; ?></div>
                </div>
                <div class="h-8 w-px bg-gray-300"></div>
                <div class="ml-4">
                    <div class="status-badge status-assessed"><i class="fas fa-chart-bar mr-2"></i>Assessment Complete</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Messages -->
    <?php if (isset($error_message)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 rounded-lg border border-red-200">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-chart-bar"></i></div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Assessed Applications</h3>
            <p class="text-gray-600 mb-6">You don't have any applications with completed assessment yet.</p>
            <div class="space-x-4">
                <a href="pending.php" class="inline-flex items-center px-5 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                    <i class="fas fa-clock mr-2"></i>Check Pending Applications
                </a>
                <a href="for_inspection.php" class="inline-flex items-center px-5 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-search mr-2"></i>Check For Inspection
                </a>
            </div>
        </div>
    <?php else: ?>
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

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Status Card -->
                    <div class="value-card">
                        <div class="flex items-center justify-center mb-4">
                            <i class="fas fa-check-circle text-3xl mr-3"></i>
                            <div>
                                <div class="text-lg font-bold">Property Assessment Complete</div>
                                <div class="text-sm opacity-90">Ready for Final Approval</div>
                            </div>
                        </div>
                        <div class="text-sm opacity-90">
                            Your property has been successfully assessed and is now pending final approval from the City Assessor.
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-100 flex justify-between items-start">
                        <div>
                            <div class="flex items-center mb-3">
                                <span class="text-xl font-bold text-gray-900 mr-4"><?php echo $app['reference_number']; ?></span>
                                <span class="status-badge status-assessed">
                                    <i class="fas fa-chart-bar mr-1"></i>Assessed
                                </span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-map-marker-alt mr-2"></i>
                                <span><?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Assessed On</div>
                            <div class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($app['assessment_date'])); ?></div>
                        </div>
                    </div>

                    <!-- PROGRESS BAR - Step 3 of 4 (75%) -->
                    <div class="px-6 py-4 bg-blue-50">
                        <div class="flex justify-between items-center mb-2">
                            <div class="text-sm font-medium text-blue-800">Application Progress</div>
                            <div class="text-sm text-blue-700">Step 3 of 4</div>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 75%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-blue-600 mt-1">
                            <span>Pending</span>
                            <span>For Inspection</span>
                            <span class="font-bold">Assessed</span>
                            <span>Approved</span>
                        </div>
                    </div>

                    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div>
                            <div class="info-card-header">
                                <div class="icon-circle bg-blue-100 text-blue-600"><i class="fas fa-user"></i></div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Applicant Information</h3>
                                    <p class="text-sm text-gray-500">Property owner details</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?php echo $full_name; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Contact Number</div>
                                    <div class="info-value"><?php echo $app['phone']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?php echo $app['email']; ?></div>
                                </div>
                                <?php if (!empty($app['tin_number'])): ?>
                                <div>
                                    <div class="info-label">TIN Number</div>
                                    <div class="info-value"><?php echo $app['tin_number']; ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4">
                                <div class="info-label">Address</div>
                                <div class="info-value text-sm">
                                    <?php 
                                    $address_parts = [];
                                    if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                                    if (!empty($app['street'])) $address_parts[] = $app['street'];
                                    if (!empty($app['owner_barangay'])) $address_parts[] = 'Brgy. ' . $app['owner_barangay'];
                                    if (!empty($app['owner_district'])) $address_parts[] = 'Dist. ' . $app['owner_district'];
                                    if (!empty($app['owner_city'])) $address_parts[] = $app['owner_city'];
                                    if (!empty($app['owner_province'])) $address_parts[] = $app['owner_province'];
                                    if (!empty($app['owner_zip_code'])) $address_parts[] = $app['owner_zip_code'];
                                    echo implode(', ', $address_parts);
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="info-card-header">
                                <div class="icon-circle bg-green-100 text-green-600"><i class="fas fa-home"></i></div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Property Details</h3>
                                    <p class="text-sm text-gray-500">Location and features</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="info-label">Lot Location</div>
                                    <div class="info-value"><?php echo $app['lot_location']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Barangay</div>
                                    <div class="info-value">Brgy. <?php echo $app['barangay']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">District</div>
                                    <div class="info-value"><?php echo $app['district']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Property Type</div>
                                    <div class="info-value"><?php echo $app['has_building'] == 'yes' ? 'With Building' : 'Vacant Land'; ?></div>
                                </div>
                            </div>
                            
                            <!-- Status Information -->
                            <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-info-circle text-green-600 mr-3 text-xl"></i>
                                    <div>
                                        <div class="font-medium text-green-900">Assessment Status</div>
                                        <div class="text-sm text-green-700 mt-1">
                                            Your property has been successfully assessed. It is now in queue for final approval.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($documents)): ?>
                        <div class="mt-8 pt-8 border-t border-gray-200 px-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-file-alt text-purple-600 mr-2"></i>Uploaded Documents
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <?php foreach ($doc_labels as $type => $label): ?>
                                    <?php
                                        $doc_url = isset($docs_by_type[$type]) ? getDocumentUrl($docs_by_type[$type]['file_path']) : '';
                                    ?>
                                    <div class="document-card" onclick="openModal('<?php echo $doc_url; ?>','<?php echo $label; ?>')">
                                        <?php if ($doc_url): ?>
                                            <?php 
                                            $file_ext = strtolower(pathinfo($docs_by_type[$type]['file_name'], PATHINFO_EXTENSION));
                                            $is_image = in_array($file_ext, ['jpg','jpeg','png','gif']);
                                            ?>
                                            <div class="mb-3">
                                                <?php if ($is_image): ?>
                                                    <i class="fas fa-image text-blue-500 text-3xl"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file-pdf text-red-500 text-3xl"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo $label; ?></div>
                                            <div class="text-xs text-gray-500 mt-1 truncate" title="<?php echo htmlspecialchars($docs_by_type[$type]['file_name']); ?>">
                                                <?php echo htmlspecialchars($docs_by_type[$type]['file_name']); ?>
                                            </div>
                                            <div class="mt-2">
                                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">Click to view</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-3">
                                                <i class="fas fa-question-circle text-gray-300 text-3xl"></i>
                                            </div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo $label; ?></div>
                                            <div class="text-xs text-gray-500 mt-1">Not uploaded</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="px-6 py-4 bg-green-50 border-t border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                            <div class="mb-4 md:mb-0">
                                <div class="font-medium text-gray-900">Final Approval Pending</div>
                                <div class="text-sm text-gray-600">
                                    Your property assessment is complete. Awaiting final approval from the City Assessor.
                                </div>
                            </div>
                            <div class="text-sm text-green-600 font-medium flex items-center">
                                <i class="fas fa-check-circle mr-2"></i> Assessment Completed
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