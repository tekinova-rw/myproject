<?php
// newupdate/payfoda/check_status.php
// Check payment and access status by email

require_once __DIR__ . '/config/config.php';

$email = isset($_GET['email']) ? sanitize($_GET['email']) : '';
$status = null;
$payment = null;
$user = null;
$error = '';
$examUrl = '';

if ($email) {
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        // Check payment status
        $sql = "SELECT * FROM payments 
                WHERE email = :email 
                ORDER BY submitted_at DESC 
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $payment = $stmt->fetch();
        
        // Check user access status
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        
        // If user has access, set the exam URL
        if ($user && $user['has_access'] == 1 && (!$user['access_expires'] || strtotime($user['access_expires']) > time())) {
            $examUrl = SITE_URL . "/PROVI/index.php?hash=" . $user['unique_hash'];
            
            // If user has active access, redirect directly to exam portal
            header('Location: ' . $examUrl);
            exit();
        }
        
        if (!$payment && !$user) {
            $error = 'No payment or access record found for this email';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Check Status - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .content {
            padding: 35px;
        }
        
        .search-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }
        
        .form-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .form-group input {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group button {
            padding: 14px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .form-group button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .status-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 16px;
            padding: 25px;
            margin-top: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .status-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            flex-wrap: wrap;
        }
        
        .status-icon {
            font-size: 40px;
        }
        
        .status-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            word-break: break-all;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 0.85rem;
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
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            word-break: break-all;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin: 15px 0;
            font-size: 0.95rem;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .button-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-block;
            padding: 14px 32px;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            border: none;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-payment {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        
        .btn-payment:hover {
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn-exam {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .btn-exam:hover {
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-exam-disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-exam-disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .btn-back {
            background: #6c757d;
        }
        
        .btn-back:hover {
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
        }
        
        .expiry-info {
            background: #e7f3ff;
            padding: 12px;
            border-radius: 10px;
            margin-top: 15px;
            text-align: center;
            font-size: 0.9rem;
            color: #004085;
        }
        
        .auto-refresh-note {
            margin-top: 25px;
            text-align: center;
            font-size: 0.8rem;
            color: #999;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 0 10px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 1.6rem;
            }
            
            .content {
                padding: 25px;
            }
            
            .search-form {
                padding: 20px;
            }
            
            .form-group {
                flex-direction: column;
            }
            
            .form-group button {
                width: 100%;
            }
            
            .status-header {
                flex-direction: column;
                text-align: center;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .button-group {
                flex-direction: column;
                gap: 12px;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
            
            .status-title {
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 1.4rem;
            }
            
            .header p {
                font-size: 0.85rem;
            }
            
            .content {
                padding: 20px;
            }
            
            .status-card {
                padding: 20px;
            }
            
            .info-value {
                font-size: 0.9rem;
            }
            
            .btn {
                padding: 12px 24px;
                font-size: 0.9rem;
            }
            
            .alert {
                font-size: 0.85rem;
                padding: 12px 15px;
            }
        }
        
        @media (max-width: 360px) {
            .header {
                padding: 20px 15px;
            }
            
            .header h1 {
                font-size: 1.2rem;
            }
            
            .content {
                padding: 15px;
            }
            
            .status-icon {
                font-size: 32px;
            }
            
            .status-title {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Check Payment Status</h1>
            <p>Enter your email to check payment and access status</p>
        </div>
        
        <div class="content">
            <div class="search-form">
                <form method="GET" action="">
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Enter your email address" 
                               value="<?php echo htmlspecialchars($email); ?>" required>
                        <button type="submit">Check Status</button>
                    </div>
                </form>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>❌ Error!</strong> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($email && !$error && !$examUrl): ?>
                <div class="status-card">
                    <div class="status-header">
                        <div class="status-icon">📧</div>
                        <div class="status-title">
                            Status for: <?php echo htmlspecialchars($email); ?>
                        </div>
                    </div>
                    
                    <div class="info-grid">
                        <?php if ($payment): ?>
                            <div class="info-card">
                                <div class="info-label">Payment Status</div>
                                <div class="info-value">
                                    <span class="status-badge status-<?php echo $payment['status']; ?>">
                                        <?php echo strtoupper($payment['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-label">Amount Paid</div>
                                <div class="info-value"><?php echo formatCurrency($payment['amount'], 'FRW'); ?></div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-label">Transaction ID</div>
                                <div class="info-value"><?php echo $payment['transaction_id'] ?: 'Not provided'; ?></div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-label">Submitted On</div>
                                <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($payment['submitted_at'])); ?></div>
                            </div>
                            
                            <?php if ($payment['admin_notes']): ?>
                                <div class="info-card">
                                    <div class="info-label">Admin Notes</div>
                                    <div class="info-value"><?php echo htmlspecialchars($payment['admin_notes']); ?></div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info" style="grid-column: 1/-1;">
                                No payment record found for this email.
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($user): ?>
                            <div class="info-card">
                                <div class="info-label">Account Status</div>
                                <div class="info-value">
                                    <?php 
                                    $hasValidAccess = $user['has_access'] && (!$user['access_expires'] || strtotime($user['access_expires']) > time());
                                    if ($hasValidAccess): ?>
                                        <span class="status-badge status-approved">✅ ACCESS GRANTED</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">⏳ PENDING / EXPIRED</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($user['access_expires']): ?>
                                <div class="info-card">
                                    <div class="info-label">Access Expires</div>
                                    <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($user['access_expires'])); ?></div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="info-card" style="grid-column: 1/-1;">
                                <div class="info-label">Account</div>
                                <div class="info-value">No user account found. Make a payment to create your account.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($payment && $payment['status'] == 'pending'): ?>
                        <div class="alert alert-warning" style="text-align: center;">
                            ⏳ Your payment is pending review. You will receive an email once approved.
                        </div>
                    <?php elseif ($payment && $payment['status'] == 'approved' && $user && !$hasValidAccess): ?>
                        <div class="alert alert-info" style="text-align: center;">
                            ✅ Your payment was approved! Click the button below to access your exam.
                        </div>
                    <?php elseif (!$payment): ?>
                        <div class="alert alert-info" style="text-align: center;">
                            💰 No payment found. Click the button below to make a payment.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="button-group">
                <a href="<?php echo SITE_URL; ?>/pay.php" class="btn btn-payment">💵 Make a Payment</a>
                <a href="<?php echo SITE_URL; ?>/PROVI/index.php" class="btn btn-exam">🎓 Go to Exam Portal</a>
                <a href="<?php echo SITE_URL; ?>/admin_login.php" class="btn btn-back">👨‍💼 Admin Login</a>
            </div>
            
            <?php if ($email && $payment && $payment['status'] == 'pending'): ?>
            <div class="auto-refresh-note">
                <i class="fas fa-sync-alt"></i> ⏰ Waiting for approval? This page will auto-refresh every 30 seconds.
                <script>
                    // Auto-refresh if payment is pending
                    setTimeout(function() {
                        location.reload();
                    }, 30000);
                </script>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>