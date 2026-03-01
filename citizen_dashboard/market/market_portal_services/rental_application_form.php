<?php
// revenue2/citizen_dashboard/market/market_portal_services/rental_application_form.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

// Check if stall_id and map_id are provided
if (!isset($_GET['map_id']) || !isset($_GET['stall_id'])) {
    header('Location: market_portal_services.php');
    exit();
}

$map_id = intval($_GET['map_id']);
$stall_id = intval($_GET['stall_id']);
$user_id = $_SESSION['user_id'];

// Include database connections
include_once '../../../db/Market/market_db.php'; // Market database
include_once '../../../Login/config/database.php'; // Users database

// Get users database connection
$usersDb = new Database();
$pdo_users = $usersDb->getConnection();

if (!$pdo_users) {
    die("Users database connection failed");
}

// Fetch user data
$user_stmt = $pdo_users->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found!";
    exit();
}

// Market database connection is already available as $pdo from market_db.php
if (!isset($pdo)) {
    die("Market database connection failed");
}

// Fetch stall details
$stmt = $pdo->prepare("
    SELECT s.*, sr.class_name, sr.price as class_price
    FROM stalls s
    LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
    WHERE s.id = ? AND s.map_id = ?
");
$stmt->execute([$stall_id, $map_id]);
$stall = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stall) {
    echo "Stall not found!";
    exit();
}

// Check if stall is available
if ($stall['status'] !== 'available') {
    echo "This stall is not available for rent!";
    exit();
}

// Prepare auto-fill data from users database
$autofill_data = [
    'first_name' => $user['first_name'] ?? '',
    'last_name' => $user['last_name'] ?? '',
    'middle_name' => $user['middle_name'] ?? '',
    'suffix' => $user['suffix'] ?? '',
    'email' => $user['email'] ?? '',
    'phone' => $user['mobile'] ?? '',
    'house_number' => $user['house_number'] ?? '',
    'street' => $user['street'] ?? '',
    'barangay' => $user['barangay'] ?? '',
    'district' => $user['district'] ?? '',
    'city' => $user['city'] ?? 'Quezon City',
    'province' => $user['province'] ?? 'Metro Manila',
    'zip_code' => $user['zip_code'] ?? '',
    'birthdate' => $user['birthdate'] ?? '',
    'gender' => $user['sex'] ?? ''
];

