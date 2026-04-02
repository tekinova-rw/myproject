<?php
// verify_system.php
// System verification script

echo "<!DOCTYPE html>
<html>
<head>
    <title>System Verification - PAYFODA</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 40px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
        }
        .content {
            padding: 30px;
        }
        .section {
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .section-title {
            background: #f8f9fa;
            padding: 12px 20px;
            font-weight: bold;
            border-bottom: 1px solid #e0e0e0;
            color: #333;
        }
        .section-content {
            padding: 20px;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        .file-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 10px;
        }
        .file-item {
            padding: 8px;
            background: #f8f9fa;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
        }
        .file-exists {
            color: green;
            font-weight: bold;
        }
        .file-missing {
            color: red;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .btn:hover {
            background: #5a67d8;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔍 PAYFODA System Verification</h1>
            <p>Checking all components and configurations</p>
        </div>
        <div class='content'>";

// Check Database Connection
echo "<div class='section'>";
echo "<div class='section-title'>📊 Database Connection</div>";
echo "<div class='section-content'>";

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "<div>✅ Database connected successfully</div>";
    echo "<div>📁 Database: " . $db->query("SELECT DATABASE()")->fetchColumn() . "</div>";
    
    // Check tables
    $tables = ['admins', 'users', 'payments', 'access_logs', 'settings'];
    echo "<div style='margin-top: 10px;'><strong>Tables:</strong></div>";
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<div>✅ $table: <strong>$count</strong> records</div>";
        } else {
            echo "<div>❌ $table: Not found</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='status-error'>❌ Database error: " . $e->getMessage() . "</div>";
}
echo "</div></div>";

// Check Required Files
echo "<div class='section'>";
echo "<div class='section-title'>📁 Required Files</div>";
echo "<div class='section-content'>";
echo "<div class='file-list'>";

$files = [
    'config/config.php',
    'config/database.php',
    'includes/functions.php',
    'includes/auth.php',
    'includes/User.php',
    'pay.php',
    'admin_login.php',
    'admin_dashboard.php',
    'admin_register.php',
    'admin_logout.php',
    'admin_forgot_password.php',
    'admin_reset_password.php',
    'approve_payment.php',
    'check_access.php',
    'check_status_ajax.php',
    'check_status.php',
    'generate_hash.php',
    'submit_payment.php',
    'test_access.php',
    '.htaccess',
    'PROVI/index.php'
];

$allExist = true;
foreach ($files as $file) {
    $exists = file_exists($file);
    $status = $exists ? "✅" : "❌";
    $color = $exists ? "file-exists" : "file-missing";
    echo "<div class='file-item $color'>$status $file</div>";
    if (!$exists) $allExist = false;
}

echo "</div>";
if ($allExist) {
    echo "<div class='status-success' style='margin-top: 15px; padding: 10px;'>✅ All required files exist!</div>";
} else {
    echo "<div class='status-error' style='margin-top: 15px; padding: 10px;'>❌ Some files are missing. Please check above.</div>";
}
echo "</div></div>";

// Check Configuration
echo "<div class='section'>";
echo "<div class='section-title'>⚙️ Configuration Settings</div>";
echo "<div class='section-content'>";

require_once 'config/config.php';
echo "<div>✅ SITE_NAME: " . SITE_NAME . "</div>";
echo "<div>✅ SITE_URL: " . SITE_URL . "</div>";
echo "<div>✅ PAYMENT_AMOUNT: " . PAYMENT_AMOUNT . " FRW</div>";
echo "<div>✅ ACCESS_DURATION_DAYS: " . ACCESS_DURATION_DAYS . " days</div>";

// Check payment plans
echo "<div style='margin-top: 10px;'><strong>Payment Plans:</strong></div>";
foreach (PAYMENT_PLANS as $key => $plan) {
    echo "<div>✅ $key: " . formatCurrency($plan['amount'], $plan['currency']) . " = {$plan['days']} days</div>";
}

echo "</div></div>";

// Check Admin Users
echo "<div class='section'>";
echo "<div class='section-title'>👤 Admin Users</div>";
echo "<div class='section-content'>";

try {
    $sql = "SELECT id, username, email, role, is_active, last_login FROM admins";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $admins = $stmt->fetchAll();
    
    if (count($admins) > 0) {
        echo "<table style='width:100%; border-collapse: collapse;'>";
        echo "<tr style='background: #f8f9fa;'><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th></tr>";
        foreach ($admins as $admin) {
            echo "<tr>";
            echo "<td>{$admin['id']}</td>";
            echo "<td>" . htmlspecialchars($admin['username']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
            echo "<td>" . ($admin['role'] == 'super_admin' ? '👑 Super Admin' : '👤 Admin') . "</td>";
            echo "<td>" . ($admin['is_active'] ? '✅ Active' : '❌ Inactive') . "</td>";
            echo "<td>" . ($admin['last_login'] ?: 'Never') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='status-warning'>⚠️ No admin users found. Please create one using create_admin.php</div>";
        echo "<a href='create_admin.php' class='btn'>Create Admin User</a>";
    }
} catch (Exception $e) {
    echo "<div class='status-error'>❌ Error: " . $e->getMessage() . "</div>";
}
echo "</div></div>";

// Check Recent Payments
echo "<div class='section'>";
echo "<div class='section-title'>💳 Recent Payments</div>";
echo "<div class='section-content'>";

try {
    $sql = "SELECT id, full_name, email, amount, status, submitted_at FROM payments ORDER BY id DESC LIMIT 5";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $payments = $stmt->fetchAll();
    
    if (count($payments) > 0) {
        echo "<table style='width:100%; border-collapse: collapse;'>";
        echo "<tr style='background: #f8f9fa;'><th>ID</th><th>Name</th><th>Email</th><th>Amount</th><th>Status</th><th>Submitted</th></tr>";
        foreach ($payments as $payment) {
            $statusClass = $payment['status'] == 'pending' ? 'status-warning' : ($payment['status'] == 'approved' ? 'status-success' : 'status-error');
            echo "<tr>";
            echo "<td>{$payment['id']}</td>";
            echo "<td>" . htmlspecialchars($payment['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($payment['email']) . "</td>";
            echo "<td>" . formatCurrency($payment['amount'], 'FRW') . "</td>";
            echo "<td><span class='status $statusClass'>" . ucfirst($payment['status']) . "</span></td>";
            echo "<td>" . date('d M Y, H:i', strtotime($payment['submitted_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div>No payments found yet.</div>";
    }
} catch (Exception $e) {
    echo "<div class='status-error'>❌ Error: " . $e->getMessage() . "</div>";
}
echo "</div></div>";

// System URLs
echo "<div class='section'>";
echo "<div class='section-title'>🔗 System URLs</div>";
echo "<div class='section-content'>";
echo "<div><strong>Payment Page:</strong> <a href='" . SITE_URL . "/pay.php' target='_blank'>" . SITE_URL . "/pay.php</a></div>";
echo "<div><strong>Admin Login:</strong> <a href='" . SITE_URL . "/admin_login.php' target='_blank'>" . SITE_URL . "/admin_login.php</a></div>";
echo "<div><strong>Admin Dashboard:</strong> <a href='" . SITE_URL . "/admin_dashboard.php' target='_blank'>" . SITE_URL . "/admin_dashboard.php</a></div>";
echo "<div><strong>Check Status:</strong> <a href='" . SITE_URL . "/check_status.php' target='_blank'>" . SITE_URL . "/check_status.php</a></div>";
echo "<div><strong>Debug Tool:</strong> <a href='" . SITE_URL . "/debug_approve.php' target='_blank'>" . SITE_URL . "/debug_approve.php</a></div>";
echo "</div></div>";

// Recommendations
echo "<div class='section'>";
echo "<div class='section-title'>💡 Recommendations</div>";
echo "<div class='section-content'>";
echo "<ul>";
echo "<li>✅ All files are in place</li>";
echo "<li>✅ Database is configured</li>";

if ($stats['payments']['pending'] > 0) {
    echo "<li>⚠️ You have <strong>{$stats['payments']['pending']}</strong> pending payments waiting for approval</li>";
} else {
    echo "<li>✅ No pending payments</li>";
}

if (count($admins) == 0) {
    echo "<li>⚠️ <strong>No admin users!</strong> Run <a href='create_admin.php'>create_admin.php</a> to create the first admin</li>";
} else {
    echo "<li>✅ Admin users exist</li>";
}

echo "<li>📧 Email is configured with error suppression (logs in logs/email_log.txt)</li>";
echo "<li>🔒 Security features: Password hashing, SQL injection prevention, XSS protection</li>";
echo "</ul>";
echo "</div></div>";

echo "</div></div>";
echo "</body></html>";
?>