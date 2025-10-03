<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$itemId = intval($input['item_id'] ?? 0);
$itemType = $input['item_type'] ?? '';

// Validate input
if (!in_array($action, ['add', 'remove']) || !$itemId || !in_array($itemType, ['book', 'project', 'game'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

try {
    switch ($action) {
        case 'add':
            // Check if item exists
            $table = $itemType === 'book' ? 'books' : ($itemType === 'project' ? 'projects' : 'games');
            $stmt = $db->prepare("SELECT id FROM $table WHERE id = ? AND is_active = 1");
            $stmt->execute([$itemId]);
            if (!$stmt->fetch()) {
                throw new Exception('Item not found');
            }
            
            // Add to wishlist (ignore if already exists)
            $stmt = $db->prepare("INSERT IGNORE INTO wishlist (user_id, item_type, item_id) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $itemType, $itemId]);
            
            $message = 'Item added to wishlist';
            break;
            
        case 'remove':
            $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND item_type = ? AND item_id = ?");
            $stmt->execute([$_SESSION['user_id'], $itemType, $itemId]);
            $message = 'Item removed from wishlist';
            break;
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>