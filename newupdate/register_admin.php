<?php
// register_admin.php - ONE TIME USE ONLY
require_once 'config/config.php';

// Check if admin already exists
$pdo = getDB();
$stmt = $pdo->query("SELECT COUNT(*) as count FROM admin");
$result = $stmt->fetch();

if ($result['count'] > 0) {
    // Admin exists - show error and don't allow registration
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Admin Already Exists</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body>
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4>Access Denied!</h4>
                <p>An admin account already exists. Only one admin account is allowed for security reasons.</p>
                <a href='login.php' class='btn btn-primary'>Go to Login</a>
            </div>
        </div>
    </body>
    </html>
    ");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (empty($username)) $errors[] = "Username is required";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    
    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) $errors[] = "Username already exists";
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
        
        if ($stmt->execute([$username, $hashed_password])) {
            $success = "Admin account created successfully! <a href='login.php'>Login here</a>";
        } else {
            $error = "Failed to create admin account";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration - One Time Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0">⚠️ Admin Registration (One-Time Only)</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning warning">
                            <strong>⚠️ IMPORTANT:</strong> This page can only be used ONCE. 
                            After creating an admin account, this page will be disabled automatically.
                            Only ONE admin account is allowed for security.
                        </div>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label>Username *</label>
                                    <input type="text" name="username" class="form-control" required 
                                           placeholder="Choose a username (e.g., admin)">
                                    <small class="text-muted">This will be your login username</small>
                                </div>
                                <div class="mb-3">
                                    <label>Password * (min 6 characters)</label>
                                    <input type="password" name="password" class="form-control" required>
                                    <small class="text-muted">Choose a strong password</small>
                                </div>
                                <div class="mb-3">
                                    <label>Confirm Password *</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Create Admin Account</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="login.php" class="text-muted">← Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>