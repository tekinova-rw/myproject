<?php
// reset_password.php
require 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_resp(['status'=>'error','message'=>'Invalid method'],405);

$email = strtolower(trim($_POST['email'] ?? ''));
$token = $_POST['token'] ?? '';
$new_password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (!$email || !$token || !$new_password || !$confirm) json_resp(['status'=>'error','message'=>'All fields required'],400);
if ($new_password !== $confirm) json_resp(['status'=>'error','message'=>"Passwords don't match"],400);
if (strlen($new_password) < 6) json_resp(['status'=>'error','message'=>'Password too short (min 6)'],400);

$stmt = $mysqli->prepare("SELECT id, reset_expires FROM users WHERE email = ? AND reset_token = ?");
$stmt->bind_param('ss', $email, $token);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    json_resp(['status'=>'error','message'=>'Invalid token or email'],400);
}
$stmt->bind_result($user_id, $reset_expires);
$stmt->fetch();
$stmt->close();

// check expiry
$now = new DateTime();
$exp = new DateTime($reset_expires);
if ($now > $exp) {
    json_resp(['status'=>'error','message'=>'Token expired'],400);
}

// update password and clear token
$hash = password_hash($new_password, PASSWORD_DEFAULT);
$upd = $mysqli->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
$upd->bind_param('si', $hash, $user_id);
if ($upd->execute()) {
    json_resp(['status'=>'success','message'=>'Password updated']);
} else {
    json_resp(['status'=>'error','message'=>'Update failed'],500);
}
