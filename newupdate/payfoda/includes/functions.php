<?php
// includes/functions.php
// Helper functions

/**
 * Generate unique hash for user access
 */
function generateUniqueHash($email) {
    return hash('sha256', $email . uniqid(mt_rand(), true) . time());
}

/**
 * Send email notification (with error suppression)
 */
function sendEmail($to, $subject, $message) {
    // For development, just log the email
    $logFile = ROOT_PATH . '/logs/email_log.txt';
    
    // Create logs directory if it doesn't exist
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0777, true);
    }
    
    $logMessage = date('Y-m-d H:i:s') . " - To: $to - Subject: $subject\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Try to send email, but don't fail if it doesn't work
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . SITE_NAME . ' <' . ADMIN_EMAIL . '>' . "\r\n";
    
    // Suppress mail errors
    @mail($to, $subject, $message, $headers);
    
    // Always return true so the process continues
    return true;
}

/**
 * Send payment approval email (with extension support)
 */
function sendPaymentApprovalEmail($email, $fullName, $hash, $durationDays, $isExtension = false) {
    $subject = "Exam Access Granted - " . SITE_NAME;
    $accessUrl = SITE_URL . "/PROVI/index.php?hash=" . $hash;
    
    $durationText = $durationDays == 7 ? '1 week' : '1 month';
    
    if ($isExtension) {
        $message = "
        <html>
        <head>
            <title>Exam Access Extended - " . SITE_NAME . "</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { padding: 30px; background: #fff; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999; text-align: center; }
                .highlight { background: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>✨ Access Extended! ✨</h2>
                </div>
                <div class='content'>
                    <h2>Hello {$fullName},</h2>
                    <p>Your payment has been approved and your access has been <strong>EXTENDED</strong>!</p>
                    <div class='highlight'>
                        <strong>📅 Extension Details:</strong><br>
                        Your existing access period has been extended by <strong>{$durationText}</strong>.<br>
                        You can now continue accessing your exam portal.
                    </div>
                    <p>Click the button below to access your exam:</p>
                    <div style='text-align: center;'>
                        <a href='{$accessUrl}' class='button'>🎓 Access Your Exam</a>
                    </div>
                    <p>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p><small>{$accessUrl}</small></p>
                    <p>If you have any questions, please contact support.</p>
                    <div class='footer'>
                        <p>Best regards,<br><strong>" . SITE_NAME . " Team</strong></p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    } else {
        $message = "
        <html>
        <head>
            <title>Exam Access Granted - " . SITE_NAME . "</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { padding: 30px; background: #fff; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999; text-align: center; }
                .highlight { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🎉 Access Granted! 🎉</h2>
                </div>
                <div class='content'>
                    <h2>Hello {$fullName},</h2>
                    <p>Your payment has been approved! You now have access to the exam portal.</p>
                    <div class='highlight'>
                        <strong>📅 Access Details:</strong><br>
                        Your access is valid for <strong>{$durationText}</strong>.<br>
                        Make sure to complete your exam before it expires.
                    </div>
                    <p>Click the button below to access your exam:</p>
                    <div style='text-align: center;'>
                        <a href='{$accessUrl}' class='button'>🎓 Start Your Exam</a>
                    </div>
                    <p>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p><small>{$accessUrl}</small></p>
                    <p>Best of luck with your exam!</p>
                    <div class='footer'>
                        <p>Best regards,<br><strong>" . SITE_NAME . " Team</strong></p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    return sendEmail($email, $subject, $message);
}

/**
 * Send payment rejection email
 */
function sendPaymentRejectionEmail($email, $fullName, $reason = '') {
    $subject = "Payment Status Update - " . SITE_NAME;
    
    $message = "
    <html>
    <head>
        <title>Payment Status Update</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 30px; background: #fff; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999; text-align: center; }
            .reason { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 15px 0; color: #721c24; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>❌ Payment Status Update</h2>
            </div>
            <div class='content'>
                <h2>Hello {$fullName},</h2>
                <p>We regret to inform you that your payment has been rejected.</p>
                " . ($reason ? "
                <div class='reason'>
                    <strong>Reason:</strong><br>
                    {$reason}
                </div>
                " : "") . "
                <p>Please contact support for further assistance or to resolve this issue.</p>
                <p>You can also submit a new payment with the correct details.</p>
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='" . SITE_URL . "/pay.php' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Submit New Payment</a>
                </div>
                <div class='footer'>
                    <p>Best regards,<br><strong>" . SITE_NAME . " Team</strong></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Upload payment proof (optional now)
 */
function uploadPaymentProof($file) {
    // If upload is not required and no file uploaded, return success
    if (!UPLOAD_REQUIRED && (!isset($file) || $file['error'] == UPLOAD_ERR_NO_FILE)) {
        return ['success' => true, 'filename' => null];
    }
    
    // Create uploads directory if it doesn't exist
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        if (UPLOAD_REQUIRED) {
            return ['success' => false, 'error' => 'Upload failed'];
        }
        return ['success' => true, 'filename' => null];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File too large. Max 5MB'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, PDF'];
    }
    
    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination = UPLOAD_DIR . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'error' => 'Failed to save file'];
}

/**
 * Log access attempt
 */
function logAccess($db, $userId, $userHash, $accessType, $status, $ip = null) {
    if (!$ip) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    }
    
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $sql = "INSERT INTO access_logs (user_id, user_hash, ip_address, user_agent, access_type, status) 
            VALUES (:user_id, :user_hash, :ip, :user_agent, :access_type, :status)";
    
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':user_id' => $userId,
        ':user_hash' => $userHash,
        ':ip' => $ip,
        ':user_agent' => $userAgent,
        ':access_type' => $accessType,
        ':status' => $status
    ]);
}

/**
 * Check if user has valid access
 */
function checkUserAccess($db, $hash) {
    $sql = "SELECT id, full_name, email, has_access, access_expires 
            FROM users 
            WHERE unique_hash = :hash AND has_access = 1 
            AND (access_expires IS NULL OR access_expires > NOW())";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':hash' => $hash]);
    
    return $stmt->fetch();
}
?>