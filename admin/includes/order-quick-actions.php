<?php
/**
 * Quick Actions Panel for Admin Orders
 * Provides one-click actions for common order management tasks
 */

// Get pending orders count for the quick actions panel
function getPendingOrdersCount($db) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Get recent orders that need attention
function getOrdersNeedingAttention($db, $limit = 5) {
    $sql = "SELECT o.id, o.created_at, o.total_amount, o.status, o.payment_status,
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   u.email as customer_email,
                   COUNT(oi.id) as item_count
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.status IN ('pending', 'processing')
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// Get today's order statistics
function getTodayOrderStats($db) {
    $sql = "SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) as revenue
            FROM orders 
            WHERE DATE(created_at) = CURDATE()";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetch();
}
?>

<!-- Quick Actions Panel -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions Dashboard
                </h5>
            </div>
            <div class="card-body">
                <?php 
                $pendingCount = getPendingOrdersCount($db);
                $todayStats = getTodayOrderStats($db);
                $urgentOrders = getOrdersNeedingAttention($db, 5);
                ?>
                
                <!-- Today's Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="d-flex align-items-center p-3 bg-light rounded">
                            <div class="flex-shrink-0">
                                <i class="fas fa-shopping-cart fa-2x text-primary"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold fs-4"><?php echo $todayStats['total_orders']; ?></div>
                                <div class="small text-muted">Orders Today</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center p-3 bg-warning bg-opacity-10 rounded">
                            <div class="flex-shrink-0">
                                <i class="fas fa-clock fa-2x text-warning"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold fs-4"><?php echo $pendingCount; ?></div>
                                <div class="small text-muted">Pending Orders</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center p-3 bg-success bg-opacity-10 rounded">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold fs-4"><?php echo $todayStats['completed_orders']; ?></div>
                                <div class="small text-muted">Completed Today</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center p-3 bg-info bg-opacity-10 rounded">
                            <div class="flex-shrink-0">
                                <i class="fas fa-dollar-sign fa-2x text-info"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold fs-4">$<?php echo number_format($todayStats['revenue'], 2); ?></div>
                                <div class="small text-muted">Revenue Today</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Action Buttons -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-tasks me-2"></i>Quick Actions
                        </h6>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="orders.php?status=pending" class="btn btn-warning">
                                <i class="fas fa-clock me-1"></i>
                                Pending Orders (<?php echo $pendingCount; ?>)
                            </a>
                            
                            <?php if ($pendingCount > 0): ?>
                            <button type="button" class="btn btn-success" onclick="bulkApproveOrders()">
                                <i class="fas fa-check-double me-1"></i>
                                Approve All Pending
                            </button>
                            <?php endif; ?>
                            
                            <a href="orders.php?date=today" class="btn btn-primary">
                                <i class="fas fa-calendar-day me-1"></i>
                                Today's Orders
                            </a>
                            
                            <a href="orders.php?payment_status=failed" class="btn btn-danger">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Failed Payments
                            </a>
                            
                            <button type="button" class="btn btn-outline-info" onclick="exportOrders()">
                                <i class="fas fa-download me-1"></i>
                                Export Orders
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Orders Needing Attention -->
                <?php if (!empty($urgentOrders)): ?>
                <div class="row">
                    <div class="col-12">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-bell me-2"></i>Orders Needing Attention
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Age</th>
                                        <th>Quick Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($urgentOrders as $order): ?>
                                    <tr>
                                        <td>
                                            <a href="#" onclick="viewOrderDetails(<?php echo $order['id']; ?>)" class="fw-bold text-decoration-none">
                                                #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                        </td>
                                        <td>
                                            <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                            <small class="d-block text-muted"><?php echo $order['item_count']; ?> items</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $order['status'] === 'pending' ? 'warning' : 'info'; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $created = new DateTime($order['created_at']);
                                            $now = new DateTime();
                                            $diff = $now->diff($created);
                                            
                                            if ($diff->days > 0) {
                                                echo $diff->days . ' days ago';
                                            } elseif ($diff->h > 0) {
                                                echo $diff->h . ' hours ago';
                                            } else {
                                                echo $diff->i . ' minutes ago';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($order['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-success btn-sm" 
                                                        onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'completed')"
                                                        title="Approve & Complete">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-warning btn-sm" 
                                                        onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'processing')"
                                                        title="Mark as Processing">
                                                    <i class="fas fa-clock"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                                        onclick="viewOrderDetails(<?php echo $order['id']; ?>)"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($urgentOrders) >= 5): ?>
                        <div class="text-center mt-3">
                            <a href="orders.php?status=pending" class="btn btn-outline-primary">
                                View All Pending Orders <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Quick status update function
function quickUpdateStatus(orderId, newStatus) {
    if (!confirm(`Are you sure you want to change this order to ${newStatus}?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('order_id', orderId);
    formData.append('new_status', newStatus);
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    
    fetch('orders.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        // Show success message and reload
        showToast(`Order #${orderId.toString().padStart(6, '0')} status updated to ${newStatus}`, 'success');
        setTimeout(() => location.reload(), 1000);
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error updating order status', 'error');
    });
}

// Bulk approve all pending orders
function bulkApproveOrders() {
    if (!confirm('Are you sure you want to approve ALL pending orders? This will mark them as completed and enable downloads.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'bulk_approve_all');
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    
    fetch('orders.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        showToast('All pending orders have been approved!', 'success');
        setTimeout(() => location.reload(), 1000);
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error approving orders', 'error');
    });
}

// View order details
function viewOrderDetails(orderId) {
    window.open(`../user/order-details.php?id=${orderId}`, '_blank');
}

// Export orders
function exportOrders() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = 'orders.php?' + params.toString();
}

// Toast notification function
function showToast(message, type = 'success') {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    // Create toast container if it doesn't exist
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = toastContainer.lastElementChild;
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}
</script>