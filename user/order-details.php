<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();

// Get order ID from URL
$orderId = intval($_GET['id'] ?? 0);
if (!$orderId) {
    redirect(BASE_PATH . '/user/orders.php', 'Invalid order ID', 'error');
}

// Get order details with items
$orderSql = "SELECT o.*, 
                    u.first_name, u.last_name, u.email,
                    c.code as coupon_code, c.name as coupon_name, c.discount_type, c.discount_value
             FROM orders o
             JOIN users u ON o.user_id = u.id
             LEFT JOIN coupons c ON o.promo_code = c.code
             WHERE o.id = ? AND o.user_id = ?";

$orderStmt = $db->prepare($orderSql);
$orderStmt->execute([$orderId, $_SESSION['user_id']]);
$order = $orderStmt->fetch();

if (!$order) {
    redirect(BASE_PATH . '/user/orders.php', 'Order not found', 'error');
}

// Get order items
$itemsSql = "SELECT oi.*, 
                    CASE 
                        WHEN oi.item_type = 'book' THEN b.cover_image
                        WHEN oi.item_type = 'project' THEN p.cover_image
                        WHEN oi.item_type = 'game' THEN g.thumbnail
                    END as image,
                    CASE 
                        WHEN oi.item_type = 'book' THEN b.file_path
                        WHEN oi.item_type = 'project' THEN p.file_path
                        WHEN oi.item_type = 'game' THEN g.file_path
                    END as download_path,
                    CASE 
                        WHEN oi.item_type = 'book' THEN b.author
                        WHEN oi.item_type = 'project' THEN 'Project'
                        WHEN oi.item_type = 'game' THEN 'Game'
                    END as author
             FROM order_items oi
             LEFT JOIN books b ON oi.item_type = 'book' AND oi.item_id = b.id
             LEFT JOIN projects p ON oi.item_type = 'project' AND oi.item_id = p.id
             LEFT JOIN games g ON oi.item_type = 'game' AND oi.item_id = g.id
             WHERE oi.order_id = ?";

$itemsStmt = $db->prepare($itemsSql);
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

// Order status timeline
$statusTimeline = [
    'pending' => [
        'title' => 'Order Placed',
        'description' => 'Your order has been received and is awaiting processing',
        'icon' => 'fas fa-shopping-cart',
        'color' => 'warning'
    ],
    'processing' => [
        'title' => 'Processing',
        'description' => 'Your order is being prepared for delivery',
        'icon' => 'fas fa-cog',
        'color' => 'info'
    ],
    'completed' => [
        'title' => 'Completed',
        'description' => 'Your order is ready for download',
        'icon' => 'fas fa-check-circle',
        'color' => 'success'
    ],
    'cancelled' => [
        'title' => 'Cancelled',
        'description' => 'Your order has been cancelled',
        'icon' => 'fas fa-times-circle',
        'color' => 'danger'
    ]
];

