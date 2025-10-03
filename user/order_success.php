<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();

$orderId = intval($_GET['order'] ?? 0);

if (!$orderId) {
    redirect(BASE_PATH . '/user/orders.php', 'Invalid order ID', 'error');
}

// Get order details with coupon information
$orderSql = "SELECT o.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    u.email as user_email,
                    c.code as coupon_code, c.name as coupon_name
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

// Get order items with detailed information
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
                        WHEN oi.item_type = 'project' THEN 'Digital Project'
                        WHEN oi.item_type = 'game' THEN 'Game'
                    END as author_info
             FROM order_items oi
             LEFT JOIN books b ON oi.item_type = 'book' AND oi.item_id = b.id
             LEFT JOIN projects p ON oi.item_type = 'project' AND oi.item_id = p.id
             LEFT JOIN games g ON oi.item_type = 'game' AND oi.item_id = g.id
             WHERE oi.order_id = ?";

$itemsStmt = $db->prepare($itemsSql);
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

// Order status information
$statusInfo = [
    'pending' => [
        'title' => 'Order Under Review',
        'message' => 'Your order is currently being reviewed by our team. You\'ll receive an email notification once it\'s approved.',
        'icon' => 'fas fa-clock',
        'color' => 'warning',
        'estimated_time' => 'Usually within 24 hours',
        'next_step' => 'Admin approval required'
    ],
    'processing' => [
        'title' => 'Processing Your Order',
        'message' => 'Great! Your order is being processed and your digital items are being prepared.',
        'icon' => 'fas fa-cog fa-spin',
        'color' => 'info',
        'estimated_time' => 'Usually within 2-4 hours',
        'next_step' => 'Preparing your downloads'
    ],
    'completed' => [
        'title' => 'Order Complete - Ready for Download!',
        'message' => 'Excellent! Your order is complete and all items are ready for download.',
        'icon' => 'fas fa-check-circle',
        'color' => 'success',
        'estimated_time' => 'Available now',
        'next_step' => 'Download your items below'
    ],
    'cancelled' => [
        'title' => 'Order Cancelled',
        'message' => 'This order has been cancelled. If you have any questions, please contact our support team.',
        'icon' => 'fas fa-times-circle',
        'color' => 'danger',
        'estimated_time' => 'N/A',
        'next_step' => 'Contact support if needed'
    ]
];

