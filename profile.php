<?php
require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize database connection
$db = new Database();

// Check authentication
requireAuth();

$errors = [];
$success = '';

// Get current profile data
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
            INSERT INTO profiles (user_id, academic_standing, enrollment_status) 
            VALUES (?, 'satisfactory', 'active')
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
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $profile = null;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $section = $_POST['section'] ?? '';
        
        try {
            $db->beginTransaction();
            
            if ($section === 'basic') {
                $result = updateBasicInfo($db, $_POST);
            } elseif ($section === 'academic') {
                $result = updateAcademicInfo($db, $_POST);
            } elseif ($section === 'contact') {
                $result = updateContactInfo($db, $_POST);
            } elseif ($section === 'image') {
                $result = updateProfileImage($db, $_FILES);
            } else {
                $errors[] = 'Invalid section.';
            }
            
            if (!empty($result['errors'])) {
                $errors = $result['errors'];
                $db->rollback();
            } else {
                $db->commit();
                $success = $result['message'] ?? 'Profile updated successfully!';
                
                // Refresh profile data
                $stmt = $db->getConnection()->prepare("
                    SELECT p.*, u.first_name, u.last_name, u.email 
                    FROM profiles p 
                    JOIN users u ON p.user_id = u.id 
                    WHERE p.user_id = ?
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $profile = $stmt->fetch();
                
                logActivity('profile_updated', 'profiles', $profile['id'], ['section' => $section]);
            }
            
        } catch (Exception $e) {
            $db->rollback();
            error_log("Profile update error: " . $e->getMessage());
            $errors[] = 'An error occurred while updating your profile. Please try again.';
        }
    }
}

