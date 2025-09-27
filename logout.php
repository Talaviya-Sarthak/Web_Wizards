<?php
require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/Auth.php';

// Initialize database and auth
$db = new Database();
$auth = new Auth($db);

// Logout user
$auth->logout();

// Redirect to login page
header('Location: login.php');
exit;
