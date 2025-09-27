<?php
require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/Auth.php';

// Initialize database and auth
$db = new Database();
$auth = new Auth($db);

// Check authentication
requireAuth();

// Get user data
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Get user profile
$stmt = $db->getConnection()->prepare("
    SELECT u.*, p.* 
    FROM users u 
    LEFT JOIN profiles p ON u.id = p.user_id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Get dashboard statistics
$stats = [];
try {
    // Total users
    $stmt = $db->getConnection()->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch()['total'];
    
    // Active students
    $stmt = $db->getConnection()->query("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND is_active = 1");
    $stats['active_students'] = $stmt->fetch()['total'];
    
    // Recent registrations
    $stmt = $db->getConnection()->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_registrations'] = $stmt->fetch()['total'];
    
    // Total profiles
    $stmt = $db->getConnection()->query("SELECT COUNT(*) as total FROM profiles");
    $stats['total_profiles'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0a0a0a">
    <style>
        /* Dashboard specific styles */
        .dashboard-header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        
        .welcome-message {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-2xl);
            font-weight: bold;
            color: var(--text-white);
        }
        
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            text-align: center;
            transition: all var(--transition-normal);
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
            border-color: var(--border-accent);
        }
        
        .stat-number {
            font-size: var(--font-size-3xl);
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: var(--spacing-sm);
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: var(--font-size-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
        }
        
        .action-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            transition: all var(--transition-normal);
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            text-decoration: none;
            color: var(--text-primary);
            border-color: var(--primary-color);
        }
        
        .action-icon {
            font-size: var(--font-size-3xl);
            margin-bottom: var(--spacing-md);
            display: block;
        }
        
        .recent-activity {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-lg);
        }
        
        .chart-placeholder {
            background: var(--bg-tertiary);
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-2xl);
            text-align: center;
            color: var(--text-muted);
            margin-bottom: var(--spacing-xl);
        }
    </style>
