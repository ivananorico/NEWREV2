<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Citizen';
$user_email = $_SESSION['user_email'] ?? '';

// Database connection
require_once '../Login/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user data directly from database
    $query = "SELECT * FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $user_data = [];
    }
} catch (Exception $e) {
    error_log("Settings Error: " . $e->getMessage());
    $user_data = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_personal'])) {
        updatePersonalInfo($db, $user_id, $_POST);
    } elseif (isset($_POST['update_password'])) {
        updatePassword($db, $user_id, $_POST);
    }
}

function updatePersonalInfo($db, $user_id, $data) {
    $query = "UPDATE users 
              SET first_name = :first_name,
                  last_name = :last_name,
                  middle_name = :middle_name,
                  suffix = :suffix,
                  birthdate = :birthdate,
                  mobile = :mobile,
                  address = :address,
                  house_number = :house_number,
                  street = :street,
                  barangay = :barangay
              WHERE id = :user_id";

    $stmt = $db->prepare($query);
    
    // Bind parameters
    $stmt->bindParam(":first_name", $data['first_name']);
    $stmt->bindParam(":last_name", $data['last_name']);
    $stmt->bindParam(":middle_name", $data['middle_name']);
    $stmt->bindParam(":suffix", $data['suffix']);
    $stmt->bindParam(":birthdate", $data['birthdate']);
    $stmt->bindParam(":mobile", $data['mobile']);
    $stmt->bindParam(":address", $data['address']);
    $stmt->bindParam(":house_number", $data['house_number']);
    $stmt->bindParam(":street", $data['street']);
    $stmt->bindParam(":barangay", $data['barangay']);
    $stmt->bindParam(":user_id", $user_id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Personal information updated successfully!';
        // Update session name
        $_SESSION['user_name'] = $data['first_name'] . ' ' . $data['last_name'];
    } else {
        $_SESSION['error_message'] = 'Failed to update personal information.';
    }
    
    header('Location: settings.php');
    exit();
}

function updatePassword($db, $user_id, $data) {
    // First get current password hash
    $query = "SELECT password_hash FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify current password
        if (password_verify($data['current_password'], $user['password_hash'])) {
            if ($data['new_password'] === $data['confirm_password']) {
                // Update password
                $new_password_hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
                
                $update_query = "UPDATE users SET password_hash = :password_hash WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':password_hash', $new_password_hash);
                $update_stmt->bindParam(':user_id', $user_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['success_message'] = 'Password updated successfully!';
                } else {
                    $_SESSION['error_message'] = 'Failed to update password.';
                }
            } else {
                $_SESSION['error_message'] = 'New passwords do not match.';
            }
        } else {
            $_SESSION['error_message'] = 'Current password is incorrect.';
        }
    } else {
        $_SESSION['error_message'] = 'User not found.';
    }
    
    header('Location: settings.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Settings</h1>
                <p class="text-gray-600">Manage your account settings and personal information</p>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Sidebar - Navigation -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-sm border p-4">
                        <nav class="space-y-2">
                            <a href="#personal" class="flex items-center space-x-3 p-3 bg-blue-50 text-blue-600 rounded-lg font-medium">
                                <i class="fas fa-user-circle w-5"></i>
                                <span>Personal Information</span>
                            </a>
                            <a href="#security" class="flex items-center space-x-3 p-3 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">
                                <i class="fas fa-shield-alt w-5"></i>
                                <span>Security</span>
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Right Content -->
                <div class="lg:col-span-2">
                    <!-- Personal Information Section -->
                    <div id="personal" class="bg-white rounded-lg shadow-sm border p-6 mb-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Personal Information</h2>
                        
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="update_personal" value="1">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($user_data['middle_name'] ?? ''); ?>" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                                    <select name="suffix" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select Suffix</option>
                                        <option value="Jr." <?php echo ($user_data['suffix'] ?? '') === 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                        <option value="Sr." <?php echo ($user_data['suffix'] ?? '') === 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                        <option value="II" <?php echo ($user_data['suffix'] ?? '') === 'II' ? 'selected' : ''; ?>>II</option>
                                        <option value="III" <?php echo ($user_data['suffix'] ?? '') === 'III' ? 'selected' : ''; ?>>III</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50" readonly>
                                    <p class="text-xs text-gray-500 mt-1">Email cannot be changed</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number *</label>
                                    <input type="tel" name="mobile" value="<?php echo htmlspecialchars($user_data['mobile'] ?? ''); ?>" placeholder="0912 345 6789" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate</label>
                                <input type="date" name="birthdate" value="<?php echo htmlspecialchars($user_data['birthdate'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>

                            <!-- Address Information -->
                            <div class="border-t pt-4 mt-4">
                                <h3 class="text-lg font-semibold text-gray-800 mb-3">Address Information</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">House Number</label>
                                        <input type="text" name="house_number" value="<?php echo htmlspecialchars($user_data['house_number'] ?? ''); ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Street</label>
                                        <input type="text" name="street" value="<?php echo htmlspecialchars($user_data['street'] ?? ''); ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Barangay</label>
                                        <input type="text" name="barangay" value="<?php echo htmlspecialchars($user_data['barangay'] ?? ''); ?>" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Complete Address</label>
                                    <textarea name="address" rows="3" 
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                              placeholder="Full address including city and province"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="flex justify-end pt-4">
                                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Security Section -->
                    <div id="security" class="bg-white rounded-lg shadow-sm border p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Security</h2>
                        
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="update_password" value="1">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                <input type="password" name="current_password" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                <input type="password" name="new_password" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                <input type="password" name="confirm_password" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                    Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6 mt-12">
        <div class="container mx-auto px-6">
            <div class="flex flex-col lg:flex-row justify-between items-center">
                <div class="text-center lg:text-left mb-4 lg:mb-0">
                    <h3 class="text-lg font-bold mb-2">GoServePH Citizen Portal</h3>
                    <p class="text-sm opacity-90">
                        Streamlining government services for Filipino citizens
                    </p>
                </div>
                <div class="flex space-x-4 text-sm">
                    <a href="#" class="hover:underline">Help Center</a>
                    <span>|</span>
                    <a href="#" class="hover:underline">Privacy Policy</a>
                    <span>|</span>
                    <a href="#" class="hover:underline">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scroll for navigation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        });
    </script>
</body>
</html>