<?php
class SecurityService {
    private $db;
    private $config;
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->config = array_merge([
            'max_attempts' => 5,
            'lockout_time' => 900, // 15 minutes
            'rate_limit_requests' => 60,
            'rate_limit_window' => 3600, // 1 hour
            'session_timeout' => 7200, // 2 hours
            'csrf_token_expiry' => 3600, // 1 hour
            'password_min_length' => 8,
            'password_require_special' => true
        ], $config);
        
        $this->initializeTables();
    }
    
    /**
     * Initialize security tables
     */
    private function initializeTables() {
        try {
            // Login attempts table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    email VARCHAR(255),
                    attempts INT DEFAULT 1,
                    locked_until TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_ip (ip_address),
                    INDEX idx_email (email),
                    INDEX idx_locked_until (locked_until)
                )
            ");
            
            // Rate limiting table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS rate_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    endpoint VARCHAR(255) NOT NULL,
                    requests INT DEFAULT 1,
                    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_ip_endpoint (ip_address, endpoint),
                    INDEX idx_window_start (window_start)
                )
            ");
            
            // CSRF tokens table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS csrf_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    token VARCHAR(128) UNIQUE NOT NULL,
                    user_id INT,
                    expires_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_token (token),
                    INDEX idx_user_id (user_id),
                    INDEX idx_expires_at (expires_at)
                )
            ");
            
            // Security logs table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS security_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    event_type VARCHAR(50) NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    user_id INT NULL,
                    details TEXT,
                    severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_event_type (event_type),
                    INDEX idx_ip_address (ip_address),
                    INDEX idx_user_id (user_id),
                    INDEX idx_severity (severity),
                    INDEX idx_created_at (created_at)
                )
            ");
            
        } catch (Exception $e) {
            error_log("Security tables initialization error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if IP is rate limited
     */
    public function isRateLimited($endpoint = 'general') {
        $ip = $this->getClientIP();
        $windowStart = date('Y-m-d H:i:s', time() - $this->config['rate_limit_window']);
        
        try {
            // Clean old entries
            $stmt = $this->db->prepare("
                DELETE FROM rate_limits 
                WHERE window_start < ?
            ");
            $stmt->execute([$windowStart]);
            
            // Check current rate
            $stmt = $this->db->prepare("
                SELECT requests 
                FROM rate_limits 
                WHERE ip_address = ? AND endpoint = ? AND window_start >= ?
            ");
            $stmt->execute([$ip, $endpoint, $windowStart]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                if ($result['requests'] >= $this->config['rate_limit_requests']) {
                    $this->logSecurityEvent('RATE_LIMIT_EXCEEDED', [
                        'endpoint' => $endpoint,
                        'requests' => $result['requests']
                    ], 'HIGH');
                    return true;
                }
                
                // Update request count
                $stmt = $this->db->prepare("
                    UPDATE rate_limits 
                    SET requests = requests + 1, updated_at = NOW() 
                    WHERE ip_address = ? AND endpoint = ? AND window_start >= ?
                ");
                $stmt->execute([$ip, $endpoint, $windowStart]);
            } else {
                // Create new entry
                $stmt = $this->db->prepare("
                    INSERT INTO rate_limits (ip_address, endpoint, requests, window_start) 
                    VALUES (?, ?, 1, NOW())
                ");
                $stmt->execute([$ip, $endpoint]);
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Rate limiting error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if account is locked due to failed attempts
     */
    public function isAccountLocked($email = null) {
        $ip = $this->getClientIP();
        
        try {
            $where = "ip_address = ?";
            $params = [$ip];
            
            if ($email) {
                $where .= " OR email = ?";
                $params[] = $email;
            }
            
            $stmt = $this->db->prepare("
                SELECT attempts, locked_until 
                FROM login_attempts 
                WHERE $where AND (locked_until IS NULL OR locked_until > NOW())
                ORDER BY updated_at DESC 
                LIMIT 1
            ");
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                if ($result['locked_until'] && strtotime($result['locked_until']) > time()) {
                    return true;
                }
                if ($result['attempts'] >= $this->config['max_attempts']) {
                    return true;
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Account lock check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record failed login attempt
     */
    public function recordFailedAttempt($email = null) {
        $ip = $this->getClientIP();
        
        try {
            $stmt = $this->db->prepare("
                SELECT id, attempts 
                FROM login_attempts 
                WHERE ip_address = ? AND (email = ? OR email IS NULL)
                ORDER BY updated_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$ip, $email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $newAttempts = $result['attempts'] + 1;
                $lockedUntil = null;
                
                if ($newAttempts >= $this->config['max_attempts']) {
                    $lockedUntil = date('Y-m-d H:i:s', time() + $this->config['lockout_time']);
                }
                
                $stmt = $this->db->prepare("
                    UPDATE login_attempts 
                    SET attempts = ?, locked_until = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$newAttempts, $lockedUntil, $result['id']]);
                
                if ($lockedUntil) {
                    $this->logSecurityEvent('ACCOUNT_LOCKED', [
                        'email' => $email,
                        'attempts' => $newAttempts
                    ], 'HIGH');
                }
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO login_attempts (ip_address, email, attempts) 
                    VALUES (?, ?, 1)
                ");
                $stmt->execute([$ip, $email]);
            }
            
            $this->logSecurityEvent('FAILED_LOGIN', ['email' => $email], 'MEDIUM');
        } catch (Exception $e) {
            error_log("Failed attempt recording error: " . $e->getMessage());
        }
    }
    
    /**
     * Clear failed attempts on successful login
     */
    public function clearFailedAttempts($email = null) {
        $ip = $this->getClientIP();
        
        try {
            $where = "ip_address = ?";
            $params = [$ip];
            
            if ($email) {
                $where .= " OR email = ?";
                $params[] = $email;
            }
            
            $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE $where");
            $stmt->execute($params);
            
            $this->logSecurityEvent('SUCCESSFUL_LOGIN', ['email' => $email], 'LOW');
        } catch (Exception $e) {
            error_log("Clear attempts error: " . $e->getMessage());
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken($user_id = null) {
        try {
            // Clean expired tokens
            $this->db->exec("DELETE FROM csrf_tokens WHERE expires_at < NOW()");
            
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + $this->config['csrf_token_expiry']);
            
            $stmt = $this->db->prepare("
                INSERT INTO csrf_tokens (token, user_id, expires_at) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$token, $user_id, $expiresAt]);
            
            return $token;
        } catch (Exception $e) {
            error_log("CSRF token generation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token, $user_id = null) {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM csrf_tokens 
                WHERE token = ? AND expires_at > NOW() AND (user_id = ? OR user_id IS NULL)
            ");
            $stmt->execute([$token, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // Delete used token
                $stmt = $this->db->prepare("DELETE FROM csrf_tokens WHERE id = ?");
                $stmt->execute([$result['id']]);
                return true;
            }
            
            $this->logSecurityEvent('CSRF_TOKEN_INVALID', ['token' => substr($token, 0, 8) . '...'], 'HIGH');
            return false;
        } catch (Exception $e) {
            error_log("CSRF token verification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate password strength
     */
    public function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < $this->config['password_min_length']) {
            $errors[] = "Password must be at least {$this->config['password_min_length']} characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if ($this->config['password_require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        // Check against common passwords
        $commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123', 'password123',
            'admin', 'letmein', 'welcome', 'monkey', '1234567890'
        ];
        
        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = "Password is too common, please choose a more secure password";
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Sanitize input to prevent XSS
     */
    public function sanitizeInput($input, $allowHtml = false) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        
        // Remove null bytes
        $input = str_replace(chr(0), '', $input);
        
        if (!$allowHtml) {
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } else {
            // Allow only safe HTML tags
            $allowedTags = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6>';
            $input = strip_tags($input, $allowedTags);
        }
        
        return trim($input);
    }
    
    /**
     * Validate file upload
     */
    public function validateFileUpload($file, $allowedTypes = [], $maxSize = null) {
        $errors = [];
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = "Invalid file upload";
            return $errors;
        }
        
        // Check file size
        $maxSize = $maxSize ?: $this->getSystemSetting('file_upload_max_size', 10485760); // 10MB default
        if ($file['size'] > $maxSize) {
            $errors[] = "File size exceeds maximum allowed size";
        }
        
        // Check file type
        if (!empty($allowedTypes)) {
            $fileType = mime_content_type($file['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "File type not allowed";
                $this->logSecurityEvent('INVALID_FILE_UPLOAD', [
                    'filename' => $file['name'],
                    'type' => $fileType
                ], 'MEDIUM');
            }
        }
        
        // Check for malicious content
        if ($this->containsMaliciousContent($file['tmp_name'])) {
            $errors[] = "File contains potentially malicious content";
            $this->logSecurityEvent('MALICIOUS_FILE_UPLOAD', [
                'filename' => $file['name']
            ], 'CRITICAL');
        }
        
        return $errors;
    }
    
    /**
     * Check for malicious content in uploaded files
     */
    private function containsMaliciousContent($filePath) {
        $content = file_get_contents($filePath, false, null, 0, 8192); // Read first 8KB
        
        // Malicious patterns
        $patterns = [
            '/<\s*script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/<\s*iframe/i',
            '/<\s*object/i',
            '/<\s*embed/i',
            '/eval\s*\(/i',
            '/base64_decode/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($eventType, $details = [], $severity = 'MEDIUM') {
        try {
            $ip = $this->getClientIP();
            $userId = $_SESSION['user_id'] ?? null;
            $detailsJson = json_encode($details);
            
            $stmt = $this->db->prepare("
                INSERT INTO security_logs (event_type, ip_address, user_id, details, severity) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$eventType, $ip, $userId, $detailsJson, $severity]);
            
            // Log critical events to system log
            if ($severity === 'CRITICAL') {
                error_log("CRITICAL SECURITY EVENT: $eventType from IP $ip - " . $detailsJson);
            }
        } catch (Exception $e) {
            error_log("Security logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Get security logs
     */
    public function getSecurityLogs($limit = 100, $severity = null) {
        try {
            $where = "1=1";
            $params = [];
            
            if ($severity) {
                $where .= " AND severity = ?";
                $params[] = $severity;
            }
            
            $stmt = $this->db->prepare("
                SELECT * FROM security_logs 
                WHERE $where 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $params[] = $limit;
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Security logs retrieval error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get system setting
     */
    private function getSystemSetting($key, $default = null) {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetchColumn();
            
            return $result !== false ? $result : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    
    /**
     * Clean up old security data
     */
    public function cleanupSecurityData() {
        try {
            // Clean expired CSRF tokens
            $this->db->exec("DELETE FROM csrf_tokens WHERE expires_at < NOW()");
            
            // Clean old rate limit entries
            $this->db->exec("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            
            // Clean old login attempts (keep for 24 hours)
            $this->db->exec("DELETE FROM login_attempts WHERE updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            
            // Clean old security logs (keep for 90 days)
            $this->db->exec("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            
            return true;
        } catch (Exception $e) {
            error_log("Security cleanup error: " . $e->getMessage());
            return false;
        }
    }
}

// Global security functions
function rateLimited($endpoint = 'general') {
    global $pdo;
    
    if (isset($pdo)) {
        $security = new SecurityService($pdo);
        return $security->isRateLimited($endpoint);
    }
    
    return false;
}

function generateCSRF($user_id = null) {
    global $pdo;
    
    if (isset($pdo)) {
        $security = new SecurityService($pdo);
        return $security->generateCSRFToken($user_id);
    }
    
    return false;
}

function verifyCSRF($token, $user_id = null) {
    global $pdo;
    
    if (isset($pdo)) {
        $security = new SecurityService($pdo);
        return $security->verifyCSRFToken($token, $user_id);
    }
    
    return false;
}

function sanitizeInput($input, $allowHtml = false) {
    global $pdo;
    
    if (isset($pdo)) {
        $security = new SecurityService($pdo);
        return $security->sanitizeInput($input, $allowHtml);
    }
    
    // Fallback sanitization
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>