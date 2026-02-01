<?php
// revenue2/citizen_dashboard/rpt/rpt_application/for_inspection.php
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

// Fetch user's applications for inspection
$applications = [];
$total_applications = 0;

try {
    // Get registrations
    $stmt = $pdo->prepare("
        SELECT 
            pr.*,
            po.* 
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE po.user_id = ? 
          AND pr.status = 'for_inspection'
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = count($applications);
    
    // Get latest inspection for each registration
    foreach ($applications as &$app) {
        $inspection_stmt = $pdo->prepare("
            SELECT 
                scheduled_date,
                assessor_name,
                status as inspection_status,
                DATE(created_at) as inspection_date
            FROM property_inspections 
            WHERE registration_id = ? 
            ORDER BY scheduled_date DESC 
            LIMIT 1
        ");
        $inspection_stmt->execute([$app['id']]);
        $inspection = $inspection_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inspection) {
            $app = array_merge($app, $inspection);
        } else {
            $app['scheduled_date'] = null;
            $app['assessor_name'] = null;
            $app['inspection_status'] = null;
            $app['inspection_date'] = null;
        }
        
        // Get documents
        $doc_stmt = $pdo->prepare("SELECT document_type, file_name, file_path FROM property_documents WHERE registration_id = ?");
        $doc_stmt->execute([$app['id']]);
        $app['documents'] = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($app);
    
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
<title>For Inspection Applications - RPT Services | GoServePH</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --primary: #4a90e2;
    --secondary: #9aa5b1;
    --accent: #4caf50;
    --warning: #f59e0b;
    --danger: #ef4444;
}

body {
    background: linear-gradient(135deg, rgba(240, 240, 240, 0.4) 0%, rgba(230, 230, 230, 0.4) 50%, rgba(220, 220, 220, 0.3) 100%);
    position: relative;
    overflow-x: hidden;
    min-height: 100vh;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    display: flex;
    flex-direction: column;
}

/* Background image with blur */
body::after {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('<?php echo $bg_image_url; ?>') center/cover no-repeat;
    opacity: 0.08;
    pointer-events: none;
    z-index: -2;
    filter: blur(1px);
}

/* Animated background particles */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 80%, rgba(76, 175, 80, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(74, 144, 226, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 40% 40%, rgba(253, 168, 17, 0.05) 0%, transparent 50%);
    animation: backgroundFloat 20s ease-in-out infinite;
    z-index: -1;
}

@keyframes backgroundFloat {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    33% { transform: translateY(-20px) rotate(1deg); }
    66% { transform: translateY(10px) rotate(-1deg); }
}

/* Glass effect header */
.glass-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border-radius: 16px;
}

/* Form card styling */
.form-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
}

