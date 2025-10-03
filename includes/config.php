<?php
/**
 * Bitversity - Core Configuration File
 * 
 * Central configuration file containing database connections,
 * security settings, helper functions, and application constants.
 * 
 * This file is included by all other PHP files in the application
 * and provides the foundation for the entire system.
 * 
 * @package    Bitversity
 * @author     Development Team
 * @version    2.1.0
 * @since      2024
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application Configuration
define('APP_NAME', 'Bitversity');
define('APP_VERSION', '1.0.0');

// Auto-detect environment and set BASE_PATH accordingly
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$server_port = $_SERVER['SERVER_PORT'] ?? '80';

// Detect if running through Laragon (Apache) or PHP built-in server
if ($server_port == '80' || $server_port == '443') {
    // Laragon/Apache environment
    if (strpos($script_name, '/Bitversity/') !== false) {
        define('BASE_PATH', '/Bitversity');
        define('APP_URL', 'http://localhost/Bitversity');
    } else {
        define('BASE_PATH', '');
        define('APP_URL', 'http://localhost');
    }
} else {
    // PHP built-in server environment
    define('BASE_PATH', '');
    define('APP_URL', 'http://localhost:' . $server_port);
}

// Security Configuration
define('HASH_ALGORITHM', 'sha256');
define('ENCRYPTION_KEY', 'your-secret-key-change-in-production');

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_FILE_TYPES', ['pdf', 'epub', 'zip', 'rar']);

// Pagination
define('ITEMS_PER_PAGE', 12);

// Include database configuration
try {
    require_once __DIR__ . '/../config/database.php';
    // Alias for consistency ($pdo is used throughout the app)
    $pdo = $db;
} catch (Exception $e) {
    // Database not available - set up minimal environment
    $db = null;
    $pdo = null;
    error_log("Database connection failed: " . $e->getMessage());
}

// Include helper functions (only if database is available)
if ($db !== null) {
    try {
        require_once __DIR__ . '/settings.php';
    } catch (Exception $e) {
        error_log("Settings loading failed: " . $e->getMessage());
    }
}

// Development mode
define('DEVELOPMENT_MODE', true); // Set to false in production

/**
 * Get current user information
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    global $db;
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

/**
 * Redirect to login if not authenticated
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_PATH . '/auth/login.php');
        exit;
    }
}

/**
 * Redirect to login if not admin
 */
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        header('Location: ' . BASE_PATH . '/index.php');
        exit;
    }
}

/**
 * Generate random token
 */
if (!function_exists('generateToken')) {
    function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
}

/**
 * Generate CSRF token for forms
 */
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Verify CSRF token
 */
if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * Sanitize input
 */
if (!function_exists('sanitize')) {
    function sanitize($input) {
        // Handle null values to prevent trim() deprecation warning
        if ($input === null) {
            return '';
        }
        return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Format price
 */
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log(1024));
    
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

/**
 * Get asset URL
 */
function asset($path) {
    return BASE_PATH . '/assets/' . ltrim($path, '/');
}

/**
 * Get upload URL
 */
function upload($path) {
    return BASE_PATH . '/uploads/' . ltrim($path, '/');
}

/**
 * Get URL with proper base path
 */
function url($path) {
    return ltrim($path, '/');
}

/**
 * Redirect with message
 */
if (!function_exists('redirect')) {
    function redirect($url, $message = null, $type = 'info') {
        if ($message) {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }
        header('Location: ' . $url);
        exit;
    }
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Pagination helper function
 */
function paginate($current_page, $total_items, $items_per_page = 12) {
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    
    return [
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'total_items' => $total_items,
        'items_per_page' => $items_per_page,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages,
        'prev_page' => $current_page > 1 ? $current_page - 1 : null,
        'next_page' => $current_page < $total_pages ? $current_page + 1 : null
    ];
}


/**
 * Time ago helper function
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'Just now';
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($time < 31104000) {
        $months = floor($time / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($time / 31104000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

/**
 * Log user activity
 */
function logActivity($userId, $action, $resourceType = null, $resourceId = null, $details = []) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, resource_type, resource_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $detailsJson = is_array($details) ? json_encode($details) : $details;
        
        $stmt->execute([
            $userId,
            $action,
            $resourceType,
            $resourceId,
            $detailsJson,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}
?>
