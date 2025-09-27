<?php
require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is authenticated
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0a0a0a">
</head>
<body>
    <div class="container" style="max-width: 600px; margin: 4rem auto; text-align: center;">
        <div class="card">
            <div class="card-body">
                <div style="font-size: 4rem; margin-bottom: 2rem;">🚫</div>
                <h1>Access Denied</h1>
                <p class="text-muted">You don't have permission to access this page.</p>
                
                <div class="alert alert-warning">
                    <h3>What happened?</h3>
                    <p>You're logged in as <strong><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Unknown'); ?></strong>, 
                    but this page requires different permissions.</p>
                </div>
                
                <div class="d-flex gap-2 justify-content-center">
                    <a href="index.php" class="btn btn-primary">Go to Dashboard</a>
                    <a href="profile.php" class="btn btn-secondary">View Profile</a>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
                
                <div class="mt-4">
                    <p class="text-muted">
                        If you believe this is an error, please contact your administrator.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
