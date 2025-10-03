-- =========================================
-- Custom Orders Database Schema
-- =========================================

-- Table for storing custom order requests
CREATE TABLE custom_order_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_type ENUM('project', 'game') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT NOT NULL,
    budget_range ENUM('under_500', '500_1000', '1000_2500', '2500_5000', '5000_10000', 'over_10000') DEFAULT 'under_500',
    timeline_needed DATE,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'payment_pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    admin_notes TEXT NULL,
    rejection_reason TEXT NULL,
    custom_price DECIMAL(10,2) NULL,
    estimated_completion_date DATE NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_order_type (order_type),
    INDEX idx_created_at (created_at)
);

-- Table for storing file attachments related to custom orders
CREATE TABLE custom_order_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custom_order_request_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size BIGINT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (custom_order_request_id) REFERENCES custom_order_requests(id) ON DELETE CASCADE,
    INDEX idx_custom_order (custom_order_request_id)
);

-- Table for tracking status change history
CREATE TABLE custom_order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custom_order_request_id INT NOT NULL,
    old_status ENUM('pending', 'under_review', 'approved', 'rejected', 'payment_pending', 'in_progress', 'completed', 'cancelled') NULL,
    new_status ENUM('pending', 'under_review', 'approved', 'rejected', 'payment_pending', 'in_progress', 'completed', 'cancelled') NOT NULL,
    changed_by INT NULL,
    reason TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (custom_order_request_id) REFERENCES custom_order_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_custom_order (custom_order_request_id),
    INDEX idx_created_at (created_at)
);

-- Table for storing communications/messages between admin and user
CREATE TABLE custom_order_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custom_order_request_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_admin_message BOOLEAN DEFAULT FALSE,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (custom_order_request_id) REFERENCES custom_order_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_custom_order (custom_order_request_id),
    INDEX idx_user (user_id),
    INDEX idx_created_at (created_at)
);

-- Table to link custom orders to actual orders when approved and paid
CREATE TABLE custom_order_to_order_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custom_order_request_id INT NOT NULL,
    order_id INT NOT NULL,
    linked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (custom_order_request_id) REFERENCES custom_order_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    UNIQUE KEY unique_custom_order_link (custom_order_request_id),
    INDEX idx_order (order_id)
);


SELECT 'Custom Orders database schema created successfully!' as message;
