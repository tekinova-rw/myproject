<?php
// config/config.php - COMPLETE UPDATED VERSION
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'opportunity_hub');

// Security settings
define('SALT', 'your-secret-salt-key-here-change-it-to-something-strong');
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5); // Max login attempts before lockout
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes lockout

// File upload settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Application settings
define('SITE_NAME', 'Opportunity Hub');
define('SITE_URL', 'http://localhost/newupdate/');
define('ADMIN_EMAIL', 'admin@opportunityhub.com');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
function getDB() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Prevent SQL injection
        return $pdo;
    } catch(PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Connection failed. Please try again later.");
    }
}

// Security functions
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateURL($url) {
    return filter_var($url, FILTER_VALIDATE_URL);
}

// Session management
function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        return false;
    }
    
    // Optional: Check IP address consistency
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        session_destroy();
        return false;
    }
    
    // Optional: Check user agent consistency
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function isCompany() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'company';
}

function isCompanyApproved($company_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT status FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch();
    return $company && $company['status'] === 'approved';
}

function redirect($url) {
    if (headers_sent()) {
        echo "<script>window.location.href='$url';</script>";
    } else {
        header("Location: $url");
    }
    exit();
}

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// File upload handling
function uploadFile($file, $target_dir = "../uploads/") {
    $errors = [];
    
    // Check if directory exists, create if not
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "File size exceeds " . (MAX_FILE_SIZE / 1048576) . "MB limit";
    }
    
    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check file type
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        $errors[] = "Only " . implode(', ', ALLOWED_EXTENSIONS) . " files are allowed";
    }
    
    // Check for virus (basic check)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime_type, $allowed_mimes)) {
        $errors[] = "Invalid file type detected";
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    // Generate unique filename
    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $target_file = $target_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'errors' => ['Failed to upload file']];
    }
}

// Time management
function timeRemaining($deadline) {
    $now = new DateTime();
    $end = new DateTime($deadline);
    
    if ($now > $end) {
        return ['expired' => true, 'text' => 'Expired'];
    }
    
    $diff = $now->diff($end);
    $totalSeconds = ($diff->days * 24 * 3600) + ($diff->h * 3600) + ($diff->i * 60) + $diff->s;
    
    $days = floor($totalSeconds / 86400);
    $hours = floor(($totalSeconds % 86400) / 3600);
    $minutes = floor(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;
    
    if ($days > 0) {
        $text = sprintf("%dd %02d:%02d:%02d", $days, $hours, $minutes, $seconds);
    } else {
        $text = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
    }
    
    return [
        'expired' => false,
        'days' => $days,
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds,
        'text' => $text
    ];
}

// Content management
function autoManageExpiredContent() {
    $pdo = getDB();
    
    try {
        // Archive expired opportunities
        $stmt = $pdo->prepare("UPDATE opportunities SET status = 'archived' WHERE deadline < NOW() AND status = 'active'");
        $stmt->execute();
        
        // Delete expired news
        $stmt = $pdo->prepare("DELETE FROM news WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        $stmt->execute();
        
        // Also delete news older than 1 month if no expiry set
        $stmt = $pdo->prepare("DELETE FROM news WHERE expires_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)");
        $stmt->execute();
        
    } catch(PDOException $e) {
        error_log("Auto cleanup failed: " . $e->getMessage());
    }
}

// Password strength checker
function checkPasswordStrength($password) {
    $strength = 0;
    $feedback = [];
    
    if (strlen($password) >= 8) {
        $strength++;
    } else {
        $feedback[] = "Password should be at least 8 characters";
    }
    
    if (preg_match('/[a-z]/', $password)) {
        $strength++;
    } else {
        $feedback[] = "Add lowercase letters";
    }
    
    if (preg_match('/[A-Z]/', $password)) {
        $strength++;
    } else {
        $feedback[] = "Add uppercase letters";
    }
    
    if (preg_match('/[0-9]/', $password)) {
        $strength++;
    } else {
        $feedback[] = "Add numbers";
    }
    
    if (preg_match('/[^a-zA-Z0-9]/', $password)) {
        $strength++;
    } else {
        $feedback[] = "Add special characters (!@#$%^&*)";
    }
    
    $level = '';
    if ($strength <= 2) {
        $level = 'Weak';
    } elseif ($strength <= 3) {
        $level = 'Medium';
    } elseif ($strength <= 4) {
        $level = 'Strong';
    } else {
        $level = 'Very Strong';
    }
    
    return [
        'score' => $strength,
        'level' => $level,
        'feedback' => $feedback
    ];
}

// Generate slug for URLs
function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// Get user IP address
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Log activity
function logActivity($user_id, $action, $details = null) {
    $pdo = getDB();
    $ip = getUserIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $action, $details, $ip, $user_agent]);
}

// Create activity_logs table if not exists (run this once)
function createActivityLogsTable() {
    $pdo = getDB();
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at)
    )";
    $pdo->exec($sql);
}

// Send email notification (basic implementation)
function sendEmail($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SITE_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Generate random string
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Check if request is AJAX
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Set flash message
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
        'time' => time()
    ];
}

// Get and clear flash message
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// Rate limiting
function checkRateLimit($key, $limit = 10, $period = 60) {
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'first_request' => time()];
        return true;
    }
    
    $data = $_SESSION['rate_limit'][$key];
    $time_passed = time() - $data['first_request'];
    
    if ($time_passed > $period) {
        // Reset after period
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'first_request' => time()];
        return true;
    }
    
    if ($data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION['rate_limit'][$key]['count']++;
    return true;
}

// Create activity logs table on first run
createActivityLogsTable();
?>