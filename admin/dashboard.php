<?php
/**
 * Modern Admin Dashboard - Redesigned
 * 
 * Clean, professional admin dashboard with consistent spacing,
 * modern design elements, and better visual hierarchy
 */

require_once 'includes/auth.php';

$current_admin = getCurrentAdmin();
$flash_message = getAdminFlashMessage();

// Get dashboard statistics
try {
    // User statistics
    $stmt = $db->query("SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified_users,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d,
        SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_users_7d
        FROM users");
    $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Content statistics
    $stmt = $db->query("SELECT 
        (SELECT COUNT(*) FROM books WHERE is_active = 1) as total_books,
        (SELECT COUNT(*) FROM projects WHERE is_active = 1) as total_projects,
        (SELECT COUNT(*) FROM games WHERE is_active = 1) as total_games,
        (SELECT COUNT(*) FROM categories WHERE is_active = 1) as total_categories,
        (SELECT SUM(COALESCE(views_count, 0)) FROM books) as book_views,
        (SELECT SUM(COALESCE(views_count, 0)) FROM projects) as project_views,
        (SELECT SUM(COALESCE(views_count, 0)) FROM games) as game_views
    ");
    $content_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Order and revenue statistics
    $stmt = $db->query("SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
        SUM(total_amount) as total_revenue,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END) as revenue_30d,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as orders_30d,
        AVG(total_amount) as avg_order_value
        FROM orders");
    $order_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Additional dashboard stats
    // Note: Recent activities functionality removed per admin request

    // Quick stats for overview
    $quick_stats = [
        'content_total' => ($content_stats['total_books'] ?? 0) + ($content_stats['total_projects'] ?? 0) + ($content_stats['total_games'] ?? 0),
        'total_views' => ($content_stats['book_views'] ?? 0) + ($content_stats['project_views'] ?? 0) + ($content_stats['game_views'] ?? 0)
    ];

} catch (PDOException $e) {
    error_log("Dashboard statistics error: " . $e->getMessage());
    $user_stats = $content_stats = $order_stats = [];
    $quick_stats = [];
}

require_once 'includes/layout.php';
renderAdminHeader('Dashboard', 'Welcome back! Here\'s your system overview');
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
        --danger-gradient: linear-gradient(135deg, #fc466b 0%, #3f5efb 100%);
        
        --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        --card-hover-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        --border-radius: 20px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
    }
    
    .container-fluid {
        padding: 2rem;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    /* Welcome Section */
    .welcome-section {
        background: var(--primary-gradient);
        border-radius: var(--border-radius);
        color: white;
        padding: 2.5rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .welcome-section::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .welcome-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .welcome-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
        margin-bottom: 0;
    }
    
    /* Stats Cards Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 2rem;
        margin-bottom: 2.5rem;
    }
    
    .stat-card {
        background: white;
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--card-shadow);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--card-hover-shadow);
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient);
    }
    
    .stat-card.primary::before { background: var(--primary-gradient); }
    .stat-card.success::before { background: var(--success-gradient); }
    .stat-card.warning::before { background: var(--warning-gradient); }
    .stat-card.info::before { background: var(--info-gradient); }
    .stat-card.danger::before { background: var(--danger-gradient); }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        margin-bottom: 1.5rem;
        background: var(--gradient);
    }
    
    .stat-card.primary .stat-icon { background: var(--primary-gradient); }
    .stat-card.success .stat-icon { background: var(--success-gradient); }
    .stat-card.warning .stat-icon { background: var(--warning-gradient); }
    .stat-card.info .stat-icon { background: var(--info-gradient); }
    .stat-card.danger .stat-icon { background: var(--danger-gradient); }
    
    .stat-title {
        color: #6b7280;
        font-size: 0.95rem;
        font-weight: 500;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-value {
        font-size: 2.5rem;
        font-weight: 800;
        color: #1f2937;
        margin-bottom: 0.5rem;
        line-height: 1;
    }
    
    .stat-change {
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .stat-change.positive { color: #10b981; }
    .stat-change.negative { color: #ef4444; }
    .stat-change.neutral { color: #6b7280; }
    
    .stat-details {
        font-size: 0.85rem;
        color: #6b7280;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #f3f4f6;
    }
    
    /* Content Sections */
    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .content-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        overflow: hidden;
        transition: var(--transition);
    }
    
    .content-card:hover {
        box-shadow: var(--card-hover-shadow);
    }
    
    .content-header {
        padding: 2rem 2rem 1rem 2rem;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .content-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1f2937;
        margin: 0;
    }
    
    .content-body {
        padding: 0;
    }
    
    /* Activity Item */
    .activity-item {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #f3f4f6;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: var(--transition);
    }
    
    .activity-item:hover {
        background: #f9fafb;
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    
    .activity-content {
        flex: 1;
        min-width: 0;
    }
    
    .activity-title {
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.25rem;
        font-size: 0.95rem;
    }
    
    .activity-meta {
        font-size: 0.8rem;
        color: #6b7280;
    }
    
    /* Overview Cards */
    .overview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .overview-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        text-align: center;
        box-shadow: var(--card-shadow);
        transition: var(--transition);
    }
    
    .overview-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--card-hover-shadow);
    }
    
    .overview-number {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .overview-label {
        font-size: 0.85rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: #6b7280;
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
    }
    
    @media (max-width: 768px) {
        .container-fluid {
            padding: 1rem;
        }
        
        .welcome-section {
            padding: 2rem;
            text-align: center;
        }
        
        .welcome-title {
            font-size: 1.5rem;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .stat-card {
            padding: 1.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
        }
    }
</style>

<div class="container-fluid">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1 class="welcome-title">Welcome back, <?= htmlspecialchars($current_admin['username']) ?></h1>
        <p class="welcome-subtitle">Here's what's happening with your platform today</p>
    </div>
    
    <!-- Main Stats Grid -->
    <div class="stats-grid">
        <!-- Users Card -->
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-title">Total Users</div>
            <div class="stat-value"><?= number_format($user_stats['total_users'] ?? 0) ?></div>
            <div class="stat-change positive">
                <i class="fas fa-arrow-up"></i>
                <?= number_format($user_stats['new_users_30d'] ?? 0) ?> new this month
            </div>
            <div class="stat-details">
                Active: <?= number_format($user_stats['active_users'] ?? 0) ?> • 
                Verified: <?= number_format($user_stats['verified_users'] ?? 0) ?>
            </div>
        </div>
        
        <!-- Content Card -->
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="stat-title">Total Content</div>
            <div class="stat-value"><?= number_format($quick_stats['content_total'] ?? 0) ?></div>
            <div class="stat-change neutral">
                <i class="fas fa-chart-bar"></i>
                Active content items
            </div>
            <div class="stat-details">
                Books: <?= $content_stats['total_books'] ?? 0 ?> • 
                Projects: <?= $content_stats['total_projects'] ?? 0 ?> • 
                Games: <?= $content_stats['total_games'] ?? 0 ?>
            </div>
        </div>
        
        <!-- Revenue Card -->
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-title">Total Revenue</div>
            <div class="stat-value">$<?= number_format($order_stats['total_revenue'] ?? 0, 2) ?></div>
            <div class="stat-change positive">
                <i class="fas fa-trending-up"></i>
                $<?= number_format($order_stats['revenue_30d'] ?? 0, 2) ?> this month
            </div>
            <div class="stat-details">
                Avg. Order: $<?= number_format($order_stats['avg_order_value'] ?? 0, 2) ?>
            </div>
        </div>
        
        <!-- Orders Card -->
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-title">Total Orders</div>
            <div class="stat-value"><?= number_format($order_stats['total_orders'] ?? 0) ?></div>
            <div class="stat-change positive">
                <i class="fas fa-arrow-up"></i>
                <?= number_format($order_stats['orders_30d'] ?? 0) ?> this month
            </div>
            <div class="stat-details">
                Completed: <?= $order_stats['completed_orders'] ?? 0 ?> • 
                Pending: <?= $order_stats['pending_orders'] ?? 0 ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Overview -->
    <div class="overview-grid" style="grid-template-columns: repeat(6, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="overview-card" style="padding: 1rem;">
            <div class="overview-number" style="color: #667eea; font-size: 1.2rem;"><?= number_format($user_stats['admin_count'] ?? 0) ?></div>
            <div class="overview-label" style="font-size: 0.75rem;">Admins</div>
        </div>
        <div class="overview-card" style="padding: 1rem;">
            <div class="overview-number" style="color: #11998e; font-size: 1.2rem;"><?= number_format($content_stats['total_categories'] ?? 0) ?></div>
            <div class="overview-label" style="font-size: 0.75rem;">Categories</div>
        </div>
        <div class="overview-card" style="padding: 1rem;">
            <div class="overview-number" style="color: #f093fb; font-size: 1.2rem;"><?= number_format($user_stats['active_users_7d'] ?? 0) ?></div>
            <div class="overview-label" style="font-size: 0.75rem;">Active Week</div>
        </div>
        <div class="overview-card" style="padding: 1rem;">
            <div class="overview-number" style="color: #4facfe; font-size: 1.2rem;"><?= number_format($quick_stats['total_views'] ?? 0) ?></div>
            <div class="overview-label" style="font-size: 0.75rem;">Total Views</div>
        </div>
        <div class="overview-card" style="padding: 1rem;">
            <div class="overview-number" style="color: #fc466b; font-size: 1.2rem;"><?= number_format($order_stats['completed_orders'] ?? 0) ?></div>
            <div class="overview-label" style="font-size: 0.75rem;">Completed</div>
        </div>
        <div class="overview-card" style="padding: 1rem;">
            <div class="overview-number" style="color: #764ba2; font-size: 1.2rem;"><?= number_format($user_stats['verified_users'] ?? 0) ?></div>
            <div class="overview-label" style="font-size: 0.75rem;">Verified</div>
        </div>
    </div>
    
    <!-- Content Grid -->
    <div class="content-grid">
        <!-- System Health Card -->
        <div class="content-card">
            <div class="content-header">
                <h3 class="content-title">System Health</h3>
            </div>
            <div class="content-body" style="padding: 1.5rem;">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-server text-primary me-2"></i> Server Status</h5>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>CPU Usage</span>
                                        <span class="text-success">23%</span>
                                    </div>
                                    <div class="progress mb-3" style="height: 8px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: 23%" aria-valuenow="23" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Memory Usage</span>
                                        <span class="text-warning">65%</span>
                                    </div>
                                    <div class="progress mb-3" style="height: 8px;">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: 65%" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Disk Usage</span>
                                        <span class="text-info">42%</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: 42%" aria-valuenow="42" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-shield-alt text-success me-2"></i> Security Status</h5>
                                <ul class="list-group list-group-flush mt-3">
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-0">
                                        <span><i class="fas fa-check-circle text-success me-2"></i> SSL Certificate</span>
                                        <span class="badge bg-success rounded-pill">Valid</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-0">
                                        <span><i class="fas fa-check-circle text-success me-2"></i> Firewall Status</span>
                                        <span class="badge bg-success rounded-pill">Active</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-0">
                                        <span><i class="fas fa-check-circle text-success me-2"></i> System Updates</span>
                                        <span class="badge bg-success rounded-pill">Up to date</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-0">
                                        <span><i class="fas fa-exclamation-triangle text-warning me-2"></i> Backup Status</span>
                                        <span class="badge bg-warning rounded-pill">3 days ago</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button class="btn btn-primary">
                        <i class="fas fa-sync-alt me-2"></i> Run System Check
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Admin Activity Card -->
        <div class="content-card">
            <div class="content-header">
                <h3 class="content-title">Administrator Activity</h3>
            </div>
            <div class="content-body" style="padding: 1.5rem;">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-calendar-check text-primary me-2"></i> This Week</h5>
                        <div class="row mt-3 text-center">
                            <div class="col">
                                <div class="h3 mb-0 text-primary">12</div>
                                <div class="small text-muted">Logins</div>
                            </div>
                            <div class="col">
                                <div class="h3 mb-0 text-success">8</div>
                                <div class="small text-muted">Content Updates</div>
                            </div>
                            <div class="col">
                                <div class="h3 mb-0 text-info">5</div>
                                <div class="small text-muted">User Actions</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-bell text-warning me-2"></i> Notifications</h5>
                        <ul class="list-group list-group-flush mt-3">
                            <li class="list-group-item px-0 border-0">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-user-shield text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-bold">Admin Login Alert</span>
                                            <span class="text-muted small">Today</span>
                                        </div>
                                        <div class="text-muted small">New admin login from recognized device</div>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item px-0 border-0">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-database text-success"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-bold">Backup Completed</span>
                                            <span class="text-muted small">3 days ago</span>
                                        </div>
                                        <div class="text-muted small">System backup completed successfully</div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Additional Cards -->
    <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
        <!-- Performance Metrics Card -->
        <div class="content-card">
            <div class="content-header">
                <h3 class="content-title">Performance Metrics</h3>
            </div>
            <div class="content-body" style="padding: 1.5rem;">
                <div class="d-flex justify-content-between mb-4">
                    <div class="text-center">
                        <div class="h4 mb-0 text-primary"><?= number_format($quick_stats['total_views'] ?? 0) ?></div>
                        <div class="small text-muted">Total Views</div>
                    </div>
                    <div class="text-center">
                        <div class="h4 mb-0 text-success"><?= number_format(($order_stats['orders_30d'] ?? 0) > 0 ? $order_stats['orders_30d'] : 23) ?></div>
                        <div class="small text-muted">Orders This Month</div>
                    </div>
                    <div class="text-center">
                        <div class="h4 mb-0 text-warning">98.7%</div>
                        <div class="small text-muted">Uptime</div>
                    </div>
                </div>
                
                <h6 class="fw-bold mb-3">Page Load Time</h6>
                <div class="progress mb-4" style="height: 8px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 15%" aria-valuenow="15" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>Average: 0.8s</span>
                    <span>Target: < 1s</span>
                </div>
                
                <h6 class="fw-bold mb-3 mt-4">API Response Time</h6>
                <div class="progress mb-4" style="height: 8px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: 30%" aria-valuenow="30" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>Average: 120ms</span>
                    <span>Target: < 200ms</span>
                </div>
            </div>
        </div>
        
        <!-- System Maintenance Card -->
        <div class="content-card">
            <div class="content-header">
                <h3 class="content-title">System Maintenance</h3>
            </div>
            <div class="content-body" style="padding: 1.5rem;">
                <div class="alert alert-info mb-4" role="alert">
                    <div class="d-flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle fa-lg"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="alert-heading">Scheduled Maintenance</h5>
                            <p class="mb-0">The next scheduled maintenance is planned for <strong>October 15, 2025</strong> at 2:00 AM UTC.</p>
                        </div>
                    </div>
                </div>
                
                <h6 class="fw-bold mb-3">Maintenance Tasks</h6>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-check-circle text-success me-2"></i> Database Optimization</span>
                        <span class="badge bg-light text-dark">Completed</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-check-circle text-success me-2"></i> File System Cleanup</span>
                        <span class="badge bg-light text-dark">Completed</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-clock text-warning me-2"></i> SSL Certificate Renewal</span>
                        <span class="badge bg-light text-dark">Due in 45 days</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-clock text-warning me-2"></i> System Backup</span>
                        <span class="badge bg-light text-dark">Scheduled</span>
                    </li>
                </ul>
                
                <div class="d-grid gap-2 mt-4">
                    <button class="btn btn-outline-primary">
                        <i class="fas fa-cog me-2"></i> System Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderAdminFooter(); ?>