// Quezon City Barangays by District
$qc_barangays = [
    1 => [
        'Alicia', 'Amihan', 'Bagong Silangan', 'Batasan Hills', 'Commonwealth', 
        'Holy Spirit', 'Matandang Balara', 'Payatas'
    ],
    2 => [
        'Bagumbuhay', 'Bagumbong', 'Caloocan', 'Capitol Park', 'Diliman', 
        'Project 6', 'Ramon Magsaysay', 'Sauyo', 'Talipapa', 'Tandang Sora', 
        'Unang Sigaw', 'Veterans Village'
    ],
    3 => [
        'Amoranto', 'Baesa', 'Balingasa', 'Bungad', 'Damar', 'Damayan', 
        'Del Monte', 'Katipunan', 'Manresa', 'Mariblo', 'Masambong', 
        'N.S. Amoranto', 'Pag-ibig', 'Paltok', 'Paraiso', 'Phil-Am', 
        'Project 7', 'Project 8', 'San Antonio', 'San Isidro', 'San Jose', 
        'San Vincente', 'Santa Cruz', 'Santa Teresita', 'Santo Cristo', 
        'Santo Domingo', 'Siena', 'St. Peter', 'Tatalon', 'Valencia', 
        'Vasra', 'West Triangle'
    ],
    4 => [
        'Bagong Lipunan', 'Botocan', 'Central', 'Cubao', 'E. Rodriguez', 
        'Immaculate Concepcion', 'Kaunlaran', 'Kristong Hari', 'Laging Handa', 
        'Mangga', 'Mariana', 'Milagrosa', 'Obrero', 'Pinagkaisahan', 'Quirino', 
        'Roxas', 'Sacred Heart', 'San Martin', 'San Vicente', 'Socorro', 
        'Ugong Norte', 'Valencia', 'Xavierville'
    ],
    5 => [
        'Bagbag', 'Capri', 'Fairview', 'Gulod', 'Greater Lagro', 'Kaligayahan', 
        'Nagkaisang Nayon', 'North Fairview', 'Novaliches', 'Pasong Putik', 
        'San Agustin', 'San Bartolome', 'Sta. Lucia', 'Sta. Monica'
    ],
    6 => [
        'Apolonio Samson', 'Baesa', 'Balong Bato', 'Culiat', 'New Era', 
        'Pasong Tamo', 'Sangandaan', 'Sauyo', 'Talipapa', 'Tandang Sora', 
        'Unang Sigaw'
    ]
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
            // Generate stall rights number
            $stall_rights_no = 'STALL-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Calculate financial amounts
            $stall_rights_amount = $stall['class_price'];
            $monthly_rent = $stall['price'];
            $security_bond = $monthly_rent * 2;
            $total_amount_due = $stall_rights_amount + $security_bond;
            
            // Start transaction
            $pdo->beginTransaction();
            
            // 1. Handle file uploads first
            $upload_paths = [
                'barangay_clearance' => '',
                'id_photo_2x2' => '',
                'valid_id' => ''
            ];
            
            // Define upload paths - FIXED to use uploads/market/documents/
            $base_upload_path = '../../../uploads/market/documents/';
            
            // Create directory structure
            $year = date('Y');
            $month = date('m');
            $upload_dir = $base_upload_path . $year . '/' . $month . '/' . $stall_rights_no . '/';
            
            // Create upload directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }
            
            // Upload each required document
            $document_types = [
                'barangay_clearance' => 'Barangay Clearance',
                'id_photo_2x2' => '2x2 ID Photo',
                'valid_id' => 'Valid ID'
            ];
            
            $uploaded_files = [];
            
            foreach ($document_types as $field_name => $document_type) {
                if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("$document_type is required");
                }
                
                $file = $_FILES[$field_name];
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                $file_type = mime_content_type($file['tmp_name']);
                
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("$document_type must be an image (JPG, JPEG, PNG)");
                }
                
                // Validate file size (5MB)
                $max_size = 5 * 1024 * 1024; // 5MB
                if ($file['size'] > $max_size) {
                    throw new Exception("$document_type is too large. Maximum size is 5MB");
                }
                
                // Generate unique filename
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $clean_ref = preg_replace('/[^A-Za-z0-9\-]/', '_', $stall_rights_no);
                $unique_filename = $field_name . '_' . $clean_ref . '_' . time() . '.' . strtolower($file_extension);
                $file_destination = $upload_dir . $unique_filename;
                
                // Move uploaded file
                if (!move_uploaded_file($file['tmp_name'], $file_destination)) {
                    throw new Exception("Failed to upload $document_type");
                }
                
                // Store relative path for database (without the ../../../)
                $relative_path = 'uploads/market/documents/' . $year . '/' . $month . '/' . $stall_rights_no . '/' . $unique_filename;
                $upload_paths[$field_name] = $relative_path;
                $uploaded_files[] = $document_type;
            }
            
            // 2. Insert into rental_registration
            $registration_stmt = $pdo->prepare("
                INSERT INTO rental_registration (
                    user_id, stall_id, map_id, stall_name, stall_class,
                    stall_rights_amount, monthly_rent, security_bond, total_amount_due,
                    barangay_clearance, id_photo_2x2, valid_id,
                    application_status, stall_rights_no
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            
            $registration_stmt->execute([
                $user_id,
                $stall_id,
                $map_id,
                $stall['name'],
                $stall['class_name'],
                $stall_rights_amount,
                $monthly_rent,
                $security_bond,
                $total_amount_due,
                $upload_paths['barangay_clearance'],
                $upload_paths['id_photo_2x2'],
                $upload_paths['valid_id'],
                $stall_rights_no
            ]);
            
            $registration_id = $pdo->lastInsertId();
            
            // 3. Insert into renter_owner
            $owner_stmt = $pdo->prepare("
                INSERT INTO renter_owner (
                    registration_id, user_id,
                    first_name, middle_name, last_name, suffix,
                    email, mobile, telephone,
                    house_number, street, barangay, district, city, province, zip_code,
                    birth_date, gender,
                    emergency_name, emergency_contact,
                    renter_code, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $city = $_POST['city'] ?? 'Quezon City';
            $province = $_POST['province'] ?? 'Metro Manila';
            $renter_code = 'RENTER-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Validate and sanitize gender input
            $gender_input = $_POST['gender'] ?? '';
            $allowed_genders = ['male', 'female', 'other'];
            $gender = in_array(strtolower($gender_input), $allowed_genders) ? strtolower($gender_input) : null;
            
            // Execute renter_owner insert
            $owner_stmt->execute([
                $registration_id,
                $user_id,
                $_POST['first_name'],
                !empty($_POST['middle_name']) ? $_POST['middle_name'] : null,
                $_POST['last_name'],
                !empty($_POST['suffix']) ? $_POST['suffix'] : null,
                $_POST['email'],
                $_POST['mobile'],
                $_POST['telephone'] ?? null,
                $_POST['house_number'] ?? null,
                $_POST['street'] ?? null,
                $_POST['barangay'] ?? null,
                $_POST['district'] ?? null,
                $city,
                $province,
                $_POST['zip_code'] ?? null,
                $_POST['birthdate'],
                $gender,
                $_POST['emergency_name'] ?? null,
                $_POST['emergency_contact'] ?? null,
                $renter_code
            ]);
            
            $renter_id = $pdo->lastInsertId();
            
            // 4. Insert into rent_stall
            $rent_stall_stmt = $pdo->prepare("
                INSERT INTO rent_stall (
                    renter_id, registration_id, stall_id, map_id,
                    business_name, business_type, stall_rights_no,
                    start_date, monthly_rent, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'pending')
            ");
            
            $rent_stall_stmt->execute([
                $renter_id,
                $registration_id,
                $stall_id,
                $map_id,
                $_POST['business_name'] ?? null,
                $_POST['business_type'] ?? null,
                $stall_rights_no,
                $monthly_rent
            ]);
            
            // 5. Update stall status to reserved
            $update_stmt = $pdo->prepare("UPDATE stalls SET status = 'reserved' WHERE id = ?");
            $update_stmt->execute([$stall_id]);
            
            $pdo->commit();
            
            // Store reference number in session for tracking
            $_SESSION['last_stall_rights_no'] = $stall_rights_no;
            
            $message = "✅ Stall rental application submitted successfully!<br>
                      <strong>Stall Rights Number:</strong> $stall_rights_no<br>
                      <strong>Stall Name:</strong> {$stall['name']}<br>
                      <strong>Monthly Rent:</strong> ₱" . number_format($monthly_rent, 2) . "<br>
                      <strong>Total Amount Due:</strong> ₱" . number_format($total_amount_due, 2) . "<br>
                      <strong>Documents Uploaded:</strong> " . implode(', ', $uploaded_files) . "<br><br>
                      <span class='text-blue-700 font-medium'><i class='fas fa-info-circle mr-1'></i> Status: <strong>Pending Application Review</strong></span>";
            $message_type = 'success';
            
            // Clear form data after successful submission
            unset($_POST);
            
        } catch(PDOException $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            $message = "❌ Database Error: " . $e->getMessage();
            $message_type = 'error';
            error_log("Database Error: " . $e->getMessage());
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

// Get the base URL for the background image
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $protocol = 'https://';
} else {
    $protocol = 'http://';
}
$base_url = $protocol . $_SERVER['HTTP_HOST'];
$bg_image_path = $base_url . '/revenue2/Login/images/gsmbg.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Stall Rental Application | GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../images/GSM_logo.png">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #9aa5b1;
            --accent: #4caf50;
            --warning: #f59e0b;
            --danger: #ef4444;
            --background: #fbfbfb;
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
            background: url('<?php echo $bg_image_path; ?>') center/cover no-repeat;
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

        .header-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border-radius: 20px;
            padding: 1.5rem;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .form-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(74, 144, 226, 0.12);
        }

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

        .section-header {
            border-left: 4px solid var(--primary);
            padding-left: 1rem;
            margin-bottom: 1rem;
        }

        .input-field {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.2s;
            width: 100%;
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        /* Simple file input styling */
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-button {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #374151;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        
        .file-input-button:hover {
            background: #f1f5f9;
            border-color: #cbd5e0;
        }
        
        .file-input-button i {
            color: #6b7280;
        }
        
        .file-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-display {
            margin-top: 8px;
            padding: 0.5rem 0.75rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.875rem;
            color: #374151;
            display: none;
        }
        
        .file-display.show {
            display: block;
        }
        
        .file-name {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .file-size {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .file-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        
        .file-action-btn {
            padding: 4px 12px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .file-action-btn:hover {
            background: #f8fafc;
        }
        
        .file-action-btn.preview:hover {
            border-color: #4a90e2;
            color: #4a90e2;
        }
        
        .file-action-btn.remove:hover {
            border-color: #ef4444;
            color: #ef4444;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #3a7bd5);
            color: white;
            padding: 12px 32px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #3a7bd5, #2a6bc4);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(74, 144, 226, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary), #8a949f);
            color: white;
            padding: 12px 32px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #8a949f, #7a8694);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(154, 165, 177, 0.3);
        }

        .stall-badge {
            background: linear-gradient(135deg, var(--primary), #3a7bd5);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .info-box {
            background: rgba(74, 144, 226, 0.08);
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid rgba(74, 144, 226, 0.2);
        }

        /* Checkbox styling */
        .checkbox-container {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .custom-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid #cbd5e0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.2s;
        }
        
        .custom-checkbox.checked {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .custom-checkbox i {
            color: white;
            font-size: 0.75rem;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .custom-checkbox.checked i {
            opacity: 1;
        }

        .checkbox-label {
            font-size: 0.875rem;
            color: #4b5563;
            line-height: 1.4;
            cursor: pointer;
        }

        /* Barangay Select Styles */
        .barangay-select {
            max-height: 200px;
            overflow-y: auto;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .barangay-select optgroup {
            font-weight: 600;
            color: #374151;
            background-color: #f9fafb;
            padding: 0.5rem;
        }
        
        .barangay-select option {
            padding: 0.25rem 1rem;
            font-size: 0.9rem;
        }

        /* Main content centering */
        .main-content-wrapper {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 200px);
        }

        .form-container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        /* Modal Styles - Simple */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            max-width: 90%;
            max-height: 90vh;
            overflow: hidden;
        }
        
        .modal-image {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            display: block;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-box .flex {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-actions a, .form-actions button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <!-- Main Content Wrapper -->
    <div class="main-content-wrapper">
        <!-- Main Content - Centered -->
        <main class="flex-1 py-8">
            <div class="form-container mx-auto px-4">
                <!-- Page Header -->
                <div class="header-box mb-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                        <a href="apply_rental.php?map_id=<?php echo $map_id; ?>" class="back-button self-start">
                            <i class="fas fa-arrow-left"></i> Back to Stall Selection
                        </a>
                        
                        <div class="text-center">
                            <h1 class="text-3xl font-bold text-gray-900 mb-2">Rental Application Form</h1>
                            <p class="text-gray-600">Apply for stall <?php echo htmlspecialchars($stall['name']); ?> at <?php echo htmlspecialchars($stall['class_name']); ?> class</p>
                        </div>
                        
                        <div class="text-right">
                            <div class="stall-badge inline-block">
                                <i class="fas fa-store mr-1"></i>
                                <?php echo htmlspecialchars($stall['name']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stall Information -->
                    <div class="info-box mt-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div>
                                <div class="text-xs text-gray-600 mb-1">Stall Class</div>
                                <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($stall['class_name']); ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-600 mb-1">Monthly Rent</div>
                                <div class="font-semibold text-green-600">₱<?php echo number_format($stall['price'], 2); ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-600 mb-1">Stall Rights Fee</div>
                                <div class="font-semibold text-orange-600">₱<?php echo number_format($stall['class_price'], 2); ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-600 mb-1">Security Bond</div>
                                <div class="font-semibold text-blue-600">₱<?php echo number_format($stall['price'] * 2, 2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="form-card mb-6 p-6 <?php echo $message_type == 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'; ?>">
                        <div class="flex items-start">
                            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle text-green-600' : 'fa-exclamation-circle text-red-600'; ?> mr-3 mt-1 text-lg"></i>
                            <div class="text-sm <?php echo $message_type == 'success' ? 'text-green-700' : 'text-red-700'; ?>">
                                <?php echo $message; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Application Form -->
                <div class="form-card p-6 mb-8">
                    <form method="POST" enctype="multipart/form-data" class="space-y-8" id="rentalForm">
                        <!-- CSRF Protection -->
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                        
                        <!-- Personal Information Section -->
                        <div class="section-header">
                            <h2 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-user-circle mr-3 text-primary"></i>
                                Personal Information
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">Fill in your personal details</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- First Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="first_name" required 
                                    value="<?php echo htmlspecialchars($form_data['first_name']); ?>"
                                    class="input-field" placeholder="Enter first name">
                            </div>
                            
                            <!-- Last Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Last Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="last_name" required 
                                    value="<?php echo htmlspecialchars($form_data['last_name']); ?>"
                                    class="input-field" placeholder="Enter last name">
                            </div>
                            
                            <!-- Middle Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Middle Name
                                </label>
                                <input type="text" name="middle_name"
                                    value="<?php echo htmlspecialchars($form_data['middle_name']); ?>"
                                    class="input-field" placeholder="Enter middle name">
                            </div>
                            
                            <!-- Suffix -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Suffix
                                </label>
                                <select name="suffix" class="input-field">
                                    <option value="">Select Suffix</option>
                                    <option value="Jr." <?php echo ($form_data['suffix'] ?? '') == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo ($form_data['suffix'] ?? '') == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="II" <?php echo ($form_data['suffix'] ?? '') == 'II' ? 'selected' : ''; ?>>II</option>
                                    <option value="III" <?php echo ($form_data['suffix'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                                </select>
                            </div>
                            
                            <!-- Birthdate -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Birthdate <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="birthdate" required
                                    value="<?php echo htmlspecialchars($form_data['birthdate']); ?>"
                                    class="input-field">
                            </div>
                            
                            <!-- Gender -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Gender
                                </label>
                                <select name="gender" class="input-field">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo (strtolower($form_data['gender'] ?? '') == 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (strtolower($form_data['gender'] ?? '') == 'female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo (strtolower($form_data['gender'] ?? '') == 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                            <!-- Email -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" name="email" required 
                                    value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                    class="input-field" placeholder="your@email.com">
                            </div>
                            
                            <!-- Mobile -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Mobile Number <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="mobile" required 
                                    value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                    class="input-field" placeholder="09171234567">
                            </div>
                            
                            <!-- Telephone -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Telephone Number
                                </label>
                                <input type="text" name="telephone"
                                    value="<?php echo htmlspecialchars($form_data['telephone'] ?? ''); ?>"
                                    class="input-field" placeholder="02-1234567">
                            </div>
                        </div>

                        <!-- Address Information -->
                        <div class="section-header mt-8">
                            <h2 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-home mr-3 text-primary"></i>
                                Address Information
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">Your complete home address</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- House Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    House Number
                                </label>
                                <input type="text" name="house_number"
                                    value="<?php echo htmlspecialchars($form_data['house_number']); ?>"
                                    class="input-field" placeholder="123">
                            </div>
                            
                            <!-- Street -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Street
                                </label>
                                <input type="text" name="street"
                                    value="<?php echo htmlspecialchars($form_data['street']); ?>"
                                    class="input-field" placeholder="Street name">
                            </div>
                            
                            <!-- Barangay -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Barangay
                                </label>
                                <select name="barangay" class="input-field barangay-select">
                                    <option value="">Select Barangay</option>
                                    <?php foreach ($qc_barangays as $district_num => $barangays): ?>
                                    <optgroup label="District <?php echo $district_num; ?>">
                                        <?php foreach ($barangays as $barangay): ?>
                                        <option value="<?php echo htmlspecialchars($barangay); ?>" 
                                            <?php echo ($form_data['barangay'] ?? '') == $barangay ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($barangay); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- District -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    District
                                </label>
                                <select name="district" class="input-field" id="home_district">
                                    <option value="">Select District</option>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($form_data['district'] ?? '') == $i ? 'selected' : ''; ?>>
                                            District <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <!-- City -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    City <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="city" required 
                                    value="<?php echo htmlspecialchars($form_data['city']); ?>"
                                    class="input-field" placeholder="City name">
                                <p class="text-xs text-gray-500 mt-1">Fixed to Quezon City</p>
                            </div>
                            
                            <!-- Province -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Province <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="province" required 
                                    value="<?php echo htmlspecialchars($form_data['province']); ?>"
                                    class="input-field" placeholder="Province name">
                                <p class="text-xs text-gray-500 mt-1">Fixed to Metro Manila</p>
                            </div>
                            
                            <!-- ZIP Code -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    ZIP Code
                                </label>
                                <input type="text" name="zip_code"
                                    value="<?php echo htmlspecialchars($form_data['zip_code']); ?>"
                                    class="input-field" placeholder="1100">
                                <p class="text-xs text-gray-500 mt-1">Quezon City ZIP codes: 1100-1128</p>
                            </div>
                        </div>

                        <!-- Business Information -->
                        <div class="section-header mt-8">
                            <h2 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-briefcase mr-3 text-primary"></i>
                                Business Information
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">Details about your business</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Business Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Business Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="business_name" required
                                    value="<?php echo htmlspecialchars($form_data['business_name'] ?? ''); ?>"
                                    class="input-field" placeholder="Enter your business name">
                            </div>
                            
                            <!-- Business Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Business Type <span class="text-red-500">*</span>
                                </label>
                                <select name="business_type" required class="input-field">
                                    <option value="">Select Business Type</option>
                                    <option value="Food" <?php echo ($form_data['business_type'] ?? '') == 'Food' ? 'selected' : ''; ?>>Food</option>
                                    <option value="Clothing" <?php echo ($form_data['business_type'] ?? '') == 'Clothing' ? 'selected' : ''; ?>>Clothing</option>
                                    <option value="Electronics" <?php echo ($form_data['business_type'] ?? '') == 'Electronics' ? 'selected' : ''; ?>>Electronics</option>
                                    <option value="Grocery" <?php echo ($form_data['business_type'] ?? '') == 'Grocery' ? 'selected' : ''; ?>>Grocery</option>
                                    <option value="Services" <?php echo ($form_data['business_type'] ?? '') == 'Services' ? 'selected' : ''; ?>>Services</option>
                                    <option value="Others" <?php echo ($form_data['business_type'] ?? '') == 'Others' ? 'selected' : ''; ?>>Others</option>
                                </select>
                            </div>
                        </div>

                        <!-- Emergency Contact -->
                        <div class="section-header mt-8">
                            <h2 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-phone-alt mr-3 text-primary"></i>
                                Emergency Contact
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">Person to contact in case of emergency</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Emergency Contact Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Emergency Contact Name
                                </label>
                                <input type="text" name="emergency_name"
                                    value="<?php echo htmlspecialchars($form_data['emergency_name'] ?? ''); ?>"
                                    class="input-field" placeholder="Full name">
                            </div>
                            
                            <!-- Emergency Contact Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Emergency Contact Number
                                </label>
                                <input type="text" name="emergency_contact"
                                    value="<?php echo htmlspecialchars($form_data['emergency_contact'] ?? ''); ?>"
                                    class="input-field" placeholder="09171234567">
                            </div>
                        </div>

                        <!-- Required Documents -->
                        <div class="section-header mt-8">
                            <h2 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-file-upload mr-3 text-primary"></i>
                                Required Documents
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">Upload clear images of the following documents (Max 5MB each)</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Barangay Clearance -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Barangay Clearance <span class="text-red-500">*</span>
                                </label>
                                <div class="file-input-wrapper">
                                    <div class="file-input-button">
                                        <span>Choose File</span>
                                        <i class="fas fa-paperclip"></i>
                                    </div>
                                    <input type="file" name="barangay_clearance" id="barangay_clearance" 
                                           accept="image/jpeg,image/jpg,image/png" 
                                           class="file-input" required
                                           onchange="handleFileSelect(this, 'barangay-display')">
                                </div>
                                <div id="barangay-display" class="file-display">
                                    <div class="file-name" id="barangay-name"></div>
                                    <div class="file-size" id="barangay-size"></div>
                                    <div class="file-actions">
                                        <button type="button" class="file-action-btn preview" onclick="previewFile('barangay_clearance')">Preview</button>
                                        <button type="button" class="file-action-btn remove" onclick="removeFile('barangay_clearance', 'barangay-display')">Remove</button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 2x2 ID Photo -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    2x2 ID Photo <span class="text-red-500">*</span>
                                </label>
                                <div class="file-input-wrapper">
                                    <div class="file-input-button">
                                        <span>Choose File</span>
                                        <i class="fas fa-paperclip"></i>
                                    </div>
                                    <input type="file" name="id_photo_2x2" id="id_photo_2x2"
                                           accept="image/jpeg,image/jpg,image/png" 
                                           class="file-input" required
                                           onchange="handleFileSelect(this, 'id-photo-display')">
                                </div>
                                <div id="id-photo-display" class="file-display">
                                    <div class="file-name" id="id-photo-name"></div>
                                    <div class="file-size" id="id-photo-size"></div>
                                    <div class="file-actions">
                                        <button type="button" class="file-action-btn preview" onclick="previewFile('id_photo_2x2')">Preview</button>
                                        <button type="button" class="file-action-btn remove" onclick="removeFile('id_photo_2x2', 'id-photo-display')">Remove</button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Valid ID -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Valid ID (Passport, Driver's License, etc.) <span class="text-red-500">*</span>
                                </label>
                                <div class="file-input-wrapper">
                                    <div class="file-input-button">
                                        <span>Choose File</span>
                                        <i class="fas fa-paperclip"></i>
                                    </div>
                                    <input type="file" name="valid_id" id="valid_id"
                                           accept="image/jpeg,image/jpg,image/png" 
                                           class="file-input" required
                                           onchange="handleFileSelect(this, 'valid-id-display')">
                                </div>
                                <div id="valid-id-display" class="file-display">
                                    <div class="file-name" id="valid-id-name"></div>
                                    <div class="file-size" id="valid-id-size"></div>
                                    <div class="file-actions">
                                        <button type="button" class="file-action-btn preview" onclick="previewFile('valid_id')">Preview</button>
                                        <button type="button" class="file-action-btn remove" onclick="removeFile('valid_id', 'valid-id-display')">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="section-header mt-8">
                            <h2 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-clipboard-check mr-3 text-primary"></i>
                                Terms and Conditions
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">Please read and accept the terms</p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <div class="space-y-3 mb-4">
                                <div class="checkbox-container">
                                    <div class="custom-checkbox" onclick="toggleCheckbox(this)">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <label class="checkbox-label">
                                        I hereby declare that all information provided in this application is true and correct to the best of my knowledge.
                                    </label>
                                    <input type="checkbox" name="terms_truthful" required class="hidden">
                                </div>
                                
                                <div class="checkbox-container">
                                    <div class="custom-checkbox" onclick="toggleCheckbox(this)">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <label class="checkbox-label">
                                        I agree to pay the monthly rent, stall rights fee, and security bond as specified.
                                    </label>
                                    <input type="checkbox" name="terms_payment" required class="hidden">
                                </div>
                                
                                <div class="checkbox-container">
                                    <div class="custom-checkbox" onclick="toggleCheckbox(this)">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <label class="checkbox-label">
                                        I agree to comply with all market rules and regulations.
                                    </label>
                                    <input type="checkbox" name="terms_rules" required class="hidden">
                                </div>
                                
                                <div class="checkbox-container">
                                    <div class="custom-checkbox" onclick="toggleCheckbox(this)">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <label class="checkbox-label">
                                        I understand that this application is subject to approval and the stall will be reserved upon submission.
                                    </label>
                                    <input type="checkbox" name="terms_approval" required class="hidden">
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-8 border-t border-gray-200">
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-info-circle mr-1"></i>
                                Your application will be processed within 3-5 working days
                            </div>
                            
                            <div class="flex gap-4 form-actions">
                                <a href="apply_rental.php?map_id=<?php echo $map_id; ?>" 
                                   class="btn-secondary flex items-center justify-center">
                                    <i class="fas fa-times mr-2"></i> Cancel
                                </a>
                                
                                <button type="submit" class="btn-primary flex items-center justify-center">
                                    <i class="fas fa-paper-plane mr-2"></i> Submit Application
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Simple Image Preview Modal -->
    <div class="modal-overlay" id="imageModal" onclick="closeModal()">
        <div class="modal-content">
            <img id="modalImage" class="modal-image" alt="Preview">
        </div>
    </div>

    <!-- Consistent Footer -->
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
                        <li><a href="../../../citizen_dashboard/citizen_dashboard.php" class="hover:text-[#4a90e2] transition-colors">Dashboard</a></li>
                        <li><a href="../market_services.php" class="hover:text-[#4a90e2] transition-colors">Market Services</a></li>
                        <li><a href="#" class="hover:text-[#4a90e2] transition-colors">My Rentals</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                    <ul class="space-y-3 text-gray-600">
                        <li><i class="fas fa-phone mr-2 text-gray-400"></i> (02) 1234-5680</li>
                        <li><i class="fas fa-envelope mr-2 text-gray-400"></i> market@goserveph.gov.ph</li>
                        <li><i class="fas fa-clock mr-2 text-gray-400"></i> Mon-Fri: 8AM - 5PM</li>
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

    <script>
    // File selection handler
    function handleFileSelect(input, displayId) {
        const file = input.files[0];
        const displayDiv = document.getElementById(displayId);
        const fileNameDiv = document.getElementById(displayId.replace('-display', '-name'));
        const fileSizeDiv = document.getElementById(displayId.replace('-display', '-size'));
        
        if (file) {
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please upload JPG, JPEG or PNG images only.');
                input.value = '';
                return;
            }
            
            // Validate file size (5MB)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('File is too large. Maximum size is 5MB.');
                input.value = '';
                return;
            }
            
            // Show file display
            displayDiv.classList.add('show');
            fileNameDiv.textContent = file.name;
            fileSizeDiv.textContent = formatFileSize(file.size);
        } else {
            displayDiv.classList.remove('show');
        }
    }
    
    // Remove file
    function removeFile(inputId, displayId) {
        const input = document.getElementById(inputId);
        const displayDiv = document.getElementById(displayId);
        
        if (input && displayDiv) {
            input.value = '';
            displayDiv.classList.remove('show');
        }
    }
    
    // Preview file in modal
    function previewFile(inputId) {
        const input = document.getElementById(inputId);
        
        if (!input) {
            console.error('Input element not found:', inputId);
            alert('File input not found.');
            return;
        }
        
        if (!input.files || input.files.length === 0) {
            alert('No file selected. Please upload a file first.');
            return;
        }
        
        const file = input.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            
            if (!modalImage) {
                console.error('Modal image element not found');
                alert('Preview feature not available.');
                return;
            }
            
            modalImage.src = e.target.result;
            modalImage.alt = file.name;
            
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        };
        
        reader.onerror = function() {
            alert('Error reading file. Please try again.');
        };
        
        reader.readAsDataURL(file);
    }
    
    // Close modal
    function closeModal() {
        const modal = document.getElementById('imageModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
    
    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
    
    // Checkbox toggle function
    function toggleCheckbox(checkboxDiv) {
        const checkbox = checkboxDiv.nextElementSibling.nextElementSibling;
        checkboxDiv.classList.toggle('checked');
        checkbox.checked = !checkbox.checked;
    }
    
    // Auto-select district based on barangay selection
    document.addEventListener('DOMContentLoaded', function() {
        const barangaySelect = document.querySelector('select[name="barangay"]');
        const districtSelect = document.getElementById('home_district');
        
        if (barangaySelect && districtSelect) {
            barangaySelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const optgroup = selectedOption.parentElement;
                
                if (optgroup && optgroup.tagName === 'OPTGROUP') {
                    const label = optgroup.label;
                    const districtNum = label.replace('District ', '');
                    if (districtNum && !isNaN(districtNum)) {
                        districtSelect.value = districtNum;
                    }
                }
            });
        }
        
        // Form validation and submission
        const form = document.getElementById('rentalForm');
        const submitButton = form ? form.querySelector('button[type="submit"]') : null;
        
        if (form && submitButton) {
            // Store original button content
            const originalButtonHTML = submitButton.innerHTML;
            let isSubmitting = false;
            
            form.addEventListener('submit', function(e) {
                // If already submitting, prevent duplicate submission
                if (isSubmitting) {
                    e.preventDefault();
                    return;
                }
                
                let isValid = true;
                
                // Check checkboxes
                const checkboxes = document.querySelectorAll('input[type="checkbox"]');
                let allChecked = true;
                
                checkboxes.forEach(cb => {
                    if (!cb.checked) {
                        allChecked = false;
                        const parent = cb.previousElementSibling.previousElementSibling;
                        if (parent) {
                            parent.style.borderColor = '#ef4444';
                            parent.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                        }
                    }
                });
                
                if (!allChecked) {
                    e.preventDefault();
                    alert('Please accept all terms and conditions.');
                    isValid = false;
                    return;
                }
                
                // Check file uploads
                const fileInputs = document.querySelectorAll('input[type="file"]');
                fileInputs.forEach(input => {
                    if (!input.files || input.files.length === 0) {
                        isValid = false;
                        const wrapper = input.closest('.file-input-wrapper');
                        if (wrapper) {
                            const button = wrapper.querySelector('.file-input-button');
                            if (button) {
                                button.style.borderColor = '#ef4444';
                                button.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                            }
                        }
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please complete all required fields and upload all documents.');
                    return;
                }
                
                // If validation passes, show loading state
                isSubmitting = true;
                submitButton.disabled = true;
                submitButton.innerHTML = `
                    <div class="flex items-center justify-center">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                        Processing...
                    </div>
                `;
                submitButton.style.opacity = '0.8';
                submitButton.style.cursor = 'not-allowed';
                
                // Allow the form to submit normally
                // The page will redirect after successful submission
            });
        }
        
        // Reset checkbox styling when clicked
        document.querySelectorAll('.custom-checkbox').forEach(checkbox => {
            checkbox.addEventListener('click', function() {
                setTimeout(() => {
                    if (this.classList.contains('checked')) {
                        this.style.borderColor = '';
                        this.style.backgroundColor = '';
                    }
                }, 100);
            });
        });

        // Reset file button styling on file input
        document.querySelectorAll('.file-input').forEach(input => {
            input.addEventListener('change', function() {
                const wrapper = this.closest('.file-input-wrapper');
                if (wrapper) {
                    const button = wrapper.querySelector('.file-input-button');
                    if (button) {
                        button.style.borderColor = '';
                        button.style.backgroundColor = '';
                    }
                }
            });
        });
        
        // Make sure modal close works properly
        const modal = document.getElementById('imageModal');
        if (modal) {
            // Close modal when clicking on the overlay (not the image)
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
            
            // Prevent clicks inside modal from closing it
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        }
        
        // Add click handlers to file input buttons for better UX
        document.querySelectorAll('.file-input-button').forEach(button => {
            button.addEventListener('click', function() {
                const wrapper = this.closest('.file-input-wrapper');
                if (wrapper) {
                    const fileInput = wrapper.querySelector('.file-input');
                    if (fileInput) {
                        fileInput.click();
                    }
                }
            });
        });
    });
</script> 
</body>
</html>