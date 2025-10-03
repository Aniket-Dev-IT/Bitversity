<?php
class AnalyticsService {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Track page views with enhanced features
    public function trackPageView($page, $user_id = null) {
        try {
            // Check if analytics is enabled and we should track this view
            if (!$this->isAnalyticsEnabled() || !$this->shouldTrackView()) {
                return false;
            }
            
            $ip = $this->getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $referrer = $_SERVER['HTTP_REFERER'] ?? '';
            
            $stmt = $this->db->prepare("
                INSERT INTO page_views (page, user_id, ip_address, user_agent, referrer, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$page, $user_id, $ip, $userAgent, $referrer]);
            
            return true;
        } catch (Exception $e) {
            error_log("Analytics tracking error: " . $e->getMessage());
            return false;
        }
    }
    
    // Track search queries
    public function trackSearch($query, $resultsCount, $user_id = null) {
        try {
            if (!$this->isAnalyticsEnabled()) {
                return false;
            }
            
            $ip = $this->getClientIP();
            
            $stmt = $this->db->prepare("
                INSERT INTO search_logs (query, results_count, user_id, ip_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            return $stmt->execute([$query, $resultsCount, $user_id, $ip]);
        } catch (Exception $e) {
            error_log("Search analytics error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get dashboard statistics
    public function getDashboardStats($days = 30) {
        $stats = [];
        
        // Total counts
        $stats['total_users'] = $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['total_orders'] = $this->db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $stats['total_revenue'] = $this->db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders")->fetchColumn();
        $stats['total_content'] = $this->db->query("
            SELECT (SELECT COUNT(*) FROM books WHERE is_active = 1) + 
                   (SELECT COUNT(*) FROM projects WHERE is_active = 1) + 
                   (SELECT COUNT(*) FROM games WHERE is_active = 1)
        ")->fetchColumn();
        
        // Recent activity (last 30 days)
        $date_from = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ?");
        $stmt->execute([$date_from]);
        $stats['new_users_period'] = $stmt->fetchColumn();
        
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE created_at >= ?");
        $stmt->execute([$date_from]);
        $stats['orders_period'] = $stmt->fetchColumn();
        
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE created_at >= ?");
        $stmt->execute([$date_from]);
        $stats['revenue_period'] = $stmt->fetchColumn();
        
        // Top selling items
        $stats['top_books'] = $this->getTopSellingItems('book', 5);
        $stats['top_projects'] = $this->getTopSellingItems('project', 5);
        $stats['top_games'] = $this->getTopSellingItems('game', 5);
        
        // Revenue by day (last 7 days)
        $stats['daily_revenue'] = $this->getDailyRevenue(7);
        
        // Popular pages
        $stats['popular_pages'] = $this->getPopularPages(10);
        
        return $stats;
    }
    
    private function getTopSellingItems($type, $limit = 10) {
        try {
            if (!$this->tableExists('order_items')) {
                return [];
            }
            
            $stmt = $this->db->prepare("
                SELECT oi.item_id, oi.item_title as title, COUNT(*) as sales, SUM(oi.unit_price * oi.quantity) as revenue
                FROM order_items oi
                WHERE oi.item_type = ?
                GROUP BY oi.item_id, oi.item_title
                ORDER BY sales DESC
                LIMIT ?
            ");
            $stmt->execute([$type, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getDailyRevenue($days = 7) {
        $stmt = $this->db->prepare("
            SELECT DATE(created_at) as date, SUM(total_amount) as revenue
            FROM orders 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getPopularPages($limit = 10) {
        if (!$this->tableExists('page_views')) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT page, COUNT(*) as views, COUNT(DISTINCT ip_address) as unique_views
            FROM page_views 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY page
            ORDER BY views DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // User engagement metrics
    public function getUserEngagement() {
        $engagement = [];
        
        // Average session duration (simulated)
        $engagement['avg_session_duration'] = '4:32';
        
        // Bounce rate (simulated)
        $engagement['bounce_rate'] = '32%';
        
        // Return visitor rate
        if ($this->tableExists('page_views')) {
            $total_sessions = $this->db->query("
                SELECT COUNT(DISTINCT CONCAT(ip_address, DATE(created_at))) FROM page_views
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ")->fetchColumn();
            
            $return_sessions = $this->db->query("
                SELECT COUNT(DISTINCT ip_address) FROM page_views 
                WHERE ip_address IN (
                    SELECT ip_address FROM page_views 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
                ) AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ")->fetchColumn();
            
            $engagement['return_rate'] = $total_sessions > 0 ? 
                round(($return_sessions / $total_sessions) * 100) . '%' : '0%';
        } else {
            $engagement['return_rate'] = '45%';
        }
        
        return $engagement;
    }
    
    // Sales analytics
    public function getSalesAnalytics($period = '30_days') {
        $analytics = [];
        
        $date_condition_with_alias = $this->getDateCondition($period, 'o');
        $date_condition_no_alias = $this->getDateCondition($period, '');
        
        // Revenue by content type
        try {
            if ($this->tableExists('order_items')) {
                $stmt = $this->db->query("
                    SELECT oi.item_type, SUM(oi.unit_price * oi.quantity) as revenue, COUNT(*) as sales
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    WHERE $date_condition_with_alias
                    GROUP BY oi.item_type
                    ORDER BY revenue DESC
                ");
                $analytics['revenue_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $analytics['revenue_by_type'] = [];
            }
        } catch (Exception $e) {
            $analytics['revenue_by_type'] = [];
        }
        
        // Average order value
        $stmt = $this->db->prepare("
            SELECT AVG(total_amount) as avg_order_value
            FROM orders 
            WHERE $date_condition_no_alias
        ");
        $stmt->execute();
        $analytics['avg_order_value'] = $stmt->fetchColumn() ?: 0;
        
        // Conversion rate (orders/users ratio)
        $total_users = $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $total_orders = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE $date_condition_no_alias");
        $total_orders->execute();
        $orders_count = $total_orders->fetchColumn();
        
        $analytics['conversion_rate'] = $total_users > 0 ? 
            round(($orders_count / $total_users) * 100, 2) : 0;
        
        return $analytics;
    }
    
    private function getDateCondition($period, $alias = 'o') {
        $column = $alias ? $alias . '.created_at' : 'created_at';
        
        switch ($period) {
            case '7_days':
                return "$column >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30_days':
                return "$column >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case '90_days':
                return "$column >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            case '1_year':
                return "$column >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return "$column >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }
    
    // Get popular searches
    public function getPopularSearches($limit = 10, $days = 30) {
        try {
            if (!$this->tableExists('search_logs')) {
                return [];
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    query,
                    COUNT(*) as search_count,
                    AVG(results_count) as avg_results,
                    COUNT(DISTINCT user_id) as unique_users
                FROM search_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY query 
                ORDER BY search_count DESC 
                LIMIT ?
            ");
            
            $stmt->execute([$days, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Analytics error: " . $e->getMessage());
            return [];
        }
    }
    
    // Get traffic statistics
    public function getTrafficStats($days = 30) {
        try {
            if (!$this->tableExists('page_views')) {
                return [];
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as page_views,
                    COUNT(DISTINCT ip_address) as unique_visitors,
                    COUNT(DISTINCT user_id) as logged_in_users
                FROM page_views 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at) 
                ORDER BY date DESC
            ");
            
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Analytics error: " . $e->getMessage());
            return [];
        }
    }
    
    // Get referrer statistics
    public function getReferrerStats($limit = 10, $days = 30) {
        try {
            if (!$this->tableExists('page_views')) {
                return [];
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    CASE 
                        WHEN referrer = '' OR referrer IS NULL THEN 'Direct'
                        WHEN referrer LIKE '%google%' THEN 'Google'
                        WHEN referrer LIKE '%bing%' THEN 'Bing'
                        WHEN referrer LIKE '%facebook%' THEN 'Facebook'
                        WHEN referrer LIKE '%twitter%' THEN 'Twitter'
                        WHEN referrer LIKE '%youtube%' THEN 'YouTube'
                        ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '//', -1), '/', 1)
                    END as referrer_source,
                    COUNT(*) as visits,
                    COUNT(DISTINCT ip_address) as unique_visitors
                FROM page_views 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY referrer_source
                ORDER BY visits DESC
                LIMIT ?
            ");
            
            $stmt->execute([$days, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Analytics error: " . $e->getMessage());
            return [];
        }
    }
    
    // Get device/browser statistics
    public function getDeviceStats($days = 30) {
        try {
            if (!$this->tableExists('page_views')) {
                return [];
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    CASE 
                        WHEN user_agent LIKE '%Mobile%' OR user_agent LIKE '%Android%' OR user_agent LIKE '%iPhone%' THEN 'Mobile'
                        WHEN user_agent LIKE '%Tablet%' OR user_agent LIKE '%iPad%' THEN 'Tablet'
                        ELSE 'Desktop'
                    END as device_type,
                    CASE 
                        WHEN user_agent LIKE '%Chrome%' THEN 'Chrome'
                        WHEN user_agent LIKE '%Firefox%' THEN 'Firefox'
                        WHEN user_agent LIKE '%Safari%' AND user_agent NOT LIKE '%Chrome%' THEN 'Safari'
                        WHEN user_agent LIKE '%Edge%' THEN 'Edge'
                        WHEN user_agent LIKE '%Opera%' THEN 'Opera'
                        ELSE 'Other'
                    END as browser,
                    COUNT(*) as visits,
                    COUNT(DISTINCT ip_address) as unique_visitors
                FROM page_views 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY device_type, browser
                ORDER BY visits DESC
            ");
            
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Analytics error: " . $e->getMessage());
            return [];
        }
    }
    
    // Get conversion funnel data
    public function getConversionFunnel($days = 30) {
        try {
            // Get unique visitors
            $stmt1 = $this->db->prepare("
                SELECT COUNT(DISTINCT ip_address) as unique_visitors
                FROM page_views 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt1->execute([$days]);
            $uniqueVisitors = $stmt1->fetchColumn() ?: 0;
            
            // Get users who viewed product pages
            $stmt2 = $this->db->prepare("
                SELECT COUNT(DISTINCT ip_address) as product_viewers
                FROM page_views 
                WHERE (page LIKE '%book_detail%' OR page LIKE '%project_detail%' OR page LIKE '%game_detail%')
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt2->execute([$days]);
            $productViewers = $stmt2->fetchColumn() ?: 0;
            
            // Get users who added to cart
            $stmt3 = $this->db->prepare("
                SELECT COUNT(DISTINCT user_id) as cart_users
                FROM cart c
                JOIN users u ON c.user_id = u.id
                WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt3->execute([$days]);
            $cartUsers = $stmt3->fetchColumn() ?: 0;
            
            // Get users who made purchases
            $stmt4 = $this->db->prepare("
                SELECT COUNT(DISTINCT user_id) as buyers
                FROM orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt4->execute([$days]);
            $buyers = $stmt4->fetchColumn() ?: 0;
            
            return [
                'unique_visitors' => $uniqueVisitors,
                'product_viewers' => $productViewers,
                'cart_users' => $cartUsers,
                'buyers' => $buyers,
                'view_to_product_rate' => $uniqueVisitors > 0 ? round(($productViewers / $uniqueVisitors) * 100, 2) : 0,
                'product_to_cart_rate' => $productViewers > 0 ? round(($cartUsers / $productViewers) * 100, 2) : 0,
                'cart_to_purchase_rate' => $cartUsers > 0 ? round(($buyers / $cartUsers) * 100, 2) : 0,
                'overall_conversion_rate' => $uniqueVisitors > 0 ? round(($buyers / $uniqueVisitors) * 100, 2) : 0
            ];
        } catch (Exception $e) {
            error_log("Analytics error: " . $e->getMessage());
            return [];
        }
    }
    
    // Check if analytics tracking is enabled
    private function isAnalyticsEnabled() {
        try {
            $stmt = $this->db->prepare("
                SELECT setting_value 
                FROM system_settings 
                WHERE setting_key = 'analytics_tracking'
            ");
            $stmt->execute();
            $result = $stmt->fetchColumn();
            return $result === '1';
        } catch (Exception $e) {
            return true; // Default to enabled if setting not found
        }
    }
    
    // Get client IP address
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    private function shouldTrackView() {
        // Simple rate limiting - don't track more than 1 view per minute from same IP
        if (!isset($_SESSION['last_tracked_view'])) {
            $_SESSION['last_tracked_view'] = time();
            return true;
        }
        
        if (time() - $_SESSION['last_tracked_view'] > 60) {
            $_SESSION['last_tracked_view'] = time();
            return true;
        }
        
        return false;
    }
    
    private function tableExists($tableName) {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE '" . $tableName . "'");
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Create all analytics tables
    public function initializeTracking() {
        try {
            // Create page_views table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS page_views (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    page VARCHAR(255) NOT NULL,
                    user_id INT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent TEXT,
                    referrer VARCHAR(500),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_page (page),
                    INDEX idx_user (user_id),
                    INDEX idx_ip (ip_address),
                    INDEX idx_created (created_at),
                    INDEX idx_page_date (page, created_at)
                )
            ");
            
            // Create search_logs table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS search_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    query VARCHAR(255) NOT NULL,
                    results_count INT NOT NULL DEFAULT 0,
                    user_id INT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_query (query),
                    INDEX idx_user (user_id),
                    INDEX idx_created (created_at),
                    INDEX idx_results (results_count)
                )
            ");
            
            // Create system_settings table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS system_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) NOT NULL UNIQUE,
                    setting_value TEXT,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_key (setting_key)
                )
            ");
            
            // Insert default system settings
            $this->db->exec("
                INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
                ('site_name', 'Bitversity', 'Website name'),
                ('site_description', 'Digital Learning Platform', 'Website description'),
                ('maintenance_mode', '0', 'Enable maintenance mode (0=disabled, 1=enabled)'),
                ('registration_enabled', '1', 'Allow new user registration'),
                ('email_verification_required', '0', 'Require email verification for new accounts'),
                ('max_cart_items', '50', 'Maximum items allowed in cart'),
                ('session_timeout', '7200', 'Session timeout in seconds'),
                ('file_upload_max_size', '10485760', 'Maximum file upload size in bytes'),
                ('reviews_require_approval', '0', 'Reviews require admin approval'),
                ('analytics_tracking', '1', 'Enable analytics tracking')
            ");
            
            return true;
        } catch (Exception $e) {
            error_log("Analytics initialization error: " . $e->getMessage());
            return false;
        }
    }
}
?>