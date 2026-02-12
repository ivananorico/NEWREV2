<?php
// revenue2/citizen_dashboard/logout_handler.php
// This file handles logout without any output before headers

// Start output buffering to prevent any accidental output
ob_start();

// Start session
session_start();

// Destroy all session data
$_SESSION = array();

// Delete session cookie
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

// Check if we're on localhost or domain
if (strpos($host, 'localhost') !== false) {
    $login_url = $protocol . "://" . $host . "/revenue2/index.php";
} else {
    $login_url = $protocol . "://" . $host . "/index.php";
}

// Clear output buffer and redirect
ob_end_clean();
header('Location: ' . $login_url);
exit();