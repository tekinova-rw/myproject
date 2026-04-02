<?php
// test_access.php - Debugging tool
session_start();

require_once 'config/database.php';

$debug_info = [];

// Check database connection
$debug_info['database_connection'] = $mysqli->ping() ? 'Connected' : 'Disconnected';

// Get table counts
$tables = ['payments', 'user_access', 'admins'];
foreach($tables as $table) {
    $result = $mysqli->query("SELECT COUNT(*) as count FROM $table");
    if($result) {
        $debug_info["{$table}_count"] = $result->fetch_assoc()['count'];
    } else {
        $debug_info["{$table}_error"] = $mysqli->error;
    }
}

// Get pending approvals count
$pending = $mysqli->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
if($pending) {
    $debug_info['pending_approvals'] = $pending->fetch_assoc()['count'];
}

// Get active users count
$active = $mysqli->query("SELECT COUNT(*) as count FROM user_access WHERE is_active = 1 AND access_expires > NOW()");
if($active) {
    $debug_info['active_users'] = $active->fetch_assoc()['count'];
}

// Session info
$debug_info['session'] = $_SESSION ?: 'No active session';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Debug - Test Access</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .section h3 {
            margin-top: 0;
            color: #333;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        .success {
            color: #4CAF50;
            font-weight: bold;
        }
        .error {
            color: #f44336;
            font-weight: bold;
        }
        .info {
            color: #2196F3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 System Debug Information</h1>
        
        <div class="section">
            <h3>📊 Database Status</h3>
            <pre><?php print_r($debug_info); ?></pre>
        </div>
        
        <div class="section">
            <h3>📝 Recent Payments (Last 5)</h3>
            <?php
            $recent = $mysqli->query("SELECT id, transaction_id, phone, amount, status, created_at FROM payments ORDER BY id DESC LIMIT 5");
            if($recent && $recent->num_rows > 0) {
                echo '<table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse;">';
                echo '<tr><th>ID</th><th>Transaction</th><th>Phone</th><th>Amount</th><th>Status</th><th>Created</th></tr>';
                while($row = $recent->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . $row['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($row['transaction_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['phone']) . '</td>';
                    echo '<td>' . $row['amount'] . '</td>';
                    echo '<td class="' . ($row['status'] == 'approved' ? 'success' : ($row['status'] == 'pending' ? 'info' : 'error')) . '">' . $row['status'] . '</td>';
                    echo '<td>' . $row['created_at'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>No payments found</p>';
            }
            ?>
        </div>
        
        <div class="section">
            <h3>👥 Active User Access</h3>
            <?php
            $active_access = $mysqli->query("SELECT user_id, transaction_id, phone, access_expires FROM user_access WHERE is_active = 1 AND access_expires > NOW() LIMIT 5");
            if($active_access && $active_access->num_rows > 0) {
                echo '<table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse;">';
                echo '<tr><th>User ID</th><th>Transaction</th><th>Phone</th><th>Expires</th></tr>';
                while($row = $active_access->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['user_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['transaction_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['phone']) . '</td>';
                    echo '<td>' . $row['access_expires'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>No active users</p>';
            }
            ?>
        </div>
        
        <div class="section">
            <h3>🔗 Quick Links</h3>
            <ul>
                <li><a href="admin_login.php">Admin Login</a></li>
                <li><a href="pay.html">Make Payment</a></li>
                <li><a href="check_access.php">Check Access</a></li>
                <li><a href="PROVI/index.html">Exam Portal</a></li>
            </ul>
        </div>
    </div>
</body>
</html>