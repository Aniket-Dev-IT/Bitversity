-- Migration: Create search_logs table for search analytics
-- Date: 2024-09-28
-- Description: Add table to track search queries for analytics and improvements

-- Create search_logs table
CREATE TABLE search_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    search_query VARCHAR(255) NOT NULL,
    search_type ENUM('search', 'autocomplete', 'suggestion') DEFAULT 'search',
    user_id INT NULL,
    results_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_search_query (search_query),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_search_type (search_type),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial sample data for testing
INSERT INTO search_logs (search_query, search_type, user_id, results_count, created_at) VALUES
('javascript', 'search', 1, 5, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('python', 'search', 2, 3, DATE_SUB(NOW(), INTERVAL 2 DAYS)),
('react', 'search', 1, 4, DATE_SUB(NOW(), INTERVAL 3 DAYS)),
('web development', 'search', NULL, 7, DATE_SUB(NOW(), INTERVAL 4 DAYS)),
('machine learning', 'search', 3, 2, DATE_SUB(NOW(), INTERVAL 5 DAYS)),
('javascript', 'autocomplete', 1, 0, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('python programming', 'search', 2, 6, DATE_SUB(NOW(), INTERVAL 6 DAYS)),
('css', 'search', NULL, 2, DATE_SUB(NOW(), INTERVAL 7 DAYS)),
('node.js', 'search', 1, 3, DATE_SUB(NOW(), INTERVAL 8 DAYS)),
('data science', 'search', 4, 4, DATE_SUB(NOW(), INTERVAL 9 DAYS));