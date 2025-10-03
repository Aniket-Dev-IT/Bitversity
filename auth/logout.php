<?php
require_once __DIR__ . '/../includes/config.php';

// Only allow logout if user is logged in
if (!isLoggedIn()) {
    redirect(BASE_PATH . '/auth/login.php', 'You are not logged in', 'warning');
}

// Log the logout
$userEmail = $_SESSION['user_email'] ?? 'Unknown';
$userId = $_SESSION['user_id'] ?? 0;
error_log("User logged out: {$userEmail} from IP: {$_SERVER['REMOTE_ADDR']}");

// Clean up remember me tokens if they exist
if ($userId && isset($_COOKIE['remember_token'])) {
    try {
        // Remove all remember tokens for this user
        $stmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Clear the cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    } catch (Exception $e) {
        error_log("Error cleaning up remember tokens: " . $e->getMessage());
    }
}

// Update last logout time in database
if ($userId) {
    try {
        $stmt = $db->prepare("UPDATE users SET last_logout = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Error updating last logout time: " . $e->getMessage());
    }
}

// Clear all session data
$sessionKeys = array_keys($_SESSION);
foreach ($sessionKeys as $key) {
    unset($_SESSION[$key]);
}

// Destroy the session completely
session_destroy();

// Start a new session for flash messages
session_start();
session_regenerate_id(true);

// Clear any other authentication-related cookies
$cookiesToClear = ['user_preferences', 'cart_token', 'session_token'];
foreach ($cookiesToClear as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        setcookie($cookie, '', time() - 3600, '/', '', true, true);
    }
}

// Handle different logout scenarios
$reason = $_GET['reason'] ?? 'normal';
$message = 'You have been successfully logged out.';
$type = 'success';

switch ($reason) {
    case 'timeout':
        $message = 'Your session has expired. Please log in again.';
        $type = 'warning';
        break;
    case 'security':
        $message = 'You have been logged out for security reasons.';
        $type = 'warning';
        break;
    case 'admin':
        $message = 'Your session was terminated by an administrator.';
        $type = 'warning';
        break;
    case 'inactivity':
        $message = 'You have been logged out due to inactivity.';
        $type = 'info';
        break;
    default:
        $message = 'You have been successfully logged out.';
        $type = 'success';
        break;
}

// Redirect to login page with success message
redirect(BASE_PATH . '/auth/login.php?logout=1', $message, $type);
?>
