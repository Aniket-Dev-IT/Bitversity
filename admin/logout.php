<?php
/**
 * Admin Logout
 * 
 * Securely logs out admin users, cleans up sessions and tokens,
 * and redirects to login page with appropriate messages.
 */

require_once 'includes/auth.php';

// Handle logout process
if (isAdminAuthenticated()) {
    // Log the logout activity before destroying session
    logAdminActivity('admin_logout', 'Admin logged out successfully');
    
    // Destroy admin session and cleanup
    destroyAdminSession();
    
    // Set success message for login page
    $_SESSION['flash_message'] = [
        'message' => 'You have been successfully logged out.',
        'type' => 'success'
    ];
} else {
    // User was not authenticated
    $_SESSION['flash_message'] = [
        'message' => 'You are already logged out.',
        'type' => 'info'
    ];
}

// Redirect to admin login
header('Location: index.php');
exit();
?>