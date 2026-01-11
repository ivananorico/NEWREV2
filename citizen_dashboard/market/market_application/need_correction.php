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
include_once '../../../db/Market/market_db.php'; // Market database
include_once '../../../Login/config/database.php'; // Users database

// Get users database connection
$usersDb = new Database();
$pdo_users = $usersDb->getConnection();

// Check if application ID is provided
if (!isset($_GET['id'])) {
    header('Location: pending.php');
    exit();
}

$application_id = intval($_GET['id']);

// Fetch the application that needs correction
$stmt = $pdo->prepare("
    SELECT rr.*, 
           s.name as stall_name, 
           s.price as stall_price,
           sr.class_name,
           ro.*
    FROM rental_registration rr
    LEFT JOIN stalls s ON rr.stall_id = s.id
    LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
    LEFT JOIN renter_owner ro ON rr.id = ro.registration_id
    WHERE rr.id = ? 
    AND rr.user_id = ? 
    AND rr.application_status = 'need_correction'
");

$stmt->execute([$application_id, $user_id]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    echo "<script>
        alert('Application not found or doesn\'t require correction.');
        window.location.href = 'pending.php';
    </script>";
    exit();
}

// Handle form submission for corrections
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Handle document uploads
        $uploaded_files = [];
        $update_docs = [];
        $upload_paths = [];
        
        $base_path = '../../../documents/market/';
        $year = date('Y');
        $month = date('m');
        $year_folder = $base_path . $year . '/';
        $month_folder = $year_folder . $month . '/';
        
        if (!file_exists($year_folder)) {
            mkdir($year_folder, 0777, true);
        }
        if (!file_exists($month_folder)) {
            mkdir($month_folder, 0777, true);
        }
        
        $registration_folder = $month_folder . $application['stall_rights_no'] . '/';
        if (!file_exists($registration_folder)) {
            mkdir($registration_folder, 0777, true);
        }
        
        $document_types = [
            'barangay_clearance' => 'Barangay Clearance',
            'id_photo_2x2' => '2x2 ID Photo',
            'valid_id' => 'Valid ID'
        ];
        
        foreach ($document_types as $field_name => $document_type) {
            if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] == UPLOAD_ERR_OK) {
                $file = $_FILES[$field_name];
                
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                $file_type = mime_content_type($file['tmp_name']);
                
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("$document_type must be an image (JPG, JPEG, PNG)");
                }
                
                $max_size = 5 * 1024 * 1024;
                if ($file['size'] > $max_size) {
                    throw new Exception("$document_type is too large. Maximum size is 5MB");
                }
                
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $clean_ref = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $application['stall_rights_no']);
                $unique_filename = $field_name . '_' . $clean_ref . '_' . time() . '.' . $file_extension;
                $file_path = $registration_folder . $unique_filename;
                
                $relative_path = 'documents/market/' . $year . '/' . $month . '/' . $application['stall_rights_no'] . '/' . $unique_filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $update_docs[] = "$field_name = ?";
                    $upload_paths[] = $relative_path;
                    $uploaded_files[] = $document_type;
                } else {
                    throw new Exception("Failed to upload $document_type");
                }
            }
        }
        
        // Update rental_registration table
        $update_fields = [];
        $update_values = [];
        
        // Add document updates if any
        if (!empty($update_docs)) {
            $update_fields = array_merge($update_fields, $update_docs);
            $update_values = array_merge($update_values, $upload_paths);
        }
        
        // Always update application status to resubmitted and clear correction notes
        $update_fields[] = "application_status = ?";
        $update_fields[] = "correction_notes = NULL";
        $update_values[] = 'resubmitted';
        
        // Add updated_at timestamp
        $update_fields[] = "updated_at = CURRENT_TIMESTAMP";
        
        // Prepare and execute the update query
        $update_sql = "UPDATE rental_registration SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $update_values[] = $application_id;
        
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute($update_values);
        
        // Update renter_owner information
        $owner_update_fields = [];
        $owner_update_values = [];
        
        $owner_fields = [
            'first_name', 'middle_name', 'last_name', 'suffix',
            'email', 'mobile', 'telephone', 'house_number', 'street',
            'barangay', 'city', 'province', 'zip_code', 'birth_date',
            'gender', 'emergency_name', 'emergency_contact'
        ];
        
        foreach ($owner_fields as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                $owner_update_fields[] = "$field = ?";
                $owner_update_values[] = $_POST[$field];
            }
        }
        
        if (!empty($owner_update_fields)) {
            $owner_update_fields[] = "updated_at = CURRENT_TIMESTAMP";
            
            $owner_update_sql = "UPDATE renter_owner SET " . implode(', ', $owner_update_fields) . " WHERE registration_id = ?";
            $owner_update_values[] = $application_id;
            
            $owner_update_stmt = $pdo->prepare($owner_update_sql);
            $owner_update_stmt->execute($owner_update_values);
        }
        
        // Commit transaction
        $pdo->commit();
        
        $message = "✅ Application successfully resubmitted!<br>
                  <strong>Application ID:</strong> {$application['stall_rights_no']}<br>
                  <strong>Stall:</strong> {$application['stall_name']}<br>
                  <strong>Status:</strong> <span class='text-blue-600 font-semibold'>Resubmitted</span><br>";
        
        if (!empty($uploaded_files)) {
            $message .= "<strong>Updated Documents:</strong> " . implode(', ', $uploaded_files) . "<br>";
        }
        
        $message .= "<br><span class='text-green-600'><i class='fas fa-check-circle mr-1'></i> Your application is now under review again.</span>";
        $message_type = 'success';
        
        // Refresh application data
        $stmt->execute([$application_id, $user_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
        error_log("Resubmission Error: " . $e->getMessage());
    }
}

