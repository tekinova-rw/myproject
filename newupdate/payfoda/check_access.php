<?php
// check_files.php
// This file checks if all required files exist

echo "<h2>PAYFODA File Checker</h2>";
echo "<pre>";

$basePath = __DIR__;

$requiredFiles = [
    'config/config.php',
    'config/database.php',
    'includes/functions.php',
    'includes/auth.php',
    'includes/User.php',
    'pay.php',
    'admin_login.php',
    'admin_dashboard.php',
    'admin_register.php',
    'admin_logout.php',
    'approve_payment.php',
    'PROVI/index.php'
];

$allExist = true;

foreach ($requiredFiles as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        echo "✅ " . $file . " - OK\n";
    } else {
        echo "❌ " . $file . " - MISSING\n";
        $allExist = false;
    }
}

echo "\n";

if ($allExist) {
    echo "🎉 All required files exist!\n";
    echo "\nNext steps:\n";
    echo "1. Create database using SQL schema\n";
    echo "2. Run create_admin.php to create admin user\n";
    echo "3. Visit: " . SITE_URL . "/pay.php\n";
} else {
    echo "⚠️ Some files are missing. Please create them.\n";
}

echo "</pre>";
?>