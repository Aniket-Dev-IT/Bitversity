-- Advanced Admin Tools Enhancement Migration
-- This migration adds tables and features for sophisticated admin management capabilities

-- Admin Message Templates for quick responses
CREATE TABLE IF NOT EXISTS admin_message_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    content TEXT NOT NULL,
    category ENUM('approval', 'rejection', 'follow_up', 'general', 'payment', 'completion') DEFAULT 'general',
    variables JSON DEFAULT NULL, -- Stores template variables like {{customer_name}}, {{order_id}}
    is_global BOOLEAN DEFAULT FALSE, -- Global templates can be used by all admins
    usage_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_admin_templates (admin_id, is_active),
    INDEX idx_template_category (category),
    INDEX idx_global_templates (is_global, is_active)
);

-- Admin Filter Presets for saving commonly used filters
CREATE TABLE IF NOT EXISTS admin_filter_presets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    filter_data JSON NOT NULL, -- Stores the complete filter configuration
    is_global BOOLEAN DEFAULT FALSE,
    usage_count INT DEFAULT 0,
    is_favorite BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_admin_preset (admin_id, name),
    INDEX idx_admin_presets (admin_id, is_favorite),
    INDEX idx_global_presets (is_global)
);

-- Admin Notifications System
CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    type ENUM('order_assigned', 'order_updated', 'urgent_order', 'overdue_order', 'system_alert', 'mention', 'task_reminder') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    metadata JSON DEFAULT NULL, -- Additional context data
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    is_read BOOLEAN DEFAULT FALSE,
    is_archived BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(500), -- URL for clickable notifications
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_notifications (admin_id, is_read, created_at DESC),
    INDEX idx_notification_type (type, priority),
    INDEX idx_unread_notifications (admin_id, is_read, expires_at)
);

-- Admin Internal Notes for collaboration
CREATE TABLE IF NOT EXISTS admin_internal_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    admin_id INT NOT NULL,
    note TEXT NOT NULL,
    note_type ENUM('general', 'technical', 'priority', 'warning', 'follow_up') DEFAULT 'general',
    is_private BOOLEAN DEFAULT FALSE, -- Private notes only visible to the author
    mentioned_admins JSON DEFAULT NULL, -- Array of admin IDs mentioned in the note
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES custom_order_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_order_notes (order_id, created_at DESC),
    INDEX idx_admin_notes (admin_id, created_at DESC),
    INDEX idx_note_type (note_type, priority)
);

-- Admin Task Management
CREATE TABLE IF NOT EXISTS admin_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    assigned_by INT,
    order_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    task_type ENUM('follow_up', 'review', 'contact_customer', 'technical_check', 'pricing', 'documentation', 'other') DEFAULT 'other',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    due_date TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    estimated_hours DECIMAL(4,2) DEFAULT NULL,
    actual_hours DECIMAL(4,2) DEFAULT NULL,
    tags JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES custom_order_requests(id) ON DELETE SET NULL,
    INDEX idx_admin_tasks (admin_id, status, due_date),
    INDEX idx_task_priority (priority, status),
    INDEX idx_order_tasks (order_id, status)
);

-- Automated Workflow Rules
CREATE TABLE IF NOT EXISTS admin_workflow_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    trigger_event ENUM('order_created', 'status_changed', 'priority_changed', 'overdue', 'assignment_changed', 'time_based') NOT NULL,
    conditions JSON NOT NULL, -- Complex conditions for rule execution
    actions JSON NOT NULL, -- Actions to perform when rule triggers
    is_active BOOLEAN DEFAULT TRUE,
    execution_count INT DEFAULT 0,
    last_executed_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_workflow_rules (trigger_event, is_active),
    INDEX idx_active_rules (is_active, trigger_event)
);

-- Workflow Rule Execution Log
CREATE TABLE IF NOT EXISTS admin_workflow_executions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rule_id INT NOT NULL,
    order_id INT,
    trigger_data JSON,
    executed_actions JSON,
    execution_status ENUM('success', 'failed', 'partial') NOT NULL,
    execution_time_ms INT DEFAULT NULL,
    error_message TEXT,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rule_id) REFERENCES admin_workflow_rules(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES custom_order_requests(id) ON DELETE SET NULL,
    INDEX idx_rule_executions (rule_id, executed_at DESC),
    INDEX idx_execution_status (execution_status, executed_at DESC)
);

-- Admin Performance Metrics
CREATE TABLE IF NOT EXISTS admin_performance_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    metric_date DATE NOT NULL,
    orders_handled INT DEFAULT 0,
    orders_approved INT DEFAULT 0,
    orders_rejected INT DEFAULT 0,
    orders_completed INT DEFAULT 0,
    avg_response_time_hours DECIMAL(6,2) DEFAULT NULL,
    total_revenue_generated DECIMAL(10,2) DEFAULT 0,
    customer_satisfaction_score DECIMAL(3,2) DEFAULT NULL, -- 0-5 scale
    notes_created INT DEFAULT 0,
    tasks_completed INT DEFAULT 0,
    templates_used INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_admin_date (admin_id, metric_date),
    INDEX idx_performance_date (metric_date DESC),
    INDEX idx_admin_performance (admin_id, metric_date DESC)
);

