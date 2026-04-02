<?php
// register-company.php - Complete company registration
require_once 'config/config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $website = sanitizeInput($_POST['website'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($name)) $errors[] = "Company name is required";
    if (strlen($name) < 2) $errors[] = "Company name must be at least 2 characters";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    if (!empty($phone) && !preg_match("/^[0-9\+\-\s]+$/", $phone)) $errors[] = "Invalid phone number format";
    
    // Check if email exists
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id FROM companies WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Email already registered";
    }
    
    // Handle logo upload
    $logo = 'default-logo.png';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['logo'], 'uploads/');
        if ($upload['success']) {
            $logo = $upload['filename'];
        } else {
            $errors = array_merge($errors, $upload['errors']);
        }
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO companies (name, email, password, logo, website, description, status) 
                               VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        
        if ($stmt->execute([$name, $email, $hashed_password, $logo, $website, $description])) {
            $company_id = $pdo->lastInsertId();
            
            // Optional: Send notification email to admin
            // mail('admin@example.com', 'New Company Registration', "Company $name has registered and needs approval");
            
            $success = true;
        } else {
            $error = "Registration failed. Please try again.";
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
    <title>Register Company - Opportunity Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 50px 0;
        }
        .register-card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            padding: 25px;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .password-strength {
            height: 5px;
            margin-top: 8px;
            border-radius: 3px;
            overflow: hidden;
            background: #e9ecef;
        }
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        .requirement-list {
            list-style: none;
            padding-left: 0;
            font-size: 12px;
            margin-top: 8px;
        }
        .requirement-list li {
            margin-bottom: 3px;
        }
        .requirement-list li.valid {
            color: #28a745;
        }
        .requirement-list li.invalid {
            color: #6c757d;
        }
        .requirement-list li i {
            margin-right: 5px;
        }
        .preview-logo {
            max-width: 100px;
            max-height: 100px;
            margin-top: 10px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card register-card">
                    <div class="card-header text-white text-center">
                        <h3 class="mb-0"><i class="fas fa-building"></i> Company Registration</h3>
                        <p class="mb-0 mt-2">Join Opportunity Hub and start posting jobs, scholarships, and internships</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong><i class="fas fa-exclamation-triangle"></i> Error!</strong><br>
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(isset($success) && $success === true): ?>
                            <div class="alert alert-success text-center">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <h4>Registration Successful!</h4>
                                <p>Your company account has been created and is pending admin approval.</p>
                                <p>You will be notified once your account is approved.</p>
                                <hr>
                                <div class="text-start">
                                    <p><strong><i class="fas fa-building"></i> Company:</strong> <?php echo htmlspecialchars($name); ?></p>
                                    <p><strong><i class="fas fa-envelope"></i> Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                                </div>
                                <a href="login.php" class="btn btn-primary btn-lg mt-3">
                                    <i class="fas fa-sign-in-alt"></i> Go to Login
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="info-box">
                                <i class="fas fa-info-circle text-primary"></i>
                                <strong>Important Information:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>After registration, your account will be <strong>pending admin approval</strong></li>
                                    <li>You will receive an email notification once approved (if email configured)</li>
                                    <li>Approved companies can post unlimited opportunities</li>
                                    <li>All posts are reviewed and can be moderated by admin</li>
                                </ul>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data" id="registerForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-building"></i> Company Name *</label>
                                        <input type="text" name="name" class="form-control" required 
                                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                        <small class="text-muted">Your official company name</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-envelope"></i> Email *</label>
                                        <input type="email" name="email" class="form-control" required 
                                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                        <small class="text-muted">Used for login and notifications</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-lock"></i> Password *</label>
                                        <input type="password" name="password" id="password" class="form-control" required>
                                        <div class="password-strength">
                                            <div class="strength-bar"></div>
                                        </div>
                                        <ul class="requirement-list">
                                            <li id="length-req" class="invalid">
                                                <i class="far fa-circle"></i> At least 6 characters
                                            </li>
                                            <li id="lowercase-req" class="invalid">
                                                <i class="far fa-circle"></i> Lowercase letter (a-z)
                                            </li>
                                            <li id="uppercase-req" class="invalid">
                                                <i class="far fa-circle"></i> Uppercase letter (A-Z)
                                            </li>
                                            <li id="number-req" class="invalid">
                                                <i class="far fa-circle"></i> Number (0-9)
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-check-circle"></i> Confirm Password *</label>
                                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                        <div id="match-message" class="small mt-1"></div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-globe"></i> Website</label>
                                        <input type="url" name="website" class="form-control" 
                                               placeholder="https://www.example.com"
                                               value="<?php echo isset($_POST['website']) ? htmlspecialchars($_POST['website']) : ''; ?>">
                                        <small class="text-muted">Your company website (optional)</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fas fa-phone"></i> Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" 
                                               placeholder="+1 234 567 8900"
                                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                        <small class="text-muted">Contact number (optional)</small>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label"><i class="fas fa-map-marker-alt"></i> Address</label>
                                        <textarea name="address" class="form-control" rows="2" 
                                                  placeholder="Company address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                                        <small class="text-muted">Optional</small>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label"><i class="fas fa-image"></i> Company Logo</label>
                                        <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                                        <div id="logoPreview"></div>
                                        <small class="text-muted">Recommended size: 200x200px. Max 5MB (JPG, PNG, GIF)</small>
                                    </div>
                                    
                                    <div class="col-12 mb-3">
                                        <label class="form-label"><i class="fas fa-info-circle"></i> Company Description</label>
                                        <textarea name="description" class="form-control" rows="4" 
                                                  placeholder="Tell us about your company..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                        <small class="text-muted">Brief description of your company (optional)</small>
                                    </div>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100 py-2" id="submitBtn">
                                    <i class="fas fa-user-plus"></i> Register Company
                                </button>
                            </form>
                            
                            <div class="text-center mt-4">
                                <p class="mb-0">Already have an account?</p>
                                <a href="login.php" class="text-decoration-none">
                                    <i class="fas fa-sign-in-alt"></i> Login here
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Account Approval</h6>
                    <p>All company accounts require admin approval before posting opportunities.</p>
                    <h6>2. Content Guidelines</h6>
                    <p>All posted opportunities must be legitimate and comply with local laws.</p>
                    <h6>3. User Conduct</h6>
                    <p>Companies must respond to applicants in a timely manner.</p>
                    <h6>4. Account Suspension</h6>
                    <p>Violation of terms may result in account suspension or termination.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Accept</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const strengthBar = document.querySelector('.strength-bar');
        
        // Requirement elements
        const lengthReq = document.getElementById('length-req');
        const lowercaseReq = document.getElementById('lowercase-req');
        const uppercaseReq = document.getElementById('uppercase-req');
        const numberReq = document.getElementById('number-req');
        const matchMessage = document.getElementById('match-message');
        
        function validatePassword() {
            const password = passwordInput.value;
            let strength = 0;
            
            // Length check
            if (password.length >= 6) {
                lengthReq.classList.add('valid');
                lengthReq.classList.remove('invalid');
                lengthReq.innerHTML = '<i class="fas fa-check-circle"></i> At least 6 characters';
                strength++;
            } else {
                lengthReq.classList.add('invalid');
                lengthReq.classList.remove('valid');
                lengthReq.innerHTML = '<i class="far fa-circle"></i> At least 6 characters';
            }
            
            // Lowercase check
            if (/[a-z]/.test(password)) {
                lowercaseReq.classList.add('valid');
                lowercaseReq.classList.remove('invalid');
                lowercaseReq.innerHTML = '<i class="fas fa-check-circle"></i> Lowercase letter (a-z)';
                strength++;
            } else {
                lowercaseReq.classList.add('invalid');
                lowercaseReq.classList.remove('valid');
                lowercaseReq.innerHTML = '<i class="far fa-circle"></i> Lowercase letter (a-z)';
            }
            
            // Uppercase check
            if (/[A-Z]/.test(password)) {
                uppercaseReq.classList.add('valid');
                uppercaseReq.classList.remove('invalid');
                uppercaseReq.innerHTML = '<i class="fas fa-check-circle"></i> Uppercase letter (A-Z)';
                strength++;
            } else {
                uppercaseReq.classList.add('invalid');
                uppercaseReq.classList.remove('valid');
                uppercaseReq.innerHTML = '<i class="far fa-circle"></i> Uppercase letter (A-Z)';
            }
            
            // Number check
            if (/[0-9]/.test(password)) {
                numberReq.classList.add('valid');
                numberReq.classList.remove('invalid');
                numberReq.innerHTML = '<i class="fas fa-check-circle"></i> Number (0-9)';
                strength++;
            } else {
                numberReq.classList.add('invalid');
                numberReq.classList.remove('valid');
                numberReq.innerHTML = '<i class="far fa-circle"></i> Number (0-9)';
            }
            
            // Update strength bar
            const strengthPercent = (strength / 4) * 100;
            strengthBar.style.width = strengthPercent + '%';
            
            if (strength <= 1) {
                strengthBar.style.backgroundColor = '#dc3545';
            } else if (strength <= 2) {
                strengthBar.style.backgroundColor = '#ffc107';
            } else {
                strengthBar.style.backgroundColor = '#28a745';
            }
        }
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    matchMessage.innerHTML = '<span class="text-success">✓ Passwords match</span>';
                    return true;
                } else {
                    matchMessage.innerHTML = '<span class="text-danger">✗ Passwords do not match</span>';
                    return false;
                }
            } else {
                matchMessage.innerHTML = '';
                return false;
            }
        }
        
        // Logo preview
        document.getElementById('logo').addEventListener('change', function(e) {
            const preview = document.getElementById('logoPreview');
            const file = e.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" class="preview-logo" alt="Logo preview">`;
                }
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });
        
        // Event listeners
        passwordInput.addEventListener('input', function() {
            validatePassword();
            checkPasswordMatch();
        });
        
        confirmInput.addEventListener('input', checkPasswordMatch);
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const isMatch = checkPasswordMatch();
            const terms = document.getElementById('terms').checked;
            
            if (!isMatch) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (!terms) {
                e.preventDefault();
                alert('Please accept the Terms and Conditions');
                return false;
            }
        });
        
        // Initial validation
        validatePassword();
    </script>
</body>
</html>