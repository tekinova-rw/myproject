<?php
// debug_approve.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/User.php';

$auth = new AdminAuth($db);

// Check if admin is logged in
if (!$auth->isLoggedIn()) {
    die('Please login first: <a href="admin_login.php">Login</a>');
}

$paymentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

echo "<h1>🔧 Payment Approval Debug Tool</h1>";
echo "<style>
    body { font-family: 'Segoe UI', Arial; padding: 20px; background: #f5f5f5; }
    .success { color: green; background: #d4edda; padding: 10px; margin: 5px 0; border-radius: 5px; }
    .error { color: red; background: #f8d7da; padding: 10px; margin: 5px 0; border-radius: 5px; }
    .info { color: blue; background: #d1ecf1; padding: 10px; margin: 5px 0; border-radius: 5px; }
    .box { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    button { padding: 8px 16px; margin: 5px; cursor: pointer; }
</style>";

if ($paymentId) {
    echo "<div class='box'>";
    echo "<h2>Testing Payment ID: $paymentId</h2>";
    
    // Get payment details
    $sql = "SELECT * FROM payments WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $paymentId]);
    $payment = $stmt->fetch();
    
    if ($payment) {
        echo "<div class='info'>";
        echo "<h3>📋 Payment Details:</h3>";
        echo "<pre>";
        print_r($payment);
        echo "</pre>";
        echo "</div>";
        
        echo "<div class='info'>";
        echo "<h3>👤 Admin Info:</h3>";
        echo "Admin ID: " . $_SESSION['admin_id'] . "<br>";
        echo "Admin Username: " . $_SESSION['admin_username'] . "<br>";
        echo "Admin Role: " . $_SESSION['admin_role'] . "<br>";
        echo "</div>";
        
        echo "<h3>⚙️ Attempting Approval...</h3>";
        
        $user = new User($db);
        
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Calculate access duration
            $durationDays = getAccessDuration($payment['amount']);
            $durationText = ($durationDays == 7) ? '1 week' : '1 month';
            echo "<div class='info'>📅 Duration: $durationText ($durationDays days)</div>";
            
            // Update payment status
            $sql = "UPDATE payments SET status = 'approved', approved_by = :admin_id, approved_at = NOW() 
                    WHERE id = :id";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                ':admin_id' => $_SESSION['admin_id'],
                ':id' => $paymentId
            ]);
            
            if ($result) {
                echo "<div class='success'>✅ Payment status updated to approved</div>";
            } else {
                echo "<div class='error'>❌ Failed to update payment status</div>";
                echo "<pre>"; print_r($stmt->errorInfo()); echo "</pre>";
            }
            
            // Get or create user
            $userData = $user->createOrGetUser($payment['full_name'], $payment['email'], $payment['phone']);
            
            if ($userData && $userData['id']) {
                echo "<div class='success'>✅ User found/created: ID {$userData['id']}</div>";
                echo "User Hash: {$userData['unique_hash']}<br>";
                
                // Grant access
                $grantResult = $user->grantAccess($userData['id'], $durationDays);
                
                if ($grantResult) {
                    echo "<div class='success'>✅ Access granted for $durationText</div>";
                    echo "Access URL: " . SITE_URL . "/PROVI/index.php?hash=" . $userData['unique_hash'] . "<br>";
                } else {
                    echo "<div class='error'>❌ Failed to grant access</div>";
                }
            } else {
                echo "<div class='error'>❌ Failed to create/get user</div>";
            }
            
            // Commit transaction
            $db->commit();
            echo "<div class='success'>✅ Transaction committed successfully</div>";
            
            echo "<h3>📊 Final Payment Status:</h3>";
            $sql = "SELECT * FROM payments WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $paymentId]);
            $updatedPayment = $stmt->fetch();
            echo "<pre>";
            print_r($updatedPayment);
            echo "</pre>";
            
            echo "<h3>👥 User Access Status:</h3>";
            $sql = "SELECT * FROM users WHERE email = :email";
            $stmt = $db->prepare($sql);
            $stmt->execute([':email' => $payment['email']]);
            $userCheck = $stmt->fetch();
            echo "<pre>";
            print_r($userCheck);
            echo "</pre>";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
            error_log("Payment approval error: " . $e->getMessage());
        }
        
        echo "</div>";
        echo '<br><a href="admin_dashboard.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a>';
        
    } else {
        echo "<div class='error'>❌ Payment not found!</div>";
        echo '<a href="admin_dashboard.php">← Back to Dashboard</a>';
    }
} else {
    // Show all pending payments
    echo "<div class='box'>";
    echo "<h2>📋 Pending Payments:</h2>";
    $sql = "SELECT id, full_name, email, amount, submitted_at FROM payments WHERE status = 'pending' ORDER BY id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $pendingPayments = $stmt->fetchAll();
    
    if ($pendingPayments) {
        echo "<table border='1' cellpadding='10' cellspacing='0' style='width:100%; border-collapse: collapse;'>";
        echo "<tr style='background: #8a0c16; color: white;'>";
        echo "<th>ID</th><th>Name</th><th>Email</th><th>Amount</th><th>Submitted</th><th>Action</th>";
        echo "</tr>";
        foreach ($pendingPayments as $payment) {
            echo "<tr>";
            echo "<td>{$payment['id']}</td>";
            echo "<td>" . htmlspecialchars($payment['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($payment['email']) . "</td>";
            echo "<td>" . formatCurrency($payment['amount'], 'FRW') . "</td>";
            echo "<td>" . date('d M Y, H:i', strtotime($payment['submitted_at'])) . "</td>";
            echo "<td><a href='?id={$payment['id']}' style='background: #a7282e; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Test Approve</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>No pending payments found.</div>";
    }
    echo "</div>";
    echo '<br><a href="admin_dashboard.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a>';
}
?>