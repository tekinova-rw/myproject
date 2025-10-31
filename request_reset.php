<?php
// request_reset.php
require 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_resp(['status'=>'error','message'=>'Invalid method'],405);

$email = strtolower(trim($_POST['email'] ?? ''));
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) json_resp(['status'=>'error','message'=>'Invalid email'],400);

// find user
$stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param('s',$email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    // do not reveal whether email exists — respond success to avoid enumeration
    json_resp(['status'=>'success','message'=>'If that email exists, a reset link was sent.']);
}
$stmt->bind_result($user_id);
$stmt->fetch();
$stmt->close();

// generate token (random)
$token = bin2hex(random_bytes(32));
$expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

// store token (you can store plain token or hash it — here we store token directly)
$upd = $mysqli->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
$upd->bind_param('ssi', $token, $expires, $user_id);
$upd->execute();
$upd->close();

// compose reset link - change domain/path as needed
$reset_link = "https://your-domain.com/reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email);

// send email (simple mail). In production use transactional email service.
$subject = "Password reset request";
$message = "Hello,\n\nWe received a request to reset your password. Click the link below to reset it (valid 1 hour):\n\n$reset_link\n\nIf you didn't request this, ignore this email.\n";
$headers = "From: no-reply@your-domain.com\r\n";

// Attempt to send; ignore failure for privacy
@mail($email, $subject, $message, $headers);

json_resp(['status'=>'success','message'=>'If that email exists, a reset link was sent.']);
