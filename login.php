<?php
// login.php
require 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_resp(['status'=>'error','message'=>'Invalid method'], 405);
}

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    json_resp(['status'=>'error','message'=>'Email and password required'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_resp(['status'=>'error','message'=>'Invalid email'], 400);
}

$stmt = $mysqli->prepare("SELECT id, full_name, password_hash FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    json_resp(['status'=>'error','message'=>'Invalid credentials'], 401);
}
$stmt->bind_result($id, $full_name, $password_hash);
$stmt->fetch();
$stmt->close();

if (!password_verify($password, $password_hash)) {
    json_resp(['status'=>'error','message'=>'Invalid credentials'], 401);
}

// successful login: set session
$_SESSION['user_id'] = $id;
$_SESSION['user_name'] = $full_name;
json_resp(['status'=>'success','message'=>'Logged in', 'user'=>['id'=>$id,'full_name'=>$full_name]]);
