<?php
// create_admin.php - Run from command line: php create_admin.php
require_once 'config/config.php';

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line\n");
}

echo "=== Admin Account Creator ===\n\n";

$pdo = getDB();
$stmt = $pdo->query("SELECT COUNT(*) as count FROM admin");
$adminCount = $stmt->fetchColumn();

if ($adminCount > 0) {
    echo "❌ Admin account already exists! Only one admin is allowed.\n";
    exit(1);
}

echo "Enter username: ";
$username = trim(fgets(STDIN));

echo "Enter password (min 6 characters): ";
$password = trim(fgets(STDIN));

if (strlen($password) < 6) {
    echo "❌ Password must be at least 6 characters\n";
    exit(1);
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
if ($stmt->execute([$username, $hashed_password])) {
    echo "✅ Admin account created successfully!\n";
    echo "Username: $username\n";
    echo "You can now login at login.php\n";
} else {
    echo "❌ Failed to create admin account\n";
}
?>