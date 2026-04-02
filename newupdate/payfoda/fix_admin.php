<?php
// fix_admin.php
// Run this to ensure admin user exists

require_once 'config/config.php';

echo "<h1>Admin Account Fix</h1>";

// Check if admin exists
$sql = "SELECT COUNT(*) as count FROM admins";
$stmt = $db->prepare($sql);
$stmt->execute();
$count = $stmt->fetch()['count'];

if ($count == 0) {
    echo "<p>No admin found. Creating admin account...</p>";
    
    $username = 'admin';
    $email = 'admin@payfoda.com';
    $password = 'Admin@123';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO admins (username, email, password_hash, role, is_active) 
            VALUES (:username, :email, :password_hash, 'super_admin', 1)";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash
    ]);
    
    if ($result) {
        echo "<p style='color:green'>✅ Admin account created successfully!</p>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> Admin@123</p>";
    } else {
        echo "<p style='color:red'>❌ Failed to create admin account</p>";
    }
} else {
    echo "<p style='color:green'>✅ Admin account already exists</p>";
    
    // Show admin details
    $sql = "SELECT id, username, email, role FROM admins";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $admins = $stmt->fetchAll();
    
    echo "<h3>Existing Admin Accounts:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
    foreach ($admins as $admin) {
        echo "<tr>";
        echo "<td>{$admin['id']}</td>";
        echo "<td>{$admin['username']}</td>";
        echo "<td>{$admin['email']}</td>";
        echo "<td>{$admin['role']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<br><a href='admin_login.php'>Go to Login</a>";
?>