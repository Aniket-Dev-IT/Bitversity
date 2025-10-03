<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();

// Handle cart updates
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $itemId = intval($_POST['item_id'] ?? 0);
    $itemType = $_POST['item_type'] ?? '';
    
    try {
        $db->beginTransaction();
        
        switch ($action) {
            case 'update_quantity':
                $quantity = max(1, intval($_POST['quantity'] ?? 1));
                $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND item_type = ? AND item_id = ?");
                $stmt->execute([$quantity, $_SESSION['user_id'], $itemType, $itemId]);
                $message = 'Cart updated successfully';
                break;
                
            case 'remove_item':
                $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ? AND item_type = ? AND item_id = ?");
                $stmt->execute([$_SESSION['user_id'], $itemType, $itemId]);
                $message = 'Item removed from cart';
                break;
                
            case 'clear_cart':
                $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $message = 'Cart cleared';
                break;
        }
        
        $db->commit();
        redirect(BASE_PATH . '/user/cart.php', $message, 'success');
        
    } catch (Exception $e) {
        $db->rollBack();
        redirect(BASE_PATH . '/user/cart.php', 'Error updating cart: ' . $e->getMessage(), 'error');
    }
}

// Get cart items with full details
$sql = "SELECT c.*, 
               CASE 
                   WHEN c.item_type = 'book' THEN b.title
                   WHEN c.item_type = 'project' THEN p.title
                   WHEN c.item_type = 'game' THEN g.title
               END as title,
               CASE 
                   WHEN c.item_type = 'book' THEN b.author
                   WHEN c.item_type = 'project' THEN 'Project'
                   WHEN c.item_type = 'game' THEN 'Game'
               END as author,
               CASE 
                   WHEN c.item_type = 'book' THEN b.price
                   WHEN c.item_type = 'project' THEN p.price
                   WHEN c.item_type = 'game' THEN g.price
               END as price,
               CASE 
                   WHEN c.item_type = 'book' THEN b.cover_image
                   WHEN c.item_type = 'project' THEN p.cover_image
                   WHEN c.item_type = 'game' THEN g.thumbnail
               END as image,
               CASE 
                   WHEN c.item_type = 'book' THEN b.stock
                   WHEN c.item_type = 'project' THEN 999
                   WHEN c.item_type = 'game' THEN 999
               END as stock,
               CASE 
                   WHEN c.item_type = 'book' THEN b.description
                   WHEN c.item_type = 'project' THEN p.description
                   WHEN c.item_type = 'game' THEN g.description
               END as description
        FROM cart c 
        LEFT JOIN books b ON c.item_type = 'book' AND c.item_id = b.id
        LEFT JOIN projects p ON c.item_type = 'project' AND c.item_id = p.id
        LEFT JOIN games g ON c.item_type = 'game' AND c.item_id = g.id
        WHERE c.user_id = ?
        ORDER BY c.added_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$cartItems = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
$totalItems = 0;
foreach ($cartItems as $item) {
    $itemTotal = $item['price'] * $item['quantity'];
    $subtotal += $itemTotal;
    $totalItems += $item['quantity'];
}

$tax = $subtotal * 0.0; // No tax for now
$shipping = 0; // Free shipping
$total = $subtotal + $tax + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - <?php echo APP_NAME; ?></title>
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <style>
        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 1.5rem 0;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .quantity-input {
            max-width: 80px;
        }
        .cart-summary {
            position: sticky;
            top: 2rem;
        }
    </style>