-- Quick Action Shortcuts for admins
CREATE TABLE IF NOT EXISTS admin_quick_actions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    action_type ENUM('status_change', 'template_send', 'task_create', 'note_add', 'assign', 'priority_change') NOT NULL,
    action_config JSON NOT NULL, -- Configuration for the quick action
    icon VARCHAR(50), -- FontAwesome icon class
    color VARCHAR(7), -- Hex color code
    sort_order INT DEFAULT 0,
    usage_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_quick_actions (admin_id, is_active, sort_order),
    INDEX idx_action_usage (usage_count DESC)
);

-- Admin Collaboration Mentions
CREATE TABLE IF NOT EXISTS admin_mentions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mentioned_admin_id INT NOT NULL,
    mentioning_admin_id INT NOT NULL,
    context_type ENUM('note', 'task', 'message') NOT NULL,
    context_id INT NOT NULL, -- ID of the note, task, or message
    order_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (mentioned_admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mentioning_admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES custom_order_requests(id) ON DELETE CASCADE,
    INDEX idx_mentioned_admin (mentioned_admin_id, is_read, created_at DESC),
    INDEX idx_mentions_context (context_type, context_id)
);

-- Enhanced Activity Log with better categorization
CREATE TABLE IF NOT EXISTS admin_activity_enhanced (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    order_id INT,
    activity_type ENUM('status_change', 'assignment', 'note_added', 'template_used', 'task_created', 'rule_executed', 'bulk_action', 'other') NOT NULL,
    activity_category ENUM('order_management', 'communication', 'automation', 'collaboration', 'system') DEFAULT 'order_management',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    metadata JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES custom_order_requests(id) ON DELETE SET NULL,
    INDEX idx_admin_activity (admin_id, created_at DESC),
    INDEX idx_activity_type (activity_type, created_at DESC),
    INDEX idx_order_activity (order_id, created_at DESC)
);

-- Admin Settings and Preferences
CREATE TABLE IF NOT EXISTS admin_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    preference_key VARCHAR(255) NOT NULL,
    preference_value JSON,
    category ENUM('notifications', 'dashboard', 'workflow', 'display', 'automation') DEFAULT 'dashboard',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_admin_preference (admin_id, preference_key),
    INDEX idx_admin_preferences (admin_id, category)
);

-- Insert default message templates
INSERT INTO admin_message_templates (admin_id, name, subject, content, category, variables, is_global, created_by) VALUES
(1, 'Order Approved', 'Your custom order has been approved!', 'Hi {{customer_name}},\n\nGreat news! Your custom {{order_type}} request "{{order_title}}" has been approved.\n\n{{#if custom_price}}Your project quote is: ${{custom_price}}{{/if}}\n\nWe will begin work shortly and keep you updated on the progress.\n\nBest regards,\nThe Bitversity Team', 'approval', '["customer_name", "order_type", "order_title", "custom_price"]', TRUE, 1),
(1, 'Order Rejected', 'Update on your custom order request', 'Hi {{customer_name}},\n\nThank you for your interest in our custom {{order_type}} services.\n\nAfter careful review, we are unable to proceed with your request "{{order_title}}" at this time.\n\n{{rejection_reason}}\n\nWe encourage you to submit a revised request or contact us to discuss alternative solutions.\n\nBest regards,\nThe Bitversity Team', 'rejection', '["customer_name", "order_type", "order_title", "rejection_reason"]', TRUE, 1),
(1, 'Follow-up Required', 'We need more information about your project', 'Hi {{customer_name}},\n\nWe are reviewing your custom {{order_type}} request "{{order_title}}" and need some additional information to provide you with an accurate quote.\n\nCould you please provide:\n{{additional_info_needed}}\n\nOnce we have this information, we can proceed with your request promptly.\n\nBest regards,\nThe Bitversity Team', 'follow_up', '["customer_name", "order_type", "order_title", "additional_info_needed"]', TRUE, 1),
(1, 'Project Completed', 'Your custom project is ready!', 'Hi {{customer_name}},\n\nExcellent news! Your custom {{order_type}} "{{order_title}}" has been completed.\n\n{{#if delivery_details}}{{delivery_details}}{{/if}}\n\nPlease review the delivered work and let us know if you have any questions or need any adjustments.\n\nThank you for choosing Bitversity!\n\nBest regards,\nThe Bitversity Team', 'completion', '["customer_name", "order_type", "order_title", "delivery_details"]', TRUE, 1);

