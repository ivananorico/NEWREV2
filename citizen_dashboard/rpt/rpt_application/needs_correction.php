<?php
// revenue2/citizen_dashboard/rpt/rpt_application/need_correction.php
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
        
        // Update owner information
        $owner_stmt = $pdo->prepare("
            UPDATE property_owners 
            SET first_name = ?, last_name = ?, middle_name = ?, suffix = ?,
                email = ?, phone = ?, tin_number = ?, 
                house_number = ?, street = ?, barangay = ?,
                district = ?, city = ?, province = ?, zip_code = ?,
                birthdate = ?
            WHERE id = (SELECT owner_id FROM property_registrations WHERE id = ?)
        ");
        
        $owner_stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['middle_name'] ?? NULL,
            $_POST['suffix'] ?? NULL,
            $_POST['email'],
            $_POST['phone'],
            $_POST['tin_number'] ?? NULL,
            $_POST['house_number'],
            $_POST['street'],
            $_POST['barangay'],
            $_POST['district'],
            $_POST['city'] ?? 'Quezon City',
            $_POST['province'] ?? 'Metro Manila',
            $_POST['zip_code'],
            !empty($_POST['birthdate']) ? $_POST['birthdate'] : NULL,
            $registration_id
        ]);
        
        // Update property registration
        $reg_stmt = $pdo->prepare("
            UPDATE property_registrations 
            SET lot_location = ?, barangay = ?, district = ?, 
                has_building = ?, status = 'resubmitted', 
                correction_notes = NULL, updated_at = NOW()
            WHERE id = ?
        ");
        
        $reg_stmt->execute([
            $_POST['property_lot_location'],
            $_POST['property_barangay'],
            $_POST['property_district'],
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
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                $file_type = mime_content_type($file['tmp_name']);
                
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("$doc_name must be an image or PDF");
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
            pr.has_building,
            pr.status,
            pr.correction_notes,
            DATE(pr.updated_at) as last_updated,
            
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
            po.zip_code as owner_zip_code
            
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
                    SELECT document_type, file_name, file_path 
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
        .status-needs_correction { background-color: #ffedd5; color: #9a3412; border: 1px solid #fb923c; }
        .empty-state { text-align: center; padding: 3rem 1.5rem; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .empty-icon { font-size: 3.5rem; color: #d1d5db; margin-bottom: 1.5rem; }
        
        /* Simple document upload style */
        .document-upload {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
        }
        
        .current-file {
            background: #f8fafc;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 0.875rem;
        }
        
        .file-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 4px;
        }
        
        .file-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-view {
            padding: 4px 12px;
            background: #3b82f6;
            color: white;
            border-radius: 4px;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-view:hover {
            background: #2563eb;
        }
        
        .upload-btn {
            border: 1px dashed #9ca3af;
            padding: 16px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            background: white;
            transition: all 0.2s;
        }
        
        .upload-btn:hover {
            border-color: #3b82f6;
            background: #f0f9ff;
        }
        
        .file-preview {
            font-size: 0.75rem;
            color: #10b981;
            margin-top: 4px;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
<?php include '../../navbar.php'; ?>

<main class="max-w-4xl mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center mb-3">
            <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Applications Needing Correction</h1>
                <p class="text-gray-600">Review and fix applications that need corrections</p>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $message_type == 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300'; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Corrections Needed</h3>
            <p class="text-gray-600 mb-6">All your applications are up to date.</p>
            <a href="pending.php" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-clock mr-2"></i>Check Pending Applications
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($applications as $app): ?>
                <?php
                    $full_name = trim($app['first_name'] . ' ' . (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . $app['last_name'] . (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
                    
                    // Fetch uploaded documents
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
                    
                    $is_editing = $edit_mode && $editing_id == $app['id'];
                ?>
                
                <!-- Application Card -->
                <div class="bg-white rounded-lg shadow border border-gray-200">
                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-lg font-bold text-gray-900"><?php echo $app['reference_number']; ?></span>
                                    <span class="status-badge status-needs_correction">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Needs Correction
                                    </span>
                                </div>
                                <div class="text-gray-600 text-sm">
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    <?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500">Updated</div>
                                <div class="font-medium"><?php echo date('M j, Y', strtotime($app['last_updated'])); ?></div>
                            </div>
                        </div>
                        
                        <!-- Correction Notes -->
                        <?php if (!empty($app['correction_notes'])): ?>
                            <div class="mb-6 p-4 bg-red-50 rounded-lg border border-red-200">
                                <h4 class="font-semibold text-red-800 mb-2 flex items-center">
                                    <i class="fas fa-comment-dots mr-2"></i>
                                    Assessor's Notes:
                                </h4>
                                <p class="text-red-700 text-sm"><?php echo htmlspecialchars($app['correction_notes']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- EDIT FORM -->
                        <?php if ($is_editing): ?>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="edit_application" value="1">
                                <input type="hidden" name="registration_id" value="<?php echo $app['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                                
                                <!-- Personal Info -->
                                <div class="mb-6">
                                    <h4 class="font-semibold text-gray-800 mb-3">Personal Information</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                                            <input type="text" name="first_name" required 
                                                value="<?php echo htmlspecialchars($edit_data['first_name']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                                            <input type="text" name="last_name" required 
                                                value="<?php echo htmlspecialchars($edit_data['last_name']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                            <input type="email" name="email" required 
                                                value="<?php echo htmlspecialchars($edit_data['email']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                                            <input type="text" name="phone" required 
                                                value="<?php echo htmlspecialchars($edit_data['phone']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">House No. *</label>
                                            <input type="text" name="house_number" required 
                                                value="<?php echo htmlspecialchars($edit_data['house_number'] ?? ''); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Street *</label>
                                            <input type="text" name="street" required 
                                                value="<?php echo htmlspecialchars($edit_data['street'] ?? ''); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Barangay *</label>
                                            <input type="text" name="barangay" required 
                                                value="<?php echo htmlspecialchars($edit_data['owner_barangay'] ?? ''); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">District *</label>
                                            <select name="district" required class="w-full px-3 py-2 border border-gray-300 rounded">
                                                <option value="">Select District</option>
                                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                                    <option value="<?php echo $i; ?>" <?php echo ($edit_data['owner_district'] ?? '') == $i ? 'selected' : ''; ?>>
                                                        District <?php echo $i; ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Property Info -->
                                <div class="mb-6">
                                    <h4 class="font-semibold text-gray-800 mb-3">Property Information</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Lot Location *</label>
                                            <input type="text" name="property_lot_location" required 
                                                value="<?php echo htmlspecialchars($edit_data['lot_location']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Property Barangay *</label>
                                            <input type="text" name="property_barangay" required 
                                                value="<?php echo htmlspecialchars($edit_data['barangay']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Property District *</label>
                                            <select name="property_district" required class="w-full px-3 py-2 border border-gray-300 rounded">
                                                <option value="">Select District</option>
                                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                                    <option value="<?php echo $i; ?>" <?php echo ($edit_data['district'] ?? '') == $i ? 'selected' : ''; ?>>
                                                        District <?php echo $i; ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Building? *</label>
                                            <select name="has_building" required class="w-full px-3 py-2 border border-gray-300 rounded">
                                                <option value="yes" <?php echo $edit_data['has_building'] == 'yes' ? 'selected' : ''; ?>>Yes</option>
                                                <option value="no" <?php echo $edit_data['has_building'] == 'no' ? 'selected' : ''; ?>>No</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Documents Section - SIMPLIFIED -->
                                <div class="mb-6">
                                    <h4 class="font-semibold text-gray-800 mb-3">Update Documents</h4>
                                    <p class="text-sm text-gray-600 mb-4">Upload new files only if you need to replace existing ones.</p>
                                    
                                    <?php 
                                    $document_fields = [
                                        'barangay_certificate' => 'Barangay Certificate',
                                        'ownership_proof' => 'Proof of Ownership',
                                        'valid_id' => 'Valid ID',
                                        'survey_plan' => 'Survey Plan'
                                    ];
                                    
                                    foreach ($document_fields as $field => $label):
                                        $has_current = isset($edit_documents[$field]);
                                        $current_doc = $has_current ? $edit_documents[$field] : null;
                                        $current_url = $has_current ? getDocumentUrl($current_doc['file_path']) : '';
                                    ?>
                                        <div class="document-upload">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <?php echo $label; ?>
                                            </label>
                                            
                                            <?php if ($has_current): ?>
                                                <div class="current-file">
                                                    <div class="file-info">
                                                        <span class="font-medium">Current File:</span>
                                                        <div class="file-actions">
                                                            <a href="<?php echo $current_url; ?>" target="_blank" 
                                                               class="btn-view">
                                                                <i class="fas fa-eye"></i> View
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <div class="text-xs text-gray-600 truncate">
                                                        <?php echo htmlspecialchars($current_doc['file_name']); ?>
                                                    </div>
                                                </div>
                                                <p class="text-xs text-gray-500 mb-2">Upload a new file to replace the current one:</p>
                                            <?php endif; ?>
                                            
                                            <label class="upload-btn">
                                                <input type="file" 
                                                       name="<?php echo $field; ?>" 
                                                       accept=".jpg,.jpeg,.png,.pdf" 
                                                       class="hidden"
                                                       onchange="showFileName(this, '<?php echo $field; ?>_name')">
                                                <i class="fas fa-upload text-gray-400 text-lg mb-2"></i>
                                                <div class="text-sm text-gray-600">Click to upload <?php echo strtolower($label); ?></div>
                                                <div class="text-xs text-gray-500">JPG, PNG, PDF (max 5MB)</div>
                                            </label>
                                            <div id="<?php echo $field; ?>_name" class="file-preview"></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Submit Buttons -->
                                <div class="flex justify-end gap-3 pt-4 border-t">
                                    <a href="?" class="px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                                        Cancel
                                    </a>
                                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center">
                                        <i class="fas fa-paper-plane mr-2"></i> Submit Corrections
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- View Mode -->
                            <div class="space-y-4">
                                <!-- Basic Info -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <h4 class="font-semibold text-gray-700 mb-2">Applicant</h4>
                                        <div class="text-sm space-y-1">
                                            <div><span class="text-gray-600">Name:</span> <?php echo $full_name; ?></div>
                                            <div><span class="text-gray-600">Email:</span> <?php echo $app['email']; ?></div>
                                            <div><span class="text-gray-600">Phone:</span> <?php echo $app['phone']; ?></div>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-700 mb-2">Property</h4>
                                        <div class="text-sm space-y-1">
                                            <div><span class="text-gray-600">Location:</span> <?php echo $app['lot_location']; ?></div>
                                            <div><span class="text-gray-600">Barangay:</span> <?php echo $app['barangay']; ?></div>
                                            <div><span class="text-gray-600">District:</span> <?php echo $app['district']; ?></div>
                                            <div><span class="text-gray-600">Building:</span> <?php echo $app['has_building'] == 'yes' ? 'Yes' : 'No'; ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Current Documents -->
                                <?php if (!empty($documents)): ?>
                                    <div>
                                        <h4 class="font-semibold text-gray-700 mb-3">Current Documents</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <?php foreach ($doc_labels as $type => $label): ?>
                                                <?php if (isset($docs_by_type[$type])): ?>
                                                    <?php $doc = $docs_by_type[$type]; ?>
                                                    <?php $doc_url = getDocumentUrl($doc['file_path']); ?>
                                                    <div class="border rounded-lg p-3">
                                                        <div class="flex justify-between items-start mb-2">
                                                            <span class="font-medium text-sm"><?php echo $label; ?></span>
                                                            <a href="<?php echo $doc_url; ?>" target="_blank" 
                                                               class="btn-view">
                                                                <i class="fas fa-eye"></i> View
                                                            </a>
                                                        </div>
                                                        <div class="text-xs text-gray-600 truncate">
                                                            <?php echo htmlspecialchars($doc['file_name']); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Action Button -->
                                <div class="pt-4 border-t">
                                    <a href="?edit=<?php echo $app['id']; ?>" 
                                       class="w-full md:w-auto inline-flex justify-center items-center px-4 py-2.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700">
                                        <i class="fas fa-edit mr-2"></i> Make Corrections
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
function showFileName(input, displayId) {
    const display = document.getElementById(displayId);
    if (input.files.length > 0) {
        const fileName = input.files[0].name;
        const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);
        display.innerHTML = `<i class="fas fa-check text-green-500 mr-1"></i> Selected: ${fileName} (${fileSize}MB)`;
    } else {
        display.innerHTML = '';
    }
}
</script>
</body>
</html>