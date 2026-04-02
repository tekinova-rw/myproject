<?php
// create_admin.php
// Run this only once to create the first admin account

require_once 'config/config.php';

// Check if any admin exists
$sql = "SELECT COUNT(*) as count FROM admins";
$stmt = $db->prepare($sql);
$stmt->execute();
$adminCount = $stmt->fetch()['count'];

if ($adminCount > 0) {
    die('⚠️ Admin account already exists. No additional admins can be created.');
}

$username = 'admin';
$email = 'admin@payfoda.com';
$password = 'Admin@123';
$role = 'super_admin';

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $sql = "INSERT INTO admins (username, email, password_hash, role, is_active) 
            VALUES (:username, :email, :password_hash, :role, 1)";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':role' => $role
    ]);
    
    if ($result) {
        echo "<h2>✅ First Admin Account Created Successfully!</h2>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> Admin@123</p>";
        echo "<p><strong>Role:</strong> Super Admin</p>";
        echo "<p><a href='admin_login.php'>Click here to login</a></p>";
        echo "<p style='color: red;'><strong>⚠️ IMPORTANT:</strong> Delete this file after setup for security!</p>";
    } else {
        echo "❌ Failed to create admin account";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>