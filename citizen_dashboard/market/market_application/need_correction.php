<?php
// revenue2/citizen_dashboard/market/market_application/need_correction.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Citizen';

// Include database connections
include_once '../../../db/Market/market_db.php';
include_once '../../../Login/config/database.php'; // Users database

// Get users database connection
$usersDb = new Database();
$pdo_users = $usersDb->getConnection();

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
            SELECT rr.id, rr.application_status, rr.stall_rights_no, rr.stall_id, rr.map_id
            FROM rental_registration rr
            WHERE rr.id = ? AND rr.user_id = ? AND rr.application_status = 'need_correction'
        ");
        $check_owner->execute([$registration_id, $user_id]);
        $application = $check_owner->fetch();
        
        if (!$application) {
            throw new Exception("You cannot edit this application. It may not be in 'Need Correction' status or you don't own it.");
        }
        
        $pdo->beginTransaction();
        
        // Update renter_owner information
        $owner_stmt = $pdo->prepare("
            UPDATE renter_owner 
            SET first_name = ?, last_name = ?, middle_name = ?, suffix = ?,
                email = ?, mobile = ?, telephone = ?, 
                house_number = ?, street = ?, barangay = ?,
                city = ?, province = ?, zip_code = ?,
                birth_date = ?, gender = ?,
                emergency_name = ?, emergency_contact = ?,
                updated_at = CURRENT_TIMESTAMP()
            WHERE registration_id = ?
        ");
        
        $owner_stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            !empty($_POST['middle_name']) ? $_POST['middle_name'] : null,
            !empty($_POST['suffix']) ? $_POST['suffix'] : null,
            $_POST['email'],
            $_POST['mobile'],
            !empty($_POST['telephone']) ? $_POST['telephone'] : null,
            $_POST['house_number'],
            $_POST['street'],
            $_POST['barangay'],
            $_POST['city'] ?? 'Quezon City',
            $_POST['province'] ?? 'Metro Manila',
            $_POST['zip_code'],
            $_POST['birthdate'],
            $_POST['gender'] ?? null,
            $_POST['emergency_name'] ?? null,
            $_POST['emergency_contact'] ?? null,
            $registration_id
        ]);
        
        // Update rent_stall table (business information)
        $rent_stmt = $pdo->prepare("
            UPDATE rent_stall 
            SET business_name = ?, business_type = ?, updated_at = CURRENT_TIMESTAMP()
            WHERE registration_id = ?
        ");
        
        $rent_stmt->execute([
            $_POST['business_name'],
            $_POST['business_type'] ?? null,
            $registration_id
        ]);
        
        // Update rental_registration status to resubmitted
        $reg_stmt = $pdo->prepare("
            UPDATE rental_registration 
            SET application_status = 'resubmitted', 
                correction_notes = NULL, 
                updated_at = CURRENT_TIMESTAMP()
            WHERE id = ?
        ");
        
        $reg_stmt->execute([$registration_id]);
        
        // Handle file uploads
        $document_types = [
            'barangay_clearance' => 'Barangay Clearance',
            'id_photo_2x2' => '2x2 ID Photo', 
            'valid_id' => 'Valid ID'
        ];
        
        $reference_number = $application['stall_rights_no'];
        $upload_dir = '../../../documents/market/' . date('Y') . '/' . date('m') . '/' . $reference_number . '/';
        
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
                
                $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $clean_ref = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $reference_number);
                $new_filename = $field_name . '_' . $clean_ref . '_' . time() . '.' . $file_ext;
                $file_path = $upload_dir . $new_filename;
                $relative_path = 'documents/market/' . date('Y') . '/' . date('m') . '/' . $reference_number . '/' . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    // Update the document path in rental_registration
                    $doc_stmt = $pdo->prepare("
                        UPDATE rental_registration 
                        SET $field_name = ?, updated_at = CURRENT_TIMESTAMP()
                        WHERE id = ?
                    ");
                    $doc_stmt->execute([$relative_path, $registration_id]);
                }
            }
        }
        
        $pdo->commit();
        
        // Redirect to resubmitted page
        header("Location: resubmitted.php?ref=" . urlencode($reference_number) . "&id=" . $registration_id);
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
            rr.id,
            rr.stall_rights_no,
            rr.stall_name,
            rr.stall_class,
            rr.stall_rights_amount,
            rr.monthly_rent,
            rr.security_bond,
            rr.total_amount_due,
            rr.correction_notes,
            rr.barangay_clearance,
            rr.id_photo_2x2,
            rr.valid_id,
            rr.application_status,  -- ADDED THIS LINE
            rr.created_at,
            rr.updated_at,
            
            s.name as stall_location,
            s.price as stall_price,
            sr.class_name,
            
            ro.first_name,
            ro.last_name,
            ro.middle_name,
            ro.suffix,
            ro.email,
            ro.mobile,
            ro.telephone,
            ro.house_number,
            ro.street,
            ro.barangay,
            ro.city,
            ro.province,
            ro.zip_code,
            ro.birth_date,
            ro.gender,
            ro.emergency_name,
            ro.emergency_contact,
            ro.renter_code,
            
            rs.business_name,
            rs.business_type
            
        FROM rental_registration rr
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
        WHERE rr.user_id = ? 
          AND rr.application_status = 'need_correction'
        ORDER BY rr.updated_at DESC
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
        if ($app['id'] == $edit_id && $app['application_status'] == 'need_correction') {
            $edit_mode = true;
            $edit_data = $app;
            $editing_id = $edit_id;
            
            // Get existing document paths
            $edit_documents = [
                'barangay_clearance' => $app['barangay_clearance'] ?? '',
                'id_photo_2x2' => $app['id_photo_2x2'] ?? '',
                'valid_id' => $app['valid_id'] ?? ''
            ];
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
    <title>Applications Needing Correction - Market Services</title>
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
        .document-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; text-align: center; transition: all 0.2s; background: white; }
        .document-card:hover { border-color: #3b82f6; transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); cursor: pointer; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); padding: 20px; }
        .modal-content { margin: auto; display: block; max-width: 90%; max-height: 90vh; border-radius: 8px; }
        .modal-close { position: absolute; top: 20px; right: 35px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 1001; }
        .modal-close:hover { color: #fbbf24; }
        .modal-caption { text-align: center; color: white; padding: 10px 20px; position: absolute; bottom: 0; width: 100%; background: rgba(0,0,0,0.7); }
        .urgent-alert { animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { background-color: #fee2e2; }
            50% { background-color: #fecaca; }
            100% { background-color: #fee2e2; }
        }
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
            <a href="../market_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Applications Needing Correction</h1>
                <p class="text-gray-600">Your stall rental applications need corrections before they can proceed</p>
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
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Corrections Needed!</h3>
            <p class="text-gray-600 mb-6">
                Great news! All your market stall applications are currently in good standing. 
                No corrections are required at this time.
            </p>
            <div class="space-y-4">
                <a href="../market_services.php" 
                   class="inline-flex items-center px-5 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Market Services
                </a>
                <div class="text-sm text-gray-500">
                    Check your application status or apply for a new stall
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($applications as $app): ?>
                <?php
                    $full_name = trim($app['first_name'] . ' ' . (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . $app['last_name'] . (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
                    $is_editing = $edit_mode && $editing_id == $app['id'];
                ?>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 <?php echo $is_editing ? 'urgent-alert' : ''; ?>">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-start">
                        <div>
                            <div class="flex items-center mb-3">
                                <span class="text-xl font-bold text-gray-900 mr-4"><?php echo $app['stall_rights_no']; ?></span>
                                <span class="status-badge status-needs-correction">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>Needs Correction
                                </span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-store mr-2"></i>
                                <span><?php echo $app['stall_name']; ?> (<?php echo $app['class_name']; ?>)</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Monthly Rent</div>
                            <div class="text-xl font-bold text-green-600">₱<?php echo number_format($app['monthly_rent'], 2); ?></div>
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
                                    <h4 class="font-semibold text-red-800 mb-1">Admin's Notes:</h4>
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
                                
                                <!-- Personal Information Section -->
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
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate *</label>
                                            <input type="date" name="birthdate" required
                                                value="<?php echo htmlspecialchars($edit_data['birth_date'] ?? ''); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                                            <select name="gender"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                <option value="">Select Gender</option>
                                                <option value="male" <?php echo ($edit_data['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="female" <?php echo ($edit_data['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                                                <option value="other" <?php echo ($edit_data['gender'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
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
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number *</label>
                                            <input type="text" name="mobile" required 
                                                value="<?php echo htmlspecialchars($edit_data['mobile']); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                placeholder="09171234567">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Telephone Number</label>
                                            <input type="text" name="telephone"
                                                value="<?php echo htmlspecialchars($edit_data['telephone'] ?? ''); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                                                <label class="block text-sm font-medium text-gray-700 mb-1">House Number</label>
                                                <input type="text" name="house_number"
                                                    value="<?php echo htmlspecialchars($edit_data['house_number'] ?? ''); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    placeholder="123">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Street</label>
                                                <input type="text" name="street"
                                                    value="<?php echo htmlspecialchars($edit_data['street'] ?? ''); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    placeholder="Main Street">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Barangay</label>
                                                <input type="text" name="barangay"
                                                    value="<?php echo htmlspecialchars($edit_data['barangay'] ?? ''); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    placeholder="Barangay Name">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">City *</label>
                                                <input type="text" name="city" value="<?php echo htmlspecialchars($edit_data['city'] ?? 'Quezon City'); ?>" required
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Province *</label>
                                                <input type="text" name="province" value="<?php echo htmlspecialchars($edit_data['province'] ?? 'Metro Manila'); ?>" required
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                                                <input type="text" name="zip_code"
                                                    value="<?php echo htmlspecialchars($edit_data['zip_code'] ?? ''); ?>"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    placeholder="1100">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Business Information Section -->
                                <div class="border-b border-gray-200 pb-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-briefcase text-green-500 mr-2"></i>
                                        Business Information
                                    </h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Business Name *</label>
                                            <input type="text" name="business_name" required
                                                value="<?php echo htmlspecialchars($edit_data['business_name'] ?? ''); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                                placeholder="Enter your business name">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Business Type</label>
                                            <select name="business_type"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                                <option value="">Select Business Type</option>
                                                <option value="food" <?php echo ($edit_data['business_type'] ?? '') == 'food' ? 'selected' : ''; ?>>Food & Groceries</option>
                                                <option value="clothing" <?php echo ($edit_data['business_type'] ?? '') == 'clothing' ? 'selected' : ''; ?>>Clothing & Apparel</option>
                                                <option value="electronics" <?php echo ($edit_data['business_type'] ?? '') == 'electronics' ? 'selected' : ''; ?>>Electronics</option>
                                                <option value="services" <?php echo ($edit_data['business_type'] ?? '') == 'services' ? 'selected' : ''; ?>>Services</option>
                                                <option value="others" <?php echo ($edit_data['business_type'] ?? '') == 'others' ? 'selected' : ''; ?>>Others</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Emergency Contact Section -->
                                <div class="border-b border-gray-200 pb-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-phone-alt text-red-500 mr-2"></i>
                                        Emergency Contact
                                    </h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Name</label>
                                            <input type="text" name="emergency_name"
                                                value="<?php echo htmlspecialchars($edit_data['emergency_name'] ?? ''); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Number</label>
                                            <input type="text" name="emergency_contact"
                                                value="<?php echo htmlspecialchars($edit_data['emergency_contact'] ?? ''); ?>"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                                placeholder="09171234567">
                                        </div>
                                    </div>
                                </div>

                                <!-- Documents Upload Section -->
                                <div class="border-b border-gray-200 pb-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-file-upload text-purple-500 mr-2"></i>
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
                                            <!-- Barangay Clearance -->
                                            <div class="space-y-1">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Barangay Clearance
                                                </label>
                                                
                                                <?php if (!empty($edit_documents['barangay_clearance'])): ?>
                                                    <?php $doc_url = getDocumentUrl($edit_documents['barangay_clearance']); ?>
                                                    <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                                        <div class="flex justify-between items-center mb-2">
                                                            <div>
                                                                <span class="font-medium text-sm text-blue-800">Current File:</span>
                                                                <div class="text-xs text-blue-600 truncate mt-1">
                                                                    Barangay Clearance
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
                                                           name="barangay_clearance" 
                                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                                           class="hidden" 
                                                           id="barangay_clearance"
                                                           onchange="showFileName(this, 'barangay_filename')">
                                                    <label for="barangay_clearance" class="cursor-pointer flex items-center">
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
                                                <p class="text-xs text-gray-500 mt-1">Clearance from your barangay of residence</p>
                                            </div>

                                            <!-- 2x2 ID Photo -->
                                            <div class="space-y-1">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    2x2 ID Photo
                                                </label>
                                                
                                                <?php if (!empty($edit_documents['id_photo_2x2'])): ?>
                                                    <?php $doc_url = getDocumentUrl($edit_documents['id_photo_2x2']); ?>
                                                    <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                                        <div class="flex justify-between items-center mb-2">
                                                            <div>
                                                                <span class="font-medium text-sm text-blue-800">Current File:</span>
                                                                <div class="text-xs text-blue-600 truncate mt-1">
                                                                    2x2 ID Photo
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
                                                           name="id_photo_2x2" 
                                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                                           class="hidden" 
                                                           id="id_photo_2x2"
                                                           onchange="showFileName(this, 'idphoto_filename')">
                                                    <label for="id_photo_2x2" class="cursor-pointer flex items-center">
                                                        <div class="flex-1">
                                                            <div class="text-sm text-gray-600">Click to upload</div>
                                                            <div class="text-xs text-gray-500">JPG, JPEG, PNG up to 5MB</div>
                                                        </div>
                                                        <div class="text-gray-400 text-sm">
                                                            <i class="fas fa-upload"></i>
                                                        </div>
                                                    </label>
                                                    <div id="idphoto_filename" class="mt-2"></div>
                                                </div>
                                                <p class="text-xs text-gray-500 mt-1">Recent 2x2 colored ID photo with white background</p>
                                            </div>

                                            <!-- Valid ID -->
                                            <div class="space-y-1">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Valid ID
                                                </label>
                                                
                                                <?php if (!empty($edit_documents['valid_id'])): ?>
                                                    <?php $doc_url = getDocumentUrl($edit_documents['valid_id']); ?>
                                                    <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                                        <div class="flex justify-between items-center mb-2">
                                                            <div>
                                                                <span class="font-medium text-sm text-blue-800">Current File:</span>
                                                                <div class="text-xs text-blue-600 truncate mt-1">
                                                                    Valid ID
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
                                                <p class="text-xs text-gray-500 mt-1">Driver's License, Passport, Postal ID, etc.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Buttons -->
                                <div class="flex justify-end space-x-3">
                                    <a href="?" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                        Cancel
                                    </a>
                                    <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center font-semibold">
                                        <i class="fas fa-paper-plane mr-2"></i> Submit Corrections
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- View Mode -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <!-- Applicant Information -->
                                <div>
                                    <div class="info-card-header">
                                        <div class="icon-circle bg-blue-100 text-blue-600">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900">Applicant Information</h3>
                                            <p class="text-sm text-gray-500">Your registered information</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <div class="info-label">Full Name</div>
                                            <div class="info-value"><?php echo $full_name; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Birthdate</div>
                                            <div class="info-value">
                                                <?php echo isset($app['birth_date']) ? date('M j, Y', strtotime($app['birth_date'])) : '-'; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="info-label">Gender</div>
                                            <div class="info-value"><?php echo ucfirst($app['gender'] ?? '-'); ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Renter Code</div>
                                            <div class="info-value"><?php echo $app['renter_code']; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Contact</div>
                                            <div class="info-value"><?php echo $app['mobile']; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Email</div>
                                            <div class="info-value"><?php echo $app['email']; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Emergency Contact</div>
                                            <div class="info-value"><?php echo $app['emergency_name'] ?? 'N/A'; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Emergency Phone</div>
                                            <div class="info-value"><?php echo $app['emergency_contact'] ?? 'N/A'; ?></div>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <div class="info-label">Address</div>
                                        <div class="info-value text-sm">
                                            <?php 
                                            $address_parts = [];
                                            if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                                            if (!empty($app['street'])) $address_parts[] = $app['street'];
                                            if (!empty($app['barangay'])) $address_parts[] = 'Brgy. ' . $app['barangay'];
                                            if (!empty($app['city'])) $address_parts[] = $app['city'];
                                            if (!empty($app['province'])) $address_parts[] = $app['province'];
                                            if (!empty($app['zip_code'])) $address_parts[] = $app['zip_code'];
                                            echo implode(', ', $address_parts);
                                            ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stall & Business Information -->
                                <div>
                                    <div class="info-card-header">
                                        <div class="icon-circle bg-green-100 text-green-600">
                                            <i class="fas fa-store"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900">Stall & Business</h3>
                                            <p class="text-sm text-gray-500">Stall and business details</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <div class="info-label">Stall Name</div>
                                            <div class="info-value"><?php echo $app['stall_name']; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Stall Class</div>
                                            <div class="info-value"><?php echo $app['class_name']; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Business Name</div>
                                            <div class="info-value"><?php echo $app['business_name'] ?? 'N/A'; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Business Type</div>
                                            <div class="info-value"><?php echo ucfirst($app['business_type'] ?? '-'); ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Monthly Rent</div>
                                            <div class="info-value text-green-600 font-bold">
                                                ₱<?php echo number_format($app['monthly_rent'], 2); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="info-label">Stall Rights Fee</div>
                                            <div class="info-value">
                                                ₱<?php echo number_format($app['stall_rights_amount'], 2); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="info-label">Security Bond</div>
                                            <div class="info-value">
                                                ₱<?php echo number_format($app['security_bond'], 2); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="info-label">Total Due</div>
                                            <div class="info-value text-red-600 font-bold">
                                                ₱<?php echo number_format($app['total_amount_due'], 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Current Documents -->
                            <?php 
                            $doc_labels = [
                                'barangay_clearance' => 'Barangay Clearance',
                                'id_photo_2x2' => '2x2 ID Photo',
                                'valid_id' => 'Valid ID'
                            ];
                            ?>
                            <div class="mt-8 pt-8 border-t border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-file-alt text-purple-600 mr-2"></i>Uploaded Documents
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php foreach ($doc_labels as $type => $label): ?>
                                        <?php
                                            $doc_path = $app[$type] ?? '';
                                            $doc_url = !empty($doc_path) ? getDocumentUrl($doc_path) : '';
                                        ?>
                                        <div class="document-card" onclick="openModal('<?php echo $doc_url; ?>','<?php echo $label; ?>')">
                                            <?php if ($doc_url): ?>
                                                <div class="mb-3">
                                                    <i class="fas fa-image text-blue-500 text-3xl"></i>
                                                </div>
                                                <div class="text-sm font-medium text-gray-900"><?php echo $label; ?></div>
                                                <div class="text-xs text-gray-500 mt-1">Document uploaded</div>
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
                            
                            <!-- Action Button -->
                            <div class="mt-8 pt-8 border-t border-gray-200 text-center">
                                <a href="?edit=<?php echo $app['id']; ?>" 
                                   class="inline-flex items-center px-8 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-semibold text-lg">
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
        if (file.type.startsWith('image/')) {
            let fileSize = '';
            if (file.size < 1024 * 1024) {
                fileSize = (file.size / 1024).toFixed(0) + ' KB';
            } else {
                fileSize = (file.size / (1024 * 1024)).toFixed(1) + ' MB';
            }
            
            let shortName = file.name;
            if (shortName.length > 25) {
                shortName = shortName.substring(0, 22) + '...';
            }
            
            display.innerHTML = `
                <div class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded px-2 py-1 text-xs">
                    <div class="flex items-center truncate">
                        <i class="fas fa-file-image text-blue-500 mr-2 text-xs"></i>
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
</script>
</body>
</html>