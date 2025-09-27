<?php
require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize database connection
$db = new Database();

// Check if user is authenticated
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Get user profile data
try {
    $stmt = $db->getConnection()->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email 
        FROM profiles p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        // Create default profile if none exists
        $stmt = $db->getConnection()->prepare("
            INSERT INTO profiles (user_id, gpa, academic_standing, enrollment_status) 
            VALUES (?, 0.00, 'satisfactory', 'active')
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Fetch the newly created profile
        $stmt = $db->getConnection()->prepare("
            SELECT p.*, u.first_name, u.last_name, u.email 
            FROM profiles p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $profile = $stmt->fetch();
    }
    
    // Log activity
    logActivity('view_dashboard');
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $profile = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0a0a0a">
    <meta name="description" content="Student Profile Management System">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-icon">🎓</div>
                    <?php echo APP_NAME; ?>
                </div>
                
                <nav class="nav">
                    <a href="index.php" class="nav-link">Dashboard</a>
                    <a href="profile.php" class="nav-link">Profile</a>
                    <?php if (hasAnyRole(['admin', 'editor'])): ?>
                    <a href="admin.php" class="nav-link">Admin</a>
                    <?php endif; ?>
                    <a href="settings.php" class="nav-link">Settings</a>
                </nav>
                
                <div class="user-menu">
                    <div class="user-avatar" onclick="toggleUserMenu()">
                        <?php echo strtoupper(substr($_SESSION['user_first_name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container" style="margin-top: 2rem;">
        <?php if ($profile): ?>
        <!-- GPA Display -->
        <div class="gpa-display">
            <div class="gpa-icon">🏆</div>
            <div class="gpa-value"><?php echo number_format($profile['gpa'], 2); ?></div>
            <div class="gpa-label">Current GPA</div>
        </div>

        <!-- Profile Summary -->
        <div class="row">
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Academic Information</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Student ID:</strong> <?php echo htmlspecialchars($profile['student_id'] ?? 'Not assigned'); ?></p>
                        <p><strong>Major:</strong> <?php echo htmlspecialchars($profile['major'] ?? 'Undeclared'); ?></p>
                        <p><strong>Minor:</strong> <?php echo htmlspecialchars($profile['minor'] ?? 'None'); ?></p>
                        <p><strong>Graduation Year:</strong> <?php echo $profile['graduation_year'] ?? 'TBD'; ?></p>
                        <p><strong>Academic Standing:</strong> 
                            <span class="text-primary"><?php echo ucfirst($profile['academic_standing']); ?></span>
                        </p>
                        <p><strong>Enrollment Status:</strong> 
                            <span class="text-primary"><?php echo ucfirst($profile['enrollment_status']); ?></span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Personal Information</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($profile['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($profile['phone'] ?? 'Not provided'); ?></p>
                        <p><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($profile['emergency_contact_name'] ?? 'Not provided'); ?></p>
                        <?php if ($profile['bio']): ?>
                        <p><strong>Bio:</strong> <?php echo htmlspecialchars(substr($profile['bio'], 0, 100)) . (strlen($profile['bio']) > 100 ? '...' : ''); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-3">
                        <a href="profile.php" class="btn btn-primary w-100">Edit Profile</a>
                    </div>
                    <div class="col-3">
                        <a href="profile.php#academic" class="btn btn-secondary w-100">Update GPA</a>
                    </div>
                    <div class="col-3">
                        <a href="profile.php#contact" class="btn btn-secondary w-100">Update Contact</a>
                    </div>
                    <div class="col-3">
                        <a href="export.php" class="btn btn-secondary w-100">Export Data</a>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Error State -->
        <div class="card">
            <div class="alert alert-danger">
                <h3>Error Loading Profile</h3>
                <p>There was an error loading your profile information. Please try refreshing the page or contact support if the problem persists.</p>
                <a href="profile.php" class="btn btn-primary">Create Profile</a>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- User Menu Dropdown (hidden by default) -->
    <div id="userMenu" class="user-menu-dropdown" style="display: none;">
        <div class="card" style="position: absolute; top: 100%; right: 0; min-width: 200px; z-index: 1000;">
            <div class="card-body">
                <p><strong><?php echo htmlspecialchars($_SESSION['user_first_name'] . ' ' . $_SESSION['user_last_name']); ?></strong></p>
                <p class="text-muted"><?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
                <hr style="border-color: var(--border-color);">
                <a href="profile.php" class="nav-link">My Profile</a>
                <a href="settings.php" class="nav-link">Settings</a>
                <a href="logout.php" class="nav-link text-danger">Logout</a>
            </div>
        </div>
    </div>

    <script>
        function toggleUserMenu() {
            const menu = document.getElementById('userMenu');
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenu');
            const userAvatar = document.querySelector('.user-avatar');
            
            if (!userAvatar.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.style.display = 'none';
            }
        });

        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Animate GPA value on load
            const gpaValue = document.querySelector('.gpa-value');
            if (gpaValue) {
                gpaValue.style.opacity = '0';
                gpaValue.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    gpaValue.style.transition = 'all 0.6s ease';
                    gpaValue.style.opacity = '1';
                    gpaValue.style.transform = 'translateY(0)';
                }, 200);
            }
        });
    </script>
</body>
</html>
