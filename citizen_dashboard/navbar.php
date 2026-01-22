<?php
// revenue2/citizen_dashboard/navbar.php

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only redirect if user_id is not set
if (!isset($_SESSION['user_id'])) {
    // Get base URL dynamically
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . "://" . $host;
    
    // Redirect to login page
    $login_url = $base_url . '/revenue2/index.php';
    header('Location: ' . $login_url);
    exit();
}

// Handle logout if logout parameter is set
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    // Destroy all session data
    $_SESSION = array();
    
    // If it's desired to kill the session, also delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Finally, destroy the session
    session_destroy();
    
    // Get base URL dynamically for redirect
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . "://" . $host;
    
    // Redirect to login page
    $login_url = $base_url . '/revenue2/index.php';
    header('Location: ' . $login_url);
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Citizen';
$user_email = $_SESSION['user_email'] ?? '';

// Define dynamic paths that work for both localhost and domain
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . "://" . $host;

$logo_path = $base_url . '/revenue2/citizen_dashboard/images/GSM_logo.png';
$dashboard_path = $base_url . '/revenue2/citizen_dashboard/citizen_dashboard.php';
$settings_path = $base_url . '/revenue2/citizen_dashboard/settings.php';

// JavaScript for logout confirmation with dynamic paths
$logout_js = "
<script>
function confirmLogout() {
    if (confirm('Are you sure you want to logout?')) {
        // Use current path with logout parameter
        var currentUrl = window.location.href;
        // Remove existing query parameters if any
        var baseUrl = currentUrl.split('?')[0];
        // Check if baseUrl already has query parameters
        var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
        window.location.href = baseUrl + separator + 'logout=true';
    }
}
</script>
";
?>
<?php echo $logout_js; ?>

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
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    font-family: inherit;
    font-size: inherit;
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
                    <!-- Logo Image -->
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" 
                         alt="GoServePH Logo" 
                         class="logo-img"
                         onerror="console.error('Failed to load image:', this.src); this.onerror=null; this.src='<?php echo $base_url; ?>/revenue2/citizen_dashboard/images/GSM_logo.png';">
                    
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
                        <button onclick="confirmLogout()" class="dropdown-link logout">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</nav>