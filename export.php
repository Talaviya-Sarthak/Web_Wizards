<?php
require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize database connection
$db = new Database();

// Check authentication
requireAuth();

// Get user data
try {
    // Get user profile data
    $stmt = $db->getConnection()->prepare("
        SELECT u.*, p.*
        FROM users u
        LEFT JOIN profiles p ON u.id = p.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    
    if (!$userData) {
        header('HTTP/1.1 404 Not Found');
        exit('User data not found');
    }
    
    // Get activity logs
    $stmt = $db->getConnection()->prepare("
        SELECT * FROM activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $activityLogs = $stmt->fetchAll();
    
    // Get audit logs
    $stmt = $db->getConnection()->prepare("
        SELECT * FROM audit_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $auditLogs = $stmt->fetchAll();
    
    // Log export activity
    logActivity('data_export', 'users', $_SESSION['user_id']);
    
} catch (Exception $e) {
    error_log("Data export error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error exporting data');
}

// Set headers for download
$filename = 'student_profile_export_' . date('Y-m-d_H-i-s') . '.json';
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Prepare export data
$exportData = [
    'export_info' => [
        'exported_at' => date('c'),
        'exported_by' => $_SESSION['user_id'],
        'data_version' => '1.0',
        'system' => APP_NAME
    ],
    'user_data' => [
        'basic_info' => [
            'id' => $userData['id'],
            'username' => $userData['username'],
            'email' => $userData['email'],
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
            'role' => $userData['role'],
            'is_active' => (bool)$userData['is_active'],
            'email_verified' => (bool)$userData['email_verified'],
            'created_at' => $userData['created_at'],
            'updated_at' => $userData['updated_at'],
            'last_login' => $userData['last_login']
        ],
        'profile_data' => [
            'student_id' => $userData['student_id'],
            'gpa' => $userData['gpa'],
            'major' => $userData['major'],
            'minor' => $userData['minor'],
            'graduation_year' => $userData['graduation_year'],
            'phone' => $userData['phone'],
            'address' => $userData['address'],
            'emergency_contact_name' => $userData['emergency_contact_name'],
            'emergency_contact_phone' => $userData['emergency_contact_phone'],
            'profile_image' => $userData['profile_image'],
            'bio' => $userData['bio'],
            'interests' => $userData['interests'],
            'achievements' => $userData['achievements'],
            'social_links' => $userData['social_links'] ? json_decode($userData['social_links'], true) : null,
            'academic_standing' => $userData['academic_standing'],
            'enrollment_status' => $userData['enrollment_status'],
            'profile_created_at' => $userData['created_at'],
            'profile_updated_at' => $userData['updated_at']
        ]
    ],
    'activity_logs' => array_map(function($log) {
        return [
            'id' => $log['id'],
            'action' => $log['action'],
            'resource_type' => $log['resource_type'],
            'resource_id' => $log['resource_id'],
            'ip_address' => $log['ip_address'],
            'user_agent' => $log['user_agent'],
            'metadata' => $log['metadata'] ? json_decode($log['metadata'], true) : null,
            'created_at' => $log['created_at']
        ];
    }, $activityLogs),
    'audit_logs' => array_map(function($log) {
        return [
            'id' => $log['id'],
            'table_name' => $log['table_name'],
            'record_id' => $log['record_id'],
            'field_name' => $log['field_name'],
            'old_value' => $log['old_value'],
            'new_value' => $log['new_value'],
            'action' => $log['action'],
            'ip_address' => $log['ip_address'],
            'user_agent' => $log['user_agent'],
            'created_at' => $log['created_at']
        ];
    }, $auditLogs),
    'data_summary' => [
        'total_activity_logs' => count($activityLogs),
        'total_audit_logs' => count($auditLogs),
        'profile_completeness' => calculateProfileCompleteness($userData),
        'account_age_days' => floor((time() - strtotime($userData['created_at'])) / 86400)
    ]
];

// Calculate profile completeness
function calculateProfileCompleteness($userData) {
    $fields = [
        'student_id', 'gpa', 'major', 'phone', 'address', 
        'emergency_contact_name', 'emergency_contact_phone', 'bio'
    ];
    
    $completed = 0;
    foreach ($fields as $field) {
        if (!empty($userData[$field])) {
            $completed++;
        }
    }
    
    return round(($completed / count($fields)) * 100, 1);
}

// Output JSON
echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
