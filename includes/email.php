<?php
class EmailService {
    private $db;
    private $from_email;
    private $from_name;
    private $notificationService;
    
    public function __construct($database) {
        $this->db = $database;
        $this->from_email = 'noreply@bitversity.com';
        $this->from_name = 'Bitversity';
    }
    
    private function getNotificationService() {
        if (!$this->notificationService) {
            require_once __DIR__ . '/notification-service.php';
            $this->notificationService = new NotificationService($this->db);
        }
        return $this->notificationService;
    }
    
    public function sendOrderConfirmation($order_id) {
        try {
            // Get order details
            $stmt = $this->db->prepare("
                SELECT o.*, u.first_name, u.last_name, u.email
                FROM orders o
                JOIN users u ON o.user_id = u.id
                WHERE o.id = ?
            ");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) return false;
            
            // Get order items
            $stmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $subject = "Order Confirmation #" . $order['id'] . " - Bitversity";
            $body = $this->generateOrderConfirmationEmail($order, $items);
            
            return $this->sendEmail($order['email'], $subject, $body);
            
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendWelcomeEmail($user_email, $user_name) {
        try {
            $subject = "Welcome to Bitversity!";
            $body = $this->generateWelcomeEmail($user_name);
            
            return $this->sendEmail($user_email, $subject, $body);
            
        } catch (Exception $e) {
            error_log("Welcome email error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendCustomOrderSubmissionConfirmation($custom_order_id) {
        try {
            // Get custom order details with user info
            $stmt = $this->db->prepare("
                SELECT cor.*, u.first_name, u.last_name, u.email, u.full_name
                FROM custom_order_requests cor
                JOIN users u ON cor.user_id = u.id
                WHERE cor.id = ?
            ");
            $stmt->execute([$custom_order_id]);
            $customOrder = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customOrder) return false;
            
            // Check if user allows this notification
            $notificationService = $this->getNotificationService();
            if (!$notificationService->canSendNotification($customOrder['user_id'], 'custom_order_submission')) {
                return true; // User opted out, not an error
            }
            
            $subject = "Custom Order Request Received - Bitversity (#{$custom_order_id})";
            $body = $this->generateCustomOrderSubmissionEmail($customOrder);
            
            // Add unsubscribe link
            $body = $notificationService->addUnsubscribeLink($body, $customOrder['user_id'], 'custom_order_submission');
            
            return $this->sendEmail($customOrder['email'], $subject, $body);
            
        } catch (Exception $e) {
            error_log("Custom order submission email error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendCustomOrderStatusUpdate($custom_order_id, $old_status, $new_status, $reason = '') {
        try {
            // Get custom order details with user info
            $stmt = $this->db->prepare("
                SELECT cor.*, u.first_name, u.last_name, u.email, u.full_name
                FROM custom_order_requests cor
                JOIN users u ON cor.user_id = u.id
                WHERE cor.id = ?
            ");
            $stmt->execute([$custom_order_id]);
            $customOrder = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customOrder) return false;
            
            // Check if user allows this notification
            $notificationService = $this->getNotificationService();
            if (!$notificationService->canSendNotification($customOrder['user_id'], 'custom_order_status_update')) {
                return true; // User opted out, not an error
            }
            
            $statusText = $this->getStatusDisplayName($new_status);
            $subject = "Custom Order Status Update - {$statusText} - Bitversity (#{$custom_order_id})";
            $body = $this->generateCustomOrderStatusUpdateEmail($customOrder, $old_status, $new_status, $reason);
            
            // Add unsubscribe link
            $body = $notificationService->addUnsubscribeLink($body, $customOrder['user_id'], 'custom_order_status_update');
            
            return $this->sendEmail($customOrder['email'], $subject, $body);
            
        } catch (Exception $e) {
            error_log("Custom order status update email error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendCustomOrderApproval($custom_order_id) {
        try {
            // Get custom order details with user info
            $stmt = $this->db->prepare("
                SELECT cor.*, u.first_name, u.last_name, u.email, u.full_name
                FROM custom_order_requests cor
                JOIN users u ON cor.user_id = u.id
                WHERE cor.id = ?
            ");
            $stmt->execute([$custom_order_id]);
            $customOrder = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customOrder) return false;
            
            // Check if user allows this notification
            $notificationService = $this->getNotificationService();
            if (!$notificationService->canSendNotification($customOrder['user_id'], 'custom_order_approval')) {
                return true; // User opted out, not an error
            }
            
            $subject = "Great News! Your Custom Order Request has been Approved - Bitversity (#{$custom_order_id})";
            $body = $this->generateCustomOrderApprovalEmail($customOrder);
            
            // Add unsubscribe link
            $body = $notificationService->addUnsubscribeLink($body, $customOrder['user_id'], 'custom_order_approval');
            
            return $this->sendEmail($customOrder['email'], $subject, $body);
            
        } catch (Exception $e) {
            error_log("Custom order approval email error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendCustomOrderRejection($custom_order_id, $rejection_reason) {
        try {
            // Get custom order details with user info
            $stmt = $this->db->prepare("
                SELECT cor.*, u.first_name, u.last_name, u.email, u.full_name
                FROM custom_order_requests cor
                JOIN users u ON cor.user_id = u.id
                WHERE cor.id = ?
            ");
            $stmt->execute([$custom_order_id]);
            $customOrder = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customOrder) return false;
            
            // Check if user allows this notification
            $notificationService = $this->getNotificationService();
            if (!$notificationService->canSendNotification($customOrder['user_id'], 'custom_order_rejection')) {
                return true; // User opted out, not an error
            }
            
            $subject = "Update on Your Custom Order Request - Bitversity (#{$custom_order_id})";
            $body = $this->generateCustomOrderRejectionEmail($customOrder, $rejection_reason);
            
            // Add unsubscribe link
            $body = $notificationService->addUnsubscribeLink($body, $customOrder['user_id'], 'custom_order_rejection');
            
            return $this->sendEmail($customOrder['email'], $subject, $body);
            
        } catch (Exception $e) {
            error_log("Custom order rejection email error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendAdminNewCustomOrderNotification($custom_order_id) {
        try {
            // Get custom order details with user info
            $stmt = $this->db->prepare("
                SELECT cor.*, u.first_name, u.last_name, u.email, u.full_name
                FROM custom_order_requests cor
                JOIN users u ON cor.user_id = u.id
                WHERE cor.id = ?
            ");
            $stmt->execute([$custom_order_id]);
            $customOrder = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customOrder) return false;
            
            // Get admin email from environment or config
            $admin_email = $_ENV['ADMIN_EMAIL'] ?? 'admin@bitversity.com';
            
            $subject = "New Custom Order Request Received - Action Required (#{$custom_order_id})";
            $body = $this->generateAdminCustomOrderNotificationEmail($customOrder);
            
            return $this->sendEmail($admin_email, $subject, $body);
            
        } catch (Exception $e) {
            error_log("Admin custom order notification email error: " . $e->getMessage());
            return false;
        }
    }
    
    private function getStatusDisplayName($status) {
        $statusMap = [
            'pending' => 'Pending Review',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Not Approved',
            'payment_pending' => 'Payment Required',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled'
        ];
        
        return $statusMap[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
    
    private function sendEmail($to, $subject, $body) {
        $headers = [
            'MIME-Version' => '1.0',
            'Content-type' => 'text/html; charset=UTF-8',
            'From' => $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To' => $this->from_email,
            'X-Mailer' => 'PHP/' . phpversion()
        ];
        
        $header_string = '';
        foreach ($headers as $key => $value) {
            $header_string .= $key . ': ' . $value . "\r\n";
        }
        
        // For development, log emails instead of sending
        if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
            $this->logEmail($to, $subject, $body);
            return true;
        }
        
        return mail($to, $subject, $body, $header_string);
    }
    
    private function logEmail($to, $subject, $body) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'to' => $to,
            'subject' => $subject,
            'body' => $body
        ];
        
        $log_file = __DIR__ . '/../logs/emails.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        
        file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    private function generateOrderConfirmationEmail($order, $items) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Order Confirmation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px 20px; }
                .order-details { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .item { padding: 10px 0; border-bottom: 1px solid #eee; }
                .item:last-child { border-bottom: none; }
                .total { font-weight: bold; font-size: 1.2em; color: #007bff; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 0.9em; color: #666; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Order Confirmed!</h1>
                    <p>Thank you for your purchase</p>
                </div>
                
                <div class="content">
                    <h2>Hello <?= htmlspecialchars($order['first_name']) ?>!</h2>
                    <p>Your order has been successfully processed and is ready for access.</p>
                    
                    <div class="order-details">
                        <h3>Order Details</h3>
                        <p><strong>Order Number:</strong> #<?= $order['id'] ?></p>
                        <p><strong>Order Date:</strong> <?= date('F j, Y', strtotime($order['created_at'])) ?></p>
                        <p><strong>Total:</strong> <span class="total">$<?= number_format($order['total'], 2) ?></span></p>
                    </div>
                    
                    <h3>Items Purchased:</h3>
                    <?php foreach ($items as $item): ?>
                        <div class="item">
                            <strong><?= htmlspecialchars($item['title']) ?></strong><br>
                            <small><?= ucfirst($item['item_type']) ?></small>
                            <span style="float: right;">$<?= number_format($item['price'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                    
                    <p style="text-align: center; margin: 30px 0;">
                        <a href="<?= SITE_URL ?>/dashboard.php" class="btn">Access Your Content</a>
                    </p>
                    
                    <p>All your purchased content is now available in your dashboard with lifetime access!</p>
                </div>
                
                <div class="footer">
                    <p>Questions? Contact us at support@bitversity.com</p>
                    <p>&copy; <?= date('Y') ?> Bitversity. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private function generateWelcomeEmail($user_name) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Welcome to Bitversity</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 30px 20px; text-align: center; }
                .content { padding: 30px 20px; }
                .features { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .feature { margin: 15px 0; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 0.9em; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Welcome to Bitversity!</h1>
                    <p>Your learning journey starts here</p>
                </div>
                
                <div class="content">
                    <h2>Hello <?= htmlspecialchars($user_name) ?>!</h2>
                    <p>Welcome to Bitversity, your premier destination for digital learning content. We're excited to have you join our community!</p>
                    
                    <div class="features">
                        <h3>What you can do on Bitversity:</h3>
                        <div class="feature">📚 <strong>Books:</strong> Comprehensive guides and tutorials</div>
                        <div class="feature">💻 <strong>Projects:</strong> Complete source code with documentation</div>
                        <div class="feature">🎮 <strong>Games:</strong> Interactive learning experiences</div>
                        <div class="feature">⭐ <strong>Reviews:</strong> Community-driven content ratings</div>
                        <div class="feature">💝 <strong>Wishlist:</strong> Save content for later</div>
                    </div>
                    
                    <p style="text-align: center; margin: 30px 0;">
                        <a href="<?= SITE_URL ?>" class="btn">Start Exploring</a>
                    </p>
                    
                    <p>Ready to enhance your skills? Browse our extensive library of books, projects, and interactive games designed to accelerate your learning!</p>
                </div>
                
                <div class="footer">
                    <p>Questions? We're here to help at support@bitversity.com</p>
                    <p>&copy; <?= date('Y') ?> Bitversity. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private function generateCustomOrderSubmissionEmail($customOrder) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Custom Order Request Received</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 30px 20px; text-align: center; }
                .content { padding: 30px 20px; }
                .order-details { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .detail-row { padding: 10px 0; border-bottom: 1px solid #eee; }
                .detail-row:last-child { border-bottom: none; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 0.9em; color: #666; }
                .timeline { background: #e8f5e8; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Custom Order Request Received!</h1>
                    <p>We've received your custom order request and will review it soon</p>
                </div>
                
                <div class="content">
                    <h2>Hello <?= htmlspecialchars($customOrder['first_name'] ?: $customOrder['full_name']) ?>!</h2>
                    <p>Thank you for submitting your custom order request. We've received all the details and our team will review your request within the next 24-48 hours.</p>
                    
                    <div class="order-details">
                        <h3>Your Request Details</h3>
                        <div class="detail-row">
                            <strong>Request ID:</strong> #<?= $customOrder['id'] ?>
                        </div>
                        <div class="detail-row">
                            <strong>Type:</strong> <?= ucfirst($customOrder['order_type']) ?>
                        </div>
                        <div class="detail-row">
                            <strong>Title:</strong> <?= htmlspecialchars($customOrder['title']) ?>
                        </div>
                        <div class="detail-row">
                            <strong>Budget Range:</strong> <?= $this->formatBudgetRange($customOrder['budget_range']) ?>
                        </div>
                        <?php if ($customOrder['timeline_needed']): ?>
                        <div class="detail-row">
                            <strong>Timeline Needed:</strong> <?= date('F j, Y', strtotime($customOrder['timeline_needed'])) ?>
                        </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <strong>Priority:</strong> <?= ucfirst($customOrder['priority']) ?>
                        </div>
                        <div class="detail-row">
                            <strong>Submitted:</strong> <?= date('F j, Y \\a\\t g:i A', strtotime($customOrder['created_at'])) ?>
                        </div>
                    </div>
                    
                    <div class="timeline">
                        <h4>What happens next?</h4>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>Our team will review your requirements</li>
                            <li>We'll provide a custom quote and timeline</li>
                            <li>Once approved, we'll begin development</li>
                            <li>You'll receive regular progress updates</li>
                        </ul>
                    </div>
                    
                    <p style="text-align: center; margin: 30px 0;">
                        <a href="<?= APP_URL ?>/dashboard.php?section=custom-orders" class="btn">View Your Request</a>
                    </p>
                    
                    <p>If you have any questions or need to provide additional information, please don't hesitate to contact us.</p>
                </div>
                
                <div class="footer">
                    <p>Questions? Contact us at support@bitversity.com</p>
                    <p>&copy; <?= date('Y') ?> Bitversity. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private function generateCustomOrderStatusUpdateEmail($customOrder, $oldStatus, $newStatus, $reason) {
        ob_start();
        $statusName = $this->getStatusDisplayName($newStatus);
        $oldStatusName = $this->getStatusDisplayName($oldStatus);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Custom Order Status Update</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 30px 20px; text-align: center; }
                .content { padding: 30px 20px; }
                .order-details { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .status-update { background: #e3f2fd; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #2196f3; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 0.9em; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Order Status Update</h1>
                    <p>Your custom order status has been updated</p>
                </div>
                
                <div class="content">
                    <h2>Hello <?= htmlspecialchars($customOrder['first_name'] ?: $customOrder['full_name']) ?>!</h2>
                    <p>There's been an update to your custom order request:</p>
                    
                    <div class="status-update">
                        <h3>Status Changed</h3>
                        <p><strong>From:</strong> <?= $oldStatusName ?> <strong>To:</strong> <?= $statusName ?></p>
                        <?php if ($reason): ?>
                        <p><strong>Note:</strong> <?= htmlspecialchars($reason) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-details">
                        <h3>Request Details</h3>
                        <p><strong>Request ID:</strong> #<?= $customOrder['id'] ?></p>
                        <p><strong>Title:</strong> <?= htmlspecialchars($customOrder['title']) ?></p>
                        <p><strong>Type:</strong> <?= ucfirst($customOrder['order_type']) ?></p>
                        <p><strong>Current Status:</strong> <?= $statusName ?></p>
                        <?php if ($customOrder['custom_price']): ?>
                        <p><strong>Quote:</strong> $<?= number_format($customOrder['custom_price'], 2) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($customOrder['admin_notes']): ?>
                    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">
                        <h4>Admin Notes:</h4>
                        <p><?= nl2br(htmlspecialchars($customOrder['admin_notes'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <p style="text-align: center; margin: 30px 0;">
                        <a href="<?= APP_URL ?>/dashboard.php?section=custom-orders&id=<?= $customOrder['id'] ?>" class="btn">View Full Details</a>
                    </p>
                </div>
                
                <div class="footer">
                    <p>Questions? Contact us at support@bitversity.com</p>
                    <p>&copy; <?= date('Y') ?> Bitversity. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private function generateCustomOrderApprovalEmail($customOrder) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Custom Order Approved</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 30px 20px; text-align: center; }
                .content { padding: 30px 20px; }
                .order-details { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .approval-box { background: #d4edda; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745; }
                .btn { display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .btn-secondary { background: #007bff; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 0.9em; color: #666; }
                .price-highlight { font-size: 1.3em; color: #28a745; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎉 Request Approved!</h1>
                    <p>Your custom order request has been approved and is ready to begin</p>
                </div>
                
                <div class="content">
                    <h2>Congratulations <?= htmlspecialchars($customOrder['first_name'] ?: $customOrder['full_name']) ?>!</h2>
                    <p>Great news! We've reviewed your custom order request and we're excited to move forward with your project.</p>
                    
                    <div class="approval-box">
                        <h3>✅ Your Request Has Been Approved</h3>
                        <p>We believe this project aligns perfectly with our capabilities and we're confident we can deliver exactly what you're looking for.</p>
                    </div>
                    
                    <div class="order-details">
                        <h3>Project Details</h3>
                        <p><strong>Request ID:</strong> #<?= $customOrder['id'] ?></p>
                        <p><strong>Title:</strong> <?= htmlspecialchars($customOrder['title']) ?></p>
                        <p><strong>Type:</strong> <?= ucfirst($customOrder['order_type']) ?></p>
                        <?php if ($customOrder['custom_price']): ?>
                        <p><strong>Project Cost:</strong> <span class="price-highlight">$<?= number_format($customOrder['custom_price'], 2) ?></span></p>
                        <?php endif; ?>
                        <?php if ($customOrder['estimated_completion_date']): ?>
                        <p><strong>Estimated Completion:</strong> <?= date('F j, Y', strtotime($customOrder['estimated_completion_date'])) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($customOrder['admin_notes']): ?>
                    <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #2196f3;">
                        <h4>Project Notes:</h4>
                        <p><?= nl2br(htmlspecialchars($customOrder['admin_notes'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">
                        <h4>Next Steps:</h4>
                        <ol style="margin: 10px 0; padding-left: 20px;">
                            <li>Review the project details and pricing</li>
                            <li>Complete the payment process to secure your slot</li>
                            <li>We'll begin development immediately after payment</li>
                            <li>Regular updates will be provided throughout the process</li>
                        </ol>
                    </div>
                    
                    <p style="text-align: center; margin: 30px 0;">
                        <?php if ($customOrder['custom_price']): ?>
                        <a href="<?= APP_URL ?>/checkout.php?custom_order=<?= $customOrder['id'] ?>" class="btn">Proceed to Payment</a>
                        <?php endif; ?>
                        <a href="<?= APP_URL ?>/dashboard.php?section=custom-orders&id=<?= $customOrder['id'] ?>" class="btn btn-secondary">View Details</a>
                    </p>
                    
                    <p>Thank you for choosing Bitversity for your custom development needs. We're looking forward to working with you!</p>
                </div>
                
                <div class="footer">
                    <p>Questions? Contact us at support@bitversity.com</p>
                    <p>&copy; <?= date('Y') ?> Bitversity. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private function generateCustomOrderRejectionEmail($customOrder, $rejectionReason) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Custom Order Request Update</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 30px 20px; text-align: center; }
                .content { padding: 30px 20px; }
                .order-details { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .rejection-box { background: #f8d7da; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545; }
                .alternative-box { background: #d1ecf1; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #17a2b8; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 0.9em; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Custom Order Request Update</h1>
                    <p>We've reviewed your custom order request</p>
                </div>
                
                <div class="content">
                    <h2>Hello <?= htmlspecialchars($customOrder['first_name'] ?: $customOrder['full_name']) ?>,</h2>
                    <p>Thank you for your interest in our custom development services. After careful review of your request, we need to provide you with an update.</p>
                    
                    <div class="rejection-box">
                        <h3>Request Status Update</h3>
                        <p>Unfortunately, we're not able to proceed with your request as currently specified.</p>
                        <?php if ($rejectionReason): ?>
                        <p><strong>Reason:</strong> <?= htmlspecialchars($rejectionReason) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-details">
                        <h3>Your Original Request</h3>
                        <p><strong>Request ID:</strong> #<?= $customOrder['id'] ?></p>
                        <p><strong>Title:</strong> <?= htmlspecialchars($customOrder['title']) ?></p>
                        <p><strong>Type:</strong> <?= ucfirst($customOrder['order_type']) ?></p>
                        <p><strong>Budget Range:</strong> <?= $this->formatBudgetRange($customOrder['budget_range']) ?></p>
                    </div>
                    
                    <div class="alternative-box">
                        <h3>Don't Give Up!</h3>
                        <p>While we can't proceed with this specific request, we'd love to help you find an alternative solution:</p>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>Consider modifying the scope or requirements</li>
                            <li>Browse our existing projects and games library</li>
                            <li>Contact us to discuss alternative approaches</li>
                            <li>Submit a new request with updated specifications</li>
                        </ul>
                    </div>
                    
                    <p style="text-align: center; margin: 30px 0;">
                        <a href="<?= APP_URL ?>/projects.php" class="btn">Browse Our Projects</a>
                        <a href="<?= APP_URL ?>/games.php" class="btn">Browse Our Games</a>
                    </p>
                    
                    <p>We appreciate your understanding and hope to work with you in the future. If you have any questions or would like to discuss alternatives, please don't hesitate to reach out.</p>
                </div>
                
                <div class="footer">
                    <p>Questions? Contact us at support@bitversity.com</p>
                    <p>&copy; <?= date('Y') ?> Bitversity. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private function generateAdminCustomOrderNotificationEmail($customOrder) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>New Custom Order Request</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #6f42c1; color: white; padding: 30px 20px; text-align: center; }
                .content { padding: 30px 20px; }
                .order-details { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .detail-row { padding: 8px 0; border-bottom: 1px solid #eee; }
                .detail-row:last-child { border-bottom: none; }
                .urgent-box { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107; }
                .btn { display: inline-block; background: #6f42c1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 0.9em; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔔 New Custom Order Request</h1>
                    <p>A new custom order request requires your attention</p>
                </div>
                
                <div class="content">
                    <h2>Action Required</h2>
                    <p>A new custom order request has been submitted and is waiting for your review.</p>
                    
                    <?php if ($customOrder['priority'] === 'high'): ?>
                    <div class="urgent-box">
                        <h3>⚡ High Priority Request</h3>
                        <p>This request has been marked as high priority by the customer.</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="order-details">
                        <h3>Request Details</h3>
                        <div class="detail-row">
                            <strong>Request ID:</strong> #<?= $customOrder['id'] ?>
                        </div>
                        <div class="detail-row">
                            <strong>Customer:</strong> <?= htmlspecialchars($customOrder['full_name']) ?> (<?= htmlspecialchars($customOrder['email']) ?>)
                        </div>
                        <div class="detail-row">
                            <strong>Type:</strong> <?= ucfirst($customOrder['order_type']) ?>
                        </div>
                        <div class="detail-row">
                            <strong>Title:</strong> <?= htmlspecialchars($customOrder['title']) ?>
                        </div>
                        <div class="detail-row">
                            <strong>Budget Range:</strong> <?= $this->formatBudgetRange($customOrder['budget_range']) ?>
                        </div>
                        <div class="detail-row">
                            <strong>Priority:</strong> <?= ucfirst($customOrder['priority']) ?>
                        </div>
                        <?php if ($customOrder['timeline_needed']): ?>
                        <div class="detail-row">
                            <strong>Timeline Needed:</strong> <?= date('F j, Y', strtotime($customOrder['timeline_needed'])) ?>
                        </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <strong>Submitted:</strong> <?= date('F j, Y \\a\\t g:i A', strtotime($customOrder['created_at'])) ?>
                        </div>
                    </div>
                    
                    <div class="order-details">
                        <h3>Description</h3>
                        <p><?= nl2br(htmlspecialchars($customOrder['description'])) ?></p>
                        
                        <h4>Requirements</h4>
                        <p><?= nl2br(htmlspecialchars($customOrder['requirements'])) ?></p>
                    </div>
                    
                    <p style="text-align: center; margin: 30px 0;">
                        <a href="<?= APP_URL ?>/admin/custom-orders.php?id=<?= $customOrder['id'] ?>" class="btn">Review Request</a>
                    </p>
                    
                    <p><strong>Please review this request and take appropriate action (approve/reject/request more information) within 48 hours.</strong></p>
                </div>
                
                <div class="footer">
                    <p>Bitversity Admin Panel</p>
                    <p>&copy; <?= date('Y') ?> Bitversity. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private function formatBudgetRange($budgetRange) {
        $budgetMap = [
            'under_500' => 'Under $500',
            '500_1000' => '$500 - $1,000',
            '1000_2500' => '$1,000 - $2,500',
            '2500_5000' => '$2,500 - $5,000',
            '5000_10000' => '$5,000 - $10,000',
            'over_10000' => 'Over $10,000'
        ];
        
        return $budgetMap[$budgetRange] ?? ucfirst(str_replace('_', ' ', $budgetRange));
    }
}
?>