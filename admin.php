<?php
require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/Auth.php';

// Initialize database and auth
$db = new Database();
$auth = new Auth($db);

// Check authentication and admin role
requireRole(['admin', 'editor']);

// Get admin statistics
$stats = [];
try {
    // Total users
    $stmt = $db->getConnection()->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch()['total'];
    
    // Active users
    $stmt = $db->getConnection()->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $stats['active_users'] = $stmt->fetch()['total'];
    
    // Recent registrations
    $stmt = $db->getConnection()->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_registrations'] = $stmt->fetch()['total'];
    
    // Total profiles
    $stmt = $db->getConnection()->query("SELECT COUNT(*) as total FROM profiles");
    $stats['total_profiles'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    error_log("Admin stats error: " . $e->getMessage());
}

// Log admin access
logActivity('admin_access', 'admin', $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0a0a0a">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navigation" role="navigation" aria-label="Main navigation">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="dashboard.php" class="navbar-brand">
                    <div class="logo">
                        <div class="logo-icon">🎓</div>
                        <?php echo APP_NAME; ?>
                    </div>
                </a>
                
                <ul class="navbar-nav d-flex">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link">Profile</a>
                    </li>
                    <?php if (hasAnyRole(['admin', 'editor'])): ?>
                    <li class="nav-item">
                        <a href="admin.php" class="nav-link" aria-current="page">Admin</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="settings.php" class="nav-link">Settings</a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link logout-link" onclick="window.dashboard.showLogoutConfirmation(); return false;" 
                           aria-label="Logout from your account">🚪 Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container" style="margin-top: 2rem;">
        <div class="row">
            <div class="col-12">
                <h1>Admin Panel</h1>
                <p class="text-muted">System administration and management tools.</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-primary"><?php echo $stats['total_users'] ?? 0; ?></h3>
                        <p class="text-muted">Total Users</p>
                    </div>
                </div>
            </div>
            <div class="col-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-success"><?php echo $stats['active_users'] ?? 0; ?></h3>
                        <p class="text-muted">Active Users</p>
                    </div>
                </div>
            </div>
            <div class="col-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-warning"><?php echo $stats['recent_registrations'] ?? 0; ?></h3>
                        <p class="text-muted">New This Week</p>
                    </div>
                </div>
            </div>
            <div class="col-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-info"><?php echo $stats['total_profiles'] ?? 0; ?></h3>
                        <p class="text-muted">Total Profiles</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Actions -->
        <div class="row mt-4">
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">User Management</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Manage users, roles, and permissions.</p>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" disabled>View All Users</button>
                            <button class="btn btn-secondary" disabled>Manage Roles</button>
                        </div>
                        <small class="text-muted d-block mt-2">Coming soon...</small>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">System Tools</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">System maintenance and monitoring tools.</p>
                        <div class="d-flex gap-2">
                            <a href="export.php" class="btn btn-primary">Export Data</a>
                            <button class="btn btn-secondary" disabled>System Logs</button>
                        </div>
                        <small class="text-muted d-block mt-2">Export functionality is available</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                            <a href="profile.php" class="btn btn-outline-secondary">View Profile</a>
                            <a href="settings.php" class="btn btn-outline-info">System Settings</a>
                            <button class="btn btn-outline-warning" onclick="window.dashboard.showNotification('Admin features coming soon!', 'info', 3000)">More Features</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- ARIA Live Region for Screen Reader Announcements -->
    <div aria-live="polite" aria-atomic="true" class="sr-only" id="aria-live-region"></div>

    <!-- Notification Container -->
    <div id="notification-container"></div>

    <!-- JavaScript -->
    <script src="assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show welcome notification
            setTimeout(() => {
                if (window.dashboard) {
                    window.dashboard.showNotification('Welcome to Admin Panel!', 'success', 3000);
                }
            }, 1000);
        });
    </script>
</body>
</html>
