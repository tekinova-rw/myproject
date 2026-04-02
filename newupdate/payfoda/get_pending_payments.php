<?php
// get_pending_payments.php
// AJAX endpoint to get pending payments for auto-refresh

require_once 'config/config.php';
require_once 'includes/auth.php';

$auth = new AdminAuth($db);

// Check if admin is logged in
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get pending payments
$sql = "SELECT id, full_name, email, amount, status, submitted_at, payment_proof 
        FROM payments 
        WHERE status = 'pending' 
        ORDER BY submitted_at DESC 
        LIMIT 20";
$stmt = $db->prepare($sql);
$stmt->execute();
$pendingPayments = $stmt->fetchAll();

// Get total pending count
$sql = "SELECT COUNT(*) as count FROM payments WHERE status = 'pending'";
$stmt = $db->prepare($sql);
$stmt->execute();
$pendingCount = $stmt->fetch()['count'];

echo json_encode([
    'payments' => $pendingPayments,
    'pending_count' => $pendingCount
]);
?>