</head>
<body data-logged-in="true">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="main-content">
        <div class="container py-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="display-6">
                        <i class="fas fa-shopping-cart text-primary me-3"></i>Shopping Cart
                    </h1>
                    <p class="text-muted"><?php echo $totalItems; ?> item<?php echo $totalItems !== 1 ? 's' : ''; ?> in your cart</p>
                </div>
            </div>

            <?php if (empty($cartItems)): ?>
            <!-- Empty Cart -->
            <div class="row">
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                        <h3>Your cart is empty</h3>
                        <p class="text-muted mb-4">Looks like you haven't added anything to your cart yet</p>
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
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Cart Items -->
            <div class="row">
                <!-- Items List -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Cart Items</h5>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="clear_cart">
                                <button type="submit" class="btn btn-outline-danger btn-sm" 
                                        onclick="return confirm('Are you sure you want to clear your cart?')">
                                    <i class="fas fa-trash me-2"></i>Clear Cart
                                </button>
                            </form>
                        </div>
                        <div class="card-body">
                            <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item">
                                <div class="row align-items-center">
                                    <!-- Item Image -->
                                    <div class="col-md-2">
                                        <?php 
                                        $imagePath = '../assets/images/';
                                        switch ($item['item_type']) {
                                            case 'book':
                                                $imagePath .= 'books/';
                                                $default = 'https://via.placeholder.com/150x200/0d6efd/ffffff?text=Book';
                                                break;
                                            case 'project':
                                                $imagePath .= 'projects/';
                                                $default = 'https://via.placeholder.com/150x200/198754/ffffff?text=Project';
                                                break;
                                            case 'game':
                                                $imagePath .= 'games/';
                                                $default = 'https://via.placeholder.com/150x200/ffc107/000000?text=Game';
                                                break;
                                        }
                                        $imageSrc = $item['image'] ? upload($item['item_type'] . 's/' . $item['image']) : $default;
                                        ?>
                                        <img src="<?php echo $imageSrc; ?>" 
                                             class="img-fluid rounded" 
                                             alt="<?php echo sanitize($item['title']); ?>"
                                             style="height: 100px; width: 100px; object-fit: cover;">
                                    </div>
                                    
                                    <!-- Item Details -->
                                    <div class="col-md-4">
                                        <h6 class="mb-1"><?php echo sanitize($item['title']); ?></h6>
                                        <p class="text-muted small mb-1"><?php echo sanitize($item['author']); ?></p>
                                        <span class="badge bg-<?php 
                                            echo match($item['item_type']) {
                                                'book' => 'primary',
                                                'project' => 'success',
                                                'game' => 'warning',
                                                default => 'secondary'
                                            };
                                        ?>"><?php echo ucfirst($item['item_type']); ?></span>
                                        
                                        <?php if ($item['description']): ?>
                                        <p class="text-muted small mt-2 mb-0">
                                            <?php echo sanitize(substr($item['description'], 0, 80)); ?>...
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Price -->
                                    <div class="col-md-2 text-center">
                                        <strong><?php echo formatPrice($item['price']); ?></strong>
                                    </div>
                                    
                                    <!-- Quantity -->
                                    <div class="col-md-2">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="update_quantity">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <input type="hidden" name="item_type" value="<?php echo $item['item_type']; ?>">
                                            <div class="input-group">
                                                <input type="number" 
                                                       class="form-control form-control-sm quantity-input" 
                                                       name="quantity" 
                                                       value="<?php echo $item['quantity']; ?>"
                                                       min="1" 
                                                       max="<?php echo $item['stock']; ?>"
                                                       onchange="this.form.submit()">
                                                <button class="btn btn-outline-secondary btn-sm" type="submit">
                                                    <i class="fas fa-sync"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <!-- Subtotal & Actions -->
                                    <div class="col-md-2 text-end">
                                        <div class="mb-2">
                                            <strong><?php echo formatPrice($item['price'] * $item['quantity']); ?></strong>
                                        </div>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="remove_item">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <input type="hidden" name="item_type" value="<?php echo $item['item_type']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Remove item">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Continue Shopping -->
                    <div class="d-flex gap-3 mt-4">
                        <a href="<?php echo BASE_PATH; ?>/public/books.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                        </a>
                        <a href="<?php echo BASE_PATH; ?>/user/wishlist.php" class="btn btn-outline-secondary">
                            <i class="fas fa-heart me-2"></i>View Wishlist
                        </a>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="cart-summary">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Order Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal (<?php echo $totalItems; ?> items)</span>
                                    <span><?php echo formatPrice($subtotal); ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Shipping</span>
                                    <span class="text-success">FREE</span>
                                </div>
                                
                                <?php if ($tax > 0): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tax</span>
                                    <span><?php echo formatPrice($tax); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between mb-3">
                                    <strong>Total</strong>
                                    <strong class="text-primary"><?php echo formatPrice($total); ?></strong>
                                </div>
                                
                                <a href="<?php echo BASE_PATH; ?>/user/checkout.php" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                                </a>
                                
                                <div class="text-center">
                                    <small class="text-muted">
                                        <i class="fas fa-shield-alt me-1"></i>Secure checkout with SSL encryption
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Promo Code -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h6 class="card-title">Have a promo code?</h6>
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Enter code">
                                    <button class="btn btn-outline-secondary" type="button">Apply</button>
                                </div>
                            </div>
                        </div>

                        <!-- Trust Indicators -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h6 class="card-title">Why shop with us?</h6>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-download text-primary me-2"></i>
                                    <small>Instant digital delivery</small>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-undo text-success me-2"></i>
                                    <small>30-day money back guarantee</small>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-headset text-info me-2"></i>
                                    <small>24/7 customer support</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/main.js'); ?>"></script>
</body>
</html>