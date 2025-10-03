<?php
/**
 * Admin User Management
 * 
 * Comprehensive interface for managing users, roles, permissions, 
 * account status with advanced filtering and user actions.
 */

require_once 'includes/auth.php';

$current_admin = getCurrentAdmin();
$flash_message = getAdminFlashMessage();

// Get filters and pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$verified_filter = $_GET['verified'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        setAdminFlashMessage('Invalid security token. Please try again.', 'error');
        header('Location: users.php');
        exit();
    }
    
    try {
        switch ($action) {
            case 'toggle_status':
                $user_id = intval($_POST['user_id'] ?? 0);
                $new_status = $_POST['new_status'] === '1' ? 1 : 0;
                
                if ($user_id === intval($current_admin['id'])) {
                    setAdminFlashMessage('You cannot deactivate your own account.', 'error');
                    break;
                }
                
                if ($user_id <= 0) {
                    setAdminFlashMessage('Invalid user ID provided.', 'error');
                    break;
                }
                
                $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                $result = $stmt->execute([$new_status, $user_id]);
                $affected_rows = $stmt->rowCount();
                
                if ($result && $affected_rows > 0) {
                    $status_text = $new_status ? 'activated' : 'deactivated';
                    logAdminActivity('user_status_change', "User account {$status_text}", [
                        'user_id' => $user_id,
                        'new_status' => $new_status
                    ]);
                    
                    setAdminFlashMessage("User account {$status_text} successfully.", 'success');
                } else {
                    setAdminFlashMessage("Failed to update user status. User may not exist. (Affected rows: {$affected_rows})", 'error');
                }
                break;
                
            case 'change_role':
                $user_id = intval($_POST['user_id'] ?? 0);
                $new_role = $_POST['new_role'] ?? '';
                
                if (!in_array($new_role, ['user', 'admin'])) {
                    setAdminFlashMessage('Invalid role specified.', 'error');
                    break;
                }
                
                if ($user_id === intval($current_admin['id'])) {
                    setAdminFlashMessage('You cannot change your own role.', 'error');
                    break;
                }
                
                $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);
                
                logAdminActivity('user_role_change', "User role changed to {$new_role}", [
                    'user_id' => $user_id,
                    'new_role' => $new_role
                ]);
                
                setAdminFlashMessage("User role changed to {$new_role} successfully.", 'success');
                break;
                
            case 'verify_email':
                $user_id = intval($_POST['user_id'] ?? 0);
                
                $stmt = $db->prepare("UPDATE users SET email_verified = 1, email_verified_at = NOW(), email_verification_token = NULL WHERE id = ?");
                $stmt->execute([$user_id]);
                
                logAdminActivity('user_email_verified', "User email manually verified", [
                    'user_id' => $user_id
                ]);
                
                setAdminFlashMessage('User email verified successfully.', 'success');
                break;
                
            case 'change_password':
                $user_id = intval($_POST['user_id'] ?? 0);
                $new_password = trim($_POST['new_password'] ?? '');
                $password_type = $_POST['password_type'] ?? 'custom';
                
                if (empty($new_password)) {
                    setAdminFlashMessage('Password cannot be empty.', 'error');
                    break;
                }
                
                if (strlen($new_password) < 6) {
                    setAdminFlashMessage('Password must be at least 6 characters long.', 'error');
                    break;
                }
                
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET password = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                // Get user details
                $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name, email FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $_SESSION['temp_password'] = [
                        'user_name' => $user['full_name'],
                        'user_email' => $user['email'],
                        'password' => $new_password,
                        'type' => $password_type
                    ];
                }
                
                logAdminActivity('user_password_change', "User password changed by admin", [
                    'user_id' => $user_id,
                    'password_type' => $password_type
                ]);
                
                $action_text = $password_type === 'generated' ? 'generated and set' : 'changed';
                setAdminFlashMessage("Password {$action_text} successfully. New password is displayed below.", 'success');
                break;
                
            case 'reset_password':
                $user_id = intval($_POST['user_id'] ?? 0);
                
                // Generate new random password
                $new_password = generateRandomPassword(12);
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET password = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                // Get user details for notification
                $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name, email FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // In a real application, you would send this via email
                    $_SESSION['temp_password'] = [
                        'user_name' => $user['full_name'],
                        'user_email' => $user['email'],
                        'password' => $new_password,
                        'type' => 'generated'
                    ];
                }
                
                logAdminActivity('user_password_reset', "User password reset by admin", [
                    'user_id' => $user_id
                ]);
                
                setAdminFlashMessage('Password reset successfully. New password is displayed below.', 'success');
                break;
                
            case 'unlock_account':
                $user_id = intval($_POST['user_id'] ?? 0);
                
                $stmt = $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
                $stmt->execute([$user_id]);
                
                logAdminActivity('user_account_unlocked', "User account unlocked", [
                    'user_id' => $user_id
                ]);
                
                setAdminFlashMessage('User account unlocked successfully.', 'success');
                break;
                
            case 'delete_user':
                $user_id = intval($_POST['user_id'] ?? 0);
                
                if ($user_id === intval($current_admin['id'])) {
                    setAdminFlashMessage('You cannot delete your own account.', 'error');
                    break;
                }
                
                if ($user_id <= 0) {
                    setAdminFlashMessage('Invalid user ID provided.', 'error');
                    break;
                }
                
                // Check if user exists first
                $stmt = $db->prepare("SELECT id, CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user_to_delete) {
                    setAdminFlashMessage('User not found.', 'error');
                    break;
                }
                
                // Delete user and related data
                $db->beginTransaction();
                
                try {
                    // Delete related data first (only tables that exist)
                    $related_tables = ['remember_tokens', 'orders', 'cart', 'wishlist', 'reviews'];
                    
                    foreach ($related_tables as $table) {
                        try {
                            $stmt = $db->prepare("DELETE FROM {$table} WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                        } catch (PDOException $e) {
                            // Table might not exist, continue
                            error_log("Table {$table} deletion failed: " . $e->getMessage());
                        }
                    }
                    
                    // Finally delete the user
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $result = $stmt->execute([$user_id]);
                    $affected_rows = $stmt->rowCount();
                    
                    if ($result && $affected_rows > 0) {
                        $db->commit();
                        
                        logAdminActivity('user_deleted', "User account deleted: {$user_to_delete['name']}", [
                            'user_id' => $user_id
                        ]);
                        
                        setAdminFlashMessage('User account deleted successfully.', 'success');
                    } else {
                        $db->rollBack();
                        setAdminFlashMessage("Failed to delete user. (Affected rows: {$affected_rows})", 'error');
                    }
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("User deletion error: " . $e->getMessage());
                    setAdminFlashMessage('Error occurred while deleting user: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'bulk_action':
                $bulk_action = $_POST['bulk_action'] ?? '';
                $selected_users = $_POST['selected_users'] ?? [];
                
                // Remove current admin from selected users for safety
                $selected_users = array_filter($selected_users, function($id) use ($current_admin) {
                    return intval($id) !== intval($current_admin['id']);
                });
                
                if ($bulk_action && !empty($selected_users)) {
                    $placeholders = str_repeat('?,', count($selected_users) - 1) . '?';
                    
                    switch ($bulk_action) {
                        case 'activate':
                            $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id IN ({$placeholders})");
                            $stmt->execute($selected_users);
                            setAdminFlashMessage(count($selected_users) . ' users activated successfully.', 'success');
                            break;
                            
                        case 'deactivate':
                            $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id IN ({$placeholders})");
                            $stmt->execute($selected_users);
                            setAdminFlashMessage(count($selected_users) . ' users deactivated successfully.', 'success');
                            break;
                            
                        case 'verify_emails':
                            $stmt = $db->prepare("UPDATE users SET email_verified = 1, email_verified_at = NOW(), email_verification_token = NULL WHERE id IN ({$placeholders})");
                            $stmt->execute($selected_users);
                            setAdminFlashMessage(count($selected_users) . ' user emails verified successfully.', 'success');
                            break;
                            
                        case 'unlock_accounts':
                            $stmt = $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id IN ({$placeholders})");
                            $stmt->execute($selected_users);
                            setAdminFlashMessage(count($selected_users) . ' user accounts unlocked successfully.', 'success');
                            break;
                    }
                    
                    logAdminActivity('bulk_user_action', "Bulk action '{$bulk_action}' performed on users", [
                        'action' => $bulk_action,
                        'users_count' => count($selected_users)
                    ]);
                }
                break;
        }
        
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("User management error: " . $e->getMessage());
        setAdminFlashMessage('An error occurred. Please try again.', 'error');
    }
    
    header('Location: users.php' . 
           ($search ? '?search=' . urlencode($search) : '') . 
           ($role_filter ? ($search ? '&' : '?') . 'role=' . urlencode($role_filter) : '') .
           ($status_filter !== '' ? (($search || $role_filter) ? '&' : '?') . 'status=' . urlencode($status_filter) : '') .
           ($verified_filter !== '' ? (($search || $role_filter || $status_filter !== '') ? '&' : '?') . 'verified=' . urlencode($verified_filter) : '') .
           (($search || $role_filter || $status_filter !== '' || $verified_filter !== '') ? '&page=' : '?page=') . $page);
    exit();
}

