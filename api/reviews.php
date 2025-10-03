<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a review']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $itemId = intval($_POST['item_id'] ?? 0);
    $itemType = $_POST['item_type'] ?? '';
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $userId = $_SESSION['user_id'];
    
    // Validate input
    if (!$itemId || !in_array($itemType, ['book', 'project', 'game'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid item specified']);
        exit;
    }
    
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
        exit;
    }
    
    if (strlen($comment) < 10) {
        echo json_encode(['success' => false, 'message' => 'Review must be at least 10 characters long']);
        exit;
    }
    
    // Check if item exists
    $tableName = $itemType === 'book' ? 'books' : ($itemType === 'project' ? 'projects' : 'games');
    $checkStmt = $db->prepare("SELECT id FROM {$tableName} WHERE id = ? AND is_active = 1");
    $checkStmt->execute([$itemId]);
    if (!$checkStmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    // Check if user already reviewed this item
    $existingStmt = $db->prepare("
        SELECT id FROM reviews 
        WHERE user_id = ? AND item_id = ? AND item_type = ?
    ");
    $existingStmt->execute([$userId, $itemId, $itemType]);
    
    $db->beginTransaction();
    
    if ($existingStmt->fetchColumn()) {
        // Update existing review
        $stmt = $db->prepare("
            UPDATE reviews 
            SET rating = ?, comment = ?, updated_at = NOW(), is_approved = 1
            WHERE user_id = ? AND item_id = ? AND item_type = ?
        ");
        $stmt->execute([$rating, $comment, $userId, $itemId, $itemType]);
        $action = 'review_updated';
        $message = 'Your review has been updated successfully';
    } else {
        // Insert new review
        $stmt = $db->prepare("
            INSERT INTO reviews (user_id, item_id, item_type, rating, comment, is_approved, created_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$userId, $itemId, $itemType, $rating, $comment]);
        $reviewId = $db->lastInsertId();
        $action = 'review_created';
        $message = 'Your review has been submitted successfully';
    }
    
    $db->commit();
    
    // Log the activity
    logActivity($userId, $action, 'reviews', $reviewId ?? null, [
        'item_id' => $itemId,
        'item_type' => $itemType,
        'rating' => $rating,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Review submission error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while submitting your review'
    ]);
}
?>