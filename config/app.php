<?php
/**
 * Application Configuration
 * Contains application-wide constants and settings
 */

// Configure session settings BEFORE any session operations
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
}

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Application Constants
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Student Profile Management');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');
define('APP_DEBUG', $_ENV['APP_DEBUG'] ?? true);
define('APP_KEY', $_ENV['APP_KEY'] ?? 'your-secret-key-here');

// Security Constants
define('SESSION_LIFETIME', $_ENV['SESSION_LIFETIME'] ?? 7200);
define('REMEMBER_ME_LIFETIME', $_ENV['REMEMBER_ME_LIFETIME'] ?? 2592000);
define('CSRF_TOKEN_LIFETIME', $_ENV['CSRF_TOKEN_LIFETIME'] ?? 3600);
define('MAX_LOGIN_ATTEMPTS', $_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5);
define('LOCKOUT_DURATION', $_ENV['LOCKOUT_DURATION'] ?? 900);

// File Upload Constants
define('MAX_FILE_SIZE', $_ENV['MAX_FILE_SIZE'] ?? 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', explode(',', $_ENV['ALLOWED_IMAGE_TYPES'] ?? 'jpg,jpeg,png,gif'));
define('UPLOAD_PATH', $_ENV['UPLOAD_PATH'] ?? 'uploads/');

// Email Constants
define('MAIL_HOST', $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com');
define('MAIL_PORT', $_ENV['MAIL_PORT'] ?? 587);
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME'] ?? '');
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD'] ?? '');
define('MAIL_ENCRYPTION', $_ENV['MAIL_ENCRYPTION'] ?? 'tls');
define('MAIL_FROM_ADDRESS', $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@localhost');
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'Student Profile System');

// Database Constants
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);
define('DB_NAME', $_ENV['DB_NAME'] ?? 'student_profiles');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Backup Constants
define('BACKUP_PATH', $_ENV['BACKUP_PATH'] ?? 'backups/');
define('BACKUP_RETENTION_DAYS', $_ENV['BACKUP_RETENTION_DAYS'] ?? 30);
define('AUTO_BACKUP_ENABLED', $_ENV['AUTO_BACKUP_ENABLED'] ?? true);

// Analytics Constants
define('ANALYTICS_ENABLED', $_ENV['ANALYTICS_ENABLED'] ?? true);
define('HEATMAP_ENABLED', $_ENV['HEATMAP_ENABLED'] ?? true);

// PWA Constants
define('PWA_NAME', $_ENV['PWA_NAME'] ?? 'Student Profiles');
define('PWA_SHORT_NAME', $_ENV['PWA_SHORT_NAME'] ?? 'StudentProfiles');
define('PWA_DESCRIPTION', $_ENV['PWA_DESCRIPTION'] ?? 'Student Profile Management System');
define('PWA_THEME_COLOR', $_ENV['PWA_THEME_COLOR'] ?? '#1a1a1a');
define('PWA_BACKGROUND_COLOR', $_ENV['PWA_BACKGROUND_COLOR'] ?? '#000000');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting based on environment
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set timezone
date_default_timezone_set('UTC');

// Include common functions
require_once __DIR__ . '/../includes/functions.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_start();
}
