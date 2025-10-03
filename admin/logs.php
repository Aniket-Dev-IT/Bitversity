<?php
/**
 * Admin Activity Logs
 * 
 * Display system activity logs for monitoring user actions,
 * admin activities, and system events.
 */

require_once 'includes/auth.php';

$current_admin = getCurrentAdmin();
$flash_message = getAdminFlashMessage();

// Get filters and pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$search = trim($_GET['search'] ?? '');
$user_filter = $_GET['user_id'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_filter = $_GET['date'] ?? '';

try {
    // Build query conditions
    $where_conditions = ['1=1'];
    $params = [];
    
    // Search filter
    if ($search) {
        $where_conditions[] = "(description LIKE ? OR action LIKE ? OR details LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // User filter
    if ($user_filter) {
        $where_conditions[] = "user_id = ?";
        $params[] = $user_filter;
    }
    
    // Action filter
    if ($action_filter) {
        $where_conditions[] = "action = ?";
        $params[] = $action_filter;
    }
    
    // Date filter
    if ($date_filter) {
        $where_conditions[] = "DATE(created_at) = ?";
        $params[] = $date_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Count total logs for pagination
    $count_sql = "SELECT COUNT(*) FROM activity_logs WHERE {$where_clause}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_logs = $stmt->fetchColumn();
    
    // Calculate pagination
    $pagination = paginate($total_logs, $page, $per_page);
    
    // Get activity logs with user information
    $sql = "SELECT al.*, 
               CONCAT(u.first_name, ' ', u.last_name) as user_name,
               u.email as user_email
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE {$where_clause} 
            ORDER BY al.created_at DESC 
            LIMIT {$per_page} OFFSET {$pagination['offset']}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique actions for filter dropdown
    $actions_stmt = $db->query("SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL ORDER BY action");
    $unique_actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get users for filter dropdown
    $users_stmt = $db->query("SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as name 
                              FROM users u 
                              INNER JOIN activity_logs al ON u.id = al.user_id 
                              ORDER BY name");
    $users_for_filter = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Logs fetch error: " . $e->getMessage());
    $logs = [];
    $total_logs = 0;
    $pagination = paginate(0, 1, $per_page);
    $unique_actions = [];
    $users_for_filter = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Bitversity Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #e5e7eb;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --sidebar-width: 280px;
        }

        body {
            background-color: var(--light-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid var(--secondary-color);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            overflow-y: auto;
        }

        .admin-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .admin-header {
            background: white;
            border-bottom: 1px solid var(--secondary-color);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid var(--secondary-color);
            text-align: center;
        }

        .sidebar-brand h4 {
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .sidebar-nav .nav-link {
            color: #6b7280;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.1);
        }

        .sidebar-nav .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .logs-table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--secondary-color);
            display: flex;
            justify-content: between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .search-filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input {
            width: 300px;
            border: 2px solid var(--secondary-color);
            border-radius: 8px;
            padding: 0.5rem 1rem;
        }

        .search-input:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .filter-select {
            min-width: 150px;
            border: 2px solid var(--secondary-color);
            border-radius: 8px;
            padding: 0.5rem;
        }

        .logs-table {
            width: 100%;
            margin: 0;
        }

        .logs-table th {
            background: var(--light-color);
            color: var(--dark-color);
            font-weight: 600;
            padding: 1rem;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .logs-table td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .action-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .action-login {
            background-color: rgba(34, 197, 94, 0.1);
            color: #059669;
        }

        .action-logout {
            background-color: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .action-create {
            background-color: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }

        .action-update {
            background-color: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .action-delete {
            background-color: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .log-details {
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .pagination-container {
            padding: 1.5rem;
            border-top: 1px solid var(--secondary-color);
            background: var(--light-color);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div class="sidebar-brand">
            <h4>Bitversity</h4>
            <small class="text-muted">Admin Panel</small>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard
            </a>
            <a href="users.php" class="nav-link">
                <i class="fas fa-users"></i>
                Users
            </a>
            <a href="books.php" class="nav-link">
                <i class="fas fa-book"></i>
                Books
            </a>
            <a href="projects.php" class="nav-link">
                <i class="fas fa-code"></i>
                Projects
            </a>
            <a href="games.php" class="nav-link">
                <i class="fas fa-gamepad"></i>
                Games
            </a>
            <a href="orders.php" class="nav-link">
                <i class="fas fa-shopping-cart"></i>
                Orders
            </a>
            <a href="analytics.php" class="nav-link">
                <i class="fas fa-chart-line"></i>
                Analytics
            </a>
            <a href="logs.php" class="nav-link active">
                <i class="fas fa-clipboard-list"></i>
                Activity Logs
            </a>
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                Settings
            </a>
            
            <hr>
            
            <a href="../index.php" class="nav-link">
                <i class="fas fa-external-link-alt"></i>
                View Site
            </a>
            <a href="logout.php" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-main">
        <!-- Header -->
        <div class="admin-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">Activity Logs</h2>
                    <small class="text-muted">Monitor system activity and user actions</small>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($current_admin['full_name']); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($current_admin['full_name']); ?>&background=6366f1&color=ffffff&size=32" 
                         class="rounded-circle" width="32" height="32" alt="Admin">
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="container-fluid p-4">
            <?php if ($flash_message): ?>
                <div class="alert alert-<?php echo $flash_message['type'] === 'error' ? 'danger' : $flash_message['type']; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flash_message['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Activity Logs Table -->
            <div class="logs-table-container">
                <div class="table-header">
                    <div class="search-filters">
                        <form method="GET" class="d-flex gap-3 align-items-center">
                            <input type="text" 
                                   name="search" 
                                   class="search-input" 
                                   placeholder="Search logs..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            
                            <select name="user_id" class="filter-select">
                                <option value="">All Users</option>
                                <?php foreach ($users_for_filter as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $user_filter === $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select name="action" class="filter-select">
                                <option value="">All Actions</option>
                                <?php foreach ($unique_actions as $action): ?>
                                    <option value="<?php echo $action; ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <input type="date" 
                                   name="date" 
                                   class="filter-select" 
                                   value="<?php echo htmlspecialchars($date_filter); ?>">
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            
                            <a href="logs.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </form>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                    <table class="table logs-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                                        <p>No activity logs found.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">
                                                <?php echo date('M j, Y', strtotime($log['created_at'])); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('g:i A', strtotime($log['created_at'])); ?>
                                            </small>
                                        </td>
                                        
                                        <td>
                                            <?php if ($log['user_name']): ?>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($log['user_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($log['user_email']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">System</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <?php
                                            $action_class = 'action-badge ';
                                            if (strpos($log['action'], 'login') !== false) {
                                                $action_class .= 'action-login';
                                            } elseif (strpos($log['action'], 'logout') !== false) {
                                                $action_class .= 'action-logout';
                                            } elseif (strpos($log['action'], 'create') !== false) {
                                                $action_class .= 'action-create';
                                            } elseif (strpos($log['action'], 'update') !== false || strpos($log['action'], 'edit') !== false) {
                                                $action_class .= 'action-update';
                                            } elseif (strpos($log['action'], 'delete') !== false) {
                                                $action_class .= 'action-delete';
                                            } else {
                                                $action_class .= 'action-create';
                                            }
                                            ?>
                                            <span class="<?php echo $action_class; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                            </span>
                                        </td>
                                        
                                        <td>
                                            <div><?php echo htmlspecialchars($log['description']); ?></div>
                                            <?php if ($log['activity_type']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($log['activity_type']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <small class="font-monospace">
                                                <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                            </small>
                                        </td>
                                        
                                        <td>
                                            <?php if ($log['details']): ?>
                                                <?php 
                                                $details = json_decode($log['details'], true);
                                                if ($details && is_array($details)) {
                                                    foreach ($details as $key => $value) {
                                                        echo '<small class="log-details">';
                                                        echo '<strong>' . htmlspecialchars($key) . ':</strong> ';
                                                        echo htmlspecialchars($value);
                                                        echo '</small><br>';
                                                    }
                                                } else {
                                                    echo '<small class="log-details">' . htmlspecialchars($log['details']) . '</small>';
                                                }
                                                ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination-container">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                Showing <?php echo number_format(($pagination['current_page'] - 1) * $per_page + 1); ?> 
                                to <?php echo number_format(min($pagination['current_page'] * $per_page, $total_logs)); ?> 
                                of <?php echo number_format($total_logs); ?> logs
                            </div>
                            
                            <nav>
                                <ul class="pagination mb-0">
                                    <?php if ($pagination['has_previous']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $pagination['previous_page']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $user_filter ? '&user_id=' . $user_filter : ''; ?><?php echo $action_filter ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $date_filter ? '&date=' . $date_filter : ''; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $pagination['current_page'] - 2);
                                    $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
                                    ?>
                                    
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $user_filter ? '&user_id=' . $user_filter : ''; ?><?php echo $action_filter ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $date_filter ? '&date=' . $date_filter : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($pagination['has_next']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $pagination['next_page']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $user_filter ? '&user_id=' . $user_filter : ''; ?><?php echo $action_filter ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $date_filter ? '&date=' . $date_filter : ''; ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>