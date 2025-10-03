<?php
/**
 * Admin Order Management
 * 
 * Comprehensive interface for managing orders, payments, refunds,
 * and processing transactions with detailed order tracking.
 */

require_once 'includes/auth.php';

$current_admin = getCurrentAdmin();
$flash_message = getAdminFlashMessage();

// Get filters and pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$payment_status_filter = $_GET['payment_status'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        setAdminFlashMessage('Invalid security token. Please try again.', 'error');
        header('Location: orders.php');
        exit();
    }
    
    try {
        switch ($action) {
            case 'update_status':
                $order_id = intval($_POST['order_id'] ?? 0);
                $new_status = $_POST['new_status'] ?? '';
                
                $valid_statuses = ['pending', 'processing', 'completed', 'cancelled', 'refunded'];
                if (!in_array($new_status, $valid_statuses)) {
                    setAdminFlashMessage('Invalid order status specified.', 'error');
                    break;
                }
                
                $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $order_id]);
                
                // Log status change
                logAdminActivity('order_status_change', "Order status changed to {$new_status}", [
                    'order_id' => $order_id,
                    'new_status' => $new_status
                ]);
                
                // Update payment status based on order status
                if ($new_status === 'completed') {
                    $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ? AND payment_status = 'pending'");
                    $stmt->execute([$order_id]);
                } elseif ($new_status === 'refunded') {
                    $stmt = $db->prepare("UPDATE orders SET payment_status = 'refunded' WHERE id = ?");
                    $stmt->execute([$order_id]);
                }
                
                setAdminFlashMessage("Order status updated to {$new_status} successfully.", 'success');
                break;
                
            case 'update_payment_status':
                $order_id = intval($_POST['order_id'] ?? 0);
                $new_payment_status = $_POST['new_payment_status'] ?? '';
                
                $valid_payment_statuses = ['pending', 'paid', 'failed', 'refunded'];
                if (!in_array($new_payment_status, $valid_payment_statuses)) {
                    setAdminFlashMessage('Invalid payment status specified.', 'error');
                    break;
                }
                
                $stmt = $db->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_payment_status, $order_id]);
                
                logAdminActivity('payment_status_change', "Payment status changed to {$new_payment_status}", [
                    'order_id' => $order_id,
                    'new_payment_status' => $new_payment_status
                ]);
                
                // Auto-update order status based on payment
                if ($new_payment_status === 'paid') {
                    $stmt = $db->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND status = 'pending'");
                    $stmt->execute([$order_id]);
                } elseif ($new_payment_status === 'refunded') {
                    $stmt = $db->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?");
                    $stmt->execute([$order_id]);
                }
                
                setAdminFlashMessage("Payment status updated to {$new_payment_status} successfully.", 'success');
                break;
                
            case 'process_refund':
                $order_id = intval($_POST['order_id'] ?? 0);
                $refund_reason = trim($_POST['refund_reason'] ?? '');
                
                // Get order details
                $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$order) {
                    setAdminFlashMessage('Order not found.', 'error');
                    break;
                }
                
                if ($order['payment_status'] !== 'paid') {
                    setAdminFlashMessage('Only paid orders can be refunded.', 'error');
                    break;
                }
                
                // Update order to refunded status
                $stmt = $db->prepare("UPDATE orders SET status = 'refunded', payment_status = 'refunded', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$order_id]);
                
                // Log refund
                logAdminActivity('order_refunded', "Order refunded: {$refund_reason}", [
                    'order_id' => $order_id,
                    'refund_reason' => $refund_reason,
                    'refund_amount' => $order['total_amount']
                ]);
                
                setAdminFlashMessage("Order #{$order_id} has been refunded successfully.", 'success');
                break;
                
            case 'delete_order':
                $order_id = intval($_POST['order_id'] ?? 0);
                
                // Check if order can be deleted (only pending/cancelled orders)
                $stmt = $db->prepare("SELECT status FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);
                $order_status = $stmt->fetchColumn();
                
                if (!in_array($order_status, ['pending', 'cancelled'])) {
                    setAdminFlashMessage('Only pending or cancelled orders can be deleted.', 'error');
                    break;
                }
                
                // Delete order items first, then order
                $db->beginTransaction();
                
                $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = ?");
                $stmt->execute([$order_id]);
                
                $stmt = $db->prepare("DELETE FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);
                
                $db->commit();
                
                logAdminActivity('order_deleted', "Order deleted", [
                    'order_id' => $order_id
                ]);
                
                setAdminFlashMessage('Order deleted successfully.', 'success');
                break;
                
            case 'bulk_action':
                $bulk_action = $_POST['bulk_action'] ?? '';
                $selected_orders = $_POST['selected_orders'] ?? [];
                
                if ($bulk_action && !empty($selected_orders)) {
                    $placeholders = str_repeat('?,', count($selected_orders) - 1) . '?';
                    
                    switch ($bulk_action) {
                        case 'mark_completed':
                            $stmt = $db->prepare("UPDATE orders SET status = 'completed', payment_status = 'paid' WHERE id IN ({$placeholders}) AND status IN ('pending', 'processing')");
                            $stmt->execute($selected_orders);
                            setAdminFlashMessage(count($selected_orders) . ' orders marked as completed.', 'success');
                            break;
                            
                        case 'mark_cancelled':
                            $stmt = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id IN ({$placeholders}) AND status = 'pending'");
                            $stmt->execute($selected_orders);
                            setAdminFlashMessage(count($selected_orders) . ' orders cancelled.', 'success');
                            break;
                            
                        case 'mark_processing':
                            $stmt = $db->prepare("UPDATE orders SET status = 'processing' WHERE id IN ({$placeholders}) AND status = 'pending'");
                            $stmt->execute($selected_orders);
                            setAdminFlashMessage(count($selected_orders) . ' orders marked as processing.', 'success');
                            break;
                    }
                    
                    logAdminActivity('bulk_order_action', "Bulk action '{$bulk_action}' performed on orders", [
                        'action' => $bulk_action,
                        'orders_count' => count($selected_orders)
                    ]);
                }
                break;
        }
        
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Order management error: " . $e->getMessage());
        setAdminFlashMessage('An error occurred. Please try again.', 'error');
    }
    
    header('Location: orders.php' . 
           ($search ? '?search=' . urlencode($search) : '') . 
           ($status_filter ? ($search ? '&' : '?') . 'status=' . urlencode($status_filter) : '') .
           ($payment_status_filter ? (($search || $status_filter) ? '&' : '?') . 'payment_status=' . urlencode($payment_status_filter) : '') .
           ($date_filter ? (($search || $status_filter || $payment_status_filter) ? '&' : '?') . 'date=' . urlencode($date_filter) : '') .
           (($search || $status_filter || $payment_status_filter || $date_filter) ? '&page=' : '?page=') . $page);
    exit();
}

