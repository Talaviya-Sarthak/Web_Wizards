<?php
require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/Auth.php';

// Initialize database and auth
$db = new Database();
$auth = new Auth($db);

// Check if already logged in
if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

// Check remember me token
$auth->checkRememberToken();
if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        if (empty($username) || empty($password)) {
            $errors[] = 'Please fill in all fields.';
        } else {
            $result = $auth->login($username, $password, $rememberMe);
            
            if ($result['success']) {
                header('Location: index.php');
                exit;
            } else {
                $errors = $result['errors'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0a0a0a">
</head>
<body>
    <div class="container" style="max-width: 400px; margin: 4rem auto;">
        <!-- Logo and Title -->
        <div class="text-center mb-4">
            <div class="logo" style="justify-content: center; margin-bottom: 2rem;">
                <div class="logo-icon">🎓</div>
                <?php echo APP_NAME; ?>
            </div>
            <h2>Welcome Back</h2>
            <p class="text-muted">Sign in to your account</p>
        </div>

        <!-- Login Form -->
        <div class="card">
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
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label for="username" class="form-label">Username or Email</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control" 
                            placeholder="Enter your username or email"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            required
                            autocomplete="username"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="form-check">
                        <input 
                            type="checkbox" 
                            id="remember_me" 
                            name="remember_me" 
                            class="form-check-input"
                            <?php echo isset($_POST['remember_me']) ? 'checked' : ''; ?>
                        >
                        <label for="remember_me" class="form-check-label">Remember me for 30 days</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        Sign In
                    </button>
                </form>

                <div class="text-center">
                    <p class="text-muted">
                        Don't have an account? 
                        <a href="register.php" class="text-primary">Create one here</a>
                    </p>
                    <p class="text-muted">
                        <a href="forgot-password.php" class="text-primary">Forgot your password?</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Demo Credentials -->
        <div class="card mt-3">
            <div class="card-body">
                <h4 class="card-title">Demo Credentials</h4>
                <p class="text-muted">Use these credentials to test the system:</p>
                <div class="row">
                    <div class="col-6">
                        <strong>Admin:</strong><br>
                        Username: <code>admin</code><br>
                        Password: <code>password</code>
                    </div>
                    <div class="col-6">
                        <strong>Student:</strong><br>
                        Username: <code>student</code><br>
                        Password: <code>password</code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on username field
            document.getElementById('username').focus();
            
            // Add loading state to form submission
            const form = document.querySelector('form');
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<span class="loading"></span> Signing In...';
                submitBtn.disabled = true;
            });
            
            // Show/hide password
            const passwordField = document.getElementById('password');
            const togglePassword = document.createElement('button');
            togglePassword.type = 'button';
            togglePassword.innerHTML = '👁️';
            togglePassword.style.cssText = `
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                cursor: pointer;
                color: var(--text-muted);
            `;
            
            const passwordGroup = passwordField.parentElement;
            passwordGroup.style.position = 'relative';
            passwordGroup.appendChild(togglePassword);
            
            togglePassword.addEventListener('click', function() {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                togglePassword.innerHTML = type === 'password' ? '👁️' : '🙈';
            });
        });
    </script>
</body>
</html>
