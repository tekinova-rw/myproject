<?php
// logout.php
session_start();
session_destroy();
header("Location: login.php");
exit();
?>

<?php
// reset_password.php
require_once 'config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            // Generate reset token (simplified - in production, send email)
            $token = bin2hex(random_bytes(32));
            $_SESSION['reset_token'] = $token;
            $_SESSION['reset_email'] = $email;
            
            $success = "Password reset link: <a href='reset_password.php?token=$token'>Click here to reset password</a><br>
                       (In production, this would be sent to your email)";
        } else {
            $error = 'Email not found';
        }
    }
}

if (isset($_GET['token']) && isset($_SESSION['reset_token']) && $_GET['token'] === $_SESSION['reset_token']) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            $pdo = getDB();
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE companies SET password = ? WHERE email = ?");
            $stmt->execute([$hashed, $_SESSION['reset_email']]);
            
            unset($_SESSION['reset_token']);
            unset($_SESSION['reset_email']);
            
            $success = 'Password reset successful! <a href="login.php">Login here</a>';
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Reset Password</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Reset Password</div>
                        <div class="card-body">
                            <?php if($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            <?php if($success): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Forgot Password</div>
                    <div class="card-body">
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label>Email Address</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Send Reset Link</button>
                            <a href="login.php" class="btn btn-secondary">Back to Login</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>