try {
    // Build query conditions
    $where_conditions = ['1=1'];
    $params = [];
    
    // Search functionality
    if ($search) {
        $where_conditions[] = "(o.id LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    // Status filter
    if ($status_filter) {
        $where_conditions[] = "o.status = ?";
        $params[] = $status_filter;
    }
    
    // Payment status filter
    if ($payment_status_filter) {
        $where_conditions[] = "o.payment_status = ?";
        $params[] = $payment_status_filter;
    }
    
    // Date filter
    if ($date_filter) {
        switch ($date_filter) {
            case 'today':
                $where_conditions[] = "DATE(o.created_at) = CURDATE()";
                break;
            case 'yesterday':
                $where_conditions[] = "DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'week':
                $where_conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $where_conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Count total orders for pagination
    $count_sql = "SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE {$where_clause}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_orders = $stmt->fetchColumn();
    
    // Calculate pagination
    $pagination = paginate($total_orders, $page, $per_page);
    
    // Get orders with user information
    $sql = "SELECT o.*, CONCAT(u.first_name, ' ', u.last_name) as full_name, u.email,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            WHERE {$where_clause} 
            ORDER BY o.created_at DESC 
            LIMIT {$per_page} OFFSET {$pagination['offset']}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order statistics
    $stats = $db->query("SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
        SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) as refunded_orders,
        SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END) as revenue_30d,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as orders_7d,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as orders_30d,
        AVG(total_amount) as avg_order_value
        FROM orders")->fetch(PDO::FETCH_ASSOC);
        
} catch (PDOException $e) {
    error_log("Order fetch error: " . $e->getMessage());
    $orders = [];
    $total_orders = 0;
    $pagination = paginate(0, 1, $per_page);
    $stats = [];
}

require_once 'includes/layout.php';
renderAdminHeader('Order Management', 'Manage orders, payments, and transactions');
?>

<style>
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

        .orders-table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--secondary-color);
            display: flex;
            justify-content: between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .search-filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input {
            width: 300px;
            border: 2px solid var(--secondary-color);
            border-radius: 8px;
            padding: 0.5rem 1rem;
        }

        .search-input:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .filter-select {
            min-width: 150px;
            border: 2px solid var(--secondary-color);
            border-radius: 8px;
            padding: 0.5rem;
        }

        .orders-table {
            width: 100%;
            margin: 0;
        }

        .orders-table th {
            background: var(--light-color);
            color: var(--dark-color);
            font-weight: 600;
            padding: 1rem;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .orders-table td {
            padding: 1rem;
            border-top: 1px solid var(--secondary-color);
            vertical-align: middle;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.1);
            color: #92400e;
        }

        .status-processing {
            background: rgba(59, 130, 246, 0.1);
            color: #1d4ed8;
        }

        .status-completed {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .status-refunded {
            background: rgba(147, 51, 234, 0.1);
            color: #7c2d12;
        }

        .payment-paid {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .payment-pending {
            background: rgba(251, 191, 36, 0.1);
            color: #92400e;
        }

        .payment-failed {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .payment-refunded {
            background: rgba(147, 51, 234, 0.1);
            color: #7c2d12;
        }

        .action-btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            margin: 0 0.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: var(--info-color);
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .pagination-container {
            padding: 1.5rem;
            border-top: 1px solid var(--secondary-color);
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .order-details-modal .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }

        @media (max-width: 768px) {
            .search-filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                width: 100%;
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
                    <div class="stat-value" style="color: var(--primary-color);"><?php echo number_format($stats['total_orders'] ?? 0); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--success-color);">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--warning-color);"><?php echo number_format($stats['pending_orders'] ?? 0); ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--info-color);">$<?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?></div>
                    <div class="stat-label">Average Order Value</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--success-color);"><?php echo number_format($stats['orders_30d'] ?? 0); ?></div>
                    <div class="stat-label">Orders This Month</div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="orders-table-container">
                <div class="table-header">
                    <div class="search-filters">
                        <form method="GET" class="d-flex gap-3 align-items-center">
                            <input type="text" 
                                   name="search" 
                                   class="search-input" 
                                   placeholder="Search orders..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            
                            <select name="status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                            
                            <select name="payment_status" class="filter-select">
                                <option value="">All Payment Status</option>
                                <option value="pending" <?php echo $payment_status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo $payment_status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="failed" <?php echo $payment_status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="refunded" <?php echo $payment_status_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                            
                            <select name="date" class="filter-select">
                                <option value="">All Time</option>
                                <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="yesterday" <?php echo $date_filter === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                            </select>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            
                            <a href="orders.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </form>
                    </div>
                </div>

                <form method="POST" id="bulkForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="bulk_action">
                    
                    <div class="px-3 py-2 border-bottom d-flex align-items-center gap-3">
                        <select name="bulk_action" class="form-select" style="width: 200px;">
                            <option value="">Bulk Actions</option>
                            <option value="mark_completed">Mark as Completed</option>
                            <option value="mark_processing">Mark as Processing</option>
                            <option value="mark_cancelled">Mark as Cancelled</option>
                        </select>
                        <button type="submit" class="btn btn-outline-primary btn-sm">Apply</button>
                    </div>

                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table orders-table">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Items</th>
                                    <th>Date</th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_orders[]" value="<?php echo $order['id']; ?>" class="order-checkbox">
                                        </td>
                                        
                                        <td>
                                            <div class="fw-semibold">#<?php echo $order['id']; ?></div>
                                            <small class="text-muted"><?php echo substr($order['order_hash'] ?? '', 0, 8); ?>...</small>
                                        </td>
                                        
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($order['full_name'] ?? 'Guest'); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['email'] ?? 'N/A'); ?></small>
                                        </td>
                                        
                                        <td>
                                            <div class="fw-semibold">$<?php echo number_format($order['total_amount'], 2); ?></div>
                                            <small class="text-muted"><?php echo $order['items_count']; ?> items</small>
                                        </td>
                                        
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        
                                        <td>
                                            <span class="status-badge payment-<?php echo $order['payment_status']; ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </td>
                                        
                                        <td><?php echo $order['items_count']; ?></td>
                                        
                                        <td>
                                            <div><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                                        </td>
                                        
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <!-- View Details -->
                                                <button type="button" class="action-btn btn-edit" 
                                                        onclick="viewOrderDetails(<?php echo $order['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <!-- Status Actions -->
                                                <?php if ($order['status'] === 'pending'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="new_status" value="processing">
                                                        <button type="submit" class="action-btn btn-warning" title="Mark Processing">
                                                            <i class="fas fa-clock"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="new_status" value="completed">
                                                        <button type="submit" class="action-btn btn-success" title="Mark Completed">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($order['status'] === 'processing'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="new_status" value="completed">
                                                        <button type="submit" class="action-btn btn-success" title="Mark Completed">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <!-- Refund Button -->
                                                <?php if ($order['payment_status'] === 'paid' && $order['status'] !== 'refunded'): ?>
                                                    <button type="button" class="action-btn btn-warning" 
                                                            onclick="showRefundModal(<?php echo $order['id']; ?>)" title="Process Refund">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <!-- Cancel/Delete -->
                                                <?php if (in_array($order['status'], ['pending', 'cancelled'])): ?>
                                                    <form method="POST" style="display: inline;"
                                                          onsubmit="return confirm('Are you sure you want to delete this order?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="delete_order">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <button type="submit" class="action-btn btn-danger" title="Delete Order">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif ($order['status'] === 'pending'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="new_status" value="cancelled">
                                                        <button type="submit" class="action-btn btn-danger" title="Cancel Order">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5">
                                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No orders found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination-container">
                        <div>
                            Showing <?php echo number_format($pagination['offset'] + 1); ?> to 
                            <?php echo number_format(min($pagination['offset'] + $per_page, $total_orders)); ?> of 
                            <?php echo number_format($total_orders); ?> orders
                        </div>
                        <nav>
                            <ul class="pagination mb-0">
                                <?php if ($pagination['has_previous']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $pagination['previous_page']; ?><?php 
                                            echo $search ? '&search=' . urlencode($search) : '';
                                            echo $status_filter ? '&status=' . urlencode($status_filter) : '';
                                            echo $payment_status_filter ? '&payment_status=' . urlencode($payment_status_filter) : '';
                                            echo $date_filter ? '&date=' . urlencode($date_filter) : '';
                                        ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                            echo $search ? '&search=' . urlencode($search) : '';
                                            echo $status_filter ? '&status=' . urlencode($status_filter) : '';
                                            echo $payment_status_filter ? '&payment_status=' . urlencode($payment_status_filter) : '';
                                            echo $date_filter ? '&date=' . urlencode($date_filter) : '';
                                        ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($pagination['has_next']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $pagination['next_page']; ?><?php 
                                            echo $search ? '&search=' . urlencode($search) : '';
                                            echo $status_filter ? '&status=' . urlencode($status_filter) : '';
                                            echo $payment_status_filter ? '&payment_status=' . urlencode($payment_status_filter) : '';
                                            echo $date_filter ? '&date=' . urlencode($date_filter) : '';
                                        ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Refund Modal -->
    <div class="modal fade" id="refundModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="process_refund">
                    <input type="hidden" name="order_id" id="refund_order_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Process Refund</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="refund_reason" class="form-label">Refund Reason</label>
                            <textarea name="refund_reason" id="refund_reason" class="form-control" rows="3" 
                                      placeholder="Enter reason for refund..." required></textarea>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This action will refund the order and cannot be undone.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Process Refund</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Select all checkbox functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        document.querySelectorAll('.order-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.order-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
                const selectAll = document.getElementById('selectAll');
                
                selectAll.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
                selectAll.checked = checkedCheckboxes.length === allCheckboxes.length;
            });
        });

        // Bulk actions form validation
        document.getElementById('bulkForm').addEventListener('submit', function(e) {
            const selectedOrders = document.querySelectorAll('.order-checkbox:checked');
            const bulkAction = document.querySelector('[name="bulk_action"]').value;
            
            if (!bulkAction) {
                e.preventDefault();
                alert('Please select a bulk action.');
                return;
            }
            
            if (selectedOrders.length === 0) {
                e.preventDefault();
                alert('Please select at least one order.');
                return;
            }
            
            const actionText = bulkAction.replace('mark_', '').replace('_', ' ');
            if (!confirm(`Are you sure you want to ${actionText} ${selectedOrders.length} selected order(s)?`)) {
                e.preventDefault();
                return;
            }
        });

        // View order details
        function viewOrderDetails(orderId) {
            const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            
            // Load order details via AJAX (you would implement this endpoint)
            fetch(`order_details.php?id=${orderId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('orderDetailsContent').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('orderDetailsContent').innerHTML = 
                        '<div class="alert alert-danger">Error loading order details.</div>';
                });
            
            modal.show();
        }

        // Show refund modal
        function showRefundModal(orderId) {
            document.getElementById('refund_order_id').value = orderId;
            const modal = new bootstrap.Modal(document.getElementById('refundModal'));
            modal.show();
        }
    </script>

<?php renderAdminFooter(); ?>
