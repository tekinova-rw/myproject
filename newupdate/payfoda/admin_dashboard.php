<?php
// admin_dashboard.php
require_once 'config/config.php';
require_once 'includes/auth.php';

$auth = new AdminAuth($db);
requireAdminLogin();

// Show success/error messages
$message = '';
$messageType = '';

if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    $messageType = 'success';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    $messageType = 'error';
    unset($_SESSION['error']);
}

// Handle delete action
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $paymentId = intval($_GET['id']);
    $confirm = isset($_GET['confirm']) ? $_GET['confirm'] : '';
    
    if ($confirm === 'yes') {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Get payment details to check if it was approved and had access granted
            $sql = "SELECT p.*, u.id as user_id, u.has_access, u.access_expires 
                    FROM payments p 
                    LEFT JOIN users u ON p.email = u.email 
                    WHERE p.id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $paymentId]);
            $payment = $stmt->fetch();
            
            if (!$payment) {
                throw new Exception('Payment not found');
            }
            
            // If payment was approved, revoke user access
            if ($payment['status'] == 'approved' && $payment['user_id']) {
                // Revoke access for this user
                $sql = "UPDATE users SET has_access = 0, access_expires = NULL WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute([':id' => $payment['user_id']]);
                
                // Log the deletion
                logAccess($db, $payment['user_id'], null, 'admin_action', 'deleted_approved_payment');
            }
            
            // Delete the payment record
            $sql = "DELETE FROM payments WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $paymentId]);
            
            // Delete payment proof file if exists
            if ($payment['payment_proof'] && file_exists(UPLOAD_DIR . $payment['payment_proof'])) {
                unlink(UPLOAD_DIR . $payment['payment_proof']);
            }
            
            $db->commit();
            $_SESSION['success'] = "Payment #{$paymentId} deleted successfully. User access revoked if applicable.";
            
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $_SESSION['error'] = "Error deleting payment: " . $e->getMessage();
        }
        
        header('Location: admin_dashboard.php');
        exit();
    } else {
        // Show confirmation dialog via JavaScript
        echo "<script>
            if (confirm('⚠️ WARNING: This will delete payment #{$paymentId} and revoke user access if approved.\\n\\nAre you sure you want to continue?')) {
                window.location.href = 'admin_dashboard.php?delete={$paymentId}&confirm=yes';
            } else {
                window.location.href = 'admin_dashboard.php';
            }
        </script>";
        exit();
    }
}

// Get statistics
$stats = [];

// Total payments
$sql = "SELECT COUNT(*) as total, 
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM payments";
$stmt = $db->prepare($sql);
$stmt->execute();
$stats['payments'] = $stmt->fetch();

// Total users
$sql = "SELECT COUNT(*) as total, 
        SUM(CASE WHEN has_access = 1 AND (access_expires IS NULL OR access_expires > NOW()) THEN 1 ELSE 0 END) as active_access
        FROM users";
$stmt = $db->prepare($sql);
$stmt->execute();
$stats['users'] = $stmt->fetch();

// Recent payments
$sql = "SELECT p.*, u.full_name as user_name, u.unique_hash,
        a.username as approved_by_name
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN admins a ON p.approved_by = a.id
        ORDER BY p.submitted_at DESC
        LIMIT 50";
$stmt = $db->prepare($sql);
$stmt->execute();
$recentPayments = $stmt->fetchAll();

