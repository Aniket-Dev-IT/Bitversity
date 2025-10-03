<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();

$user = getCurrentUser();

// Get user statistics
$stats = [];

// Total purchases
$stmt = $db->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$_SESSION['user_id']]);
$stats['orders'] = $stmt->fetch()['count'];

// Library items
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM order_items oi 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.user_id = ? AND o.status = 'completed'
");
$stmt->execute([$_SESSION['user_id']]);
$stats['library_items'] = $stmt->fetch()['count'];

// Wishlist items
$stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['wishlist_items'] = $stmt->fetch()['count'];

// Cart items
$stmt = $db->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['cart_items'] = $stmt->fetch()['count'];

// Custom orders (with error handling for missing table)
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM custom_order_requests WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['custom_orders'] = $stmt->fetch()['count'];
    
    // Pending custom orders
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM custom_order_requests WHERE user_id = ? AND status IN ('pending', 'under_review', 'approved')");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['pending_custom_orders'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    // If custom_order_requests table doesn't exist yet, use default values
    $stats['custom_orders'] = 0;
    $stats['pending_custom_orders'] = 0;
}

// Recent purchases
$stmt = $db->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    WHERE o.user_id = ? AND o.status = 'completed'
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recentOrders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <style>
        .user-avatar-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            margin: 0 auto;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .user-avatar-circle .avatar-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .user-avatar-circle i {
            font-size: 3rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .welcome-hero-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 2rem;
            color: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        @media (max-width: 768px) {
            .user-avatar-circle {
                width: 100px;
                height: 100px;
                margin-bottom: 1rem;
            }
            
            .user-avatar-circle i {
                font-size: 2.5rem;
            }
            
            .welcome-hero-card {
                padding: 1.5rem;
                text-align: center;
            }
        }
        
        /* Profile photo loading animation */
        .avatar-image {
            transition: all 0.3s ease;
        }
        
        .avatar-image:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.3);
        }
        
        /* Image loading state */
        .avatar-image[src=""],
        .avatar-image:not([src]) {
            opacity: 0;
        }
        
        .avatar-image[src]:not([src=""]) {
            opacity: 1;
        }
    </style>
