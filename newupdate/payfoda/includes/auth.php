<?php
// includes/auth.php
// Authentication helper for admin

// Define root path if not defined
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/config/config.php';

class AdminAuth {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function login($username, $password) {
        $sql = "SELECT id, username, email, password_hash, role, is_active 
                FROM admins 
                WHERE (username = :username OR email = :email) AND is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':email' => $username
        ]);
        
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['login_time'] = time();
            
            // Update last login
            $sql = "UPDATE admins SET last_login = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $admin['id']]);
            
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['admin_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    // REMOVED: register function - no new admins can be created after first one
    
    public function isSuperAdmin() {
        return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] == 'super_admin';
    }
    
    public function isAdmin() {
        return $this->isLoggedIn();
    }
    
    public function requireSuperAdmin() {
        if (!$this->isLoggedIn() || !$this->isSuperAdmin()) {
            header('Location: admin_dashboard.php');
            exit();
        }
    }
    
    public function changePassword($adminId, $currentPassword, $newPassword) {
        $sql = "SELECT password_hash FROM admins WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $adminId]);
        $admin = $stmt->fetch();
        
        if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }
        
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'New password must be at least 8 characters'];
        }
        
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE admins SET password_hash = :password WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute([':password' => $newHash, ':id' => $adminId])) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Password change failed'];
    }
    
    // Check if admin exists
    public function adminExists() {
        $sql = "SELECT COUNT(*) as count FROM admins";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
}
?>