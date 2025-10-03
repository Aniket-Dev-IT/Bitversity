<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();

// Pagination settings
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Status filter
$statusFilter = $_GET['status'] ?? 'all';
$validStatuses = ['all', 'pending', 'completed', 'cancelled'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'all';
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM orders WHERE user_id = ?";
$countParams = [$_SESSION['user_id']];

if ($statusFilter !== 'all') {
    $countSql .= " AND status = ?";
    $countParams[] = $statusFilter;
}

$countStmt = $db->prepare($countSql);
$countStmt->execute($countParams);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $limit);

// Get orders
$ordersSql = "SELECT o.*, 
                     COUNT(oi.id) as item_count,
                     SUM(oi.quantity) as total_items
              FROM orders o
              LEFT JOIN order_items oi ON o.id = oi.order_id
              WHERE o.user_id = ?";

$orderParams = [$_SESSION['user_id']];

if ($statusFilter !== 'all') {
    $ordersSql .= " AND o.status = ?";
    $orderParams[] = $statusFilter;
}

$ordersSql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$orderParams[] = $limit;
$orderParams[] = $offset;

$orderStmt = $db->prepare($ordersSql);
$orderStmt->execute($orderParams);
$orders = $orderStmt->fetchAll();

// Get order statistics
$statsSql = "SELECT 
                 COUNT(*) as total_orders,
                 SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                 SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                 SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                 COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) as total_spent
             FROM orders 
             WHERE user_id = ?";

