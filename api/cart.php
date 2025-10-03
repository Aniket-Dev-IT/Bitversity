<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get request data from JSON or POST
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? '';
$itemId = intval($input['item_id'] ?? 0);
$itemType = $input['item_type'] ?? '';
$quantity = intval($input['quantity'] ?? 1);

// Validate input
if (!in_array($action, ['add', 'remove', 'update']) || !$itemId || !in_array($itemType, ['book', 'project', 'game'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

try {
    $db->beginTransaction();
    
    switch ($action) {
        case 'add':
            // Check if item exists
            $table = $itemType === 'book' ? 'books' : ($itemType === 'project' ? 'projects' : 'games');
            $stmt = $db->prepare("SELECT id, price FROM $table WHERE id = ? AND is_active = 1");
            $stmt->execute([$itemId]);
            if (!$stmt->fetch()) {
                throw new Exception('Item not found');
            }
            
            // Check if already in cart
            $stmt = $db->prepare("SELECT quantity FROM cart WHERE user_id = ? AND item_type = ? AND item_id = ?");
            $stmt->execute([$_SESSION['user_id'], $itemType, $itemId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update quantity
                $newQuantity = $existing['quantity'] + $quantity;
                $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND item_type = ? AND item_id = ?");
                $stmt->execute([$newQuantity, $_SESSION['user_id'], $itemType, $itemId]);
            } else {
                // Add new item
                $stmt = $db->prepare("INSERT INTO cart (user_id, item_type, item_id, quantity) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $itemType, $itemId, $quantity]);
            }
            
            $message = 'Item added to cart';
            break;
            
        case 'remove':
            $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ? AND item_type = ? AND item_id = ?");
            $stmt->execute([$_SESSION['user_id'], $itemType, $itemId]);
            $message = 'Item removed from cart';
            break;
            
        case 'update':
            if ($quantity <= 0) {
                $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ? AND item_type = ? AND item_id = ?");
                $stmt->execute([$_SESSION['user_id'], $itemType, $itemId]);
            } else {
                $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND item_type = ? AND item_id = ?");
                $stmt->execute([$quantity, $_SESSION['user_id'], $itemType, $itemId]);
            }
            $message = 'Cart updated';
            break;
    }
    
    // Get updated cart count
    $stmt = $db->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cartCount = $stmt->fetch()['count'] ?? 0;
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'cart_count' => $cartCount
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>