// Helper functions for updating different sections
function updateBasicInfo($db, $data) {
    $bio = sanitizeInput($data['bio'] ?? '');
    $interests = sanitizeInput($data['interests'] ?? '');
    $achievements = sanitizeInput($data['achievements'] ?? '');
    
    $stmt = $db->getConnection()->prepare("
        UPDATE profiles 
        SET bio = ?, interests = ?, achievements = ?, updated_at = datetime('now')
        WHERE user_id = ?
    ");
    $stmt->execute([$bio, $interests, $achievements, $_SESSION['user_id']]);
    
    return ['success' => true, 'message' => 'Basic information updated successfully!'];
}

function updateAcademicInfo($db, $data) {
    $errors = [];
    
    $studentId = sanitizeInput($data['student_id'] ?? '');
    $gpa = floatval($data['gpa'] ?? 0);
    $major = sanitizeInput($data['major'] ?? '');
    $minor = sanitizeInput($data['minor'] ?? '');
    $graduationYear = intval($data['graduation_year'] ?? 0);
    $academicStanding = sanitizeInput($data['academic_standing'] ?? 'satisfactory');
    $enrollmentStatus = sanitizeInput($data['enrollment_status'] ?? 'active');
    
    // Validate GPA
    if ($gpa < 0 || $gpa > 10.0) {
        $errors[] = 'GPA must be between 0.0 and 10.0';
    }
    
    // Validate graduation year
    if ($graduationYear > 0 && ($graduationYear < 2020 || $graduationYear > 2030)) {
        $errors[] = 'Graduation year must be between 2020 and 2030';
    }
    
    if (!empty($errors)) {
        return ['errors' => $errors];
    }
    
    // Check if student ID is already taken
    if (!empty($studentId)) {
        $stmt = $db->getConnection()->prepare("
            SELECT id FROM profiles WHERE student_id = ? AND user_id != ?
        ");
        $stmt->execute([$studentId, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors[] = 'Student ID is already taken by another user';
            return ['errors' => $errors];
        }
    }
    
    $stmt = $db->getConnection()->prepare("
        UPDATE profiles 
        SET student_id = ?, gpa = ?, major = ?, minor = ?, graduation_year = ?, 
            academic_standing = ?, enrollment_status = ?, updated_at = datetime('now')
        WHERE user_id = ?
    ");
    $stmt->execute([
        $studentId, $gpa, $major, $minor, $graduationYear,
        $academicStanding, $enrollmentStatus, $_SESSION['user_id']
    ]);
    
    return ['success' => true, 'message' => 'Academic information updated successfully!'];
}

function updateContactInfo($db, $data) {
    $phone = sanitizeInput($data['phone'] ?? '');
    $address = sanitizeInput($data['address'] ?? '');
    $emergencyContactName = sanitizeInput($data['emergency_contact_name'] ?? '');
    $emergencyContactPhone = sanitizeInput($data['emergency_contact_phone'] ?? '');
    
    $stmt = $db->getConnection()->prepare("
        UPDATE profiles 
        SET phone = ?, address = ?, emergency_contact_name = ?, emergency_contact_phone = ?, updated_at = datetime('now')
        WHERE user_id = ?
    ");
    $stmt->execute([$phone, $address, $emergencyContactName, $emergencyContactPhone, $_SESSION['user_id']]);
    
    return ['success' => true, 'message' => 'Contact information updated successfully!'];
}

function updateProfileImage($db, $files) {
    $errors = [];
    
    if (!isset($files['profile_image']) || $files['profile_image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'No image file uploaded';
        return ['errors' => $errors];
    }
    
    $imageErrors = validateImage($files['profile_image']);
    if (!empty($imageErrors)) {
        return ['errors' => $imageErrors];
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = UPLOAD_PATH;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate secure filename
    $filename = generateSecureFilename($files['profile_image']['name']);
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($files['profile_image']['tmp_name'], $filepath)) {
        $errors[] = 'Failed to save image file';
        return ['errors' => $errors];
    }
    
    // Update database
    $stmt = $db->getConnection()->prepare("
        UPDATE profiles SET profile_image = ?, updated_at = datetime('now') WHERE user_id = ?
    ");
    $stmt->execute([$filename, $_SESSION['user_id']]);
    
    return ['success' => true, 'message' => 'Profile image updated successfully!'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0a0a0a">
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

        <?php if ($profile): ?>
        <!-- Profile Header -->
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-3">
                        <div class="text-center">
                            <?php if ($profile['profile_image']): ?>
                                <img src="<?php echo UPLOAD_PATH . htmlspecialchars($profile['profile_image']); ?>" 
                                     alt="Profile Image" 
                                     style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <div class="user-avatar" style="width: 120px; height: 120px; font-size: 3rem; margin: 0 auto;">
                                    <?php echo strtoupper(substr($profile['first_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-9">
                        <h1><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></h1>
                        <p class="text-muted"><?php echo htmlspecialchars($profile['email']); ?></p>
                        <?php if ($profile['student_id']): ?>
                            <p><strong>Student ID:</strong> <?php echo htmlspecialchars($profile['student_id']); ?></p>
                        <?php endif; ?>
                        <?php if ($profile['gpa']): ?>
                            <p><strong>GPA:</strong> <span class="text-primary"><?php echo number_format($profile['gpa'], 2); ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Sections -->
        <div class="row">
            <!-- Basic Information -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Basic Information</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="section" value="basic">
                            
                            <div class="form-group">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea id="bio" name="bio" class="form-control" rows="4" 
                                    placeholder="Tell us about yourself"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="interests" class="form-label">Interests</label>
                                <textarea id="interests" name="interests" class="form-control" rows="3" 
                                    placeholder="What are your interests and hobbies?"><?php echo htmlspecialchars($profile['interests'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="achievements" class="form-label">Achievements</label>
                                <textarea id="achievements" name="achievements" class="form-control" rows="3" 
                                    placeholder="List your academic and personal achievements"><?php echo htmlspecialchars($profile['achievements'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Basic Info</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Academic Information -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Academic Information</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="section" value="academic">
                            
                            <div class="form-group">
                                <label for="student_id" class="form-label">Student ID</label>
                                <input type="text" id="student_id" name="student_id" class="form-control" 
                                    value="<?php echo htmlspecialchars($profile['student_id'] ?? ''); ?>" 
                                    placeholder="Enter your student ID">
                            </div>
                            
                            <div class="form-group">
                                <label for="gpa" class="form-label">GPA</label>
                                <input type="number" id="gpa" name="gpa" class="form-control" 
                                    value="<?php echo $profile['gpa'] ?? ''; ?>" 
                                    min="0" max="10" step="0.01" placeholder="0.00">
                                <small class="text-muted">GPA scale: 0.0 to 10.0</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="major" class="form-label">Major</label>
                                        <input type="text" id="major" name="major" class="form-control" 
                                            value="<?php echo htmlspecialchars($profile['major'] ?? ''); ?>" 
                                            placeholder="Your major">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="minor" class="form-label">Minor</label>
                                        <input type="text" id="minor" name="minor" class="form-control" 
                                            value="<?php echo htmlspecialchars($profile['minor'] ?? ''); ?>" 
                                            placeholder="Your minor">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="graduation_year" class="form-label">Graduation Year</label>
                                <input type="number" id="graduation_year" name="graduation_year" class="form-control" 
                                    value="<?php echo $profile['graduation_year'] ?? ''; ?>" 
                                    min="2020" max="2030" placeholder="2024">
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="academic_standing" class="form-label">Academic Standing</label>
                                        <select id="academic_standing" name="academic_standing" class="form-control form-select">
                                            <option value="excellent" <?php echo ($profile['academic_standing'] ?? '') === 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                                            <option value="good" <?php echo ($profile['academic_standing'] ?? '') === 'good' ? 'selected' : ''; ?>>Good</option>
                                            <option value="satisfactory" <?php echo ($profile['academic_standing'] ?? '') === 'satisfactory' ? 'selected' : ''; ?>>Satisfactory</option>
                                            <option value="probation" <?php echo ($profile['academic_standing'] ?? '') === 'probation' ? 'selected' : ''; ?>>Probation</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="enrollment_status" class="form-label">Enrollment Status</label>
                                        <select id="enrollment_status" name="enrollment_status" class="form-control form-select">
                                            <option value="active" <?php echo ($profile['enrollment_status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($profile['enrollment_status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="graduated" <?php echo ($profile['enrollment_status'] ?? '') === 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                                            <option value="suspended" <?php echo ($profile['enrollment_status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Academic Info</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="row">
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Contact Information</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="section" value="contact">
                            
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                    value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" 
                                    placeholder="+1 (555) 123-4567">
                            </div>
                            
                            <div class="form-group">
                                <label for="address" class="form-label">Address</label>
                                <textarea id="address" name="address" class="form-control" rows="3" 
                                    placeholder="Your current address"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <h4>Emergency Contact</h4>
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="emergency_contact_name" class="form-label">Name</label>
                                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" 
                                            value="<?php echo htmlspecialchars($profile['emergency_contact_name'] ?? ''); ?>" 
                                            placeholder="Emergency contact name">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="emergency_contact_phone" class="form-label">Phone</label>
                                        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" 
                                            value="<?php echo htmlspecialchars($profile['emergency_contact_phone'] ?? ''); ?>" 
                                            placeholder="+1 (555) 123-4567">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Contact Info</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Profile Image -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Profile Image</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="section" value="image">
                            
                            <div class="form-group">
                                <label for="profile_image" class="form-label">Upload Image</label>
                                <input type="file" id="profile_image" name="profile_image" class="form-control" 
                                    accept="image/jpeg,image/png,image/gif">
                                <small class="text-muted">Max size: <?php echo formatFileSize(MAX_FILE_SIZE); ?>. Allowed: JPG, PNG, GIF</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Image</button>
                        </form>
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
            </div>
        </div>
        <?php endif; ?>
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

        // Form validation and interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // GPA validation
            const gpaInput = document.getElementById('gpa');
            if (gpaInput) {
                gpaInput.addEventListener('input', function() {
                    const value = parseFloat(this.value);
                    if (value > 10.0) {
                        this.value = 10.0;
                    } else if (value < 0) {
                        this.value = 0;
                    }
                });
            }

            // Phone number formatting
            const phoneInputs = document.querySelectorAll('input[type="tel"]');
            phoneInputs.forEach(input => {
                input.addEventListener('input', function() {
                    let value = this.value.replace(/\D/g, '');
                    if (value.length >= 10) {
                        value = value.substring(0, 10);
                        value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                    }
                    this.value = value;
                });
            });

            // Form submission feedback
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.textContent;
                    submitBtn.innerHTML = '<span class="loading"></span> Updating...';
                    submitBtn.disabled = true;
                });
            });
        });
    </script>
</body>
</html>
