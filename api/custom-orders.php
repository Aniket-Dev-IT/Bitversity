<?php
/**
 * Custom Orders API Endpoint
 * 
 * Handles CRUD operations for custom order requests
 */

// Prevent any warnings/errors from being output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

require_once __DIR__ . '/../includes/config.php';
// Auth functions are in config.php
// Upload and email functionality will be added later

// Clear any unwanted output and set JSON header
ob_clean();
header('Content-Type: application/json');

// Enable CORS for development (remove in production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Helper function to send JSON response
function sendResponse($success, $message = '', $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Helper function to validate CSRF token
function validateRequest() {
    if (!isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        sendResponse(false, 'CSRF token required', null, 403);
    }
    
    if (!validateCSRFToken($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        sendResponse(false, 'Invalid CSRF token', null, 403);
    }
}

// Helper function to log custom order activity
function logCustomOrderActivity($action, $description, $customOrderId = null, $metadata = []) {
    // Activity logging disabled - table doesn't exist yet
    // This would log to activity_log table when implemented
    return true;
}

// Main request handler
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    $pathParts = array_filter(explode('/', $pathInfo));
    
    switch ($method) {
        case 'GET':
            handleGetRequest($pathParts);
            break;
            
        case 'POST':
            validateRequest();
            handlePostRequest($pathParts);
            break;
            
        case 'PUT':
            validateRequest();
            handlePutRequest($pathParts);
            break;
            
        case 'DELETE':
            validateRequest();
            handleDeleteRequest($pathParts);
            break;
            
        default:
            sendResponse(false, 'Method not allowed', null, 405);
    }
} catch (Exception $e) {
    error_log("Custom Orders API Error: " . $e->getMessage());
    sendResponse(false, 'Internal server error', null, 500);
}

function handleGetRequest($pathParts) {
    global $db;
    
    if (!isLoggedIn()) {
        sendResponse(false, 'Authentication required', null, 401);
    }
    
    $customOrderId = isset($pathParts[0]) ? intval($pathParts[0]) : null;
    
    if ($customOrderId) {
        // Get specific custom order
        getCustomOrder($customOrderId);
    } else {
        // List custom orders
        listCustomOrders();
    }
}

function handlePostRequest($pathParts) {
    global $db;
    
    if (!isLoggedIn()) {
        sendResponse(false, 'Authentication required', null, 401);
    }
    
    $customOrderId = isset($pathParts[0]) ? intval($pathParts[0]) : null;
    
    if ($customOrderId && isset($pathParts[1])) {
        if ($pathParts[1] === 'attachments') {
            uploadAttachment($customOrderId);
        } elseif ($pathParts[1] === 'messages') {
            addMessage($customOrderId);
        } else {
            sendResponse(false, 'Invalid endpoint', null, 404);
        }
    } else {
        // Create new custom order request
        createCustomOrder();
    }
}

function handlePutRequest($pathParts) {
    global $db;
    
    if (!isLoggedIn()) {
        sendResponse(false, 'Authentication required', null, 401);
    }
    
    $customOrderId = isset($pathParts[0]) ? intval($pathParts[0]) : null;
    
    if (!$customOrderId) {
        sendResponse(false, 'Custom order ID required', null, 400);
    }
    
    if (isset($pathParts[1])) {
        if ($pathParts[1] === 'status' && isAdmin()) {
            updateOrderStatus($customOrderId);
        } elseif ($pathParts[1] === 'price' && isAdmin()) {
            updateOrderPrice($customOrderId);
        } else {
            sendResponse(false, 'Invalid endpoint or insufficient permissions', null, 403);
        }
    } else {
        updateCustomOrder($customOrderId);
    }
}

function handleDeleteRequest($pathParts) {
    global $db;
    
    if (!isLoggedIn()) {
        sendResponse(false, 'Authentication required', null, 401);
    }
    
    $customOrderId = isset($pathParts[0]) ? intval($pathParts[0]) : null;
    
    if (!$customOrderId) {
        sendResponse(false, 'Custom order ID required', null, 400);
    }
    
    if (isset($pathParts[1]) && $pathParts[1] === 'attachments' && isset($pathParts[2])) {
        $attachmentId = intval($pathParts[2]);
        deleteAttachment($customOrderId, $attachmentId);
    } else {
        deleteCustomOrder($customOrderId);
    }
}

function createCustomOrder() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['order_type', 'title', 'description', 'requirements'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            sendResponse(false, "Field '$field' is required", null, 400);
        }
    }
    
    // Validate order type
    if (!in_array($input['order_type'], ['project', 'game'])) {
        sendResponse(false, 'Invalid order type', null, 400);
    }
    
    // Validate budget range
    $validBudgets = ['under_500', '500_1000', '1000_2500', '2500_5000', '5000_10000', 'over_10000'];
    if (isset($input['budget_range']) && !in_array($input['budget_range'], $validBudgets)) {
        sendResponse(false, 'Invalid budget range', null, 400);
    }
    
    try {
        // Use only existing columns in the database
        $stmt = $db->prepare("
            INSERT INTO custom_order_requests 
            (user_id, type, title, description, technical_requirements, budget_range, timeline, deadline, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $deadline = isset($input['timeline_needed']) ? $input['timeline_needed'] : null;
        $budgetRange = $input['budget_range'] ?? 'under_500';
        $timeline = $input['timeline'] ?? '1-2 weeks';
        
        $stmt->execute([
            $_SESSION['user_id'],
            $input['order_type'], // This maps to 'type' column
            trim($input['title']),
            trim($input['description']),
            trim($input['requirements']), // This maps to 'technical_requirements' column
            $budgetRange,
            $timeline,
            $deadline
        ]);
        
        $customOrderId = $db->lastInsertId();
        
        // Skip status history logging - table doesn't exist
        // Skip activity logging - handled by logCustomOrderActivity (disabled)
        logCustomOrderActivity('custom_order_created', 'Custom order request created', $customOrderId, [
            'order_type' => $input['order_type'],
            'title' => $input['title']
        ]);
        
        // Skip email notifications for now - may require additional setup
        // Future: Add email notification system here
        
        sendResponse(true, 'Custom order request created successfully', ['id' => $customOrderId], 201);
        
    } catch (PDOException $e) {
        error_log("Database error creating custom order: " . $e->getMessage());
        sendResponse(false, 'Failed to create custom order request', null, 500);
    }
}

function listCustomOrders() {
    global $db;
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = min(50, max(10, intval($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;
    
    $isAdmin = isAdmin();
    $userId = $_SESSION['user_id'];
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    
    if (!$isAdmin) {
        $whereConditions[] = "cor.user_id = ?";
        $params[] = $userId;
    }
    
    // Filter by status
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $whereConditions[] = "cor.status = ?";
        $params[] = $_GET['status'];
    }
    
    // Filter by order type
    if (isset($_GET['order_type']) && !empty($_GET['order_type'])) {
        $whereConditions[] = "cor.order_type = ?";
        $params[] = $_GET['order_type'];
    }
    
    // Search
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $whereConditions[] = "(cor.title LIKE ? OR cor.description LIKE ?)";
        $searchTerm = "%" . $_GET['search'] . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    try {
        // Get total count
        $countSql = "SELECT COUNT(*) FROM custom_order_requests cor $whereClause";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $totalCount = $stmt->fetchColumn();
        
        // Get custom orders
        $sql = "
            SELECT cor.*, 
                   CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name, 
                   u.email as user_email,
                   0 as attachments_count,
                   0 as unread_messages_count
            FROM custom_order_requests cor
            LEFT JOIN users u ON cor.user_id = u.id
            $whereClause
            ORDER BY cor.created_at DESC
            LIMIT $perPage OFFSET $offset
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $customOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalPages = ceil($totalCount / $perPage);
        
        sendResponse(true, 'Custom orders retrieved successfully', [
            'custom_orders' => $customOrders,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error listing custom orders: " . $e->getMessage());
        sendResponse(false, 'Failed to retrieve custom orders', null, 500);
    }
}

function getCustomOrder($customOrderId) {
    global $db;
    
    $isAdmin = isAdmin();
    $userId = $_SESSION['user_id'];
    
    try {
        $whereClause = $isAdmin ? "WHERE cor.id = ?" : "WHERE cor.id = ? AND cor.user_id = ?";
        $params = $isAdmin ? [$customOrderId] : [$customOrderId, $userId];
        
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
            sendResponse(false, 'Custom order not found', null, 404);
        }
        
        // Add default values for missing fields to match what the JavaScript expects
        $customOrder['priority'] = $customOrder['priority'] ?? 'normal';
        $customOrder['timeline_needed'] = $customOrder['timeline_needed'] ?? $customOrder['deadline'] ?? null;
        $customOrder['rejection_reason'] = $customOrder['rejection_reason'] ?? null;
        $customOrder['admin_name'] = null; // No assignment feature yet
        
        // Set empty arrays for features not yet implemented
        $customOrder['attachments'] = [];
        $customOrder['status_history'] = [];
        $customOrder['messages'] = [];
        $customOrder['attachments_count'] = 0;
        $customOrder['unread_messages_count'] = 0;
        $customOrder['total_messages_count'] = 0;
        
        sendResponse(true, 'Custom order retrieved successfully', $customOrder);
        
    } catch (PDOException $e) {
        error_log("Database error getting custom order: " . $e->getMessage());
        sendResponse(false, 'Failed to retrieve custom order', null, 500);
    }
}

// Original updateOrderStatus function moved to stub at end of file

// Original functions moved to stubs at end of file for features not yet implemented

// Stub functions for features not yet implemented
function updateCustomOrder($customOrderId) {
    sendResponse(false, 'Update functionality not yet implemented', null, 501);
}

function deleteCustomOrder($customOrderId) {
    sendResponse(false, 'Delete functionality not yet implemented', null, 501);
}

// Disable functions that require non-existent tables
function uploadAttachment($customOrderId) {
    sendResponse(false, 'File upload functionality not yet implemented', null, 501);
}

function deleteAttachment($customOrderId, $attachmentId) {
    sendResponse(false, 'File delete functionality not yet implemented', null, 501);
}

function addMessage($customOrderId) {
    sendResponse(false, 'Messaging functionality not yet implemented', null, 501);
}

function updateOrderStatus($customOrderId) {
    sendResponse(false, 'Status update functionality not yet implemented', null, 501);
}

function updateOrderPrice($customOrderId) {
    sendResponse(false, 'Price update functionality not yet implemented', null, 501);
}

?>