try {
    // Build query conditions
    $where_conditions = ['1=1'];
    $params = [];
    
    // Search functionality
    if ($search) {
        $where_conditions[] = "(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ? OR email LIKE ? OR username LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    // Role filter
    if ($role_filter) {
        $where_conditions[] = "role = ?";
        $params[] = $role_filter;
    }
    
    // Status filter
    if ($status_filter !== '') {
        $where_conditions[] = "is_active = ?";
        $params[] = intval($status_filter);
    }
    
    // Email verified filter
    if ($verified_filter !== '') {
        $where_conditions[] = "email_verified = ?";
        $params[] = intval($verified_filter);
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Count total users for pagination
    $count_sql = "SELECT COUNT(*) FROM users WHERE {$where_clause}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    
    // Calculate pagination
    $pagination = paginateAdmin($total_users, $page, $per_page);
    
    // Get users
    $sql = "SELECT *, 
               CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name,
               CASE WHEN locked_until IS NOT NULL AND locked_until > NOW() THEN 1 ELSE 0 END as is_locked
            FROM users 
            WHERE {$where_clause} 
            ORDER BY created_at DESC 
            LIMIT {$per_page} OFFSET {$pagination['offset']}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user statistics
    $stats = $db->query("SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users,
        SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified_users,
        SUM(CASE WHEN email_verified = 0 THEN 1 ELSE 0 END) as unverified_users,
        SUM(CASE WHEN locked_until IS NOT NULL AND locked_until > NOW() THEN 1 ELSE 0 END) as locked_users,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_users_7d,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d
        FROM users")->fetch(PDO::FETCH_ASSOC);
        
} catch (PDOException $e) {
    error_log("User fetch error: " . $e->getMessage());
    $users = [];
    $total_users = 0;
    $pagination = paginateAdmin(0, 1, $per_page);
    $stats = [];
}

// Check for temporary password to display
$temp_password_info = $_SESSION['temp_password'] ?? null;
if ($temp_password_info) {
    unset($_SESSION['temp_password']);
}

require_once 'includes/layout.php';
renderAdminHeader('User Management', 'Manage users, roles, and permissions');
?>

<style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .users-table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin: 0;
        }

        .table-header {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .search-filters {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input {
            width: 280px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .search-input:focus {
            border-color: #6366f1;
            outline: none;
        }

        .filter-select {
            min-width: 140px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.5rem;
            font-size: 0.9rem;
        }

        .users-table {
            width: 100%;
            margin: 0;
            font-size: 0.9rem;
        }

        .users-table th {
            background: #f8fafc;
            color: #1f2937;
            font-weight: 600;
            padding: 0.8rem;
            border: none;
            font-size: 0.85rem;
        }

        .users-table td {
            padding: 0.8rem;
            border-top: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .status-badge {
            padding: 0.25rem 0.7rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .role-badge {
            padding: 0.25rem 0.7rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .role-admin {
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
        }

        .role-user {
            background: rgba(107, 114, 128, 0.1);
            color: #374151;
        }

        .action-btn {
            padding: 0.35rem 0.7rem;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            margin: 0 0.15rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #3b82f6;
            color: white;
        }

        .btn-toggle {
            background: #f59e0b;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .verified-badge {
            color: #10b981;
        }

        .unverified-badge {
            color: #ef4444;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #6366f1;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }

        .pagination-container {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .temp-password-alert {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 0.8rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                margin-bottom: 1.5rem;
            }
            
            .table-header {
                padding: 1rem;
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            
            .search-filters {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }
            
            .search-input, .filter-select {
                width: 100%;
                min-width: auto;
            }
            
            .users-table th,
            .users-table td {
                padding: 0.6rem 0.4rem;
                font-size: 0.8rem;
            }
            
            
            .user-avatar {
                width: 28px;
                height: 28px;
                font-size: 0.7rem;
                margin-right: 0;
                flex-shrink: 0;
            }
            
            .action-btn {
                padding: 0.3rem 0.5rem;
                font-size: 0.7rem;
                margin: 0 0.1rem;
            }
            
            .status-badge, .role-badge {
                padding: 0.2rem 0.5rem;
                font-size: 0.7rem;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.6rem;
            }
            
            .users-table-container {
                margin: 0 -15px;
                border-radius: 0;
                box-shadow: none;
                border-top: 1px solid #e5e7eb;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .table-header {
                padding: 0.8rem 1rem;
            }
            
            .users-table {
                font-size: 0.75rem;
            }
            
            .users-table th,
            .users-table td {
                padding: 0.5rem 0.3rem;
            }
            
            /* Hide some columns on very small screens */
            .users-table th:nth-child(6),
            .users-table td:nth-child(6),
            .users-table th:nth-child(7),
            .users-table td:nth-child(7) {
                display: none;
            }
            
            .pagination-container {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>

<?php if ($flash_message): ?>
    <div class="alert alert-<?php echo $flash_message['type'] === 'error' ? 'danger' : $flash_message['type']; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($flash_message['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($temp_password_info): ?>
    <div class="container-fluid">
        <div class="temp-password-alert">
            <?php 
            $password_action = 'set';
            $icon = 'fas fa-key';
            if (isset($temp_password_info['type'])) {
                $password_action = $temp_password_info['type'] === 'generated' ? 'generated and set' : 'changed';
                $icon = $temp_password_info['type'] === 'generated' ? 'fas fa-dice' : 'fas fa-key';
            }
            ?>
            <h5><i class="<?php echo $icon; ?> me-2"></i>Password Successfully <?php echo ucfirst($password_action); ?>!</h5>
            <p class="mb-2">
                New password for <strong><?php echo htmlspecialchars($temp_password_info['user_name']); ?></strong> 
                (<?php echo htmlspecialchars($temp_password_info['user_email']); ?>):
            </p>
            <div class="d-flex align-items-center gap-2 mb-3">
                <code class="bg-white bg-opacity-20 px-3 py-2 rounded flex-grow-1"><?php echo htmlspecialchars($temp_password_info['password']); ?></code>
                <button class="btn btn-light btn-sm" onclick="copyToClipboard('<?php echo htmlspecialchars($temp_password_info['password']); ?>')">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <small class="d-block"><i class="fas fa-shield-alt me-1"></i><strong>Security:</strong> Share this password securely with the user.</small>
                </div>
                <div class="col-md-6">
                    <small class="d-block"><i class="fas fa-clock me-1"></i><strong>Action:</strong> Password was <?php echo $password_action; ?> by admin.</small>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="container-fluid px-3">
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--primary-color);"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--success-color);"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--warning-color);"><?php echo number_format($stats['admin_count'] ?? 0); ?></div>
                    <div class="stat-label">Administrators</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--info-color);"><?php echo number_format($stats['verified_users'] ?? 0); ?></div>
                    <div class="stat-label">Verified Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--danger-color);"><?php echo number_format($stats['locked_users'] ?? 0); ?></div>
                    <div class="stat-label">Locked Accounts</div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="users-table-container">
                <div class="table-header">
                    <div class="search-filters">
                        <form method="GET" class="d-flex gap-3 align-items-center">
                            <input type="text" 
                                   name="search" 
                                   class="search-input" 
                                   placeholder="Search users..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            
                            <select name="role" class="filter-select">
                                <option value="">All Roles</option>
                                <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            
                            <select name="status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            
                            <select name="verified" class="filter-select">
                                <option value="">All Verified</option>
                                <option value="1" <?php echo $verified_filter === '1' ? 'selected' : ''; ?>>Verified</option>
                                <option value="0" <?php echo $verified_filter === '0' ? 'selected' : ''; ?>>Unverified</option>
                            </select>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            
                            <a href="users.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </form>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="user_form.php" class="btn btn-success">
                            <i class="fas fa-user-plus me-2"></i>Add User
                        </a>
                    </div>
                </div>

                <form method="POST" id="bulkForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="bulk_action">
                    
                    <div class="px-3 py-2 border-bottom d-flex align-items-center gap-3">
                        <select name="bulk_action" class="form-select" style="width: 200px;">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="verify_emails">Verify Emails</option>
                            <option value="unlock_accounts">Unlock Accounts</option>
                        </select>
                        <button type="submit" class="btn btn-outline-primary btn-sm">Apply</button>
                    </div>

                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table users-table">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Email Verified</th>
                                    <th>Last Login</th>
                                    <th>Joined</th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" 
                                                   class="user-checkbox" <?php echo $user['id'] == $current_admin['id'] ? 'disabled' : ''; ?>>
                                        </td>
                                        
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <div class="fw-semibold text-nowrap">
                                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                                        <?php if ($user['id'] == $current_admin['id']): ?>
                                                            <small class="badge bg-warning text-dark ms-1">You</small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted text-truncate" style="max-width: 180px;"><?php echo htmlspecialchars($user['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td>
                                            <span class="role-badge <?php echo $user['role'] === 'admin' ? 'role-admin' : 'role-user'; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        
                                        <td>
                                            <?php if ($user['is_locked']): ?>
                                                <span class="status-badge" style="background: rgba(239, 68, 68, 0.1); color: #dc2626;">Locked</span>
                                            <?php else: ?>
                                                <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <?php if ($user['email_verified']): ?>
                                                <i class="fas fa-check-circle text-success"></i> Verified
                                            <?php else: ?>
                                                <i class="fas fa-times-circle text-danger"></i> Unverified
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <?php if ($user['last_login']): ?>
                                                <small><?php echo date('M j, Y g:i A', strtotime($user['last_login'])); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Never</small>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                                        </td>
                                        
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <?php if ($user['id'] != $current_admin['id']): ?>
                                                    <!-- Toggle Status -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="new_status" value="<?php echo $user['is_active'] ? '0' : '1'; ?>">
                                                        <button type="submit" class="action-btn btn-toggle" 
                                                                title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> User">
                                                            <i class="fas fa-<?php echo $user['is_active'] ? 'user-slash' : 'user-check'; ?>"></i>
                                                        </button>
                                                    </form>

                                                    <!-- Change Role -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="change_role">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="new_role" value="<?php echo $user['role'] === 'admin' ? 'user' : 'admin'; ?>">
                                                        <button type="submit" class="action-btn btn-edit" 
                                                                title="Change to <?php echo $user['role'] === 'admin' ? 'User' : 'Admin'; ?>"
                                                                onclick="return confirm('Are you sure you want to change this user\\'s role?');">
                                                            <i class="fas fa-user-cog"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if (!$user['email_verified']): ?>
                                                    <!-- Verify Email -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="verify_email">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="action-btn btn-success" title="Verify Email">
                                                            <i class="fas fa-envelope-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($user['is_locked']): ?>
                                                    <!-- Unlock Account -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="unlock_account">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="action-btn btn-success" title="Unlock Account">
                                                            <i class="fas fa-unlock"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Change Password -->
                                                <button type="button" class="action-btn btn-toggle" 
                                                        title="Change Password" 
                                                        onclick="openPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>', '<?php echo htmlspecialchars($user['email']); ?>')">
                                                    <i class="fas fa-key"></i>
                                                </button>

                                                <?php if ($user['id'] != $current_admin['id']): ?>
                                                    <!-- Delete User -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="action-btn btn-danger" title="Delete User"
                                                                onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No users found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination-container">
                        <div>
                            Showing <?php echo number_format($pagination['offset'] + 1); ?> to 
                            <?php echo number_format(min($pagination['offset'] + $per_page, $total_users)); ?> of 
                            <?php echo number_format($total_users); ?> users
                        </div>
                        <nav>
                            <ul class="pagination mb-0">
                                <?php if ($pagination['has_previous']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $pagination['previous_page']; ?><?php 
                                            echo $search ? '&search=' . urlencode($search) : '';
                                            echo $role_filter ? '&role=' . urlencode($role_filter) : '';
                                            echo $status_filter !== '' ? '&status=' . urlencode($status_filter) : '';
                                            echo $verified_filter !== '' ? '&verified=' . urlencode($verified_filter) : '';
                                        ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                            echo $search ? '&search=' . urlencode($search) : '';
                                            echo $role_filter ? '&role=' . urlencode($role_filter) : '';
                                            echo $status_filter !== '' ? '&status=' . urlencode($status_filter) : '';
                                            echo $verified_filter !== '' ? '&verified=' . urlencode($verified_filter) : '';
                                        ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($pagination['has_next']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $pagination['next_page']; ?><?php 
                                            echo $search ? '&search=' . urlencode($search) : '';
                                            echo $role_filter ? '&role=' . urlencode($role_filter) : '';
                                            echo $status_filter !== '' ? '&status=' . urlencode($status_filter) : '';
                                            echo $verified_filter !== '' ? '&verified=' . urlencode($verified_filter) : '';
                                        ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
</div>

<!-- Password Management Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordModalLabel">
                    <i class="fas fa-key me-2"></i>Change User Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="passwordForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="user_id" id="passwordUserId">
                    <input type="hidden" name="password_type" id="passwordType" value="custom">
                    
                    <div class="mb-3">
                        <h6>Changing password for:</h6>
                        <div class="p-3 bg-light rounded">
                            <div class="fw-bold" id="passwordUserName"></div>
                            <div class="text-muted small" id="passwordUserEmail"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="newPassword" name="new_password" 
                                   placeholder="Enter new password" required 
                                   oninput="checkPasswordStrength()">
                            <button type="button" class="btn btn-outline-secondary" 
                                    onclick="generateRandomPassword()" title="Generate Random Password">
                                <i class="fas fa-dice"></i>
                            </button>
                        </div>
                        <div id="passwordStrength" class="small mt-1"></div>
                    </div>
                    
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Security Note:</strong> For security reasons, current passwords cannot be displayed. 
                        The new password will be shown after saving so you can share it with the user.
                    </div>
                    
                    <div class="alert alert-warning small">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> The user will need to login with this new password. 
                        Make sure to communicate it to them securely.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script>
        // Select all checkbox functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox:not([disabled])');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.user-checkbox:not([disabled])');
                const checkedCheckboxes = document.querySelectorAll('.user-checkbox:not([disabled]):checked');
                const selectAll = document.getElementById('selectAll');
                
                selectAll.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
                selectAll.checked = checkedCheckboxes.length === allCheckboxes.length;
            });
        });

        // Bulk actions form validation
        document.getElementById('bulkForm').addEventListener('submit', function(e) {
            const selectedUsers = document.querySelectorAll('.user-checkbox:checked');
            const bulkAction = document.querySelector('[name="bulk_action"]').value;
            
            if (!bulkAction) {
                e.preventDefault();
                alert('Please select a bulk action.');
                return;
            }
            
            if (selectedUsers.length === 0) {
                e.preventDefault();
                alert('Please select at least one user.');
                return;
            }
            
            const actionText = bulkAction.replace('_', ' ');
            if (!confirm(`Are you sure you want to ${actionText} ${selectedUsers.length} selected user(s)?`)) {
                e.preventDefault();
                return;
            }
        });

        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Password copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
        
        // Password modal functions
        function openPasswordModal(userId, userName, userEmail) {
            document.getElementById('passwordUserId').value = userId;
            document.getElementById('passwordUserName').textContent = userName;
            document.getElementById('passwordUserEmail').textContent = userEmail;
            document.getElementById('newPassword').value = '';
            document.getElementById('passwordStrength').textContent = '';
            document.getElementById('passwordType').value = 'custom';
            
            const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
            modal.show();
        }
        
        function generateRandomPassword() {
            const length = 12;
            const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            let password = '';
            for (let i = 0; i < length; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            document.getElementById('newPassword').value = password;
            document.getElementById('passwordType').value = 'generated';
            checkPasswordStrength();
        }
        
        function checkPasswordStrength() {
            const password = document.getElementById('newPassword').value;
            const strengthElement = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthElement.textContent = '';
                strengthElement.className = '';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 8) {
                strength++;
            } else {
                feedback.push('At least 8 characters');
            }
            
            if (/[a-z]/.test(password)) {
                strength++;
            } else {
                feedback.push('Lowercase letter');
            }
            
            if (/[A-Z]/.test(password)) {
                strength++;
            } else {
                feedback.push('Uppercase letter');
            }
            
            if (/\d/.test(password)) {
                strength++;
            } else {
                feedback.push('Number');
            }
            
            if (/[!@#$%^&*]/.test(password)) {
                strength++;
            } else {
                feedback.push('Special character (!@#$%^&*)');
            }
            
            let strengthText = '';
            let strengthClass = '';
            
            if (strength >= 4) {
                strengthText = 'Strong password';
                strengthClass = 'text-success';
            } else if (strength >= 3) {
                strengthText = 'Good password';
                strengthClass = 'text-warning';
            } else if (strength >= 2) {
                strengthText = 'Weak password';
                strengthClass = 'text-warning';
            } else {
                strengthText = 'Very weak password';
                strengthClass = 'text-danger';
            }
            
            if (feedback.length > 0 && strength < 4) {
                strengthText += ' (Missing: ' + feedback.join(', ') + ')';
            }
            
            strengthElement.textContent = strengthText;
            strengthElement.className = strengthClass;
        }
    </script>

<?php renderAdminFooter(); ?>