$statsStmt = $db->prepare($statsSql);
$statsStmt->execute([$_SESSION['user_id']]);
$stats = $statsStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - <?php echo APP_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <style>
        .order-card {
            border: none;
            border-radius: 15px;
            transition: all 0.4s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.07);
            background: white;
            position: relative;
            overflow: hidden;
        }
        
        .order-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .order-card:hover::before {
            transform: scaleX(1);
        }
        
        .order-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transform: translateY(-4px);
        }
        
        .status-badge {
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .filter-badge:hover {
            transform: scale(1.05);
            text-decoration: none;
        }
        
        .filter-badge.active {
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        
        .action-card {
            border: none;
            border-radius: 15px;
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }
        
        .action-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .action-card:hover::before {
            left: 100%;
        }
        
        .summary-bar {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            border: 1px solid #dee2e6;
        }
        
        .summary-item {
            margin: 0.25rem 0;
        }
        
        @media (max-width: 768px) {
            .summary-bar .d-flex {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body data-logged-in="true">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="main-content">
        <div class="container py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="display-6">
                        <i class="fas fa-history text-info me-3"></i>Order History
                    </h1>
                    <p class="text-muted">Track and manage all your orders</p>
                </div>
            </div>

            <!-- Order Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <a href="?status=completed" class="text-decoration-none">
                        <div class="action-card bg-success">
                            <div class="card-body text-center text-white p-4">
                                <i class="fas fa-download fa-2x mb-2"></i>
                                <h4><?php echo $stats['completed_orders']; ?></h4>
                                <p class="mb-0">Ready to Download</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="?status=pending" class="text-decoration-none">
                        <div class="action-card bg-warning">
                            <div class="card-body text-center text-white p-4">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h4><?php echo $stats['pending_orders']; ?></h4>
                                <p class="mb-0">Awaiting Payment</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="<?php echo BASE_PATH; ?>/user/library.php" class="text-decoration-none">
                        <div class="action-card bg-primary">
                            <div class="card-body text-center text-white p-4">
                                <i class="fas fa-book-open fa-2x mb-2"></i>
                                <h4>My Library</h4>
                                <p class="mb-0">Access Downloads</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="<?php echo BASE_PATH; ?>/books.php" class="text-decoration-none">
                        <div class="action-card bg-info">
                            <div class="card-body text-center text-white p-4">
                                <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                <h4>Shop More</h4>
                                <p class="mb-0">Browse Catalog</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="summary-bar">
                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                            <div class="summary-item">
                                <span class="text-muted">Total Orders:</span>
                                <strong class="text-dark ms-2"><?php echo $stats['total_orders']; ?></strong>
                            </div>
                            <div class="summary-item">
                                <span class="text-muted">Total Spent:</span>
                                <strong class="text-success ms-2"><?php echo formatPrice($stats['total_spent']); ?></strong>
                            </div>
                            <div class="summary-item">
                                <span class="text-muted">Success Rate:</span>
                                <strong class="text-info ms-2">
                                    <?php echo $stats['total_orders'] > 0 ? round(($stats['completed_orders'] / $stats['total_orders']) * 100) : 0; ?>%
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="bg-light rounded p-3">
                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <span class="fw-bold">Filter by status:</span>
                            
                            <a href="?status=all&page=<?php echo $page; ?>" class="filter-badge">
                                <span class="badge bg-secondary <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                                    All <span class="badge bg-light text-dark ms-1"><?php echo $stats['total_orders']; ?></span>
                                </span>
                            </a>
                            
                            <a href="?status=completed&page=1" class="filter-badge">
                                <span class="badge bg-success <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>">
                                    Completed <span class="badge bg-light text-dark ms-1"><?php echo $stats['completed_orders']; ?></span>
                                </span>
                            </a>
                            
                            <a href="?status=pending&page=1" class="filter-badge">
                                <span class="badge bg-warning <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
                                    Pending <span class="badge bg-light text-dark ms-1"><?php echo $stats['pending_orders']; ?></span>
                                </span>
                            </a>
                            
                            <a href="?status=cancelled&page=1" class="filter-badge">
                                <span class="badge bg-danger <?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>">
                                    Cancelled <span class="badge bg-light text-dark ms-1"><?php echo $stats['cancelled_orders']; ?></span>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($orders)): ?>
            <!-- No Orders -->
            <div class="row">
                <div class="col-12">
                    <div class="text-center py-5">
                        <?php if ($statusFilter !== 'all'): ?>
                            <i class="fas fa-filter fa-4x text-muted mb-4"></i>
                            <h3>No <?php echo $statusFilter; ?> orders found</h3>
                            <p class="text-muted mb-4">You don't have any <?php echo $statusFilter; ?> orders yet</p>
                            <a href="?status=all" class="btn btn-primary">
                                <i class="fas fa-list me-2"></i>View All Orders
                            </a>
                        <?php else: ?>
                            <i class="fas fa-shopping-bag fa-4x text-muted mb-4"></i>
                            <h3>No orders yet</h3>
                            <p class="text-muted mb-4">You haven't placed any orders yet. Start shopping to build your digital library!</p>
                            <div class="d-flex flex-wrap gap-3 justify-content-center">
                                <a href="<?php echo BASE_PATH; ?>/public/books.php" class="btn btn-primary">
                                    <i class="fas fa-book me-2"></i>Browse Books
                                </a>
                                <a href="<?php echo BASE_PATH; ?>/public/projects.php" class="btn btn-success">
                                    <i class="fas fa-code me-2"></i>Explore Projects
                                </a>
                                <a href="<?php echo BASE_PATH; ?>/public/games.php" class="btn btn-warning">
                                    <i class="fas fa-gamepad me-2"></i>Play Games
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Orders List -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>
                            <?php if ($statusFilter === 'all'): ?>
                                All Orders (<?php echo $totalOrders; ?>)
                            <?php else: ?>
                                <?php echo ucfirst($statusFilter); ?> Orders (<?php echo $totalOrders; ?>)
                            <?php endif; ?>
                        </h5>
                        <small class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></small>
                    </div>
                    
                    <?php foreach ($orders as $order): ?>
                    <div class="order-card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <!-- Order Info -->
                                <div class="col-md-3">
                                    <h6 class="mb-1">Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h6>
                                    <p class="text-muted small mb-1">
                                        <?php echo date('M j, Y \a\t g:i A', strtotime($order['created_at'])); ?>
                                    </p>
                                    <span class="status-badge badge bg-<?php 
                                        echo match($order['status']) {
                                            'completed' => 'success',
                                            'pending' => 'warning',
                                            'cancelled' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                                
                                <!-- Order Details -->
                                <div class="col-md-3">
                                    <p class="mb-1">
                                        <i class="fas fa-box me-2"></i>
                                        <?php echo $order['total_items']; ?> item<?php echo $order['total_items'] > 1 ? 's' : ''; ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-credit-card me-2"></i>
                                        <?php echo ucfirst($order['payment_method']); ?>
                                    </p>
                                </div>
                                
                                <!-- Amount -->
                                <div class="col-md-2 text-center">
                                    <h5 class="text-primary mb-0"><?php echo formatPrice($order['total_amount']); ?></h5>
                                    <?php if ($order['discount_amount'] > 0): ?>
                                    <small class="text-success">-<?php echo formatPrice($order['discount_amount']); ?> saved</small>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Actions -->
                                <div class="col-md-4 text-end">
                                    <div class="btn-group" role="group">
                                        <a href="<?php echo BASE_PATH; ?>/user/order-details.php?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                        
                                        <?php if ($order['status'] === 'completed'): ?>
                                        <a href="<?php echo BASE_PATH; ?>/user/library.php" 
                                           class="btn btn-success btn-sm">
                                            <i class="fas fa-download me-1"></i>Downloads
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['status'] === 'pending'): ?>
                                        <a href="<?php echo BASE_PATH; ?>/user/checkout.php" 
                                           class="btn btn-warning btn-sm">
                                            <i class="fas fa-credit-card me-1"></i>Pay Now
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Order Items Preview -->
                            <?php
                            $itemsSql = "SELECT oi.item_title, oi.item_type, oi.quantity FROM order_items oi WHERE oi.order_id = ? LIMIT 3";
                            $itemsStmt = $db->prepare($itemsSql);
                            $itemsStmt->execute([$order['id']]);
                            $orderItems = $itemsStmt->fetchAll();
                            ?>
                            
                            <?php if (!empty($orderItems)): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="border-top pt-3">
                                        <h6 class="text-muted mb-2">Items:</h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($orderItems as $item): ?>
                                            <span class="badge bg-light text-dark">
                                                <span class="badge bg-<?php 
                                                    echo match($item['item_type']) {
                                                        'book' => 'primary',
                                                        'project' => 'success',
                                                        'game' => 'warning',
                                                        default => 'secondary'
                                                    };
                                                ?> me-1"><?php echo $item['quantity']; ?></span>
                                                <?php echo sanitize($item['item_title']); ?>
                                            </span>
                                            <?php endforeach; ?>
                                            
                                            <?php if ($order['item_count'] > 3): ?>
                                            <span class="text-muted small">
                                                +<?php echo $order['item_count'] - 3; ?> more...
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="row">
                <div class="col-12">
                    <nav aria-label="Order history pagination">
                        <ul class="pagination justify-content-center">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?status=<?php echo $statusFilter; ?>&page=<?php echo $page - 1; ?>">
                                    <i class="fas fa-chevron-left me-1"></i>Previous
                                </a>
                            </li>
                            
                            <!-- Page Numbers -->
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?status=<?php echo $statusFilter; ?>&page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <!-- Next Page -->
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?status=<?php echo $statusFilter; ?>&page=<?php echo $page + 1; ?>">
                                    Next<i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/main.js'); ?>"></script>
</body>
</html>