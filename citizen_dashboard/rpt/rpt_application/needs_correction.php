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
    <title>Applications Needing Correction - RPT Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #9aa5b1;
            --accent: #4caf50;
            --background: #fbfbfb;
        }

        body {
            background: linear-gradient(135deg, rgba(240, 240, 240, 0.4) 0%, rgba(230, 230, 230, 0.4) 50%, rgba(220, 220, 220, 0.3) 100%);
            position: relative;
            overflow-x: hidden;
            min-height: 100vh;
        }

        /* Background image with blur - same as login */
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
        
        /* Animated background particles - same as login */
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

        /* Header box styles */
        .header-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border-radius: 20px;
            padding: 1.5rem;
        }

        /* Form card styling */
        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border-radius: 16px;
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

        .status-needs-correction {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fbbf24;
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

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); padding: 20px; }
        .modal-content { margin: auto; display: block; max-width: 90%; max-height: 90vh; border-radius: 8px; }
        .modal-close { position: absolute; top: 20px; right: 35px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 1001; }
        .modal-close:hover { color: #fbbf24; }
        .modal-caption { text-align: center; color: white; padding: 10px 20px; position: absolute; bottom: 0; width: 100%; background: rgba(0,0,0,0.7); }

        /* File upload */
        .file-upload-small {
            transition: all 0.3s ease;
            border: 2px dashed #d1d5db;
            padding: 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
        }

        .file-upload-small:hover {
            border-color: #4a90e2;
            background-color: rgba(74, 144, 226, 0.05);
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #4a90e2;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #357ABD;
        }

        /* Info Box */
        .info-box {
            background: #f8fafc;
            border-left: 3px solid #3b82f6;
            padding: 1rem;
            margin-bottom: 0.5rem;
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
        <div class="header-box">
            <div class="flex items-center mb-4">
                <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Applications Needing Correction</h1>
                    <p class="text-gray-600 mt-1">Please fix the issues noted below to continue your application</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($error_message)): ?>
        <div class="mb-6 p-4 bg-red-50 text-red-700 rounded-lg border border-red-200">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="simple-card p-12 text-center">
            <div class="text-gray-400 mb-4">
                <i class="fas fa-check-circle text-5xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Corrections Needed</h3>
            <p class="text-gray-600 mb-6">All your applications are up to date.</p>
            <div>
                <a href="pending.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                    <i class="fas fa-clock mr-2"></i>Check Pending Applications
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
                    <!-- EDIT MODE - Modern Form like rpt_registration.php -->
                    <div class="form-card p-6 md:p-8">
                        <div class="flex items-center mb-6">
                            <a href="?" class="text-gray-600 hover:text-blue-600 mr-4">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Make Corrections</h2>
                                <p class="text-gray-600">Update your application information</p>
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="space-y-8">
                            <input type="hidden" name="edit_application" value="1">
                            <input type="hidden" name="registration_id" value="<?php echo $app['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                            
                            <!-- Personal Information Section -->
                            <div class="border-b border-gray-200 pb-8">
                                <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                                    <i class="fas fa-user text-blue-500 mr-3 text-xl"></i>
                                    Personal Information
                                </h3>
                                
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
                            <div class="border-b border-gray-200 pb-8">
                                <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                                    <i class="fas fa-home text-green-500 mr-3 text-xl"></i>
                                    Home Address
                                </h3>
                                
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
                            <div class="border-b border-gray-200 pb-8">
                                <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                                    <i class="fas fa-map-marker-alt text-orange-500 mr-3 text-xl"></i>
                                    Property Location
                                </h3>
                                
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
                            <div class="border-b border-gray-200 pb-8">
                                <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                                    <i class="fas fa-file-upload text-red-500 mr-3 text-xl"></i>
                                    Update Documents (Optional)
                                </h3>
                                
                                <div class="space-y-6">
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                                        <h4 class="font-semibold text-yellow-800 mb-2 flex items-center text-sm">
                                            <i class="fas fa-exclamation-triangle mr-2 text-sm"></i>
                                            Important Notes:
                                        </h4>
                                        <ul class="text-yellow-700 text-xs space-y-1 ml-4 list-disc">
                                            <li>All documents must be clear and readable images</li>
                                            <li>Accepted formats: JPG, JPEG, PNG only</li>
                                            <li>Maximum file size: 5MB per file</li>
                                            <li>Make sure documents are not expired</li>
                                        </ul>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                <?php echo $doc_info['label']; ?>
                                            </label>
                                            
                                            <?php if ($has_existing): ?>
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
                                                               class="px-2 py-1 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 flex items-center transition-colors">
                                                                <i class="fas fa-eye mr-1 text-xs"></i> View
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="file-upload-small"
                                                 onclick="document.getElementById('<?php echo $field_name; ?>').click()">
                                                <input type="file" 
                                                       name="<?php echo $field_name; ?>" 
                                                       accept=".jpg,.jpeg,.png"
                                                       class="hidden" 
                                                       id="<?php echo $field_name; ?>"
                                                       onchange="showFileName(this, '<?php echo $field_name; ?>_filename')">
                                                
                                                <div class="flex items-center">
                                                    <i class="fas fa-cloud-upload-alt text-gray-400 mr-3 text-lg"></i>
                                                    <div class="flex-1">
                                                        <div class="text-xs text-gray-600">Click to upload new file</div>
                                                        <div class="text-xs text-gray-500">JPG, JPEG, PNG up to 5MB</div>
                                                    </div>
                                                </div>
                                                
                                                <div id="<?php echo $field_name; ?>_filename" class="mt-2"></div>
                                            </div>
                                            
                                            <p class="text-xs text-gray-500"><?php echo $doc_info['description']; ?></p>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Building Information Section -->
                            <div class="pb-8">
                                <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                                    <i class="fas fa-building text-purple-500 mr-3 text-xl"></i>
                                    Building Information
                                </h3>
                                
                                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                                    <label class="block text-sm font-medium text-gray-700 mb-4">
                                        Does this property have any buildings? <span class="text-red-500">*</span>
                                    </label>
                                    
                                    <div class="flex space-x-8">
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
                            <div class="flex justify-end space-x-4">
                                <a href="?" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                    Cancel
                                </a>
                                <button type="submit" 
                                    class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-8 rounded-xl transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Submit Corrections
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- VIEW MODE - UPDATED WITH ALL DATA LIKE pending.php -->
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                        <!-- Header -->
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="font-semibold text-gray-900"><?php echo $app['reference_number']; ?></span>
                                        <span class="status-badge status-needs-correction">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>Needs Correction
                                        </span>
                                    </div>
                                    <div class="text-gray-600 text-sm">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        <?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?>
                                    </div>
                                </div>
                                <div class="text-gray-600 text-sm">
                                    Submitted: <?php echo date('M j, Y', strtotime($app['submitted_date'])); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
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

                        <!-- Correction Notes -->
                        <?php if (!empty($app['correction_notes'])): ?>
                            <div class="p-6 bg-yellow-50 border-b border-yellow-200">
                                <div class="flex">
                                    <div class="mr-3 text-yellow-500">
                                        <i class="fas fa-comment-dots"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 mb-1">Assessor's Notes:</div>
                                        <div class="text-sm text-gray-700"><?php echo htmlspecialchars($app['correction_notes']); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Information Sections -->
                        <div class="p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <!-- Applicant Info - UPDATED WITH ALL FIELDS -->
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
                                            <!-- ADDED: Gender field -->
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1">Gender</div>
                                                <div class="text-sm font-medium"><?php echo ucfirst($app['sex'] ?? 'Not specified'); ?></div>
                                            </div>
                                            <!-- ADDED: Marital Status field -->
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1">Marital Status</div>
                                                <div class="text-sm font-medium"><?php echo ucfirst($app['marital_status'] ?? 'Not specified'); ?></div>
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
                                                if (!empty($app['owner_barangay'])) $address_parts[] = 'Brgy. ' . $app['owner_barangay'];
                                                if (!empty($app['owner_district'])) $address_parts[] = 'Dist. ' . $app['owner_district'];
                                                if (!empty($app['owner_city'])) $address_parts[] = $app['owner_city'];
                                                echo implode(', ', $address_parts);
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Property Info - UPDATED WITH ALL FIELDS -->
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
                                            <!-- ADDED: Province field -->
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1">Province</div>
                                                <div class="text-sm font-medium"><?php echo $app['province']; ?></div>
                                            </div>
                                            <!-- ADDED: Zip Code field -->
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
                            
                            <!-- Current Documents -->
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
                            
                            <!-- Action Button -->
                            <div class="mt-8 pt-8 border-t border-gray-200 text-center">
                                <a href="?edit=<?php echo $app['id']; ?>" 
                                   class="inline-flex items-center px-8 py-3 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white rounded-lg font-semibold shadow-md hover:shadow-lg transition-all duration-300">
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
                <div class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded px-2 py-1 text-xs">
                    <div class="flex items-center truncate">
                        <i class="fas fa-file-image text-blue-500 mr-1 text-xs"></i>
                        <span class="text-blue-700 truncate" title="${file.name}">${shortName}</span>
                    </div>
                    <div class="flex items-center space-x-2 ml-2">
                        <span class="text-xs text-blue-500 text-xs">${fileSize}</span>
                        <button type="button" onclick="removeFile(this, '${input.id}', '${displayId}')" 
                                class="text-blue-400 hover:text-red-500 transition-colors text-xs">
                            <i class="fas fa-times text-xs"></i>
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

// Add drag and drop functionality
document.addEventListener('DOMContentLoaded', function() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        const dropzone = document.getElementById(input.id + '_filename')?.parentElement;
        
        if (!dropzone) return;
        
        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('border-blue-500', 'bg-blue-50');
        });
        
        dropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('border-blue-500', 'bg-blue-50');
        });
        
        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('border-blue-500', 'bg-blue-50');
            
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                const event = new Event('change', { bubbles: true });
                input.dispatchEvent(event);
            }
        });
    });
    
    // Style radio buttons
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
    });
});
</script>
</body>
</html>