$currentStatus = $statusInfo[$order['status']] ?? $statusInfo['pending'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - <?php echo APP_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <style>
        .order-success-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .status-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .order-timeline {
            position: relative;
            padding: 2rem 0;
        }
        
        .timeline-step {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .timeline-step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 25px;
            top: 50px;
            width: 2px;
            height: 50px;
            background-color: #dee2e6;
        }
        
        .timeline-step.active::after {
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
        
        .order-summary-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .download-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .download-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .next-steps-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
        }
        
        .tracking-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .estimated-time {
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            display: inline-block;
            margin-top: 1rem;
        }
    </style>
</head>
<body data-logged-in="true">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="main-content">
        <div class="container py-4">
            <!-- Order Success Header -->
            <div class="order-success-header">
                <i class="<?php echo $currentStatus['icon']; ?> status-icon <?php echo $order['status'] === 'pending' ? 'pulse-animation' : ''; ?>"></i>
                <h1 class="display-5 mb-3"><?php echo $currentStatus['title']; ?></h1>
                <p class="lead mb-3"><?php echo $currentStatus['message']; ?></p>
                <div class="d-flex justify-content-center align-items-center gap-4 flex-wrap">
                    <div class="bg-white bg-opacity-20 rounded p-3">
                        <strong>Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                    </div>
                    <div class="estimated-time">
                        <i class="fas fa-clock me-2"></i>
                        <strong><?php echo $currentStatus['estimated_time']; ?></strong>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Order Tracking Timeline -->
                    <div class="order-summary-card">
                        <h5 class="mb-4">
                            <i class="fas fa-route me-2"></i>Order Progress
                        </h5>
                        
                        <div class="order-timeline">
                            <?php 
                            $timelineSteps = [
                                'pending' => ['Order Placed', 'Order received and queued for review'],
                                'processing' => ['Processing', 'Order approved and being prepared'],
                                'completed' => ['Ready for Download', 'All items available for download']
                            ];
                            
                            $currentStepIndex = array_search($order['status'], array_keys($timelineSteps));
                            $stepIndex = 0;
                            
                            foreach ($timelineSteps as $status => $stepInfo):
                                $isActive = $stepIndex <= $currentStepIndex;
                                $isCurrent = $status === $order['status'];
                            ?>
                            <div class="timeline-step <?php echo $isActive ? 'active' : ''; ?>">
                                <div class="timeline-icon <?php echo $isActive ? 'bg-success text-white' : 'bg-light text-muted'; ?>">
                                    <?php if ($isCurrent): ?>
                                    <i class="fas fa-clock"></i>
                                    <?php elseif ($isActive): ?>
                                    <i class="fas fa-check"></i>
                                    <?php else: ?>
                                    <i class="fas fa-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h6 class="mb-1 <?php echo $isCurrent ? 'text-primary' : ''; ?>">
                                        <?php echo $stepInfo[0]; ?>
                                        <?php if ($isCurrent): ?>
                                        <span class="badge bg-primary ms-2">Current</span>
                                        <?php endif; ?>
                                    </h6>
                                    <p class="text-muted small mb-0"><?php echo $stepInfo[1]; ?></p>
                                </div>
                            </div>
                            <?php 
                            $stepIndex++;
                            endforeach; 
                            ?>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="order-summary-card">
                        <h5 class="mb-4">
                            <i class="fas fa-box me-2"></i>Your Items (<?php echo count($orderItems); ?>)
                        </h5>
                        
                        <?php foreach ($orderItems as $item): ?>
                        <div class="download-item">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <?php 
                                    $default = 'https://via.placeholder.com/100x120/' . substr(md5($item['item_type']), 0, 6) . '/ffffff?text=' . strtoupper($item['item_type'][0]);
                                    $imageSrc = $item['image'] ? upload($item['item_type'] . 's/' . $item['image']) : $default;
                                    ?>
                                    <img src="<?php echo $imageSrc; ?>" 
                                         class="img-fluid rounded" 
                                         alt="<?php echo sanitize($item['item_title']); ?>"
                                         style="height: 80px; width: 65px; object-fit: cover;">
                                </div>
                                <div class="col-md-5">
                                    <h6 class="mb-1"><?php echo sanitize($item['item_title']); ?></h6>
                                    <p class="text-muted mb-1"><?php echo sanitize($item['author_info']); ?></p>
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
                                    <strong><?php echo formatPrice($item['unit_price']); ?></strong>
                                    <?php if ($item['quantity'] > 1): ?>
                                    <br><small class="text-muted">each</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3 text-end">
                                    <?php if ($order['status'] === 'completed'): ?>
                                        <?php if ($item['download_path']): ?>
                                        <a href="<?php echo BASE_PATH; ?>/user/download.php?type=<?php echo $item['item_type']; ?>&id=<?php echo $item['item_id']; ?>" 
                                           class="btn btn-success">
                                            <i class="fas fa-download me-1"></i>Download Now
                                        </a>
                                        <?php else: ?>
                                        <button class="btn btn-secondary" disabled>
                                            <i class="fas fa-clock me-1"></i>Preparing...
                                        </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary" disabled>
                                            <i class="fas fa-lock me-1"></i>Awaiting Approval
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Order Summary -->
                    <div class="order-summary-card">
                        <h5 class="mb-4">
                            <i class="fas fa-receipt me-2"></i>Order Summary
                        </h5>
                        
                        <div class="mb-3">
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
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping:</span>
                                <span class="text-success">FREE</span>
                            </div>
                            
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Total Paid:</strong>
                                <strong class="text-primary"><?php echo formatPrice($order['total_amount']); ?></strong>
                            </div>
                        </div>
                        
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6 class="mb-2">Order Information</h6>
                            <small class="d-block mb-1"><strong>Order Date:</strong> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></small>
                            <small class="d-block mb-1"><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method']); ?></small>
                            <small class="d-block"><strong>Email:</strong> <?php echo sanitize($order['user_email']); ?></small>
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div class="next-steps-card">
                        <h5 class="mb-3">
                            <i class="fas fa-arrow-right me-2"></i>What's Next?
                        </h5>
                        
                        <?php if ($order['status'] === 'pending'): ?>
                        <div class="mb-3">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Awaiting Admin Approval</strong>
                        </div>
                        <p class="mb-3">Your order is currently under review. Once approved, you'll receive an email notification and can download your items.</p>
                        <ul class="mb-3">
                            <li>You'll receive an email confirmation</li>
                            <li>Admin will review within 24 hours</li>
                            <li>Downloads will be enabled once approved</li>
                        </ul>
                        
                        <?php elseif ($order['status'] === 'processing'): ?>
                        <div class="mb-3">
                            <i class="fas fa-cog fa-spin me-2"></i>
                            <strong>Order Being Processed</strong>
                        </div>
                        <p class="mb-3">Great news! Your order has been approved and is being prepared for download.</p>
                        <ul class="mb-3">
                            <li>Your items are being prepared</li>
                            <li>Downloads usually ready within 2-4 hours</li>
                            <li>You'll be notified when ready</li>
                        </ul>
                        
                        <?php elseif ($order['status'] === 'completed'): ?>
                        <div class="mb-3">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Ready for Download!</strong>
                        </div>
                        <p class="mb-3">Perfect! All your items are ready for download. You can access them anytime from your library.</p>
                        <div class="d-grid gap-2">
                            <a href="<?php echo BASE_PATH; ?>/user/library.php" class="btn btn-light">
                                <i class="fas fa-book-open me-2"></i>Go to My Library
                            </a>
                        </div>
                        
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <div class="order-summary-card">
                        <h6 class="mb-3">Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <a href="<?php echo BASE_PATH; ?>/user/order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>View Full Order Details
                            </a>
                            <a href="<?php echo BASE_PATH; ?>/user/orders.php" class="btn btn-outline-secondary">
                                <i class="fas fa-history me-2"></i>View All Orders
                            </a>
                            <a href="<?php echo BASE_PATH; ?>/public/" class="btn btn-primary">
                                <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/main.js'); ?>"></script>
    
    <script>
        // Auto-refresh page if order is pending or processing (every 60 seconds)
        <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
        setTimeout(() => {
            location.reload();
        }, 60000);
        
        // Show a subtle notification about auto-refresh
        setTimeout(() => {
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-info border-0';
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-info-circle me-2"></i>
                        This page will automatically refresh to show status updates
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            let toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                document.body.appendChild(toastContainer);
            }
            
            toastContainer.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>