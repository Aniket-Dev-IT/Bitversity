<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();

$user = getCurrentUser();
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';
$view_mode = $_GET['view'] ?? 'grid'; // grid or timeline

// Get user's custom orders with enhanced data
try {
    $offset = ($page - 1) * $per_page;
    
    // Build WHERE conditions
    $where_conditions = ['user_id = ?'];
    $params = [$user['id']];
    
    if ($filter_status) {
        $where_conditions[] = 'status = ?';
        $params[] = $filter_status;
    }
    
    if ($filter_type) {
        $where_conditions[] = 'type = ?';
        $params[] = $filter_type;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Count total orders
    $count_sql = "SELECT COUNT(*) FROM custom_order_requests WHERE {$where_clause}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_orders = $stmt->fetchColumn();
    
    // Get orders with enhanced information
    $sql = "SELECT cor.*, 
               NULL as admin_name,
               0 as attachments_count,
               0 as unread_messages_count,
               0 as total_messages_count,
               NULL as last_status_change
            FROM custom_order_requests cor
            WHERE {$where_clause}
            ORDER BY 
                CASE 
                    WHEN cor.status = 'pending' THEN 1
                    WHEN cor.status = 'under_review' THEN 2
                    WHEN cor.status = 'approved' THEN 3
                    WHEN cor.status = 'payment_pending' THEN 4
                    WHEN cor.status = 'in_progress' THEN 5
                    WHEN cor.status = 'completed' THEN 6
                    ELSE 7
                END, cor.created_at DESC
            LIMIT {$per_page} OFFSET {$offset}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $custom_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_pages = ceil($total_orders / $per_page);
    
} catch (PDOException $e) {
    error_log("Custom orders fetch error: " . $e->getMessage());
    error_log("SQL Query: " . $sql ?? 'Unknown');
    error_log("Parameters: " . json_encode($params ?? []));
    $custom_orders = [];
    $total_orders = 0;
    $total_pages = 1;
    
    // In development, show the error
    if (defined('DEBUG') && DEBUG) {
        echo '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Get enhanced statistics
$stats = [];
try {
    $stats_sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN custom_price > 0 THEN custom_price ELSE 0 END) as total_quoted_value,
        AVG(CASE WHEN status = 'completed' AND custom_price > 0 THEN custom_price ELSE NULL END) as avg_project_value,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as orders_30d,
        0 as urgent_active
        FROM custom_order_requests WHERE user_id = ?";
    $stmt = $db->prepare($stats_sql);
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activity/timeline data (simplified for now since tables may not exist)
    $timeline_sql = "SELECT cor.id, cor.title as order_title, cor.status, cor.updated_at as created_at, 'System' as changed_by_name, cor.status as new_status, '' as reason
                     FROM custom_order_requests cor
                     WHERE cor.user_id = ?
                     ORDER BY cor.updated_at DESC
                     LIMIT 10";
    $stmt = $db->prepare($timeline_sql);
    $stmt->execute([$user['id']]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Stats fetch error: " . $e->getMessage());
    $stats = [
        'total' => 0, 
        'pending' => 0, 
        'under_review' => 0, 
        'approved' => 0, 
        'completed' => 0,
        'in_progress' => 0,
        'rejected' => 0,
        'total_quoted_value' => 0,
        'avg_project_value' => 0,
        'orders_30d' => 0,
        'urgent_active' => 0
    ];
    $recent_activity = [];
}

// Ensure all required keys exist with default values
$stats = array_merge([
    'total' => 0, 
    'pending' => 0, 
    'under_review' => 0, 
    'approved' => 0, 
    'completed' => 0,
    'in_progress' => 0,
    'rejected' => 0,
    'total_quoted_value' => 0,
    'avg_project_value' => 0,
    'orders_30d' => 0,
    'urgent_active' => 0
], $stats ?: []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Custom Orders - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <style>
        .status-badge {
            font-size: 0.8em;
            font-weight: 500;
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
        }
        
        .order-card {
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .order-card.pending { border-left-color: #ffc107; background: linear-gradient(90deg, rgba(255,193,7,0.02) 0%, transparent 100%); }
        .order-card.under_review { border-left-color: #17a2b8; background: linear-gradient(90deg, rgba(23,162,184,0.02) 0%, transparent 100%); }
        .order-card.approved { border-left-color: #28a745; background: linear-gradient(90deg, rgba(40,167,69,0.02) 0%, transparent 100%); }
        .order-card.in_progress { border-left-color: #007bff; background: linear-gradient(90deg, rgba(0,123,255,0.02) 0%, transparent 100%); }
        .order-card.completed { border-left-color: #6c757d; background: linear-gradient(90deg, rgba(108,117,125,0.02) 0%, transparent 100%); }
        .order-card.rejected { border-left-color: #dc3545; background: linear-gradient(90deg, rgba(220,53,69,0.02) 0%, transparent 100%); }
        .order-card.payment_pending { border-left-color: #fd7e14; background: linear-gradient(90deg, rgba(253,126,20,0.02) 0%, transparent 100%); }
        
        .stats-overview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .stats-overview::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }
        
        .filter-controls {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
        }
        
        .view-switcher .btn {
            border-radius: 20px;
            margin: 0 0.2rem;
            transition: all 0.2s ease;
        }
        
        .no-orders {
            text-align: center;
            padding: 4rem;
            color: #6c757d;
            background: #f8f9fa;
            border-radius: 12px;
            margin: 2rem 0;
        }
        
        .priority-urgent {
            border-top: 3px solid #dc3545;
            position: relative;
        }
        
        .priority-urgent::after {
            content: 'URGENT';
            position: absolute;
            top: -3px;
            right: 10px;
            background: #dc3545;
            color: white;
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            font-weight: bold;
            border-radius: 0 0 4px 4px;
        }
        
        .priority-high {
            border-top: 3px solid #fd7e14;
        }
        
        .unread-indicator {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 12px;
            height: 12px;
            background: #dc3545;
            border-radius: 50%;
            border: 2px solid white;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        
        .message-count {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border-radius: 12px;
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .stat-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            border-radius: 12px !important;
            backdrop-filter: blur(10px);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            scale: 1.02;
        }
        
        /* Professional Gradient Colors Matching the Hero */
        .stat-card-total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card-pending {
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            color: #2d3436 !important;
        }
        
        .stat-card-pending i,
        .stat-card-pending h4,
        .stat-card-pending small {
            color: #2d3436 !important;
        }
        
        .stat-card-review {
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
        }
        
        .stat-card-approved {
            background: linear-gradient(135deg, #55a3ff 0%, #003d82 100%);
        }
        
        .stat-card-progress {
            background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%);
        }
        
        .stat-card-done {
            background: linear-gradient(135deg, #636e72 0%, #2d3436 100%);
        }
        
        /* Add subtle animation effects */
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }
        
        .stat-card:hover::before {
            left: 100%;
        }
        
        .order-card {
            cursor: pointer;
        }
        
        .order-card .card-body:hover {
            background: rgba(0,0,0,0.02);
        }
        
        .timeline-sidebar {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            border-left: 2px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -5px;
            top: 0.5rem;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #007bff;
        }
        
        .timeline-item:last-child {
            border-left: 2px solid transparent;
        }
        
        .floating-action {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }
        
        .floating-action .btn {
            border-radius: 50px;
            padding: 1rem 2rem;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
            font-weight: 600;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box .form-control {
            border-radius: 25px;
            padding-left: 2.5rem;
        }
        
        .search-box .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .order-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        
        .order-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Enhanced Stats Overview -->
            <div class="stats-overview">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h1 class="display-6 mb-2">
                            <i class="fas fa-hammer me-3"></i>My Custom Orders
                        </h1>
                        <p class="mb-3 opacity-75">Track your custom development requests and communicate with our team</p>
                        <?php if (($stats['orders_30d'] ?? 0) > 0): ?>
                            <small class="badge bg-light text-dark me-2"><?= $stats['orders_30d'] ?? 0 ?> orders this month</small>
                        <?php endif; ?>
                        <?php if (($stats['total_quoted_value'] ?? 0) > 0): ?>
                            <small class="badge bg-light text-dark">$<?= number_format($stats['total_quoted_value'] ?? 0) ?> total quoted value</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-6">
                        <div class="row g-3">
                            <div class="col-4 col-lg-2">
                                <div class="stat-card stat-card-total text-white text-center p-3 rounded">
                                    <i class="fas fa-list-alt fa-lg mb-2"></i>
                                    <h4 class="mb-0"><?= $stats['total'] ?? 0 ?></h4>
                                    <small>Total</small>
                                </div>
                            </div>
                            <div class="col-4 col-lg-2">
                                <div class="stat-card stat-card-pending text-center p-3 rounded">
                                    <i class="fas fa-clock fa-lg mb-2"></i>
                                    <h4 class="mb-0"><?= $stats['pending'] ?? 0 ?></h4>
                                    <small>Pending</small>
                                </div>
                            </div>
                            <div class="col-4 col-lg-2">
                                <div class="stat-card stat-card-review text-white text-center p-3 rounded">
                                    <i class="fas fa-search fa-lg mb-2"></i>
                                    <h4 class="mb-0"><?= $stats['under_review'] ?? 0 ?></h4>
                                    <small>Review</small>
                                </div>
                            </div>
                            <div class="col-4 col-lg-2">
                                <div class="stat-card stat-card-approved text-white text-center p-3 rounded">
                                    <i class="fas fa-check fa-lg mb-2"></i>
                                    <h4 class="mb-0"><?= $stats['approved'] ?? 0 ?></h4>
                                    <small>Approved</small>
                                </div>
                            </div>
                            <div class="col-4 col-lg-2">
                                <div class="stat-card stat-card-progress text-white text-center p-3 rounded">
                                    <i class="fas fa-cogs fa-lg mb-2"></i>
                                    <h4 class="mb-0"><?= $stats['in_progress'] ?? 0 ?></h4>
                                    <small>Progress</small>
                                </div>
                            </div>
                            <div class="col-4 col-lg-2">
                                <div class="stat-card stat-card-done text-white text-center p-3 rounded">
                                    <i class="fas fa-trophy fa-lg mb-2"></i>
                                    <h4 class="mb-0"><?= $stats['completed'] ?? 0 ?></h4>
                                    <small>Done</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8">
                    <!-- Filter Controls -->
                    <div class="filter-controls">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="search-box">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" class="form-control" id="searchOrders" placeholder="Search orders...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="under_review" <?= $filter_status === 'under_review' ? 'selected' : '' ?>>Under Review</option>
                                    <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="typeFilter">
                                    <option value="">All Types</option>
                                    <option value="project" <?= $filter_type === 'project' ? 'selected' : '' ?>>Projects</option>
                                    <option value="game" <?= $filter_type === 'game' ? 'selected' : '' ?>>Games</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="view-switcher">
                                    <button class="btn btn-outline-primary btn-sm <?= $view_mode === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">
                                        <i class="fas fa-th"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm <?= $view_mode === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">
                                        <i class="fas fa-list"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
            
                    <!-- Enhanced Custom Orders List -->
                    <div id="ordersContainer">
                        <?php if (empty($custom_orders)): ?>
                        <div class="no-orders">
                            <i class="fas fa-hammer fa-4x mb-4"></i>
                            <h3>No Custom Orders Yet</h3>
                            <p class="mb-4">You haven't submitted any custom development requests yet.<br>Start by requesting a custom project or game development service.</p>
                            <div class="d-flex gap-3 justify-content-center">
                                <a href="<?php echo BASE_PATH; ?>/request-project.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-code me-2"></i>Request Custom Project
                                </a>
                                <a href="<?php echo BASE_PATH; ?>/request-game.php" class="btn btn-success btn-lg">
                                    <i class="fas fa-gamepad me-2"></i>Request Custom Game
                                </a>
                            </div>
                        </div>
                        <?php else: ?>
                        
                        <?php foreach ($custom_orders as $order): 
                            $status_classes = [
                                'pending' => 'bg-warning text-dark',
                                'under_review' => 'bg-info text-white',
                                'approved' => 'bg-success text-white',
                                'rejected' => 'bg-danger text-white',
                                'payment_pending' => 'bg-orange text-white',
                                'in_progress' => 'bg-primary text-white',
                                'completed' => 'bg-dark text-white'
                            ];
                            $status_class = $status_classes[$order['status']] ?? 'bg-secondary text-white';
                        ?>
                        <div class="order-card <?= $order['status'] ?> <?= ($order['priority'] ?? '') === 'urgent' ? 'priority-urgent' : '' ?> <?= ($order['priority'] ?? '') === 'high' ? 'priority-high' : '' ?>" data-order-id="<?= $order['id'] ?>" onclick="viewOrderDetails(<?= $order['id'] ?>)">
                            <div class="card-body p-4">
                                <div class="row">
                                    <div class="col-lg-8">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <h5 class="card-title mb-2 fw-bold"><?= htmlspecialchars($order['title']) ?></h5>
                                                <div class="d-flex align-items-center gap-2 mb-3">
                                                    <span class="status-badge <?= $status_class ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                                    </span>
                                                    <span class="badge bg-light text-dark">
                                                        <i class="fas fa-<?= $order['type'] === 'game' ? 'gamepad' : 'code' ?> me-1"></i>
                                                        <?= ucfirst($order['type']) ?>
                                                    </span>
                                                    <?php if (($order['priority'] ?? '') === 'urgent'): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>Urgent
                                                        </span>
                                                    <?php elseif (($order['priority'] ?? '') === 'high'): ?>
                                                        <span class="badge bg-warning text-dark">High Priority</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($order['custom_price'] > 0): ?>
                                            <div class="text-end ms-3">
                                                <h4 class="text-success mb-0">$<?= number_format($order['custom_price'], 2) ?></h4>
                                                <small class="text-muted">Quoted Price</small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="card-text text-muted mb-3 lh-sm">
                                            <?= htmlspecialchars(substr($order['description'], 0, 200)) ?>
                                            <?= strlen($order['description']) > 200 ? '...' : '' ?>
                                        </p>
                                        
                                        <div class="order-meta">
                                            <span class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?= date('M j, Y', strtotime($order['created_at'])) ?>
                                            </span>
                                            
                                            <?php if ($order['attachments_count'] > 0): ?>
                                            <span class="text-muted">
                                                <i class="fas fa-paperclip me-1"></i>
                                                <?= $order['attachments_count'] ?> file<?= $order['attachments_count'] > 1 ? 's' : '' ?>
                                            </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($order['total_messages_count'] > 0): ?>
                                            <span class="position-relative">
                                                <span class="message-count">
                                                    <i class="fas fa-comments me-1"></i>
                                                    <?= $order['total_messages_count'] ?> message<?= $order['total_messages_count'] > 1 ? 's' : '' ?>
                                                </span>
                                                <?php if ($order['unread_messages_count'] > 0): ?>
                                                    <span class="unread-indicator"></span>
                                                <?php endif; ?>
                                            </span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($order['timeline_needed'] ?? $order['deadline'])): ?>
                                            <span class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                Due: <?= date('M j, Y', strtotime($order['timeline_needed'] ?? $order['deadline'])) ?>
                                            </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($order['admin_name']): ?>
                                            <span class="text-muted">
                                                <i class="fas fa-user-tie me-1"></i>
                                                Assigned to <?= htmlspecialchars($order['admin_name']) ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-4">
                                        <div class="order-actions justify-content-lg-end">
                                            <button type="button" class="btn btn-primary btn-sm" onclick="viewOrderDetails(<?= $order['id'] ?>)">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </button>
                                            
                                            <?php if ($order['status'] === 'approved' && $order['custom_price'] > 0): ?>
                                            <button type="button" class="btn btn-success btn-sm" onclick="initiatePayment(<?= $order['id'] ?>)">
                                                <i class="fas fa-credit-card me-1"></i>Pay Now
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($order['last_status_change']): ?>
                                        <div class="text-muted mt-3">
                                            <small>
                                                <i class="fas fa-history me-1"></i>
                                                Last updated <?= date('M j, Y g:i A', strtotime($order['last_status_change'])) ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Enhanced Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted">
                                    Showing <?= (($page - 1) * $per_page) + 1 ?> to <?= min($page * $per_page, $total_orders) ?> of <?= $total_orders ?> orders
                                </div>
                                <ul class="pagination mb-0">
                                    <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1<?= $filter_status ? '&status=' . $filter_status : '' ?><?= $filter_type ? '&type=' . $filter_type : '' ?>">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?><?= $filter_status ? '&status=' . $filter_status : '' ?><?= $filter_type ? '&type=' . $filter_type : '' ?>">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?><?= $filter_status ? '&status=' . $filter_status : '' ?><?= $filter_type ? '&type=' . $filter_type : '' ?>"><?= $i ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?><?= $filter_status ? '&status=' . $filter_status : '' ?><?= $filter_type ? '&type=' . $filter_type : '' ?>">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $total_pages ?><?= $filter_status ? '&status=' . $filter_status : '' ?><?= $filter_type ? '&type=' . $filter_type : '' ?>">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </nav>
                        <?php endif; ?>
                        
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Timeline Sidebar -->
                <div class="col-lg-4">
                    <div class="timeline-sidebar">
                        <h5 class="mb-4">
                            <i class="fas fa-history me-2"></i>Recent Activity
                        </h5>
                        
                        <?php if (!empty($recent_activity)): ?>
                            <?php foreach ($recent_activity as $activity): ?>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($activity['order_title']) ?></h6>
                                        <p class="mb-1 text-muted small">
                                            Status changed to <strong><?= ucfirst(str_replace('_', ' ', $activity['new_status'])) ?></strong>
                                            <?php if ($activity['changed_by_name']): ?>
                                                by <?= htmlspecialchars($activity['changed_by_name']) ?>
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($activity['reason']): ?>
                                            <p class="mb-0 text-muted small">"<?= htmlspecialchars($activity['reason']) ?>"</p>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?= date('M j', strtotime($activity['created_at'])) ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-clock fa-2x mb-2 opacity-50"></i>
                                <p class="mb-0">No recent activity</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Floating Action Button -->
        <div class="floating-action">
            <div class="dropup">
                <button class="btn btn-primary btn-lg dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-2"></i>New Request
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item" href="<?= BASE_PATH ?>/request-project.php">
                            <i class="fas fa-code me-2"></i>Custom Project
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?= BASE_PATH ?>/request-game.php">
                            <i class="fas fa-gamepad me-2"></i>Custom Game
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">
                        <i class="fas fa-file-invoice me-2"></i>Order Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="orderDetailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading order details...</p>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <div id="orderDetailsActions"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Messaging Modal -->
    <div class="modal fade" id="messagingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-comments me-2"></i>Messages
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="messagesContainer" style="height: 400px; overflow-y: auto;">
                        <!-- Messages will be loaded here -->
                    </div>
                    <div class="p-3 border-top">
                        <form id="messageForm">
                            <div class="input-group">
                                <textarea class="form-control" id="messageText" placeholder="Type your message..." rows="2" required></textarea>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Additional Info Modal -->
    <div class="modal fade" id="additionalInfoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add Additional Information
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="additionalInfoForm">
                        <input type="hidden" id="additionalInfoOrderId">
                        <div class="mb-3">
                            <label for="additionalDescription" class="form-label">Additional Description</label>
                            <textarea class="form-control" id="additionalDescription" rows="4" placeholder="Add more details about your requirements..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="additionalRequirements" class="form-label">Updated Requirements</label>
                            <textarea class="form-control" id="additionalRequirements" rows="4" placeholder="Any changes or additions to technical requirements..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="additionalFiles" class="form-label">Additional Files</label>
                            <input type="file" class="form-control" id="additionalFiles" multiple>
                            <small class="text-muted">Upload any additional reference files, documents, or assets.</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitAdditionalInfo()">Add Information</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentOrderId = null;
        let messagesPollingInterval = null;
        
        // Enhanced search and filtering
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchOrders');
            const statusFilter = document.getElementById('statusFilter');
            const typeFilter = document.getElementById('typeFilter');
            
            // Debounced search
            let searchTimeout;
            searchInput?.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    filterOrders();
                }, 300);
            });
            
            statusFilter?.addEventListener('change', () => {
                updateURL();
                window.location.reload();
            });
            
            typeFilter?.addEventListener('change', () => {
                updateURL();
                window.location.reload();
            });
            
            // Auto-refresh for real-time updates
            setInterval(() => {
                checkForUpdates();
            }, 30000); // Check every 30 seconds
        });
        
        function updateURL() {
            const status = document.getElementById('statusFilter')?.value || '';
            const type = document.getElementById('typeFilter')?.value || '';
            const params = new URLSearchParams();
            
            if (status) params.set('status', status);
            if (type) params.set('type', type);
            
            const newURL = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
            window.history.replaceState({}, '', newURL);
        }
        
        function filterOrders() {
            const searchTerm = document.getElementById('searchOrders')?.value.toLowerCase() || '';
            const orderCards = document.querySelectorAll('.order-card');
            
            orderCards.forEach(card => {
                const title = card.querySelector('.card-title')?.textContent.toLowerCase() || '';
                const description = card.querySelector('.card-text')?.textContent.toLowerCase() || '';
                const matches = title.includes(searchTerm) || description.includes(searchTerm);
                
                card.style.display = matches ? 'block' : 'none';
            });
        }
        
        function switchView(mode) {
            const params = new URLSearchParams(window.location.search);
            params.set('view', mode);
            window.location.search = params.toString();
        }
        
        // Enhanced order details modal
        async function viewOrderDetails(orderId) {
            const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            const content = document.getElementById('orderDetailsContent');
            const actions = document.getElementById('orderDetailsActions');
            
            content.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading order details...</p>
                </div>
            `;
            
            actions.innerHTML = '';
            modal.show();
            
            try {
                const response = await fetch(`../api/get-order.php?id=${orderId}`);
                const data = await response.json();
                
                if (data.success) {
                    renderEnhancedOrderDetails(data.data);
                    renderOrderActions(data.data);
                    currentOrderId = orderId;
                } else {
                    throw new Error(data.message || 'Failed to load order details');
                }
            } catch (error) {
                content.innerHTML = `
                    <div class="alert alert-danger m-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading order details: ${error.message}
                    </div>
                `;
            }
        }
        
        function renderEnhancedOrderDetails(order) {
            const content = document.getElementById('orderDetailsContent');
            
            const statusClasses = {
                'pending': 'warning',
                'under_review': 'info',
                'approved': 'success',
                'rejected': 'danger',
                'payment_pending': 'warning',
                'in_progress': 'primary',
                'completed': 'dark'
            };
            
            const statusClass = statusClasses[order.status] || 'secondary';
            
            let statusHistory = '';
            if (order.status_history && order.status_history.length > 0) {
                statusHistory = `
                    <div class="mt-4">
                        <h6><i class="fas fa-history me-2"></i>Status History</h6>
                        <div class="timeline">
                `;
                
                order.status_history.forEach(history => {
                    statusHistory += `
                        <div class="timeline-item mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-${statusClasses[history.new_status] || 'secondary'} me-2">
                                        ${history.new_status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                    </span>
                                    ${history.changed_by_name ? `<small class="text-muted">by ${history.changed_by_name}</small>` : ''}
                                </div>
                                <small class="text-muted">${new Date(history.created_at).toLocaleString()}</small>
                            </div>
                            ${history.reason ? `<p class="mt-2 mb-0 text-muted small">"${history.reason}"</p>` : ''}
                        </div>
                    `;
                });
                
                statusHistory += '</div></div>';
            }
            
            let attachments = '';
            if (order.attachments && order.attachments.length > 0) {
                attachments = `
                    <div class="mt-4">
                        <h6><i class="fas fa-paperclip me-2"></i>Attachments</h6>
                        <div class="row">
                `;
                
                order.attachments.forEach(attachment => {
                    const fileIcon = getFileIcon(attachment.original_filename);
                    attachments += `
                        <div class="col-md-6 mb-2">
                            <div class="border rounded p-2 d-flex align-items-center">
                                <i class="${fileIcon} me-2 text-primary"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">${attachment.original_filename}</div>
                                    <small class="text-muted">${formatFileSize(attachment.file_size || 0)}</small>
                                </div>
                                <a href="../api/download-attachment.php?id=${attachment.id}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                    `;
                });
                
                attachments += '</div></div>';
            }
            
            content.innerHTML = `
                <div class="p-4">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="d-flex align-items-start justify-content-between mb-4">
                                <div>
                                    <h3 class="mb-2">${order.title}</h3>
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <span class="badge bg-${statusClass} fs-6">
                                            ${order.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                        </span>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-${order.type === 'game' ? 'gamepad' : 'code'} me-1"></i>
                                            ${order.type.charAt(0).toUpperCase() + order.type.slice(1)}
                                        </span>
                                        ${(order.priority || '') === 'urgent' ? '<span class="badge bg-danger">Urgent</span>' : ''}
                                        ${(order.priority || '') === 'high' ? '<span class="badge bg-warning text-dark">High Priority</span>' : ''}
                                    </div>
                                </div>
                                ${order.custom_price > 0 ? `
                                    <div class="text-end">
                                        <h4 class="text-success mb-0">$${parseFloat(order.custom_price).toFixed(2)}</h4>
                                        <small class="text-muted">Quoted Price</small>
                                    </div>
                                ` : ''}
                            </div>
                            
                            <div class="mb-4">
                                <h6><i class="fas fa-file-text me-2"></i>Description</h6>
                                <div class="bg-light p-3 rounded">${order.description}</div>
                            </div>
                            
                            ${order.technical_requirements ? `
                                <div class="mb-4">
                                    <h6><i class="fas fa-list-check me-2"></i>Technical Requirements</h6>
                                    <div class="bg-light p-3 rounded">${order.technical_requirements}</div>
                                </div>
                            ` : ''}
                            
                            ${order.rejection_reason ? `
                                <div class="mb-4">
                                    <div class="alert alert-warning">
                                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Rejection Reason</h6>
                                        ${order.rejection_reason}
                                    </div>
                                </div>
                            ` : ''}
                            
                            ${attachments}
                            ${statusHistory}
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Order Information</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>Order ID:</strong></td>
                                            <td>#${order.id}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Type:</strong></td>
                                            <td>${order.type.charAt(0).toUpperCase() + order.type.slice(1)}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Priority:</strong></td>
                                            <td>${(order.priority || 'normal').charAt(0).toUpperCase() + (order.priority || 'normal').slice(1)}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Budget Range:</strong></td>
                                            <td>${order.budget_range || 'Not specified'}</td>
                                        </tr>
                                        ${(order.timeline_needed || order.deadline) ? `
                                            <tr>
                                                <td><strong>Timeline:</strong></td>
                                                <td>${new Date(order.timeline_needed || order.deadline).toLocaleDateString()}</td>
                                            </tr>
                                        ` : ''}
                                        <tr>
                                            <td><strong>Submitted:</strong></td>
                                            <td>${new Date(order.created_at).toLocaleDateString()}</td>
                                        </tr>
                                        ${order.admin_name ? `
                                            <tr>
                                                <td><strong>Assigned to:</strong></td>
                                                <td>${order.admin_name}</td>
                                            </tr>
                                        ` : ''}
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function renderOrderActions(order) {
            const actions = document.getElementById('orderDetailsActions');
            let actionButtons = '';
            
            if (order.status === 'approved' && order.custom_price > 0) {
                actionButtons += `<button class="btn btn-success me-2" onclick="initiatePayment(${order.id})">
                    <i class="fas fa-credit-card me-2"></i>Pay Now
                </button>`;
            }
            
            if (['pending', 'under_review'].includes(order.status)) {
                actionButtons += `<button class="btn btn-outline-primary me-2" onclick="addAdditionalInfo(${order.id})">
                    <i class="fas fa-plus me-2"></i>Add Information
                </button>`;
            }
            
            if (order.total_messages_count > 0 || ['approved', 'in_progress', 'completed'].includes(order.status)) {
                actionButtons += `<button class="btn btn-outline-primary me-2" onclick="openMessaging(${order.id})">
                    <i class="fas fa-comments me-2"></i>Messages
                    ${order.unread_messages_count > 0 ? `<span class="badge bg-danger ms-1">${order.unread_messages_count}</span>` : ''}
                </button>`;
            }
            
            actionButtons += `<button class="btn btn-outline-secondary" onclick="exportOrderPDF(${order.id})">
                <i class="fas fa-file-pdf me-2"></i>Export PDF
            </button>`;
            
            actions.innerHTML = actionButtons;
        }
        
        // Messaging functionality
        async function openMessaging(orderId) {
            currentOrderId = orderId;
            const modal = new bootstrap.Modal(document.getElementById('messagingModal'));
            
            modal.show();
            await loadMessages();
            
            // Start polling for new messages
            if (messagesPollingInterval) {
                clearInterval(messagesPollingInterval);
            }
            messagesPollingInterval = setInterval(loadMessages, 5000);
            
            // Setup message form
            document.getElementById('messageForm').onsubmit = async function(e) {
                e.preventDefault();
                await sendMessage();
            };
            
            // Stop polling when modal closes
            document.getElementById('messagingModal').addEventListener('hidden.bs.modal', function() {
                if (messagesPollingInterval) {
                    clearInterval(messagesPollingInterval);
                    messagesPollingInterval = null;
                }
            });
        }
        
        async function loadMessages() {
            if (!currentOrderId) return;
            
            try {
                const response = await fetch(`../api/custom-order-messages.php?order_id=${currentOrderId}`);
                const data = await response.json();
                
                if (data.success) {
                    renderMessages(data.data);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }
        
        function renderMessages(messages) {
            const container = document.getElementById('messagesContainer');
            
            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
                        <p>No messages yet. Start the conversation!</p>
                    </div>
                `;
                return;
            }
            
            let messagesHtml = '';
            messages.forEach(message => {
                const isAdmin = message.is_admin_message;
                const messageClass = isAdmin ? 'message-admin' : 'message-user';
                const alignment = isAdmin ? 'justify-content-start' : 'justify-content-end';
                
                messagesHtml += `
                    <div class="d-flex ${alignment} mb-3 p-3">
                        <div class="message-bubble ${messageClass}" style="max-width: 70%;">
                            <div class="d-flex align-items-center mb-2">
                                <strong class="me-2">${isAdmin ? (message.sender_name || 'Admin') : 'You'}</strong>
                                <small class="text-muted">${new Date(message.created_at).toLocaleString()}</small>
                            </div>
                            <div>${message.message}</div>
                            ${message.attachments && message.attachments.length > 0 ? 
                                '<div class="mt-2">' + 
                                message.attachments.map(att => `
                                    <a href="../api/download-attachment.php?id=${att.id}" class="btn btn-sm btn-outline-primary me-1">
                                        <i class="fas fa-download me-1"></i>${att.original_filename}
                                    </a>
                                `).join('') + 
                                '</div>' : ''
                            }
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = messagesHtml;
            container.scrollTop = container.scrollHeight;
        }
        
        async function sendMessage() {
            const messageText = document.getElementById('messageText');
            const message = messageText.value.trim();
            
            if (!message || !currentOrderId) return;
            
            try {
                const response = await fetch('../api/custom-order-messages.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        order_id: currentOrderId,
                        message: message
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    messageText.value = '';
                    await loadMessages();
                } else {
                    throw new Error(data.message || 'Failed to send message');
                }
            } catch (error) {
                alert('Error sending message: ' + error.message);
            }
        }
        
        // Additional info functionality
        function addAdditionalInfo(orderId) {
            document.getElementById('additionalInfoOrderId').value = orderId;
            const modal = new bootstrap.Modal(document.getElementById('additionalInfoModal'));
            modal.show();
        }
        
        async function submitAdditionalInfo() {
            const orderId = document.getElementById('additionalInfoOrderId').value;
            const description = document.getElementById('additionalDescription').value.trim();
            const requirements = document.getElementById('additionalRequirements').value.trim();
            const files = document.getElementById('additionalFiles').files;
            
            if (!description && !requirements && files.length === 0) {
                alert('Please provide at least some additional information.');
                return;
            }
            
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('additional_description', description);
            formData.append('additional_requirements', requirements);
            
            for (let i = 0; i < files.length; i++) {
                formData.append('additional_files[]', files[i]);
            }
            
            try {
                const response = await fetch('../api/add-order-info.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('additionalInfoModal')).hide();
                    showNotification('Additional information submitted successfully!', 'success');
                    
                    // Refresh the current view
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Failed to submit additional information');
                }
            } catch (error) {
                alert('Error submitting information: ' + error.message);
            }
        }
        
        // Utility functions
        function initiatePayment(orderId) {
            // Implement payment functionality
            window.location.href = `../payment/process.php?order_id=${orderId}`;
        }
        
        function duplicateOrder(orderId) {
            if (confirm('Are you sure you want to create a duplicate of this order?')) {
                window.location.href = `../projects.php?duplicate=${orderId}`;
            }
        }
        
        function cancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                fetch('../api/cancel-order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Order cancelled successfully', 'info');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        alert('Error cancelling order: ' + data.message);
                    }
                });
            }
        }
        
        function exportOrderPDF(orderId) {
            window.open(`../api/export-order-pdf.php?id=${orderId}`, '_blank');
        }
        
        function checkForUpdates() {
            // Simple check for notifications or updates
            fetch('../api/check-order-updates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.hasUpdates) {
                        showNotification('You have new order updates available', 'info');
                    }
                })
                .catch(error => console.log('Update check failed:', error));
        }
        
        function showNotification(message, type = 'info') {
            const alertClass = {
                'success': 'alert-success',
                'error': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info'
            }[type] || 'alert-info';
            
            const notification = document.createElement('div');
            notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 5000);
        }
        
        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const iconMap = {
                'pdf': 'fas fa-file-pdf text-danger',
                'doc': 'fas fa-file-word text-primary',
                'docx': 'fas fa-file-word text-primary',
                'xls': 'fas fa-file-excel text-success',
                'xlsx': 'fas fa-file-excel text-success',
                'ppt': 'fas fa-file-powerpoint text-warning',
                'pptx': 'fas fa-file-powerpoint text-warning',
                'jpg': 'fas fa-file-image text-info',
                'jpeg': 'fas fa-file-image text-info',
                'png': 'fas fa-file-image text-info',
                'gif': 'fas fa-file-image text-info',
                'zip': 'fas fa-file-archive text-secondary',
                'rar': 'fas fa-file-archive text-secondary',
                'txt': 'fas fa-file-alt text-muted'
            };
            return iconMap[ext] || 'fas fa-file text-muted';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Add CSS for message bubbles
        const messageStyles = document.createElement('style');
        messageStyles.textContent = `
            .message-bubble {
                padding: 1rem;
                border-radius: 1rem;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .message-admin {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
            }
            .message-user {
                background: #007bff;
                color: white;
                text-align: right;
            }
            .message-user .text-muted {
                color: rgba(255,255,255,0.8) !important;
            }
        `;
        document.head.appendChild(messageStyles);
    </script>
</body>
</html>