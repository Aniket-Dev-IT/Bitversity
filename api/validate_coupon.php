<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$couponCode = trim($input['coupon_code'] ?? '');
$orderTotal = floatval($input['order_total'] ?? 0);

if (empty($couponCode)) {
    echo json_encode(['success' => false, 'message' => 'Coupon code is required']);
    exit;
}

if ($orderTotal <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order total']);
    exit;
}

try {
    // Get coupon details
    $stmt = $db->prepare("
        SELECT * FROM coupons 
        WHERE code = ? 
        AND is_active = 1 
        AND valid_from <= NOW() 
        AND valid_until >= NOW()
    ");
    $stmt->execute([strtoupper($couponCode)]);
    $coupon = $stmt->fetch();
    
    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon code']);
        exit;
    }
    
    // Check minimum order amount
    if ($orderTotal < $coupon['minimum_order_amount']) {
        echo json_encode([
            'success' => false, 
            'message' => "Minimum order amount of $" . number_format($coupon['minimum_order_amount'], 2) . " required for this coupon"
        ]);
        exit;
    }
    
    // Check usage limit
    if ($coupon['usage_limit'] !== null && $coupon['used_count'] >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'Coupon usage limit reached']);
        exit;
    }
    
    // Check if user has already used this coupon (for single-use per user coupons)
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM coupon_usage 
        WHERE coupon_id = ? AND user_id = ?
    ");
    $stmt->execute([$coupon['id'], $_SESSION['user_id']]);
    $userUsageCount = $stmt->fetchColumn();
    
    // For now, allow only one use per user per coupon
    if ($userUsageCount > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already used this coupon']);
        exit;
    }
    
    // Calculate discount
    $discountAmount = 0;
    if ($coupon['discount_type'] === 'percentage') {
        $discountAmount = ($orderTotal * $coupon['discount_value']) / 100;
        
        // Apply maximum discount limit if set
        if ($coupon['maximum_discount_amount'] !== null && $discountAmount > $coupon['maximum_discount_amount']) {
            $discountAmount = $coupon['maximum_discount_amount'];
        }
    } else { // fixed
        $discountAmount = $coupon['discount_value'];
    }
    
    // Make sure discount doesn't exceed order total
    if ($discountAmount > $orderTotal) {
        $discountAmount = $orderTotal;
    }
    
    echo json_encode([
        'success' => true,
        'coupon' => [
            'id' => $coupon['id'],
            'code' => $coupon['code'],
            'name' => $coupon['name'],
            'description' => $coupon['description'],
            'discount_type' => $coupon['discount_type'],
            'discount_value' => $coupon['discount_value']
        ],
        'discount_amount' => round($discountAmount, 2),
        'new_total' => round($orderTotal - $discountAmount, 2),
        'message' => "Coupon '{$coupon['code']}' applied successfully! You saved $" . number_format($discountAmount, 2)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error validating coupon: ' . $e->getMessage()]);
}
?>