-- Insert default workflow rules
INSERT INTO admin_workflow_rules (name, description, trigger_event, conditions, actions, created_by) VALUES
('Auto-assign urgent orders', 'Automatically assign urgent orders to available admins', 'order_created', '{"priority": "urgent"}', '{"assign_to_least_loaded": true, "send_notification": true, "add_task": {"title": "Review urgent order", "due_hours": 2}}', 1),
('Overdue order alerts', 'Send alerts for overdue orders', 'time_based', '{"overdue_hours": 24, "status": ["pending", "under_review"]}', '{"send_notification": {"type": "urgent_order", "title": "Order Overdue"}, "escalate_priority": "urgent"}', 1),
('Welcome new orders', 'Send welcome message to new order customers', 'order_created', '{}', '{"send_template": {"template_id": "welcome_message"}, "add_task": {"title": "Initial review", "due_hours": 4}}', 1);

-- Insert default quick actions
INSERT INTO admin_quick_actions (admin_id, name, description, action_type, action_config, icon, color, sort_order) VALUES
(1, 'Quick Approve', 'Approve order and send template message', 'status_change', '{"new_status": "approved", "send_template": true, "template_category": "approval"}', 'fa-check', '#28a745', 1),
(1, 'Request Info', 'Change status and send follow-up template', 'template_send', '{"template_category": "follow_up", "change_status": "under_review"}', 'fa-question-circle', '#17a2b8', 2),
(1, 'Reject Order', 'Reject order with reason template', 'status_change', '{"new_status": "rejected", "send_template": true, "template_category": "rejection"}', 'fa-times', '#dc3545', 3),
(1, 'Assign to Me', 'Assign current order to myself', 'assign', '{"assign_to": "self", "add_task": true}', 'fa-user', '#6c757d', 4);

-- Update existing tables to support new features
-- Add tags field to custom_order_requests if not exists
ALTER TABLE custom_order_requests 
ADD COLUMN IF NOT EXISTS tags JSON DEFAULT NULL,
ADD COLUMN IF NOT EXISTS internal_priority_score INT DEFAULT 50,
ADD COLUMN IF NOT EXISTS complexity_score ENUM('simple', 'medium', 'complex', 'very_complex') DEFAULT 'medium',
ADD COLUMN IF NOT EXISTS estimated_hours DECIMAL(6,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS actual_hours DECIMAL(6,2) DEFAULT NULL;

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_orders_priority_score ON custom_order_requests(internal_priority_score DESC);
CREATE INDEX IF NOT EXISTS idx_orders_complexity ON custom_order_requests(complexity_score);
CREATE INDEX IF NOT EXISTS idx_orders_tags ON custom_order_requests((CAST(tags AS CHAR(255))));

-- Add notification preferences to admin_preferences
INSERT IGNORE INTO admin_preferences (admin_id, preference_key, preference_value, category)
SELECT id, 'email_notifications', '{"new_orders": true, "status_changes": true, "mentions": true, "overdue_alerts": true}', 'notifications'
FROM users WHERE user_type = 'admin';

INSERT IGNORE INTO admin_preferences (admin_id, preference_key, preference_value, category)
SELECT id, 'dashboard_layout', '{"view_mode": "kanban", "items_per_page": 15, "show_completed": false}', 'dashboard'
FROM users WHERE user_type = 'admin';

-- Create triggers for automatic metrics collection
DELIMITER //

CREATE TRIGGER IF NOT EXISTS update_admin_metrics_on_order_change
AFTER UPDATE ON custom_order_requests
FOR EACH ROW
BEGIN
    IF NEW.assigned_to IS NOT NULL AND (OLD.status != NEW.status OR OLD.assigned_to != NEW.assigned_to) THEN
        INSERT INTO admin_performance_metrics (admin_id, metric_date, orders_handled)
        VALUES (NEW.assigned_to, CURDATE(), 1)
        ON DUPLICATE KEY UPDATE 
        orders_handled = orders_handled + 1,
        orders_approved = orders_approved + (CASE WHEN NEW.status = 'approved' THEN 1 ELSE 0 END),
        orders_rejected = orders_rejected + (CASE WHEN NEW.status = 'rejected' THEN 1 ELSE 0 END),
        orders_completed = orders_completed + (CASE WHEN NEW.status = 'completed' THEN 1 ELSE 0 END),
        total_revenue_generated = total_revenue_generated + (CASE WHEN NEW.status = 'completed' AND NEW.custom_price > 0 THEN NEW.custom_price ELSE 0 END);
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS log_template_usage
AFTER INSERT ON custom_order_messages
FOR EACH ROW
BEGIN
    IF NEW.is_admin_message = 1 AND NEW.template_id IS NOT NULL THEN
        UPDATE admin_message_templates 
        SET usage_count = usage_count + 1, updated_at = NOW()
        WHERE id = NEW.template_id;
        
        INSERT INTO admin_performance_metrics (admin_id, metric_date, templates_used)
        VALUES (NEW.user_id, CURDATE(), 1)
        ON DUPLICATE KEY UPDATE templates_used = templates_used + 1;
    END IF;
END//

DELIMITER ;

-- Add template_id column to custom_order_messages to track template usage
ALTER TABLE custom_order_messages 
ADD COLUMN IF NOT EXISTS template_id INT DEFAULT NULL,
ADD FOREIGN KEY IF NOT EXISTS (template_id) REFERENCES admin_message_templates(id) ON DELETE SET NULL;

COMMIT;