<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/database.php';

// Site configuration
define('SITE_NAME', 'E-Commerce Platform');
define('SITE_URL', 'http://localhost/my_works/ousl/eex6340-mini-project');

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper function to check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Helper function to check if user is customer
function isCustomer() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer';
}

// Helper function to check if user is delivery personnel
function isDelivery() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'delivery';
}

// Helper function to get current user role
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

// Helper function to get current user ID
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Helper function to redirect
function redirect($url) {
    // If URL doesn't start with http:// or https://, treat as relative path
    if (!preg_match('/^https?:\/\//', $url)) {
        // If we're in admin folder and URL doesn't start with ../
        if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false && !strpos($url, '../') && !strpos($url, '/admin/')) {
            // If it's an admin file, keep as is, otherwise go up one level
            if (strpos($url, 'admin/') === false && $url !== 'login.php' && $url !== 'register.php') {
                $url = '../' . $url;
            }
        }
    }
    header("Location: " . $url);
    exit();
}

// Helper function to sanitize input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Helper function to get base path for URLs (handles admin subdirectory)
function getBasePath() {
    // If we're in admin folder, go up one level
    if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) {
        return '../';
    }
    
    // If we're in root, use ./
    return './';
}

// Helper function to format currency
function formatCurrency($amount) {
    return 'LKR ' . number_format($amount, 2);
}

// Helper function to get absolute URL path
function getUrlPath($file) {
    // Remove leading ./ or ../ if file already has it
    $file = ltrim($file, './');
    
    $is_in_admin = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
    $file_is_admin = (strpos($file, 'admin/') === 0);
    
    // If we're in admin folder
    if ($is_in_admin) {
        // If linking to another admin file, use just the filename (same directory)
        if ($file_is_admin) {
            return './' . str_replace('admin/', '', $file);
        }
        // If linking to root file, go up one level
        return '../' . $file;
    }
    
    // If we're in root folder
    // If linking to admin file, use admin/ path
    if ($file_is_admin) {
        return './' . $file;
    }
    // If linking to root file, use ./
    return './' . $file;
}
?>

