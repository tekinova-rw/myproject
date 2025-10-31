<?php
// config.php
// Set appropriate values
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'auth_system';
$charset = 'utf8mb4';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'DB connection failed']);
    exit;
}
$mysqli->set_charset($charset);

// Helper: send JSON and exit
function json_resp($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
