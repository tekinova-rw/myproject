<?php
// includes/User.php
// User management class

// Define root path if not defined
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/config/config.php';

class User {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function createOrGetUser($fullName, $email, $phone = null) {
        // Check if user exists
        $sql = "SELECT id, full_name, email, unique_hash, has_access, access_expires 
                FROM users 
                WHERE email = :email";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        
        if ($user) {
            return $user;
        }
        
        // Create new user
        $uniqueHash = generateUniqueHash($email);
        
        $sql = "INSERT INTO users (full_name, email, phone, unique_hash) 
                VALUES (:full_name, :email, :phone, :unique_hash)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':phone' => $phone,
            ':unique_hash' => $uniqueHash
        ]);
        
        if ($result) {
            return [
                'id' => $this->db->lastInsertId(),
                'full_name' => $fullName,
                'email' => $email,
                'unique_hash' => $uniqueHash,
                'has_access' => 0,
                'access_expires' => null
            ];
        }
        
        return null;
    }
    
    /**
     * Grant access to user for specified duration
     * This REPLACES any existing access with new expiry date
     * @param int $userId User ID
     * @param int $durationDays Number of days access should last (7 for 1 week, 30 for 1 month)
     * @return bool Success or failure
     */
    public function grantAccess($userId, $durationDays = 7) {
        $expires = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));
        
        $sql = "UPDATE users 
                SET has_access = 1, access_expires = :expires 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':expires' => $expires,
            ':id' => $userId
        ]);
    }
    
    /**
     * Extend user access by adding days to current expiry
     * This ADDS to existing access duration
     * @param int $userId User ID
     * @param int $additionalDays Number of days to add to current access
     * @return bool Success or failure
     */
    public function extendAccess($userId, $additionalDays) {
        $sql = "UPDATE users 
                SET access_expires = DATE_ADD(access_expires, INTERVAL :days DAY),
                    has_access = 1
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':days' => $additionalDays,
            ':id' => $userId
        ]);
    }
    
    /**
     * Check if user has active access
     * @param int $userId User ID
     * @return bool True if user has active access
     */
    public function hasActiveAccess($userId) {
        $sql = "SELECT has_access, access_expires 
                FROM users 
                WHERE id = :id 
                AND has_access = 1 
                AND (access_expires IS NULL OR access_expires > NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get user's current access expiry
     * @param int $userId User ID
     * @return string|null Expiry date or null if no access
     */
    public function getAccessExpiry($userId) {
        $sql = "SELECT access_expires FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $result = $stmt->fetch();
        
        return $result ? $result['access_expires'] : null;
    }
    
    public function revokeAccess($userId) {
        $sql = "UPDATE users SET has_access = 0, access_expires = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $userId]);
    }
    
    public function getUsersWithPayments() {
        $sql = "SELECT u.*, 
                COUNT(p.id) as payment_count,
                MAX(p.status) as latest_payment_status,
                MAX(p.submitted_at) as last_payment_date
                FROM users u
                LEFT JOIN payments p ON u.id = p.user_id
                GROUP BY u.id
                ORDER BY u.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getUserByHash($hash) {
        $sql = "SELECT * FROM users WHERE unique_hash = :hash";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':hash' => $hash]);
        return $stmt->fetch();
    }
    
    public function getUserByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }
    
    /**
     * Get remaining access days for a user
     * @param int $userId User ID
     * @return int Remaining days (0 if no access or expired)
     */
    public function getRemainingDays($userId) {
        $sql = "SELECT access_expires FROM users WHERE id = :id AND has_access = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $result = $stmt->fetch();
        
        if ($result && $result['access_expires']) {
            $expires = strtotime($result['access_expires']);
            $now = time();
            if ($expires > $now) {
                return ceil(($expires - $now) / 86400);
            }
        }
        
        return 0;
    }
}
?>