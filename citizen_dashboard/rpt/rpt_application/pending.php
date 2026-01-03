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
/* Tailwind + custom classes */
.status-badge { display: inline-flex; align-items: center; padding: 0.375rem 0.875rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
.status-pending { background-color: #fef3c7; color: #92400e; border: 1px solid #fbbf24; }
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
                <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-4 text-lg"><i class="fas fa-arrow-left"></i></a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Pending Applications</h1>
                    <p class="text-gray-600 mt-1">Your submitted applications are waiting for review</p>
                </div>
            </div>

            <?php if ($total_applications > 0): ?>
                <div class="mt-4 flex items-center">
                    <div class="mr-4">
                        <div class="text-2xl font-bold text-gray-900"><?php echo $total_applications; ?></div>
                        <div class="text-sm text-gray-500">Application<?php echo $total_applications > 1 ? 's' : ''; ?></div>
                    </div>
                    <div class="h-8 w-px bg-gray-300"></div>
                    <div class="ml-4">
                        <div class="status-badge status-pending"><i class="fas fa-clock mr-2"></i>Pending Review</div>
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
            <div class="empty-icon"><i class="fas fa-inbox"></i></div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Pending Applications</h3>
            <p class="text-gray-600 mb-6">You don't have any applications pending review.</p>
            <a href="need_correction.php" class="inline-flex items-center px-5 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-exclamation-triangle mr-2"></i>Check for Corrections
            </a>
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

                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-start">
                        <div>
                            <div class="flex items-center mb-3">
                                <span class="text-xl font-bold text-gray-900 mr-4"><?php echo $app['reference_number']; ?></span>
                                <span class="status-badge status-pending"><i class="fas fa-clock mr-1"></i>Pending</span>
                            </div>
                            <div class="flex items-center text-gray-600"><i class="fas fa-map-marker-alt mr-2"></i>
                                <span><?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Submitted</div>
                            <div class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($app['created_at'])); ?></div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-blue-50">
                        <div class="flex justify-between items-center mb-2">
                            <div class="text-sm font-medium text-blue-800">Application Progress</div>
                            <div class="text-sm text-blue-700">Step 1 of 4</div>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: 25%"></div></div>
                        <div class="flex justify-between text-xs text-blue-600 mt-1">
                            <span>Pending</span>
                            <span>For Inspection</span>
                            <span>Assessed</span>
                            <span>Approved</span>
                        </div>
                    </div>

                    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div>
                            <div class="info-card-header">
                                <div class="icon-circle bg-blue-100 text-blue-600"><i class="fas fa-user"></i></div>
                                <div><h3 class="font-semibold text-gray-900">Applicant</h3><p class="text-sm text-gray-500">Your registered information</p></div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div><div class="info-label">Full Name</div><div class="info-value"><?php echo $full_name; ?></div></div>
                                <div><div class="info-label">Birthdate</div><div class="info-value"><?php echo isset($app['birthdate']) ? date('M j, Y', strtotime($app['birthdate'])) : '-'; ?></div></div>
                                <div><div class="info-label">Contact</div><div class="info-value"><?php echo $app['phone']; ?></div></div>
                                <div><div class="info-label">Email</div><div class="info-value"><?php echo $app['email']; ?></div></div>
                                <div><div class="info-label">Sex</div><div class="info-value"><?php echo ucfirst($app['sex']); ?></div></div>
                                <div><div class="info-label">Marital Status</div><div class="info-value"><?php echo ucfirst($app['marital_status']); ?></div></div>
                                <?php if (!empty($app['tin_number'])): ?>
                                <div><div class="info-label">TIN</div><div class="info-value"><?php echo $app['tin_number']; ?></div></div>
                                <?php endif; ?>
                                <?php if (!empty($app['city'])): ?>
                                <div><div class="info-label">City</div><div class="info-value"><?php echo $app['city']; ?></div></div>
                                <?php endif; ?>
                                <?php if (!empty($app['province'])): ?>
                                <div><div class="info-label">Province</div><div class="info-value"><?php echo $app['province']; ?></div></div>
                                <?php endif; ?>
                                <?php if (!empty($app['zip_code'])): ?>
                                <div><div class="info-label">Zip Code</div><div class="info-value"><?php echo $app['zip_code']; ?></div></div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4">
                                <div class="info-label">Address</div>
                                <div class="info-value text-sm">
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

                        <div>
                            <div class="info-card-header">
                                <div class="icon-circle bg-green-100 text-green-600"><i class="fas fa-home"></i></div>
                                <div><h3 class="font-semibold text-gray-900">Property</h3><p class="text-sm text-gray-500">Property details</p></div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div><div class="info-label">Location</div><div class="info-value"><?php echo $app['lot_location']; ?></div></div>
                                <div><div class="info-label">Barangay</div><div class="info-value">Brgy. <?php echo $app['barangay']; ?></div></div>
                                <div><div class="info-label">District</div><div class="info-value"><?php echo $app['district']; ?></div></div>
                                <div><div class="info-label">City</div><div class="info-value"><?php echo $app['city']; ?></div></div>
                                <div><div class="info-label">Province</div><div class="info-value"><?php echo $app['province']; ?></div></div>
                                <div><div class="info-label">Zip Code</div><div class="info-value"><?php echo $app['zip_code']; ?></div></div>
                                <div><div class="info-label">Building</div><div class="info-value"><?php echo $app['has_building'] == 'yes' ? 'Has Building' : 'Vacant Land'; ?></div></div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($documents)): ?>
                        <div class="mt-8 pt-8 border-t border-gray-200">
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
                                            <div class="mt-2"><span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">Click to view</span></div>
                                        <?php else: ?>
                                            <div class="mb-3"><i class="fas fa-question-circle text-gray-300 text-3xl"></i></div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo $label; ?></div>
                                            <div class="text-xs text-gray-500 mt-1">Not uploaded</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex items-center">
                            <div>
                                <div class="font-medium text-gray-900">What's Next?</div>
                                <div class="text-sm text-gray-600">Waiting for assessor review (3-5 business days)</div>
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
