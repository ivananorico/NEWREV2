<?php
// revenue2/citizen_dashboard/rpt/rpt_application/rpt_application.php
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
        
        // Verify ownership and status (ONLY allow editing if status is 'needs_correction')
        $check_owner = $pdo->prepare("
            SELECT pr.id, pr.status 
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
                birthdate = ?, updated_at = NOW()
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
        
        // Update property registration - change status to 'resubmitted' and clear correction notes
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
        
        // Handle file uploads (only if new files are provided)
        $document_types = [
            'barangay_certificate' => 'Barangay Certificate',
            'ownership_proof' => 'Proof of Ownership', 
            'valid_id' => 'Valid ID',
            'survey_plan' => 'Survey Plan'
        ];
        
        // Get reference number for folder naming
        $ref_stmt = $pdo->prepare("SELECT reference_number FROM property_registrations WHERE id = ?");
        $ref_stmt->execute([$registration_id]);
        $ref_data = $ref_stmt->fetch();
        $reference_number = $ref_data['reference_number'];
        
        // Create upload directory if it doesn't exist
        $upload_dir = '../../../documents/rpt/applications/' . $reference_number . '/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($document_types as $field_name => $doc_name) {
            if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] == UPLOAD_ERR_OK) {
                $file = $_FILES[$field_name];
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                $file_type = mime_content_type($file['tmp_name']);
                
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("$doc_name must be an image (JPG, JPEG, PNG) or PDF");
                }
                
                // Validate file size
                $max_size = 5 * 1024 * 1024; // 5MB
                if ($file['size'] > $max_size) {
                    throw new Exception("$doc_name is too large. Maximum size is 5MB");
                }
                
                // Get current document info if exists
                $check_stmt = $pdo->prepare("
                    SELECT id, file_path, file_name 
                    FROM property_documents 
                    WHERE registration_id = ? AND document_type = ?
                    LIMIT 1
                ");
                $check_stmt->execute([$registration_id, $field_name]);
                $existing_doc = $check_stmt->fetch();
                
                if ($existing_doc) {
                    // UPDATE existing document - overwrite the same file
                    $old_file_path = '../../../' . $existing_doc['file_path'];
                    
                    // Delete old file if exists
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                    
                    // Use the same filename format
                    $file_ext = pathinfo($existing_doc['file_name'], PATHINFO_EXTENSION);
                    if (empty($file_ext)) {
                        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    }
                    
                    $new_filename = $field_name . '.' . $file_ext;
                    $file_path = $upload_dir . $new_filename;
                    $relative_path = 'documents/rpt/applications/' . $reference_number . '/' . $new_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // UPDATE existing record
                        $doc_stmt = $pdo->prepare("
                            UPDATE property_documents 
                            SET file_name = ?, 
                                file_path = ?, 
                                file_size = ?, 
                                file_type = ?, 
                                uploaded_by = ?, 
                                created_at = NOW(),
                                updated_at = NOW()
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
                    }
                } else {
                    // INSERT new document
                    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $new_filename = $field_name . '.' . $file_ext;
                    $file_path = $upload_dir . $new_filename;
                    $relative_path = 'documents/rpt/applications/' . $reference_number . '/' . $new_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // INSERT new record
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
        
        $message = "✅ Application updated and resubmitted successfully!<br>
                   <strong>Status:</strong> Resubmitted<br>
                   <strong>Next:</strong> Wait for assessor review";
        $message_type = 'success';
        
        // Redirect to avoid form resubmission
        header("Location: rpt_application.php?success=1&ref=" . $registration_id);
        exit();
        
    } catch(Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Fetch user's applications
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
            DATE(pr.created_at) as application_date,
            DATE(pr.updated_at) as last_updated,
            
            -- Owner details
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
            
            -- Inspection details
            pi.scheduled_date as inspection_date,
            pi.assessor_name,
            pi.status as inspection_status,
            pi.updated_at as inspection_date_completed,
            
            -- Land properties if assessed
            lp.tdn as land_tdn,
            lp.land_area_sqm,
            lp.land_market_value,
            lp.land_assessed_value,
            lp.annual_tax as land_annual_tax,
            
            -- Building properties if assessed
            bp.tdn as building_tdn,
            bp.floor_area_sqm,
            bp.building_market_value,
            bp.building_assessed_value,
            bp.annual_tax as building_annual_tax,
            
            -- Property totals
            pt.total_annual_tax,
            pt.approval_date
            
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        LEFT JOIN property_inspections pi ON pi.registration_id = pr.id
        LEFT JOIN land_properties lp ON lp.registration_id = pr.id
        LEFT JOIN building_properties bp ON bp.land_id = lp.id
        LEFT JOIN property_totals pt ON pt.registration_id = pr.id
        WHERE po.user_id = ?
        ORDER BY 
            CASE pr.status
                WHEN 'needs_correction' THEN 1
                WHEN 'pending' THEN 2
                WHEN 'resubmitted' THEN 3
                WHEN 'for_inspection' THEN 4
                WHEN 'assessed' THEN 5
                WHEN 'approved' THEN 6
                ELSE 7
            END,
            pr.created_at DESC
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
    
    // Find the application and verify it's editable
    foreach ($applications as $app) {
        if ($app['id'] == $edit_id && $app['status'] == 'needs_correction') {
            $edit_mode = true;
            $edit_data = $app;
            $editing_id = $edit_id;
            
            // Fetch existing documents for this application
            try {
                $doc_stmt = $pdo->prepare("
                    SELECT document_type, file_name, file_path, created_at 
                    FROM property_documents 
                    WHERE registration_id = ? 
                    ORDER BY created_at DESC
                ");
                $doc_stmt->execute([$edit_id]);
                $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Organize documents by type (will show latest due to ORDER BY created_at DESC)
                $documents_by_type = [];
                foreach ($documents as $doc) {
                    $documents_by_type[$doc['document_type']] = $doc;
                }
                $edit_documents = $documents_by_type;
                
            } catch(PDOException $e) {
                // Silently fail for documents
            }
            break;
        }
    }
}

// Success message from redirect
if (isset($_GET['success'])) {
    $message = "✅ Application updated and resubmitted successfully!<br>
               <strong>Status:</strong> Resubmitted<br>
               <strong>Next:</strong> Wait for assessor review";
    $message_type = 'success';
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
    <title>My Applications - RPT Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; border: 1px solid #fbbf24; }
        .status-needs_correction { background-color: #ffedd5; color: #9a3412; border: 1px solid #fb923c; }
        .status-resubmitted { background-color: #f0e7ff; color: #6b21a8; border: 1px solid #a855f7; }
        .status-for_inspection { background-color: #dbeafe; color: #1e40af; border: 1px solid #60a5fa; }
        .status-assessed { background-color: #e0e7ff; color: #3730a3; border: 1px solid #818cf8; }
        .status-approved { background-color: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        
        /* Modal for viewing images */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
        }
        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90vh;
        }
        .modal-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
        }
        
        .image-thumbnail {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .image-thumbnail:hover {
            transform: scale(1.02);
        }
        
        /* Custom file input */
        .custom-file-input {
            border: 2px dashed #d1d5db;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .custom-file-input:hover {
            border-color: #3b82f6;
            background-color: #f8fafc;
        }
        .custom-file-input.has-file {
            border-color: #10b981;
            background-color: #f0fdf4;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../../navbar.php'; ?>
    
    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <span class="modal-close">&times;</span>
        <img class="modal-content" id="modalImage">
        <div id="modalCaption" class="absolute bottom-0 left:0 right:0 bg-black bg-opacity-70 text-white p-4 text-center"></div>
    </div>
    
    <main class="container mx-auto px-4 py-8">
        <!-- Page Header -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="flex items-center mb-4">
                <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">My Property Applications</h1>
                    <p class="text-gray-600">View, track, and manage your RPT applications</p>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
                <?php
                // Count statuses
                $stats = [
                    'total' => $total_applications,
                    'pending' => 0,
                    'needs_correction' => 0,
                    'approved' => 0
                ];
                
                foreach ($applications as $app) {
                    if (in_array($app['status'], ['pending', 'for_inspection', 'resubmitted'])) {
                        $stats['pending']++;
                    } elseif ($app['status'] == 'needs_correction') {
                        $stats['needs_correction']++;
                    } elseif ($app['status'] == 'approved') {
                        $stats['approved']++;
                    }
                }
                ?>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-file-alt text-blue-500 text-2xl mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-600">Total</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-clock text-yellow-500 text-2xl mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-600">Pending</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['pending']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-orange-500 text-2xl mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-600">Needs Correction</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['needs_correction']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 text-2xl mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-600">Approved</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['approved']; ?></p>
                        </div>
                    </div>
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

        <?php if (isset($error_message)): ?>
            <div class="mb-6 p-4 bg-red-100 text-red-700 rounded-lg border border-red-300">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($total_applications === 0): ?>
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <div class="text-gray-400 mb-6">
                    <i class="fas fa-file-alt text-6xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-700 mb-3">No Applications Found</h3>
                <p class="text-gray-600 mb-6">You haven't submitted any property applications yet.</p>
                <a href="../rpt_registration/rpt_registration.php" 
                   class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300">
                    <i class="fas fa-plus mr-2"></i>
                    Submit New Application
                </a>
            </div>
        <?php else: ?>
            <!-- Applications List -->
            <div class="space-y-6">
                <?php foreach ($applications as $app): ?>
                    <?php
                    $status = $app['status'];
                    $status_class = "status-$status";
                    
                    // Status labels
                    $status_labels = [
                        'pending' => 'Pending Review',
                        'for_inspection' => 'For Inspection',
                        'needs_correction' => 'Needs Correction',
                        'resubmitted' => 'Resubmitted',
                        'assessed' => 'Assessed',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected'
                    ];
                    $status_label = $status_labels[$status] ?? ucfirst($status);
                    
                    // Check if this is the one being edited
                    $is_editing = $edit_mode && $editing_id == $app['id'];
                    
                    // Construct full name
                    $full_name = trim($app['first_name'] . ' ' . 
                        (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . 
                        $app['last_name'] . 
                        (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
                    ?>
                    
                    <!-- Application Card -->
                    <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200">
                        <!-- Application Header -->
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <span class="text-lg font-bold text-gray-800 mr-3">
                                            <?php echo $app['reference_number']; ?>
                                        </span>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </div>
                                    <p class="text-gray-700">
                                        <i class="fas fa-map-marker-alt text-gray-400 mr-2"></i>
                                        <?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?>
                                    </p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        <i class="far fa-calendar mr-1"></i>
                                        Applied: <?php echo date('F j, Y', strtotime($app['application_date'])); ?>
                                        <?php if ($app['last_updated'] != $app['application_date']): ?>
                                            • Updated: <?php echo date('F j, Y', strtotime($app['last_updated'])); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <!-- Edit Button (Only for needs_correction status) -->
                                    <?php if ($status == 'needs_correction' && !$is_editing): ?>
                                        <a href="?edit=<?php echo $app['id']; ?>" 
                                           class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium px-4 py-2 rounded-lg transition-colors flex items-center">
                                            <i class="fas fa-edit mr-2"></i>
                                            Edit & Resubmit
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Cancel Edit Button -->
                                    <?php if ($is_editing): ?>
                                        <a href="?" 
                                           class="bg-gray-500 hover:bg-gray-600 text-white font-medium px-4 py-2 rounded-lg transition-colors flex items-center">
                                            <i class="fas fa-times mr-2"></i>
                                            Cancel Edit
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Pay Tax Button (Only for approved) -->
                                    <?php if ($status == 'approved'): ?>
                                        <a href="../rpt_tax_payment/rpt_tax_payment.php?registration_id=<?php echo $app['id']; ?>" 
                                           class="bg-green-500 hover:bg-green-600 text-white font-medium px-4 py-2 rounded-lg transition-colors flex items-center">
                                            <i class="fas fa-credit-card mr-2"></i>
                                            Pay Taxes
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- EDIT FORM (Only shows when editing needs_correction application) -->
                        <?php if ($is_editing): ?>
                            <div class="p-6 bg-yellow-50 border-t border-yellow-200">
                                <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                                    <i class="fas fa-edit text-yellow-600 mr-2"></i>
                                    Edit Application - <?php echo $app['reference_number']; ?>
                                </h3>
                                
                                <!-- Correction Notes -->
                                <?php if (!empty($app['correction_notes'])): ?>
                                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                                        <h4 class="font-semibold text-red-800 mb-2 flex items-center">
                                            <i class="fas fa-exclamation-circle mr-2"></i>
                                            Assessor's Notes:
                                        </h4>
                                        <p class="text-red-700 whitespace-pre-line"><?php echo htmlspecialchars($app['correction_notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" enctype="multipart/form-data" class="space-y-6" id="editForm">
                                    <input type="hidden" name="edit_application" value="1">
                                    <input type="hidden" name="registration_id" value="<?php echo $app['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                                    
                                    <!-- Personal Information Section -->
                                    <div class="border-b border-gray-300 pb-6">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                            <i class="fas fa-user text-blue-500 mr-2"></i>
                                            Personal Information
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                                                <input type="text" name="first_name" required 
                                                    value="<?php echo htmlspecialchars($edit_data['first_name']); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                                                <input type="text" name="last_name" required 
                                                    value="<?php echo htmlspecialchars($edit_data['last_name']); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                                <input type="text" name="middle_name"
                                                    value="<?php echo htmlspecialchars($edit_data['middle_name'] ?? ''); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                                                <select name="suffix" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                                    <option value="">Select Suffix</option>
                                                    <option value="Jr." <?php echo ($edit_data['suffix'] ?? '') == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                                    <option value="Sr." <?php echo ($edit_data['suffix'] ?? '') == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                                    <option value="II" <?php echo ($edit_data['suffix'] ?? '') == 'II' ? 'selected' : ''; ?>>II</option>
                                                    <option value="III" <?php echo ($edit_data['suffix'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate</label>
                                                <input type="date" name="birthdate"
                                                    value="<?php echo htmlspecialchars($edit_data['birthdate'] ?? ''); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                                <input type="email" name="email" required 
                                                    value="<?php echo htmlspecialchars($edit_data['email']); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                                                <input type="text" name="phone" required 
                                                    value="<?php echo htmlspecialchars($edit_data['phone']); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">TIN Number</label>
                                                <input type="text" name="tin_number" 
                                                    value="<?php echo htmlspecialchars($edit_data['tin_number'] ?? ''); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                            </div>
                                        </div>
                                        
                                        <!-- Address Fields -->
                                        <div class="mt-6">
                                            <h5 class="text-md font-semibold text-gray-700 mb-3">Home Address</h5>
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">House Number *</label>
                                                    <input type="text" name="house_number" required 
                                                        value="<?php echo htmlspecialchars($edit_data['house_number'] ?? ''); ?>"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Street *</label>
                                                    <input type="text" name="street" required 
                                                        value="<?php echo htmlspecialchars($edit_data['street'] ?? ''); ?>"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Barangay *</label>
                                                    <input type="text" name="barangay" required 
                                                        value="<?php echo htmlspecialchars($edit_data['owner_barangay'] ?? ''); ?>"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">District *</label>
                                                    <select name="district" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
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
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Province *</label>
                                                    <input type="text" name="province" value="Metro Manila" required readonly
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code *</label>
                                                    <input type="text" name="zip_code" required 
                                                        value="<?php echo htmlspecialchars($edit_data['owner_zip_code'] ?? ''); ?>"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Property Information Section -->
                                    <div class="border-b border-gray-300 pb-6">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                            <i class="fas fa-map-marker-alt text-green-500 mr-2"></i>
                                            Property Location
                                        </h4>
                                        <div class="space-y-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Lot Location *</label>
                                                <input type="text" name="property_lot_location" required 
                                                    value="<?php echo htmlspecialchars($edit_data['lot_location']); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Property Barangay *</label>
                                                    <input type="text" name="property_barangay" required 
                                                        value="<?php echo htmlspecialchars($edit_data['barangay']); ?>"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Property District *</label>
                                                    <select name="property_district" required 
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                                        <option value="">Select District</option>
                                                        <?php for ($i = 1; $i <= 6; $i++): ?>
                                                            <option value="<?php echo $i; ?>" <?php echo ($edit_data['district'] ?? '') == $i ? 'selected' : ''; ?>>
                                                                District <?php echo $i; ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Does the property have a building? *</label>
                                                <select name="has_building" required 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                                    <option value="0" <?php echo ($edit_data['has_building'] == 0) ? 'selected' : ''; ?>>No</option>
                                                    <option value="1" <?php echo ($edit_data['has_building'] == 1) ? 'selected' : ''; ?>>Yes</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Documents Section -->
                                    <!-- Upload new documents -->
<div class="space-y-6">
    <?php 
    $document_fields = [
        'barangay_certificate' => 'Barangay Certificate',
        'ownership_proof' => 'Proof of Ownership',
        'valid_id' => 'Valid ID',
        'survey_plan' => 'Survey Plan'
    ];
    
    foreach ($document_fields as $field => $label):
        $has_current = isset($edit_documents[$field]);
    ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <?php echo $label; ?>
                <?php if ($has_current): ?>
                    <span class="text-xs text-green-600 ml-2">(Current file will be replaced)</span>
                <?php endif; ?>
            </label>
            
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-500 transition-colors">
                <input type="file" 
                       name="<?php echo $field; ?>" 
                       id="<?php echo $field; ?>"
                       accept=".jpg,.jpeg,.png,.pdf" 
                       class="hidden"
                       onchange="previewFile(this, '<?php echo $field; ?>_preview')">
                <label for="<?php echo $field; ?>" class="cursor-pointer block">
                    <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                    <p class="text-sm text-gray-600 mb-1">Click to upload new <?php echo strtolower($label); ?></p>
                    <p class="text-xs text-gray-500">JPG, PNG, PDF up to 5MB</p>
                </label>
                <div id="<?php echo $field; ?>_preview" class="mt-2">
                    <?php if ($has_current): ?>
                        <div class="text-sm text-blue-600 bg-blue-50 p-2 rounded border border-blue-200">
                            <i class="fas fa-file mr-1"></i>
                            Current: <?php echo htmlspecialchars($edit_documents[$field]['file_name']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                <i class="fas fa-info-circle mr-1"></i>
                Will <?php echo $has_current ? 'replace' : 'upload'; ?> existing document
            </p>
        </div>
    <?php endforeach; ?>
</div>
                                    </div>
                                    
                                    <!-- Form Actions -->
                                    <div class="flex justify-end space-x-4 pt-4">
                                        <a href="?" class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                            Cancel
                                        </a>
                                        <button type="submit" 
                                                class="px-6 py-3 bg-yellow-600 hover:bg-yellow-700 text-white font-medium rounded-lg transition-colors flex items-center">
                                            <i class="fas fa-paper-plane mr-2"></i>
                                            Submit Corrections & Replace Documents
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Application Details (Always Visible) -->
                        <div class="p-6 bg-gray-50 border-t border-gray-200">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <!-- Personal Information -->
                                <div>
                                    <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-user-circle text-blue-500 mr-2"></i>
                                        Applicant Information
                                    </h4>
                                    <div class="space-y-3">
                                        <div class="flex">
                                            <span class="w-1/3 text-gray-600 font-medium">Full Name:</span>
                                            <span class="w-2/3 text-gray-800"><?php echo $full_name; ?></span>
                                        </div>
                                        <div class="flex">
                                            <span class="w-1/3 text-gray-600 font-medium">Email:</span>
                                            <span class="w-2/3 text-gray-800"><?php echo $app['email']; ?></span>
                                        </div>
                                        <div class="flex">
                                            <span class="w-1/3 text-gray-600 font-medium">Phone:</span>
                                            <span class="w-2/3 text-gray-800"><?php echo $app['phone']; ?></span>
                                        </div>
                                        <?php if (!empty($app['tin_number'])): ?>
                                            <div class="flex">
                                                <span class="w-1/3 text-gray-600 font-medium">TIN:</span>
                                                <span class="w-2/3 text-gray-800"><?php echo $app['tin_number']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($app['birthdate'])): ?>
                                            <div class="flex">
                                                <span class="w-1/3 text-gray-600 font-medium">Birthdate:</span>
                                                <span class="w-2/3 text-gray-800"><?php echo date('F j, Y', strtotime($app['birthdate'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex">
                                            <span class="w-1/3 text-gray-600 font-medium">Address:</span>
                                            <span class="w-2/3 text-gray-800">
                                                <?php 
                                                $address_parts = [];
                                                if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                                                if (!empty($app['street'])) $address_parts[] = $app['street'];
                                                if (!empty($app['owner_barangay'])) $address_parts[] = 'Brgy. ' . $app['owner_barangay'];
                                                if (!empty($app['owner_district'])) $address_parts[] = 'Dist. ' . $app['owner_district'];
                                                if (!empty($app['owner_city'])) $address_parts[] = $app['owner_city'];
                                                if (!empty($app['owner_zip_code'])) $address_parts[] = $app['owner_zip_code'];
                                                echo implode(', ', $address_parts);
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Correction Notes (if any) -->
                                    <?php if (!empty($app['correction_notes'])): ?>
                                        <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                                            <h5 class="font-semibold text-red-800 mb-2 flex items-center">
                                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                                Correction Required
                                            </h5>
                                            <p class="text-red-700 whitespace-pre-line"><?php echo htmlspecialchars($app['correction_notes']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Property Information -->
                                <div>
                                    <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-map-marker-alt text-green-500 mr-2"></i>
                                        Property Information
                                    </h4>
                                    <div class="space-y-3">
                                        <div class="flex">
                                            <span class="w-1/3 text-gray-600 font-medium">Lot Location:</span>
                                            <span class="w-2/3 text-gray-800"><?php echo $app['lot_location']; ?></span>
                                        </div>
                                        <div class="flex">
                                            <span class="w-1/3 text-gray-600 font-medium">Barangay:</span>
                                            <span class="w-2/3 text-gray-800">Brgy. <?php echo $app['barangay']; ?></span>
                                        </div>
                                        <div class="flex">
                                            <span class="w-1/3 text-gray-600 font-medium">District:</span>
                                            <span class="w-2/3 text-gray-800"><?php echo $app['district']; ?></span>
                                        </div>
                                        <div class="flex">
                                            <span class="w-1/3 text-gray-600 font-medium">City:</span>
                                            <span class="w-2/3 text-gray-800"><?php echo $app['city']; ?></span>
                                        </div>
                                        <div class="flex">
                                            <span class="w-1/3 text-gray-600 font-medium">Has Building:</span>
                                            <span class="w-2/3 text-gray-800">
                                                <?php echo $app['has_building'] ? 'Yes' : 'No'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Inspection Information -->
                                    <?php if (!empty($app['inspection_date'])): ?>
                                        <div class="mt-6">
                                            <h5 class="text-md font-bold text-gray-800 mb-3 flex items-center">
                                                <i class="fas fa-clipboard-check text-purple-500 mr-2"></i>
                                                Inspection Details
                                            </h5>
                                            <div class="space-y-2">
                                                <div class="flex">
                                                    <span class="w-1/3 text-gray-600 font-medium">Scheduled Date:</span>
                                                    <span class="w-2/3 text-gray-800">
                                                        <?php echo date('F j, Y', strtotime($app['inspection_date'])); ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($app['assessor_name'])): ?>
                                                    <div class="flex">
                                                        <span class="w-1/3 text-gray-600 font-medium">Assessor:</span>
                                                        <span class="w-2/3 text-gray-800"><?php echo $app['assessor_name']; ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($app['inspection_status'])): ?>
                                                    <div class="flex">
                                                        <span class="w-1/3 text-gray-600 font-medium">Status:</span>
                                                        <span class="w-2/3">
                                                            <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                                                <?php echo $app['inspection_status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                                <?php echo ucfirst($app['inspection_status']); ?>
                                                            </span>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Tax Assessment Information -->
                                    <?php if ($status == 'assessed' || $status == 'approved'): ?>
                                        <div class="mt-6">
                                            <h5 class="text-md font-bold text-gray-800 mb-3 flex items-center">
                                                <i class="fas fa-chart-line text-indigo-500 mr-2"></i>
                                                Tax Assessment
                                            </h5>
                                            <div class="space-y-4">
                                                <?php if (!empty($app['land_tdn'])): ?>
                                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                                        <h6 class="font-semibold text-gray-700 mb-2">Land</h6>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div>
                                                                <span class="text-sm text-gray-600">TDN:</span>
                                                                <p class="font-medium"><?php echo $app['land_tdn']; ?></p>
                                                            </div>
                                                            <div>
                                                                <span class="text-sm text-gray-600">Area:</span>
                                                                <p><?php echo number_format($app['land_area_sqm'] ?? 0, 2); ?> sqm</p>
                                                            </div>
                                                            <div>
                                                                <span class="text-sm text-gray-600">Assessed Value:</span>
                                                                <p class="font-medium text-blue-600">₱<?php echo number_format($app['land_assessed_value'] ?? 0, 2); ?></p>
                                                            </div>
                                                            <div>
                                                                <span class="text-sm text-gray-600">Annual Tax:</span>
                                                                <p class="font-bold text-red-600">₱<?php echo number_format($app['land_annual_tax'] ?? 0, 2); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($app['building_tdn'])): ?>
                                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                                        <h6 class="font-semibold text-gray-700 mb-2">Building</h6>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div>
                                                                <span class="text-sm text-gray-600">TDN:</span>
                                                                <p class="font-medium"><?php echo $app['building_tdn']; ?></p>
                                                            </div>
                                                            <div>
                                                                <span class="text-sm text-gray-600">Floor Area:</span>
                                                                <p><?php echo number_format($app['floor_area_sqm'] ?? 0, 2); ?> sqm</p>
                                                            </div>
                                                            <div>
                                                                <span class="text-sm text-gray-600">Assessed Value:</span>
                                                                <p class="font-medium text-blue-600">₱<?php echo number_format($app['building_assessed_value'] ?? 0, 2); ?></p>
                                                            </div>
                                                            <div>
                                                                <span class="text-sm text-gray-600">Annual Tax:</span>
                                                                <p class="font-bold text-red-600">₱<?php echo number_format($app['building_annual_tax'] ?? 0, 2); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($app['total_annual_tax'])): ?>
                                                    <div class="bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-lg p-4">
                                                        <div class="flex justify-between items-center">
                                                            <div>
                                                                <h6 class="font-bold text-gray-800">Total Annual Tax</h6>
                                                                <p class="text-sm text-gray-600">To be paid yearly</p>
                                                            </div>
                                                            <div class="text-right">
                                                                <p class="text-2xl font-bold text-red-600">
                                                                    ₱<?php echo number_format($app['total_annual_tax'], 2); ?>
                                                                </p>
                                                                <?php if ($status == 'approved'): ?>
                                                                    <p class="text-sm text-green-600">
                                                                        <i class="fas fa-check-circle mr-1"></i>
                                                                        Approved on <?php echo date('F j, Y', strtotime($app['approval_date'])); ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
    // Image Modal
    const modal = document.getElementById("imageModal");
    const modalImg = document.getElementById("modalImage");
    const modalCaption = document.getElementById("modalCaption");
    
    function viewDocument(src, caption) {
        modal.style.display = "block";
        // Add base path for proper document access
        modalImg.src = '../../../../../' + src;
        modalCaption.innerHTML = caption || "";
    }
    
    // Close modal
    document.querySelector(".modal-close").onclick = function() {
        modal.style.display = "none";
    }
    
    modal.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    }
    
    // Update filename display for file inputs
        // File preview for edit form
    function previewFile(input, previewId) {
        const preview = document.getElementById(previewId);
        const file = input.files[0];
        
        if (file) {
            // Clear previous preview
            preview.innerHTML = '';
            
            // Check file type
            if (file.type.startsWith('image/') || file.type === 'application/pdf') {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    let previewContent = '';
                    
                    if (file.type.startsWith('image/')) {
                        previewContent = `
                            <img src="${e.target.result}" class="max-w-full h-32 object-contain border rounded">
                            <div class="text-xs text-gray-600 mt-1">
                                <i class="fas fa-file-image mr-1"></i> ${file.name} (${(file.size/1024/1024).toFixed(2)}MB)
                            </div>
                        `;
                    } else if (file.type === 'application/pdf') {
                        previewContent = `
                            <div class="bg-red-100 p-3 rounded border border-red-200">
                                <i class="fas fa-file-pdf text-red-500 text-2xl mb-2"></i>
                                <div class="text-xs text-gray-600">
                                    <i class="fas fa-file-pdf mr-1"></i> ${file.name} (${(file.size/1024/1024).toFixed(2)}MB)
                                </div>
                            </div>
                        `;
                    }
                    
                    preview.innerHTML = previewContent;
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = `<div class="text-red-500 text-sm"><i class="fas fa-exclamation-circle mr-1"></i> Please upload an image or PDF file</div>`;
                input.value = ''; // Clear the input
            }
        }
    }
    
    // Add drag and drop functionality
    document.addEventListener('DOMContentLoaded', function() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        
        fileInputs.forEach(input => {
            const parentDiv = input.parentElement;
            
            // Hover effect
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
    
    // Form validation
    document.getElementById('editForm')?.addEventListener('submit', function(e) {
        // Allow submission even if not all documents are uploaded
        // The PHP code will handle missing files gracefully
        return true;
    });
    </script>
</body>
</html>