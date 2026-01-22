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

// CHECK IF USER HAS ANY EXISTING REGISTRATION (ALLOW ONLY ONE)
try {
    $checkExistingStmt = $pdo->prepare("
        SELECT pr.id, pr.reference_number, pr.status 
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE po.user_id = ? 
        LIMIT 1
    ");
    $checkExistingStmt->execute([$user_id]);
    $existingRegistration = $checkExistingStmt->fetch();
    
    if ($existingRegistration) {
        $statusText = ucfirst(str_replace('_', ' ', $existingRegistration['status']));
        header("Location: ../rpt_application/pending.php?message=" . urlencode("You already have a registered property (Reference: {$existingRegistration['reference_number']}). Please check your application status."));
        exit();
    }
} catch (PDOException $e) {
    error_log("Registration check error: " . $e->getMessage());
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
        'birthdate' => $user_details['birthdate'] ?? '',
        'sex' => $user_details['sex'] ?? '',
        'marital_status' => $user_details['marital_status'] ?? ''
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
        'birthdate' => '',
        'sex' => '',
        'marital_status' => ''
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
            // DOUBLE-CHECK FOR ANY EXISTING REGISTRATION (FINAL VALIDATION)
            $finalCheckStmt = $pdo->prepare("
                SELECT pr.id FROM property_registrations pr
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE po.user_id = ?
                LIMIT 1
            ");
            $finalCheckStmt->execute([$user_id]);
            
            if ($finalCheckStmt->fetch()) {
                throw new Exception("You already have a registered property. Please check your application status.");
            }
            
            // Generate reference number
            $reference_number = 'RPT-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Double-check for duplicate registration
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
            
            // 1. Create new property owner record
            $owner_stmt = $pdo->prepare("INSERT INTO property_owners 
                (owner_code, first_name, last_name, middle_name, suffix, 
                 birthdate, sex, marital_status, email, phone, address, house_number, street, barangay, 
                 district, city, province, zip_code, tin_number, user_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            
            $owner_code = 'OWNER-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $owner_stmt->execute([
                $owner_code,
                $_POST['first_name'],
                $_POST['last_name'],
                !empty($_POST['middle_name']) ? $_POST['middle_name'] : null,
                !empty($_POST['suffix']) ? $_POST['suffix'] : null,
                !empty($_POST['birthdate']) ? $_POST['birthdate'] : null,
                !empty($_POST['sex']) ? $_POST['sex'] : null,
                !empty($_POST['marital_status']) ? $_POST['marital_status'] : null,
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
            
            // Store reference number in session
            $_SESSION['last_reference_number'] = $reference_number;
            $_SESSION['registration_success'] = true;
            
            // REDIRECT TO PENDING APPLICATIONS PAGE
            header("Location: ../rpt_application/pending.php?ref=" . urlencode($reference_number));
            exit();
            
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

// Get base URL for background image
$base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
$bg_image_path = $base_url . '/revenue2/Login/images/gsmbg.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Registration - RPT Services | GoServePH</title>
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
            font-family: 'Inter', system-ui, sans-serif;
        }

        /* Background image with blur - same as login */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('<?php echo $bg_image_path; ?>') center/cover no-repeat;
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

        /* Small file upload area styling */
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

        .file-upload-small.drag-over {
            border-color: #4a90e2;
            background-color: rgba(74, 144, 226, 0.1);
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
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <?php include '../../navbar.php'; ?>
    
    <main class="container mx-auto px-4 md:px-6 py-8">
        <!-- Header in Box -->
        <div class="header-box mb-8">
            <div class="flex items-center">
                <a href="../rpt_services.php" 
                   class="inline-flex items-center text-gray-600 hover:text-[var(--primary)] mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Property Registration</h1>
                    <p class="text-gray-600">Register your property for Real Property Tax assessment</p>
                </div>
            </div>
        </div>

        <!-- Registration Form -->
        <div class="form-card p-6 md:p-8">
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type == 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300'; ?>">
                    <div class="flex items-start">
                        <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2 mt-1"></i>
                        <div class="text-sm"><?php echo $message; ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                <!-- CSRF Protection -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="5242880"> <!-- 5MB max -->
                
                <!-- Personal Information Section -->
                <div class="border-b border-gray-200 pb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-user text-blue-500 mr-3 text-xl"></i>
                        Personal Information
                        <span class="ml-2 text-xs font-normal bg-blue-100 text-blue-700 px-2 py-1 rounded-full">Required</span>
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                First Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="first_name" required 
                                value="<?php echo htmlspecialchars($form_data['first_name']); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                placeholder="Enter your first name">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Last Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="last_name" required 
                                value="<?php echo htmlspecialchars($form_data['last_name']); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                placeholder="Enter your last name">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Middle Name</label>
                            <input type="text" name="middle_name"
                                value="<?php echo htmlspecialchars($form_data['middle_name']); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                placeholder="Enter middle name (optional)">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Suffix</label>
                            <select name="suffix"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white">
                                <option value="">Select Suffix</option>
                                <option value="Jr." <?php echo ($form_data['suffix'] ?? '') == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                <option value="Sr." <?php echo ($form_data['suffix'] ?? '') == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                <option value="II" <?php echo ($form_data['suffix'] ?? '') == 'II' ? 'selected' : ''; ?>>II</option>
                                <option value="III" <?php echo ($form_data['suffix'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                                <option value="IV" <?php echo ($form_data['suffix'] ?? '') == 'IV' ? 'selected' : ''; ?>>IV</option>
                            </select>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Birthdate</label>
                            <input type="date" name="birthdate"
                                value="<?php echo htmlspecialchars($form_data['birthdate']); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Sex</label>
                            <select name="sex"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white">
                                <option value="">Select Sex</option>
                                <option value="male" <?php echo ($form_data['sex'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($form_data['sex'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($form_data['sex'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Marital Status</label>
                            <select name="marital_status"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white">
                                <option value="">Select Marital Status</option>
                                <option value="single" <?php echo ($form_data['marital_status'] ?? '') == 'single' ? 'selected' : ''; ?>>Single</option>
                                <option value="married" <?php echo ($form_data['marital_status'] ?? '') == 'married' ? 'selected' : ''; ?>>Married</option>
                                <option value="divorced" <?php echo ($form_data['marital_status'] ?? '') == 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="widowed" <?php echo ($form_data['marital_status'] ?? '') == 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input type="email" name="email" required 
                                value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                placeholder="Enter your email">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Phone Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="phone" required 
                                value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                placeholder="Enter your phone number">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">TIN Number</label>
                            <input type="text" name="tin_number" 
                                value="<?php echo htmlspecialchars($form_data['tin_number'] ?? ''); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                placeholder="Enter TIN (optional)">
                        </div>
                    </div>
                </div>

                <!-- Address Information Section -->
                <div class="border-b border-gray-200 pb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-home text-green-500 mr-3 text-xl"></i>
                        Home Address
                        <span class="ml-2 text-xs font-normal bg-green-100 text-green-700 px-2 py-1 rounded-full">Required</span>
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                House Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="house_number" required 
                                value="<?php echo htmlspecialchars($form_data['house_number']); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                                placeholder="123">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Street <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="street" required 
                                value="<?php echo htmlspecialchars($form_data['street']); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                                placeholder="Main Street">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Barangay <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="barangay" required 
                                value="<?php echo htmlspecialchars($form_data['barangay']); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                                placeholder="Barangay Name">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                District <span class="text-red-500">*</span>
                            </label>
                            <select name="district" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200 bg-white">
                                <option value="">Select District</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($form_data['district'] ?? '') == $i ? 'selected' : ''; ?>>
                                        District <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                City <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="city" value="Quezon City" required readonly
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Province <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="province" value="Metro Manila" required readonly
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                ZIP Code <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="zip_code" required 
                                value="<?php echo htmlspecialchars($form_data['zip_code']); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200"
                                placeholder="1100">
                        </div>
                    </div>
                </div>

                <!-- Property Information Section -->
                <div class="border-b border-gray-200 pb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-map-marker-alt text-orange-500 mr-3 text-xl"></i>
                        Property Location
                        <span class="ml-2 text-xs font-normal bg-orange-100 text-orange-700 px-2 py-1 rounded-full">Required</span>
                    </h3>
                    
                    <div class="space-y-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Lot Location <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="property_lot_location" required 
                                value="<?php echo htmlspecialchars($form_data['property_lot_location'] ?? ''); ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-200"
                                placeholder="e.g., Lot 5, Block 2 or specific location">
                            <p class="text-xs text-gray-500 mt-2">The physical location identifier of your property</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Property Barangay <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="property_barangay" required 
                                    value="<?php echo htmlspecialchars($form_data['property_barangay'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-200"
                                    placeholder="Enter property barangay">
                            </div>
                            
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Property District <span class="text-red-500">*</span>
                                </label>
                                <select name="property_district" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-200 bg-white">
                                    <option value="">Select District</option>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($form_data['property_district'] ?? '') == $i ? 'selected' : ''; ?>>
                                            District <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Property City <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="property_city" value="Quezon City" required readonly
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                            </div>
                            
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Property Province <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="property_province" value="Metro Manila" required readonly
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                            </div>
                            
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Property ZIP Code <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="property_zip_code" required 
                                    value="<?php echo htmlspecialchars($form_data['property_zip_code'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-200"
                                    placeholder="1100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documents Upload Section - Made Smaller -->
                <div class="border-b border-gray-200 pb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-file-upload text-red-500 mr-3 text-xl"></i>
                        Required Documents Upload
                        <span class="ml-2 text-xs font-normal bg-red-100 text-red-700 px-2 py-1 rounded-full">Required</span>
                    </h3>
                    
                    <div class="space-y-6">
                        <!-- Important Notes -->
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

                        <!-- Document Upload Grid - Smaller -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php
                            $documents = [
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
                            
                            foreach ($documents as $field_name => $doc_info):
                            ?>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    <span class="text-red-500">*</span> <?php echo $doc_info['label']; ?>
                                </label>
                                
                                <div class="file-upload-small"
                                     id="<?php echo $field_name; ?>_dropzone"
                                     onclick="document.getElementById('<?php echo $field_name; ?>').click()">
                                    <input type="file" 
                                           name="<?php echo $field_name; ?>" 
                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                           required
                                           class="hidden" 
                                           id="<?php echo $field_name; ?>"
                                           onchange="showFileName(this, '<?php echo $field_name; ?>_filename')">
                                    
                                    <div class="flex items-center">
                                        <i class="fas fa-cloud-upload-alt text-gray-400 mr-3 text-lg"></i>
                                        <div class="flex-1">
                                            <div class="text-xs text-gray-600">Click to upload</div>
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
                        <span class="ml-2 text-xs font-normal bg-purple-100 text-purple-700 px-2 py-1 rounded-full">Required</span>
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
                                        <?php echo (isset($form_data['has_building']) && $form_data['has_building'] == 'yes') ? 'checked' : ''; ?>>
                                    <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center">
                                        <div class="w-3 h-3 rounded-full bg-purple-600 hidden"></div>
                                    </div>
                                </div>
                                <span class="ml-3 text-gray-700">Yes, there is a building/house</span>
                            </label>
                            
                            <label class="flex items-center cursor-pointer">
                                <div class="relative">
                                    <input type="radio" name="has_building" value="no" 
                                        class="sr-only"
                                        <?php echo (isset($form_data['has_building']) && $form_data['has_building'] == 'no') ? 'checked' : ''; ?>>
                                    <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center">
                                        <div class="w-3 h-3 rounded-full bg-purple-600 hidden"></div>
                                    </div>
                                </div>
                                <span class="ml-3 text-gray-700">No, it's vacant land</span>
                            </label>
                        </div>
                        
                        <p class="text-sm text-gray-500 mt-4">
                            <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                            Our assessor will visit to verify property details and calculate taxes based on actual inspection.
                        </p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" 
                        class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-4 px-10 rounded-xl transition-all duration-300 flex items-center shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                        <i class="fas fa-paper-plane mr-3"></i>
                        Submit Registration
                    </button>
                </div>
            </form>
        </div>

        <!-- Compact Status Information Box -->
        <div class="mt-8 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-5">
            <h4 class="font-semibold text-blue-800 mb-3 flex items-center">
                <i class="fas fa-info-circle mr-2 text-lg"></i>
                Application Status
            </h4>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Step 1 -->
                <div class="bg-white rounded-lg p-4 border border-blue-200">
                    <div class="flex items-center mb-2">
                        <div class="w-6 h-6 rounded-full bg-blue-100 border-2 border-blue-500 flex items-center justify-center mr-2">
                            <span class="text-blue-700 font-bold text-xs">1</span>
                        </div>
                        <span class="font-medium text-blue-700 text-sm">Pending</span>
                    </div>
                    <p class="text-xs text-blue-600">Application submitted for review</p>
                </div>
                
                <!-- Step 2 -->
                <div class="bg-white rounded-lg p-4 border border-blue-200">
                    <div class="flex items-center mb-2">
                        <div class="w-6 h-6 rounded-full bg-blue-100 border-2 border-blue-300 flex items-center justify-center mr-2">
                            <span class="text-blue-500 font-bold text-xs">2</span>
                        </div>
                        <span class="font-medium text-blue-600 text-sm">Inspection</span>
                    </div>
                    <p class="text-xs text-blue-500">Property verification by assessor</p>
                </div>
                
                <!-- Step 3 -->
                <div class="bg-white rounded-lg p-4 border border-blue-200">
                    <div class="flex items-center mb-2">
                        <div class="w-6 h-6 rounded-full bg-blue-100 border-2 border-blue-300 flex items-center justify-center mr-2">
                            <span class="text-blue-500 font-bold text-xs">3</span>
                        </div>
                        <span class="font-medium text-blue-600 text-sm">Assessed</span>
                    </div>
                    <p class="text-xs text-blue-500">Valuation completed</p>
                </div>
                
                <!-- Step 4 -->
                <div class="bg-white rounded-lg p-4 border border-blue-200">
                    <div class="flex items-center mb-2">
                        <div class="w-6 h-6 rounded-full bg-blue-100 border-2 border-blue-300 flex items-center justify-center mr-2">
                            <span class="text-blue-500 font-bold text-xs">4</span>
                        </div>
                        <span class="font-medium text-blue-600 text-sm">Approved</span>
                    </div>
                    <p class="text-xs text-blue-500">TDN issued, ready for payment</p>
                </div>
            </div>
            
            <p class="mt-4 text-blue-600 text-sm flex items-center">
                <i class="fas fa-clock mr-2"></i>
                All registrations start as "Pending" and will be updated by the assessor.
            </p>
        </div>
    </main>

    <script>
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
            
            // Get short file name (max 20 chars)
            let shortName = file.name;
            if (shortName.length > 20) {
                shortName = shortName.substring(0, 17) + '...';
            }
            
            // Show file name in a compact format
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
        const dropzoneId = input.id + '_dropzone';
        const dropzone = document.getElementById(dropzoneId);
        
        if (!dropzone) return;
        
        // Highlight on drag over
        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over', 'border-blue-500', 'bg-blue-50');
        });
        
        dropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over', 'border-blue-500', 'bg-blue-50');
        });
        
        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over', 'border-blue-500', 'bg-blue-50');
            
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
        
        // Initialize checked state
        if (radio.checked) {
            const radioDiv = radio.parentElement.querySelector('div');
            const innerCircle = radioDiv.querySelector('div');
            innerCircle.classList.remove('hidden');
            radioDiv.classList.add('border-purple-600');
        }
    });
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('border-red-500');
        } else {
            field.classList.remove('border-red-500');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields marked with *');
    }
});
</script>
</body>
</html>