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
$user_email = $_SESSION['user_email'] ?? '';

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
            pr.updated_at,
            pr.created_at,
            
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
<title>Applications Needing Correction - RPT Services | GoServePH</title>
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

.status-needs-correction {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
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

/* File upload card */
.file-upload-card {
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 1.5rem;
    background: #f9fafb;
    transition: all 0.2s ease;
    cursor: pointer;
}

.file-upload-card:hover {
    border-color: #4a90e2;
    background: rgba(74, 144, 226, 0.05);
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

/* Form input focus */
.form-input:focus {
    outline: none;
    ring: 2px;
    ring-color: var(--primary);
    border-color: var(--primary);
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
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Applications Needing Correction</h1>
                    <p class="text-gray-600">Please fix the issues noted below to continue your application</p>
                </div>
                
                <div class="text-right">
                    <?php if ($total_applications > 0): ?>
                    <div class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-700 rounded-lg text-sm font-medium">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?php echo $total_applications; ?> Need Correction
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($total_applications > 0): ?>
            <div class="mt-4 p-4 bg-amber-50 rounded-lg border border-amber-100">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-amber-500 mt-0.5 mr-3"></i>
                    <div>
                        <p class="text-sm text-amber-700">
                            Please review the assessor's notes and make the necessary corrections. You'll need to resubmit your application for review. 
                            You'll receive email notifications at <span class="font-semibold"><?php echo $user_email; ?></span> once your application is reviewed again.
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
                    <i class="fas fa-check-circle text-6xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Corrections Needed</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto">All your applications are up to date and don't require any corrections at this time.</p>
                <div class="space-x-4">
                    <a href="pending.php" 
                       class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors">
                        <i class="fas fa-clock mr-2"></i> View Pending Applications
                    </a>
                    <a href="application_history.php" 
                       class="inline-flex items-center px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium transition-colors">
                        <i class="fas fa-history mr-2"></i> View History
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Applications List -->
            <div class="space-y-8">
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

                    <?php if ($is_editing): ?>
                        <!-- EDIT MODE FORM -->
                        <div class="form-card overflow-hidden">
                            <!-- Form Header -->
                            <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-3 mb-2">
                                            <span class="font-bold text-gray-900 text-lg"><?php echo $app['reference_number']; ?></span>
                                            <span class="status-badge status-needs-correction">
                                                <i class="fas fa-edit"></i> Editing Application
                                            </span>
                                        </div>
                                        <div class="text-gray-600 text-sm">
                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                            <?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?>
                                        </div>
                                    </div>
                                    <a href="?" class="back-button">
                                        <i class="fas fa-times"></i> Cancel Edit
                                    </a>
                                </div>
                            </div>

                            <!-- Form Content -->
                            <form method="POST" enctype="multipart/form-data" class="p-6">
                                <input type="hidden" name="edit_application" value="1">
                                <input type="hidden" name="registration_id" value="<?php echo $app['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                                
                                <?php if (!empty($message)): ?>
                                    <div class="mb-6 p-4 <?php echo $message_type == 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700'; ?> rounded-lg border">
                                        <div class="flex items-start">
                                            <i class="fas <?php echo $message_type == 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> mr-3 mt-0.5"></i>
                                            <div class="text-sm"><?php echo $message; ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Correction Notes -->
                                <?php if (!empty($app['correction_notes'])): ?>
                                    <div class="mb-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                        <div class="flex items-start">
                                            <i class="fas fa-comment-dots text-yellow-500 mr-3 mt-0.5"></i>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900 mb-1">Assessor's Notes:</div>
                                                <div class="text-sm text-gray-700"><?php echo htmlspecialchars($app['correction_notes']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Personal Information Section -->
                                <div class="mb-8">
                                    <div class="section-header">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-user-circle mr-3 text-blue-500"></i>
                                            Personal Information
                                        </h3>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                First Name <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="first_name" required 
                                                value="<?php echo htmlspecialchars($edit_data['first_name']); ?>"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                Last Name <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="last_name" required 
                                                value="<?php echo htmlspecialchars($edit_data['last_name']); ?>"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">Middle Name</label>
                                            <input type="text" name="middle_name"
                                                value="<?php echo htmlspecialchars($edit_data['middle_name'] ?? ''); ?>"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">Suffix</label>
                                            <select name="suffix"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white">
                                                <option value="">Select Suffix</option>
                                                <option value="Jr." <?php echo ($edit_data['suffix'] ?? '') == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                                <option value="Sr." <?php echo ($edit_data['suffix'] ?? '') == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                                <option value="II" <?php echo ($edit_data['suffix'] ?? '') == 'II' ? 'selected' : ''; ?>>II</option>
                                                <option value="III" <?php echo ($edit_data['suffix'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                                            </select>
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">Birthdate</label>
                                            <input type="date" name="birthdate"
                                                value="<?php echo htmlspecialchars($edit_data['birthdate'] ?? ''); ?>"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">Sex</label>
                                            <select name="sex"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white">
                                                <option value="">Select Sex</option>
                                                <option value="male" <?php echo ($edit_data['sex'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="female" <?php echo ($edit_data['sex'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                                            </select>
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">Marital Status</label>
                                            <select name="marital_status"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white">
                                                <option value="">Select Marital Status</option>
                                                <option value="single" <?php echo ($edit_data['marital_status'] ?? '') == 'single' ? 'selected' : ''; ?>>Single</option>
                                                <option value="married" <?php echo ($edit_data['marital_status'] ?? '') == 'married' ? 'selected' : ''; ?>>Married</option>
                                            </select>
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                Email Address <span class="text-red-500">*</span>
                                            </label>
                                            <input type="email" name="email" required 
                                                value="<?php echo htmlspecialchars($edit_data['email']); ?>"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                Phone Number <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="phone" required 
                                                value="<?php echo htmlspecialchars($edit_data['phone']); ?>"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">TIN Number</label>
                                            <input type="text" name="tin_number" 
                                                value="<?php echo htmlspecialchars($edit_data['tin_number'] ?? ''); ?>"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                    </div>
                                </div>

                                <!-- Address Information Section -->
                                <div class="mb-8">
                                    <div class="section-header">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-home mr-3 text-green-500"></i>
                                            Home Address
                                        </h3>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                House Number <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="house_number" required 
                                                value="<?php echo htmlspecialchars($edit_data['house_number'] ?? ''); ?>"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                Street <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="street" required 
                                                value="<?php echo htmlspecialchars($edit_data['street'] ?? ''); ?>"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                Barangay <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="barangay" required 
                                                value="<?php echo htmlspecialchars($edit_data['owner_barangay'] ?? ''); ?>"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                District <span class="text-red-500">*</span>
                                            </label>
                                            <select name="district" required 
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200 bg-white">
                                                <option value="">Select District</option>
                                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                                    <option value="<?php echo $i; ?>" <?php echo ($edit_data['owner_district'] ?? '') == $i ? 'selected' : ''; ?>>
                                                        District <?php echo $i; ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                City <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" value="Quezon City" required readonly
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                                            <input type="hidden" name="city" value="Quezon City">
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                ZIP Code <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="zip_code" required 
                                                value="<?php echo htmlspecialchars($edit_data['owner_zip_code'] ?? ''); ?>"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                    </div>
                                </div>

                                <!-- Property Information Section -->
                                <div class="mb-8">
                                    <div class="section-header">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-map-marker-alt mr-3 text-orange-500"></i>
                                            Property Location
                                        </h3>
                                    </div>
                                    
                                    <div class="space-y-6">
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                Lot Location <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="property_lot_location" required 
                                                value="<?php echo htmlspecialchars($edit_data['lot_location']); ?>"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div class="space-y-2">
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Property Barangay <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" name="property_barangay" required 
                                                    value="<?php echo htmlspecialchars($edit_data['barangay']); ?>"
                                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-200">
                                            </div>
                                            
                                            <div class="space-y-2">
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Property District <span class="text-red-500">*</span>
                                                </label>
                                                <select name="property_district" required 
                                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-200 bg-white">
                                                    <option value="">Select District</option>
                                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo ($edit_data['district'] ?? '') == $i ? 'selected' : ''; ?>>
                                                            District <?php echo $i; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="space-y-2">
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Property City <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" value="Quezon City" required readonly
                                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                                                <input type="hidden" name="property_city" value="Quezon City">
                                            </div>
                                            
                                            <div class="space-y-2">
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Property ZIP Code <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" name="property_zip_code" required 
                                                    value="<?php echo htmlspecialchars($edit_data['zip_code'] ?? ''); ?>"
                                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-200">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Documents Upload Section -->
                                <div class="mb-8">
                                    <div class="section-header">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-file-upload mr-3 text-red-500"></i>
                                            Update Documents (Optional)
                                        </h3>
                                    </div>
                                    
                                    <div class="space-y-6">
                                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                            <div class="flex items-start">
                                                <i class="fas fa-info-circle text-yellow-500 mr-3 mt-0.5"></i>
                                                <div class="text-sm text-yellow-700">
                                                    <p class="font-medium mb-1">Important Notes:</p>
                                                    <ul class="list-disc pl-4 space-y-1">
                                                        <li>All documents must be clear and readable images (JPG, JPEG, PNG only)</li>
                                                        <li>Maximum file size: 5MB per file</li>
                                                        <li>Make sure documents are not expired</li>
                                                        <li>Only upload new files if you need to update them</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <?php
                                            $documents_list = [
                                                'barangay_certificate' => [
                                                    'label' => 'Barangay Certificate',
                                                    'description' => 'Issued by the barangay where the property is located'
                                                ],
                                                'ownership_proof' => [
                                                    'label' => 'Proof of Ownership',
                                                    'description' => 'Deed of Sale, Tax Declaration, Title, etc.'
                                                ],
                                                'valid_id' => [
                                                    'label' => 'Valid ID',
                                                    'description' => 'Government-issued ID (Driver\'s License, Passport, etc.)'
                                                ],
                                                'survey_plan' => [
                                                    'label' => 'Survey Plan',
                                                    'description' => 'Property sketch or survey plan'
                                                ]
                                            ];
                                            
                                            foreach ($documents_list as $field_name => $doc_info):
                                                $has_existing = isset($edit_documents[$field_name]);
                                                $doc = $has_existing ? $edit_documents[$field_name] : null;
                                                $doc_url = $doc ? getDocumentUrl($doc['file_path']) : '';
                                            ?>
                                            <div class="space-y-3">
                                                <label class="block text-sm font-medium text-gray-700">
                                                    <?php echo $doc_info['label']; ?>
                                                </label>
                                                
                                                <?php if ($has_existing): ?>
                                                    <div class="mb-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
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
                                                                   class="px-2 py-1 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 flex items-center transition-colors">
                                                                    <i class="fas fa-eye mr-1 text-xs"></i> View
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="file-upload-card" onclick="document.getElementById('<?php echo $field_name; ?>').click()">
                                                    <input type="file" 
                                                           name="<?php echo $field_name; ?>" 
                                                           accept=".jpg,.jpeg,.png"
                                                           class="hidden" 
                                                           id="<?php echo $field_name; ?>"
                                                           onchange="showFileName(this, '<?php echo $field_name; ?>_filename')">
                                                    
                                                    <div class="flex items-center">
                                                        <i class="fas fa-cloud-upload-alt text-gray-400 mr-3 text-xl"></i>
                                                        <div class="flex-1">
                                                            <div class="text-sm text-gray-600 font-medium">Click to upload new file</div>
                                                            <div class="text-xs text-gray-500 mt-1">JPG, JPEG, PNG up to 5MB</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div id="<?php echo $field_name; ?>_filename" class="mt-3"></div>
                                                </div>
                                                
                                                <p class="text-xs text-gray-500"><?php echo $doc_info['description']; ?></p>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Building Information Section -->
                                <div class="mb-8">
                                    <div class="section-header">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-building mr-3 text-purple-500"></i>
                                            Building Information
                                        </h3>
                                    </div>
                                    
                                    <div class="p-6 bg-gray-50 rounded-xl border border-gray-200">
                                        <label class="block text-sm font-medium text-gray-700 mb-4">
                                            Does this property have any buildings? <span class="text-red-500">*</span>
                                        </label>
                                        
                                        <div class="flex flex-col sm:flex-row sm:space-x-8 space-y-4 sm:space-y-0">
                                            <label class="flex items-center cursor-pointer">
                                                <div class="relative">
                                                    <input type="radio" name="has_building" value="yes" required 
                                                        class="sr-only"
                                                        <?php echo ($edit_data['has_building'] == 'yes') ? 'checked' : ''; ?>>
                                                    <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center">
                                                        <div class="w-3 h-3 rounded-full bg-purple-600 <?php echo ($edit_data['has_building'] == 'yes') ? '' : 'hidden'; ?>"></div>
                                                    </div>
                                                </div>
                                                <span class="ml-3 text-gray-700">Yes, there is a building/house</span>
                                            </label>
                                            
                                            <label class="flex items-center cursor-pointer">
                                                <div class="relative">
                                                    <input type="radio" name="has_building" value="no" 
                                                        class="sr-only"
                                                        <?php echo ($edit_data['has_building'] == 'no') ? 'checked' : ''; ?>>
                                                    <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center">
                                                        <div class="w-3 h-3 rounded-full bg-purple-600 <?php echo ($edit_data['has_building'] == 'no') ? '' : 'hidden'; ?>"></div>
                                                    </div>
                                                </div>
                                                <span class="ml-3 text-gray-700">No, it's vacant land</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Buttons -->
                                <div class="flex flex-col sm:flex-row justify-end space-y-4 sm:space-y-0 sm:space-x-4 pt-6 border-t border-gray-200">
                                    <a href="?" 
                                       class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                        <i class="fas fa-times mr-2"></i> Cancel
                                    </a>
                                    <button type="submit" 
                                        class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-8 rounded-lg transition-all duration-300 flex items-center justify-center shadow-lg hover:shadow-xl">
                                        <i class="fas fa-paper-plane mr-3"></i>
                                        Submit Corrections
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- VIEW MODE - Application Card -->
                        <div class="form-card overflow-hidden">
                            <!-- Card Header -->
                            <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-3 mb-2">
                                            <span class="font-bold text-gray-900 text-lg"><?php echo $app['reference_number']; ?></span>
                                            <span class="status-badge status-needs-correction">
                                                <i class="fas fa-clock"></i> Needs Correction
                                            </span>
                                        </div>
                                        <div class="text-gray-600 text-sm">
                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                            <?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?>
                                        </div>
                                    </div>
                                    <div class="text-gray-600 text-sm">
                                        <i class="far fa-calendar mr-1"></i>
                                        Updated: <?php echo date('F j, Y', strtotime($app['updated_at'])); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress Section -->
                            <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="text-sm font-medium text-gray-900">Application Progress</div>
                                    <div class="text-sm font-semibold text-blue-600">Step 1 of 4</div>
                                </div>
                                <div class="progress-container mb-2">
                                    <div class="progress-fill" style="width: 25%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-600">
                                    <span class="font-medium text-blue-600">Pending</span>
                                    <span>Inspection</span>
                                    <span>Assessment</span>
                                    <span>Approval</span>
                                </div>
                            </div>

                            <!-- Correction Notes -->
                            <?php if (!empty($app['correction_notes'])): ?>
                                <div class="p-6 bg-yellow-50 border-b border-yellow-200">
                                    <div class="flex items-start">
                                        <i class="fas fa-comment-dots text-yellow-500 mt-0.5 mr-3"></i>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900 mb-1">Assessor's Notes:</div>
                                            <div class="text-sm text-gray-700"><?php echo htmlspecialchars($app['correction_notes']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

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
                                <?php if (!empty($documents)): ?>
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
                                                    Your application needs corrections. Please review the assessor's notes above and make the necessary changes. Once corrected, resubmit your application for review.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="p-4 bg-amber-50 border border-amber-100 rounded-lg">
                                        <div class="flex items-start">
                                            <div class="mr-3 text-amber-500">
                                                <i class="fas fa-clock text-lg"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900 mb-1">Action Required</div>
                                                <div class="text-sm text-gray-700">
                                                    • Review the correction notes<br>
                                                    • Make necessary changes to your application<br>
                                                    • Update any required documents<br>
                                                    • Resubmit for review
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Button -->
                                <div class="mt-8 pt-8 border-t border-gray-200 text-center">
                                    <a href="?edit=<?php echo $app['id']; ?>" 
                                       class="inline-flex items-center px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors">
                                        <i class="fas fa-edit mr-3"></i> Make Corrections
                                    </a>
                                    <p class="text-sm text-gray-600 mt-4">
                                        Please fix the issues noted above and resubmit your application to continue the process.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
        this.style.transform = 'translateY(-4px)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// File upload functionality
function showFileName(input, displayId) {
    const display = document.getElementById(displayId);
    const file = input.files[0];
    
    if (file) {
        if (file.type.startsWith('image/')) {
            let fileSize = '';
            if (file.size < 1024 * 1024) {
                fileSize = (file.size / 1024).toFixed(0) + ' KB';
            } else {
                fileSize = (file.size / (1024 * 1024)).toFixed(1) + ' MB';
            }
            
            let shortName = file.name;
            if (shortName.length > 20) {
                shortName = shortName.substring(0, 17) + '...';
            }
            
            display.innerHTML = `
                <div class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                    <div class="flex items-center truncate">
                        <i class="fas fa-file-image text-blue-500 mr-2"></i>
                        <span class="text-blue-700 truncate" title="${file.name}">${shortName}</span>
                    </div>
                    <div class="flex items-center space-x-2 ml-3">
                        <span class="text-xs text-blue-500">${fileSize}</span>
                        <button type="button" onclick="removeFile(this, '${input.id}', '${displayId}')" 
                                class="text-blue-400 hover:text-red-500 transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        } else {
            display.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-red-600 text-sm">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    Invalid file type. Please upload JPG, JPEG, or PNG only.
                </div>
            `;
            input.value = '';
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

// Style radio buttons
document.addEventListener('DOMContentLoaded', function() {
    const radioButtons = document.querySelectorAll('input[type="radio"]');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            const allRadios = document.querySelectorAll(`input[name="${this.name}"]`);
            allRadios.forEach(r => {
                const radioDiv = r.parentElement.querySelector('div');
                const innerCircle = radioDiv.querySelector('div');
                if (r.checked) {
                    innerCircle.classList.remove('hidden');
                    radioDiv.classList.add('border-purple-600');
                } else {
                    innerCircle.classList.add('hidden');
                    radioDiv.classList.remove('border-purple-600');
                }
            });
        });
        
        // Initialize checked state
        if (radio.checked) {
            const radioDiv = radio.parentElement.querySelector('div');
            const innerCircle = radioDiv.querySelector('div');
            innerCircle.classList.remove('hidden');
            radioDiv.classList.add('border-purple-600');
        }
    });
    
    // Add drag and drop for file uploads
    const fileUploadCards = document.querySelectorAll('.file-upload-card');
    fileUploadCards.forEach(card => {
        card.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('border-blue-500', 'bg-blue-50');
        });
        
        card.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('border-blue-500', 'bg-blue-50');
        });
        
        card.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('border-blue-500', 'bg-blue-50');
            
            if (e.dataTransfer.files.length) {
                const input = this.querySelector('input[type="file"]');
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