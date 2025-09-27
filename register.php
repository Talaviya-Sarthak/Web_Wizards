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

$errors = [];
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $firstName = sanitizeInput($_POST['first_name'] ?? '');
        $lastName = sanitizeInput($_POST['last_name'] ?? '');
        $agreeTerms = isset($_POST['agree_terms']);
        
        // Validate form
        if (empty($username) || empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
            $errors[] = 'Please fill in all required fields.';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
        
        if (!$agreeTerms) {
            $errors[] = 'You must agree to the terms and conditions.';
        }
        
        if (empty($errors)) {
            $result = $auth->register($username, $email, $password, $firstName, $lastName);
            
            if ($result['success']) {
                $success = 'Registration successful! You can now log in.';
                // Clear form data
                $_POST = [];
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
    <title>Register - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0a0a0a">
</head>
<body>
    <div class="container" style="max-width: 500px; margin: 2rem auto;">
        <!-- Logo and Title -->
        <div class="text-center mb-4">
            <div class="logo" style="justify-content: center; margin-bottom: 2rem;">
                <div class="logo-icon">🎓</div>
                <?php echo APP_NAME; ?>
            </div>
            <h2>Create Account</h2>
            <p class="text-muted">Join our student profile management system</p>
        </div>

        <!-- Registration Form -->
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
                        <br><br>
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                <?php endif; ?>

                <?php if (empty($success)): ?>
                <form method="POST" action="" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input 
                                    type="text" 
                                    id="first_name" 
                                    name="first_name" 
                                    class="form-control" 
                                    placeholder="Enter your first name"
                                    value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                    required
                                    autocomplete="given-name"
                                >
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input 
                                    type="text" 
                                    id="last_name" 
                                    name="last_name" 
                                    class="form-control" 
                                    placeholder="Enter your last name"
                                    value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                    required
                                    autocomplete="family-name"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="username" class="form-label">Username *</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control" 
                            placeholder="Choose a username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            required
                            autocomplete="username"
                            pattern="[a-zA-Z0-9_]+"
                            title="Username can only contain letters, numbers, and underscores"
                        >
                        <small class="text-muted">Only letters, numbers, and underscores allowed</small>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address *</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="Enter your email address"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                            autocomplete="email"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password *</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Create a strong password"
                            required
                            autocomplete="new-password"
                            minlength="8"
                        >
                        <div id="password-strength" class="mt-2"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-control" 
                            placeholder="Confirm your password"
                            required
                            autocomplete="new-password"
                        >
                        <div id="password-match" class="mt-2"></div>
                    </div>

                    <div class="form-check">
                        <input 
                            type="checkbox" 
                            id="agree_terms" 
                            name="agree_terms" 
                            class="form-check-input"
                            required
                        >
                        <label for="agree_terms" class="form-check-label">
                            I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> 
                            and <a href="privacy.php" target="_blank">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        Create Account
                    </button>
                </form>
                <?php endif; ?>

                <div class="text-center">
                    <p class="text-muted">
                        Already have an account? 
                        <a href="login.php" class="text-primary">Sign in here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const strengthDiv = document.getElementById('password-strength');
            const matchDiv = document.getElementById('password-match');
            
            // Password strength indicator
            password.addEventListener('input', function() {
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
            
            // Password match indicator
            confirmPassword.addEventListener('input', function() {
                const match = this.value === password.value;
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
            
            // Form submission
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<span class="loading"></span> Creating Account...';
                submitBtn.disabled = true;
            });
            
            // Real-time validation
            const inputs = form.querySelectorAll('input[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.style.borderColor = 'var(--accent-danger)';
                    } else {
                        this.style.borderColor = 'var(--border-color)';
                    }
                });
            });
        });
    </script>
</body>
</html>
