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

$errors = [];
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $section = $_POST['section'] ?? '';
        
        try {
            if ($section === 'password') {
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if ($newPassword !== $confirmPassword) {
                    $errors[] = 'New passwords do not match.';
                } else {
                    $result = $auth->changePassword($_SESSION['user_id'], $currentPassword, $newPassword);
                    
                    if ($result['success']) {
                        $success = 'Password changed successfully!';
                    } else {
                        $errors = $result['errors'];
                    }
                }
            } elseif ($section === 'profile') {
                $firstName = sanitizeInput($_POST['first_name'] ?? '');
                $lastName = sanitizeInput($_POST['last_name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                
                if (empty($firstName) || empty($lastName) || empty($email)) {
                    $errors[] = 'Please fill in all required fields.';
                } elseif (!validateEmail($email)) {
                    $errors[] = 'Invalid email address.';
                } else {
                    // Check if email is already taken
                    $stmt = $db->getConnection()->prepare("
                        SELECT id FROM users WHERE email = ? AND id != ?
                    ");
                    $stmt->execute([$email, $_SESSION['user_id']]);
                    
                    if ($stmt->fetch()) {
                        $errors[] = 'Email address is already taken.';
                    } else {
                        $stmt = $db->getConnection()->prepare("
                            UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = datetime('now') 
                            WHERE id = ?
                        ");
                        $stmt->execute([$firstName, $lastName, $email, $_SESSION['user_id']]);
                        
                        // Update session variables
                        $_SESSION['user_first_name'] = $firstName;
                        $_SESSION['user_last_name'] = $lastName;
                        $_SESSION['user_email'] = $email;
                        
                        $success = 'Profile updated successfully!';
                        logActivity('profile_settings_updated', 'users', $_SESSION['user_id']);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Settings update error: " . $e->getMessage());
            $errors[] = 'An error occurred while updating your settings. Please try again.';
        }
    }
}

// Get current user data
try {
    $stmt = $db->getConnection()->prepare("
        SELECT * FROM users WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    error_log("Settings data fetch error: " . $e->getMessage());
    $user = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
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
                        <a href="admin.php" class="nav-link">Admin</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="settings.php" class="nav-link" aria-current="page">Settings</a>
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
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin: 0; padding-left: 1rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Settings -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Profile Settings</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="section" value="profile">
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <input type="text" id="first_name" name="first_name" class="form-control" 
                                            value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="last_name" class="form-label">Last Name *</label>
                                        <input type="text" id="last_name" name="last_name" class="form-control" 
                                            value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                    value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" id="username" name="username" class="form-control" 
                                    value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Password Settings -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Change Password</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="section" value="password">
                            
                            <div class="form-group">
                                <label for="current_password" class="form-label">Current Password *</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password" class="form-label">New Password *</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" 
                                    minlength="8" required>
                                <div id="password-strength" class="mt-2"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                    minlength="8" required>
                                <div id="password-match" class="mt-2"></div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Security Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h4>Two-Factor Authentication</h4>
                                <p class="text-muted">Add an extra layer of security to your account</p>
                                <button class="btn btn-secondary" disabled>Enable 2FA (Coming Soon)</button>
                            </div>
                            <div class="col-6">
                                <h4>Login Sessions</h4>
                                <p class="text-muted">Manage your active login sessions</p>
                                <button class="btn btn-secondary" disabled>View Sessions (Coming Soon)</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Export -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Data Management</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h4>Export Your Data</h4>
                                <p class="text-muted">Download a copy of your personal data</p>
                                <a href="export.php" class="btn btn-primary">Export Data</a>
                            </div>
                            <div class="col-6">
                                <h4>Delete Account</h4>
                                <p class="text-muted">Permanently delete your account and all associated data</p>
                                <button class="btn btn-danger" disabled>Delete Account (Contact Admin)</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- User Menu Dropdown -->
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

        // Password strength indicator
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const strengthDiv = document.getElementById('password-strength');
            const matchDiv = document.getElementById('password-match');
            
            if (newPassword) {
                newPassword.addEventListener('input', function() {
                    const value = this.value;
                    let strength = 0;
                    let feedback = [];
                    
                    if (value.length >= 8) strength++;
                    else feedback.push('At least 8 characters');
                    
                    if (/[A-Z]/.test(value)) strength++;
                    else feedback.push('One uppercase letter');
                    
                    if (/[a-z]/.test(value)) strength++;
                    else feedback.push('One lowercase letter');
                    
                    if (/[0-9]/.test(value)) strength++;
                    else feedback.push('One number');
                    
                    if (/[^A-Za-z0-9]/.test(value)) strength++;
                    else feedback.push('One special character');
                    
                    const colors = ['#ff4444', '#ffaa00', '#ffaa00', '#00ff88', '#00ff88'];
                    const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
                    
                    if (value.length > 0) {
                        strengthDiv.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="flex: 1; height: 4px; background: var(--border-color); border-radius: 2px;">
                                    <div style="height: 100%; width: ${(strength / 5) * 100}%; background: ${colors[strength - 1] || '#ff4444'}; border-radius: 2px; transition: all 0.3s ease;"></div>
                                </div>
                                <span style="color: ${colors[strength - 1] || '#ff4444'}; font-size: 0.875rem; font-weight: 500;">
                                    ${labels[strength - 1] || 'Very Weak'}
                                </span>
                            </div>
                            ${feedback.length > 0 ? `<small class="text-muted">Still needed: ${feedback.join(', ')}</small>` : ''}
                        `;
                    } else {
                        strengthDiv.innerHTML = '';
                    }
                });
            }
            
            if (confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    const match = this.value === newPassword.value;
                    if (this.value.length > 0) {
                        matchDiv.innerHTML = `
                            <small style="color: ${match ? 'var(--accent-primary)' : 'var(--accent-danger)'};">
                                ${match ? '✓ Passwords match' : '✗ Passwords do not match'}
                            </small>
                        `;
                    } else {
                        matchDiv.innerHTML = '';
                    }
                });
            }
        });
    </script>
    <!-- ARIA Live Region for Screen Reader Announcements -->
    <div aria-live="polite" aria-atomic="true" class="sr-only" id="aria-live-region"></div>

    <!-- Notification Container -->
    <div id="notification-container"></div>

    <!-- JavaScript -->
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
