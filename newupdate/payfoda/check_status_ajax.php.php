<?php
// check_status_ajax.php
require_once 'config/config.php';

$email = isset($_GET['email']) ? sanitize($_GET['email']) : '';

if (empty($email)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Email required'
    ]);
    exit();
}

// Check latest payment status
$sql = "SELECT status, submitted_at, approved_at, admin_notes 
        FROM payments 
        WHERE email = :email 
        ORDER BY submitted_at DESC 
        LIMIT 1";
$stmt = $db->prepare($sql);
$stmt->execute([':email' => $email]);
$payment = $stmt->fetch();

// Check user access status
$sql = "SELECT has_access, access_expires, unique_hash 
        FROM users 
        WHERE email = :email";
$stmt = $db->prepare($sql);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

$hasActiveAccess = false;
$accessUrl = null;

if ($user) {
    $hasActiveAccess = ($user['has_access'] == 1 && 
                       (!$user['access_expires'] || strtotime($user['access_expires']) > time()));
    
    if ($hasActiveAccess) {
        $accessUrl = SITE_URL . '/PROVI/index.php?hash=' . $user['unique_hash'];
    }
}

// Build response
$response = [
    'success'         => true,
    'payment_status'  => $payment ? $payment['status'] : 'none',
    'has_access'      => $hasActiveAccess,
    'access_url'      => $accessUrl,
    'submitted_at'    => $payment['submitted_at'] ?? null,
    'approved_at'     => $payment['approved_at'] ?? null,
    'admin_notes'     => $payment['admin_notes'] ?? null,
    'access_expires'  => $user ? $user['access_expires'] : null
];

// Add extra info for better debugging
if ($hasActiveAccess) {
    $response['message'] = 'Access granted - Redirecting...';
} elseif ($payment && $payment['status'] === 'approved') {
    $response['message'] = 'Payment approved - Activating access...';
} else {
    $response['message'] = 'Waiting for approval...';
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo json_encode($response);
?>