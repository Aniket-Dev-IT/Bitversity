<?php
/**
 * Session Management Utilities
 * Bitversity - Digital Learning Platform
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Login user
 */
function loginUser($userId, $userData = null) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $userData['role'] ?? 'user';
    $_SESSION['username'] = $userData['username'] ?? '';
    $_SESSION['email'] = $userData['email'] ?? '';
    session_regenerate_id(true);
}

/**
 * Logout user
 */
function logoutUser() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
    session_start();
}


/**
 * Set flash message
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}


/**
 * Check if user owns resource
 */
function checkOwnership($userId, $resourceUserId) {
    return $userId == $resourceUserId || isAdmin();
}

/**
 * Get CSRF token
 */
function getCsrfToken() {
    return $_SESSION['csrf_token'] ?? '';
}

?>
