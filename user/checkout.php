<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();

// Check if cart is empty
$cartStmt = $db->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
$cartStmt->execute([$_SESSION['user_id']]);
if ($cartStmt->fetchColumn() == 0) {
    redirect(BASE_PATH . '/user/cart.php', 'Your cart is empty', 'warning');
}

// Handle order placement
if ($_POST && isset($_POST['place_order'])) {
    $billingName = trim($_POST['billing_name'] ?? '');
    $billingEmail = trim($_POST['billing_email'] ?? '');
    $billingAddress = trim($_POST['billing_address'] ?? '');
    $billingCity = trim($_POST['billing_city'] ?? '');
    $billingState = trim($_POST['billing_state'] ?? '');
    $billingZip = trim($_POST['billing_zip'] ?? '');
    $billingCountry = trim($_POST['billing_country'] ?? 'US');
    $paymentMethod = $_POST['payment_method'] ?? 'card';
    $promoCode = trim($_POST['promo_code'] ?? '');
    
    $errors = [];
    
    // Validate required fields
    if (empty($billingName)) $errors[] = 'Full name is required';
    if (empty($billingEmail) || !filter_var($billingEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (empty($billingAddress)) $errors[] = 'Address is required';
    if (empty($billingCity)) $errors[] = 'City is required';
    if (empty($billingZip)) $errors[] = 'ZIP code is required';
    
    if ($paymentMethod === 'card') {
        $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
        $cardExpiry = trim($_POST['card_expiry'] ?? '');
        $cardCvv = trim($_POST['card_cvv'] ?? '');
        $cardName = trim($_POST['card_name'] ?? '');
        
        if (empty($cardNumber) || strlen($cardNumber) < 13) $errors[] = 'Valid card number is required';
        if (empty($cardExpiry) || !preg_match('/^\d{2}\/\d{2}$/', $cardExpiry)) $errors[] = 'Valid expiry date is required (MM/YY)';
        if (empty($cardCvv) || strlen($cardCvv) < 3) $errors[] = 'Valid CVV is required';
        if (empty($cardName)) $errors[] = 'Cardholder name is required';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Get cart items with pricing
            $cartSql = "SELECT c.*, 
                               CASE 
                                   WHEN c.item_type = 'book' THEN b.title
                                   WHEN c.item_type = 'project' THEN p.title
                                   WHEN c.item_type = 'game' THEN g.title
                               END as title,
                               CASE 
                                   WHEN c.item_type = 'book' THEN b.price
                                   WHEN c.item_type = 'project' THEN p.price
                                   WHEN c.item_type = 'game' THEN g.price
                               END as price,
                               999 as stock
                        FROM cart c 
                        LEFT JOIN books b ON c.item_type = 'book' AND c.item_id = b.id
                        LEFT JOIN projects p ON c.item_type = 'project' AND c.item_id = p.id
                        LEFT JOIN games g ON c.item_type = 'game' AND c.item_id = g.id
                        WHERE c.user_id = ?";
            
            $cartStmt = $db->prepare($cartSql);
            $cartStmt->execute([$_SESSION['user_id']]);
            $cartItems = $cartStmt->fetchAll();
            
            // Calculate totals (no stock checking needed for digital products)
            $subtotal = 0;
            foreach ($cartItems as $item) {
                // Digital products have unlimited stock, so no checking needed
                $subtotal += $item['price'] * $item['quantity'];
            }
            
            // Enhanced coupon validation
            $discount = 0;
            $couponId = null;
            if (!empty($promoCode)) {
                // Validate coupon using database
                $couponStmt = $db->prepare("
                    SELECT * FROM coupons 
                    WHERE code = ? 
                    AND is_active = 1 
                    AND valid_from <= NOW() 
                    AND valid_until >= NOW()
                ");
                $couponStmt->execute([strtoupper($promoCode)]);
                $coupon = $couponStmt->fetch();
                
                if ($coupon) {
                    // Check minimum order amount
                    if ($subtotal >= $coupon['minimum_order_amount']) {
                        // Check usage limit
                        $canUseCoupon = true;
                        if ($coupon['usage_limit'] !== null && $coupon['used_count'] >= $coupon['usage_limit']) {
                            $canUseCoupon = false;
                            $errors[] = 'Coupon usage limit reached';
                        }
                        
                        // Check if user already used this coupon
                        $userUsageStmt = $db->prepare("SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ? AND user_id = ?");
                        $userUsageStmt->execute([$coupon['id'], $_SESSION['user_id']]);
                        if ($userUsageStmt->fetchColumn() > 0) {
                            $canUseCoupon = false;
                            $errors[] = 'You have already used this coupon';
                        }
                        
                        if ($canUseCoupon) {
                            // Calculate discount
                            if ($coupon['discount_type'] === 'percentage') {
                                $discount = ($subtotal * $coupon['discount_value']) / 100;
                                if ($coupon['maximum_discount_amount'] !== null && $discount > $coupon['maximum_discount_amount']) {
                                    $discount = $coupon['maximum_discount_amount'];
                                }
                            } else {
                                $discount = $coupon['discount_value'];
                            }
                            
                            if ($discount > $subtotal) {
                                $discount = $subtotal;
                            }
                            
                            $couponId = $coupon['id'];
                        }
                    } else {
                        $errors[] = "Minimum order amount of $" . number_format($coupon['minimum_order_amount'], 2) . " required for this coupon";
                    }
                } else {
                    $errors[] = 'Invalid or expired coupon code';
                }
            }
            
            $tax = 0; // No tax for now
            $shipping = 0; // Free shipping
            $total = $subtotal - $discount + $tax + $shipping;
            
            // Create order
            $orderStmt = $db->prepare("INSERT INTO orders (user_id, total_amount, status, billing_name, billing_email, billing_address, billing_city, billing_state, billing_zip, billing_country, payment_method, promo_code, discount_amount, tax_amount, shipping_amount) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $orderStmt->execute([$_SESSION['user_id'], $total, $billingName, $billingEmail, $billingAddress, $billingCity, $billingState, $billingZip, $billingCountry, $paymentMethod, $promoCode, $discount, $tax, $shipping]);
            
            $orderId = $db->lastInsertId();
            
            // Create order items
            $orderItemStmt = $db->prepare("INSERT INTO order_items (order_id, item_type, item_id, quantity, unit_price, total_price, item_title) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($cartItems as $item) {
                $totalPrice = $item['price'] * $item['quantity'];
                $orderItemStmt->execute([$orderId, $item['item_type'], $item['item_id'], $item['quantity'], $item['price'], $totalPrice, $item['title']]);
                
                // No stock updates needed for digital products
                // Digital items (books, projects, games) have unlimited availability
            }
            
            // Process payment (simulated - in real app, integrate with payment gateway)
            $paymentSuccessful = true;
            
            if ($paymentSuccessful) {
                // Keep order as 'pending' - admin will approve it later
                // Record coupon usage if coupon was used
                if ($couponId && $discount > 0) {
                    $db->prepare("INSERT INTO coupon_usage (coupon_id, user_id, order_id, discount_amount) VALUES (?, ?, ?, ?)")
                       ->execute([$couponId, $_SESSION['user_id'], $orderId, $discount]);
                    
                    // Update coupon used count
                    $db->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")
                       ->execute([$couponId]);
                }
                
                // Clear cart
                $db->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$_SESSION['user_id']]);
                
                $db->commit();
                redirect(BASE_PATH . '/user/order_success.php?order=' . $orderId, 'Order placed successfully! Your order is pending admin approval.', 'success');
            } else {
                throw new Exception("Payment processing failed");
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

// Get cart items for display
$cartSql = "SELECT c.*, 
                   CASE 
                       WHEN c.item_type = 'book' THEN b.title
                       WHEN c.item_type = 'project' THEN p.title
                       WHEN c.item_type = 'game' THEN g.title
                   END as title,
                   CASE 
                       WHEN c.item_type = 'book' THEN b.price
                       WHEN c.item_type = 'project' THEN p.price
                       WHEN c.item_type = 'game' THEN g.price
                   END as price,
                   CASE 
                       WHEN c.item_type = 'book' THEN b.cover_image
                       WHEN c.item_type = 'project' THEN p.cover_image
                       WHEN c.item_type = 'game' THEN g.thumbnail
                   END as image
            FROM cart c 
            LEFT JOIN books b ON c.item_type = 'book' AND c.item_id = b.id
            LEFT JOIN projects p ON c.item_type = 'project' AND c.item_id = p.id
            LEFT JOIN games g ON c.item_type = 'game' AND c.item_id = g.id
            WHERE c.user_id = ?
            ORDER BY c.added_at DESC";

$cartStmt = $db->prepare($cartSql);
$cartStmt->execute([$_SESSION['user_id']]);
$cartItems = $cartStmt->fetchAll();

// Calculate totals
$subtotal = 0;
$totalItems = 0;
foreach ($cartItems as $item) {
    $itemTotal = $item['price'] * $item['quantity'];
    $subtotal += $itemTotal;
    $totalItems += $item['quantity'];
}

$discount = 0;
$tax = 0;
$shipping = 0;
$total = $subtotal - $discount + $tax + $shipping;

// Get user info for pre-filling
$userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$user = $userStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo APP_NAME; ?></title>
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <style>
        .checkout-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .coupon-section {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 2px dashed #2196f3;
        }
        
        .available-coupon {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .available-coupon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .coupon-applied {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .order-item {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .payment-method {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .payment-method:hover {
            border-color: #0d6efd;
        }
        
        .payment-method.active {
            border-color: #0d6efd;
            background-color: #f0f8ff;
        }
        
        .progress-bar-custom {
            height: 6px;
            border-radius: 10px;
            background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
        }
        
        .order-summary {
            position: sticky;
            top: 2rem;
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .discount-highlight {
            animation: highlight 2s ease-in-out;
        }
        
        @keyframes highlight {
            0% { background-color: #fff3cd; }
            100% { background-color: transparent; }
        }
        
        .card-input {
            position: relative;
        }
        .card-input input {
            padding-left: 3rem;
        }
        .card-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            z-index: 3;
        }
    </style>
</head>
<body data-logged-in="true">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="main-content">
        <div class="container py-4">
            <!-- Progress Steps -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-center mb-4">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fas fa-check"></i>
                            </div>
                            <span class="ms-2 me-4 fw-bold text-success">Cart</span>
                            <div class="progress-bar-custom" style="width: 100px;"></div>
                        </div>
                        <div class="d-flex align-items-center ms-4">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <span class="ms-2 me-4 fw-bold text-primary">Checkout</span>
                            <div class="bg-light" style="width: 100px; height: 6px; border-radius: 10px;"></div>
                        </div>
                        <div class="d-flex align-items-center ms-4">
                            <div class="rounded-circle bg-light text-muted d-flex align-items-center justify-content-center border" style="width: 40px; height: 40px;">
                                <i class="fas fa-check"></i>
                            </div>
                            <span class="ms-2 text-muted">Complete</span>
                        </div>
                    </div>
                    
                    <h1 class="display-6 text-center">
                        <i class="fas fa-shield-alt text-success me-3"></i>Secure Checkout
                    </h1>
                    <p class="text-center text-muted">Your payment is protected with 256-bit SSL encryption</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h6>Please fix the following errors:</h6>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" id="checkoutForm">
                <div class="row">
                    <!-- Checkout Form -->
                    <div class="col-lg-7">
                        <!-- Billing Information -->
                        <div class="checkout-section">
                            <h4 class="mb-4">
                                <i class="fas fa-user me-2"></i>Billing Information
                            </h4>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="billing_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="billing_name" name="billing_name" 
                                           value="<?php echo sanitize($_POST['billing_name'] ?? $user['full_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="billing_email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="billing_email" name="billing_email" 
                                           value="<?php echo sanitize($_POST['billing_email'] ?? $user['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="billing_address" class="form-label">Street Address *</label>
                                <input type="text" class="form-control" id="billing_address" name="billing_address" 
                                       value="<?php echo sanitize($_POST['billing_address'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="billing_city" class="form-label">City *</label>
                                    <input type="text" class="form-control" id="billing_city" name="billing_city" 
                                           value="<?php echo sanitize($_POST['billing_city'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="billing_state" class="form-label">State/Province</label>
                                    <input type="text" class="form-control" id="billing_state" name="billing_state" 
                                           value="<?php echo sanitize($_POST['billing_state'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="billing_zip" class="form-label">ZIP/Postal Code *</label>
                                    <input type="text" class="form-control" id="billing_zip" name="billing_zip" 
                                           value="<?php echo sanitize($_POST['billing_zip'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="billing_country" class="form-label">Country</label>
                                <select class="form-select" id="billing_country" name="billing_country">
                                    <option value="US" <?php echo ($_POST['billing_country'] ?? 'US') === 'US' ? 'selected' : ''; ?>>United States</option>
                                    <option value="CA" <?php echo ($_POST['billing_country'] ?? '') === 'CA' ? 'selected' : ''; ?>>Canada</option>
                                    <option value="GB" <?php echo ($_POST['billing_country'] ?? '') === 'GB' ? 'selected' : ''; ?>>United Kingdom</option>
                                    <option value="AU" <?php echo ($_POST['billing_country'] ?? '') === 'AU' ? 'selected' : ''; ?>>Australia</option>
                                    <option value="DE" <?php echo ($_POST['billing_country'] ?? '') === 'DE' ? 'selected' : ''; ?>>Germany</option>
                                    <option value="FR" <?php echo ($_POST['billing_country'] ?? '') === 'FR' ? 'selected' : ''; ?>>France</option>
                                </select>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="checkout-section">
                            <h4 class="mb-4">
                                <i class="fas fa-credit-card me-2"></i>Payment Method
                            </h4>
                            
                            <!-- Credit Card -->
                            <div class="payment-method active" data-method="card">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="payment_card" value="card" checked>
                                    <label class="form-check-label" for="payment_card">
                                        <strong>Credit/Debit Card</strong>
                                        <div class="d-flex gap-2 mt-2">
                                            <i class="fab fa-cc-visa fa-2x text-primary"></i>
                                            <i class="fab fa-cc-mastercard fa-2x text-warning"></i>
                                            <i class="fab fa-cc-amex fa-2x text-info"></i>
                                            <i class="fab fa-cc-discover fa-2x text-success"></i>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Card Details -->
                            <div id="cardDetails" class="mt-3">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="card_name" class="form-label">Cardholder Name *</label>
                                        <input type="text" class="form-control" id="card_name" name="card_name" 
                                               value="<?php echo sanitize($_POST['card_name'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="card_number" class="form-label">Card Number *</label>
                                        <div class="card-input">
                                            <i class="fas fa-credit-card card-icon text-muted"></i>
                                            <input type="text" class="form-control" id="card_number" name="card_number" 
                                                   placeholder="1234 5678 9012 3456" maxlength="19"
                                                   value="<?php echo sanitize($_POST['card_number'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="card_expiry" class="form-label">Expiry Date *</label>
                                        <input type="text" class="form-control" id="card_expiry" name="card_expiry" 
                                               placeholder="MM/YY" maxlength="5"
                                               value="<?php echo sanitize($_POST['card_expiry'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="card_cvv" class="form-label">CVV *</label>
                                        <input type="text" class="form-control" id="card_cvv" name="card_cvv" 
                                               placeholder="123" maxlength="4"
                                               value="<?php echo sanitize($_POST['card_cvv'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- PayPal -->
                            <div class="payment-method" data-method="paypal">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="payment_paypal" value="paypal">
                                    <label class="form-check-label" for="payment_paypal">
                                        <strong>PayPal</strong>
                                        <i class="fab fa-paypal fa-2x text-primary ms-2"></i>
                                        <p class="text-muted small mt-2 mb-0">Pay securely with your PayPal account</p>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Promo Code -->
                        <div class="checkout-section">
                            <h5 class="mb-3">
                                <i class="fas fa-tag me-2"></i>Promo Code
                            </h5>
                            <div class="input-group">
                                <input type="text" class="form-control" name="promo_code" placeholder="Enter promo code" 
                                       value="<?php echo sanitize($_POST['promo_code'] ?? ''); ?>">
                                <button class="btn btn-outline-secondary" type="button" onclick="validatePromo()">Apply</button>
                            </div>
                            <small class="text-muted">Try "WELCOME10" for 10% off your first order!</small>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="col-lg-5">
                        <div class="order-summary">
                            <h4 class="mb-4">
                                <i class="fas fa-shopping-bag me-2"></i>Order Summary
                            </h4>
                            
                            <!-- Order Items -->
                            <div class="mb-4">
                                <?php foreach ($cartItems as $item): ?>
                                <div class="order-item">
                                    <div class="row align-items-center">
                                        <div class="col-3">
                                            <?php 
                                            switch ($item['item_type']) {
                                                case 'book':
                                                    $default = 'https://via.placeholder.com/80x100/0d6efd/ffffff?text=Book';
                                                    break;
                                                case 'project':
                                                    $default = 'https://via.placeholder.com/80x100/198754/ffffff?text=Project';
                                                    break;
                                                case 'game':
                                                    $default = 'https://via.placeholder.com/80x100/ffc107/000000?text=Game';
                                                    break;
                                            }
                                            $imageSrc = $item['image'] ? upload($item['item_type'] . 's/' . $item['image']) : $default;
                                            ?>
                                            <img src="<?php echo $imageSrc; ?>" class="img-fluid rounded" alt="<?php echo sanitize($item['title']); ?>" style="height: 60px; width: 50px; object-fit: cover;">
                                        </div>
                                        <div class="col-6">
                                            <h6 class="mb-1 small"><?php echo sanitize($item['title']); ?></h6>
                                            <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                        </div>
                                        <div class="col-3 text-end">
                                            <strong><?php echo formatPrice($item['price'] * $item['quantity']); ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pricing Breakdown -->
                            <div class="border-top pt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal (<?php echo $totalItems; ?> items)</span>
                                    <span><?php echo formatPrice($subtotal); ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Discount</span>
                                    <span class="text-success">-<?php echo formatPrice($discount); ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Shipping</span>
                                    <span class="text-success">FREE</span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tax</span>
                                    <span><?php echo formatPrice($tax); ?></span>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between mb-4">
                                    <h5>Total</h5>
                                    <h5 class="text-primary"><?php echo formatPrice($total); ?></h5>
                                </div>
                                
                                <button type="submit" name="place_order" class="btn btn-success w-100 py-3 mb-3">
                                    <i class="fas fa-lock me-2"></i>Place Order Securely
                                </button>
                                
                                <div class="text-center">
                                    <small class="text-muted">
                                        <i class="fas fa-shield-alt me-1"></i>Your payment information is encrypted and secure
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/main.js'); ?>"></script>
    
    <script>
        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                // Remove active class from all methods
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
                
                // Add active class to clicked method
                this.classList.add('active');
                
                // Check the radio button
                this.querySelector('input[type="radio"]').checked = true;
                
                // Show/hide card details
                const cardDetails = document.getElementById('cardDetails');
                if (this.dataset.method === 'card') {
                    cardDetails.style.display = 'block';
                } else {
                    cardDetails.style.display = 'none';
                }
            });
        });
        
        // Card number formatting
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            e.target.value = value;
        });
        
        // Expiry date formatting
        document.getElementById('card_expiry').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
        
        // CVV validation
        document.getElementById('card_cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
        
        // Real-time promo code validation
        function validatePromo() {
            const promoInput = document.querySelector('input[name="promo_code"]');
            const promoCode = promoInput.value.trim();
            const currentTotal = <?php echo $total; ?>;
            
            if (!promoCode) {
                showPromoMessage('Please enter a coupon code', 'warning');
                return;
            }
            
            // Show loading state
            const applyBtn = document.querySelector('.input-group button');
            const originalText = applyBtn.textContent;
            applyBtn.disabled = true;
            applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            
            // Validate coupon via API
            fetch('<?php echo BASE_PATH; ?>/api/validate_coupon.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    coupon_code: promoCode,
                    order_total: currentTotal
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showPromoMessage(data.message, 'success');
                    promoInput.classList.remove('is-invalid');
                    promoInput.classList.add('is-valid');
                    
                    // Update discount display
                    updateDiscountDisplay(data.discount_amount, data.new_total);
                } else {
                    showPromoMessage(data.message, 'danger');
                    promoInput.classList.remove('is-valid');
                    promoInput.classList.add('is-invalid');
                }
            })
            .catch(error => {
                console.error('Error validating coupon:', error);
                showPromoMessage('Error validating coupon. Please try again.', 'danger');
            })
            .finally(() => {
                applyBtn.disabled = false;
                applyBtn.textContent = originalText;
            });
        }
        
        function showPromoMessage(message, type) {
            // Remove existing message
            const existingMsg = document.querySelector('.promo-message');
            if (existingMsg) existingMsg.remove();
            
            // Create new message
            const msgDiv = document.createElement('div');
            msgDiv.className = `alert alert-${type} alert-dismissible fade show promo-message mt-2`;
            msgDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Add after promo code input group
            const inputGroup = document.querySelector('.input-group');
            inputGroup.parentNode.insertBefore(msgDiv, inputGroup.nextSibling);
            
            // Auto-remove success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    if (msgDiv.parentNode) {
                        msgDiv.classList.remove('show');
                        setTimeout(() => msgDiv.remove(), 300);
                    }
                }, 5000);
            }
        }
        
        function updateDiscountDisplay(discountAmount, newTotal) {
            // Update discount row in order summary
            const discountSpan = document.querySelector('.d-flex:has(.text-success) .text-success');
            if (discountSpan) {
                discountSpan.textContent = '-$' + discountAmount.toFixed(2);
            }
            
            // Update total
            const totalElement = document.querySelector('.d-flex:has(h5) h5.text-primary');
            if (totalElement) {
                totalElement.textContent = '$' + newTotal.toFixed(2);
                totalElement.classList.add('discount-highlight');
                setTimeout(() => {
                    totalElement.classList.remove('discount-highlight');
                }, 2000);
            }
        }
        
        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
            
            if (paymentMethod === 'card') {
                const cardNumber = document.getElementById('card_number').value.replace(/\D/g, '');
                const cardExpiry = document.getElementById('card_expiry').value;
                const cardCvv = document.getElementById('card_cvv').value;
                const cardName = document.getElementById('card_name').value;
                
                if (!cardNumber || cardNumber.length < 13) {
                    alert('Please enter a valid card number');
                    e.preventDefault();
                    return;
                }
                
                if (!cardExpiry || !cardExpiry.match(/^\d{2}\/\d{2}$/)) {
                    alert('Please enter a valid expiry date (MM/YY)');
                    e.preventDefault();
                    return;
                }
                
                if (!cardCvv || cardCvv.length < 3) {
                    alert('Please enter a valid CVV');
                    e.preventDefault();
                    return;
                }
                
                if (!cardName.trim()) {
                    alert('Please enter the cardholder name');
                    e.preventDefault();
                    return;
                }
            }
        });
    </script>
</body>
</html>