<?php
/**
 * Authentication Class
 * Handles user authentication, session management, and security
 */

class Auth {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Register a new user
     */
    public function register($username, $email, $password, $firstName, $lastName, $role = 'student') {
        try {
            // Validate input
            $errors = $this->validateRegistration($username, $email, $password, $firstName, $lastName);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // Check if username or email already exists
            if ($this->userExists($username, $email)) {
                return ['success' => false, 'errors' => ['Username or email already exists']];
            }
            
            // Hash password
            $passwordHash = hashPassword($password);
            
            // Generate email verification token
            $verificationToken = generateToken();
            
            // Insert user
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO users (username, email, password_hash, first_name, last_name, role, email_verified) 
                VALUES (?, ?, ?, ?, ?, ?, FALSE)
            ");
            
            $stmt->execute([$username, $email, $passwordHash, $firstName, $lastName, $role]);
            $userId = $this->db->lastInsertId();
            
            // Create default profile
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO profiles (user_id, academic_standing, enrollment_status) 
                VALUES (?, 'satisfactory', 'active')
            ");
            $stmt->execute([$userId]);
            
            // Log activity
            logActivity('user_registered', 'users', $userId);
            
            return ['success' => true, 'user_id' => $userId];
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Registration failed. Please try again.']];
        }
    }
    
