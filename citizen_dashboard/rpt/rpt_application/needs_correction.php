<?php
// revenue2/citizen_dashboard/rpt/rpt_application/need_correction.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Citizen';

// Include database connection
require_once '../../../db/RPT/rpt_db.php';
$pdo = getDatabaseConnection();

// Handle form submission for editing
$message = '';
$message_type = '';
$editing_id = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_application'])) {
    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Invalid security token. Please try again.");
        }
        
        $registration_id = $_POST['registration_id'];
        
        // Verify ownership and status
        $check_owner = $pdo->prepare("
            SELECT pr.id, pr.status, pr.reference_number 
            FROM property_registrations pr
            JOIN property_owners po ON pr.owner_id = po.id
            WHERE pr.id = ? AND po.user_id = ? AND pr.status = 'needs_correction'
        ");
        $check_owner->execute([$registration_id, $user_id]);
        $application = $check_owner->fetch();
        
        if (!$application) {
            throw new Exception("You cannot edit this application. It may not be in 'Needs Correction' status or you don't own it.");
        }
        
        $pdo->beginTransaction();
        
        // Update owner information with all fields from rpt_registration.php
        $owner_stmt = $pdo->prepare("
            UPDATE property_owners 
            SET first_name = ?, last_name = ?, middle_name = ?, suffix = ?,
                birthdate = ?, sex = ?, marital_status = ?, email = ?, phone = ?, 
                tin_number = ?, house_number = ?, street = ?, barangay = ?,
                district = ?, city = ?, province = ?, zip_code = ?
            WHERE id = (SELECT owner_id FROM property_registrations WHERE id = ?)
        ");
        
        $owner_stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            !empty($_POST['middle_name']) ? $_POST['middle_name'] : null,
            !empty($_POST['suffix']) ? $_POST['suffix'] : null,
            !empty($_POST['birthdate']) ? $_POST['birthdate'] : null,
            !empty($_POST['sex']) ? $_POST['sex'] : null,
            !empty($_POST['marital_status']) ? $_POST['marital_status'] : null,
            $_POST['email'],
            $_POST['phone'],
            !empty($_POST['tin_number']) ? $_POST['tin_number'] : null,
            $_POST['house_number'],
            $_POST['street'],
            $_POST['barangay'],
            $_POST['district'],
            $_POST['city'] ?? 'Quezon City',
            $_POST['province'] ?? 'Metro Manila',
            $_POST['zip_code'],
            $registration_id
        ]);
        
        // Update property registration
        $reg_stmt = $pdo->prepare("
            UPDATE property_registrations 
            SET lot_location = ?, barangay = ?, district = ?, 
                city = ?, province = ?, zip_code = ?, has_building = ?, 
                status = 'resubmitted', correction_notes = NULL, updated_at = NOW()
            WHERE id = ?
        ");
        
        $reg_stmt->execute([
            trim($_POST['property_lot_location']),
            trim($_POST['property_barangay']),
            trim($_POST['property_district']),
            $_POST['property_city'] ?? 'Quezon City',
            $_POST['property_province'] ?? 'Metro Manila',
            $_POST['property_zip_code'],
            $_POST['has_building'],
            $registration_id
        ]);
        
        // Handle file uploads
        $document_types = [
            'barangay_certificate' => 'Barangay Certificate',
            'ownership_proof' => 'Proof of Ownership', 
            'valid_id' => 'Valid ID',
            'survey_plan' => 'Survey Plan'
        ];
        
        $reference_number = $application['reference_number'];
        $upload_dir = '../../../documents/rpt/applications/' . $reference_number . '/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($document_types as $field_name => $doc_name) {
            if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] == UPLOAD_ERR_OK) {
                $file = $_FILES[$field_name];
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                $file_type = mime_content_type($file['tmp_name']);
                
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("$doc_name must be an image (JPG, JPEG, PNG)");
                }
                
                $max_size = 5 * 1024 * 1024;
                if ($file['size'] > $max_size) {
                    throw new Exception("$doc_name is too large. Maximum size is 5MB");
                }
                
                // Check if document exists
                $check_stmt = $pdo->prepare("
                    SELECT id, file_path, file_name 
                    FROM property_documents 
                    WHERE registration_id = ? AND document_type = ?
                    LIMIT 1
                ");
                $check_stmt->execute([$registration_id, $field_name]);
                $existing_doc = $check_stmt->fetch();
                
                $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = $field_name . '_' . time() . '.' . $file_ext;
                $file_path = $upload_dir . $new_filename;
                $relative_path = 'documents/rpt/applications/' . $reference_number . '/' . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    if ($existing_doc) {
                        // Delete old file
                        $old_file_path = '../../../' . $existing_doc['file_path'];
                        if (file_exists($old_file_path)) {
                            unlink($old_file_path);
                        }
                        
                        // Update record
                        $doc_stmt = $pdo->prepare("
                            UPDATE property_documents 
                            SET file_name = ?, file_path = ?, file_size = ?, 
                                file_type = ?, uploaded_by = ?
                            WHERE id = ?
                        ");
                        $doc_stmt->execute([
                            $file['name'],
                            $relative_path,
                            $file['size'],
                            $file_type,
                            $user_id,
                            $existing_doc['id']
                        ]);
                    } else {
                        // Insert new record
                        $doc_stmt = $pdo->prepare("
                            INSERT INTO property_documents 
                            (registration_id, document_type, file_name, file_path, file_size, file_type, uploaded_by, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $doc_stmt->execute([
                            $registration_id,
                            $field_name,
                            $file['name'],
                            $relative_path,
                            $file['size'],
                            $file_type,
                            $user_id
                        ]);
                    }
                }
            }
        }
        
        $pdo->commit();
        
        // Redirect to resubmitted page
        header("Location: resubmitted.php?ref=" . $reference_number . "&id=" . $registration_id);
        exit();
        
    } catch(Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

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

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Fetch user's applications needing correction
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
            pr.city,
            pr.province,
            pr.zip_code,
            pr.has_building,
            pr.status,
            pr.correction_notes,
            DATE(pr.updated_at) as last_updated,
            DATE(pr.created_at) as submitted_date,
            
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
            po.zip_code as owner_zip_code,
            po.birthdate,
            po.sex,
            po.marital_status
            
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE po.user_id = ? 
          AND pr.status = 'needs_correction'
        ORDER BY pr.updated_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = count($applications);
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Check if we're in edit mode
$edit_mode = false;
$edit_data = [];
$edit_documents = [];

if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    
    foreach ($applications as $app) {
        if ($app['id'] == $edit_id && $app['status'] == 'needs_correction') {
            $edit_mode = true;
            $edit_data = $app;
            $editing_id = $edit_id;
            
            // Fetch existing documents
            try {
                $doc_stmt = $pdo->prepare("
                    SELECT document_type, file_name, file_path, file_size, file_type
                    FROM property_documents 
                    WHERE registration_id = ?
                ");
                $doc_stmt->execute([$edit_id]);
                $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $documents_by_type = [];
                foreach ($documents as $doc) {
                    $documents_by_type[$doc['document_type']] = $doc;
                }
                $edit_documents = $documents_by_type;
                
            } catch(PDOException $e) {}
            break;
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications Needing Correction - RPT Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge { display: inline-flex; align-items: center; padding: 0.375rem 0.875rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
        .status-needs-correction { background-color: #fef3c7; color: #92400e; border: 1px solid #fbbf24; }
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
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center mb-3">
            <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-4"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Applications Needing Correction</h1>
                <p class="text-gray-600">Your submitted applications need corrections before they can proceed</p>
            </div>
        </div>

        <!-- Status Summary -->
        <?php if ($total_applications > 0): ?>
            <div class="mt-6 flex items-center">
                <div class="mr-4">
                    <div class="text-2xl font-bold text-gray-900"><?php echo $total_applications; ?></div>
                    <div class="text-sm text-gray-500">Application<?php echo $total_applications > 1 ? 's' : ''; ?> Need<?php echo $total_applications == 1 ? 's' : ''; ?> Correction</div>
                </div>
                <div class="h-8 w-px bg-gray-300"></div>
                <div class="ml-4">
                    <div class="status-badge status-needs-correction">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Needs Correction
                    </div>
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
            <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Corrections Needed</h3>
            <p class="text-gray-600 mb-6">All your applications are up to date. Great job!</p>
            <a href="pending.php" class="inline-flex items-center px-5 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-clock mr-2"></i>Check Pending Applications
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($applications as $app): ?>
                <?php
                    $full_name = trim($app['first_name'] . ' ' . (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . $app['last_name'] . (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));

                    $documents = [];
                    try {
                        $doc_stmt = $pdo->prepare("SELECT document_type, file_name, file_path, file_size FROM property_documents WHERE registration_id = ?");
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
                    
                    $is_editing = $edit_mode && $editing_id == $app['id'];
                ?>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-start">
                        <div>
                            <div class="flex items-center mb-3">
                                <span class="text-xl font-bold text-gray-900 mr-4"><?php echo $app['reference_number']; ?></span>
                                <span class="status-badge status-needs-correction">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>Needs Correction
                                </span>
                            </div>
                            <div class="flex items-center text-gray-600"><i class="fas fa-map-marker-alt mr-2"></i>
                                <span><?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Submitted</div>
                            <div class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($app['submitted_date'])); ?></div>
                        </div>
                    </div>

                    <!-- PROGRESS BAR - STILL IN PENDING STAGE -->
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

                    <!-- Correction Notes -->
                    <?php if (!empty($app['correction_notes'])): ?>
                        <div class="px-6 py-4 bg-red-50 border-y border-red-200">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-comment-dots text-red-500 text-lg mt-1 mr-3"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-red-800 mb-1">Assessor's Notes:</h4>
                                    <p class="text-red-700 text-sm"><?php echo htmlspecialchars($app['correction_notes']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="p-6">
                        <?php if ($is_editing): ?>
                            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                                <input type="hidden" name="edit_application" value="1">
                                <input type="hidden" name="registration_id" value="<?php echo $app['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                                
                                <!-- Personal Information Section - Same as rpt_registration.php -->
                                <div class="border-b border-gray-200 pb-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-user text-blue-500 mr-2"></i>
                                        Personal Information
                                    </h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                                            <input type="text" name="first_name" required 
                                                value="<?php echo htmlspecialchars($edit_data['first_name']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                placeholder="Enter your first name">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                                            <input type="text" name="last_name" required 
                                                value="<?php echo htmlspecialchars($edit_data['last_name']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                placeholder="Enter your last name">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                            <input type="text" name="middle_name"
                                                value="<?php echo htmlspecialchars($edit_data['middle_name'] ?? ''); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                placeholder="Enter middle name (optional)">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                                            <select name="suffix"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                <option value="">Select Suffix</option>
                                                <option value="Jr." <?php echo ($edit_data['suffix'] ?? '') == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                                <option value="Sr." <?php echo ($edit_data['suffix'] ?? '') == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                                <option value="II" <?php echo ($edit_data['suffix'] ?? '') == 'II' ? 'selected' : ''; ?>>II</option>
                                                <option value="III" <?php echo ($edit_data['suffix'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                                                <option value="IV" <?php echo ($edit_data['suffix'] ?? '') == 'IV' ? 'selected' : ''; ?>>IV</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate</label>
                                            <input type="date" name="birthdate"
                                                value="<?php echo htmlspecialchars($edit_data['birthdate'] ?? ''); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Sex</label>
                                            <select name="sex"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                <option value="">Select Sex</option>
                                                <option value="male" <?php echo ($edit_data['sex'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="female" <?php echo ($edit_data['sex'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                                                <option value="other" <?php echo ($edit_data['sex'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Marital Status</label>
                                            <select name="marital_status"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                <option value="">Select Marital Status</option>
                                                <option value="single" <?php echo ($edit_data['marital_status'] ?? '') == 'single' ? 'selected' : ''; ?>>Single</option>
                                                <option value="married" <?php echo ($edit_data['marital_status'] ?? '') == 'married' ? 'selected' : ''; ?>>Married</option>
                                                <option value="divorced" <?php echo ($edit_data['marital_status'] ?? '') == 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                                                <option value="widowed" <?php echo ($edit_data['marital_status'] ?? '') == 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                            <input type="email" name="email" required 
                                                value="<?php echo htmlspecialchars($edit_data['email']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                placeholder="Enter your email">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                                            <input type="text" name="phone" required 
                                                value="<?php echo htmlspecialchars($edit_data['phone']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                placeholder="Enter your phone number">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">TIN Number</label>
                                            <input type="text" name="tin_number" 
                                                value="<?php echo htmlspecialchars($edit_data['tin_number'] ?? ''); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                placeholder="Enter TIN (optional)">
                                        </div>
                                    </div>
                                    
                                    <!-- Detailed Address Fields -->
                                    <div class="mt-6">
                                        <h4 class="text-md font-semibold text-gray-700 mb-3 flex items-center">
                                            <i class="fas fa-home text-gray-500 mr-2"></i>
                                            Home Address
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">House Number *</label>
                                                <input type="text" name="house_number" required 
                                                    value="<?php echo htmlspecialchars($edit_data['house_number'] ?? ''); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    placeholder="123">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Street *</label>
                                                <input type="text" name="street" required 
                                                    value="<?php echo htmlspecialchars($edit_data['street'] ?? ''); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    placeholder="Main Street">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Barangay *</label>
                                                <input type="text" name="barangay" required 
                                                    value="<?php echo htmlspecialchars($edit_data['owner_barangay'] ?? ''); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    placeholder="Barangay Name">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">District *</label>
                                                <select name="district" required 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                    <option value="">Select District</option>
                                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo ($edit_data['owner_district'] ?? '') == $i ? 'selected' : ''; ?>>
                                                            District <?php echo $i; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">City *</label>
                                                <input type="text" name="city" value="Quezon City" required readonly
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Province *</label>
                                                <input type="text" name="province" value="Metro Manila" required readonly
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code *</label>
                                                <input type="text" name="zip_code" required 
                                                    value="<?php echo htmlspecialchars($edit_data['owner_zip_code'] ?? ''); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    placeholder="1100">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Property Information Section - Same as rpt_registration.php -->
                                <div class="border-b border-gray-200 pb-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-map-marker-alt text-green-500 mr-2"></i>
                                        Property Location
                                    </h3>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Lot Location *</label>
                                            <input type="text" name="property_lot_location" required 
                                                value="<?php echo htmlspecialchars($edit_data['lot_location']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                                placeholder="e.g., Lot 5, Block 2 or specific location">
                                            <p class="text-xs text-gray-500 mt-1">The physical location identifier of your property</p>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Property Barangay *</label>
                                                <input type="text" name="property_barangay" required 
                                                    value="<?php echo htmlspecialchars($edit_data['barangay']); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                                    placeholder="Enter property barangay">
                                                <p class="text-xs text-gray-500 mt-1">Barangay where the property is located</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Property District *</label>
                                                <select name="property_district" required 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                                    <option value="">Select District</option>
                                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo ($edit_data['district'] ?? '') == $i ? 'selected' : ''; ?>>
                                                            District <?php echo $i; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                                <p class="text-xs text-gray-500 mt-1">District where the property is located</p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Property City *</label>
                                                <input type="text" name="property_city" value="Quezon City" required readonly
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Property Province *</label>
                                                <input type="text" name="property_province" value="Metro Manila" required readonly
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Property ZIP Code *</label>
                                                <input type="text" name="property_zip_code" required 
                                                    value="<?php echo htmlspecialchars($edit_data['zip_code'] ?? ''); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                                    placeholder="1100">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Documents Upload Section -->
                                <div class="border-b border-gray-200 pb-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-file-upload text-red-500 mr-2"></i>
                                        Update Documents (Optional)
                                    </h3>
                                    
                                    <div class="space-y-6">
                                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                            <h4 class="font-semibold text-yellow-800 mb-1 flex items-center text-sm">
                                                <i class="fas fa-exclamation-triangle mr-2 text-xs"></i>
                                                Important Notes:
                                            </h4>
                                            <ul class="text-yellow-700 text-xs space-y-1 ml-4 list-disc">
                                                <li>All documents must be clear and readable images</li>
                                                <li>Accepted formats: JPG, JPEG, PNG only</li>
                                                <li>Maximum file size: 5MB per file</li>
                                                <li>Make sure documents are not expired</li>
                                                <li>Take clear photos or scans of documents</li>
                                                <li>Upload new files only if you need to replace existing ones</li>
                                            </ul>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <!-- Barangay Certificate -->
                                            <div class="space-y-1">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    <?php if (isset($edit_documents['barangay_certificate'])): ?>
                                                        Update Barangay Certificate
                                                    <?php else: ?>
                                                        <span class="text-red-500">*</span> Barangay Certificate
                                                    <?php endif; ?>
                                                </label>
                                                
                                                <?php if (isset($edit_documents['barangay_certificate'])): ?>
                                                    <?php $doc = $edit_documents['barangay_certificate']; ?>
                                                    <?php $doc_url = getDocumentUrl($doc['file_path']); ?>
                                                    <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                                        <div class="flex justify-between items-center mb-2">
                                                            <div>
                                                                <span class="font-medium text-sm text-blue-800">Current File:</span>
                                                                <div class="text-xs text-blue-600 truncate mt-1">
                                                                    <?php echo htmlspecialchars($doc['file_name']); ?>
                                                                </div>
                                                                <div class="text-xs text-blue-500 mt-1">
                                                                    <?php echo formatFileSize($doc['file_size'] ?? 0); ?>
                                                                </div>
                                                            </div>
                                                            <?php if ($doc_url): ?>
                                                                <a href="<?php echo $doc_url; ?>" target="_blank" 
                                                                   class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 flex items-center transition-colors">
                                                                    <i class="fas fa-eye mr-1 text-xs"></i> View
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="border border-gray-300 rounded-lg p-3 hover:border-blue-500 transition-colors">
                                                    <input type="file" 
                                                           name="barangay_certificate" 
                                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                                           class="hidden" 
                                                           id="barangay_certificate"
                                                           onchange="showFileName(this, 'barangay_filename')">
                                                    <label for="barangay_certificate" class="cursor-pointer flex items-center">
                                                        <div class="flex-1">
                                                            <div class="text-sm text-gray-600">Click to upload</div>
                                                            <div class="text-xs text-gray-500">JPG, JPEG, PNG up to 5MB</div>
                                                        </div>
                                                        <div class="text-gray-400 text-sm">
                                                            <i class="fas fa-upload"></i>
                                                        </div>
                                                    </label>
                                                    <div id="barangay_filename" class="mt-2"></div>
                                                </div>
                                                <p class="text-xs text-gray-500 mt-1">Issued by the barangay where the property is located</p>
                                            </div>

                                            <!-- Proof of Ownership -->
                                            <div class="space-y-1">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    <?php if (isset($edit_documents['ownership_proof'])): ?>
                                                        Update Proof of Ownership
                                                    <?php else: ?>
                                                        <span class="text-red-500">*</span> Proof of Ownership
                                                    <?php endif; ?>
                                                </label>
                                                
                                                <?php if (isset($edit_documents['ownership_proof'])): ?>
                                                    <?php $doc = $edit_documents['ownership_proof']; ?>
                                                    <?php $doc_url = getDocumentUrl($doc['file_path']); ?>
                                                    <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                                        <div class="flex justify-between items-center mb-2">
                                                            <div>
                                                                <span class="font-medium text-sm text-blue-800">Current File:</span>
                                                                <div class="text-xs text-blue-600 truncate mt-1">
                                                                    <?php echo htmlspecialchars($doc['file_name']); ?>
                                                                </div>
                                                                <div class="text-xs text-blue-500 mt-1">
                                                                    <?php echo formatFileSize($doc['file_size'] ?? 0); ?>
                                                                </div>
                                                            </div>
                                                            <?php if ($doc_url): ?>
                                                                <a href="<?php echo $doc_url; ?>" target="_blank" 
                                                                   class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 flex items-center transition-colors">
                                                                    <i class="fas fa-eye mr-1 text-xs"></i> View
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="border border-gray-300 rounded-lg p-3 hover:border-blue-500 transition-colors">
                                                    <input type="file" 
                                                           name="ownership_proof" 
                                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                                           class="hidden" 
                                                           id="ownership_proof"
                                                           onchange="showFileName(this, 'ownership_filename')">
                                                    <label for="ownership_proof" class="cursor-pointer flex items-center">
                                                        <div class="flex-1">
                                                            <div class="text-sm text-gray-600">Click to upload</div>
                                                            <div class="text-xs text-gray-500">JPG, JPEG, PNG up to 5MB</div>
                                                        </div>
                                                        <div class="text-gray-400 text-sm">
                                                            <i class="fas fa-upload"></i>
                                                        </div>
                                                    </label>
                                                    <div id="ownership_filename" class="mt-2"></div>
                                                </div>
                                                <p class="text-xs text-gray-500 mt-1">Deed of Sale, Tax Declaration, Title, etc.</p>
                                            </div>

                                            <!-- Valid ID -->
                                            <div class="space-y-1">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    <?php if (isset($edit_documents['valid_id'])): ?>
                                                        Update Valid ID
                                                    <?php else: ?>
                                                        <span class="text-red-500">*</span> Valid ID
                                                    <?php endif; ?>
                                                </label>
                                                
                                                <?php if (isset($edit_documents['valid_id'])): ?>
                                                    <?php $doc = $edit_documents['valid_id']; ?>
                                                    <?php $doc_url = getDocumentUrl($doc['file_path']); ?>
                                                    <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                                        <div class="flex justify-between items-center mb-2">
                                                            <div>
                                                                <span class="font-medium text-sm text-blue-800">Current File:</span>
                                                                <div class="text-xs text-blue-600 truncate mt-1">
                                                                    <?php echo htmlspecialchars($doc['file_name']); ?>
                                                                </div>
                                                                <div class="text-xs text-blue-500 mt-1">
                                                                    <?php echo formatFileSize($doc['file_size'] ?? 0); ?>
                                                                </div>
                                                            </div>
                                                            <?php if ($doc_url): ?>
                                                                <a href="<?php echo $doc_url; ?>" target="_blank" 
                                                                   class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 flex items-center transition-colors">
                                                                    <i class="fas fa-eye mr-1 text-xs"></i> View
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="border border-gray-300 rounded-lg p-3 hover:border-blue-500 transition-colors">
                                                    <input type="file" 
                                                           name="valid_id" 
                                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                                           class="hidden" 
                                                           id="valid_id"
                                                           onchange="showFileName(this, 'validid_filename')">
                                                    <label for="valid_id" class="cursor-pointer flex items-center">
                                                        <div class="flex-1">
                                                            <div class="text-sm text-gray-600">Click to upload</div>
                                                            <div class="text-xs text-gray-500">JPG, JPEG, PNG up to 5MB</div>
                                                        </div>
                                                        <div class="text-gray-400 text-sm">
                                                            <i class="fas fa-upload"></i>
                                                        </div>
                                                    </label>
                                                    <div id="validid_filename" class="mt-2"></div>
                                                </div>
                                                <p class="text-xs text-gray-500 mt-1">Government-issued ID (Driver's License, Passport, etc.)</p>
                                            </div>

                                            <!-- Survey Plan -->
                                            <div class="space-y-1">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    <?php if (isset($edit_documents['survey_plan'])): ?>
                                                        Update Survey Plan
                                                    <?php else: ?>
                                                        <span class="text-red-500">*</span> Survey Plan
                                                    <?php endif; ?>
                                                </label>
                                                
                                                <?php if (isset($edit_documents['survey_plan'])): ?>
                                                    <?php $doc = $edit_documents['survey_plan']; ?>
                                                    <?php $doc_url = getDocumentUrl($doc['file_path']); ?>
                                                    <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                                        <div class="flex justify-between items-center mb-2">
                                                            <div>
                                                                <span class="font-medium text-sm text-blue-800">Current File:</span>
                                                                <div class="text-xs text-blue-600 truncate mt-1">
                                                                    <?php echo htmlspecialchars($doc['file_name']); ?>
                                                                </div>
                                                                <div class="text-xs text-blue-500 mt-1">
                                                                    <?php echo formatFileSize($doc['file_size'] ?? 0); ?>
                                                                </div>
                                                            </div>
                                                            <?php if ($doc_url): ?>
                                                                <a href="<?php echo $doc_url; ?>" target="_blank" 
                                                                   class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 flex items-center transition-colors">
                                                                    <i class="fas fa-eye mr-1 text-xs"></i> View
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="border border-gray-300 rounded-lg p-3 hover:border-blue-500 transition-colors">
                                                    <input type="file" 
                                                           name="survey_plan" 
                                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                                           class="hidden" 
                                                           id="survey_plan"
                                                           onchange="showFileName(this, 'survey_filename')">
                                                    <label for="survey_plan" class="cursor-pointer flex items-center">
                                                        <div class="flex-1">
                                                            <div class="text-sm text-gray-600">Click to upload</div>
                                                            <div class="text-xs text-gray-500">JPG, JPEG, PNG up to 5MB</div>
                                                        </div>
                                                        <div class="text-gray-400 text-sm">
                                                            <i class="fas fa-upload"></i>
                                                        </div>
                                                    </label>
                                                    <div id="survey_filename" class="mt-2"></div>
                                                </div>
                                                <p class="text-xs text-gray-500 mt-1">Property sketch or survey plan</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Building Information Section -->
                                <div class="pb-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-building text-purple-500 mr-2"></i>
                                        Building Information
                                    </h3>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <label class="block text-sm font-medium text-gray-700 mb-3">Does this property have any buildings? *</label>
                                        <div class="flex space-x-6">
                                            <label class="flex items-center">
                                                <input type="radio" name="has_building" value="yes" required 
                                                    class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300"
                                                    <?php echo ($edit_data['has_building'] == 'yes') ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">Yes, there is a building/house</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="radio" name="has_building" value="no" 
                                                    class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300"
                                                    <?php echo ($edit_data['has_building'] == 'no') ? 'checked' : ''; ?>>
                                                <span class="ml-2 text-gray-700">No, it's vacant land</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Buttons -->
                                <div class="flex justify-end space-x-3">
                                    <a href="?" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                        Cancel
                                    </a>
                                    <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center font-semibold">
                                        <i class="fas fa-paper-plane mr-2"></i> Submit Corrections
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- View Mode - Changed "Applicant" to "Owner Info" -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <div>
                                    <div class="info-card-header">
                                        <div class="icon-circle bg-blue-100 text-blue-600"><i class="fas fa-user"></i></div>
                                        <div><h3 class="font-semibold text-gray-900">Owner Info</h3><p class="text-sm text-gray-500">Your registered information</p></div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div><div class="info-label">Full Name</div><div class="info-value"><?php echo $full_name; ?></div></div>
                                        <div><div class="info-label">Birthdate</div><div class="info-value"><?php echo isset($app['birthdate']) ? date('M j, Y', strtotime($app['birthdate'])) : '-'; ?></div></div>
                                        <div><div class="info-label">Sex</div><div class="info-value"><?php echo ucfirst($app['sex'] ?? '-'); ?></div></div>
                                        <div><div class="info-label">Marital Status</div><div class="info-value"><?php echo ucfirst($app['marital_status'] ?? '-'); ?></div></div>
                                        <div><div class="info-label">Contact</div><div class="info-value"><?php echo $app['phone']; ?></div></div>
                                        <div><div class="info-label">Email</div><div class="info-value"><?php echo $app['email']; ?></div></div>
                                        <?php if (!empty($app['tin_number'])): ?>
                                        <div><div class="info-label">TIN</div><div class="info-value"><?php echo $app['tin_number']; ?></div></div>
                                        <?php endif; ?>
                                        <?php if (!empty($app['owner_city'])): ?>
                                        <div><div class="info-label">City</div><div class="info-value"><?php echo $app['owner_city']; ?></div></div>
                                        <?php endif; ?>
                                        <?php if (!empty($app['owner_province'])): ?>
                                        <div><div class="info-label">Province</div><div class="info-value"><?php echo $app['owner_province']; ?></div></div>
                                        <?php endif; ?>
                                        <?php if (!empty($app['owner_zip_code'])): ?>
                                        <div><div class="info-label">Zip Code</div><div class="info-value"><?php echo $app['owner_zip_code']; ?></div></div>
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
                            
                            <!-- Current Documents -->
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
                            
                            <!-- Action Button -->
                            <div class="mt-8 pt-8 border-t border-gray-200 text-center">
                                <a href="?edit=<?php echo $app['id']; ?>" 
                                   class="inline-flex items-center px-8 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition font-semibold text-lg">
                                    <i class="fas fa-edit mr-3"></i> Make Corrections
                                </a>
                                <p class="text-sm text-gray-600 mt-4">
                                    Please fix the issues noted above and resubmit your application to continue the process.
                                </p>
                            </div>
                        <?php endif; ?>
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

function showFileName(input, displayId) {
    const display = document.getElementById(displayId);
    const file = input.files[0];
    
    if (file) {
        // Check file type
        if (file.type.startsWith('image/')) {
            // Format file size
            let fileSize = '';
            if (file.size < 1024 * 1024) { // Less than 1MB
                fileSize = (file.size / 1024).toFixed(0) + ' KB';
            } else {
                fileSize = (file.size / (1024 * 1024)).toFixed(1) + ' MB';
            }
            
            // Get short file name (max 25 chars)
            let shortName = file.name;
            if (shortName.length > 25) {
                shortName = shortName.substring(0, 22) + '...';
            }
            
            // Show file name in a compact format
            display.innerHTML = `
                <div class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded px-2 py-1 text-xs">
                    <div class="flex items-center truncate">
                        <i class="${file.type === 'application/pdf' ? 'fas fa-file-pdf text-red-500' : 'fas fa-file-image text-blue-500'} mr-2 text-xs"></i>
                        <span class="truncate" title="${file.name}">${shortName}</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-500 text-xs">${fileSize}</span>
                        <button type="button" onclick="removeFile(this, '${input.id}', '${displayId}')" 
                                class="text-gray-400 hover:text-red-500 text-xs">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        } else {
            display.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded px-2 py-1 text-xs text-red-600">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    Invalid file type. Use JPG, JPEG, or PNG
                </div>
            `;
            input.value = ''; // Clear the input
        }
    } else {
        display.innerHTML = '';
    }
}

function removeFile(button, inputId, displayId) {
    const input = document.getElementById(inputId);
    const display = document.getElementById(displayId);
    
    input.value = '';
    display.innerHTML = '';
}

// Add drag and drop functionality
document.addEventListener('DOMContentLoaded', function() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        const parentLabel = input.parentElement.querySelector('label[for]');
        const parentDiv = parentLabel.parentElement;
        
        // Highlight on drag over
        parentDiv.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('border-blue-500', 'bg-blue-50');
        });
        
        parentDiv.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('border-blue-500', 'bg-blue-50');
        });
        
        parentDiv.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('border-blue-500', 'bg-blue-50');
            
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                const event = new Event('change', { bubbles: true });
                input.dispatchEvent(event);
            }
        });
    });
});
</script>
</body>
</html>