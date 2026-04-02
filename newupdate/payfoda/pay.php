<?php
require_once 'config/config.php';

// Add missing function if not exists in config.php
if (!function_exists('getPlanDetails')) {
    function getPlanDetails($amount) {
        if ($amount == 1500) {
            return ['name' => 'Premium Plan', 'days' => 30, 'amount' => 1500];
        } else {
            return ['name' => 'Basic Plan', 'days' => 7, 'amount' => 500];
        }
    }
}

$error = '';
$success = '';
$submittedEmail = '';
$redirectUrl = '';
$hasActiveAccess = false;
$activeUserData = null;

// Check if user already has active access via email parameter
if (isset($_GET['email']) && !empty($_GET['email'])) {
    $checkEmail = sanitize($_GET['email']);
    $sql = "SELECT u.has_access, u.access_expires, u.unique_hash, u.full_name 
            FROM users u 
            WHERE u.email = :email 
            AND u.has_access = 1 
            AND (u.access_expires IS NULL OR u.access_expires > NOW())";
    $stmt = $db->prepare($sql);
    $stmt->execute([':email' => $checkEmail]);
    $activeUserData = $stmt->fetch();
    
    if ($activeUserData) {
        $hasActiveAccess = true;
    }
}

// Check via AJAX request for real-time validation
if (isset($_GET['check_access']) && isset($_GET['email'])) {
    header('Content-Type: application/json');
    $checkEmail = sanitize($_GET['email']);
    $sql = "SELECT u.has_access, u.access_expires, u.unique_hash, u.full_name 
            FROM users u 
            WHERE u.email = :email 
            AND u.has_access = 1 
            AND (u.access_expires IS NULL OR u.access_expires > NOW())";
    $stmt = $db->prepare($sql);
    $stmt->execute([':email' => $checkEmail]);
    $userData = $stmt->fetch();
    
    if ($userData) {
        $remainingDays = ceil((strtotime($userData['access_expires']) - time()) / 86400);
        echo json_encode([
            'has_access' => true,
            'access_url' => SITE_URL . '/PROVI/index.php?hash=' . $userData['unique_hash'],
            'expires_on' => date('d M Y', strtotime($userData['access_expires'])),
            'remaining_days' => $remainingDays,
            'full_name' => $userData['full_name']
        ]);
    } else {
        echo json_encode(['has_access' => false]);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $transactionId = sanitize($_POST['transaction_id'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $submittedEmail = $email;
    $action = sanitize($_POST['action'] ?? 'new_payment');
    
    $errors = [];
    
    if (empty($fullName)) $errors[] = 'Full name is required';
    if (empty($email)) $errors[] = 'Email address is required';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
    if (empty($transactionId)) $errors[] = 'Transaction ID / Reference Number is required';
    
    $validAmounts = [500, 1500];
    if (!in_array($amount, $validAmounts)) $errors[] = 'Please select a valid payment plan';
    
    $upload = ['success' => true, 'filename' => null];
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] != UPLOAD_ERR_NO_FILE) {
        $upload = uploadPaymentProof($_FILES['payment_proof']);
        if (!$upload['success']) $errors[] = $upload['error'];
    }
    
    if (empty($errors)) {
        try {
            // Check if transaction ID already exists
            $sql = "SELECT id FROM payments WHERE transaction_id = :transaction_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':transaction_id' => $transactionId]);
            $existingTransaction = $stmt->fetch();
            
            if ($existingTransaction) {
                $error = 'This Transaction ID has already been used. Please use a valid transaction ID.';
                if ($upload['filename'] && file_exists(UPLOAD_DIR . $upload['filename'])) unlink(UPLOAD_DIR . $upload['filename']);
            } else {
                // Check if user already exists
                $sql = "SELECT u.id, u.has_access, u.access_expires, u.unique_hash FROM users u WHERE u.email = :email";
                $stmt = $db->prepare($sql);
                $stmt->execute([':email' => $email]);
                $existingUser = $stmt->fetch();
                
                $planDetails = getPlanDetails($amount);
                $additionalDays = $planDetails['days'];
                
                $sql = "INSERT INTO payments (full_name, email, phone, amount, transaction_id, payment_proof, status) 
                        VALUES (:full_name, :email, :phone, :amount, :transaction_id, :payment_proof, 'pending')";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':amount' => $amount,
                    ':transaction_id' => $transactionId,
                    ':payment_proof' => $upload['filename']
                ]);
                
                if ($result) {
                    $paymentId = $db->lastInsertId();
                    
                    if ($existingUser && $existingUser['has_access'] == 1 && (!$existingUser['access_expires'] || strtotime($existingUser['access_expires']) > time())) {
                        $currentExpires = $existingUser['access_expires'];
                        $sql = "UPDATE payments SET admin_notes = :notes WHERE id = :id";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            ':notes' => "Extension request: Current access expires on " . date('d M Y', strtotime($currentExpires)) . ". This payment will extend by {$additionalDays} days.",
                            ':id' => $paymentId
                        ]);
                    }
                    
                    logAccess($db, null, null, 'payment_submission', 'pending');
                    
                    $durationText = ($amount == 1500) ? '1 month (30 days)' : '1 week (7 days)';
                    $subject = "Payment Received - " . SITE_NAME;
                    $message = "
                    <html>
                    <head><title>Payment Confirmation</title></head>
                    <body>
                        <h2>Hello {$fullName},</h2>
                        <p>Thank you for your payment submission.</p>
                        <p><strong>Payment Details:</strong></p>
                        <ul>
                            <li>Plan: " . ($amount == 1500 ? 'Premium Plan - 1 Month' : 'Basic Plan - 1 Week') . "</li>
                            <li>Amount: " . formatCurrency($amount, 'FRW') . "</li>
                            <li>Duration: {$durationText}</li>
                            <li>Transaction ID: " . htmlspecialchars($transactionId) . "</li>
                            <li>Submitted: " . date('d M Y, h:i A') . "</li>
                        </ul>";
                    
                    if ($existingUser && $existingUser['has_access'] == 1 && strtotime($existingUser['access_expires']) > time()) {
                        $message .= "<p><strong>⚠️ Note:</strong> You already have active access until " . date('d M Y', strtotime($existingUser['access_expires'])) . ". This payment will EXTEND your access by {$durationText} after approval.</p>";
                    }
                    
                    $message .= "<p>Your payment is now pending review. You will be redirected to the waiting page where you will be automatically taken to the exam once approved.</p>
                                <p>Best regards,<br>" . SITE_NAME . " Team</p>
                            </body></html>";
                    
                    sendEmail($email, $subject, $message);
                    
                    header('Location: PROVI/waiting.php?email=' . urlencode($email) . '&payment_id=' . $paymentId);
                    exit();
                    
                } else {
                    $error = 'Failed to submit payment. Please try again.';
                    if ($upload['filename'] && file_exists(UPLOAD_DIR . $upload['filename'])) unlink(UPLOAD_DIR . $upload['filename']);
                }
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = 'This Transaction ID has already been used. Please use a unique transaction ID.';
            } else {
                $error = 'Database error. Please try again.';
            }
            if ($upload['filename'] && file_exists(UPLOAD_DIR . $upload['filename'])) unlink(UPLOAD_DIR . $upload['filename']);
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Submit Payment - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 20px 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 680px;
            width: 100%;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.02);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .header {
            background: linear-gradient(135deg, #1a472a 0%, #2c5e3a 100%);
            color: white;
            padding: 32px 24px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 600;
            letter-spacing: -0.3px;
        }
        
        .header p {
            opacity: 0.85;
            font-size: 15px;
        }
        
        .content {
            padding: 28px 24px;
        }
        
        /* Active Access Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: white;
            border-radius: 28px;
            max-width: 450px;
            width: 90%;
            padding: 28px;
            text-align: center;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-icon {
            font-size: 56px;
            margin-bottom: 16px;
        }
        
        .modal h3 {
            font-size: 24px;
            color: #1a472a;
            margin-bottom: 12px;
        }
        
        .modal p {
            color: #5a6e5c;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .modal-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 40px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .modal-btn-primary {
            background: linear-gradient(135deg, #2c5e3a, #1f4529);
            color: white;
        }
        
        .modal-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(44,94,58,0.3);
        }
        
        .modal-btn-secondary {
            background: #e9ecef;
            color: #2c5e3a;
        }
        
        .modal-btn-secondary:hover {
            background: #dee2e6;
        }
        
        .plans-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .plan-card {
            border: 2px solid #e9ecef;
            border-radius: 20px;
            padding: 20px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s ease;
            background: #ffffff;
        }
        
        .plan-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
            border-color: #cbd5e1;
        }
        
        .plan-card.selected {
            border-color: #2c5e3a;
            background: linear-gradient(135deg, #f8faf6 0%, #f0f5ed 100%);
            box-shadow: 0 4px 12px rgba(44,94,58,0.12);
        }
        
        .plan-card h3 {
            color: #1a2c1e;
            font-size: 20px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .plan-price {
            font-size: 32px;
            font-weight: 700;
            color: #2c5e3a;
            margin: 12px 0;
        }
        
        .plan-duration {
            color: #5a6e5c;
            font-size: 13px;
            margin-top: 8px;
        }
        
        .plan-badge {
            position: absolute;
            top: -10px;
            right: 12px;
            background: #f59e0b;
            color: white;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .plan-card { position: relative; }
        
        .form-group {
            margin-bottom: 22px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #1e2a22;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            font-size: 15px;
            transition: all 0.2s;
            background: #fefefe;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #2c5e3a;
            box-shadow: 0 0 0 3px rgba(44,94,58,0.12);
            background: white;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #7f8c7d;
            margin-top: 6px;
        }
        
        .file-input-wrapper input[type="file"] {
            padding: 12px;
            border: 1.5px dashed #cbd5e1;
            background: #fafcf8;
            border-radius: 16px;
            cursor: pointer;
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #2c5e3a 0%, #1f4529 100%);
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 12px;
            letter-spacing: 0.3px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(44,94,58,0.25);
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 18px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fff5f5;
            color: #b91c1c;
            border: 1px solid #fed7d7;
        }
        
        .alert-success {
            background: #ecfdf5;
            color: #2c5e3a;
            border: 1px solid #a7f3d0;
        }
        
        .payment-instructions {
            background: #fafcf8;
            padding: 20px;
            border-radius: 20px;
            margin-top: 28px;
            font-size: 13.5px;
            border: 1px solid #e9ecef;
        }
        
        .payment-instructions h4 {
            margin-bottom: 12px;
            color: #1e2a22;
            font-weight: 600;
        }
        
        .mobile-money {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 12px 14px;
            margin: 14px 0;
            border-radius: 14px;
        }
        
        .mobile-money code {
            background: #ffffff;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #b45309;
            border: 1px solid #fed7aa;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2c5e3a;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .loading {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading.active { display: flex; }
        
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e9ecef;
            border-top-color: #2c5e3a;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        @media (max-width: 600px) {
            .container { border-radius: 24px; }
            .header { padding: 24px 20px; }
            .header h1 { font-size: 24px; }
            .content { padding: 20px; }
            .plans-container { grid-template-columns: 1fr; gap: 12px; }
            .plan-card { padding: 16px; }
            .plan-price { font-size: 28px; }
            .btn-submit { padding: 14px; }
            .payment-instructions { padding: 16px; }
            .modal { padding: 20px; }
            .modal h3 { font-size: 20px; }
            .modal-buttons { flex-direction: column; }
            .modal-btn { width: 100%; }
        }
        
        @media (max-width: 480px) {
            .header h1 { font-size: 22px; }
            .form-group input { padding: 12px 14px; }
            .btn-submit { font-size: 15px; }
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>
    
    <!-- Modal for active access users -->
    <div class="modal-overlay" id="activeAccessModal">
        <div class="modal">
            <div class="modal-icon">✅</div>
            <h3>You Already Have Active Access!</h3>
            <p id="modalMessage">You currently have active access to the exam portal.</p>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-primary" id="goToExamBtn">🎓 Go to Exam Portal</button>
                <button class="modal-btn modal-btn-secondary" id="extendAccessBtn">⏰ Extend My Access</button>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="header">
            <h1>💚 Payment Submission</h1>
            <p>Select your plan and complete payment</p>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>⚠️ Oops!</strong><br>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" id="paymentForm">
                <div class="plans-container">
                    <div class="plan-card" onclick="selectPlan(500)">
                        <input type="radio" name="amount" value="500" id="plan_basic" required>
                        <h3>📘 Basic Plan</h3>
                        <div class="plan-price">500 FRW</div>
                        <div class="plan-duration">⏰ 7 days access (1 week)</div>
                    </div>
                    
                    <div class="plan-card" onclick="selectPlan(1500)">
                        <input type="radio" name="amount" value="1500" id="plan_premium">
                        <h3>🌟 Premium Plan</h3>
                        <div class="plan-price">1500 FRW</div>
                        <div class="plan-duration">⏰ 30 days access (1 month)</div>
                        <div class="plan-badge">Best Value</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Full name <span class="required">*</span></label>
                    <input type="text" name="full_name" required 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                           placeholder="Enter your full name" id="fullNameInput">
                </div>
                
                <div class="form-group">
                    <label>Email address <span class="required">*</span></label>
                    <input type="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="you@example.com" id="emailInput">
                    <div class="help-text">We'll send your access link here after approval.</div>
                </div>
                
                <div class="form-group">
                    <label>Phone number <span class="optional">(Optional)</span></label>
                    <input type="tel" name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                           placeholder="For contact purposes">
                </div>
                
                <div class="form-group">
                    <label>Transaction ID / Reference <span class="required">*</span></label>
                    <input type="text" name="transaction_id" required 
                           value="<?php echo htmlspecialchars($_POST['transaction_id'] ?? ''); ?>"
                           placeholder="e.g., MOMO-123456" id="transactionIdInput">
                    <div class="help-text">Required to verify your payment. Each ID is unique.</div>
                </div>
                
                <div class="form-group">
                    <label>Upload payment proof <span class="optional">(Optional)</span></label>
                    <div class="file-input-wrapper">
                        <input type="file" name="payment_proof" accept="image/*,.pdf">
                    </div>
                    <div class="help-text">Screenshot or photo (JPG, PNG, PDF, max 5MB)</div>
                    <div class="help-text" id="fileName"></div>
                </div>
                
                <div class="payment-instructions">
                    <h4>📌 How to pay</h4>
                    <div class="mobile-money">
                        <strong>📱 Mobile Money (MTN Rwanda):</strong><br>
                        Dial <code>*182*8*1*814570#</code> and follow the prompts.<br>
                        <small>Or use MoMo Pay Code: 814570</small>
                    </div>
                    <div>
                        <strong>🏦 Bank transfer:</strong><br>
                        PAYFODA Exam Portal<br>
                        Account: 1234567890 | Bank of Kigali<br>
                        <strong>📧 Other options:</strong> Contact support for assistance.
                    </div>
                    <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid #e2e8f0;">
                        <small>⚠️ After payment, enter your Transaction ID above and submit. You'll be redirected to a waiting page and automatically taken to the exam once approved.</small>
                    </div>
                </div>
                
                <input type="hidden" name="action" id="actionInput" value="new_payment">
                <button type="submit" class="btn-submit" id="submitBtn">💚 Submit Payment</button>
            </form>
            
            <div style="text-align: center; margin-top: 24px;">
                <a href="check_status.php" class="back-link">🔍 Check existing payment status →</a>
            </div>
        </div>
    </div>
    
    <script>
        let pendingCheck = false;
        let currentAccessData = null;
        
        function selectPlan(amount) {
            document.querySelectorAll('input[name="amount"]').forEach(radio => {
                radio.checked = false;
                radio.closest('.plan-card').classList.remove('selected');
            });
            const selectedRadio = document.querySelector(`input[name="amount"][value="${amount}"]`);
            if (selectedRadio) {
                selectedRadio.checked = true;
                selectedRadio.closest('.plan-card').classList.add('selected');
            }
        }
        
        document.querySelectorAll('input[name="amount"]').forEach(radio => {
            if (radio.checked) radio.closest('.plan-card').classList.add('selected');
            radio.addEventListener('change', function() {
                document.querySelectorAll('.plan-card').forEach(card => card.classList.remove('selected'));
                if (this.checked) this.closest('.plan-card').classList.add('selected');
            });
        });
        
        // Check if transaction ID has access
        async function checkTransactionAccess() {
            const transactionId = document.getElementById('transactionIdInput')?.value.trim();
            if (!transactionId || transactionId.length < 3) return;
            
            // Check if this transaction ID was used for approved payment
            try {
                const response = await fetch(`check_status_ajax.php?transaction_id=${encodeURIComponent(transactionId)}`);
                const data = await response.json();
                
                if (data.has_access && data.access_url) {
                    currentAccessData = data;
                    const modal = document.getElementById('activeAccessModal');
                    const modalMessage = document.getElementById('modalMessage');
                    modalMessage.innerHTML = `This Transaction ID (${transactionId}) already has active access!<br><strong>Expires: ${data.expires_on}</strong><br>Remaining: ${data.remaining_days} days`;
                    modal.classList.add('active');
                }
            } catch (error) {
                console.error('Error checking transaction:', error);
            }
        }
        
        // Check if email has active access
        async function checkEmailAccess() {
            const email = document.getElementById('emailInput')?.value.trim();
            if (!email || !email.includes('@')) return;
            
            if (pendingCheck) return;
            pendingCheck = true;
            
            try {
                const response = await fetch(`?check_access=1&email=${encodeURIComponent(email)}`);
                const data = await response.json();
                
                if (data.has_access) {
                    currentAccessData = data;
                    const modal = document.getElementById('activeAccessModal');
                    const modalMessage = document.getElementById('modalMessage');
                    modalMessage.innerHTML = `Hello ${data.full_name || 'there'}!<br>You have active access until <strong>${data.expires_on}</strong> (${data.remaining_days} days remaining).<br>Would you like to continue to the exam or extend your access?`;
                    modal.classList.add('active');
                }
            } catch (error) {
                console.error('Error checking email:', error);
            } finally {
                pendingCheck = false;
            }
        }
        
        // Modal button handlers
        document.getElementById('goToExamBtn')?.addEventListener('click', () => {
            if (currentAccessData && currentAccessData.access_url) {
                window.location.href = currentAccessData.access_url;
            } else {
                const email = document.getElementById('emailInput')?.value.trim();
                if (email) {
                    window.location.href = `check_status.php?email=${encodeURIComponent(email)}`;
                }
            }
        });
        
        document.getElementById('extendAccessBtn')?.addEventListener('click', () => {
            document.getElementById('activeAccessModal').classList.remove('active');
            // Keep form enabled for extension payment
            document.getElementById('actionInput').value = 'extend';
            // Pre-fill name from access data if available
            if (currentAccessData && currentAccessData.full_name) {
                document.getElementById('fullNameInput').value = currentAccessData.full_name;
            }
        });
        
        // Real-time validation on email and transaction ID
        let emailTimer;
        const emailInput = document.getElementById('emailInput');
        const transactionInput = document.getElementById('transactionIdInput');
        
        if (emailInput) {
            emailInput.addEventListener('input', () => {
                clearTimeout(emailTimer);
                emailTimer = setTimeout(checkEmailAccess, 800);
            });
        }
        
        if (transactionInput) {
            transactionInput.addEventListener('input', () => {
                clearTimeout(emailTimer);
                emailTimer = setTimeout(checkTransactionAccess, 800);
            });
        }
        
        document.querySelectorAll('input[name="amount"]').forEach(radio => {
            if (radio.checked) radio.closest('.plan-card').classList.add('selected');
            radio.addEventListener('change', function() {
                document.querySelectorAll('.plan-card').forEach(card => card.classList.remove('selected'));
                if (this.checked) this.closest('.plan-card').classList.add('selected');
            });
        });
        
        const fileInput = document.querySelector('input[type="file"]');
        const fileNameSpan = document.getElementById('fileName');
        if (fileInput) {
            fileInput.addEventListener('change', () => {
                const name = fileInput.files[0]?.name;
                if (name) {
                    fileNameSpan.innerHTML = `📎 ${name}`;
                    fileNameSpan.style.color = '#2c5e3a';
                } else {
                    fileNameSpan.innerHTML = '';
                }
            });
        }
        
        document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
            const selectedPlan = document.querySelector('input[name="amount"]:checked');
            if (!selectedPlan) {
                e.preventDefault();
                alert('Please select a payment plan (500 FRW for 1 week or 1500 FRW for 1 month)');
                return false;
            }
            const txId = document.querySelector('input[name="transaction_id"]');
            if (!txId.value.trim()) {
                e.preventDefault();
                alert('Please enter your Transaction ID / Reference Number');
                txId.focus();
                return false;
            }
            const btn = document.getElementById('submitBtn');
            const loading = document.getElementById('loading');
            btn.disabled = true;
            btn.innerHTML = '⏳ Processing...';
            loading.classList.add('active');
            setTimeout(() => {
                if (btn.disabled) {
                    btn.disabled = false;
                    btn.innerHTML = '💚 Submit Payment';
                    loading.classList.remove('active');
                }
            }, 10000);
        });
        
        document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
            const file = document.querySelector('input[type="file"]').files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                e.preventDefault();
                alert('File too large. Max size is 5MB.');
                return false;
            }
            const allowed = ['image/jpeg','image/jpg','image/png','application/pdf'];
            if (file && !allowed.includes(file.type)) {
                e.preventDefault();
                alert('Invalid file type. Please upload JPG, PNG, or PDF.');
                return false;
            }
        });
        
        // Initial check if email is pre-filled
        if (emailInput && emailInput.value.trim()) {
            setTimeout(checkEmailAccess, 500);
        }
        
        console.log('Payment page loaded with extension support');
    </script>
</body>
</html>