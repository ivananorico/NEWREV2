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
    'city' => $user['city'] ?? 'Quezon City',
    'province' => $user['province'] ?? 'Metro Manila',
    'zip_code' => $user['zip_code'] ?? '',
    'birthdate' => $user['birthdate'] ?? ''
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
            
            // 1. Insert into rental_registration (23 columns total)
            // Columns that will be NULL: interview_date, interviewer, interview_notes, 
            // payment_date, reference_number, remarks, correction_notes
            $registration_stmt = $pdo->prepare("
                INSERT INTO rental_registration (
                    user_id, stall_id, map_id, stall_name, stall_class,
                    stall_rights_amount, monthly_rent, security_bond, total_amount_due,
                    barangay_clearance, id_photo_2x2, valid_id,
                    application_status, stall_rights_no
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            
            // 2. Handle file uploads
            $uploaded_files = [];
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
            
            $registration_folder = $month_folder . $stall_rights_no . '/';
            if (!file_exists($registration_folder)) {
                mkdir($registration_folder, 0777, true);
            }
            
            $document_types = [
                'barangay_clearance' => 'Barangay Clearance',
                'id_photo_2x2' => '2x2 ID Photo',
                'valid_id' => 'Valid ID'
            ];
            
            $upload_paths = [];
            
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
                    $clean_ref = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $stall_rights_no);
                    $unique_filename = $field_name . '_' . $clean_ref . '_' . time() . '.' . $file_extension;
                    $file_path = $registration_folder . $unique_filename;
                    
                    $relative_path = 'documents/market/' . $year . '/' . $month . '/' . $stall_rights_no . '/' . $unique_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        $upload_paths[$field_name] = $relative_path;
                        $uploaded_files[] = $document_type;
                    } else {
                        throw new Exception("Failed to upload $document_type");
                    }
                } else {
                    throw new Exception("$document_type is required");
                }
            }
            
            // Execute rental registration insert (13 values for 13 columns specified)
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
            
            // 3. Insert into renter_owner (22 columns total)
            $owner_stmt = $pdo->prepare("
                INSERT INTO renter_owner (
                    registration_id, user_id,
                    first_name, middle_name, last_name, suffix,
                    email, mobile, telephone,
                    house_number, street, barangay, city, province, zip_code,
                    birth_date, gender,
                    emergency_name, emergency_contact,
                    renter_code, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $city = $_POST['city'] ?? 'Quezon City';
            $province = $_POST['province'] ?? 'Metro Manila';
            $renter_code = 'RENTER-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Execute renter_owner insert (21 values for 21 columns + 1 hardcoded)
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
                $city,
                $province,
                $_POST['zip_code'] ?? null,
                $_POST['birthdate'],
                $_POST['gender'] ?? null,
                $_POST['emergency_name'] ?? null,
                $_POST['emergency_contact'] ?? null,
                $renter_code
                // Status is hardcoded as 'pending' in SQL
            ]);
            
            $renter_id = $pdo->lastInsertId();
            
            // 4. Insert into rent_stall (12 columns total)
            $rent_stall_stmt = $pdo->prepare("
                INSERT INTO rent_stall (
                    renter_id, registration_id, stall_id, map_id,
                    business_name, business_type, stall_rights_no,
                    start_date, monthly_rent, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'active')
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
                      <span class='text-blue-700 font-medium'><i class='fas fa-info-circle mr-1'></i> Status: <strong>Pending Interview</strong></span>";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Stall Rental Application</title>
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
                <a href="market_portal_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Market Stall Rental Application</h1>
                    <p class="text-gray-600">Apply for stall rental at <?php echo htmlspecialchars($stall['name']); ?></p>
                </div>
            </div>
            
            <!-- Stall Information Card -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                <h3 class="font-semibold text-blue-800 mb-3 flex items-center">
                    <i class="fas fa-store mr-2"></i>
                    Selected Stall Details
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <div class="text-xs text-gray-500">Stall Name</div>
                        <div class="font-semibold"><?php echo htmlspecialchars($stall['name']); ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Stall Class</div>
                        <div class="font-semibold"><?php echo htmlspecialchars($stall['class_name']); ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Monthly Rent</div>
                        <div class="font-semibold text-green-600">₱<?php echo number_format($stall['price'], 2); ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Stall Rights Fee</div>
                        <div class="font-semibold text-red-600">₱<?php echo number_format($stall['class_price'], 2); ?></div>
                    </div>
                </div>
                <div class="mt-3 text-sm text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Security bond: ₱<?php echo number_format($stall['price'] * 2, 2); ?> (2 months deposit)
                </div>
            </div>
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
                                <option value="Jr." <?php echo ($form_data['suffix'] ?? '') == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                <option value="Sr." <?php echo ($form_data['suffix'] ?? '') == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                <option value="II" <?php echo ($form_data['suffix'] ?? '') == 'II' ? 'selected' : ''; ?>>II</option>
                                <option value="III" <?php echo ($form_data['suffix'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                                <option value="IV" <?php echo ($form_data['suffix'] ?? '') == 'IV' ? 'selected' : ''; ?>>IV</option>
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
                                <option value="male" <?php echo ($form_data['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($form_data['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($form_data['gender'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
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
                                value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="09171234567">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telephone Number</label>
                            <input type="text" name="telephone"
                                value="<?php echo htmlspecialchars($form_data['telephone'] ?? ''); ?>"
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
                                value="<?php echo htmlspecialchars($form_data['business_name'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                placeholder="Enter your business name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Business Type</label>
                            <select name="business_type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="">Select Business Type</option>
                                <option value="food" <?php echo ($form_data['business_type'] ?? '') == 'food' ? 'selected' : ''; ?>>Food & Groceries</option>
                                <option value="clothing" <?php echo ($form_data['business_type'] ?? '') == 'clothing' ? 'selected' : ''; ?>>Clothing & Apparel</option>
                                <option value="electronics" <?php echo ($form_data['business_type'] ?? '') == 'electronics' ? 'selected' : ''; ?>>Electronics</option>
                                <option value="services" <?php echo ($form_data['business_type'] ?? '') == 'services' ? 'selected' : ''; ?>>Services</option>
                                <option value="others" <?php echo ($form_data['business_type'] ?? '') == 'others' ? 'selected' : ''; ?>>Others</option>
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
                                value="<?php echo htmlspecialchars($form_data['emergency_name'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Number</label>
                            <input type="text" name="emergency_contact"
                                value="<?php echo htmlspecialchars($form_data['emergency_contact'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                placeholder="09171234567">
                        </div>
                    </div>
                </div>

                <!-- Documents Upload Section -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-file-upload text-purple-500 mr-2"></i>
                        Required Documents Upload
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
                            </ul>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Barangay Clearance -->
                            <div class="space-y-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <span class="text-red-500">*</span> Barangay Clearance
                                </label>
                                <div class="border border-gray-300 rounded-lg p-3 hover:border-blue-500 transition-colors">
                                    <input type="file" 
                                           name="barangay_clearance" 
                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                           required
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
                                    <span class="text-red-500">*</span> 2x2 ID Photo
                                </label>
                                <div class="border border-gray-300 rounded-lg p-3 hover:border-blue-500 transition-colors">
                                    <input type="file" 
                                           name="id_photo_2x2" 
                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                           required
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
                                    <span class="text-red-500">*</span> Valid Government ID
                                </label>
                                <div class="border border-gray-300 rounded-lg p-3 hover:border-blue-500 transition-colors">
                                    <input type="file" 
                                           name="valid_id" 
                                           accept=".jpg,.jpeg,.png,image/jpeg,image/jpg,image/png"
                                           required
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

                <!-- Terms and Conditions -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-700 mb-3">Terms and Conditions</h4>
                    <div class="space-y-2 text-sm text-gray-600">
                        <div class="flex items-start">
                            <input type="checkbox" name="terms_agreed" id="terms_agreed" 
                                   class="mt-1 mr-2" required>
                            <label for="terms_agreed">
                                I certify that all information provided is true and correct. I understand that false information may lead to application rejection.
                            </label>
                        </div>
                        <div class="flex items-start">
                            <input type="checkbox" name="interview_agreed" id="interview_agreed" 
                                   class="mt-1 mr-2" required>
                            <label for="interview_agreed">
                                I agree to undergo an interview process with the market administrator.
                            </label>
                        </div>
                        <div class="flex items-start">
                            <input type="checkbox" name="payment_agreed" id="payment_agreed" 
                                   class="mt-1 mr-2" required>
                            <label for="payment_agreed">
                                I understand that stall rights fee and security bond must be paid upon approval before contract signing.
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-between">
                    <a href="apply_rental.php?map_id=<?php echo $map_id; ?>" 
                       class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Stall Selection
                    </a>
                    
                    <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-lg transition duration-300 flex items-center">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Submit Application
                    </button>
                </div>
            </form>
        </div>

        <!-- Status Information Box -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h4 class="font-semibold text-blue-800 mb-3 flex items-center">
                <i class="fas fa-info-circle mr-2"></i>
                Application Process Flow
            </h4>
            <div class="text-blue-700 text-sm space-y-3">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center mr-3">
                        <span class="text-blue-700 font-bold">1</span>
                    </div>
                    <div>
                        <span class="font-medium">Pending</span> - Your application has been submitted and is awaiting interview schedule
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center mr-3">
                        <span class="text-blue-700 font-bold">2</span>
                    </div>
                    <div>
                        <span class="font-medium">Interviewed</span> - Interview completed with market administrator
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center mr-3">
                        <span class="text-blue-700 font-bold">3</span>
                    </div>
                    <div>
                        <span class="font-medium">Paying</span> - Stall rights fee payment required
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center mr-3">
                        <span class="text-blue-700 font-bold">4</span>
                    </div>
                    <div>
                        <span class="font-medium">Approved</span> - Contract signed and stall assigned
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
            
            // Check if all checkboxes are checked
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            for (let checkbox of checkboxes) {
                if (!checkbox.checked) {
                    e.preventDefault();
                    alert('Please agree to all terms and conditions.');
                    checkbox.focus();
                    break;
                }
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