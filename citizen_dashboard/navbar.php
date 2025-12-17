<?php
// revenue/citizen_dashboard/navbar.php

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only redirect if user_id is not set
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Citizen';
$user_email = $_SESSION['user_email'] ?? '';

// DEBUG: Let's see what's happening
$current_url = $_SERVER['REQUEST_URI'];
$script_path = $_SERVER['SCRIPT_FILENAME'];
$document_root = $_SERVER['DOCUMENT_ROOT'];

// Calculate the correct image path
// Image is at: revenue/citizen_dashboard/images/GSM_logo.png
// Navbar is at: revenue/citizen_dashboard/navbar.php

// Get the directory of the current page
$current_page = $_SERVER['SCRIPT_NAME']; // e.g., /revenue/citizen_dashboard/business/business_services.php
$current_dir = dirname($current_page); // e.g., /revenue/citizen_dashboard/business

// Image is in: /revenue/citizen_dashboard/images/GSM_logo.png
$image_dir = '/revenue/citizen_dashboard/images/GSM_logo.png';

// Calculate relative path from current directory to image
function calculateRelativePath($from, $to) {
    $from = explode('/', trim($from, '/'));
    $to = explode('/', trim($to, '/'));
    
    // Find common path
    $commonLength = 0;
    $minLength = min(count($from), count($to));
    for ($i = 0; $i < $minLength; $i++) {
        if ($from[$i] !== $to[$i]) {
            break;
        }
        $commonLength++;
    }
    
    // Go up from current location
    $upLevels = count($from) - $commonLength;
    $relativePath = str_repeat('../', $upLevels);
    
    // Go down to image
    for ($i = $commonLength; $i < count($to); $i++) {
        $relativePath .= $to[$i] . '/';
    }
    
    return rtrim($relativePath, '/');
}

// Calculate paths
$logout_path = '';
$dashboard_path = '';
$settings_path = '';
$logo_path = '';

if (strpos($current_url, '/revenue/citizen_dashboard/') !== false) {
    // We're in citizen_dashboard or its subdirectories
    $relative_path = substr($current_url, strpos($current_url, '/revenue/citizen_dashboard/') + 26);
    $dirs = explode('/', dirname($relative_path));
    $dirs = array_filter($dirs);
    $depth = count($dirs);
    
    if ($depth == 0) {
        // In citizen_dashboard root
        $logout_path = './logout.php';
        $dashboard_path = './citizen_dashboard.php';
        $settings_path = './settings.php';
        $logo_path = './images/GSM_logo.png'; // Same directory level
    } else {
        // In subdirectory
        $logout_path = str_repeat('../', $depth) . 'logout.php';
        $dashboard_path = str_repeat('../', $depth) . 'citizen_dashboard.php';
        $settings_path = str_repeat('../', $depth) . 'settings.php';
        $logo_path = str_repeat('../', $depth) . 'images/GSM_logo.png';
    }
} else {
    // Fallback
    $logout_path = 'logout.php';
    $dashboard_path = 'citizen_dashboard.php';
    $settings_path = 'settings.php';
    $logo_path = './images/GSM_logo.png';
}

// ALTERNATIVE: Use absolute URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$absolute_logo_path = $protocol . '://' . $host . '/revenue/citizen_dashboard/images/GSM_logo.png';

// Debug output - UNCOMMENT THIS TO SEE WHAT'S WRONG
echo "<!-- DEBUG INFO START -->";
echo "<!-- Current URL: " . htmlspecialchars($current_url) . " -->";
echo "<!-- Script Path: " . htmlspecialchars($script_path) . " -->";
echo "<!-- Current Dir: " . htmlspecialchars($current_dir) . " -->";
echo "<!-- Calculated Logo Path: " . htmlspecialchars($logo_path) . " -->";
echo "<!-- Absolute Logo Path: " . htmlspecialchars($absolute_logo_path) . " -->";
echo "<!-- Depth: " . (isset($depth) ? $depth : 'N/A') . " -->";
echo "<!-- DEBUG INFO END -->";

// Try using absolute path
$logo_path = $absolute_logo_path;

?>
<style>
:root {
    --primary: #4CAF50;
    --secondary: #4A90E2;
    --accent: #FDA811;
    --background: #FBFBFB;
}

.dropdown-container {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 0.5rem;
    width: 12rem;
    background-color: #ffffff;
    border-radius: 0.5rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease-in-out;
    z-index: 50;
}

.dropdown-container:hover .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-menu::before {
    content: '';
    position: absolute;
    top: -6px;
    right: 12px;
    width: 12px;
    height: 12px;
    background: #ffffff;
    transform: rotate(45deg);
    border-top: 1px solid #e5e7eb;
    border-left: 1px solid #e5e7eb;
}

.dropdown-link {
    display: block;
    padding: 0.75rem 1rem;
    color: #374151;
    text-decoration: none;
    transition: background-color 0.2s ease;
    border-radius: 0.25rem;
    margin: 0.25rem;
}

.dropdown-link:hover {
    background-color: #f3f4f6;
}

.dropdown-link.settings:hover {
    background-color: #eff6ff;
    color: #1e40af;
}

.dropdown-link.logout:hover {
    background-color: #fef2f2;
    color: #dc2626;
}

.divider {
    height: 1px;
    background-color: #e5e7eb;
    margin: 0.25rem 0.5rem;
}

.logo-img {
    height: 40px;
    width: auto;
    object-fit: contain;
}
</style>

<!-- Navigation Bar -->
<nav style="background-color: #FBFBFB; border-bottom: 0.2px solid #4A90E2;">
    <div class="container mx-auto px-6">
        <div class="flex justify-between items-center py-4">

            <!-- Logo and Brand -->
            <div class="flex items-center space-x-3">
                <a href="<?php echo htmlspecialchars($dashboard_path); ?>" class="flex items-center space-x-3 no-underline">
                    <!-- Logo Image - Using absolute path -->
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" 
                         alt="GoServePH Logo" 
                         class="logo-img"
                         onerror="console.error('Failed to load image:', this.src);">
                    
                    <div>
                        <h1 class="text-xl font-bold" style="word-spacing: -0.2em;">
                            <span style="color: #4A90E2;">Go</span><!--
                            --><span style="color: #4CAF50;">Serve</span><!--
                            --><span style="color: #4A90E2;">PH</span>
                        </h1>
                        <p class="text-xs" style="color: #6b7280;">Citizen Dashboard</p>
                    </div>
                </a>
            </div>

            <!-- User Info and Menu -->
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm font-semibold" style="color: #1f2937;">
                        Welcome, <?php echo htmlspecialchars($user_name); ?>
                    </p>
                    <p class="text-xs" style="color: #6b7280;">
                        <?php echo htmlspecialchars($user_email); ?>
                    </p>
                </div>

                <div class="dropdown-container">
                    <button class="w-10 h-10 rounded-full flex items-center justify-center transition-colors hover:bg-gray-200"
                        style="background-color: #e5e7eb;">
                        <i class="fas fa-user" style="color: #4b5563;"></i>
                    </button>

                    <!-- Dropdown Menu -->
                    <div class="dropdown-menu">
                        <a href="<?php echo htmlspecialchars($settings_path); ?>" class="dropdown-link settings">
                            <i class="fas fa-user-cog mr-2"></i>Profile & Settings
                        </a>
                        <div class="divider"></div>
                        <a href="<?php echo htmlspecialchars($logout_path); ?>" class="dropdown-link logout">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</nav>