<?php
// revenue2/citizen_dashboard/rpt/rpt_application/resubmitted.php
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

// Get reference number and ID from URL (optional)
$reference_number = $_GET['ref'] ?? '';
$registration_id = $_GET['id'] ?? 0;
$specific_application = !empty($reference_number) && !empty($registration_id);

// Also fetch ALL resubmitted applications for this user
try {
    $resubmitted_stmt = $pdo->prepare("
        SELECT 
            pr.id,
            pr.reference_number,
            pr.lot_location,
            pr.barangay,
            pr.status,
            DATE(pr.updated_at) as resubmitted_date
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE po.user_id = ? 
          AND pr.status = 'resubmitted'
        ORDER BY pr.updated_at DESC
    ");
    $resubmitted_stmt->execute([$user_id]);
    $all_resubmitted = $resubmitted_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_resubmitted = count($all_resubmitted);
} catch(PDOException $e) {
    $all_resubmitted = [];
    $total_resubmitted = 0;
    $error_message = "Database error: " . $e->getMessage();
}

// If a specific application is requested, fetch its details
$application = [];
$documents = [];
$error_message = '';

if ($specific_application) {
    try {
        // Get specific application details
        $stmt = $pdo->prepare("
            SELECT 
                pr.id,
                pr.reference_number,
                pr.lot_location,
                pr.barangay,
                pr.district,
                pr.has_building,
                pr.status,
                DATE(pr.updated_at) as resubmitted_date,
                DATE(pr.created_at) as created_date,
                
                po.first_name,
                po.last_name,
                po.middle_name,
                po.suffix,
                po.email,
                po.phone,
                po.house_number,
                po.street,
                po.barangay as owner_barangay,
                po.district as owner_district
                
            FROM property_registrations pr
            JOIN property_owners po ON pr.owner_id = po.id
            WHERE pr.reference_number = ? 
              AND pr.id = ? 
              AND po.user_id = ?
              AND pr.status = 'resubmitted'
        ");
        $stmt->execute([$reference_number, $registration_id, $user_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($application) {
            // Get documents for this specific application
            $doc_stmt = $pdo->prepare("
                SELECT document_type, file_name, file_path 
                FROM property_documents 
                WHERE registration_id = ?
                ORDER BY created_at DESC
            ");
            $doc_stmt->execute([$registration_id]);
            $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error_message = "Specific application not found. It may have been updated or you don't have permission to view it.";
        }
    } catch(PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Function to get document URL
function getDocumentUrl(string $dbPath): string
{
    if (empty($dbPath)) return '#';
    
    $clean = str_replace(['../', './'], '', $dbPath);
    $clean = ltrim($clean, '/');
    if (strpos($clean, 'revenue2/') === 0) {
        $clean = substr($clean, strlen('revenue2/'));
    }
    return '/revenue2/' . $clean;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resubmitted Applications - RPT Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge { display: inline-flex; align-items: center; padding: 0.375rem 0.875rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
        .status-resubmitted { background-color: #dbeafe; color: #1e40af; border: 1px solid #60a5fa; }
        .document-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; transition: all 0.2s; }
        .document-card:hover { border-color: #3b82f6; background: #f8fafc; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
<?php include '../../navbar.php'; ?>

<main class="max-w-6xl mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center mb-3">
            <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Resubmitted Applications</h1>
                <p class="text-gray-600">Applications you have corrected and resubmitted</p>
            </div>
        </div>
        
        <?php if ($total_resubmitted > 0): ?>
            <div class="mt-4 flex items-center">
                <div class="mr-4">
                    <div class="text-2xl font-bold text-gray-900"><?php echo $total_resubmitted; ?></div>
                    <div class="text-sm text-gray-500">Resubmitted Application<?php echo $total_resubmitted > 1 ? 's' : ''; ?></div>
                </div>
                <div class="h-8 w-px bg-gray-300"></div>
                <div class="ml-4">
                    <div class="status-badge status-resubmitted">
                        <i class="fas fa-sync-alt mr-2"></i>Under Review
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Show specific application if requested -->
    <?php if ($specific_application && !empty($application)): ?>
        <?php
            $full_name = trim($application['first_name'] . ' ' . 
                             (!empty($application['middle_name']) ? $application['middle_name'] . ' ' : '') . 
                             $application['last_name'] . 
                             (!empty($application['suffix']) ? ' ' . $application['suffix'] : ''));
            
            $doc_labels = [
                'barangay_certificate' => 'Barangay Certificate',
                'ownership_proof' => 'Proof of Ownership',
                'valid_id' => 'Valid ID',
                'survey_plan' => 'Survey Plan'
            ];
            
            $docs_by_type = [];
            foreach ($documents as $doc) $docs_by_type[$doc['document_type']] = $doc;
        ?>
        
        <!-- Success Card -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <div>
                    <div class="flex items-center mb-3">
                        <span class="text-xl font-bold text-gray-900 mr-4"><?php echo $application['reference_number']; ?></span>
                        <span class="status-badge status-resubmitted">
                            <i class="fas fa-sync-alt mr-1"></i>Resubmitted
                        </span>
                    </div>
                    <div class="text-gray-600">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        <?php echo $application['lot_location']; ?>, Brgy. <?php echo $application['barangay']; ?>
                    </div>
                </div>
                <div class="mt-4 md:mt-0 text-right">
                    <div class="text-sm text-gray-500">Resubmitted</div>
                    <div class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($application['resubmitted_date'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Application Details -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-8">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Application Details</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Applicant Info -->
                <div>
                    <h3 class="font-semibold text-gray-700 mb-3">Applicant Information</h3>
                    <div class="text-sm space-y-2">
                        <div><span class="text-gray-600">Name:</span> <?php echo $full_name; ?></div>
                        <div><span class="text-gray-600">Email:</span> <?php echo $application['email']; ?></div>
                        <div><span class="text-gray-600">Phone:</span> <?php echo $application['phone']; ?></div>
                        <div><span class="text-gray-600">Address:</span> 
                            <?php 
                            $address_parts = [];
                            if (!empty($application['house_number'])) $address_parts[] = $application['house_number'];
                            if (!empty($application['street'])) $address_parts[] = $application['street'];
                            if (!empty($application['owner_barangay'])) $address_parts[] = 'Brgy. ' . $application['owner_barangay'];
                            echo implode(', ', $address_parts);
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Property Info -->
                <div>
                    <h3 class="font-semibold text-gray-700 mb-3">Property Information</h3>
                    <div class="text-sm space-y-2">
                        <div><span class="text-gray-600">Location:</span> <?php echo $application['lot_location']; ?></div>
                        <div><span class="text-gray-600">Barangay:</span> <?php echo $application['barangay']; ?></div>
                        <div><span class="text-gray-600">District:</span> <?php echo $application['district']; ?></div>
                        <div><span class="text-gray-600">Building:</span> <?php echo $application['has_building'] == 'yes' ? 'Yes' : 'No'; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Submitted Documents -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-8">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Submitted Documents</h2>
            
            <?php if (!empty($documents)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($doc_labels as $type => $label): ?>
                        <?php if (isset($docs_by_type[$type])): ?>
                            <?php $doc = $docs_by_type[$type]; ?>
                            <?php $doc_url = getDocumentUrl($doc['file_path']); ?>
                            <div class="document-card">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-900 mb-1"><?php echo $label; ?></h4>
                                        <div class="text-sm text-gray-600 truncate">
                                            <?php echo htmlspecialchars($doc['file_name']); ?>
                                        </div>
                                    </div>
                                    <?php if ($doc_url != '#'): ?>
                                        <a href="<?php echo $doc_url; ?>" target="_blank" 
                                           class="ml-3 px-3 py-1.5 bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200 flex items-center whitespace-nowrap">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="document-card opacity-60">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-900 mb-1"><?php echo $label; ?></h4>
                                        <div class="text-sm text-gray-500">Not uploaded</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No documents uploaded for this application.</p>
            <?php endif; ?>
        </div>
        
        <!-- Back to All Resubmitted -->
        <div class="mt-6">
            <a href="resubmitted.php" class="text-blue-600 hover:text-blue-800 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to All Resubmitted Applications
            </a>
        </div>
        
    <?php elseif ($specific_application && !empty($error_message)): ?>
        <!-- Specific application not found -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="text-4xl text-red-500 mb-4 text-center">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-900 mb-2 text-center">Application Not Found</h2>
            <p class="text-gray-600 mb-6 text-center"><?php echo $error_message; ?></p>
            <div class="text-center">
                <a href="resubmitted.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    <i class="fas fa-list mr-2"></i> View All Resubmitted Applications
                </a>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Show ALL resubmitted applications -->
    <?php if ($total_resubmitted > 0): ?>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">
                <?php echo $specific_application ? 'Your Other Resubmitted Applications' : 'All Resubmitted Applications'; ?>
            </h2>
            
            <div class="space-y-4">
                <?php foreach ($all_resubmitted as $app): ?>
                    <?php if (!$specific_application || $app['id'] != $application['id']): ?>
                        <a href="resubmitted.php?ref=<?php echo $app['reference_number']; ?>&id=<?php echo $app['id']; ?>"
                           class="block p-5 border border-gray-200 rounded-lg hover:bg-blue-50 hover:border-blue-200 transition">
                            <div class="flex justify-between items-center">
                                <div>
                                    <div class="font-bold text-gray-900"><?php echo $app['reference_number']; ?></div>
                                    <div class="text-sm text-gray-600 mt-1"><?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?></div>
                                </div>
                                <div class="text-right">
                                    <span class="status-badge status-resubmitted">
                                        <i class="fas fa-sync-alt mr-1"></i>Resubmitted
                                    </span>
                                    <div class="text-xs text-gray-500 mt-2">
                                        <?php echo date('M j, Y', strtotime($app['resubmitted_date'])); ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        
    <?php elseif ($total_resubmitted === 0): ?>
        <!-- No resubmitted applications -->
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <div class="text-gray-400 mb-6">
                <i class="fas fa-inbox text-6xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-700 mb-3">No Resubmitted Applications</h3>
            <p class="text-gray-600 mb-6">You haven't resubmitted any applications yet.</p>
            <a href="need_correction.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-edit mr-2"></i> Check Applications Needing Correction
            </a>
        </div>
    <?php endif; ?>
    
    <!-- Next Steps Info -->
    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="font-bold text-blue-800 mb-3 flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            What happens with resubmitted applications?
        </h3>
        <div class="text-blue-700 space-y-2">
            <div class="flex items-start">
                <i class="fas fa-clock text-blue-500 mt-1 mr-2"></i>
                <span>Resubmitted applications will be reviewed by the assessor within 3-5 working days.</span>
            </div>
            <div class="flex items-start">
                <i class="fas fa-user-check text-blue-500 mt-1 mr-2"></i>
                <span>The assessor will check if your corrections are complete and accurate.</span>
            </div>
            <div class="flex items-start">
                <i class="fas fa-bell text-blue-500 mt-1 mr-2"></i>
                <span>You'll be notified via email about the status update (approved or needs more corrections).</span>
            </div>
        </div>
    </div>
</main>
</body>
</html>