// Merge application data with POST data for form display
$form_data = array_merge($application, $_POST ?? []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correct Application - GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .document-preview {
            max-height: 150px;
            object-fit: contain;
        }
        
        .current-doc {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../../navbar.php'; ?>
    
    <main class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <div class="flex items-center mb-4">
                <a href="pending.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Correct Application</h1>
                    <p class="text-gray-600 mt-1">Make the required corrections and resubmit your application</p>
                </div>
            </div>
            
            <!-- Application Info -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-4">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mt-1 mr-3"></i>
                    <div class="flex-1">
                        <h3 class="font-semibold text-yellow-800 mb-2">Correction Required</h3>
                        <p class="text-yellow-700 mb-3">
                            Please correct the following issues in your application:
                        </p>
                        <div class="bg-white border border-yellow-300 rounded p-3 text-yellow-800">
                            <i class="fas fa-comment-dots mr-2"></i>
                            <strong>Admin Notes:</strong> 
                            <?php echo htmlspecialchars($application['correction_notes'] ?? 'No specific notes provided'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Application Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-xs text-gray-500">Application ID</div>
                    <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($application['stall_rights_no']); ?></div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-xs text-gray-500">Stall</div>
                    <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($application['stall_name']); ?></div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-xs text-gray-500">Stall Class</div>
                    <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($application['class_name']); ?></div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-xs text-gray-500">Monthly Rent</div>
                    <div class="font-semibold text-green-600">₱<?php echo number_format($application['stall_price'], 2); ?></div>
                </div>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $message_type == 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300'; ?>">
            <div class="flex items-start">
                <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2 mt-1"></i>
                <div><?php echo $message; ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Correction Form -->
        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
            
            <!-- Personal Information -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-user text-blue-500 mr-2"></i>
                    Personal Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                        <input type="text" name="first_name" required 
                               value="<?php echo htmlspecialchars($form_data['first_name']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                        <input type="text" name="last_name" required 
                               value="<?php echo htmlspecialchars($form_data['last_name']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                        <input type="text" name="middle_name"
                               value="<?php echo htmlspecialchars($form_data['middle_name'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                        <select name="suffix" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Suffix</option>
                            <option value="Jr." <?php echo ($form_data['suffix'] ?? '') == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                            <option value="Sr." <?php echo ($form_data['suffix'] ?? '') == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                            <option value="II" <?php echo ($form_data['suffix'] ?? '') == 'II' ? 'selected' : ''; ?>>II</option>
                            <option value="III" <?php echo ($form_data['suffix'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                            <option value="IV" <?php echo ($form_data['suffix'] ?? '') == 'IV' ? 'selected' : ''; ?>>IV</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate *</label>
                        <input type="date" name="birth_date" required
                               value="<?php echo htmlspecialchars($form_data['birth_date']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                        <select name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($form_data['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($form_data['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($form_data['gender'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-phone text-green-500 mr-2"></i>
                    Contact Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="email" required
                               value="<?php echo htmlspecialchars($form_data['email']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mobile *</label>
                        <input type="text" name="mobile" required
                               value="<?php echo htmlspecialchars($form_data['mobile']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Telephone</label>
                        <input type="text" name="telephone"
                               value="<?php echo htmlspecialchars($form_data['telephone'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                </div>
            </div>
            
            <!-- Address Information -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-home text-purple-500 mr-2"></i>
                    Address Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">House Number</label>
                        <input type="text" name="house_number"
                               value="<?php echo htmlspecialchars($form_data['house_number'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Street</label>
                        <input type="text" name="street"
                               value="<?php echo htmlspecialchars($form_data['street'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Barangay</label>
                        <input type="text" name="barangay"
                               value="<?php echo htmlspecialchars($form_data['barangay'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City *</label>
                        <input type="text" name="city" required
                               value="<?php echo htmlspecialchars($form_data['city']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Province *</label>
                        <input type="text" name="province" required
                               value="<?php echo htmlspecialchars($form_data['province']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                        <input type="text" name="zip_code"
                               value="<?php echo htmlspecialchars($form_data['zip_code'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>
            </div>
            
            <!-- Emergency Contact -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                    Emergency Contact
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Name</label>
                        <input type="text" name="emergency_name"
                               value="<?php echo htmlspecialchars($form_data['emergency_name'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Number</label>
                        <input type="text" name="emergency_contact"
                               value="<?php echo htmlspecialchars($form_data['emergency_contact'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>
                </div>
            </div>
            
            <!-- Document Upload Section -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-file-upload text-orange-500 mr-2"></i>
                    Update Documents
                </h3>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <h4 class="font-semibold text-yellow-800 mb-2 flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>
                        Important:
                    </h4>
                    <ul class="text-yellow-700 text-sm space-y-1 ml-4 list-disc">
                        <li>Only upload files if you need to replace the current documents</li>
                        <li>If your documents are correct, you don't need to upload them again</li>
                        <li>Accepted formats: JPG, JPEG, PNG (max 5MB each)</li>
                    </ul>
                </div>
                
                <div class="space-y-6">
                    <!-- Current Documents Preview -->
                    <div class="mb-4">
                        <h4 class="font-medium text-gray-700 mb-3">Current Documents:</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <?php
                            $documents = [
                                'barangay_clearance' => 'Barangay Clearance',
                                'id_photo_2x2' => '2x2 ID Photo',
                                'valid_id' => 'Valid ID'
                            ];
                            
                            foreach ($documents as $key => $label):
                                $path = $application[$key] ?? '';
                                $full_path = '../../../' . $path;
                            ?>
                            <div class="current-doc p-3">
                                <div class="text-xs text-gray-500 mb-1"><?php echo $label; ?></div>
                                <?php if (file_exists($full_path)): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo $full_path; ?>" 
                                             alt="<?php echo $label; ?>"
                                             class="document-preview w-full rounded">
                                    </div>
                                    <a href="<?php echo $full_path; ?>" 
                                       target="_blank"
                                       class="text-xs text-blue-600 hover:underline flex items-center">
                                        <i class="fas fa-eye mr-1"></i>
                                        View Current
                                    </a>
                                <?php else: ?>
                                    <div class="text-xs text-gray-400 italic">No document uploaded</div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Upload New Documents -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                New Barangay Clearance
                            </label>
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
                                        <div class="text-xs text-gray-500">Replace current document</div>
                                    </div>
                                    <div class="text-gray-400 text-sm">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                </label>
                                <div id="barangay_filename" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                New 2x2 ID Photo
                            </label>
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
                                        <div class="text-xs text-gray-500">Replace current document</div>
                                    </div>
                                    <div class="text-gray-400 text-sm">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                </label>
                                <div id="idphoto_filename" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                New Valid ID
                            </label>
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
                                        <div class="text-xs text-gray-500">Replace current document</div>
                                    </div>
                                    <div class="text-gray-400 text-sm">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                </label>
                                <div id="validid_filename" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                <a href="pending.php" 
                   class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 flex items-center">
                    <i class="fas fa-times mr-2"></i>
                    Cancel
                </a>
                
                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-lg transition duration-300 flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    Resubmit Application
                </button>
            </div>
        </form>
        
        <!-- Process Flow -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
            <h4 class="font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-sync-alt mr-2"></i>
                Resubmission Process
            </h4>
            <div class="text-gray-600 text-sm space-y-3">
                <div class="flex items-center">
                    <div class="w-6 h-6 rounded-full bg-yellow-100 border border-yellow-300 flex items-center justify-center mr-3">
                        <i class="fas fa-exclamation text-yellow-600 text-xs"></i>
                    </div>
                    <div>
                        <span class="font-medium">Need Correction</span> - Application returned for corrections
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="w-6 h-6 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center mr-3">
                        <i class="fas fa-redo text-blue-600 text-xs"></i>
                    </div>
                    <div>
                        <span class="font-medium">Resubmitted</span> - You've made corrections and resubmitted
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="w-6 h-6 rounded-full bg-gray-100 border border-gray-300 flex items-center justify-center mr-3">
                        <i class="fas fa-clock text-gray-600 text-xs"></i>
                    </div>
                    <div>
                        <span class="font-medium">Pending Review</span> - Application goes back to administrator for review
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
                    if (shortName.length > 20) {
                        shortName = shortName.substring(0, 17) + '...';
                    }
                    
                    display.innerHTML = `
                        <div class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded px-2 py-1 text-xs">
                            <div class="flex items-center truncate">
                                <i class="fas fa-file-image text-green-500 mr-2 text-xs"></i>
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

        // Set max date for birthdate (18 years ago)
        window.onload = function() {
            const today = new Date();
            const maxDate = new Date();
            maxDate.setFullYear(today.getFullYear() - 18);
            
            const birthDateInput = document.querySelector('input[name="birth_date"]');
            if (birthDateInput) {
                birthDateInput.max = maxDate.toISOString().split('T')[0];
            }
        };
    </script>
</body>
</html>