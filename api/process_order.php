<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $db->beginTransaction();
    
    // Get cart items
    $stmt = $db->prepare("
        SELECT c.*, 
               CASE 
                   WHEN c.item_type = 'book' THEN b.title
                   WHEN c.item_type = 'project' THEN p.title
                   WHEN c.item_type = 'game' THEN g.title
               END as title,
               CASE 
                   WHEN c.item_type = 'book' THEN b.price
                   WHEN c.item_type = 'project' THEN p.price
                   WHEN c.item_type = 'game' THEN g.price
               END as price
        FROM cart c
        LEFT JOIN books b ON c.item_type = 'book' AND c.item_id = b.id
        LEFT JOIN projects p ON c.item_type = 'project' AND c.item_id = p.id
        LEFT JOIN games g ON c.item_type = 'game' AND c.item_id = g.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cart_items)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit();
    }
    
    // Calculate totals
    $subtotal = array_sum(array_column($cart_items, 'price'));
    $tax = $subtotal * 0.08;
    $total = $subtotal + $tax;
    
    // Create order
    $stmt = $db->prepare("
        INSERT INTO orders (user_id, total_amount, tax_amount, status, created_at, updated_at) 
        VALUES (?, ?, ?, 'completed', NOW(), NOW())
    ");
    $stmt->execute([$user_id, $total, $tax]);
    $order_id = $db->lastInsertId();
    
    // Add order items
    foreach ($cart_items as $item) {
        $quantity = $item['quantity'] ?? 1;
        $totalPrice = $item['price'] * $quantity;
        $stmt = $db->prepare("
            INSERT INTO order_items (order_id, item_type, item_id, item_title, unit_price, total_price, quantity) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$order_id, $item['item_type'], $item['item_id'], $item['title'], $item['price'], $totalPrice, $quantity]);
    }
    
    // Clear cart
    $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    $db->commit();
    
    // Send order confirmation email
    global $email_service;
    if ($email_service) {
        $email_service->sendOrderConfirmation($order_id);
    }
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'message' => 'Order processed successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process order: ' . $e->getMessage()
    ]);
}
?>