    /**
     * Login user
     */
    public function login($username, $password, $rememberMe = false) {
        try {
            // Check for account lockout
            if ($this->isAccountLocked($username)) {
                return ['success' => false, 'errors' => ['Account is temporarily locked due to too many failed login attempts']];
            }
            
            // Get user by username or email
            $stmt = $this->db->getConnection()->prepare("
                SELECT id, username, email, password_hash, first_name, last_name, role, is_active, 
                       failed_login_attempts, locked_until 
                FROM users 
                WHERE (username = ? OR email = ?) AND is_active = TRUE
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'errors' => ['Invalid credentials']];
            }
            
            // Verify password
            if (!verifyPassword($password, $user['password_hash'])) {
                $this->recordFailedLogin($user['id']);
                return ['success' => false, 'errors' => ['Invalid credentials']];
            }
            
            // Clear failed login attempts
            $this->clearFailedLogins($user['id']);
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_first_name'] = $user['first_name'];
            $_SESSION['user_last_name'] = $user['last_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Handle remember me
            if ($rememberMe) {
                $this->createRememberToken($user['id']);
            }
            
            // Update last login
            $stmt = $this->db->getConnection()->prepare("
                UPDATE users SET last_login = datetime('now') WHERE id = ?
            ");
            $stmt->execute([$user['id']]);
            
            // Log activity
            logActivity('user_login', 'users', $user['id']);
            
            return ['success' => true, 'user' => $user];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Login failed. Please try again.']];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            // Log activity
            logActivity('user_logout', 'users', $_SESSION['user_id']);
            
            // Clear remember token if exists
            if (isset($_COOKIE['remember_token'])) {
                $this->clearRememberToken($_COOKIE['remember_token']);
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            }
        }
        
        // Destroy session
        session_destroy();
        session_start();
    }
    
    /**
     * Check remember me token
     */
    public function checkRememberToken() {
        if (!isset($_COOKIE['remember_token']) || isAuthenticated()) {
            return false;
        }
        
        try {
            $tokenHash = hash('sha256', $_COOKIE['remember_token']);
            
            $stmt = $this->db->getConnection()->prepare("
                SELECT rt.user_id, u.username, u.email, u.first_name, u.last_name, u.role, u.is_active
                FROM remember_tokens rt
                JOIN users u ON rt.user_id = u.id
                WHERE rt.token_hash = ? AND rt.expires_at > datetime('now') AND u.is_active = 1
            ");
            $stmt->execute([$tokenHash]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Update token usage
                $stmt = $this->db->getConnection()->prepare("
                    UPDATE remember_tokens SET last_used_at = datetime('now') WHERE token_hash = ?
                ");
                $stmt->execute([$tokenHash]);
                
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_first_name'] = $user['first_name'];
                $_SESSION['user_last_name'] = $user['last_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Log activity
                logActivity('remember_login', 'users', $user['user_id']);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Remember token check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create remember me token
     */
    private function createRememberToken($userId) {
        try {
            $token = generateToken(32);
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + REMEMBER_ME_LIFETIME);
            
            $stmt = $this->db->getConnection()->prepare("
                INSERT INTO remember_tokens (user_id, token_hash, expires_at, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $tokenHash,
                $expiresAt,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            // Set secure cookie
            setcookie(
                'remember_token',
                $token,
                time() + REMEMBER_ME_LIFETIME,
                '/',
                '',
                isset($_SERVER['HTTPS']),
                true // HttpOnly
            );
            
        } catch (Exception $e) {
            error_log("Remember token creation error: " . $e->getMessage());
        }
    }
    
    /**
     * Clear remember token
     */
    private function clearRememberToken($token) {
        try {
            $tokenHash = hash('sha256', $token);
            $stmt = $this->db->getConnection()->prepare("
                DELETE FROM remember_tokens WHERE token_hash = ?
            ");
            $stmt->execute([$tokenHash]);
        } catch (Exception $e) {
            error_log("Remember token clear error: " . $e->getMessage());
        }
    }
    
    /**
     * Record failed login attempt
     */
    private function recordFailedLogin($userId) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE users 
                SET failed_login_attempts = failed_login_attempts + 1,
                    locked_until = CASE 
                        WHEN failed_login_attempts + 1 >= ? THEN datetime('now', '+' || ? || ' seconds')
                        ELSE locked_until
                    END
                WHERE id = ?
            ");
            $stmt->execute([MAX_LOGIN_ATTEMPTS, LOCKOUT_DURATION, $userId]);
        } catch (Exception $e) {
            error_log("Failed login recording error: " . $e->getMessage());
        }
    }
    
    /**
     * Clear failed login attempts
     */
    private function clearFailedLogins($userId) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE users 
                SET failed_login_attempts = 0, locked_until = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Clear failed logins error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if account is locked
     */
    private function isAccountLocked($username) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT locked_until FROM users 
                WHERE (username = ? OR email = ?) AND locked_until > datetime('now')
            ");
            $stmt->execute([$username, $username]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("Account lock check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user exists
     */
    private function userExists($username, $email) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT id FROM users WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $email]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("User exists check error: " . $e->getMessage());
            return true; // Assume exists to prevent registration
        }
    }
    
    /**
     * Validate registration data
     */
    private function validateRegistration($username, $email, $password, $firstName, $lastName) {
        $errors = [];
        
        // Username validation
        if (empty($username) || strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters long";
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "Username can only contain letters, numbers, and underscores";
        }
        
        // Email validation
        if (!validateEmail($email)) {
            $errors[] = "Invalid email address";
        }
        
        // Password validation
        $passwordErrors = validatePassword($password);
        $errors = array_merge($errors, $passwordErrors);
        
        // Name validation
        if (empty($firstName) || strlen($firstName) < 2) {
            $errors[] = "First name must be at least 2 characters long";
        }
        
        if (empty($lastName) || strlen($lastName) < 2) {
            $errors[] = "Last name must be at least 2 characters long";
        }
        
        return $errors;
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get current password hash
            $stmt = $this->db->getConnection()->prepare("
                SELECT password_hash FROM users WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'errors' => ['User not found']];
            }
            
            // Verify current password
            if (!verifyPassword($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'errors' => ['Current password is incorrect']];
            }
            
            // Validate new password
            $passwordErrors = validatePassword($newPassword);
            if (!empty($passwordErrors)) {
                return ['success' => false, 'errors' => $passwordErrors];
            }
            
            // Update password
            $newPasswordHash = hashPassword($newPassword);
            $stmt = $this->db->getConnection()->prepare("
                UPDATE users SET password_hash = ? WHERE id = ?
            ");
            $stmt->execute([$newPasswordHash, $userId]);
            
            // Log activity
            logActivity('password_changed', 'users', $userId);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Password change failed. Please try again.']];
        }
    }
}