$currentStatus = $order['status'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?> - <?php echo APP_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <style>
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .status-timeline {
            position: relative;
        }
        
        .timeline-item {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 25px;
            top: 50px;
            width: 2px;
            height: 30px;
            background-color: #dee2e6;
        }
        
        .timeline-item.active::after {
            background-color: #28a745;
        }
        
        .timeline-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            z-index: 1;
        }
        
        .order-item {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .order-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .item-image {
            width: 80px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .download-btn {
            transition: all 0.3s ease;
        }
        
        .download-btn:hover {
            transform: scale(1.05);
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
        }
    </style>
</head>
<body data-logged-in="true">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="main-content">
        <div class="container py-4">
            <!-- Order Header -->
            <div class="order-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2">
                            <i class="fas fa-receipt me-3"></i>
                            Order #<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?>
                        </h1>
                        <p class="mb-0">
                            <i class="fas fa-calendar me-2"></i>
                            Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <span class="badge bg-<?php echo $statusTimeline[$currentStatus]['color']; ?> fs-6 px-3 py-2">
                            <i class="<?php echo $statusTimeline[$currentStatus]['icon']; ?> me-2"></i>
                            <?php echo ucfirst($currentStatus); ?>
                        </span>
                        <div class="mt-2">
                            <h4 class="mb-0"><?php echo formatPrice($order['total_amount']); ?></h4>
                            <?php if ($order['discount_amount'] > 0): ?>
                            <small>Saved: <?php echo formatPrice($order['discount_amount']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Order Timeline -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-route me-2"></i>Order Tracking
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="status-timeline">
                                <?php foreach ($statusTimeline as $status => $info): ?>
                                <?php 
                                $isActive = false;
                                $isCompleted = false;
                                
                                if ($currentStatus === $status) {
                                    $isActive = true;
                                } elseif ($currentStatus === 'completed' && in_array($status, ['pending', 'processing'])) {
                                    $isCompleted = true;
                                } elseif ($currentStatus === 'processing' && $status === 'pending') {
                                    $isCompleted = true;
                                }
                                
                                $iconClass = $isActive ? "bg-{$info['color']} text-white" : 
                                            ($isCompleted ? "bg-success text-white" : "bg-light text-muted");
                                ?>
                                <div class="timeline-item <?php echo $isActive || $isCompleted ? 'active' : ''; ?>">
                                    <div class="timeline-icon <?php echo $iconClass; ?>">
                                        <i class="<?php echo $info['icon']; ?>"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1 <?php echo $isActive ? "text-{$info['color']}" : ''; ?>">
                                            <?php echo $info['title']; ?>
                                        </h6>
                                        <p class="text-muted small mb-0"><?php echo $info['description']; ?></p>
                                        <?php if ($isActive): ?>
                                        <small class="text-success">
                                            <i class="fas fa-clock me-1"></i>Current Status
                                        </small>
                                        <?php elseif ($isCompleted): ?>
                                        <small class="text-success">
                                            <i class="fas fa-check me-1"></i>Completed
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if ($currentStatus === 'pending'): ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Awaiting Admin Approval</strong><br>
                                Your order will be processed within 24 hours. You'll receive an email notification when it's ready for download.
                            </div>
                            <?php elseif ($currentStatus === 'completed'): ?>
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Ready for Download!</strong><br>
                                Your digital items are now available for download below.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-box me-2"></i>Order Items (<?php echo count($orderItems); ?>)
                            </h5>
                            <?php if ($currentStatus === 'completed'): ?>
                            <a href="<?php echo BASE_PATH; ?>/user/library.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-book-open me-1"></i>Go to Library
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php foreach ($orderItems as $item): ?>
                            <div class="order-item">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <?php 
                                        $default = 'https://via.placeholder.com/80x100/f8f9fa/6c757d?text=' . strtoupper($item['item_type'][0]);
                                        $imageSrc = $item['image'] ? upload($item['item_type'] . 's/' . $item['image']) : $default;
                                        ?>
                                        <img src="<?php echo $imageSrc; ?>" class="item-image" alt="<?php echo sanitize($item['item_title']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-1"><?php echo sanitize($item['item_title']); ?></h6>
                                        <p class="text-muted small mb-1"><?php echo sanitize($item['author']); ?></p>
                                        <div class="d-flex gap-2">
                                            <span class="badge bg-<?php 
                                                echo match($item['item_type']) {
                                                    'book' => 'primary',
                                                    'project' => 'success',
                                                    'game' => 'warning',
                                                    default => 'secondary'
                                                };
                                            ?>"><?php echo ucfirst($item['item_type']); ?></span>
                                            <span class="badge bg-light text-dark">Qty: <?php echo $item['quantity']; ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        <div class="fw-bold"><?php echo formatPrice($item['unit_price']); ?></div>
                                        <small class="text-muted">per item</small>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <?php if ($currentStatus === 'completed' && $item['download_path']): ?>
                                        <a href="<?php echo BASE_PATH; ?>/user/download.php?type=<?php echo $item['item_type']; ?>&id=<?php echo $item['item_id']; ?>" 
                                           class="btn btn-success btn-sm download-btn">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled>
                                            <i class="fas fa-lock"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calculator me-2"></i>Order Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="order-summary">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal:</span>
                                            <span><?php echo formatPrice($order['total_amount'] + $order['discount_amount']); ?></span>
                                        </div>
                                        
                                        <?php if ($order['discount_amount'] > 0): ?>
                                        <div class="d-flex justify-content-between mb-2 text-success">
                                            <span>
                                                <i class="fas fa-tag me-1"></i>
                                                Discount <?php if ($order['promo_code'] || $order['coupon_code']): ?>(<?php echo $order['coupon_code'] ?? $order['promo_code']; ?>)<?php endif; ?>:
                                            </span>
                                            <span>-<?php echo formatPrice($order['discount_amount']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <hr>
                                        <div class="d-flex justify-content-between fw-bold fs-5">
                                            <span>Total:</span>
                                            <span class="text-primary"><?php echo formatPrice($order['total_amount']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="order-summary">
                                        <h6 class="mb-3">
                                            <i class="fas fa-info-circle me-2"></i>Order Information
                                        </h6>
                                        <div class="mb-2">
                                            <strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method']); ?>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Order Status:</strong> 
                                            <span class="text-<?php echo $statusTimeline[$currentStatus]['color']; ?>">
                                                <?php echo ucfirst($currentStatus); ?>
                                            </span>
                                        </div>
                                        <?php if ($order['promo_code'] || $order['coupon_code']): ?>
                                        <div class="mb-2">
                                            <strong>Coupon Applied:</strong> 
                                            <span class="badge bg-success"><?php echo $order['coupon_code'] ?? $order['promo_code']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong>Order Date:</strong> <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="row mt-4">
                <div class="col-12 text-center">
                    <div class="d-flex flex-wrap gap-3 justify-content-center">
                        <a href="<?php echo BASE_PATH; ?>/user/orders.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Orders
                        </a>
                        
                        <?php if ($currentStatus === 'completed'): ?>
                        <a href="<?php echo BASE_PATH; ?>/user/library.php" class="btn btn-success">
                            <i class="fas fa-download me-2"></i>Download Items
                        </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo BASE_PATH; ?>/public/" class="btn btn-primary">
                            <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                        </a>
                        
                        <?php if ($currentStatus === 'pending'): ?>
                        <button class="btn btn-outline-danger" onclick="cancelOrder()">
                            <i class="fas fa-times me-2"></i>Cancel Order
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/main.js'); ?>"></script>
    
    <script>
        function cancelOrder() {
            if (confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                // Implementation for order cancellation
                fetch('<?php echo BASE_PATH; ?>/api/cancel-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: <?php echo $orderId; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to cancel order: ' + data.message);
                    }
                });
            }
        }
        
        // Add download progress indication
        document.querySelectorAll('.download-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const originalHtml = this.innerHTML;
                
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                this.disabled = true;
                
                setTimeout(() => {
                    window.location.href = this.href;
                    
                    setTimeout(() => {
                        this.innerHTML = originalHtml;
                        this.disabled = false;
                    }, 2000);
                }, 1000);
            });
        });
    </script>
</body>
</html>