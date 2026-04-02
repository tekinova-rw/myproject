<?php
// PROVI/waiting.php
// Waiting page that auto-refreshes and redirects when access is granted

require_once __DIR__ . '/../config/config.php';

$email = isset($_GET['email']) ? sanitize($_GET['email']) : '';
$paymentId = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;

// If email provided, check if user already has active access
if ($email) {
    $sql = "SELECT u.has_access, u.access_expires, u.unique_hash 
            FROM users u 
            WHERE u.email = :email 
            AND u.has_access = 1 
            AND (u.access_expires IS NULL OR u.access_expires > NOW())";
    $stmt = $db->prepare($sql);
    $stmt->execute([':email' => $email]);
    $activeUser = $stmt->fetch();
    
    if ($activeUser) {
        header('Location: index.php?hash=' . $activeUser['unique_hash']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="rw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Itegereza Ishuri - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        
        .container {
            max-width: 560px;
            width: 100%;
            background: white;
            border-radius: 28px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            overflow: hidden;
            text-align: center;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .header {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
            padding: 32px 20px;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .header p {
            opacity: 0.95;
            font-size: 14px;
        }
        
        .content {
            padding: 35px 25px;
        }
        
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.08); opacity: 0.9; }
        }
        
        h2 {
            color: #1e2a22;
            font-size: 24px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .message {
            color: #5a6e5c;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .status-box {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 28px 20px;
            margin: 25px 0;
        }
        
        .spinner {
            width: 55px;
            height: 55px;
            border: 4px solid #e9ecef;
            border-top: 4px solid #ff9800;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
            margin: 0 auto 18px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .status-text {
            font-weight: 600;
            color: #ff9800;
            font-size: 17px;
            margin-bottom: 10px;
        }
        
        .countdown {
            font-size: 14px;
            color: #7f8c7d;
        }
        
        .countdown span {
            font-weight: bold;
            color: #ff9800;
            font-size: 20px;
        }
        
        .payment-details {
            background: #e8f0fe;
            border-radius: 18px;
            padding: 18px;
            margin: 25px 0;
            text-align: left;
            font-size: 14px;
            border-left: 4px solid #ff9800;
        }
        
        .payment-details p {
            margin: 8px 0;
        }
        
        .payment-details strong {
            color: #f57c00;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #ff9800;
            color: white;
            text-decoration: none;
            border-radius: 40px;
            margin: 8px 6px;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #f57c00;
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(245, 124, 0, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .progress-bar {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
            margin: 22px 0 8px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff9800, #f57c00);
            animation: loading 30s linear forwards;
        }
        
        @keyframes loading {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        
        .footer-text {
            margin-top: 25px;
            font-size: 12px;
            color: #8b9a8d;
            line-height: 1.5;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-radius: 12px;
            padding: 12px;
            font-size: 13px;
            margin-top: 15px;
        }
        
        @media (max-width: 600px) {
            .container { border-radius: 20px; }
            .header { padding: 25px 15px; }
            .header h1 { font-size: 24px; }
            .content { padding: 28px 20px; }
            .icon { font-size: 65px; }
            h2 { font-size: 22px; }
            .btn { padding: 10px 20px; font-size: 13px; }
            .status-box { padding: 22px 15px; }
        }
    </style>
</head>
<body>
    <div class="container" id="waitingContainer">
        <div class="header">
            <h1>⏳ Itegereza Ishuri</h1>
            <p>Ishuri ryawe riri gutegerezwa gukorerwa</p>
        </div>
        
        <div class="content">
            <div class="icon">💳</div>
            <h2>Itegereza Emera</h2>
            <div class="message">
                Murakoze cyane kwishyura! <br>
                Admin ari kureba ubwishyu bwawe. Uzohita ujya mu kizamini iyo byemewe.
            </div>
            
            <div class="status-box">
                <div class="spinner"></div>
                <div class="status-text" id="statusText">Gitegereje kwemeza...</div>
                <div class="countdown">
                    Kongera kureba nyuma ya <span id="countdown">10</span> amasegonda
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
            </div>
            
            <?php if ($email): ?>
            <div class="payment-details">
                <p><strong>📧 Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                <?php if ($paymentId > 0): ?>
                <p><strong>🆔 ID yo kwishyura:</strong> #<?php echo $paymentId; ?></p>
                <?php endif; ?>
                <p><strong>⏰ Igihe gitegerejwe:</strong> Mu masaha make cyangwa 24</p>
                <p><small>Urubuga ruzahita rujya mu kizamini iyo ishuri ryemewe.</small></p>
            </div>
            <?php endif; ?>
            
            <div id="errorMessage" class="alert-warning" style="display: none;">
                ⚠️ Ikibazo cyo guhura na seriveri. Kanda "Reba Status" kugira ugerageze.
            </div>
            
            <div>
                <a href="../pay.php" class="btn btn-secondary">← Garuka mu Kwishyura</a>
                <a href="../check_status.php?email=<?php echo urlencode($email); ?>" class="btn" id="statusBtn">📊 Reba Status</a>
            </div>
            
            <div class="footer-text">
                <small>Uru rubuga ruzahita rwongera kureba buri masegonda 10.<br>
                Uzajya uhita ujya mu kizamini iyo admin yemeye ubwishyu bwawe.</small>
            </div>
        </div>
    </div>
    
    <script>
        let countdown = 10;
        let isRedirecting = false;
        let countdownInterval = null;
        const email = "<?php echo addslashes($email); ?>";

        function updateCountdown() {
            const el = document.getElementById('countdown');
            if (el) el.textContent = countdown;

            if (countdown <= 0) {
                checkPaymentStatus();
                countdown = 10;
                
                // Reset progress bar
                const progress = document.getElementById('progressFill');
                if (progress) {
                    progress.style.animation = 'none';
                    void progress.offsetWidth;
                    progress.style.animation = 'loading 30s linear forwards';
                }
            } else {
                countdown--;
            }
        }

        async function checkPaymentStatus() {
            if (isRedirecting) return;
            
            const errorDiv = document.getElementById('errorMessage');
            if (errorDiv) errorDiv.style.display = 'none';

            try {
                const response = await fetch(`../check_status_ajax.php?email=${encodeURIComponent(email)}&t=${Date.now()}`, {
                    headers: { 'Cache-Control': 'no-cache' }
                });

                if (!response.ok) throw new Error('Network error');

                const data = await response.json();
                console.log('Status received:', data);

                // MAIN AUTO REDIRECT LOGIC - When admin approves
                if (data.has_access === true && data.access_url) {
                    isRedirecting = true;
                    if (countdownInterval) clearInterval(countdownInterval);

                    // Show success UI
                    const statusBox = document.querySelector('.status-box');
                    if (statusBox) {
                        statusBox.innerHTML = `
                            <div style="font-size: 52px; margin: 15px 0;">✅</div>
                            <div class="status-text" style="color: #28a745; font-size: 19px;">
                                Access yemewe!
                            </div>
                            <div style="margin-top: 10px; color: #2c5e3a;">Urujya mu kizamini...</div>
                        `;
                    }

                    // Redirect after nice delay
                    setTimeout(() => {
                        window.location.href = data.access_url;
                    }, 1200);
                    return;
                }

                // Update status text
                const statusTextEl = document.getElementById('statusText');
                if (statusTextEl) {
                    if (data.payment_status === 'approved') {
                        statusTextEl.innerHTML = '✅ Payment yemewe - Gutegereza access...';
                        statusTextEl.style.color = '#28a745';
                    } else if (data.payment_status === 'rejected') {
                        statusTextEl.innerHTML = '❌ Ishuri ryangwa';
                        statusTextEl.style.color = '#dc3545';
                    } else {
                        statusTextEl.innerHTML = '⏳ Gitegereje kwemeza...';
                    }
                }

            } catch (error) {
                console.error('Error checking status:', error);
                const errorDiv = document.getElementById('errorMessage');
                if (errorDiv) {
                    errorDiv.style.display = 'block';
                }
            }
        }

        // Start everything
        function startWaiting() {
            // Initial check
            setTimeout(checkPaymentStatus, 600);

            // Countdown every second
            countdownInterval = setInterval(() => {
                if (!isRedirecting) updateCountdown();
            }, 1000);

            // Backup check every 12 seconds
            setInterval(() => {
                if (!isRedirecting) checkPaymentStatus();
            }, 12000);
        }

        window.onload = function() {
            console.log('✅ Waiting page loaded for:', email);
            startWaiting();
        };

        // Check again when user returns to tab
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !isRedirecting) {
                checkPaymentStatus();
            }
        });
    </script>
</body>
</html>