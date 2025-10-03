-- =========================================
-- Bitversity Database Schema
-- =========================================

-- Create database (uncomment if needed)
-- CREATE DATABASE bitversity CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE bitversity;

-- =========================================
-- USER MANAGEMENT TABLES
-- =========================================

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255) NULL,
    email_verified_at TIMESTAMP NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    last_logout TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- Remember tokens for "Remember Me" functionality
CREATE TABLE remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_token (user_id),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- Password reset tokens
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- =========================================
-- CONTENT MANAGEMENT TABLES
-- =========================================

-- Categories for organizing content
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    color VARCHAR(7) DEFAULT '#6c757d',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (is_active),
    INDEX idx_sort (sort_order)
);

-- Books table
CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    author VARCHAR(255) NOT NULL,
    description TEXT,
    content LONGTEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    category_id INT,
    cover_image VARCHAR(255),
    file_size BIGINT DEFAULT 0,
    download_link VARCHAR(500),
    isbn VARCHAR(20),
    pages INT DEFAULT 0,
    language VARCHAR(10) DEFAULT 'en',
    level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    tags JSON,
    is_active BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    stock INT DEFAULT 0,
    views_count INT DEFAULT 0,
    downloads_count INT DEFAULT 0,
    rating DECIMAL(3, 2) DEFAULT 0.00,
    reviews_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_category (category_id),
    INDEX idx_active (is_active),
    INDEX idx_featured (is_featured),
    INDEX idx_price (price),
    INDEX idx_rating (rating),
    FULLTEXT idx_search (title, author, description)
);

-- Projects table
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    content LONGTEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    category_id INT,
    cover_image VARCHAR(255),
    file_size BIGINT DEFAULT 0,
    download_link VARCHAR(500),
    github_url VARCHAR(500),
    demo_url VARCHAR(500),
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    technologies JSON,
    requirements TEXT,
    duration_hours INT DEFAULT 0,
    language VARCHAR(10) DEFAULT 'en',
    tags JSON,
    is_active BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    views_count INT DEFAULT 0,
    downloads_count INT DEFAULT 0,
    rating DECIMAL(3, 2) DEFAULT 0.00,
    reviews_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_category (category_id),
    INDEX idx_active (is_active),
    INDEX idx_featured (is_featured),
    INDEX idx_price (price),
    INDEX idx_difficulty (difficulty),
    INDEX idx_rating (rating),
    FULLTEXT idx_search (title, description)
);

-- Games table
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    content LONGTEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    category_id INT,
    thumbnail VARCHAR(255),
    screenshots JSON,
    file_size BIGINT DEFAULT 0,
    download_link VARCHAR(500),
    play_url VARCHAR(500),
    game_type ENUM('educational', 'simulation', 'quiz', 'puzzle', 'other') DEFAULT 'educational',
    age_rating ENUM('all', '7+', '12+', '16+', '18+') DEFAULT 'all',
    platform ENUM('web', 'mobile', 'desktop', 'all') DEFAULT 'web',
    language VARCHAR(10) DEFAULT 'en',
    tags JSON,
    is_active BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    views_count INT DEFAULT 0,
    downloads_count INT DEFAULT 0,
    plays_count INT DEFAULT 0,
    rating DECIMAL(3, 2) DEFAULT 0.00,
    reviews_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_category (category_id),
    INDEX idx_active (is_active),
    INDEX idx_featured (is_featured),
    INDEX idx_price (price),
    INDEX idx_type (game_type),
    INDEX idx_rating (rating),
    FULLTEXT idx_search (title, description)
);

-- =========================================
-- E-COMMERCE TABLES
-- =========================================

-- User shopping cart
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_type ENUM('book', 'project', 'game') NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, item_type, item_id),
    INDEX idx_user (user_id),
    INDEX idx_item (item_type, item_id)
);

-- User wishlist
CREATE TABLE wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_type ENUM('book', 'project', 'game') NOT NULL,
    item_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, item_type, item_id),
    INDEX idx_user (user_id),
    INDEX idx_item (item_type, item_id)
);

-- Orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    tax_amount DECIMAL(10, 2) DEFAULT 0.00,
    shipping_amount DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('pending', 'processing', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_method ENUM('card', 'paypal', 'bank_transfer', 'other') DEFAULT 'card',
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_date TIMESTAMP NULL,
    billing_name VARCHAR(255) NOT NULL,
    billing_email VARCHAR(255) NOT NULL,
    billing_address VARCHAR(500) NOT NULL,
    billing_city VARCHAR(100) NOT NULL,
    billing_state VARCHAR(100),
    billing_zip VARCHAR(20) NOT NULL,
    billing_country VARCHAR(10) DEFAULT 'US',
    promo_code VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created (created_at)
);

-- Order items
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_type ENUM('book', 'project', 'game') NOT NULL,
    item_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_item (item_type, item_id)
);

-- =========================================
-- REVIEW AND RATING SYSTEM
-- =========================================

