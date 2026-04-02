<?php
// login.php - COMPLETE UPDATED VERSION with security improvements
require_once 'config/config.php';

// Security: Only allow POST requests for login processing
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success_message = '';
$show_debug = isset($_GET['debug']) && $_GET['debug'] === 'true';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token validation failed. Please refresh the page and try again.';
    } else {
        $login_input = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $user_type = sanitizeInput($_POST['user_type'] ?? '');
        $remember_me = isset($_POST['remember_me']) ? true : false;
        
        if (empty($login_input) || empty($password)) {
            $error = 'Please fill in all fields';
        } else {
            try {
                $pdo = getDB();
                
                if ($user_type === 'admin') {
                    // Admin login - check by username OR email
                    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ? OR email = ? LIMIT 1");
                    $stmt->execute([$login_input, $login_input]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password'])) {
                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_type'] = 'admin';
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['admin_email'] = $user['email'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                        
                        // Remember me functionality
                        if ($remember_me) {
                            $token = bin2hex(random_bytes(32));
                            $_SESSION['remember_token'] = $token;
                            setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true);
                        }
                        
                        // Update last login timestamp
                        $stmt = $pdo->prepare("UPDATE admin SET last_login = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        
                        $success_message = 'Login successful! Redirecting to admin panel...';
                        header("Refresh: 2; url=dashboard/admin.php");
                    } else {
                        $error = 'Invalid admin credentials. Please check your username/email and password.';
                        // Log failed attempt (optional)
                        error_log("Failed admin login attempt for: " . $login_input . " from IP: " . $_SERVER['REMOTE_ADDR']);
                    }
                } else {
                    // Company login - check by email
                    $stmt = $pdo->prepare("SELECT * FROM companies WHERE email = ? LIMIT 1");
                    $stmt->execute([$login_input]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password'])) {
                        // Check account status
                        if ($user['status'] === 'suspended') {
                            $error = 'Your account has been suspended. Please contact the administrator for assistance.';
                        } elseif ($user['status'] === 'pending') {
                            $error = 'Your account is pending admin approval. You will receive an email once approved.';
                        } else {
                            // Regenerate session ID to prevent session fixation
                            session_regenerate_id(true);
                            
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_type'] = 'company';
                            $_SESSION['company_name'] = $user['name'];
                            $_SESSION['company_email'] = $user['email'];
                            $_SESSION['last_activity'] = time();
                            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                            
                            // Remember me functionality
                            if ($remember_me) {
                                $token = bin2hex(random_bytes(32));
                                $_SESSION['remember_token'] = $token;
                                setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true);
                            }
                            
                            $success_message = 'Login successful! Redirecting to company dashboard...';
                            header("Refresh: 2; url=dashboard/company.php");
                        }
                    } else {
                        $error = 'Invalid email or password. Please try again.';
                        // Log failed attempt
                        error_log("Failed company login attempt for: " . $login_input . " from IP: " . $_SERVER['REMOTE_ADDR']);
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error occurred. Please try again later.';
                error_log("Login database error: " . $e->getMessage());
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Opportunity Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
            transition: transform 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .card-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .info-badge {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 10px;
            margin-top: 15px;
            font-size: 12px;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-card {
            animation: fadeIn 0.6s ease-out;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
        }
        
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading.active {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>
    
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card login-card">
                        <div class="card-header">
                            <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                            <h3>Welcome Back!</h3>
                            <p>Login to access your account</p>
                        </div>
                        <div class="card-body p-4">
                            <?php if($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <strong>Error!</strong> <?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if($success_message): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Success!</strong> <?php echo $success_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Debug Information (only when enabled) -->
                            <?php if($show_debug && !empty($debug)): ?>
                                <div class="alert alert-info debug-info">
                                    <strong><i class="fas fa-bug"></i> Debug Info:</strong>
                                    <pre style="margin: 10px 0; font-size: 11px;"><?php print_r($debug); ?></pre>
                                    <small>Remove ?debug from URL to hide this</small>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="" id="loginForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-user-tag"></i> Login As
                                    </label>
                                    <select name="user_type" class="form-select" required>
                                        <option value="company">🏢 Company / Employer</option>
                                        <option value="admin">👑 Administrator</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-envelope"></i> Email / Username
                                    </label>
                                    <input type="text" name="email" class="form-control" required 
                                           placeholder="Enter your email or username"
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                           autocomplete="username">
                                    <small class="text-muted" id="loginHint">
                                        <i class="fas fa-info-circle"></i> 
                                        <span class="company-hint">Enter your registered email address</span>
                                        <span class="admin-hint" style="display:none;">Enter username OR email address</span>
                                    </small>
                                </div>
                                
                                <div class="mb-3 password-toggle">
                                    <label class="form-label">
                                        <i class="fas fa-lock"></i> Password
                                    </label>
                                    <input type="password" name="password" id="password" class="form-control" required 
                                           placeholder="Enter your password" autocomplete="current-password">
                                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                                </div>
                                
                                <div class="mb-3 d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <input type="checkbox" name="remember_me" class="form-check-input" id="rememberMe">
                                        <label class="form-check-label" for="rememberMe">
                                            <i class="fas fa-check-circle"></i> Remember Me
                                        </label>
                                    </div>
                                    <a href="reset_password.php" class="text-decoration-none small">
                                        <i class="fas fa-key"></i> Forgot Password?
                                    </a>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-login w-100" id="loginBtn">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </button>
                            </form>
                            
                            <div class="text-center mt-4">
                                <p class="mb-2">Don't have an account?</p>
                                <a href="register-company.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-building"></i> Register as Company
                                </a>
                            </div>
                            
                            <div class="info-badge text-center">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt"></i> Secure Login | 
                                    <i class="fas fa-clock"></i> Session expires after 1 hour
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
        
        // Update hint based on user type selection
        const userTypeSelect = document.querySelector('select[name="user_type"]');
        const companyHint = document.querySelector('.company-hint');
        const adminHint = document.querySelector('.admin-hint');
        
        if (userTypeSelect) {
            userTypeSelect.addEventListener('change', function() {
                if (this.value === 'admin') {
                    companyHint.style.display = 'none';
                    adminHint.style.display = 'inline';
                } else {
                    companyHint.style.display = 'inline';
                    adminHint.style.display = 'none';
                }
            });
        }
        
        // Show loading spinner on form submit
        const loginForm = document.getElementById('loginForm');
        const loadingDiv = document.getElementById('loading');
        const loginBtn = document.getElementById('loginBtn');
        
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const email = document.querySelector('input[name="email"]').value;
                const password = document.querySelector('input[name="password"]').value;
                const userType = document.querySelector('select[name="user_type"]').value;
                
                // Client-side validation
                if (!email || !password) {
                    e.preventDefault();
                    alert('Please enter both email/username and password');
                    return false;
                }
                
                if (userType === 'company' && !email.includes('@')) {
                    e.preventDefault();
                    alert('For company login, please enter a valid email address');
                    return false;
                }
                
                // Show loading spinner
                loadingDiv.classList.add('active');
                loginBtn.disabled = true;
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
                
                // Allow form submission
                return true;
            });
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Add floating label effect
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
        });
        
        // Prevent multiple form submissions
        let formSubmitted = false;
        loginForm.addEventListener('submit', function() {
            if (formSubmitted) {
                return false;
            }
            formSubmitted = true;
            return true;
        });
        
        // Add keyboard shortcut (Enter to submit)
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && document.activeElement === password) {
                loginForm.submit();
            }
        });
    </script>
</body>
</html>