<?php
/**
 * Admin Authentication Middleware
 * 
 * Provides session management, role-based access control, and security 
 * functions for the admin panel.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/../includes/config.php';

/**
 * Check if user is authenticated as admin
 */
function isAdminAuthenticated() {
    global $db;
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // If user_role is already set in session and is admin, return true
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        return true;
    }
    
    // If user_role is not set, check database to see if user is admin
    try {
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['role'] === 'admin') {
            // Set the role in session for future checks
            $_SESSION['user_role'] = 'admin';
            // Set last_activity if not set
            if (!isset($_SESSION['last_activity'])) {
                $_SESSION['last_activity'] = time();
            }
            return true;
        }
    } catch (PDOException $e) {
        error_log("Error checking admin role: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Check if user session is expired (30 minutes of inactivity)
 */
function isSessionExpired() {
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }
    
    $inactive_time = 30 * 60; // 30 minutes
    return (time() - $_SESSION['last_activity']) > $inactive_time;
}

/**
 * Refresh user session activity timestamp
 */
function refreshSessionActivity() {
    $_SESSION['last_activity'] = time();
}

/**
 * Require admin authentication - redirect to login if not authenticated
 */
function requireAdminAuth() {
    global $db;
    
    // Check remember me token if session is not valid
    if (!isAdminAuthenticated() || isSessionExpired()) {
        $authenticated = false;
        
        // Try to authenticate with remember token
        if (isset($_COOKIE['remember_admin'])) {
            $authenticated = authenticateWithRememberToken();
        }
        
        if (!$authenticated) {
            // Clear session and redirect to login
            destroyAdminSession();
            header('Location: /Bitversity/admin/');
            exit();
        }
    }
    
    // Refresh activity timestamp
    refreshSessionActivity();
    
    // Update last activity in database periodically (every 5 minutes)
    if (!isset($_SESSION['last_db_activity']) || 
        (time() - $_SESSION['last_db_activity']) > 300) {
        
        try {
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $_SESSION['last_db_activity'] = time();
        } catch (PDOException $e) {
            error_log("Error updating admin activity: " . $e->getMessage());
        }
    }
}

/**
 * Authenticate user with remember me token
 */
function authenticateWithRememberToken() {
    global $db;
    
    if (!isset($_COOKIE['remember_admin'])) {
        return false;
    }
    
    list($user_id, $token) = explode(':', $_COOKIE['remember_admin'], 2);
    
    try {
        // Get remember token from database
        $stmt = $db->prepare("
            SELECT rt.*, u.* 
            FROM remember_tokens rt 
            JOIN users u ON rt.user_id = u.id 
            WHERE rt.user_id = ? AND rt.expires_at > NOW() AND u.role = 'admin' AND u.is_active = 1
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && password_verify($token, $result['token'])) {
            // Valid token - recreate session
            $_SESSION['user_id'] = $result['id'];
            $_SESSION['user_email'] = $result['email'];
            $_SESSION['user_name'] = trim($result['first_name'] . ' ' . $result['last_name']);
            $_SESSION['user_role'] = $result['role'];
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = time();
            
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$result['id']]);
            
            // Log auto-login event
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) 
                                VALUES (?, 'admin_auto_login', 'Admin auto-login via remember token', ?, ?)");
            $stmt->execute([
                $result['id'],
                $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            
            return true;
        } else {
            // Invalid or expired token - clear cookie
            setcookie('remember_admin', '', time() - 3600, '/', '', true, true);
        }
        
    } catch (PDOException $e) {
        error_log("Remember token authentication error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Get current admin user information
 */
function getCurrentAdmin() {
    global $db;
    
    if (!isAdminAuthenticated()) {
        return null;
    }
    
    try {
        $stmt = $db->prepare("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ? AND role = 'admin'");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching admin user: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if admin has specific permission
 * (For future role-based permissions system)
 */
function hasAdminPermission($permission) {
    // For now, all admins have all permissions
    // This can be extended with a permissions system
    return isAdminAuthenticated();
}

/**
 * Destroy admin session and cookies
 */
function destroyAdminSession() {
    global $db;
    
    // Log logout event
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                                VALUES (?, 'admin_logout', 'Admin logged out', ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            
            // Update last logout time
            $stmt = $db->prepare("UPDATE users SET last_logout = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
        } catch (PDOException $e) {
            error_log("Error logging admin logout: " . $e->getMessage());
        }
    }
    
    // Clear remember token from database if it exists
    if (isset($_COOKIE['remember_admin'])) {
        list($user_id, $token) = explode(':', $_COOKIE['remember_admin'], 2);
        try {
            $stmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
            $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Error clearing remember token: " . $e->getMessage());
        }
    }
    
    // Clear cookies
    setcookie('remember_admin', '', time() - 3600, '/', '', true, true);
    
    // Destroy session
    session_destroy();
    session_start(); // Start fresh session
}

/**
 * Set flash message for next request
 */
function setAdminFlashMessage($message, $type = 'info') {
    $_SESSION['admin_flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear flash message
 */
function getAdminFlashMessage() {
    if (isset($_SESSION['admin_flash_message'])) {
        $flash = $_SESSION['admin_flash_message'];
        unset($_SESSION['admin_flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Log admin activity
 */
function logAdminActivity($activity_type, $description, $metadata = null) {
    global $db;
    
    if (!isAdminAuthenticated()) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO admin_activity_log (user_id, action, action_description, metadata) 
                            VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $activity_type,
            $description,
            $metadata ? json_encode($metadata) : null
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Error logging admin activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if current request is AJAX
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 */
function sendJsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Format file size
 */
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log(1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure random password
 */
function generateRandomPassword($length = 12) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $max = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, $max)];
    }
    
    return $password;
}

/**
 * Create URL slug from string
 */
function createSlug($string) {
    $slug = strtolower($string);
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * Paginate results for admin
 */
if (!function_exists('paginateAdmin')) {
    function paginateAdmin($total_items, $current_page, $items_per_page = 20) {
        $total_pages = ceil($total_items / $items_per_page);
        $current_page = max(1, min($total_pages, intval($current_page)));
        $offset = ($current_page - 1) * $items_per_page;
        
        return [
            'total_items' => $total_items,
            'total_pages' => $total_pages,
            'current_page' => $current_page,
            'items_per_page' => $items_per_page,
            'offset' => $offset,
            'has_previous' => $current_page > 1,
            'has_next' => $current_page < $total_pages,
            'previous_page' => $current_page > 1 ? $current_page - 1 : null,
            'next_page' => $current_page < $total_pages ? $current_page + 1 : null
        ];
    }
}

/**
 * Alias for paginate function used in custom orders
 */
if (!function_exists('paginate')) {
    function paginate($total_items, $current_page, $items_per_page = 20) {
        return paginateAdmin($total_items, $current_page, $items_per_page);
    }
}

// Initialize admin session security check for all admin pages
if (basename($_SERVER['SCRIPT_NAME']) !== 'index.php') {
    requireAdminAuth();
}
?>