-- Reviews for all content types
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_type ENUM('book', 'project', 'game') NOT NULL,
    item_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255),
    comment TEXT,
    is_verified_purchase BOOLEAN DEFAULT FALSE,
    is_approved BOOLEAN DEFAULT TRUE,
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, item_type, item_id),
    INDEX idx_item (item_type, item_id),
    INDEX idx_rating (rating),
    INDEX idx_approved (is_approved),
    INDEX idx_created (created_at)
);

-- =========================================
-- CONTENT MANAGEMENT SYSTEM
-- =========================================

-- Blog posts and articles
CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    excerpt TEXT,
    content LONGTEXT NOT NULL,
    author_id INT NOT NULL,
    category_id INT,
    featured_image VARCHAR(255),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    is_featured BOOLEAN DEFAULT FALSE,
    views_count INT DEFAULT 0,
    meta_title VARCHAR(255),
    meta_description TEXT,
    tags JSON,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_featured (is_featured),
    INDEX idx_published (published_at),
    FULLTEXT idx_search (title, excerpt, content)
);

-- =========================================
-- SYSTEM TABLES
-- =========================================

-- Application settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_public (is_public)
);

-- Activity log for auditing
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50),
    resource_id INT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created (created_at)
);

-- File uploads tracking
CREATE TABLE uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    upload_type ENUM('avatar', 'cover', 'content', 'attachment', 'other') DEFAULT 'other',
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_type (upload_type),
    INDEX idx_filename (filename)
);

-- =========================================
-- INDEXES FOR PERFORMANCE
-- =========================================

-- Additional composite indexes for common queries
ALTER TABLE books ADD INDEX idx_active_featured (is_active, is_featured);
ALTER TABLE books ADD INDEX idx_category_active (category_id, is_active);
ALTER TABLE books ADD INDEX idx_price_rating (price, rating);

ALTER TABLE projects ADD INDEX idx_active_featured (is_active, is_featured);
ALTER TABLE projects ADD INDEX idx_category_active (category_id, is_active);
ALTER TABLE projects ADD INDEX idx_difficulty_rating (difficulty, rating);

ALTER TABLE games ADD INDEX idx_active_featured (is_active, is_featured);
ALTER TABLE games ADD INDEX idx_category_active (category_id, is_active);
ALTER TABLE games ADD INDEX idx_type_rating (game_type, rating);

ALTER TABLE orders ADD INDEX idx_user_status (user_id, status);
ALTER TABLE orders ADD INDEX idx_status_created (status, created_at);

-- =========================================
-- VIEWS FOR COMMON QUERIES
-- =========================================

-- View for popular content
CREATE VIEW popular_content AS
SELECT 
    'book' as item_type, id, title, views_count, rating, reviews_count, created_at
FROM books WHERE is_active = 1
UNION ALL
SELECT 
    'project' as item_type, id, title, views_count, rating, reviews_count, created_at
FROM projects WHERE is_active = 1
UNION ALL
SELECT 
    'game' as item_type, id, title, views_count, rating, reviews_count, created_at
FROM games WHERE is_active = 1
ORDER BY views_count DESC, rating DESC;

-- View for featured content
CREATE VIEW featured_content AS
SELECT 
    'book' as item_type, id, title, price, cover_image as image, rating, created_at
FROM books WHERE is_active = 1 AND is_featured = 1
UNION ALL
SELECT 
    'project' as item_type, id, title, price, cover_image as image, rating, created_at
FROM projects WHERE is_active = 1 AND is_featured = 1
UNION ALL
SELECT 
    'game' as item_type, id, title, price, thumbnail as image, rating, created_at
FROM games WHERE is_active = 1 AND is_featured = 1
ORDER BY rating DESC, created_at DESC;

-- View for user statistics
CREATE VIEW user_stats AS
SELECT 
    u.id,
    u.full_name,
    u.email,
    u.created_at,
    COALESCE(order_stats.order_count, 0) as total_orders,
    COALESCE(order_stats.total_spent, 0) as total_spent,
    COALESCE(library_stats.library_items, 0) as library_items,
    COALESCE(review_stats.review_count, 0) as review_count
FROM users u
LEFT JOIN (
    SELECT user_id, COUNT(*) as order_count, SUM(total_amount) as total_spent
    FROM orders WHERE status = 'completed'
    GROUP BY user_id
) order_stats ON u.id = order_stats.user_id
LEFT JOIN (
    SELECT o.user_id, COUNT(DISTINCT CONCAT(oi.item_type, '-', oi.item_id)) as library_items
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE o.status = 'completed'
    GROUP BY o.user_id
) library_stats ON u.id = library_stats.user_id
LEFT JOIN (
    SELECT user_id, COUNT(*) as review_count
    FROM reviews WHERE is_approved = 1
    GROUP BY user_id
) review_stats ON u.id = review_stats.user_id;

-- =========================================
-- COMPLETION MESSAGE
-- =========================================
SELECT 'Database schema created successfully!' as message;