</head>
<body data-logged-in="true">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="main-content">
        <div class="container py-4">
            <!-- Enhanced Welcome Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="welcome-hero-card">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center mb-3 mb-md-0">
                                <div class="user-avatar-circle">
                                    <?php if ($user['profile_photo']): ?>
                                        <img src="<?php echo BASE_PATH; ?>/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                             alt="Profile Photo" class="avatar-image">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="welcome-content">
                                    <h1 class="display-6 mb-2 text-white">Welcome back, <?php echo sanitize($user['first_name']); ?></h1>
                                    <p class="text-white-50 mb-3">Ready to continue your learning journey? You have <?php echo $stats['library_items']; ?> items in your library.</p>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <span class="badge bg-light bg-opacity-25 text-white px-3 py-2">
                                            <i class="fas fa-graduation-cap me-2"></i><?php echo $stats['orders']; ?> Completed Orders
                                        </span>
                                        <span class="badge bg-light bg-opacity-25 text-white px-3 py-2">
                                            <i class="fas fa-heart me-2"></i><?php echo $stats['wishlist_items']; ?> Wishlist Items
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="welcome-actions">
                                    <a href="<?php echo BASE_PATH; ?>/books.php" class="btn btn-light btn-lg mb-2 d-block d-sm-inline-block">
                                        <i class="fas fa-book me-2"></i>Browse Content
                                    </a>
                                    <a href="<?php echo BASE_PATH; ?>/user/library.php" class="btn btn-outline-light btn-lg d-block d-sm-inline-block">
                                        <i class="fas fa-book-reader me-2"></i>My Library
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Statistics Cards -->
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-5 g-3 mb-4">
                <div class="col">
                    <div class="stat-card stat-card-primary text-center h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="stat-icon-wrapper mb-3">
                                <i class="fas fa-book-reader fa-2x"></i>
                            </div>
                            <h3 class="stat-number mb-2"><?php echo $stats['library_items']; ?></h3>
                            <p class="stat-label mb-0 mt-auto">Library Items</p>
                            <div class="stat-progress">
                                <div class="progress-bar"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col">
                    <div class="stat-card stat-card-danger text-center h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="stat-icon-wrapper mb-3">
                                <i class="fas fa-heart fa-2x"></i>
                            </div>
                            <h3 class="stat-number mb-2"><?php echo $stats['wishlist_items']; ?></h3>
                            <p class="stat-label mb-0 mt-auto">
                                <a href="<?php echo BASE_PATH; ?>/user/wishlist.php" class="text-decoration-none">Wishlist Items</a>
                            </p>
                            <div class="stat-progress">
                                <div class="progress-bar"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col">
                    <div class="stat-card stat-card-success text-center h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="stat-icon-wrapper mb-3">
                                <i class="fas fa-shopping-cart fa-2x"></i>
                            </div>
                            <h3 class="stat-number mb-2"><?php echo $stats['cart_items']; ?></h3>
                            <p class="stat-label mb-0 mt-auto">
                                <a href="<?php echo BASE_PATH; ?>/user/cart.php" class="text-decoration-none">Cart Items</a>
                            </p>
                            <div class="stat-progress">
                                <div class="progress-bar"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col">
                    <div class="stat-card stat-card-warning text-center h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="stat-icon-wrapper mb-3">
                                <i class="fas fa-hammer fa-2x"></i>
                            </div>
                            <h3 class="stat-number mb-2"><?php echo $stats['custom_orders']; ?></h3>
                            <p class="stat-label mb-0 mt-auto">
                                <a href="<?php echo BASE_PATH; ?>/user/custom-orders.php" class="text-decoration-none">Custom Orders</a>
                            </p>
                            <div class="stat-progress">
                                <div class="progress-bar"></div>
                            </div>
                            <?php if ($stats['pending_custom_orders'] > 0): ?>
                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="badge bg-light text-warning"><?php echo $stats['pending_custom_orders']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col">
                    <div class="stat-card stat-card-info text-center h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="stat-icon-wrapper mb-3">
                                <i class="fas fa-receipt fa-2x"></i>
                            </div>
                            <h3 class="stat-number mb-2"><?php echo $stats['orders']; ?></h3>
                            <p class="stat-label mb-0 mt-auto">
                                <a href="<?php echo BASE_PATH; ?>/user/orders.php" class="text-decoration-none">Total Orders</a>
                            </p>
                            <div class="stat-progress">
                                <div class="progress-bar"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Overview -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="activity-card">
                        <div class="card-body">
                            <h5 class="card-title d-flex align-items-center mb-3">
                                <i class="fas fa-chart-line text-success me-2"></i>
                                Your Progress
                            </h5>
                            
                            <div class="progress-item mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Library Building</span>
                                    <span class="badge bg-primary"><?php echo $stats['library_items']; ?> items</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo min(($stats['library_items'] / 10) * 100, 100); ?>%;"></div>
                                </div>
                                <small class="text-muted">Goal: 10 items</small>
                            </div>
                            
                            <div class="progress-item mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Orders Completed</span>
                                    <span class="badge bg-success"><?php echo $stats['orders']; ?> orders</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo min(($stats['orders'] / 5) * 100, 100); ?>%;"></div>
                                </div>
                                <small class="text-muted">Goal: 5 orders</small>
                            </div>
                            
                            <?php if ($stats['library_items'] >= 10): ?>
                            <div class="achievement-badge">
                                <i class="fas fa-trophy text-warning"></i>
                                <span class="ms-2">Library Master achieved!</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="activity-card">
                        <div class="card-body">
                            <h5 class="card-title d-flex align-items-center mb-3">
                                <i class="fas fa-star text-warning me-2"></i>
                                Recommendations
                            </h5>
                            
                            <?php if ($stats['library_items'] > 0): ?>
                            <div class="recommendation-item mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="rec-icon bg-primary text-white">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="mb-1">Expand Your Collection</h6>
                                        <p class="text-muted mb-0 small">Based on your purchases</p>
                                    </div>
                                    <a href="<?php echo BASE_PATH; ?>/books.php" class="btn btn-sm btn-outline-primary ms-auto">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($stats['wishlist_items'] > 0): ?>
                            <div class="recommendation-item mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="rec-icon bg-danger text-white">
                                        <i class="fas fa-heart"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="mb-1">Complete Your Wishlist</h6>
                                        <p class="text-muted mb-0 small"><?php echo $stats['wishlist_items']; ?> items waiting</p>
                                    </div>
                                    <a href="<?php echo BASE_PATH; ?>/user/wishlist.php" class="btn btn-sm btn-outline-danger ms-auto">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($stats['cart_items'] > 0): ?>
                            <div class="recommendation-item mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="rec-icon bg-success text-white">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="mb-1">Complete Your Purchase</h6>
                                        <p class="text-muted mb-0 small"><?php echo $stats['cart_items']; ?> items in cart</p>
                                    </div>
                                    <a href="<?php echo BASE_PATH; ?>/user/cart.php" class="btn btn-sm btn-outline-success ms-auto">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($stats['library_items'] == 0): ?>
                            <div class="recommendation-item">
                                <div class="d-flex align-items-center">
                                    <div class="rec-icon bg-info text-white">
                                        <i class="fas fa-rocket"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="mb-1">Start Your Journey</h6>
                                        <p class="text-muted mb-0 small">Discover amazing content</p>
                                    </div>
                                    <a href="<?php echo BASE_PATH; ?>/books.php" class="btn btn-sm btn-outline-info ms-auto">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <?php if (!empty($recentOrders)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0"><i class="fas fa-clock text-primary me-2"></i>Recent Orders</h4>
                            <a href="<?php echo BASE_PATH; ?>/user/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['order_number']; ?></strong></td>
                                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                        <td><?php echo $order['item_count']; ?> items</td>
                                        <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-success">Completed</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <style>
        /* Enhanced Dashboard Styling */
        .welcome-hero-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-hero-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            z-index: 1;
        }
        
        .user-avatar-circle {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 2rem;
            color: white;
            border: 3px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }
        
        .user-avatar-circle .avatar-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .dashboard-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-card-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .stat-card-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .stat-card-info {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .stat-card-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
            color: white;
        }
        
        .stat-icon-wrapper {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            backdrop-filter: blur(10px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .stat-label a {
            color: inherit;
        }
        
        .stat-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
        }
        
        .stat-progress .progress-bar {
            height: 100%;
            background: rgba(255, 255, 255, 0.5);
            width: 0;
            animation: progressLoad 2s ease-out forwards;
        }
        
        @keyframes progressLoad {
            from { width: 0; }
            to { width: 100%; }
        }
        
        .activity-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            background: white;
            height: 100%;
        }
        
        .activity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .progress-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .recommendation-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        
        .recommendation-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .rec-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .achievement-badge {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
        }
        
        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        /* Ensure icons display properly */
        .fas, .far, .fab {
            font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important;
            font-weight: 900;
        }
        
        /* Fix navbar collapse for mobile */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: rgba(13, 110, 253, 0.95);
                border-radius: 8px;
                padding: 1rem;
                margin-top: 0.5rem;
            }
            
            .navbar-nav .nav-link {
                padding: 0.75rem 1rem;
                border-radius: 6px;
                margin: 0.2rem 0;
            }
            
            .navbar-nav .nav-link:hover {
                background: rgba(255, 255, 255, 0.1);
            }
        }
        
        /* Ensure proper card height */
        .h-100 .card-body {
            min-height: 120px;
        }
        
        /* Better spacing for Quick Actions */
        .btn.py-3 {
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        /* Fix table responsive issues */
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        /* Better mobile spacing */
        @media (max-width: 576px) {
            .display-6 {
                font-size: 1.5rem;
            }
            
            .dashboard-card {
                padding: 1rem;
            }
            
            .btn.py-3 {
                min-height: 60px;
                padding: 0.75rem !important;
            }
            
            .fa-lg {
                font-size: 1.2em;
            }
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/main.js'); ?>"></script>
    
    <script>
        // Ensure navbar works properly
        document.addEventListener('DOMContentLoaded', function() {
            // Fix for navbar collapse
            var navbarToggler = document.querySelector('.navbar-toggler');
            var navbarCollapse = document.querySelector('.navbar-collapse');
            
            if (navbarToggler && navbarCollapse) {
                navbarToggler.addEventListener('click', function() {
                    // Force show/hide if Bootstrap doesn't initialize properly
                    if (navbarCollapse.classList.contains('show')) {
                        navbarCollapse.classList.remove('show');
                    } else {
                        navbarCollapse.classList.add('show');
                    }
                });
            }
            
            // Check if FontAwesome icons are loading
            setTimeout(function() {
                var icons = document.querySelectorAll('.fas, .far, .fab');
                icons.forEach(function(icon) {
                    if (getComputedStyle(icon).fontFamily.indexOf('Font Awesome') === -1) {
                        console.warn('FontAwesome icons may not be loading properly');
                    }
                });
            }, 1000);
        });
    </script>
</body>
</html>
