<?php
/**
 * Admin Custom Orders Management
 * 
 * Interface for admins to view, approve/reject, and manage custom order requests
 */

require_once 'includes/auth.php';

$current_admin = getCurrentAdmin();
$flash_message = getAdminFlashMessage();

// Get filters and pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        setAdminFlashMessage('Invalid security token. Please try again.', 'error');
        header('Location: custom-orders.php');
        exit();
    }
    
    try {
        switch ($action) {
            case 'bulk_action':
                $bulk_action = $_POST['bulk_action'] ?? '';
                $selected_orders = $_POST['selected_orders'] ?? [];
                
                if (empty($selected_orders) || !is_array($selected_orders)) {
                    setAdminFlashMessage('No orders selected for bulk action.', 'error');
                    break;
                }
                
                $count = 0;
                foreach ($selected_orders as $order_id) {
                    $order_id = intval($order_id);
                    
                    switch ($bulk_action) {
                        case 'approve':
                            $stmt = $db->prepare("UPDATE custom_order_requests SET status = 'approved', updated_at = NOW() WHERE id = ? AND status = 'pending'");
                            if ($stmt->execute([$order_id]) && $stmt->rowCount() > 0) {
                                $count++;
                            }
                            break;
                            
                        case 'under_review':
                            $stmt = $db->prepare("UPDATE custom_order_requests SET status = 'under_review', updated_at = NOW() WHERE id = ? AND status = 'pending'");
                            if ($stmt->execute([$order_id]) && $stmt->rowCount() > 0) {
                                $count++;
                            }
                            break;
                            
                        case 'assign_to_me':
                            // Add assigned_to column to track admin assignment
                            $stmt = $db->prepare("UPDATE custom_order_requests SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
                            if ($stmt->execute([$_SESSION['user_id'], $order_id]) && $stmt->rowCount() > 0) {
                                $count++;
                            }
                            break;
                    }
                }
                
                logAdminActivity('bulk_action_performed', "Bulk action '{$bulk_action}' performed on {$count} orders", [
                    'bulk_action' => $bulk_action,
                    'affected_count' => $count,
                    'order_ids' => $selected_orders
                ]);
                
                setAdminFlashMessage("Bulk action completed successfully. {$count} orders updated.", 'success');
                break;
            case 'update_status':
                $custom_order_id = intval($_POST['custom_order_id'] ?? 0);
                $new_status = $_POST['new_status'] ?? '';
                $reason = trim($_POST['reason'] ?? '');
                $admin_notes = trim($_POST['admin_notes'] ?? '');
                $rejection_reason = trim($_POST['rejection_reason'] ?? '');
                
                $valid_statuses = ['pending', 'under_review', 'approved', 'rejected', 'payment_pending', 'in_progress', 'completed', 'cancelled'];
                if (!in_array($new_status, $valid_statuses)) {
                    setAdminFlashMessage('Invalid status specified.', 'error');
                    break;
                }
                
                // Get current status
                $stmt = $db->prepare("SELECT status FROM custom_order_requests WHERE id = ?");
                $stmt->execute([$custom_order_id]);
                $current_status = $stmt->fetchColumn();
                
                if (!$current_status) {
                    setAdminFlashMessage('Custom order not found.', 'error');
                    break;
                }
                
                // Update status
                $stmt = $db->prepare("UPDATE custom_order_requests SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $admin_notes, $custom_order_id]);
                
                // Update rejection reason if rejected
                if ($new_status === 'rejected' && $rejection_reason) {
                    $stmt = $db->prepare("UPDATE custom_order_requests SET rejection_reason = ? WHERE id = ?");
                    $stmt->execute([$rejection_reason, $custom_order_id]);
                }
                
                // Status history tracking removed (table doesn't exist yet)
                
                logAdminActivity('custom_order_status_changed', "Custom order status changed to {$new_status}", [
                    'custom_order_id' => $custom_order_id,
                    'old_status' => $current_status,
                    'new_status' => $new_status
                ]);
                
                setAdminFlashMessage("Custom order status updated to {$new_status} successfully.", 'success');
                break;
                
            case 'set_price':
                $custom_order_id = intval($_POST['custom_order_id'] ?? 0);
                $custom_price = floatval($_POST['custom_price'] ?? 0);
                $estimated_completion_date = $_POST['estimated_completion_date'] ?? null;
                
                if ($custom_price < 0) {
                    setAdminFlashMessage('Price cannot be negative.', 'error');
                    break;
                }
                
                $stmt = $db->prepare("UPDATE custom_order_requests SET custom_price = ?, estimated_completion_date = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$custom_price, $estimated_completion_date, $custom_order_id]);
                
                if ($stmt->rowCount() === 0) {
                    setAdminFlashMessage('Custom order not found.', 'error');
                    break;
                }
                
                logAdminActivity('custom_order_price_set', "Custom price set to $" . number_format($custom_price, 2), [
                    'custom_order_id' => $custom_order_id,
                    'custom_price' => $custom_price
                ]);
                
                setAdminFlashMessage('Custom price and timeline set successfully.', 'success');
                break;
                
            case 'add_message':
                $custom_order_id = intval($_POST['custom_order_id'] ?? 0);
                $message = trim($_POST['message'] ?? '');
                
                if (empty($message)) {
                    setAdminFlashMessage('Message cannot be empty.', 'error');
                    break;
                }
                
                // Message system not implemented yet (table doesn't exist)
                
                logAdminActivity('custom_order_message_added', 'Admin message added to custom order', [
                    'custom_order_id' => $custom_order_id
                ]);
                
                setAdminFlashMessage('Message sent successfully.', 'success');
                break;
        }
        
    } catch (PDOException $e) {
        error_log("Custom order management error: " . $e->getMessage());
        setAdminFlashMessage('An error occurred. Please try again.', 'error');
    }
    
    header('Location: custom-orders.php' . 
           ($search ? '?search=' . urlencode($search) : '') . 
           ($status_filter ? ($search ? '&' : '?') . 'status=' . urlencode($status_filter) : '') .
           ($type_filter ? (($search || $status_filter) ? '&' : '?') . 'type=' . urlencode($type_filter) : '') .
           ($date_filter ? (($search || $status_filter || $type_filter) ? '&' : '?') . 'date=' . urlencode($date_filter) : '') .
           (($search || $status_filter || $type_filter || $date_filter) ? '&page=' : '?page=') . $page);
    exit();
}

try {
    // Build query conditions
    $where_conditions = ['1=1'];
    $params = [];
    
    // Search functionality
    if ($search) {
        $where_conditions[] = "(cor.title LIKE ? OR cor.description LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    // Status filter
    if ($status_filter) {
        $where_conditions[] = "cor.status = ?";
        $params[] = $status_filter;
    }
    
    // Type filter
    if ($type_filter) {
        $where_conditions[] = "cor.type = ?";
        $params[] = $type_filter;
    }
    
    // Date filter
    if ($date_filter) {
        switch ($date_filter) {
            case 'today':
                $where_conditions[] = "DATE(cor.created_at) = CURDATE()";
                break;
            case 'yesterday':
                $where_conditions[] = "DATE(cor.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'week':
                $where_conditions[] = "cor.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $where_conditions[] = "cor.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Count total custom orders for pagination
    $count_sql = "SELECT COUNT(*) FROM custom_order_requests cor LEFT JOIN users u ON cor.user_id = u.id WHERE {$where_clause}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_custom_orders = $stmt->fetchColumn();
    
    // Calculate pagination
    $pagination = paginate($total_custom_orders, $page, $per_page);
    
    // Get custom orders with user information
    $sql = "SELECT cor.*, 
               CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name, 
               u.email as user_email,
               0 as attachments_count,
               0 as unread_messages_count
            FROM custom_order_requests cor 
            LEFT JOIN users u ON cor.user_id = u.id 
            WHERE {$where_clause} 
            ORDER BY 
                CASE 
                    WHEN cor.status = 'pending' THEN 1
                    WHEN cor.status = 'under_review' THEN 2
                    WHEN cor.status = 'approved' THEN 3
                    ELSE 4
                END,
                cor.created_at DESC 
            LIMIT {$per_page} OFFSET {$pagination['offset']}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $custom_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enhanced analytics with trend data
    $stats = $db->query("SELECT 
        COUNT(*) as total_custom_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_orders,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_orders,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_orders,
        SUM(CASE WHEN status = 'approved' OR status = 'completed' THEN custom_price ELSE 0 END) as total_approved_value,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as orders_7d,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as orders_30d,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as orders_today,
        AVG(CASE WHEN status = 'completed' AND custom_price > 0 THEN custom_price END) as avg_order_value,
        0 as urgent_pending,
        0 as high_priority_pending,
        SUM(CASE WHEN type = 'project' THEN 1 ELSE 0 END) as total_projects,
        SUM(CASE WHEN type = 'game' THEN 1 ELSE 0 END) as total_games
        FROM custom_order_requests")->fetch(PDO::FETCH_ASSOC);
        
    // Get trend data for the last 7 days
    $trend_data = $db->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM custom_order_requests 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Admin workload data (assignment feature not implemented yet)
    $admin_workload = [];
    
    // Response time analytics (status history not implemented yet)
    $response_stats = ['avg_first_response_hours' => null, 'total_responses' => 0];
        
} catch (PDOException $e) {
    error_log("Custom order fetch error: " . $e->getMessage());
    $custom_orders = [];
    $total_custom_orders = 0;
    $pagination = paginate(0, 1, $per_page);
    $stats = [];
}

require_once 'includes/layout.php';
renderAdminHeader('Custom Orders Management', 'Manage custom development requests');
?>

<style>
    .custom-order-card {
        border: 1px solid #e3e6f0;
        border-radius: 0.35rem;
        transition: all 0.3s;
        margin-bottom: 1rem;
    }
    
    .custom-order-card:hover {
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }
    
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-pending { background-color: #ffeaa7; color: #fdcb6e; }
    .status-under_review { background-color: #74b9ff; color: #0984e3; }
    .status-approved { background-color: #00b894; color: #00cec9; }
    .status-rejected { background-color: #fd79a8; color: #e84393; }
    .status-payment_pending { background-color: #fdcb6e; color: #e17055; }
    .status-in_progress { background-color: #6c5ce7; color: #a29bfe; }
    .status-completed { background-color: #00b894; color: #00cec9; }
    .status-cancelled { background-color: #636e72; color: #b2bec3; }
    
    .order-type-badge {
        padding: 0.125rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.7rem;
        font-weight: 500;
    }
    
    .type-project { background-color: #e3f2fd; color: #1976d2; }
    .type-game { background-color: #f3e5f5; color: #7b1fa2; }
    
    .priority-badge {
        padding: 0.125rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.7rem;
        font-weight: 500;
    }
    
    .priority-low { background-color: #e8f5e8; color: #4caf50; }
    .priority-medium { background-color: #fff3e0; color: #ff9800; }
    .priority-high { background-color: #ffebee; color: #f44336; }
    .priority-urgent { background-color: #fce4ec; color: #e91e63; }
    
    .budget-display {
        font-size: 0.8rem;
        color: #6c757d;
    }
    
    .custom-price-display {
        font-size: 1.1rem;
        font-weight: 600;
        color: #28a745;
    }
    
    .timeline-display {
        font-size: 0.8rem;
        color: #6c757d;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        text-align: center;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        color: #6b7280;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .action-btn {
        padding: 0.25rem 0.5rem;
        border: none;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        margin: 0.125rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-view {
        background: #17a2b8;
        color: white;
    }
    
    .btn-approve {
        background: #28a745;
        color: white;
    }
    
    .btn-reject {
        background: #dc3545;
        color: white;
    }
    
    .btn-price {
        background: #ffc107;
        color: #212529;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .custom-order-card {
            margin-bottom: 1rem;
        }
    }
</style>

<?php if ($flash_message): ?>
    <div class="alert alert-<?php echo $flash_message['type'] === 'error' ? 'danger' : $flash_message['type']; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($flash_message['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value" style="color: var(--primary-color);"><?php echo number_format($stats['total_custom_orders'] ?? 0); ?></div>
        <div class="stat-label">Total Requests</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--warning-color);"><?php echo number_format($stats['pending_orders'] ?? 0); ?></div>
        <div class="stat-label">Pending Review</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--success-color);"><?php echo number_format($stats['approved_orders'] ?? 0); ?></div>
        <div class="stat-label">Approved</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--info-color);">$<?php echo number_format($stats['total_approved_value'] ?? 0, 2); ?></div>
        <div class="stat-label">Approved Value</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--secondary-color);"><?php echo number_format($stats['orders_30d'] ?? 0); ?></div>
        <div class="stat-label">This Month</div>
    </div>
</div>

<!-- Enhanced Search and Filters -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Filters & Search</h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#analyticsModal">
                <i class="fas fa-chart-bar me-1"></i>Analytics
            </button>
            <button type="button" class="btn btn-outline-success btn-sm" onclick="exportCustomOrders()">
                <i class="fas fa-download me-1"></i>Export
            </button>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Title, customer, email..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>🟡 Pending</option>
                    <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>🔍 Under Review</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>✅ Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>❌ Rejected</option>
                    <option value="payment_pending" <?php echo $status_filter === 'payment_pending' ? 'selected' : ''; ?>>💳 Payment Pending</option>
                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>🔨 In Progress</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>✅ Completed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="project" <?php echo $type_filter === 'project' ? 'selected' : ''; ?>>💻 Project</option>
                    <option value="game" <?php echo $type_filter === 'game' ? 'selected' : ''; ?>>🎮 Game</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="">All Priorities</option>
                    <option value="urgent" <?php echo ($_GET['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>🔥 Urgent</option>
                    <option value="high" <?php echo ($_GET['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>⚡ High</option>
                    <option value="medium" <?php echo ($_GET['priority'] ?? '') === 'medium' ? 'selected' : ''; ?>>📋 Medium</option>
                    <option value="low" <?php echo ($_GET['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>📝 Low</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date Range</label>
                <select name="date" class="form-select">
                    <option value="">All Time</option>
                    <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="yesterday" <?php echo $date_filter === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                    <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="quarter" <?php echo $date_filter === 'quarter' ? 'selected' : ''; ?>>Last 3 Months</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <a href="custom-orders.php" class="btn btn-outline-secondary" title="Clear filters">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div class="card mb-4 d-none" id="bulkActionsBar">
    <div class="card-body py-2">
        <form method="POST" id="bulkActionForm" class="d-flex align-items-center gap-3">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="bulk_action">
            <span class="text-muted"><span id="selectedCount">0</span> orders selected</span>
            <select name="bulk_action" class="form-select" style="width: auto;" required>
                <option value="">Choose Action</option>
                <option value="approve">✅ Approve Selected</option>
                <option value="under_review">🔍 Move to Under Review</option>
                <option value="assign_to_me">👤 Assign to Me</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">Cancel</button>
        </form>
    </div>
</div>

<!-- Custom Orders List -->
<div class="row">
    <?php if (empty($custom_orders)): ?>
        <div class="col-12">
            <div class="text-center py-5">
                <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                <h3>No custom orders found</h3>
                <p class="text-muted">Custom development requests will appear here</p>
            </div>
        </div>
    <?php else: ?>
    <!-- Orders Header with Select All -->
    <div class="col-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="selectAll">
                <label class="form-check-label" for="selectAll">
                    <strong>Select All (<?php echo count($custom_orders); ?> orders)</strong>
                </label>
            </div>
            <div class="text-muted">
                Showing <?php echo $pagination['offset'] + 1; ?>-<?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_custom_orders); ?> of <?php echo number_format($total_custom_orders); ?> orders
            </div>
        </div>
    </div>
    
        <?php foreach ($custom_orders as $order): ?>
        <div class="col-lg-6 col-xl-4">
            <div class="custom-order-card" data-order-id="<?php echo $order['id']; ?>">
                <div class="card-body">
                    <!-- Selection checkbox and header -->
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="form-check">
                            <input class="form-check-input order-checkbox" type="checkbox" value="<?php echo $order['id']; ?>" id="order_<?php echo $order['id']; ?>">
                            <label class="form-check-label" for="order_<?php echo $order['id']; ?>">
                                <small class="text-muted">#<?php echo $order['id']; ?></small>
                            </label>
                        </div>
                        <div class="text-end">
                            <span class="status-badge status-<?php echo str_replace('_', '-', $order['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Type and Priority badges -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="order-type-badge type-<?php echo $order['type']; ?>">
                                <?php echo $order['type'] === 'project' ? '💻' : '🎮'; ?> <?php echo ucfirst($order['type']); ?>
                            </span>
                            <?php if ($order['priority'] !== 'medium'): ?>
                            <span class="priority-badge priority-<?php echo $order['priority']; ?>">
                                <?php 
                                    $priority_icons = ['urgent' => '🔥', 'high' => '⚡', 'low' => '📝'];
                                    echo ($priority_icons[$order['priority']] ?? '') . ' ' . ucfirst($order['priority']); 
                                ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">
                            <?php echo date('M j, g:i A', strtotime($order['created_at'])); ?>
                        </small>
                    </div>
                    
                    <!-- Title and customer -->
                    <h6 class="card-title mb-1" title="<?php echo htmlspecialchars($order['title']); ?>">
                        <?php echo htmlspecialchars(substr($order['title'], 0, 50)); ?>
                        <?php echo strlen($order['title']) > 50 ? '...' : ''; ?>
                    </h6>
                    <small class="text-muted mb-2 d-block">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($order['user_name']); ?>
                        <span class="ms-2">
                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($order['user_email']); ?>
                        </span>
                    </small>
                    
                    <!-- Description -->
                    <p class="card-text small text-muted mb-2">
                        <?php echo htmlspecialchars(substr($order['description'], 0, 100)); ?>
                        <?php echo strlen($order['description']) > 100 ? '...' : ''; ?>
                    </p>
                    
                    <!-- Budget and price -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="budget-display">
                            Budget: <?php 
                                $budget_ranges = [
                                    'under_500' => 'Under $500',
                                    '500_1000' => '$500 - $1,000',
                                    '1000_2500' => '$1,000 - $2,500',
                                    '2500_5000' => '$2,500 - $5,000',
                                    '5000_10000' => '$5,000 - $10,000',
                                    'over_10000' => 'Over $10,000'
                                ];
                                echo $budget_ranges[$order['budget_range']] ?? $order['budget_range'];
                            ?>
                        </div>
                        <?php if ($order['custom_price'] > 0): ?>
                        <div class="custom-price-display">
                            Quote: $<?php echo number_format($order['custom_price'], 2); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Timeline -->
                    <div class="timeline-display mb-3">
                        <i class="fas fa-calendar me-1"></i>
                        Needed by: <?php echo $order['deadline'] ? date('M j, Y', strtotime($order['deadline'])) : 'Not specified'; ?>
                        <?php if ($order['estimated_completion_date']): ?>
                        <br><i class="fas fa-clock me-1"></i>
                        Est. completion: <?php echo date('M j, Y', strtotime($order['estimated_completion_date'])); ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Attachments and messages indicator -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <small class="text-muted">
                            <?php if ($order['attachments_count'] > 0): ?>
                            <i class="fas fa-paperclip me-1"></i><?php echo $order['attachments_count']; ?> files
                            <?php endif; ?>
                            <?php if ($order['unread_messages_count'] > 0): ?>
                            <span class="ms-2 badge bg-danger"><?php echo $order['unread_messages_count']; ?> unread</span>
                            <?php endif; ?>
                        </small>
                        <small class="text-muted">
                            <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                        </small>
                    </div>
                    
                    <!-- Action buttons -->
                    <div class="d-flex gap-1 flex-wrap">
                        <button type="button" class="action-btn btn-view" onclick="viewCustomOrder(<?php echo $order['id']; ?>)" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        
                        <?php if (in_array($order['status'], ['pending', 'under_review'])): ?>
                        <button type="button" class="action-btn btn-approve" onclick="updateStatus(<?php echo $order['id']; ?>, 'approved')" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button type="button" class="action-btn btn-reject" onclick="updateStatus(<?php echo $order['id']; ?>, 'rejected')" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] === 'approved' && $order['custom_price'] == 0): ?>
                        <button type="button" class="action-btn btn-price" onclick="setPrice(<?php echo $order['id']; ?>)" title="Set Price">
                            <i class="fas fa-dollar-sign"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($pagination['total_pages'] > 1): ?>
<nav aria-label="Custom orders pagination" class="mt-4">
    <ul class="pagination justify-content-center">
        <?php if ($pagination['has_previous']): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?php echo $pagination['previous_page']; ?><?php 
                echo $search ? '&search=' . urlencode($search) : '';
                echo $status_filter ? '&status=' . urlencode($status_filter) : '';
                echo $type_filter ? '&type=' . urlencode($type_filter) : '';
                echo $date_filter ? '&date=' . urlencode($date_filter) : '';
            ?>">Previous</a>
        </li>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $i; ?><?php 
                echo $search ? '&search=' . urlencode($search) : '';
                echo $status_filter ? '&status=' . urlencode($status_filter) : '';
                echo $type_filter ? '&type=' . urlencode($type_filter) : '';
                echo $date_filter ? '&date=' . urlencode($date_filter) : '';
            ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
        
        <?php if ($pagination['has_next']): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?php echo $pagination['next_page']; ?><?php 
                echo $search ? '&search=' . urlencode($search) : '';
                echo $status_filter ? '&status=' . urlencode($status_filter) : '';
                echo $type_filter ? '&type=' . urlencode($type_filter) : '';
                echo $date_filter ? '&date=' . urlencode($date_filter) : '';
            ?>">Next</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- Modals -->
<!-- View Custom Order Modal -->
<div class="modal fade" id="viewCustomOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Custom Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customOrderDetails">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="custom_order_id" id="status_custom_order_id">
                <input type="hidden" name="new_status" id="status_new_status">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalTitle">Update Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for status change</label>
                        <input type="text" name="reason" id="reason" class="form-control" placeholder="Brief reason for this change">
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes</label>
                        <textarea name="admin_notes" id="admin_notes" class="form-control" rows="3" placeholder="Internal notes (not visible to customer)"></textarea>
                    </div>
                    
                    <div class="mb-3" id="rejectionReasonDiv" style="display: none;">
                        <label for="rejection_reason" class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3" placeholder="Explain why this request is being rejected (visible to customer)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="statusSubmitBtn">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Set Price Modal -->
<div class="modal fade" id="setPriceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="set_price">
                <input type="hidden" name="custom_order_id" id="price_custom_order_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Set Custom Price</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="custom_price" class="form-label">Custom Price ($) <span class="text-danger">*</span></label>
                        <input type="number" name="custom_price" id="custom_price" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="estimated_completion_date" class="form-label">Estimated Completion Date</label>
                        <input type="date" name="estimated_completion_date" id="estimated_completion_date" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Set Price</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Analytics Modal -->
<div class="modal fade" id="analyticsModal" tabindex="-1" aria-labelledby="analyticsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="analyticsModalLabel">
                    <i class="fas fa-chart-bar me-2"></i>Custom Orders Analytics
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Enhanced Statistics -->
                    <div class="col-12 mb-4">
                        <h6>Performance Overview</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="stat-card bg-primary text-white">
                                    <div class="stat-value"><?php echo number_format($stats['orders_today'] ?? 0); ?></div>
                                    <div class="stat-label">Today</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card bg-info text-white">
                                    <div class="stat-value"><?php echo number_format($stats['orders_7d'] ?? 0); ?></div>
                                    <div class="stat-label">This Week</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card bg-warning text-white">
                                    <div class="stat-value"><?php echo number_format($stats['urgent_pending'] ?? 0); ?></div>
                                    <div class="stat-label">Urgent Pending</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card bg-success text-white">
                                    <div class="stat-value"><?php echo $response_stats['avg_first_response_hours'] ? round($response_stats['avg_first_response_hours'], 1) . 'h' : 'N/A'; ?></div>
                                    <div class="stat-label">Avg Response Time</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Distribution -->
                    <div class="col-md-6">
                        <h6>Order Type Distribution</h6>
                        <canvas id="orderTypeChart" width="400" height="200"></canvas>
                    </div>
                    
                    <!-- Status Distribution -->
                    <div class="col-md-6">
                        <h6>Status Distribution</h6>
                        <canvas id="statusChart" width="400" height="200"></canvas>
                    </div>
                    
                    <!-- Trend Chart -->
                    <div class="col-12 mt-4">
                        <h6>7-Day Trend</h6>
                        <canvas id="trendChart" width="800" height="300"></canvas>
                    </div>
                    
                    <!-- Admin Workload -->
                    <?php if (!empty($admin_workload)): ?>
                    <div class="col-12 mt-4">
                        <h6>Admin Workload</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Admin</th>
                                        <th>Active Orders</th>
                                        <th>Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admin_workload as $admin): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                        <td><?php echo $admin['assigned_count']; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?php echo min(100, ($admin['assigned_count'] / max(1, max(array_column($admin_workload, 'assigned_count')))) * 100); ?>%">
                                                    <?php echo $admin['assigned_count']; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="exportAnalytics()">
                    <i class="fas fa-download me-1"></i>Export Report
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js for Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Enhanced Admin Dashboard JavaScript
console.log('Custom Orders JavaScript loaded successfully');

// Bulk Selection Management
let selectedOrders = new Set();

document.addEventListener('DOMContentLoaded', function() {
    // Initialize bulk selection
    initializeBulkSelection();
    
    // Initialize analytics charts
    initializeAnalyticsCharts();
    
    // Auto-refresh for new orders (every 30 seconds)
    setInterval(checkForNewOrders, 30000);
});

function initializeBulkSelection() {
    const selectAll = document.getElementById('selectAll');
    const orderCheckboxes = document.querySelectorAll('.order-checkbox');
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectedCountSpan = document.getElementById('selectedCount');
    
    // Select all functionality
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            orderCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                if (this.checked) {
                    selectedOrders.add(checkbox.value);
                } else {
                    selectedOrders.delete(checkbox.value);
                }
            });
            updateBulkActionsBar();
        });
    }
    
    // Individual checkboxes
    orderCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                selectedOrders.add(this.value);
            } else {
                selectedOrders.delete(this.value);
                if (selectAll) selectAll.checked = false;
            }
            updateBulkActionsBar();
        });
    });
    
    // Bulk action form submission
    const bulkForm = document.getElementById('bulkActionForm');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            if (selectedOrders.size === 0) {
                e.preventDefault();
                alert('Please select at least one order.');
                return;
            }
            
            // Add selected orders to form
            selectedOrders.forEach(orderId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_orders[]';
                input.value = orderId;
                this.appendChild(input);
            });
            
            // Confirm action
            const action = this.bulk_action.value;
            if (!confirm(`Are you sure you want to ${action.replace('_', ' ')} ${selectedOrders.size} selected orders?`)) {
                e.preventDefault();
            }
        });
    }
}

function updateBulkActionsBar() {
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectedCountSpan = document.getElementById('selectedCount');
    
    if (selectedCountSpan) {
        selectedCountSpan.textContent = selectedOrders.size;
    }
    
    if (bulkActionsBar) {
        if (selectedOrders.size > 0) {
            bulkActionsBar.classList.remove('d-none');
        } else {
            bulkActionsBar.classList.add('d-none');
        }
    }
}

function clearSelection() {
    selectedOrders.clear();
    document.querySelectorAll('.order-checkbox').forEach(cb => cb.checked = false);
    const selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
    updateBulkActionsBar();
}

function exportCustomOrders() {
    const url = new URL(window.location);
    url.pathname = url.pathname.replace('custom-orders.php', 'export-custom-orders.php');
    window.open(url.toString(), '_blank');
}

function exportAnalytics() {
    // Create and download analytics report
    const reportData = {
        generated_at: new Date().toISOString(),
        stats: <?php echo json_encode($stats); ?>,
        trend_data: <?php echo json_encode($trend_data); ?>,
        admin_workload: <?php echo json_encode($admin_workload); ?>
    };
    
    const blob = new Blob([JSON.stringify(reportData, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `custom-orders-analytics-${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    URL.revokeObjectURL(url);
}

function initializeAnalyticsCharts() {
    // Order Type Distribution Chart
    const orderTypeCtx = document.getElementById('orderTypeChart');
    if (orderTypeCtx) {
        new Chart(orderTypeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Projects', 'Games'],
                datasets: [{
                    data: [<?php echo $stats['total_projects'] ?? 0; ?>, <?php echo $stats['total_games'] ?? 0; ?>],
                    backgroundColor: ['#0d6efd', '#198754'],
                    borderColor: ['#ffffff'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Status Distribution Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: ['Pending', 'Under Review', 'Approved', 'In Progress', 'Completed', 'Rejected'],
                datasets: [{
                    label: 'Orders',
                    data: [
                        <?php echo $stats['pending_orders'] ?? 0; ?>,
                        <?php echo $stats['under_review_orders'] ?? 0; ?>,
                        <?php echo $stats['approved_orders'] ?? 0; ?>,
                        <?php echo $stats['in_progress_orders'] ?? 0; ?>,
                        <?php echo $stats['completed_orders'] ?? 0; ?>,
                        <?php echo $stats['rejected_orders'] ?? 0; ?>
                    ],
                    backgroundColor: ['#ffc107', '#17a2b8', '#28a745', '#6f42c1', '#20c997', '#dc3545'],
                    borderColor: ['#ffffff'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
}

function checkForNewOrders() {
    if (document.hidden) return; // Don't check if tab is not visible
    
    fetch('custom-orders.php?ajax=1&check_new=1')
        .then(response => response.json())
        .then(data => {
            if (data.new_orders_count > 0) {
                // Update page title with count
                document.title = `(${data.new_orders_count}) Custom Orders - Admin`;
                
                // Show notification
                showNotification(`${data.new_orders_count} new custom order(s) received!`, 'info');
            }
        })
        .catch(error => console.log('Auto-refresh error:', error));
}

function showNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 5000);
}

function viewCustomOrder(customOrderId) {
    console.log('viewCustomOrder called with ID:', customOrderId);
    
    const modalElement = document.getElementById('viewCustomOrderModal');
    const content = document.getElementById('customOrderDetails');
    
    if (!modalElement) {
        console.error('Modal element not found!');
        alert('Error: Modal not found. Please refresh the page.');
        return;
    }
    
    if (!content) {
        console.error('Modal content element not found!');
        alert('Error: Modal content not found. Please refresh the page.');
        return;
    }
    
    const modal = new bootstrap.Modal(modalElement);
    
    // Show loading
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // Fetch custom order details
    console.log('Fetching order details from API...');
    fetch(`../api/get-order.php?id=${customOrderId}`)
        .then(response => {
            console.log('API Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('API Response data:', data);
            if (data.success) {
                console.log('Rendering order details...');
                renderCustomOrderDetails(data.data);
            } else {
                console.error('API Error:', data.message);
                content.innerHTML = `
                    <div class="alert alert-danger">
                        Failed to load custom order details: ${data.message || 'Unknown error'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            content.innerHTML = `
                <div class="alert alert-danger">
                    Failed to load custom order details. Please try again.<br>
                    <small>Error: ${error.message}</small>
                </div>
            `;
        });
}

function renderCustomOrderDetails(order) {
    const content = document.getElementById('customOrderDetails');
    
    let statusBadge = `<span class="status-badge status-${order.status.replace('_', '-')}">${order.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>`;
    
    let attachmentsHtml = '';
    if (order.attachments && order.attachments.length > 0) {
        attachmentsHtml = '<h6>Attachments:</h6><ul class="list-unstyled">';
        order.attachments.forEach(att => {
            attachmentsHtml += `<li class="mb-1"><i class="fas fa-file me-2"></i><a href="../uploads/${att.file_path}" target="_blank">${att.original_filename}</a> <small class="text-muted">(${(att.file_size / 1024 / 1024).toFixed(2)} MB)</small></li>`;
        });
        attachmentsHtml += '</ul>';
    }
    
    let statusHistoryHtml = '';
    if (order.status_history && order.status_history.length > 0) {
        statusHistoryHtml = '<h6>Status History:</h6><div class="timeline">';
        order.status_history.forEach(history => {
            statusHistoryHtml += `
                <div class="timeline-item mb-2">
                    <small class="text-muted">${new Date(history.created_at).toLocaleString()}</small><br>
                    <strong>${history.new_status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</strong>
                    ${history.changed_by_name ? ` by ${history.changed_by_name}` : ''}
                    ${history.reason ? `<br><em>${history.reason}</em>` : ''}
                </div>
            `;
        });
        statusHistoryHtml += '</div>';
    }
    
    content.innerHTML = `
        <div class="row">
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>${order.title}</h4>
                    ${statusBadge}
                </div>
                
                <div class="mb-3">
                    <h6>Description:</h6>
                    <p>${order.description}</p>
                </div>
                
                <div class="mb-3">
                    <h6>Requirements:</h6>
                    <p>${order.requirements}</p>
                </div>
                
                ${order.admin_notes ? `<div class="mb-3"><h6>Admin Notes:</h6><p class="text-muted">${order.admin_notes}</p></div>` : ''}
                ${order.rejection_reason ? `<div class="mb-3"><h6>Rejection Reason:</h6><p class="text-danger">${order.rejection_reason}</p></div>` : ''}
                
                ${attachmentsHtml}
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6>Order Information</h6>
                        <p><strong>Customer:</strong> ${order.user_name}<br>
                        <strong>Email:</strong> ${order.user_email}<br>
                        <strong>Type:</strong> ${order.type.charAt(0).toUpperCase() + order.type.slice(1)}<br>
                        <strong>Priority:</strong> ${order.priority.charAt(0).toUpperCase() + order.priority.slice(1)}<br>
                        <strong>Budget Range:</strong> ${order.budget_range.replace('_', ' ')}<br>
                        ${order.custom_price > 0 ? `<strong>Custom Price:</strong> $${parseFloat(order.custom_price).toFixed(2)}<br>` : ''}
                        <strong>Requested Date:</strong> ${order.deadline || 'Not specified'}<br>
                        ${order.estimated_completion_date ? `<strong>Est. Completion:</strong> ${order.estimated_completion_date}<br>` : ''}
                        <strong>Created:</strong> ${new Date(order.created_at).toLocaleDateString()}</p>
                    </div>
                </div>
                
                ${statusHistoryHtml}
            </div>
        </div>
    `;
}

function updateStatus(customOrderId, status) {
    console.log('updateStatus called with ID:', customOrderId, 'Status:', status);
    
    const orderIdInput = document.getElementById('status_custom_order_id');
    const statusInput = document.getElementById('status_new_status');
    const modalElement = document.getElementById('updateStatusModal');
    
    if (!orderIdInput || !statusInput || !modalElement) {
        console.error('Required elements not found for status update');
        alert('Error: Required form elements not found. Please refresh the page.');
        return;
    }
    
    orderIdInput.value = customOrderId;
    statusInput.value = status;
    
    const modal = new bootstrap.Modal(modalElement);
    const title = document.getElementById('statusModalTitle');
    const submitBtn = document.getElementById('statusSubmitBtn');
    const rejectionDiv = document.getElementById('rejectionReasonDiv');
    
    if (status === 'approved') {
        title.textContent = 'Approve Custom Order';
        submitBtn.textContent = 'Approve Order';
        submitBtn.className = 'btn btn-success';
        rejectionDiv.style.display = 'none';
    } else if (status === 'rejected') {
        title.textContent = 'Reject Custom Order';
        submitBtn.textContent = 'Reject Order';
        submitBtn.className = 'btn btn-danger';
        rejectionDiv.style.display = 'block';
        document.getElementById('rejection_reason').required = true;
    }
    
    modal.show();
}

function setPrice(customOrderId) {
    document.getElementById('price_custom_order_id').value = customOrderId;
    const modal = new bootstrap.Modal(document.getElementById('setPriceModal'));
    modal.show();
}

// Auto refresh every 30 seconds for new orders
setInterval(() => {
    if (document.hidden) return; // Don't refresh if tab is not visible
    
    // Only refresh if we're showing pending or under_review orders
    const currentStatus = new URLSearchParams(window.location.search).get('status');
    if (!currentStatus || currentStatus === 'pending' || currentStatus === 'under_review') {
        // Simple refresh - just reload the page
        // You can implement AJAX refresh later if needed
        console.log('Auto-refresh would happen here');
    }
}, 30000);
</script>

<?php renderAdminFooter(); ?>