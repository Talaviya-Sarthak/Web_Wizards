<?php
require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize database connection
$db = new Database();

// Check authentication and admin role
requireAnyRole(['admin', 'editor', 'viewer']);

$errors = [];
$success = '';

// Get search and filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$role_filter = sanitizeInput($_GET['role'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR p.student_id LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($role_filter)) {
    $conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $conditions[] = "p.enrollment_status = ?";
    $params[] = $status_filter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count for pagination
try {
    $countStmt = $db->getConnection()->prepare("
        SELECT COUNT(*) as total
        FROM users u
        LEFT JOIN profiles p ON u.id = p.user_id
        {$whereClause}
    ");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $per_page);
    
    // Get users with profiles
    $stmt = $db->getConnection()->prepare("
        SELECT u.*, p.student_id, p.gpa, p.major, p.academic_standing, p.enrollment_status,
               p.created_at as profile_created, p.updated_at as profile_updated
        FROM users u
        LEFT JOIN profiles p ON u.id = p.user_id
        {$whereClause}
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $users = $stmt->fetchAll();
    
    // Log activity
    logActivity('admin_dashboard_view', 'admin', null, [
        'search' => $search,
        'filters' => ['role' => $role_filter, 'status' => $status_filter],
        'page' => $page
    ]);
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $users = [];
    $totalRecords = 0;
    $totalPages = 0;
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'];
        $userIds = $_POST['user_ids'] ?? [];
        
        if (empty($userIds)) {
            $errors[] = 'Please select at least one user.';
        } else {
            try {
                $db->beginTransaction();
                
                switch ($action) {
                    case 'activate':
                        $stmt = $db->getConnection()->prepare("UPDATE users SET is_active = TRUE WHERE id = ?");
                        foreach ($userIds as $userId) {
                            $stmt->execute([$userId]);
                        }
                        $success = 'Selected users have been activated.';
                        break;
                        
                    case 'deactivate':
                        $stmt = $db->getConnection()->prepare("UPDATE users SET is_active = FALSE WHERE id = ?");
                        foreach ($userIds as $userId) {
                            $stmt->execute([$userId]);
                        }
                        $success = 'Selected users have been deactivated.';
                        break;
                        
                    case 'delete':
                        if (!hasRole('admin')) {
                            $errors[] = 'You do not have permission to delete users.';
                        } else {
                            $stmt = $db->getConnection()->prepare("DELETE FROM users WHERE id = ?");
                            foreach ($userIds as $userId) {
                                $stmt->execute([$userId]);
                            }
                            $success = 'Selected users have been deleted.';
                        }
                        break;
                        
                    case 'export':
                        // Handle CSV export
                        header('Content-Type: text/csv');
                        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i-s') . '.csv"');
                        
                        $output = fopen('php://output', 'w');
                        fputcsv($output, ['ID', 'Username', 'Email', 'Name', 'Role', 'Status', 'Student ID', 'GPA', 'Major', 'Created']);
                        
                        foreach ($users as $user) {
                            fputcsv($output, [
                                $user['id'],
                                $user['username'],
                                $user['email'],
                                $user['first_name'] . ' ' . $user['last_name'],
                                $user['role'],
                                $user['is_active'] ? 'Active' : 'Inactive',
                                $user['student_id'] ?? '',
                                $user['gpa'] ?? '',
                                $user['major'] ?? '',
                                $user['created_at']
                            ]);
                        }
                        
                        fclose($output);
                        exit;
                }
                
                $db->commit();
                logActivity('bulk_action', 'admin', null, ['action' => $action, 'user_count' => count($userIds)]);
                
            } catch (Exception $e) {
                $db->rollback();
                error_log("Bulk action error: " . $e->getMessage());
                $errors[] = 'An error occurred while processing the request.';
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
    <title>Admin Panel - <?php echo APP_NAME; ?></title>
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
                    <a href="admin.php" class="nav-link">Admin</a>
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
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">User Management</h2>
                        <p class="card-subtitle">Manage student profiles and user accounts</p>
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
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Search and Filters -->
                        <form method="GET" action="" class="mb-4">
                            <div class="row">
                                <div class="col-4">
                                    <div class="form-group">
                                        <label for="search" class="form-label">Search</label>
                                        <input type="text" id="search" name="search" class="form-control" 
                                            placeholder="Search by name, email, or student ID"
                                            value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                <div class="col-2">
                                    <div class="form-group">
                                        <label for="role" class="form-label">Role</label>
                                        <select id="role" name="role" class="form-control form-select">
                                            <option value="">All Roles</option>
                                            <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            <option value="editor" <?php echo $role_filter === 'editor' ? 'selected' : ''; ?>>Editor</option>
                                            <option value="viewer" <?php echo $role_filter === 'viewer' ? 'selected' : ''; ?>>Viewer</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-2">
                                    <div class="form-group">
                                        <label for="status" class="form-label">Status</label>
                                        <select id="status" name="status" class="form-control form-select">
                                            <option value="">All Status</option>
                                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="graduated" <?php echo $status_filter === 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                                            <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">Search</button>
                                            <a href="admin.php" class="btn btn-secondary">Clear</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Bulk Actions -->
                        <form method="POST" action="" id="bulkActionsForm" class="mb-4">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <button type="button" class="btn btn-secondary" onclick="selectAll()">Select All</button>
                                    <button type="button" class="btn btn-secondary" onclick="selectNone()">Select None</button>
                                </div>
                                <div class="d-flex gap-2">
                                    <select name="action" class="form-control" style="width: auto;">
                                        <option value="">Bulk Actions</option>
                                        <option value="activate">Activate Selected</option>
                                        <option value="deactivate">Deactivate Selected</option>
                                        <?php if (hasRole('admin')): ?>
                                        <option value="delete">Delete Selected</option>
                                        <?php endif; ?>
                                        <option value="export">Export Selected</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary">Apply</button>
                                </div>
                            </div>

                            <!-- Users Table -->
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>
                                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                            </th>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Student ID</th>
                                            <th>GPA</th>
                                            <th>Major</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" class="user-checkbox">
                                            </td>
                                            <td><?php echo $user['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                <br><small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                                <?php if ($user['enrollment_status']): ?>
                                                <br><small class="text-muted"><?php echo ucfirst($user['enrollment_status']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['student_id'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if ($user['gpa']): ?>
                                                    <span class="text-primary"><?php echo number_format($user['gpa'], 2); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['major'] ?? 'Undeclared'); ?></td>
                                            <td>
                                                <small><?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="admin-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                                    <?php if (hasAnyRole(['admin', 'editor'])): ?>
                                                    <a href="admin-user.php?id=<?php echo $user['id']; ?>&edit=1" class="btn btn-sm btn-primary">Edit</a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted">
                                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $totalRecords); ?> 
                                    of <?php echo $totalRecords; ?> users
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-secondary">Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                       class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-secondary">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
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

        // Bulk selection functions
        function selectAll() {
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
            document.getElementById('selectAll').checked = true;
        }

        function selectNone() {
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('selectAll').checked = false;
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        // Update select all checkbox when individual checkboxes change
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.user-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
                document.getElementById('selectAll').checked = allCheckboxes.length === checkedCheckboxes.length;
            });
        });

        // Form submission confirmation
        document.getElementById('bulkActionsForm').addEventListener('submit', function(e) {
            const action = this.querySelector('select[name="action"]').value;
            const selectedUsers = document.querySelectorAll('.user-checkbox:checked').length;
            
            if (!action) {
                e.preventDefault();
                alert('Please select an action.');
                return;
            }
            
            if (selectedUsers === 0) {
                e.preventDefault();
                alert('Please select at least one user.');
                return;
            }
            
            if (action === 'delete') {
                if (!confirm(`Are you sure you want to delete ${selectedUsers} user(s)? This action cannot be undone.`)) {
                    e.preventDefault();
                }
            } else if (action === 'deactivate') {
                if (!confirm(`Are you sure you want to deactivate ${selectedUsers} user(s)?`)) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>
