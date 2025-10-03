<?php
require_once __DIR__ . '/../includes/config.php';

// Set JSON header and prevent errors
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (simplified check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get order ID from URL parameter
$orderId = intval($_GET['id'] ?? 0);

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    
    // Build WHERE clause - admin can see all orders, users can only see their own
    if ($isAdmin) {
        $whereClause = "WHERE cor.id = ?";
        $params = [$orderId];
    } else {
        $whereClause = "WHERE cor.id = ? AND cor.user_id = ?";
        $params = [$orderId, $userId];
    }
    
    $sql = "
        SELECT cor.*, 
               CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name, 
               u.email as user_email, 
               u.id as user_id
        FROM custom_order_requests cor
        LEFT JOIN users u ON cor.user_id = u.id
        $whereClause
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $customOrder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customOrder) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Custom order not found']);
        exit;
    }
    
    // Add default values for missing fields to match what the JavaScript expects
    $customOrder['priority'] = $customOrder['priority'] ?? 'medium';
    $customOrder['deadline'] = $customOrder['deadline'] ?? null;
    $customOrder['rejection_reason'] = $customOrder['rejection_reason'] ?? null;
    $customOrder['admin_name'] = null; // No assignment feature yet
    $customOrder['requirements'] = $customOrder['technical_requirements'] ?? $customOrder['description'] ?? '';
    
    // Set empty arrays for features not yet implemented
    $customOrder['attachments'] = [];
    $customOrder['status_history'] = [];
    $customOrder['messages'] = [];
    $customOrder['attachments_count'] = 0;
    $customOrder['unread_messages_count'] = 0;
    $customOrder['total_messages_count'] = 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Custom order retrieved successfully',
        'data' => $customOrder
    ]);
    
} catch (Exception $e) {
    error_log("Get order error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>