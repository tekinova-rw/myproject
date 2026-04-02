<?php
// config/config.php
// Application configuration

session_start();

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Site configuration
define('SITE_NAME', 'PAYFODA Exam Portal');
define('SITE_URL', 'http://localhost/newupdate/payfoda');
define('ADMIN_EMAIL', 'admin@payfoda.com');

// Payment Plans
define('PAYMENT_PLANS', [
    'basic' => [
        'amount' => 500,
        'currency' => 'FRW',
        'days' => 7,
        'name' => 'Basic Plan - 1 Week Access'
    ],
    'premium' => [
        'amount' => 1500,
        'currency' => 'FRW',
        'days' => 30,
        'name' => 'Premium Plan - 1 Month Access'
    ]
]);

// Default payment settings (for backward compatibility)
define('PAYMENT_AMOUNT', 500);
define('ACCESS_DURATION_DAYS', 7);

// File upload settings for payment proofs (optional now)
define('UPLOAD_DIR', ROOT_PATH . '/assets/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
define('UPLOAD_REQUIRED', false); // Make upload optional

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 7200); // 2 hours

// Timezone
date_default_timezone_set('Africa/Kigali'); // Rwanda timezone

// Load database configuration
if (file_exists(ROOT_PATH . '/config/database.php')) {
    require_once ROOT_PATH . '/config/database.php';
} else {
    die('Error: config/database.php file not found. Please create it.');
}

// Load helper functions
if (file_exists(ROOT_PATH . '/includes/functions.php')) {
    require_once ROOT_PATH . '/includes/functions.php';
} else {
    die('Error: includes/functions.php file not found. Please create it in the includes folder.');
}

// CSRF Token Functions
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || $token !== $_SESSION[CSRF_TOKEN_NAME]) {
        return false;
    }
    return true;
}

// Check if user is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}

// Redirect if not logged in
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: admin_login.php');
        exit();
    }
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Get access duration based on amount
function getAccessDuration($amount) {
    if ($amount == 1500) {
        return 30; // 1 month
    } else {
        return 7; // 1 week (default for 500 FRW)
    }
}

// Format currency
function formatCurrency($amount, $currency = 'FRW') {
    return number_format($amount, 0) . ' ' . $currency;
}

// Debug function to check if files exist
function checkRequiredFiles() {
    $requiredFiles = [
        ROOT_PATH . '/config/database.php',
        ROOT_PATH . '/includes/functions.php',
        ROOT_PATH . '/includes/auth.php',
        ROOT_PATH . '/includes/User.php'
    ];
    
    $missing = [];
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            $missing[] = $file;
        }
    }
    
    if (!empty($missing)) {
        echo "<div style='background:#f8d7da; color:#721c24; padding:15px; margin:10px; border-radius:5px;'>";
        echo "<h3>Missing Required Files:</h3>";
        echo "<ul>";
        foreach ($missing as $file) {
            echo "<li>" . str_replace(ROOT_PATH, '', $file) . "</li>";
        }
        echo "</ul>";
        echo "<p>Please create these files to continue.</p>";
        echo "</div>";
        return false;
    }
    
    return true;
}
?>