/* Status badge */
.status-badge {
    padding: 0.375rem 0.875rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.status-inspection {
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    color: white;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.status-pending {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.status-approved {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.status-rejected {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

/* Progress bar */
.progress-container {
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #6366f1);
    border-radius: 3px;
    transition: width 0.3s ease;
}

/* Document card */
.document-card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    background: white;
    transition: all 0.2s ease;
    cursor: pointer;
}

.document-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: #4a90e2;
}

/* Section header */
.section-header {
    border-left: 4px solid var(--primary);
    padding-left: 1rem;
    margin-bottom: 1.5rem;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    padding: 20px;
}

.modal-content {
    margin: auto;
    display: block;
    max-width: 90%;
    max-height: 85vh;
    object-fit: contain;
    border-radius: 8px;
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    z-index: 1001;
}

.modal-close:hover {
    color: #ddd;
}

.modal-caption {
    margin: auto;
    display: block;
    width: 80%;
    text-align: center;
    color: white;
    padding: 10px 0;
    font-size: 0.9rem;
}

/* Back button */
.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 8px;
    transition: all 0.2s;
    background: rgba(74, 144, 226, 0.1);
}

.back-button:hover {
    background: rgba(74, 144, 226, 0.2);
    transform: translateX(-2px);
}

/* Timeline */
.timeline-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    position: relative;
}

.timeline-connector {
    position: absolute;
    top: 20px;
    left: 50%;
    right: -50%;
    height: 2px;
    background: #e5e7eb;
    z-index: 0;
}

.timeline-step.active .timeline-connector {
    background: #3b82f6;
}

/* Inspection highlight box */
.inspection-highlight {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border: 2px solid #3b82f6;
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1rem 0;
}

/* Info box for property type highlighting */
.info-box-highlight {
    background: #f0f9ff;
    border: 2px solid #0ea5e9;
    padding: 0.875rem;
    border-radius: 8px;
}

@media (max-width: 768px) {
    .modal-content {
        max-width: 95%;
        max-height: 80vh;
    }
    
    .modal-close {
        right: 20px;
        font-size: 30px;
    }
}
</style>
</head>
<body class="bg-gray-50">
<?php include '../../navbar.php'; ?>

<!-- Main Content Wrapper -->
<div class="flex-1 py-8">
    <div class="max-w-6xl mx-auto px-4">
        <!-- Page Header -->
        <div class="glass-header p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                <a href="../rpt_services.php" class="back-button self-start">
                    <i class="fas fa-arrow-left"></i> Back to RPT Services
                </a>
                
                <div class="text-center">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">For Inspection Applications</h1>
                    <p class="text-gray-600">Applications scheduled for property inspection</p>
                </div>
                
                <div class="text-right">
                    <?php if ($total_applications > 0): ?>
                    <div class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-700 rounded-lg text-sm font-medium">
                        <i class="fas fa-search mr-2"></i>
                        <?php echo $total_applications; ?> For Inspection
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($total_applications > 0): ?>
            <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-100">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-3"></i>
                    <div>
                        <p class="text-sm text-blue-700">
                            Your applications are scheduled for property inspection. Please ensure someone is available at the property during the scheduled inspection date. You'll receive email notifications at 
                            <span class="font-semibold"><?php echo $_SESSION['user_email'] ?? 'your registered email'; ?></span> 
                            for any updates.
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Error Message -->
        <?php if (isset($error_message)): ?>
            <div class="form-card mb-6 p-4 bg-red-50 border border-red-200">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle text-red-600 mr-3 mt-0.5"></i>
                    <div class="text-sm text-red-700"><?php echo $error_message; ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($total_applications === 0): ?>
            <!-- Empty State -->
            <div class="form-card p-12 text-center">
                <div class="text-gray-300 mb-6">
                    <i class="fas fa-search text-6xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Applications For Inspection</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto">You don't have any applications currently scheduled for inspection. Check your pending applications for status updates.</p>
                <div class="space-x-4">
                    <a href="pending.php" 
                       class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors">
                        <i class="fas fa-clock mr-2"></i> View Pending
                    </a>
                    <a href="assessed.php" 
                       class="inline-flex items-center px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 font-medium transition-colors">
                        <i class="fas fa-chart-bar mr-2"></i> View Assessed
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Applications List -->
            <div class="space-y-8">
                <?php foreach ($applications as $app): ?>
                    <?php
                        $full_name = trim($app['first_name'] . ' ' . (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . $app['last_name'] . (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));

                        $doc_labels = [
                            'barangay_certificate' => 'Barangay Certificate',
                            'ownership_proof' => 'Proof of Ownership',
                            'valid_id' => 'Valid ID',
                            'survey_plan' => 'Survey Plan'
                        ];

                        $docs_by_type = [];
                        foreach ($app['documents'] as $doc) $docs_by_type[$doc['document_type']] = $doc;
                    ?>

                    <!-- Application Card -->
                    <div class="form-card overflow-hidden">
                        <!-- Card Header -->
                        <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="font-bold text-gray-900 text-lg"><?php echo $app['reference_number']; ?></span>
                                        <span class="status-badge status-inspection">
                                            <i class="fas fa-search"></i> For Inspection
                                        </span>
                                    </div>
                                    <div class="text-gray-600 text-sm">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        <?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?>
                                    </div>
                                </div>
                                <div class="text-gray-600 text-sm">
                                    <i class="far fa-calendar mr-1"></i>
                                    Submitted: <?php echo date('F j, Y', strtotime($app['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Inspection Schedule Highlight -->
                        <?php if (!empty($app['scheduled_date'])): ?>
                        <div class="inspection-highlight mx-6 mt-6">
                            <div class="flex items-center justify-between mb-4">
                                <div class="font-semibold text-blue-800 flex items-center text-lg">
                                    <i class="fas fa-calendar-alt mr-3"></i>Inspection Scheduled
                                </div>
                                <div class="text-blue-700 font-bold">
                                    <?php 
                                    $today = new DateTime();
                                    $inspection_date = new DateTime($app['scheduled_date']);
                                    $interval = $today->diff($inspection_date);
                                    $days = $interval->days;
                                    
                                    if ($today > $inspection_date) {
                                        echo '<span class="text-amber-600">' . $days . " day" . ($days != 1 ? 's' : '') . " ago</span>";
                                    } elseif ($today < $inspection_date) {
                                        echo '<span class="text-green-600">in ' . $days . " day" . ($days != 1 ? 's' : '') . "</span>";
                                    } else {
                                        echo '<span class="text-green-600 font-bold">TODAY</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="text-xs text-gray-600 mb-2 font-medium">SCHEDULED DATE</div>
                                    <div class="text-xl font-bold text-gray-900">
                                        <i class="fas fa-calendar-day mr-2 text-blue-500"></i>
                                        <?php echo date('F j, Y', strtotime($app['scheduled_date'])); ?>
                                    </div>
                                    <div class="text-sm text-gray-500 mt-1">
                                        <?php echo date('l', strtotime($app['scheduled_date'])); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-600 mb-2 font-medium">ASSIGNED ASSESSOR</div>
                                    <div class="text-lg font-semibold text-gray-900">
                                        <i class="fas fa-user-tie mr-2 text-green-500"></i>
                                        <?php echo htmlspecialchars($app['assessor_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500 mt-1">
                                        Will contact you for inspection details
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Progress Section -->
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                            <div class="flex items-center justify-between mb-3">
                                <div class="text-sm font-medium text-gray-900">Application Progress</div>
                                <div class="text-sm font-semibold text-blue-600">Step 2 of 4</div>
                            </div>
                            <div class="progress-container mb-2">
                                <div class="progress-fill" style="width: 50%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-600">
                                <span>Pending</span>
                                <span class="font-medium text-blue-600">Inspection</span>
                                <span>Assessment</span>
                                <span>Approval</span>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-6">
                            <!-- Two Column Layout -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                                <!-- Applicant Information -->
                                <div>
                                    <div class="section-header">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-user-circle mr-3 text-blue-500"></i>
                                            Applicant Information
                                        </h3>
                                    </div>
                                    
                                    <div class="space-y-5">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Full Name</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $full_name; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Contact Number</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['phone']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Email Address</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['email']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Birthdate</div>
                                                <div class="text-sm font-semibold text-gray-900">
                                                    <?php echo isset($app['birthdate']) ? date('F j, Y', strtotime($app['birthdate'])) : 'Not provided'; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Gender</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo ucfirst($app['sex'] ?? 'Not provided'); ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Marital Status</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo ucfirst($app['marital_status'] ?? 'Not provided'); ?></div>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($app['tin_number'])): ?>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1 font-medium">Tax Identification Number</div>
                                            <div class="text-sm font-semibold text-gray-900"><?php echo $app['tin_number']; ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1 font-medium">Address</div>
                                            <div class="text-sm text-gray-700">
                                                <?php 
                                                $address_parts = [];
                                                if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                                                if (!empty($app['street'])) $address_parts[] = $app['street'];
                                                if (!empty($app['barangay'])) $address_parts[] = 'Brgy. ' . $app['barangay'];
                                                if (!empty($app['district'])) $address_parts[] = 'Dist. ' . $app['district'];
                                                if (!empty($app['city'])) $address_parts[] = $app['city'];
                                                if (!empty($app['province'])) $address_parts[] = $app['province'];
                                                if (!empty($app['zip_code'])) $address_parts[] = $app['zip_code'];
                                                echo implode(', ', $address_parts);
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Property Information -->
                                <div>
                                    <div class="section-header">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-home mr-3 text-green-500"></i>
                                            Property Information
                                        </h3>
                                    </div>
                                    
                                    <div class="space-y-5">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Location</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['lot_location']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Barangay</div>
                                                <div class="text-sm font-semibold text-gray-900">Brgy. <?php echo $app['barangay']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">District</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['district']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">City</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['city']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">Province</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['province']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1 font-medium">ZIP Code</div>
                                                <div class="text-sm font-semibold text-gray-900"><?php echo $app['zip_code']; ?></div>
                                            </div>
                                        </div>
                                        
                                        <!-- Highlighted Property Type Box -->
                                        <div class="info-box-highlight">
                                            <div class="text-xs text-gray-500 mb-1 font-medium">Property Type</div>
                                            <div class="text-sm font-semibold text-gray-900">
                                                <?php 
                                                if ($app['has_building'] == 'yes') {
                                                    echo '<span class="text-green-600"><i class="fas fa-building mr-1"></i> With Building</span>';
                                                } else {
                                                    echo '<span class="text-blue-600"><i class="fas fa-mountain mr-1"></i> Vacant Land</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Documents Section -->
                            <?php if (!empty($app['documents'])): ?>
                            <div class="mt-8 pt-8 border-t border-gray-200">
                                <div class="section-header">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i class="fas fa-file-alt mr-3 text-gray-600"></i>
                                        Uploaded Documents
                                    </h3>
                                </div>
                                
                                <p class="text-gray-600 text-sm mb-6">Click on any document to view</p>
                                
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
                                                <div class="mb-3 text-center">
                                                    <?php if ($is_image): ?>
                                                        <i class="fas fa-image text-3xl text-blue-500"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-file-pdf text-3xl text-red-500"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-sm font-semibold text-gray-900 mb-1 text-center"><?php echo $label; ?></div>
                                                <div class="text-xs text-gray-500 truncate text-center mb-2" title="<?php echo htmlspecialchars($docs_by_type[$type]['file_name']); ?>">
                                                    <?php 
                                                    $short_name = strlen($docs_by_type[$type]['file_name']) > 20 
                                                        ? substr($docs_by_type[$type]['file_name'], 0, 17) . '...' 
                                                        : $docs_by_type[$type]['file_name'];
                                                    echo htmlspecialchars($short_name);
                                                    ?>
                                                </div>
                                                <div class="text-xs text-blue-600 text-center font-medium">
                                                    <i class="fas fa-eye mr-1"></i>Click to view
                                                </div>
                                            <?php else: ?>
                                                <div class="mb-3 text-center text-gray-300">
                                                    <i class="fas fa-question-circle text-3xl"></i>
                                                </div>
                                                <div class="text-sm font-semibold text-gray-900 mb-1 text-center"><?php echo $label; ?></div>
                                                <div class="text-xs text-gray-400 text-center">Not uploaded</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Next Steps & Notes -->
                            <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="p-4 bg-blue-50 border border-blue-100 rounded-lg">
                                    <div class="flex items-start">
                                        <div class="mr-3 text-blue-500">
                                            <i class="fas fa-info-circle text-lg"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900 mb-1">What's Next?</div>
                                            <div class="text-sm text-gray-700">
                                                <?php if (!empty($app['scheduled_date'])): ?>
                                                    Property inspection is scheduled. Please ensure someone is available at the property on <span class="font-medium"><?php echo date('F j, Y', strtotime($app['scheduled_date'])); ?></span>.
                                                    The assessor will verify documents and inspect the property.
                                                <?php else: ?>
                                                    Waiting for inspection schedule assignment. You'll be notified once a date is set.
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="p-4 bg-green-50 border border-green-100 rounded-lg">
                                    <div class="flex items-start">
                                        <div class="mr-3 text-green-500">
                                            <i class="fas fa-clock text-lg"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900 mb-1">Inspection Checklist</div>
                                            <div class="text-sm text-gray-700">
                                                • Ensure property is accessible<br>
                                                • Have original documents ready<br>
                                                • Property owner or authorized representative should be present<br>
                                                • Inspection takes 1-2 hours
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 mt-16">
    <div class="container mx-auto px-6 py-12 max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
            <!-- Brand -->
            <div class="col-span-1">
                <div class="flex items-center mb-4 text-2xl font-bold">
                    <span style="color: #4a90e2;">Go</span><span style="color: #4caf50;">Serve</span><span style="color: #4a90e2;">PH</span>
                </div>
                <p class="text-gray-600 leading-relaxed">
                    The official digital gateway of your Local Government Unit, providing efficient and transparent government services.
                </p>
            </div>
            
            <!-- Portal Links -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Portal</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><a href="../../citizen_dashboard.php" class="hover:text-[#4a90e2] transition-colors">Dashboard</a></li>
                    <li><a href="../rpt_services.php" class="hover:text-[#4a90e2] transition-colors">RPT Services</a></li>
                    <li><a href="application_history.php" class="hover:text-[#4a90e2] transition-colors">Application History</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                <ul class="space-y-3 text-gray-600">
                    <li class="flex items-start">
                        <i class="fas fa-phone mr-2 text-gray-400 mt-0.5"></i>
                        <span>(02) 8123-4567</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-envelope mr-2 text-gray-400 mt-0.5"></i>
                        <span>rpt@goserveph.gov.ph</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-clock mr-2 text-gray-400 mt-0.5"></i>
                        <span>Mon-Fri: 8AM - 5PM</span>
                    </li>
                </ul>
            </div>

            <!-- Social -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Connect</h4>
                <div class="flex space-x-4 text-2xl">
                    <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-red-600 transition-colors">
                        <i class="fab fa-youtube"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Copyright -->
        <div class="border-t border-gray-200 mt-10 pt-8">
            <p class="text-sm text-gray-500 text-center">
                &copy; 2026 GoServePH Local Government Unit. Republic of the Philippines.
            </p>
        </div>
    </div>
</footer>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="modal-close">&times;</span>
    <img class="modal-content" id="modalImage">
    <div id="modalCaption" class="modal-caption"></div>
</div>

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

// Close modal when clicking X
document.getElementsByClassName("modal-close")[0].onclick = function() {
    modal.style.display = "none";
}

// Close modal when clicking outside image
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

// Close modal with ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && modal.style.display === 'block') {
        modal.style.display = 'none';
    }
});

// Document card hover effects
document.querySelectorAll('.document-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});
</script>
</body>
</html>