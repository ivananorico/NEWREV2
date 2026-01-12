<?php
// revenue2/citizen_dashboard/market/market_application/edit_correction.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Include database connections
include_once '../../../db/Market/market_db.php';

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Location: need_correction.php');
    exit();
}

$application_id = intval($_GET['id']);

// Fetch application data
try {
    $stmt = $pdo->prepare("
        SELECT 
            rr.*,
            ro.*,
            s.name as stall_name,
            sr.class_name,
            sr.price as class_price,
            m.name as market_name
        FROM rental_registration rr
        JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN maps m ON rr.map_id = m.id
        WHERE rr.id = ? 
          AND ro.user_id = ? 
          AND rr.application_status = 'need_correction'
    ");
    $stmt->execute([$application_id, $user_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        header('Location: need_correction.php');
        exit();
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Store current document paths before handling submission
$current_documents = [
    'barangay_clearance' => $application['barangay_clearance'] ?? '',
    'id_photo_2x2' => $application['id_photo_2x2'] ?? '',
    'valid_id' => $application['valid_id'] ?? ''
];

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "❌ Security token invalid. Please try again.";
        $message_type = 'error';
    } else {
        try {
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
                $_POST['city'],
                $_POST['province'],
                $_POST['zip_code'],
                $_POST['birthdate'],
                $_POST['gender'],
                $_POST['emergency_name'] ?? null,
                $_POST['emergency_contact'] ?? null,
                $application_id
            ]);
            
            // Update rental_registration status to resubmitted
            $reg_stmt = $pdo->prepare("
                UPDATE rental_registration 
                SET application_status = 'resubmitted', 
                    correction_notes = NULL, 
                    updated_at = CURRENT_TIMESTAMP()
                WHERE id = ?
            ");
            
            $reg_stmt->execute([$application_id]);
            
            // Handle file uploads - ONLY UPDATE IF NEW FILE IS UPLOADED
            $document_types = [
                'barangay_clearance' => 'Barangay Clearance',
                'id_photo_2x2' => '2x2 ID Photo', 
                'valid_id' => 'Valid ID'
            ];
            
            $reference_number = $application['stall_rights_no'];
            $year = date('Y');
            $month = date('m');
            $upload_dir = "../../../documents/market/{$year}/{$month}/{$reference_number}/";
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($document_types as $field_name => $doc_name) {
                // Check if a new file was uploaded for this document type
                if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] == UPLOAD_ERR_OK && $_FILES[$field_name]['size'] > 0) {
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
                    $relative_path = "documents/market/{$year}/{$month}/{$reference_number}/{$new_filename}";
                    
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // Update the document path in rental_registration
                        $doc_stmt = $pdo->prepare("
                            UPDATE rental_registration 
                            SET $field_name = ?, updated_at = CURRENT_TIMESTAMP()
                            WHERE id = ?
                        ");
                        $doc_stmt->execute([$relative_path, $application_id]);
                        
                        // Update current_documents array with new path
                        $current_documents[$field_name] = $relative_path;
                    }
                }
                // If no new file was uploaded, keep the existing document path
                // No need to update the database for this field
            }
            
            $pdo->commit();
            
            // Redirect to success page
            header("Location: resubmitted.php?ref=" . urlencode($reference_number) . "&id=" . $application_id);
            exit();
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Prepare form data
$form_data = [
    'first_name' => $application['first_name'] ?? '',
    'last_name' => $application['last_name'] ?? '',
    'middle_name' => $application['middle_name'] ?? '',
    'suffix' => $application['suffix'] ?? '',
    'email' => $application['email'] ?? '',
    'mobile' => $application['mobile'] ?? '',
    'telephone' => $application['telephone'] ?? '',
    'house_number' => $application['house_number'] ?? '',
    'street' => $application['street'] ?? '',
    'barangay' => $application['barangay'] ?? '',
    'city' => $application['city'] ?? 'Quezon City',
    'province' => $application['province'] ?? 'Metro Manila',
    'zip_code' => $application['zip_code'] ?? '',
    'birthdate' => $application['birth_date'] ?? '',
    'gender' => $application['gender'] ?? '',
    'emergency_name' => $application['emergency_name'] ?? '',
    'emergency_contact' => $application['emergency_contact'] ?? ''
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Application Corrections - Market Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include '../../navbar.php'; ?>
    
    <main class="container mx-auto px-6 py-8">
        <!-- Page Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center mb-4">
                <a href="need_correction.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Edit Application Corrections</h1>
                    <p class="text-gray-600">Make corrections for stall rights #<?php echo htmlspecialchars($application['stall_rights_no']); ?></p>
                </div>
            </div>
            
            <!-- Stall Information Card -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                <h3 class="font-semibold text-blue-800 mb-3 flex items-center">
                    <i class="fas fa-store mr-2"></i>
                    Stall Details
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <div class="text-xs text-gray-500">Stall Name</div>
                        <div class="font-semibold"><?php echo htmlspecialchars($application['stall_name']); ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Stall Class</div>
                        <div class="font-semibold"><?php echo htmlspecialchars($application['class_name']); ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Monthly Rent</div>
                        <div class="font-semibold text-green-600">₱<?php echo number_format($application['monthly_rent'], 2); ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Stall Rights No.</div>
                        <div class="font-semibold"><?php echo htmlspecialchars($application['stall_rights_no']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Correction Notes -->
            <?php if (!empty($application['correction_notes'])): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mt-4">
                <h3 class="font-semibold text-red-800 mb-2 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Correction Notes from Admin
                </h3>
                <p class="text-red-700 text-sm"><?php echo htmlspecialchars($application['correction_notes']); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Application Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type == 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300'; ?>">
                    <div class="flex items-start">
                        <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2 mt-1"></i>
                        <div class="text-sm"><?php echo $message; ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- CSRF Protection -->
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
                                value="<?php echo htmlspecialchars($form_data['first_name']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                            <input type="text" name="last_name" required 
                                value="<?php echo htmlspecialchars($form_data['last_name']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                            <input type="text" name="middle_name"
                                value="<?php echo htmlspecialchars($form_data['middle_name']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                            <select name="suffix"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select Suffix</option>
                                <option value="Jr." <?php echo $form_data['suffix'] == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                <option value="Sr." <?php echo $form_data['suffix'] == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                <option value="II" <?php echo $form_data['suffix'] == 'II' ? 'selected' : ''; ?>>II</option>
                                <option value="III" <?php echo $form_data['suffix'] == 'III' ? 'selected' : ''; ?>>III</option>
                                <option value="IV" <?php echo $form_data['suffix'] == 'IV' ? 'selected' : ''; ?>>IV</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate *</label>
                            <input type="date" name="birthdate" required
                                value="<?php echo htmlspecialchars($form_data['birthdate']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                            <select name="gender"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $form_data['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $form_data['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $form_data['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                            <input type="email" name="email" required 
                                value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number *</label>
                            <input type="text" name="mobile" required 
                                value="<?php echo htmlspecialchars($form_data['mobile']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="09171234567">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telephone Number</label>
                            <input type="text" name="telephone"
                                value="<?php echo htmlspecialchars($form_data['telephone']); ?>"
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
                                    value="<?php echo htmlspecialchars($form_data['house_number']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Street</label>
                                <input type="text" name="street"
                                    value="<?php echo htmlspecialchars($form_data['street']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Barangay</label>
                                <input type="text" name="barangay"
                                    value="<?php echo htmlspecialchars($form_data['barangay']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">City *</label>
                                <input type="text" name="city" value="<?php echo htmlspecialchars($form_data['city']); ?>" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Province *</label>
                                <input type="text" name="province" value="<?php echo htmlspecialchars($form_data['province']); ?>" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                                <input type="text" name="zip_code"
                                    value="<?php echo htmlspecialchars($form_data['zip_code']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
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
                                value="<?php echo htmlspecialchars($form_data['emergency_name']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Number</label>
                            <input type="text" name="emergency_contact"
                                value="<?php echo htmlspecialchars($form_data['emergency_contact']); ?>"
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
                                <li>Only upload new documents if you need to replace existing ones</li>
                                <li>If you don't upload a new file, the existing document will be kept</li>
                                <li>All documents must be clear and readable images</li>
                                <li>Accepted formats: JPG, JPEG, PNG only</li>
                                <li>Maximum file size: 5MB per file</li>
                                <li>Make sure documents are not expired</li>
                            </ul>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Barangay Clearance -->
                            <div class="space-y-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Barangay Clearance (Optional)
                                </label>
                                <?php if (!empty($current_documents['barangay_clearance'])): ?>
                                <div class="mb-2 p-2 bg-green-50 border border-green-200 rounded">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs text-green-700 font-medium">
                                                <i class="fas fa-check-circle mr-1"></i>Document already uploaded
                                            </div>
                                            <div class="text-xs text-gray-600 truncate mt-1">
                                                <?php 
                                                $filename = basename($current_documents['barangay_clearance']);
                                                echo htmlspecialchars(substr($filename, 0, 30)) . (strlen($filename) > 30 ? '...' : '');
                                                ?>
                                            </div>
                                        </div>
                                        <?php 
                                        // Function to get viewable URL
                                        function getDocumentUrl($path) {
                                            if (empty($path)) return '';
                                            // Remove any relative paths for security
                                            $clean = str_replace(['../', './'], '', $path);
                                            $clean = ltrim($clean, '/');
                                            // Make sure it's within the documents directory
                                            if (strpos($clean, 'documents/') === 0) {
                                                return '/revenue2/' . $clean;
                                            }
                                            return '';
                                        }
                                        $view_url = getDocumentUrl($current_documents['barangay_clearance']);
                                        ?>
                                        <?php if ($view_url): ?>
                                        <a href="<?php echo $view_url; ?>" target="_blank" 
                                           class="text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 px-2 py-1 rounded flex items-center transition-colors">
                                            <i class="fas fa-eye mr-1"></i> View
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
                                            <div class="text-sm text-gray-600">Click to upload new file</div>
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
                                    2x2 ID Photo (Optional)
                                </label>
                                <?php if (!empty($current_documents['id_photo_2x2'])): ?>
                                <div class="mb-2 p-2 bg-green-50 border border-green-200 rounded">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs text-green-700 font-medium">
                                                <i class="fas fa-check-circle mr-1"></i>Document already uploaded
                                            </div>
                                            <div class="text-xs text-gray-600 truncate mt-1">
                                                <?php 
                                                $filename = basename($current_documents['id_photo_2x2']);
                                                echo htmlspecialchars(substr($filename, 0, 30)) . (strlen($filename) > 30 ? '...' : '');
                                                ?>
                                            </div>
                                        </div>
                                        <?php $view_url = getDocumentUrl($current_documents['id_photo_2x2']); ?>
                                        <?php if ($view_url): ?>
                                        <a href="<?php echo $view_url; ?>" target="_blank" 
                                           class="text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 px-2 py-1 rounded flex items-center transition-colors">
                                            <i class="fas fa-eye mr-1"></i> View
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
                                            <div class="text-sm text-gray-600">Click to upload new file</div>
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
                                    Valid ID (Optional)
                                </label>
                                <?php if (!empty($current_documents['valid_id'])): ?>
                                <div class="mb-2 p-2 bg-green-50 border border-green-200 rounded">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs text-green-700 font-medium">
                                                <i class="fas fa-check-circle mr-1"></i>Document already uploaded
                                            </div>
                                            <div class="text-xs text-gray-600 truncate mt-1">
                                                <?php 
                                                $filename = basename($current_documents['valid_id']);
                                                echo htmlspecialchars(substr($filename, 0, 30)) . (strlen($filename) > 30 ? '...' : '');
                                                ?>
                                            </div>
                                        </div>
                                        <?php $view_url = getDocumentUrl($current_documents['valid_id']); ?>
                                        <?php if ($view_url): ?>
                                        <a href="<?php echo $view_url; ?>" target="_blank" 
                                           class="text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 px-2 py-1 rounded flex items-center transition-colors">
                                            <i class="fas fa-eye mr-1"></i> View
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
                                            <div class="text-sm text-gray-600">Click to upload new file</div>
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
                <div class="flex justify-between">
                    <a href="need_correction.php" 
                       class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Cancel
                    </a>
                    
                    <button type="submit" 
                        class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-8 rounded-lg transition duration-300 flex items-center">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Submit Corrections
                    </button>
                </div>
            </form>
        </div>

        <!-- Status Information Box -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h4 class="font-semibold text-blue-800 mb-3 flex items-center">
                <i class="fas fa-info-circle mr-2"></i>
                After Submitting Corrections
            </h4>
            <div class="text-blue-700 text-sm space-y-3">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center mr-3">
                        <span class="text-blue-700 font-bold">1</span>
                    </div>
                    <div>
                        <span class="font-medium">Resubmitted</span> - Your corrected application will be reviewed again
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center mr-3">
                        <span class="text-blue-700 font-bold">2</span>
                    </div>
                    <div>
                        <span class="font-medium">Interview</span> - If corrections are accepted, you'll be scheduled for interview
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center mr-3">
                        <span class="text-blue-700 font-bold">3</span>
                    </div>
                    <div>
                        <span class="font-medium">Payment</span> - After successful interview, proceed with payment
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
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

        // Form validation for birthdate (must be at least 18 years old)
        document.querySelector('form').addEventListener('submit', function(e) {
            const birthDate = document.querySelector('input[name="birthdate"]');
            const today = new Date();
            const minDate = new Date();
            minDate.setFullYear(today.getFullYear() - 18);
            
            if (new Date(birthDate.value) > minDate) {
                e.preventDefault();
                alert('You must be at least 18 years old to apply.');
                birthDate.focus();
            }
        });

        // Set max date for birthdate (18 years ago)
        window.onload = function() {
            const today = new Date();
            const maxDate = new Date();
            maxDate.setFullYear(today.getFullYear() - 18);
            
            const birthDateInput = document.querySelector('input[name="birthdate"]');
            birthDateInput.max = maxDate.toISOString().split('T')[0];
        };
    </script>
</body>
</html>