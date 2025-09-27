<?php
require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/Auth.php';

// Initialize database and auth
$db = new Database();
$auth = new Auth($db);

// Check if user is logged in
if (isAuthenticated()) {
    // Log the logout activity before destroying session
    logActivity('user_logout', 'users', $_SESSION['user_id']);
    
    // Logout user
    $auth->logout();
    
    // Set success message for login page
    session_start();
    $_SESSION['logout_success'] = 'You have been successfully logged out.';
}

// Redirect to login page
header('Location: login.php?logout=success');
exit;
