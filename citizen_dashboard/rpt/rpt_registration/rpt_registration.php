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

// Get base URL for background image - FIXED VERSION
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . $host;
$bg_image_path = $base_url . '/revenue2/Login/images/gsmbg.png';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Registration - RPT Services | GoServePH</title>
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

        /* Modal Styles */
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
    <?php include '../../navbar.php'; ?>
    
    <div class="main-content-wrapper">
        <main class="flex-1 py-8">
            <div class="form-container mx-auto px-4">
                <!-- Page Header -->
                <div class="header-box mb-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                        <a href="../rpt_services.php" class="back-button self-start">
                            <i class="fas fa-arrow-left"></i> Back to RPT Services
                        </a>
                        
                        <div class="text-center">
                            <h1 class="text-3xl font-bold text-gray-900 mb-2">Property Registration</h1>
                            <p class="text-gray-600">Register your property for Real Property Tax assessment</p>
                        </div>
                        
                        <div class="text-right">
                            <div class="stall-badge inline-block" style="background: linear-gradient(135deg, var(--primary), #3a7bd5); color: white; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 0.75rem;">
                                <i class="fas fa-home mr-1"></i>
                                New Registration
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
                    <form method="POST" enctype="multipart/form-data" class="space-y-8" id="propertyForm">
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
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                                    Birthdate
                                </label>
                                <input type="date" name="birthdate"
                                    value="<?php echo htmlspecialchars($form_data['birthdate']); ?>"
                                    class="input-field">
                            </div>
                            
                            <!-- Sex -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Sex
                                </label>
                                <select name="sex" class="input-field">
                                    <option value="">Select Sex</option>
                                    <option value="male" <?php echo ($form_data['sex'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($form_data['sex'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($form_data['sex'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <!-- Marital Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Marital Status
                                </label>
                                <select name="marital_status" class="input-field">
                                    <option value="">Select Marital Status</option>
                                    <option value="single" <?php echo ($form_data['marital_status'] ?? '') == 'single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="married" <?php echo ($form_data['marital_status'] ?? '') == 'married' ? 'selected' : ''; ?>>Married</option>
                                    <option value="divorced" <?php echo ($form_data['marital_status'] ?? '') == 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="widowed" <?php echo ($form_data['marital_status'] ?? '') == 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                                </select>
                            </div>
                            
                            <!-- Email -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" name="email" required 
                                    value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                    class="input-field" placeholder="your@email.com">
                            </div>
                            
                            <!-- Phone -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Phone Number <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="phone" required 
                                    value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                    class="input-field" placeholder="09171234567">
                            </div>
                            
                            <!-- TIN Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    TIN Number
                                </label>
                                <input type="text" name="tin_number"
                                    value="<?php echo htmlspecialchars($form_data['tin_number'] ?? ''); ?>"
                                    class="input-field" placeholder="Enter TIN (optional)">
                            </div>
                        </div>

                        <!-- Address Information -->
                        <div class="section-header mt-8">
                            <h2 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-home mr-3 text-primary"></i>
                                Home Address
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">Your complete home address</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- House Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    House Number <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="house_number" required
                                    value="<?php echo htmlspecialchars($form_data['house_number']); ?>"
                                    class="input-field" placeholder="123">
                            </div>
                            
                            <!-- Street -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Street <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="street" required
                                    value="<?php echo htmlspecialchars($form_data['street']); ?>"
                                    class="input-field" placeholder="Street name">
                            </div>
                            
                            <!-- Barangay -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Barangay <span class="text-red-500">*</span>
                                </label>
                                <select name="barangay" required class="input-field barangay-select">
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
                                    District <span class="text-red-500">*</span>
                                </label>
                                <select name="district" required class="input-field" id="home_district">
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
                                    class="input-field" placeholder="Province">
                                <p class="text-xs text-gray-500 mt-1">Fixed to Metro Manila</p>
                            </div>
                            
                            <!-- ZIP Code -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    ZIP Code <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="zip_code" required
                                    value="<?php echo htmlspecialchars($form_data['zip_code']); ?>"
                                    class="input-field" placeholder="1100">
                                <p class="text-xs text-gray-500 mt-1">Quezon City ZIP codes: 1100-1128</p>
                            </div>
                        </div>

                        <!-- Property Location -->
                        <div class="section-header mt-8">
                            <h2 class="text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-map-marker-alt mr-3 text-primary"></i>
                                Property Location
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">Details about your property</p>
                        </div>
                        
                        <div class="space-y-4">
                            <!-- Lot Location -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Lot Location <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="property_lot_location" required
                                    value="<?php echo htmlspecialchars($form_data['property_lot_location'] ?? ''); ?>"
                                    class="input-field" placeholder="Lot 5, Block 2">
                                <p class="text-gray-500 text-xs mt-1">Physical location identifier of your property</p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Property Barangay -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Property Barangay <span class="text-red-500">*</span>
                                    </label>
                                    <select name="property_barangay" required class="input-field barangay-select" id="property_barangay">
                                        <option value="">Select Barangay</option>
                                        <?php foreach ($qc_barangays as $district_num => $barangays): ?>
                                        <optgroup label="District <?php echo $district_num; ?>" data-district="<?php echo $district_num; ?>">
                                            <?php foreach ($barangays as $barangay): ?>
                                            <option value="<?php echo htmlspecialchars($barangay); ?>" 
                                                <?php echo ($form_data['property_barangay'] ?? '') == $barangay ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($barangay); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Property District -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Property District <span class="text-red-500">*</span>
                                    </label>
                                    <select name="property_district" required class="input-field" id="property_district">
                                        <option value="">Select District</option>
                                        <?php for ($i = 1; $i <= 6; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo ($form_data['property_district'] ?? '') == $i ? 'selected' : ''; ?>>
                                                District <?php echo $i; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <!-- Property City -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Property City <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="property_city" required 
                                        value="<?php echo htmlspecialchars($form_data['property_city'] ?? 'Quezon City'); ?>"
                                        class="input-field" placeholder="Quezon City">
                                    <p class="text-xs text-gray-500 mt-1">Fixed to Quezon City</p>
                                </div>
                                
                                <!-- Property Province -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Property Province <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="property_province" required 
                                        value="<?php echo htmlspecialchars($form_data['property_province'] ?? 'Metro Manila'); ?>"
                                        class="input-field" placeholder="Metro Manila">
                                    <p class="text-xs text-gray-500 mt-1">Fixed to Metro Manila</p>
                                </div>
                                
                                <!-- Property ZIP Code -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Property ZIP Code <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="property_zip_code" required
                                        value="<?php echo htmlspecialchars($form_data['property_zip_code'] ?? ''); ?>"
                                        class="input-field" placeholder="1100">
                                    <p class="text-xs text-gray-500 mt-1">Quezon City ZIP codes: 1100-1128</p>
                                </div>
                                
                                <!-- Has Building -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Has Building? <span class="text-red-500">*</span>
                                    </label>
                                    <div class="flex gap-4 mt-2">
                                        <label class="flex items-center">
                                            <input type="radio" name="has_building" value="yes" required 
                                                <?php echo (isset($form_data['has_building']) && $form_data['has_building'] == 'yes') ? 'checked' : ''; ?>
                                                class="mr-2">
                                            <span>Yes</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="has_building" value="no"
                                                <?php echo (isset($form_data['has_building']) && $form_data['has_building'] == 'no') ? 'checked' : ''; ?>
                                                class="mr-2">
                                            <span>No</span>
                                        </label>
                                    </div>
                                </div>
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

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Barangay Certificate -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Barangay Certificate <span class="text-red-500">*</span>
                                </label>
                                <div class="file-input-wrapper">
                                    <div class="file-input-button">
                                        <span>Choose File</span>
                                        <i class="fas fa-paperclip"></i>
                                    </div>
                                    <input type="file" name="barangay_certificate" id="barangay_certificate" 
                                           accept="image/jpeg,image/jpg,image/png" 
                                           class="file-input" required
                                           onchange="handleFileSelect(this, 'barangay-certificate-display')">
                                </div>
                                <div id="barangay-certificate-display" class="file-display">
                                    <div class="file-name" id="barangay-certificate-name"></div>
                                    <div class="file-size" id="barangay-certificate-size"></div>
                                    <div class="file-actions">
                                        <button type="button" class="file-action-btn preview" onclick="previewFile('barangay_certificate')">Preview</button>
                                        <button type="button" class="file-action-btn remove" onclick="removeFile('barangay_certificate', 'barangay-certificate-display')">Remove</button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Proof of Ownership -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Proof of Ownership <span class="text-red-500">*</span>
                                </label>
                                <div class="file-input-wrapper">
                                    <div class="file-input-button">
                                        <span>Choose File</span>
                                        <i class="fas fa-paperclip"></i>
                                    </div>
                                    <input type="file" name="ownership_proof" id="ownership_proof"
                                           accept="image/jpeg,image/jpg,image/png" 
                                           class="file-input" required
                                           onchange="handleFileSelect(this, 'ownership-proof-display')">
                                </div>
                                <div id="ownership-proof-display" class="file-display">
                                    <div class="file-name" id="ownership-proof-name"></div>
                                    <div class="file-size" id="ownership-proof-size"></div>
                                    <div class="file-actions">
                                        <button type="button" class="file-action-btn preview" onclick="previewFile('ownership_proof')">Preview</button>
                                        <button type="button" class="file-action-btn remove" onclick="removeFile('ownership_proof', 'ownership-proof-display')">Remove</button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Valid ID -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Valid ID <span class="text-red-500">*</span>
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
                            
                            <!-- Survey Plan -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Survey Plan <span class="text-red-500">*</span>
                                </label>
                                <div class="file-input-wrapper">
                                    <div class="file-input-button">
                                        <span>Choose File</span>
                                        <i class="fas fa-paperclip"></i>
                                    </div>
                                    <input type="file" name="survey_plan" id="survey_plan"
                                           accept="image/jpeg,image/jpg,image/png" 
                                           class="file-input" required
                                           onchange="handleFileSelect(this, 'survey-plan-display')">
                                </div>
                                <div id="survey-plan-display" class="file-display">
                                    <div class="file-name" id="survey-plan-name"></div>
                                    <div class="file-size" id="survey-plan-size"></div>
                                    <div class="file-actions">
                                        <button type="button" class="file-action-btn preview" onclick="previewFile('survey_plan')">Preview</button>
                                        <button type="button" class="file-action-btn remove" onclick="removeFile('survey_plan', 'survey-plan-display')">Remove</button>
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
                                        I agree to allow property inspection by authorized assessors.
                                    </label>
                                    <input type="checkbox" name="terms_inspection" required class="hidden">
                                </div>
                                
                                <div class="checkbox-container">
                                    <div class="custom-checkbox" onclick="toggleCheckbox(this)">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <label class="checkbox-label">
                                        I understand that property tax will be calculated based on assessment results.
                                    </label>
                                    <input type="checkbox" name="terms_tax" required class="hidden">
                                </div>
                                
                                <div class="checkbox-container">
                                    <div class="custom-checkbox" onclick="toggleCheckbox(this)">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <label class="checkbox-label">
                                        I understand that this application is subject to approval and verification.
                                    </label>
                                    <input type="checkbox" name="terms_approval" required class="hidden">
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-8 border-t border-gray-200">
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-info-circle mr-1"></i>
                                Inspection will be scheduled within 7 days after submission
                            </div>
                            
                            <div class="flex gap-4 form-actions">
                                <a href="../rpt_services.php" 
                                   class="btn-secondary flex items-center justify-center">
                                    <i class="fas fa-times mr-2"></i> Cancel
                                </a>
                                
                                <button type="submit" class="btn-primary flex items-center justify-center">
                                    <i class="fas fa-paper-plane mr-2"></i> Submit Registration
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal-overlay" id="imageModal" onclick="closeModal()">
        <div class="modal-content">
            <img id="modalImage" class="modal-image" alt="Preview">
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
                        <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Services</a></li>
                        <li><a href="#" class="hover:text-[#4a90e2] transition-colors">My Applications</a></li>
                        <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Settings</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                    <ul class="space-y-3 text-gray-600">
                        <li><i class="fas fa-phone mr-2 text-gray-400"></i> (02) 8123 4567</li>
                        <li><i class="fas fa-envelope mr-2 text-gray-400"></i> support@goserveph.gov.ph</li>
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
        // For property barangay selection
        const propertyBarangay = document.getElementById('property_barangay');
        const propertyDistrict = document.getElementById('property_district');
        
        if (propertyBarangay && propertyDistrict) {
            propertyBarangay.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const optgroup = selectedOption.parentElement;
                
                if (optgroup && optgroup.tagName === 'OPTGROUP') {
                    const districtNum = optgroup.getAttribute('data-district');
                    if (districtNum) {
                        propertyDistrict.value = districtNum;
                    }
                }
            });
        }
        
        // For home address barangay selection
        const homeBarangay = document.querySelector('select[name="barangay"]');
        const homeDistrict = document.getElementById('home_district');
        
        if (homeBarangay && homeDistrict) {
            homeBarangay.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const optgroup = selectedOption.parentElement;
                
                if (optgroup && optgroup.tagName === 'OPTGROUP') {
                    const label = optgroup.label;
                    const districtNum = label.replace('District ', '');
                    if (districtNum && !isNaN(districtNum)) {
                        homeDistrict.value = districtNum;
                    }
                }
            });
        }
        
        // Form validation and submission
        const form = document.getElementById('propertyForm');
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