</head>
<body>
    <!-- Skip Links for Accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <a href="#navigation" class="skip-link">Skip to navigation</a>
    <a href="#dashboard" class="skip-link">Skip to dashboard</a>
    
    <!-- Navigation -->
    <nav class="navbar" id="navigation" role="navigation" aria-label="Main navigation">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="index.php" class="navbar-brand">
                    <div class="logo">
                        <div class="logo-icon">🎓</div>
                        <?php echo APP_NAME; ?>
                    </div>
                </a>
                
                <ul class="navbar-nav d-flex">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link" aria-current="page">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link">Profile</a>
                    </li>
                    <?php if (hasAnyRole(['admin', 'editor'])): ?>
                    <li class="nav-item">
                        <a href="admin.php" class="nav-link">Admin</a>
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
    <main id="main-content" class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome-message">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <div>
                    <h1>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                    <p class="text-muted">Here's what's happening in your student management system.</p>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div id="dashboard" class="dashboard-grid" role="main" aria-label="Dashboard">
            <!-- Statistics Cards -->
            <div class="card dashboard-widget" id="stats-overview" draggable="true" aria-label="Statistics overview widget">
                <div class="card-header">
                    <h2 class="card-title">System Overview</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="stat-card" tabindex="0" role="button" aria-label="Total users: <?php echo $stats['total_users'] ?? 0; ?>">
                                <div class="stat-number"><?php echo $stats['total_users'] ?? 0; ?></div>
                                <div class="stat-label">Total Users</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card" tabindex="0" role="button" aria-label="Active students: <?php echo $stats['active_students'] ?? 0; ?>">
                                <div class="stat-number"><?php echo $stats['active_students'] ?? 0; ?></div>
                                <div class="stat-label">Active Students</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card" tabindex="0" role="button" aria-label="Recent registrations: <?php echo $stats['recent_registrations'] ?? 0; ?>">
                                <div class="stat-number"><?php echo $stats['recent_registrations'] ?? 0; ?></div>
                                <div class="stat-label">New This Week</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card" tabindex="0" role="button" aria-label="Total profiles: <?php echo $stats['total_profiles'] ?? 0; ?>">
                                <div class="stat-number"><?php echo $stats['total_profiles'] ?? 0; ?></div>
                                <div class="stat-label">Total Profiles</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card dashboard-widget" id="quick-actions" draggable="true" aria-label="Quick actions widget">
                <div class="card-header">
                    <h2 class="card-title">Quick Actions</h2>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="profile.php" class="action-card" role="button" aria-label="Edit your profile">
                            <span class="action-icon">👤</span>
                            <strong>Edit Profile</strong>
                        </a>
                        <a href="register.php" class="action-card" role="button" aria-label="Register new student">
                            <span class="action-icon">➕</span>
                            <strong>Add Student</strong>
                        </a>
                        <a href="settings.php" class="action-card" role="button" aria-label="System settings">
                            <span class="action-icon">⚙️</span>
                            <strong>Settings</strong>
                        </a>
                        <a href="export.php" class="action-card" role="button" aria-label="Export data">
                            <span class="action-icon">📊</span>
                            <strong>Export Data</strong>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Chart Placeholder -->
            <div class="card dashboard-widget" id="analytics-chart" draggable="true" aria-label="Analytics chart widget">
                <div class="card-header">
                    <h2 class="card-title">Analytics</h2>
                    <div class="chart-controls">
                        <button class="btn btn-sm btn-outline-primary" aria-label="Refresh chart data">🔄</button>
                        <button class="btn btn-sm btn-outline-primary" aria-label="Export chart">📤</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-placeholder">
                        <canvas class="chart-canvas" role="img" aria-label="Student registration trends chart" tabindex="0"></canvas>
                        <p>Interactive chart will be rendered here</p>
                        <small class="text-muted">Use arrow keys to navigate data points</small>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card dashboard-widget" id="recent-activity" draggable="true" aria-label="Recent activity widget">
                <div class="card-header">
                    <h2 class="card-title">Recent Activity</h2>
                </div>
                <div class="card-body">
                    <div class="recent-activity">
                        <div class="activity-item">
                            <div class="activity-icon">👤</div>
                            <div>
                                <strong>New Student Registered</strong>
                                <p class="text-muted">John Doe joined the system</p>
                                <small class="text-muted">2 hours ago</small>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">📝</div>
                            <div>
                                <strong>Profile Updated</strong>
                                <p class="text-muted">Jane Smith updated her profile</p>
                                <small class="text-muted">4 hours ago</small>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">🔐</div>
                            <div>
                                <strong>Login Activity</strong>
                                <p class="text-muted">Successful login from new device</p>
                                <small class="text-muted">1 day ago</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Profile Summary -->
            <div class="card dashboard-widget" id="profile-summary" draggable="true" aria-label="User profile summary widget">
                <div class="card-header">
                    <h2 class="card-title">Your Profile</h2>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="user-avatar profile-img-lg">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                        <div class="ml-3">
                            <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                            <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
                        </div>
                    </div>
                    
                    <div class="status-indicator active">
                        Account Status: Active
                    </div>
                    
                    <div class="progress-bar mt-3">
                        <div class="progress-fill" style="width: 75%"></div>
                    </div>
                    <small class="text-muted">Profile completion: 75%</small>
                    
                    <div class="mt-3">
                        <a href="profile.php" class="btn btn-primary">Complete Profile</a>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="card dashboard-widget" id="system-status" draggable="true" aria-label="System status widget">
                <div class="card-header">
                    <h2 class="card-title">System Status</h2>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Database Connection</span>
                        <div class="status-indicator active">Online</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Server Status</span>
                        <div class="status-indicator active">Healthy</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Last Backup</span>
                        <div class="status-indicator warning">2 hours ago</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Storage Usage</span>
                        <div class="status-indicator active">45%</div>
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
        // Initialize dashboard features
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers for stat cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('click', function() {
                    const label = this.getAttribute('aria-label');
                    if (window.dashboard) {
                        window.dashboard.showNotification(`Clicked: ${label}`, 'info', 2000);
                    }
                });
                
                card.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });
            });
            
            // Add keyboard navigation to widgets
            document.querySelectorAll('.dashboard-widget').forEach(widget => {
                widget.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });
            });
            
            // Simulate chart data
            const canvas = document.querySelector('.chart-canvas');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                canvas.width = canvas.offsetWidth;
                canvas.height = 200;
                
                // Draw a simple line chart
                ctx.strokeStyle = '#00d4ff';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.moveTo(0, 150);
                ctx.lineTo(100, 100);
                ctx.lineTo(200, 80);
                ctx.lineTo(300, 120);
                ctx.lineTo(400, 60);
                ctx.stroke();
            }
            
            // Show welcome notification
            setTimeout(() => {
                if (window.dashboard) {
                    window.dashboard.showNotification(
                        `Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! Dashboard loaded successfully.`, 
                        'success', 
                        3000
                    );
                }
            }, 1000);
        });
    </script>
</body>
</html>
