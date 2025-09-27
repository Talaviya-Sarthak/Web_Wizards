<?php
// Simple PHP test file
echo "<h1>🎓 Student Portal Test</h1>";
echo "<p>✅ PHP is working!</p>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Current Time: " . date('Y-m-d H:i:s') . "</p>";

// Check required extensions
$extensions = ['pdo', 'pdo_mysql', 'json', 'session'];
echo "<h2>Required Extensions:</h2>";
foreach ($extensions as $ext) {
    $status = extension_loaded($ext) ? '✅' : '❌';
    echo "<p>{$status} {$ext}</p>";
}

// Check file permissions
echo "<h2>File Permissions:</h2>";
echo "<p>" . (is_writable('.') ? '✅' : '❌') . " Current directory writable</p>";
echo "<p>" . (is_writable('uploads') ? '✅' : '❌') . " Uploads directory writable</p>";

echo "<h2>Next Steps:</h2>";
echo "<p>1. If all checks pass, go to <a href='install.php'>install.php</a></p>";
echo "<p>2. If any checks fail, install XAMPP or fix the issues</p>";
?>
