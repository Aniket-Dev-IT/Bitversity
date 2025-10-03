-- Create custom_order_requests table for the Bitversity platform
-- This table stores custom development project requests from users

CREATE TABLE IF NOT EXISTS `custom_order_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `project_type` enum('web_app','mobile_app','educational_game','custom_tool','other') NOT NULL DEFAULT 'other',
    `title` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `requirements` text,
    `budget_range` varchar(50),
    `timeline` varchar(50),
    `status` enum('pending','under_review','approved','in_progress','completed','cancelled','rejected') NOT NULL DEFAULT 'pending',
    `custom_price` decimal(10,2) DEFAULT NULL,
    `admin_notes` text,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_project_type` (`project_type`),
    CONSTRAINT `fk_custom_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample data for testing (optional)
-- Note: Replace user_id values with actual user IDs from your system
INSERT IGNORE INTO `custom_order_requests` (`id`, `user_id`, `project_type`, `title`, `description`, `requirements`, `budget_range`, `timeline`, `status`, `custom_price`, `created_at`) VALUES
(1, 1, 'web_app', 'Learning Management System', 'Need a custom LMS for our university with student tracking, course management, and grading features.', 'PHP/MySQL, Bootstrap, responsive design, admin panel, student portal, assignment submission, grading system', '$2000-$5000', '4-6 weeks', 'completed', 3500.00, '2024-01-15 10:30:00'),
(2, 1, 'educational_game', 'Math Learning Game', 'Interactive math game for elementary students with levels, progress tracking, and rewards system.', 'HTML5/JavaScript, mobile-friendly, 50+ levels, progress saving, teacher dashboard', '$800-$1500', '2-3 weeks', 'completed', 1200.00, '2024-02-10 14:20:00'),
(3, 2, 'mobile_app', 'Study Tracker App', 'Cross-platform mobile app for tracking study sessions, setting goals, and scheduling.', 'React Native, iOS/Android, push notifications, calendar integration, progress analytics', '$1500-$3000', '3-5 weeks', 'completed', 2200.00, '2024-03-05 09:15:00');