<?php
/**
 * Installation Script for Student Profile Management System
 * Run this script once to set up the database and initial configuration
 */

// Prevent running in production
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
    die('Installation script can only be run on localhost');
}

$errors = [];
$success = [];
$step = $_GET['step'] ?? 1;

// Step 1: Check requirements
if ($step == 1) {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'JSON Extension' => extension_loaded('json'),
        'Session Extension' => extension_loaded('session'),
        'File Uploads' => ini_get('file_uploads'),
        'Write Permissions' => is_writable('.'),
    ];
    
    $allRequirementsMet = !in_array(false, $requirements);
}

// Step 2: Database setup
if ($step == 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? 'localhost';
    $dbName = $_POST['db_name'] ?? 'student_profiles';
    $dbUser = $_POST['db_user'] ?? 'root';
    $dbPass = $_POST['db_pass'] ?? '';
    $appName = $_POST['app_name'] ?? 'Student Profile Management';
    $appUrl = $_POST['app_url'] ?? 'http://localhost';
    $adminUsername = $_POST['admin_username'] ?? 'admin';
    $adminEmail = $_POST['admin_email'] ?? 'admin@example.com';
    $adminPassword = $_POST['admin_password'] ?? '';
    
    try {
        // Test database connection
        $dsn = "mysql:host={$dbHost};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        
        // Read and execute schema
        $schema = file_get_contents('database/schema.sql');
        $statements = explode(';', $schema);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                $pdo->exec($statement);
            }
        }
        
        // Create .env file
        $envContent = "# Database Configuration
DB_HOST={$dbHost}
DB_PORT=3306
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}

# Application Configuration
APP_NAME=\"{$appName}\"
APP_URL={$appUrl}
APP_ENV=production
APP_DEBUG=false
APP_KEY=" . bin2hex(random_bytes(32)) . "

# Security Configuration
SESSION_LIFETIME=7200
REMEMBER_ME_LIFETIME=2592000
CSRF_TOKEN_LIFETIME=3600
MAX_LOGIN_ATTEMPTS=5
LOCKOUT_DURATION=900

# File Upload Configuration
MAX_FILE_SIZE=5242880
ALLOWED_IMAGE_TYPES=jpg,jpeg,png,gif
UPLOAD_PATH=uploads/

# Email Configuration
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@localhost
MAIL_FROM_NAME=\"{$appName}\"

# SMS Configuration (Twilio for 2FA)
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_PHONE=

# API Keys
HAVEIBEENPWNED_API_KEY=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=

# WebSocket Configuration
WS_HOST=localhost
WS_PORT=8080

# Backup Configuration
BACKUP_PATH=backups/
BACKUP_RETENTION_DAYS=30
AUTO_BACKUP_ENABLED=true
AUTO_BACKUP_SCHEDULE=0 2 * * *

# Analytics Configuration
ANALYTICS_ENABLED=true
HEATMAP_ENABLED=true

