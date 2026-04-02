<?php
require_once 'config/config.php';

// This file is for utility purposes - only accessible by admins
// For security, you should add IP restriction or admin session check
if (!isset($_GET['admin_key']) || $_GET['admin_key'] !== 'your_secret_key') {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied');
}

$email = isset($_GET['email']) ? sanitize($_GET['email']) : '';
$hash = generateUniqueHash($email);

echo json_encode([
    'email' => $email,
    'hash' => $hash
]);
?>