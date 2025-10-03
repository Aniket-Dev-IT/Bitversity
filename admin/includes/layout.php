<?php
// Admin Layout Template - Use this for all admin pages

// Ensure admin authentication
if (!function_exists('getCurrentAdmin')) {
    require_once __DIR__ . '/auth.php';
}

$current_admin = getCurrentAdmin();
$flash_message = getAdminFlashMessage();

// Get page-specific variables if not set
$page_title = $page_title ?? 'Admin Panel';
$page_description = $page_description ?? 'Bitversity Administration';

function renderAdminHeader($title, $description = '') {
    global $current_admin;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - Bitversity Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            :root {
                --primary-color: #6366f1;
                --primary-dark: #4338ca;
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
                margin: 0;
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
                text-decoration: none;
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

            .admin-content {
                padding: 2rem;
            }

            .btn-primary {
                background-color: var(--primary-color);
                border-color: var(--primary-color);
            }

            .btn-primary:hover {
                background-color: var(--primary-dark);
                border-color: var(--primary-dark);
            }

            .text-primary {
                color: var(--primary-color) !important;
            }

            .bg-primary {
                background-color: var(--primary-color) !important;
            }

            .alert-dismissible .btn-close {
                padding: 0.75rem 0.75rem;
            }

            .card {
                border: none;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            }

            .table {
                margin-bottom: 0;
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
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    Users
                </a>
                <a href="books.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'books.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    Books
                </a>
                <a href="projects.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'projects.php' ? 'active' : ''; ?>">
                    <i class="fas fa-code"></i>
                    Projects
                </a>
                <a href="games.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'games.php' ? 'active' : ''; ?>">
                    <i class="fas fa-gamepad"></i>
                    Games
                </a>
                <a href="orders.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    Orders
                </a>
                <a href="custom-orders.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'custom-orders.php' ? 'active' : ''; ?>">
                    <i class="fas fa-hammer"></i>
                    Custom Orders
                    <?php
                    // Show pending custom orders count
                    try {
                        global $db;
                        $pending_custom_orders = $db->query("SELECT COUNT(*) FROM custom_order_requests WHERE status IN ('pending', 'under_review')")->fetchColumn();
                        if ($pending_custom_orders > 0) {
                            echo '<span class="badge bg-warning rounded-pill ms-2">' . $pending_custom_orders . '</span>';
                        }
                    } catch (Exception $e) {
                        // Silently ignore errors
                    }
                    ?>
                </a>
                <a href="analytics.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    Analytics
                </a>
                <a href="logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'logs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    Activity Logs
                </a>
                <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
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
                        <h2 class="mb-0"><?php echo htmlspecialchars($title); ?></h2>
                        <?php if ($description): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($description); ?></small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted">Welcome, <?php echo htmlspecialchars($current_admin['full_name']); ?></span>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($current_admin['full_name']); ?>&background=6366f1&color=ffffff&size=32" 
                             class="rounded-circle" width="32" height="32" alt="Admin">
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="admin-content">
                <?php
                global $flash_message;
                if ($flash_message): ?>
                    <div class="alert alert-<?php echo $flash_message['type'] === 'error' ? 'danger' : $flash_message['type']; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($flash_message['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
    <?php
}

function renderAdminFooter() {
    ?>
            </div> <!-- admin-content -->
        </div> <!-- admin-main -->

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        </script>
    </body>
    </html>
    <?php
}
?>