// Get admin role
$isSuperAdmin = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] == 'super_admin';
$pendingCount = $stats['payments']['pending'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 24px;
        }
        
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .admin-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-card .sub {
            color: #999;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease-out;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .payments-table {
            background: white;
            border-radius: 10px;
            overflow-x: auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .payments-table h2 {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .badge {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 8px;
        }
        
        .refresh-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .refresh-btn:hover {
            background: #5a67d8;
        }
        
        .auto-refresh-badge {
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            margin-left: 8px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f8f8;
            font-weight: 600;
            color: #666;
            position: sticky;
            top: 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 5px;
            transition: all 0.2s;
        }
        
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 5px;
            transition: all 0.2s;
        }
        
        .btn-delete {
            background: #6c757d;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-delete:hover {
            background: #5a6268;
            opacity: 0.9;
            transform: scale(1.02);
        }
        
        .btn-approve:hover, .btn-reject:hover, .btn-delete:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }
        
        .proof-link {
            color: #667eea;
            text-decoration: none;
        }
        
        .proof-link:hover {
            text-decoration: underline;
        }
        
        .plan-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .plan-week {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .plan-month {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .pending-count {
            font-weight: bold;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            th, td {
                font-size: 12px;
                padding: 8px;
            }
            
            .btn-approve, .btn-reject, .btn-delete {
                padding: 3px 8px;
                font-size: 11px;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .payments-table h2 {
                flex-direction: column;
                align-items: stretch;
            }
        }
        
        @media print {
            .header, .btn-approve, .btn-reject, .btn-delete, .logout-btn, .refresh-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>💰 <?php echo SITE_NAME; ?> - Admin Dashboard</h1>
            <div class="user-info">
                <span class="admin-badge">
                    <?php echo $isSuperAdmin ? '👑 Super Admin' : '👤 Admin'; ?>
                </span>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="admin_logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>💰 Total Payments</h3>
                <div class="number"><?php echo $stats['payments']['total']; ?></div>
                <div class="sub">
                    Pending: <span id="pendingCount"><?php echo $stats['payments']['pending']; ?></span> | 
                    Approved: <?php echo $stats['payments']['approved']; ?> | 
                    Rejected: <?php echo $stats['payments']['rejected']; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h3>👥 Total Users</h3>
                <div class="number"><?php echo $stats['users']['total']; ?></div>
                <div class="sub">Active Access: <?php echo $stats['users']['active_access']; ?></div>
            </div>
        </div>
        
        <div class="payments-table">
            <h2>
                <span>
                    📋 Recent Payment Submissions
                    <?php if ($pendingCount > 0): ?>
                        <span class="badge" id="pendingBadge"><?php echo $pendingCount; ?> pending</span>
                    <?php endif; ?>
                    <span class="auto-refresh-badge" id="autoRefreshStatus">🔄 Auto-refresh ON</span>
                </span>
                <div>
                    <button class="refresh-btn" onclick="location.reload();">🔄 Refresh Now</button>
                </div>
            </h2>
            
            <table id="paymentsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Amount</th>
                        <th>Plan</th>
                        <th>Proof</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="paymentsTableBody">
                    <?php foreach ($recentPayments as $payment): ?>
                    <tr data-payment-id="<?php echo $payment['id']; ?>" data-status="<?php echo $payment['status']; ?>">
                        <td>#<?php echo $payment['id']; ?></td>
                        <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($payment['email']); ?></td>
                        <td><strong><?php echo formatCurrency($payment['amount'], 'FRW'); ?></strong></td>
                        <td>
                            <?php 
                            $duration = getAccessDuration($payment['amount']);
                            if ($duration == 7): ?>
                                <span class="plan-badge plan-week">📅 1 Week</span>
                            <?php else: ?>
                                <span class="plan-badge plan-month">📆 1 Month</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($payment['payment_proof']): ?>
                                <a href="assets/uploads/<?php echo $payment['payment_proof']; ?>" target="_blank" class="proof-link">📎 View</a>
                            <?php else: ?>
                                <span style="color:#999;">No proof</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $payment['status']; ?>" id="status-<?php echo $payment['id']; ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d M Y, H:i', strtotime($payment['submitted_at'])); ?></td>
                        <td>
                            <?php if ($payment['status'] == 'pending'): ?>
                                <button class="btn-approve" onclick="approvePayment(<?php echo $payment['id']; ?>)">✅ Approve</button>
                                <button class="btn-reject" onclick="rejectPayment(<?php echo $payment['id']; ?>)">❌ Reject</button>
                            <?php else: ?>
                                <button class="btn-delete" onclick="deletePayment(<?php echo $payment['id']; ?>, '<?php echo $payment['status']; ?>')">🗑️ Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($recentPayments)): ?>
                    <tr>
                        <td colspan="9" class="empty-state">
                            <div>📭 No payments found</div>
                            <small>When users submit payments, they will appear here.</small>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        let autoRefreshInterval = null;
        let isAutoRefreshRunning = true;
        
        function approvePayment(paymentId) {
            if (confirm('✅ Are you sure you want to approve this payment?\n\nThe user will receive access immediately.')) {
                window.location.href = 'approve_payment.php?id=' + paymentId + '&action=approve';
            }
        }
        
        function rejectPayment(paymentId) {
            let reason = prompt('❌ Please provide a reason for rejection:');
            if (reason !== null && reason.trim() !== '') {
                window.location.href = 'approve_payment.php?id=' + paymentId + '&action=reject&reason=' + encodeURIComponent(reason);
            } else if (reason !== null) {
                alert('Please provide a reason for rejection.');
            }
        }
        
        function deletePayment(paymentId, status) {
            let warningMsg = '⚠️ WARNING: This will delete payment #' + paymentId + '.\n\n';
            if (status === 'approved') {
                warningMsg += 'This payment was APPROVED. Deleting it will REVOKE user access.\n\n';
            }
            warningMsg += 'Are you sure you want to continue?';
            
            if (confirm(warningMsg)) {
                window.location.href = 'admin_dashboard.php?delete=' + paymentId + '&confirm=yes';
            }
        }
        
        // Auto-refresh pending count every 10 seconds
        function refreshPendingCount() {
            fetch('get_pending_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('pendingBadge');
                    const countSpan = document.getElementById('pendingCount');
                    if (badge && data.pending > 0) {
                        badge.textContent = data.pending + ' pending';
                        badge.style.display = 'inline-block';
                        if (countSpan) countSpan.textContent = data.pending;
                    } else if (badge && data.pending === 0) {
                        badge.style.display = 'none';
                        if (countSpan) countSpan.textContent = '0';
                    }
                })
                .catch(error => console.error('Error refreshing count:', error));
        }
        
        // Auto-refresh table data for pending payments
        function refreshTableData() {
            fetch('get_pending_payments.php')
                .then(response => response.json())
                .then(data => {
                    if (data.payments && data.payments.length > 0) {
                        updateTableRows(data.payments);
                    }
                    // Update pending count
                    const badge = document.getElementById('pendingBadge');
                    if (badge && data.pending_count !== undefined) {
                        if (data.pending_count > 0) {
                            badge.textContent = data.pending_count + ' pending';
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error refreshing table:', error));
        }
        
        function updateTableRows(payments) {
            const tbody = document.getElementById('paymentsTableBody');
            if (!tbody) return;
            
            // Update existing rows or add new ones
            payments.forEach(payment => {
                const existingRow = document.querySelector(`tr[data-payment-id="${payment.id}"]`);
                const statusSpan = document.getElementById(`status-${payment.id}`);
                
                if (statusSpan && payment.status !== statusSpan.textContent.trim().toLowerCase()) {
                    // Update status
                    statusSpan.textContent = payment.status.charAt(0).toUpperCase() + payment.status.slice(1);
                    statusSpan.className = `status-badge status-${payment.status}`;
                    
                    // Update row data-status attribute
                    if (existingRow) {
                        existingRow.setAttribute('data-status', payment.status);
                        
                        // Update actions column
                        const actionsCell = existingRow.querySelector('td:last-child');
                        if (actionsCell) {
                            if (payment.status === 'pending') {
                                actionsCell.innerHTML = `
                                    <button class="btn-approve" onclick="approvePayment(${payment.id})">✅ Approve</button>
                                    <button class="btn-reject" onclick="rejectPayment(${payment.id})">❌ Reject</button>
                                `;
                            } else {
                                actionsCell.innerHTML = `<button class="btn-delete" onclick="deletePayment(${payment.id}, '${payment.status}')">🗑️ Delete</button>`;
                            }
                        }
                    }
                }
            });
        }
        
        // Start auto-refresh
        function startAutoRefresh() {
            if (autoRefreshInterval) clearInterval(autoRefreshInterval);
            autoRefreshInterval = setInterval(() => {
                if (isAutoRefreshRunning) {
                    refreshPendingCount();
                    if (<?php echo $pendingCount; ?> > 0) {
                        refreshTableData();
                    }
                }
            }, 10000); // Refresh every 10 seconds
        }
        
        // Stop auto-refresh
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }
        
        // Toggle auto-refresh
        function toggleAutoRefresh() {
            isAutoRefreshRunning = !isAutoRefreshRunning;
            const statusBadge = document.getElementById('autoRefreshStatus');
            if (statusBadge) {
                if (isAutoRefreshRunning) {
                    statusBadge.textContent = '🔄 Auto-refresh ON';
                    statusBadge.style.background = '#28a745';
                    startAutoRefresh();
                } else {
                    statusBadge.textContent = '⏸️ Auto-refresh OFF';
                    statusBadge.style.background = '#6c757d';
                    stopAutoRefresh();
                }
            }
        }
        
        // Add toggle button to header
        document.addEventListener('DOMContentLoaded', function() {
            startAutoRefresh();
            
            // Add toggle button to the h2 section
            const h2Section = document.querySelector('.payments-table h2');
            if (h2Section) {
                const toggleBtn = document.createElement('button');
                toggleBtn.textContent = isAutoRefreshRunning ? '⏸️ Pause Auto-refresh' : '▶️ Resume Auto-refresh';
                toggleBtn.className = 'refresh-btn';
                toggleBtn.style.marginLeft = '10px';
                toggleBtn.style.background = isAutoRefreshRunning ? '#dc3545' : '#28a745';
                toggleBtn.onclick = function() {
                    toggleAutoRefresh();
                    this.textContent = isAutoRefreshRunning ? '⏸️ Pause Auto-refresh' : '▶️ Resume Auto-refresh';
                    this.style.background = isAutoRefreshRunning ? '#dc3545' : '#28a745';
                };
                h2Section.querySelector('div').appendChild(toggleBtn);
            }
        });
        
        console.log('Admin dashboard loaded successfully. Role: <?php echo $_SESSION['admin_role'] ?? 'admin'; ?>');
    </script>
</body>
</html>