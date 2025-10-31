<?php
// signup.php
require 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_resp(['status'=>'error','message'=>'Invalid method'], 405);
}

// get POST data (works whether fetch or form POST)
$full_name = trim($_POST['full_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (!$full_name || !$email || !$password || !$confirm) {
    json_resp(['status'=>'error','message'=>'All fields required'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_resp(['status'=>'error','message'=>'Invalid email'], 400);
}
if ($password !== $confirm) {
    json_resp(['status'=>'error','message'=>"Passwords don't match"], 400);
}
if (strlen($password) < 6) {
    json_resp(['status'=>'error','message'=>'Password too short (min 6)'], 400);
}

// check existing email
$stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    json_resp(['status'=>'error','message'=>'Email already registered'], 409);
}
$stmt->close();

// insert user
$hash = password_hash($password, PASSWORD_DEFAULT);
$ins = $mysqli->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
$ins->bind_param('sss', $full_name, $email, $hash);
if ($ins->execute()) {
    json_resp(['status'=>'success','message'=>'Account created']);
} else {
    json_resp(['status'=>'error','message'=>'Insert failed: '.$mysqli->error], 500);
}
