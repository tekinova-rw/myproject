<?php
// approve_payment.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/User.php';

$auth = new AdminAuth($db);
requireAdminLogin();

$paymentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$reason = isset($_GET['reason']) ? sanitize($_GET['reason']) : '';

// Get the current admin ID from session
$adminId = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;

// Verify admin exists in database
$sql = "SELECT id, username FROM admins WHERE id = :id AND is_active = 1";
$stmt = $db->prepare($sql);
$stmt->execute([':id' => $adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    $_SESSION['error'] = 'Admin account not found. Please login again.';
    header('Location: admin_login.php');
    exit();
}

if (!$paymentId || !in_array($action, ['approve', 'reject'])) {
    $_SESSION['error'] = 'Invalid request. Payment ID or action missing.';
    header('Location: admin_dashboard.php');
    exit();
}

$user = new User($db);

try {
    // Start transaction
    $db->beginTransaction();
    
    // Get payment details
    $sql = "SELECT * FROM payments WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $paymentId]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        throw new Exception('Payment not found with ID: ' . $paymentId);
    }
    
    if ($payment['status'] != 'pending') {
        throw new Exception('Payment already processed. Current status: ' . $payment['status']);
    }
    
    if ($action == 'approve') {
        // Calculate access duration based on payment amount
        $durationDays = getAccessDuration($payment['amount']);
        $durationText = $durationDays == 7 ? '1 week' : '1 month';
        
        // Update payment status with valid admin ID
        $sql = "UPDATE payments SET status = 'approved', approved_by = :admin_id, approved_at = NOW() 
                WHERE id = :id";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':admin_id' => $admin['id'],
            ':id' => $paymentId
        ]);
        
        if (!$result) {
            throw new Exception('Failed to update payment status');
        }
        
        // Get or create user
        $userData = $user->createOrGetUser($payment['full_name'], $payment['email'], $payment['phone']);
        
        if (!$userData || !isset($userData['id'])) {
            throw new Exception('Failed to create/get user');
        }
        
        // Check if user already has active access
        $sql = "SELECT has_access, access_expires FROM users WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $userData['id']]);
        $currentUser = $stmt->fetch();
        
        $accessMessage = '';
        
        if ($currentUser && $currentUser['has_access'] == 1 && $currentUser['access_expires'] && strtotime($currentUser['access_expires']) > time()) {
            // User has active access - EXTEND instead of replace
            $currentExpires = $currentUser['access_expires'];
            $newExpires = date('Y-m-d H:i:s', strtotime($currentExpires . " +{$durationDays} days"));
            
            $sql = "UPDATE users SET access_expires = :new_expires WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':new_expires' => $newExpires,
                ':id' => $userData['id']
            ]);
            
            $accessMessage = "✅ Access EXTENDED by {$durationText}. Old expiry: " . date('d M Y', strtotime($currentExpires)) . ". New expiry: " . date('d M Y', strtotime($newExpires));
            
            // Add note to payment about extension
            $sql = "UPDATE payments SET admin_notes = CONCAT(IFNULL(admin_notes, ''), ' | Access extended from ', :old_expires, ' to ', :new_expires) WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':old_expires' => $currentExpires,
                ':new_expires' => $newExpires,
                ':id' => $paymentId
            ]);
            
        } else {
            // New user or expired access - grant new access
            $grantResult = $user->grantAccess($userData['id'], $durationDays);
            
            if (!$grantResult) {
                throw new Exception('Failed to grant access to user');
            }
            
            // Get the new expiry date
            $sql = "SELECT access_expires FROM users WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $userData['id']]);
            $newUser = $stmt->fetch();
            $newExpiry = $newUser['access_expires'];
            
            $accessMessage = "✅ New access granted for {$durationText}. Expires on: " . date('d M Y', strtotime($newExpiry));
        }
        
        // Send approval email with appropriate message
        sendPaymentApprovalEmail($payment['email'], $payment['full_name'], $userData['unique_hash'], $durationDays, $currentUser && $currentUser['has_access'] == 1 && strtotime($currentUser['access_expires']) > time());
        
        // Commit transaction
        $db->commit();
        
        $_SESSION['success'] = "✅ Payment #{$paymentId} approved! {$accessMessage}";
        
    } elseif ($action == 'reject') {
        // Update payment status
        $sql = "UPDATE payments SET status = 'rejected', admin_notes = :notes, approved_by = :admin_id, approved_at = NOW() 
                WHERE id = :id";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':notes' => $reason ?: 'No reason provided',
            ':admin_id' => $admin['id'],
            ':id' => $paymentId
        ]);
        
        if (!$result) {
            throw new Exception('Failed to update payment status');
        }
        
        // Send rejection email
        sendPaymentRejectionEmail($payment['email'], $payment['full_name'], $reason);
        
        // Commit transaction
        $db->commit();
        
        $_SESSION['success'] = "❌ Payment #{$paymentId} rejected successfully.";
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['error'] = "Error: " . $e->getMessage();
    error_log("Payment approval error: " . $e->getMessage());
}

header('Location: admin_dashboard.php');
exit();
?>