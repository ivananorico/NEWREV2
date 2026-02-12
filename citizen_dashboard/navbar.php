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
    
    if (strpos($host, 'localhost') !== false) {
        $login_url = $protocol . "://" . $host . "/revenue2/index.php";
    } else {
        $login_url = $protocol . "://" . $host . "/index.php";
    }
    
    header('Location: ' . $login_url);
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Citizen';
$user_email = $_SESSION['user_email'] ?? '';

// Function to build correct URLs based on environment
function build_url($relative_path) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    
    if (strpos($host, 'localhost') !== false) {
        return $protocol . "://" . $host . "/revenue2" . $relative_path;
    } else {
        return $protocol . "://" . $host . $relative_path;
    }
}

// Build URLs for different resources
$logo_path = build_url('/citizen_dashboard/images/GSM_logo.png');
$dashboard_path = build_url('/citizen_dashboard/citizen_dashboard.php');
$settings_path = build_url('/citizen_dashboard/settings.php');
?>
<style>
:root {
    --primary: #4a90e2;
    --secondary: #9aa5b1;
    --accent: #4caf50;
    --background: #fbfbfb;
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
    transition: all 0.2s ease;
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
    background-color: #4a90e2;
    color: white;
    transform: translateX(5px);
}

.dropdown-link.settings:hover {
    background-color: #4caf50;
    color: white;
}

.dropdown-link.logout:hover {
    background-color: #ef4444;
    color: white;
}

.divider {
    height: 1px;
    background-color: #4a90e2;
    margin: 0.25rem 0.5rem;
    opacity: 0.3;
}

.logo-img {
    height: 40px;
    width: auto;
    object-fit: contain;
}

/* Navbar specific styles */
nav {
    background-color: #fbfbfb;
    border-bottom: 2px solid #4a90e2;
    box-shadow: 0 2px 10px rgba(74, 144, 226, 0.1);
}

.user-avatar {
    background: linear-gradient(135deg, #4a90e2, #4caf50);
    color: white;
    border: 2px solid white;
    box-shadow: 0 2px 8px rgba(74, 144, 226, 0.3);
}

.user-avatar:hover {
    background: linear-gradient(135deg, #4caf50, #4a90e2);
    transform: rotate(5deg);
}

.user-name {
    color: #4a90e2;
    font-weight: 600;
    text-shadow: 0 1px 2px rgba(74, 144, 226, 0.1);
}

.user-email {
    color: #9aa5b1;
    font-style: italic;
}

.brand-title span:nth-child(1) {
    color: #4a90e2;
    text-shadow: 0 2px 4px rgba(74, 144, 226, 0.2);
}

.brand-title span:nth-child(2) {
    color: #4caf50;
    text-shadow: 0 2px 4px rgba(76, 175, 80, 0.2);
}

.brand-title span:nth-child(3) {
    color: #4a90e2;
    text-shadow: 0 2px 4px rgba(74, 144, 226, 0.2);
}

.brand-subtitle {
    color: #9aa5b1;
    background: linear-gradient(90deg, #4a90e2, #4caf50);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 500;
}

/* Dropdown hover effects */
.dropdown-link i {
    transition: transform 0.2s ease;
}

.dropdown-link:hover i {
    transform: scale(1.2);
}

/* Container glow effect */
.container {
    position: relative;
}

.container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, #4a90e2, #4caf50, transparent);
    opacity: 0.3;
}
</style>

<!-- Navigation Bar -->
<nav>
    <div class="container mx-auto px-6">
        <div class="flex justify-between items-center py-4">

            <!-- Logo and Brand -->
            <div class="flex items-center space-x-3">
                <a href="<?php echo htmlspecialchars($dashboard_path); ?>" class="flex items-center space-x-3 no-underline">
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" 
                         alt="GoServePH Logo" 
                         class="logo-img"
                         onerror="this.style.display='none'; console.error('Logo not found:', this.src);">
                    
                    <div>
                        <h1 class="text-xl font-bold brand-title" style="word-spacing: -0.2em;">
                            <span>Go</span><!--
                            --><span>Serve</span><!--
                            --><span>PH</span>
                        </h1>
                        <p class="text-xs brand-subtitle">Citizen Dashboard</p>
                    </div>
                </a>
            </div>

            <!-- User Info and Menu -->
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm font-semibold user-name">
                       <?php echo htmlspecialchars($user_name); ?>
                    </p>
                    <p class="text-xs user-email">
                        <?php echo htmlspecialchars($user_email); ?>
                    </p>
                </div>

                <div class="dropdown-container">
                    <button class="w-10 h-10 rounded-full flex items-center justify-center transition-all user-avatar"
                        style="background-color: #4a90e2;">
                        <i class="fas fa-user" style="color: white;"></i>
                    </button>

                    <!-- Dropdown Menu -->
                    <div class="dropdown-menu">
                        <a href="<?php echo htmlspecialchars($settings_path); ?>" class="dropdown-link settings">
                            <i class="fas fa-user-cog mr-2"></i>Profile & Settings
                        </a>
                        <div class="divider"></div>
                        <button onclick="parent.showLogoutModal ? parent.showLogoutModal() : showLogoutModal()" class="dropdown-link logout">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</nav>

<script>
// Fallback logout modal functions if parent doesn't have them
function showLogoutModal() {
    // Try to call parent function first
    if (window.parent && typeof window.parent.showLogoutModal === 'function') {
        window.parent.showLogoutModal();
    } else if (window.opener && typeof window.opener.showLogoutModal === 'function') {
        window.opener.showLogoutModal();
    } else {
        console.error('Logout modal function not found');
        // Fallback direct logout
        window.location.href = '<?php echo $logout_handler_url ?? "../logout_handler.php"; ?>';
    }
}

// Add hover effects
document.addEventListener('DOMContentLoaded', function() {
    const userAvatar = document.querySelector('.user-avatar');
    if (userAvatar) {
        userAvatar.addEventListener('mouseenter', function() {
            this.style.transform = 'rotate(5deg) scale(1.1)';
        });
        
        userAvatar.addEventListener('mouseleave', function() {
            this.style.transform = 'rotate(0deg) scale(1)';
        });
    }
});
</script>