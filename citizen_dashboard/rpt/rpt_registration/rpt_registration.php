<?php
// revenue2/citizen_dashboard/rpt/rpt_registration/rpt_registration.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Citizen';
$user_email = $_SESSION['user_email'] ?? '';

// Include database connections
require_once '../../../db/RPT/rpt_db.php'; // RPT database connection
require_once '../../../Login/config/database.php'; // Database class for users DB

// Get RPT database connection
$pdo = getDatabaseConnection();

// Check if connection failed
if (is_array($pdo) && isset($pdo['error'])) {
    die("Database connection error: " . $pdo['message']);
}

// Fetch user details from users database for auto-fill
$user_details = null;
try {
    $users_database = new Database();
    $users_pdo = $users_database->getConnection();
    
    $user_stmt = $users_pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_details = $user_stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Users DB Error: " . $e->getMessage());
}

// Prepare auto-fill data from users database
$autofill_data = [];
if ($user_details) {
    $autofill_data = [
        'first_name' => $user_details['first_name'] ?? '',
        'last_name' => $user_details['last_name'] ?? '',
        'middle_name' => $user_details['middle_name'] ?? '',
        'suffix' => $user_details['suffix'] ?? '',
        'email' => $user_details['email'] ?? '',
        'phone' => $user_details['mobile'] ?? '',
        'house_number' => $user_details['house_number'] ?? '',
        'street' => $user_details['street'] ?? '',
        'barangay' => $user_details['barangay'] ?? '',
        'district' => $user_details['district'] ?? '',
        'city' => $user_details['city'] ?? 'Quezon City',
        'province' => $user_details['province'] ?? 'Metro Manila',
        'zip_code' => $user_details['zip_code'] ?? '',
        'birthdate' => $user_details['birthdate'] ?? ''
    ];
} else {
    $autofill_data = [
        'first_name' => '',
        'last_name' => '',
        'middle_name' => '',
        'suffix' => '',
        'email' => $user_email,
        'phone' => '',
        'house_number' => '',
        'street' => '',
        'barangay' => '',
        'district' => '',
        'city' => 'Quezon City',
        'province' => 'Metro Manila',
        'zip_code' => '',
        'birthdate' => ''
    ];
}

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
            // Generate reference number
            $reference_number = 'RPT-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Check if this user already has pending registration for the same property
            $checkDuplicate = $pdo->prepare("
                SELECT pr.id, pr.reference_number, pr.status 
                FROM property_registrations pr
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE po.user_id = ? 
                AND pr.lot_location LIKE ? 
                AND pr.barangay = ? 
                AND pr.district = ?
                AND pr.status IN ('pending', 'for_inspection', 'needs_correction', 'resubmitted')
                LIMIT 1
            ");
            $checkDuplicate->execute([
                $user_id,
                '%' . trim($_POST['property_lot_location']) . '%',
                $_POST['property_barangay'],
                $_POST['property_district']
            ]);
            
            $duplicate = $checkDuplicate->fetch();
            
            if ($duplicate) {
                $statusText = ucfirst(str_replace('_', ' ', $duplicate['status']));
                throw new Exception("You already have a $statusText registration for this property (Reference: {$duplicate['reference_number']}). Please wait for assessment or check your application status.");
            }
            
            // Build address string from individual fields
            $owner_address = trim(($_POST['house_number'] ? $_POST['house_number'] . ' ' : '') . 
                                 ($_POST['street'] ? $_POST['street'] . ', ' : '') . 
                                 ($_POST['barangay'] ? $_POST['barangay'] . ', ' : '') . 
                                 ($_POST['district'] ? 'District ' . $_POST['district'] . ', ' : '') .
                                 ($_POST['city'] ? $_POST['city'] . ', ' : '') . 
                                 ($_POST['province'] ? $_POST['province'] . ' ' : '') . 
                                 ($_POST['zip_code'] ? $_POST['zip_code'] : ''));
            
            // 1. Create new property owner record with separate name fields
            $owner_stmt = $pdo->prepare("INSERT INTO property_owners 
                (owner_code, first_name, last_name, middle_name, suffix, 
                 birthdate, email, phone, address, house_number, street, barangay, 
                 district, city, province, zip_code, tin_number, user_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            
            $owner_code = 'OWNER-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $owner_stmt->execute([
                $owner_code,
                $_POST['first_name'],
                $_POST['last_name'],
                !empty($_POST['middle_name']) ? $_POST['middle_name'] : null,
                !empty($_POST['suffix']) ? $_POST['suffix'] : null,
                !empty($_POST['birthdate']) ? $_POST['birthdate'] : null,
                $_POST['email'],
                $_POST['phone'],
                $owner_address,
                $_POST['house_number'],
                $_POST['street'],
                $_POST['barangay'],
                $_POST['district'],
                $_POST['city'] ?? 'Quezon City',
                $_POST['province'] ?? 'Metro Manila',
                $_POST['zip_code'],
                !empty($_POST['tin_number']) ? $_POST['tin_number'] : null,
                $user_id
            ]);
            
            $owner_id = $pdo->lastInsertId();
            
            // 2. Insert into property_registrations
            $reg_stmt = $pdo->prepare("INSERT INTO property_registrations 
                (reference_number, owner_id, lot_location, barangay, district, city, province, zip_code, has_building, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            
            $reg_stmt->execute([
                $reference_number,
                $owner_id,
                trim($_POST['property_lot_location']),
                trim($_POST['property_barangay']),
                trim($_POST['property_district']),
                $_POST['property_city'] ?? 'Quezon City',
                $_POST['property_province'] ?? 'Metro Manila',
                $_POST['property_zip_code'],
                $_POST['has_building']
            ]);
            
            $registration_id = $pdo->lastInsertId();
            
            // 3. Handle file uploads
            $uploaded_files = [];
            $base_path = '../../../documents/rpt/';
            
            // Create organized folder structure by year/month
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
            
            // Create registration folder using reference number
            $registration_folder = $month_folder . $reference_number . '/';
            if (!file_exists($registration_folder)) {
                mkdir($registration_folder, 0777, true);
            }
            
            // Define document types and their field names
            $document_types = [
                'barangay_certificate' => 'Barangay Certificate',
                'ownership_proof' => 'Proof of Ownership',
                'valid_id' => 'Valid ID',
                'survey_plan' => 'Survey Plan'
            ];
            
            foreach ($document_types as $field_name => $document_type) {
                if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] == UPLOAD_ERR_OK) {
                    $file = $_FILES[$field_name];
                    
                    // Validate file type (images only)
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                    $file_type = mime_content_type($file['tmp_name']);
                    
                    if (!in_array($file_type, $allowed_types)) {
                        throw new Exception("$document_type must be an image (JPG, JPEG, PNG)");
                    }
                    
                    // Validate file size (5MB max)
                    $max_size = 5 * 1024 * 1024; // 5MB
                    if ($file['size'] > $max_size) {
                        throw new Exception("$document_type is too large. Maximum size is 5MB");
                    }
                    
                    // Generate unique filename using reference number
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $clean_ref = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $reference_number);
                    $unique_filename = $field_name . '_' . $clean_ref . '_' . time() . '.' . $file_extension;
                    $file_path = $registration_folder . $unique_filename;
                    
                    // Store relative path for database
                    $relative_path = 'documents/rpt/' . $year . '/' . $month . '/' . $reference_number . '/' . $unique_filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // Insert document record
                        $doc_stmt = $pdo->prepare("INSERT INTO property_documents 
                            (registration_id, document_type, file_name, file_path, file_size, file_type, uploaded_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
                        
                        $doc_stmt->execute([
                            $registration_id,
                            $field_name,
                            $file['name'],
                            $relative_path,
                            $file['size'],
                            $file_type,
                            $user_id
                        ]);
                        
                        $uploaded_files[] = $document_type;
                    } else {
                        throw new Exception("Failed to upload $document_type");
                    }
                } else {
                    throw new Exception("$document_type is required");
                }
            }
            
            // 4. Auto-schedule inspection (7 days from now)
            $inspection_stmt = $pdo->prepare("INSERT INTO property_inspections 
                (registration_id, scheduled_date, assessor_name, status) 
                VALUES (?, ?, ?, 'scheduled')");
            
            $inspection_date = date('Y-m-d', strtotime('+7 days'));
            $inspection_stmt->execute([
                $registration_id,
                $inspection_date,
                'To be assigned'
            ]);
            
            $pdo->commit();
            
            // Store reference number in session for tracking
            $_SESSION['last_reference_number'] = $reference_number;
            
            $message = "✅ Registration submitted successfully!<br>
                       <strong>Reference Number:</strong> $reference_number<br>
                       <strong>Inspection Date:</strong> $inspection_date<br>
                       <strong>Documents Uploaded:</strong> " . implode(', ', $uploaded_files) . "<br><br>
                       <span class='text-blue-700 font-medium'><i class='fas fa-info-circle mr-1'></i> Status: <strong>Pending Assessment</strong></span>";
            $message_type = 'success';
            
            // Clear form data after successful submission
            unset($_POST);
            
        } catch(PDOException $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            $message = "❌ Database Error: " . $e->getMessage();
            $message_type = 'error';
        } catch(Exception $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            $message = "❌ " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Merge POST data with autofill for form display
$form_data = array_merge($autofill_data, $_POST ?? []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Registration - RPT Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">
        <!-- Page Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center mb-4">
                <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Property Registration</h1>
                    <p class="text-gray-600">Register your property for Real Property Tax assessment</p>
                </div>
            </div>
        </div>

        <!-- Registration Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type == 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300'; ?>">
                    <div class="flex items-start">
                        <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2 mt-1"></i>
                        <div class="text-sm"><?php echo $message; ?></div>
                    </div>
                    
                    <?php if ($message_type == 'success' && isset($_SESSION['last_reference_number'])): ?>
                        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded">
                            <h5 class="font-semibold text-blue-800 mb-2 flex items-center">
                                <i class="fas fa-tracking mr-2"></i>
                                Track Your Application
                            </h5>
                            <p class="text-blue-700 text-sm mb-2">
                                Reference Number: <strong><?php echo $_SESSION['last_reference_number']; ?></strong><br>
                                Current Status: <span class="font-medium">Pending Assessment</span>
                            </p>
                            <div class="mt-2">
                                <a href="../tracking/rpt_tracking.php?ref=<?php echo urlencode($_SESSION['last_reference_number']); ?>"
                                   class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium text-sm bg-white px-3 py-1 rounded border border-blue-300">
                                    <i class="fas fa-search mr-1"></i>
                                    Track Application Status
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- CSRF Protection -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="5242880"> <!-- 5MB max -->
                
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter your first name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                            <input type="text" name="last_name" required 
                                value="<?php echo htmlspecialchars($form_data['last_name']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter your last name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                            <input type="text" name="middle_name"
                                value="<?php echo htmlspecialchars($form_data['middle_name']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter middle name (optional)">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                            <select name="suffix"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select Suffix</option>
                                <option value="Jr." <?php echo ($form_data['suffix'] ?? '') == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                <option value="Sr." <?php echo ($form_data['suffix'] ?? '') == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                <option value="II" <?php echo ($form_data['suffix'] ?? '') == 'II' ? 'selected' : ''; ?>>II</option>
                                <option value="III" <?php echo ($form_data['suffix'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                                <option value="IV" <?php echo ($form_data['suffix'] ?? '') == 'IV' ? 'selected' : ''; ?>>IV</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate</label>
                            <input type="date" name="birthdate"
                                value="<?php echo htmlspecialchars($form_data['birthdate']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                            <input type="email" name="email" required 
                                value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter your email">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                            <input type="text" name="phone" required 
                                value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter your phone number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">TIN Number</label>
                            <input type="text" name="tin_number" 
                                value="<?php echo htmlspecialchars($form_data['tin_number'] ?? ''); ?>"
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
                                    value="<?php echo htmlspecialchars($form_data['house_number']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="123">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Street *</label>
                                <input type="text" name="street" required 
                                    value="<?php echo htmlspecialchars($form_data['street']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Main Street">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Barangay *</label>
                                <input type="text" name="barangay" required 
                                    value="<?php echo htmlspecialchars($form_data['barangay']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Barangay Name">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">District *</label>
                                <select name="district" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select District</option>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($form_data['district'] ?? '') == $i ? 'selected' : ''; ?>>
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
                                    value="<?php echo htmlspecialchars($form_data['zip_code']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="1100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Property Information Section -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-map-marker-alt text-green-500 mr-2"></i>
                        Property Location
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lot Location *</label>
                            <input type="text" name="property_lot_location" required 
                                value="<?php echo htmlspecialchars($form_data['property_lot_location'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                placeholder="e.g., Lot 5, Block 2 or specific location">
                            <p class="text-xs text-gray-500 mt-1">The physical location identifier of your property</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Property Barangay *</label>
                                <input type="text" name="property_barangay" required 
                                    value="<?php echo htmlspecialchars($form_data['property_barangay'] ?? ''); ?>"
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
                                        <option value="<?php echo $i; ?>" <?php echo ($form_data['property_district'] ?? '') == $i ? 'selected' : ''; ?>>
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
                                    value="<?php echo htmlspecialchars($form_data['property_zip_code'] ?? ''); ?>"
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
                        Required Documents Upload
                    </h3>
                    
                    <div class="space-y-6">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <h4 class="font-semibold text-yellow-800 mb-2 flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                Important Notes:
                            </h4>
                            <ul class="text-yellow-700 text-sm space-y-1 ml-5 list-disc">
                                <li>All documents must be clear and readable images</li>
                                <li>Accepted formats: JPG, JPEG, PNG only</li>
                                <li>Maximum file size: 5MB per file</li>
                                <li>Make sure documents are not expired</li>
                                <li>Take clear photos or scans of documents</li>
                            </ul>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Barangay Certificate -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    <span class="text-red-500">*</span> Barangay Certificate
                                </label>
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-500 transition-colors">
                                    <input type="file" 
                                           name="barangay_certificate" 
                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                           required
                                           class="hidden" 
                                           id="barangay_certificate"
                                           onchange="previewFile(this, 'barangay_preview')">
                                    <label for="barangay_certificate" class="cursor-pointer block">
                                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                                        <p class="text-sm text-gray-600 mb-1">Click to upload</p>
                                        <p class="text-xs text-gray-500">JPG, JPEG, PNG up to 5MB</p>
                                    </label>
                                    <div id="barangay_preview" class="mt-2"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Issued by the barangay where the property is located</p>
                            </div>

                            <!-- Proof of Ownership -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    <span class="text-red-500">*</span> Proof of Ownership
                                </label>
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-500 transition-colors">
                                    <input type="file" 
                                           name="ownership_proof" 
                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                           required
                                           class="hidden" 
                                           id="ownership_proof"
                                           onchange="previewFile(this, 'ownership_preview')">
                                    <label for="ownership_proof" class="cursor-pointer block">
                                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                                        <p class="text-sm text-gray-600 mb-1">Click to upload</p>
                                        <p class="text-xs text-gray-500">JPG, JPEG, PNG up to 5MB</p>
                                    </label>
                                    <div id="ownership_preview" class="mt-2"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Deed of Sale, Tax Declaration, Title, etc.</p>
                            </div>

                            <!-- Valid ID -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    <span class="text-red-500">*</span> Valid ID
                                </label>
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-500 transition-colors">
                                    <input type="file" 
                                           name="valid_id" 
                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                           required
                                           class="hidden" 
                                           id="valid_id"
                                           onchange="previewFile(this, 'validid_preview')">
                                    <label for="valid_id" class="cursor-pointer block">
                                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                                        <p class="text-sm text-gray-600 mb-1">Click to upload</p>
                                        <p class="text-xs text-gray-500">JPG, JPEG, PNG up to 5MB</p>
                                    </label>
                                    <div id="validid_preview" class="mt-2"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Government-issued ID (Driver's License, Passport, etc.)</p>
                            </div>

                            <!-- Survey Plan -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    <span class="text-red-500">*</span> Survey Plan
                                </label>
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-500 transition-colors">
                                    <input type="file" 
                                           name="survey_plan" 
                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                           required
                                           class="hidden" 
                                           id="survey_plan"
                                           onchange="previewFile(this, 'survey_preview')">
                                    <label for="survey_plan" class="cursor-pointer block">
                                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                                        <p class="text-sm text-gray-600 mb-1">Click to upload</p>
                                        <p class="text-xs text-gray-500">JPG, JPEG, PNG up to 5MB</p>
                                    </label>
                                    <div id="survey_preview" class="mt-2"></div>
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
                                    <?php echo (isset($form_data['has_building']) && $form_data['has_building'] == 'yes') ? 'checked' : ''; ?>>
                                <span class="ml-2 text-gray-700">Yes, there is a building/house</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="has_building" value="no" 
                                    class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300"
                                    <?php echo (isset($form_data['has_building']) && $form_data['has_building'] == 'no') ? 'checked' : ''; ?>>
                                <span class="ml-2 text-gray-700">No, it's vacant land</span>
                            </label>
                        </div>
                        <p class="text-sm text-gray-500 mt-3">
                            <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                            Our assessor will visit to verify property details and calculate taxes based on actual inspection.
                        </p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-lg transition duration-300 flex items-center">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Submit Registration
                    </button>
                </div>
            </form>
        </div>

        <!-- Status Information Box -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h4 class="font-semibold text-blue-800 mb-3 flex items-center">
                <i class="fas fa-info-circle mr-2"></i>
                Application Status Flow
            </h4>
            <div class="text-blue-700 text-sm space-y-3">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center mr-3">
                        <span class="text-blue-700 font-bold">1</span>
                    </div>
                    <div>
                        <span class="font-medium">Pending</span> - Your application has been submitted and is awaiting review
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center mr-3">
                        <span class="text-blue-700 font-bold">2</span>
                    </div>
                    <div>
                        <span class="font-medium">For Inspection</span> - An assessor will visit your property
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center mr-3">
                        <span class="text-blue-700 font-bold">3</span>
                    </div>
                    <div>
                        <span class="font-medium">Assessed</span> - Property valuation completed
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center mr-3">
                        <span class="text-blue-700 font-bold">4</span>
                    </div>
                    <div>
                        <span class="font-medium">Approved</span> - Tax Declaration Number (TDN) issued, ready for tax payments
                    </div>
                </div>
                <p class="mt-4 text-blue-600 font-medium">
                    <i class="fas fa-clock mr-1"></i>
                    All registrations start as "Pending" and will be updated by the assessor.
                </p>
            </div>
        </div>
    </main>

    <script>
    function previewFile(input, previewId) {
        const preview = document.getElementById(previewId);
        const file = input.files[0];
        
        if (file) {
            // Clear previous preview
            preview.innerHTML = '';
            
            // Check file type
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'max-w-full h-32 object-contain border rounded';
                    preview.appendChild(img);
                    
                    // Add file info
                    const info = document.createElement('div');
                    info.className = 'text-xs text-gray-600 mt-1';
                    info.innerHTML = `<i class="fas fa-file-image mr-1"></i> ${file.name} (${(file.size/1024/1024).toFixed(2)}MB)`;
                    preview.appendChild(info);
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = `<div class="text-red-500 text-sm"><i class="fas fa-exclamation-circle mr-1"></i> Please upload an image file</div>`;
                input.value = ''; // Clear the input
            }
        }
    }
    
    // Add some interactivity to file upload areas
    document.addEventListener('DOMContentLoaded', function() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        
        fileInputs.forEach(input => {
            const parentDiv = input.parentElement.parentElement;
            
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
    </script>
</body>
</html> 