# PWA Configuration
PWA_NAME=\"{$appName}\"
PWA_SHORT_NAME=StudentProfiles
PWA_DESCRIPTION=Student Profile Management System
PWA_THEME_COLOR=#1a1a1a
PWA_BACKGROUND_COLOR=#000000";

        file_put_contents('.env', $envContent);
        
        // Create uploads directory
        if (!is_dir('uploads')) {
            mkdir('uploads', 0755, true);
        }
        
        // Create backups directory
        if (!is_dir('backups')) {
            mkdir('backups', 0755, true);
        }
        
        // Update admin user
        if (!empty($adminPassword)) {
            $adminPasswordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, email = ?, password_hash = ?, first_name = 'Admin', last_name = 'User'
                WHERE role = 'admin'
            ");
            $stmt->execute([$adminUsername, $adminEmail, $adminPasswordHash]);
        }
        
        $success[] = 'Database setup completed successfully!';
        $success[] = 'Environment file created.';
        $success[] = 'Directories created.';
        $success[] = 'Admin user updated.';
        
    } catch (Exception $e) {
        $errors[] = 'Database setup failed: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Student Profile Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .install-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .step {
            display: none;
        }
        .step.active {
            display: block;
        }
        .requirement {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .requirement:last-child {
            border-bottom: none;
        }
        .status {
            font-weight: bold;
        }
        .status.pass {
            color: var(--accent-primary);
        }
        .status.fail {
            color: var(--accent-danger);
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background-color: var(--bg-tertiary);
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }
        .progress-fill {
            height: 100%;
            background-color: var(--accent-primary);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="text-center mb-4">
            <div class="logo" style="justify-content: center; margin-bottom: 2rem;">
                <div class="logo-icon">🎓</div>
                Student Profile Management System
            </div>
            <h1>Installation Wizard</h1>
            <p class="text-muted">Set up your student profile management system</p>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo ($step / 3) * 100; ?>%"></div>
        </div>

        <!-- Step 1: Requirements Check -->
        <div class="step <?php echo $step == 1 ? 'active' : ''; ?>">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">System Requirements</h2>
                    <p class="card-subtitle">Checking if your system meets the requirements</p>
                </div>
                <div class="card-body">
                    <?php if (isset($requirements)): ?>
                        <?php foreach ($requirements as $requirement => $status): ?>
                            <div class="requirement">
                                <span><?php echo $requirement; ?></span>
                                <span class="status <?php echo $status ? 'pass' : 'fail'; ?>">
                                    <?php echo $status ? '✓ PASS' : '✗ FAIL'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($allRequirementsMet): ?>
                            <div class="alert alert-success mt-3">
                                <strong>All requirements met!</strong> You can proceed with the installation.
                            </div>
                            <div class="text-center mt-3">
                                <a href="?step=2" class="btn btn-primary">Continue to Database Setup</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mt-3">
                                <strong>Some requirements are not met.</strong> Please fix the issues above before continuing.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Step 2: Database Setup -->
        <div class="step <?php echo $step == 2 ? 'active' : ''; ?>">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Database Configuration</h2>
                    <p class="card-subtitle">Configure your database connection</p>
                </div>
                <div class="card-body">
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
                            <ul style="margin: 0; padding-left: 1rem;">
                                <?php foreach ($success as $msg): ?>
                                    <li><?php echo htmlspecialchars($msg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="text-center mt-3">
                            <a href="?step=3" class="btn btn-primary">Continue to Final Setup</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="db_host" class="form-label">Database Host</label>
                                        <input type="text" id="db_host" name="db_host" class="form-control" 
                                            value="localhost" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="db_name" class="form-label">Database Name</label>
                                        <input type="text" id="db_name" name="db_name" class="form-control" 
                                            value="student_profiles" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="db_user" class="form-label">Database Username</label>
                                        <input type="text" id="db_user" name="db_user" class="form-control" 
                                            value="root" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="db_pass" class="form-label">Database Password</label>
                                        <input type="password" id="db_pass" name="db_pass" class="form-control">
                                    </div>
                                </div>
                            </div>
                            
                            <hr style="border-color: var(--border-color); margin: 2rem 0;">
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="app_name" class="form-label">Application Name</label>
                                        <input type="text" id="app_name" name="app_name" class="form-control" 
                                            value="Student Profile Management" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="app_url" class="form-label">Application URL</label>
                                        <input type="url" id="app_url" name="app_url" class="form-control" 
                                            value="http://localhost" required>
                                    </div>
                                </div>
                            </div>
                            
                            <hr style="border-color: var(--border-color); margin: 2rem 0;">
                            
                            <h3>Admin Account</h3>
                            <div class="row">
                                <div class="col-4">
                                    <div class="form-group">
                                        <label for="admin_username" class="form-label">Admin Username</label>
                                        <input type="text" id="admin_username" name="admin_username" class="form-control" 
                                            value="admin" required>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label for="admin_email" class="form-label">Admin Email</label>
                                        <input type="email" id="admin_email" name="admin_email" class="form-control" 
                                            value="admin@example.com" required>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label for="admin_password" class="form-label">Admin Password</label>
                                        <input type="password" id="admin_password" name="admin_password" class="form-control" 
                                            placeholder="Enter a strong password" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-3">
                                <button type="submit" class="btn btn-primary">Setup Database</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Step 3: Final Setup -->
        <div class="step <?php echo $step == 3 ? 'active' : ''; ?>">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Installation Complete!</h2>
                    <p class="card-subtitle">Your student profile management system is ready</p>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <h3>🎉 Installation Successful!</h3>
                        <p>Your student profile management system has been successfully installed and configured.</p>
                    </div>
                    
                    <h3>Next Steps:</h3>
                    <ol>
                        <li><strong>Delete this installation file</strong> for security reasons</li>
                        <li><strong>Configure your web server</strong> to point to this directory</li>
                        <li><strong>Set up SSL/HTTPS</strong> for production use</li>
                        <li><strong>Configure email settings</strong> in your .env file</li>
                        <li><strong>Set up regular backups</strong> of your database</li>
                    </ol>
                    
                    <h3>Default Login Credentials:</h3>
                    <div class="alert alert-warning">
                        <p><strong>Username:</strong> admin</p>
                        <p><strong>Password:</strong> [The password you set during installation]</p>
                        <p><em>Please change the admin password after your first login!</em></p>
                    </div>
                    
                    <h3>Features Available:</h3>
                    <ul>
                        <li>✅ Secure user authentication with password hashing</li>
                        <li>✅ Student profile management with image upload</li>
                        <li>✅ Admin panel with user management</li>
                        <li>✅ Responsive dark-themed UI</li>
                        <li>✅ PWA support for mobile devices</li>
                        <li>✅ Data export functionality</li>
                        <li>✅ Audit logging for all changes</li>
                        <li>✅ Session management with remember me</li>
                    </ul>
                    
                    <div class="text-center mt-4">
                        <a href="login.php" class="btn btn-primary btn-lg">Go to Login</a>
                        <a href="index.php" class="btn btn-secondary btn-lg">View Dashboard</a>
                    </div>
                    
                    <div class="alert alert-danger mt-4">
                        <h4>⚠️ Security Notice</h4>
                        <p><strong>Important:</strong> Please delete this installation file (install.php) immediately after installation for security reasons.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Password strength indicator for admin password
            const adminPassword = document.getElementById('admin_password');
            if (adminPassword) {
                adminPassword.addEventListener('input', function() {
                    const value = this.value;
                    let strength = 0;
                    
                    if (value.length >= 8) strength++;
                    if (/[A-Z]/.test(value)) strength++;
                    if (/[a-z]/.test(value)) strength++;
                    if (/[0-9]/.test(value)) strength++;
                    if (/[^A-Za-z0-9]/.test(value)) strength++;
                    
                    const colors = ['#ff4444', '#ffaa00', '#ffaa00', '#00ff88', '#00ff88'];
                    const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
                    
                    if (value.length > 0) {
                        const indicator = document.createElement('div');
                        indicator.style.cssText = `
                            margin-top: 0.5rem;
                            padding: 0.5rem;
                            border-radius: 4px;
                            background-color: var(--bg-tertiary);
                            color: ${colors[strength - 1] || '#ff4444'};
                            font-size: 0.875rem;
                            font-weight: 500;
                        `;
                        indicator.textContent = `Password Strength: ${labels[strength - 1] || 'Very Weak'}`;
                        
                        // Remove existing indicator
                        const existing = this.parentElement.querySelector('.password-indicator');
                        if (existing) existing.remove();
                        
                        indicator.className = 'password-indicator';
                        this.parentElement.appendChild(indicator);
                    }
                });
            }
        });
    </script>
</body>
</html>
