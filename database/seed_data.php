<?php
require_once __DIR__ . '/../includes/config.php';

/**
 * Database Seeder
 * Populates the database with sample data for demonstration
 */

echo "🌱 Starting Database Seeding...\n";

try {
    $db->beginTransaction();
    
    // Clear existing data (optional - comment out if you want to keep existing data)
    echo "📋 Clearing existing sample data...\n";
    clearExistingData();
    
    // Seed data in order of dependencies
    echo "👥 Creating sample users...\n";
    seedUsers();
    
    echo "📚 Creating sample categories...\n";
    seedCategories();
    
    echo "📖 Creating sample books...\n";
    seedBooks();
    
    echo "💻 Creating sample projects...\n";
    seedProjects();
    
    echo "🎮 Creating sample games...\n";
    seedGames();
    
    echo "🛒 Creating sample orders...\n";
    seedOrders();
    
    echo "⭐ Creating sample reviews...\n";
    seedReviews();
    
    echo "❤️ Creating sample wishlists...\n";
    seedWishlists();
    
    echo "📊 Creating sample analytics data...\n";
    seedAnalytics();
    
    $db->commit();
    echo "✅ Database seeding completed successfully!\n";
    echo "🎉 Sample data has been created for demonstration purposes.\n\n";
    
    // Display login credentials
    displayLoginCredentials();
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ Error seeding database: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

/**
 * Clear existing sample data
 */
function clearExistingData() {
    global $db;
    
    // Disable foreign key checks temporarily
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Clear tables in reverse dependency order (only if they exist)
    $tables = [
        'activity_logs', 'user_purchases', 'order_items', 'orders', 'cart_items',
        'wishlist', 'reviews', 'games', 'projects', 'books', 'categories'
    ];
    
    foreach ($tables as $table) {
        // Check if table exists before trying to clear it
        $result = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($result && $result->rowCount() > 0) {
            $db->exec("DELETE FROM {$table} WHERE id > 0");
            $db->exec("ALTER TABLE {$table} AUTO_INCREMENT = 1");
        }
    }
    
    // Keep admin user but clear other users (only if users table exists)
    $result = $db->query("SHOW TABLES LIKE 'users'");
    if ($result && $result->rowCount() > 0) {
        $db->exec("DELETE FROM users WHERE id > 1");
        $db->exec("ALTER TABLE users AUTO_INCREMENT = 2");
    }
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
}

/**
 * Seed sample users
 */
function seedUsers() {
    global $db;
    
    $users = [
        [
            'username' => 'john_dev',
            'email' => 'john@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'full_name' => 'John Developer',
            'role' => 'user',
            'is_active' => 1,
            'email_verified' => 1
        ],
        [
            'username' => 'sarah_coder',
            'email' => 'sarah@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'full_name' => 'Sarah Coder',
            'role' => 'user',
            'is_active' => 1,
            'email_verified' => 1
        ],
        [
            'username' => 'mike_student',
            'email' => 'mike@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'full_name' => 'Mike Student',
            'role' => 'user',
            'is_active' => 1,
            'email_verified' => 1
        ],
        [
            'username' => 'alex_learner',
            'email' => 'alex@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'full_name' => 'Alex Learner',
            'role' => 'user',
            'is_active' => 1,
            'email_verified' => 1
        ],
        [
            'username' => 'emma_tech',
            'email' => 'emma@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'full_name' => 'Emma Tech',
            'role' => 'user',
            'is_active' => 1,
            'email_verified' => 1
        ]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO users (username, email, password, full_name, role, is_active, email_verified, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($users as $user) {
        $stmt->execute([
            $user['username'], $user['email'], $user['password'], 
            $user['full_name'], $user['role'], $user['is_active'], $user['email_verified']
        ]);
    }
}

/**
 * Seed sample categories
 */
function seedCategories() {
    global $db;
    
    $categories = [
        [
            'name' => 'Web Development',
            'slug' => 'web-development',
            'description' => 'Frontend and backend web development technologies'
        ],
        [
            'name' => 'Mobile Development',
            'slug' => 'mobile-development',
            'description' => 'iOS, Android, and cross-platform mobile app development'
        ],
        [
            'name' => 'Data Science',
            'slug' => 'data-science',
            'description' => 'Machine learning, AI, and data analysis'
        ],
        [
            'name' => 'DevOps',
            'slug' => 'devops',
            'description' => 'Deployment, automation, and infrastructure'
        ],
        [
            'name' => 'Game Development',
            'slug' => 'game-development',
            'description' => 'Game engines, graphics, and game programming'
        ],
        [
            'name' => 'Cybersecurity',
            'slug' => 'cybersecurity',
            'description' => 'Security, ethical hacking, and privacy'
        ],
        [
            'name' => 'Database',
            'slug' => 'database',
            'description' => 'SQL, NoSQL, and database design'
        ],
        [
            'name' => 'Programming Languages',
            'slug' => 'programming-languages',
            'description' => 'Various programming languages and paradigms'
        ]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO categories (name, slug, description, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    
    foreach ($categories as $category) {
        $stmt->execute([$category['name'], $category['slug'], $category['description']]);
    }
}

/**
 * Seed sample books
 */
function seedBooks() {
    global $db;
    
    $books = [
        [
            'title' => 'Modern JavaScript: The Complete Guide',
            'author' => 'Sarah Johnson',
            'description' => 'A comprehensive guide to modern JavaScript including ES6+, async programming, and advanced concepts. Perfect for developers looking to master JavaScript.',
            'category_id' => 1,
            'price' => 29.99,
            'pages' => 450,
            'language' => 'English',
            'file_path' => 'books/modern-javascript.pdf',
            'cover_image' => 'modern-javascript-cover.jpg',
            'is_active' => 1,
            'rating' => 4.8,
            'downloads_count' => 1250,
            'reviews_count' => 89
        ],
        [
            'title' => 'React Native: Building Mobile Apps',
            'author' => 'David Chen',
            'description' => 'Learn to build cross-platform mobile applications using React Native. Covers navigation, state management, and native module integration.',
            'category_id' => 2,
            'price' => 34.99,
            'pages' => 520,
            'language' => 'English',
            'file_path' => 'books/react-native-guide.pdf',
            'cover_image' => 'react-native-cover.jpg',
            'is_active' => 1,
            'rating' => 4.6,
            'downloads_count' => 890,
            'reviews_count' => 67
        ],
        [
            'title' => 'Python for Data Science',
            'author' => 'Dr. Maria Rodriguez',
            'description' => 'Master data science with Python. Covers pandas, numpy, matplotlib, scikit-learn, and machine learning fundamentals.',
            'category_id' => 3,
            'price' => 39.99,
            'pages' => 680,
            'language' => 'English',
            'file_path' => 'books/python-data-science.pdf',
            'cover_image' => 'python-ds-cover.jpg',
            'is_active' => 1,
            'rating' => 4.9,
            'downloads_count' => 2100,
            'reviews_count' => 156
        ],
        [
            'title' => 'Docker & Kubernetes Mastery',
            'author' => 'Alex Thompson',
            'description' => 'Complete guide to containerization and orchestration. Learn Docker, Kubernetes, and modern DevOps practices.',
            'category_id' => 4,
            'price' => 44.99,
            'pages' => 380,
            'language' => 'English',
            'file_path' => 'books/docker-kubernetes.pdf',
            'cover_image' => 'docker-k8s-cover.jpg',
            'is_active' => 1,
            'rating' => 4.7,
            'downloads_count' => 760,
            'reviews_count' => 45
        ],
        [
            'title' => 'Game Development with Unity',
            'author' => 'Emma Wilson',
            'description' => '2D and 3D game development using Unity engine. Includes scripting, physics, animations, and publishing to multiple platforms.',
            'category_id' => 5,
            'price' => 49.99,
            'pages' => 720,
            'language' => 'English',
            'file_path' => 'books/unity-game-dev.pdf',
            'cover_image' => 'unity-cover.jpg',
            'is_active' => 1,
            'rating' => 4.5,
            'downloads_count' => 650,
            'reviews_count' => 38
        ],
        [
            'title' => 'Ethical Hacking Fundamentals',
            'author' => 'James Security',
            'description' => 'Learn ethical hacking and penetration testing. Covers network security, vulnerability assessment, and security tools.',
            'category_id' => 6,
            'price' => 54.99,
            'pages' => 590,
            'language' => 'English',
            'file_path' => 'books/ethical-hacking.pdf',
            'cover_image' => 'ethical-hacking-cover.jpg',
            'is_active' => 1,
            'rating' => 4.4,
            'downloads_count' => 430,
            'reviews_count' => 29
        ],
        [
            'title' => 'Advanced SQL Techniques',
            'author' => 'Lisa Database',
            'description' => 'Master advanced SQL queries, optimization, and database design. Covers MySQL, PostgreSQL, and SQL Server.',
            'category_id' => 7,
            'price' => 32.99,
            'pages' => 420,
            'language' => 'English',
            'file_path' => 'books/advanced-sql.pdf',
            'cover_image' => 'sql-cover.jpg',
            'is_active' => 1,
            'rating' => 4.3,
            'downloads_count' => 890,
            'reviews_count' => 72
        ],
        [
            'title' => 'Go Programming Language',
            'author' => 'Robert Gopher',
            'description' => 'Complete guide to Go programming. Learn concurrency, web servers, microservices, and best practices.',
            'category_id' => 8,
            'price' => 0.00,
            'pages' => 340,
            'language' => 'English',
            'file_path' => 'books/go-programming.pdf',
            'cover_image' => 'go-cover.jpg',
            'is_active' => 1,
            'rating' => 4.6,
            'downloads_count' => 1520,
            'reviews_count' => 94
        ]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO books (title, author, description, category_id, price, pages, language, 
                          file_path, cover_image, is_active, rating, downloads_count, reviews_count, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($books as $book) {
        $stmt->execute([
            $book['title'], $book['author'], $book['description'], $book['category_id'],
            $book['price'], $book['pages'], $book['language'], $book['file_path'],
            $book['cover_image'], $book['is_active'], $book['rating'], 
            $book['downloads_count'], $book['reviews_count']
        ]);
    }
}

/**
 * Seed sample projects
 */
function seedProjects() {
    global $db;
    
    $projects = [
        [
            'title' => 'E-commerce Website with React',
            'description' => 'Build a complete e-commerce website using React, Redux, and Node.js. Includes user authentication, shopping cart, payment integration, and admin panel.',
            'category_id' => 1,
            'difficulty' => 'Intermediate',
            'technologies' => 'React, Redux, Node.js, MongoDB, Stripe',
            'demo_url' => 'https://demo-ecommerce.example.com',
            'price' => 59.99,
            'image' => 'ecommerce-project.jpg',
            'is_active' => 1,
            'rating' => 4.7,
            'downloads_count' => 456,
            'reviews_count' => 34
        ],
        [
            'title' => 'Flutter Chat Application',
            'description' => 'Real-time chat application using Flutter and Firebase. Features include group chats, file sharing, push notifications, and user presence.',
            'category_id' => 2,
            'difficulty' => 'Advanced',
            'technologies' => 'Flutter, Firebase, Dart, Cloud Firestore',
            'demo_url' => 'https://flutter-chat.example.com',
            'price' => 49.99,
            'image' => 'flutter-chat.jpg',
            'is_active' => 1,
            'rating' => 4.8,
            'downloads_count' => 324,
            'reviews_count' => 28
        ],
        [
            'title' => 'Machine Learning Stock Predictor',
            'description' => 'Predict stock prices using machine learning algorithms. Includes data collection, preprocessing, model training, and web interface.',
            'category_id' => 3,
            'difficulty' => 'Advanced',
            'technologies' => 'Python, Scikit-learn, Pandas, Flask, Chart.js',
            'demo_url' => 'https://stock-predictor.example.com',
            'price' => 79.99,
            'image' => 'ml-stocks.jpg',
            'is_active' => 1,
            'rating' => 4.5,
            'downloads_count' => 198,
            'reviews_count' => 19
        ],
        [
            'title' => 'CI/CD Pipeline with Jenkins',
            'description' => 'Set up automated CI/CD pipeline using Jenkins, Docker, and Kubernetes. Includes testing, building, and deployment automation.',
            'category_id' => 4,
            'difficulty' => 'Intermediate',
            'technologies' => 'Jenkins, Docker, Kubernetes, Git, AWS',
            'demo_url' => '',
            'price' => 39.99,
            'image' => 'cicd-pipeline.jpg',
            'is_active' => 1,
            'rating' => 4.6,
            'downloads_count' => 287,
            'reviews_count' => 22
        ],
        [
            'title' => '2D Platformer Game',
            'description' => 'Create a complete 2D platformer game with Unity. Includes character controls, level design, enemies, collectibles, and sound effects.',
            'category_id' => 5,
            'difficulty' => 'Beginner',
            'technologies' => 'Unity, C#, 2D Physics, Animation',
            'demo_url' => 'https://platformer-demo.example.com',
            'price' => 0.00,
            'image' => '2d-platformer.jpg',
            'is_active' => 1,
            'rating' => 4.4,
            'downloads_count' => 892,
            'reviews_count' => 67
        ],
        [
            'title' => 'Web Security Scanner',
            'description' => 'Build a web vulnerability scanner that detects common security issues like SQL injection, XSS, and CSRF vulnerabilities.',
            'category_id' => 6,
            'difficulty' => 'Advanced',
            'technologies' => 'Python, Requests, BeautifulSoup, Threading',
            'demo_url' => '',
            'price' => 69.99,
            'image' => 'security-scanner.jpg',
            'is_active' => 1,
            'rating' => 4.3,
            'downloads_count' => 156,
            'reviews_count' => 14
        ]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO projects (title, description, category_id, difficulty, technologies, 
                             demo_url, price, image, is_active, rating, downloads_count, reviews_count, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($projects as $project) {
        $stmt->execute([
            $project['title'], $project['description'], $project['category_id'], $project['difficulty'],
            $project['technologies'], $project['demo_url'], $project['price'], $project['image'],
            $project['is_active'], $project['rating'], $project['downloads_count'], $project['reviews_count']
        ]);
    }
}

/**
 * Seed sample games
 */
function seedGames() {
    global $db;
    
    $games = [
        [
            'title' => 'Code Quest: JavaScript Adventure',
            'description' => 'Learn JavaScript fundamentals through an interactive RPG adventure. Solve coding challenges to progress through the story.',
            'category_id' => 8,
            'genre' => 'Educational RPG',
            'price' => 19.99,
            'image' => 'code-quest-js.jpg',
            'is_active' => 1,
            'rating' => 4.6,
            'plays_count' => 2340,
            'reviews_count' => 87
        ],
        [
            'title' => 'Algorithm Master',
            'description' => 'Master data structures and algorithms through interactive puzzles and challenges. Visual representations help understand complex concepts.',
            'category_id' => 8,
            'genre' => 'Puzzle',
            'price' => 24.99,
            'image' => 'algorithm-master.jpg',
            'is_active' => 1,
            'rating' => 4.8,
            'plays_count' => 1890,
            'reviews_count' => 124
        ],
        [
            'title' => 'CSS Battle Arena',
            'description' => 'Compete with other developers in CSS challenges. Create pixel-perfect designs and climb the leaderboard.',
            'category_id' => 1,
            'genre' => 'Competitive',
            'price' => 0.00,
            'image' => 'css-battle.jpg',
            'is_active' => 1,
            'rating' => 4.4,
            'plays_count' => 5670,
            'reviews_count' => 203
        ],
        [
            'title' => 'SQL Detective',
            'description' => 'Solve mysteries using SQL queries. Each case requires different database skills to uncover clues and solve the crime.',
            'category_id' => 7,
            'genre' => 'Mystery',
            'price' => 15.99,
            'image' => 'sql-detective.jpg',
            'is_active' => 1,
            'rating' => 4.5,
            'plays_count' => 1234,
            'reviews_count' => 78
        ],
        [
            'title' => 'Python Snake Evolution',
            'description' => 'Classic snake game with a twist - your snake evolves as you learn Python concepts. Each level introduces new programming concepts.',
            'category_id' => 8,
            'genre' => 'Arcade',
            'price' => 12.99,
            'image' => 'python-snake.jpg',
            'is_active' => 1,
            'rating' => 4.2,
            'plays_count' => 3456,
            'reviews_count' => 145
        ]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO games (title, description, category_id, genre, price, image, 
                          is_active, rating, plays_count, reviews_count, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($games as $game) {
        $stmt->execute([
            $game['title'], $game['description'], $game['category_id'], $game['genre'],
            $game['price'], $game['image'], $game['is_active'], $game['rating'],
            $game['plays_count'], $game['reviews_count']
        ]);
    }
}

/**
 * Seed sample orders
 */
function seedOrders() {
    global $db;
    
    $orders = [
        [
            'user_id' => 2,
            'order_number' => 'ORD-2024-001',
            'total_amount' => 89.98,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'created_at' => '2024-01-15 10:30:00'
        ],
        [
            'user_id' => 3,
            'order_number' => 'ORD-2024-002',
            'total_amount' => 49.99,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'paypal',
            'created_at' => '2024-01-20 14:15:00'
        ],
        [
            'user_id' => 4,
            'order_number' => 'ORD-2024-003',
            'total_amount' => 119.97,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'created_at' => '2024-02-01 09:45:00'
        ],
        [
            'user_id' => 5,
            'order_number' => 'ORD-2024-004',
            'total_amount' => 79.99,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'created_at' => '2024-02-10 16:20:00'
        ],
        [
            'user_id' => 6,
            'order_number' => 'ORD-2024-005',
            'total_amount' => 24.99,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'paypal',
            'created_at' => '2024-02-12 11:10:00'
        ]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO orders (user_id, order_number, total_amount, status, payment_status, 
                           payment_method, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($orders as $order) {
        $stmt->execute([
            $order['user_id'], $order['order_number'], $order['total_amount'],
            $order['status'], $order['payment_status'], $order['payment_method'],
            $order['created_at'], $order['created_at']
        ]);
    }
    
    // Add order items
    $orderItems = [
        ['order_id' => 1, 'item_type' => 'book', 'item_id' => 1, 'price' => 29.99, 'quantity' => 1],
        ['order_id' => 1, 'item_type' => 'project', 'item_id' => 1, 'price' => 59.99, 'quantity' => 1],
        ['order_id' => 2, 'item_type' => 'project', 'item_id' => 2, 'price' => 49.99, 'quantity' => 1],
        ['order_id' => 3, 'item_type' => 'book', 'item_id' => 3, 'price' => 39.99, 'quantity' => 1],
        ['order_id' => 3, 'item_type' => 'game', 'item_id' => 1, 'price' => 19.99, 'quantity' => 1],
        ['order_id' => 3, 'item_type' => 'book', 'item_id' => 4, 'price' => 44.99, 'quantity' => 1],
        ['order_id' => 4, 'item_type' => 'project', 'item_id' => 3, 'price' => 79.99, 'quantity' => 1],
        ['order_id' => 5, 'item_type' => 'game', 'item_id' => 2, 'price' => 24.99, 'quantity' => 1]
    ];
    
    $itemStmt = $db->prepare("
        INSERT INTO order_items (order_id, item_type, item_id, price, quantity)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($orderItems as $item) {
        $itemStmt->execute([
            $item['order_id'], $item['item_type'], $item['item_id'],
            $item['price'], $item['quantity']
        ]);
    }
}

/**
 * Seed sample reviews
 */
function seedReviews() {
    global $db;
    
    $reviews = [
        ['user_id' => 2, 'item_type' => 'book', 'item_id' => 1, 'rating' => 5, 'comment' => 'Excellent JavaScript guide! Very comprehensive and easy to follow.'],
        ['user_id' => 3, 'item_type' => 'book', 'item_id' => 1, 'rating' => 5, 'comment' => 'Best JavaScript book I\'ve read. The examples are practical and relevant.'],
        ['user_id' => 4, 'item_type' => 'book', 'item_id' => 3, 'rating' => 5, 'comment' => 'Perfect for data science beginners. Great explanations and code examples.'],
        ['user_id' => 5, 'item_type' => 'project', 'item_id' => 1, 'rating' => 4, 'comment' => 'Good e-commerce project. Learned a lot about React and backend integration.'],
        ['user_id' => 6, 'item_type' => 'game', 'item_id' => 1, 'rating' => 5, 'comment' => 'Fun way to learn JavaScript! My kids love it too.'],
        ['user_id' => 2, 'item_type' => 'game', 'item_id' => 3, 'rating' => 4, 'comment' => 'Great CSS practice. The challenges are well-designed.'],
        ['user_id' => 3, 'item_type' => 'project', 'item_id' => 2, 'rating' => 5, 'comment' => 'Amazing Flutter tutorial. Helped me build my first mobile app.']
    ];
    
    $stmt = $db->prepare("
        INSERT INTO reviews (user_id, item_type, item_id, rating, comment, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($reviews as $review) {
        $stmt->execute([
            $review['user_id'], $review['item_type'], $review['item_id'],
            $review['rating'], $review['comment']
        ]);
    }
}

/**
 * Seed sample wishlists
 */
function seedWishlists() {
    global $db;
    
    $wishlists = [
        ['user_id' => 2, 'item_type' => 'book', 'item_id' => 5],
        ['user_id' => 2, 'item_type' => 'project', 'item_id' => 3],
        ['user_id' => 3, 'item_type' => 'book', 'item_id' => 6],
        ['user_id' => 3, 'item_type' => 'game', 'item_id' => 4],
        ['user_id' => 4, 'item_type' => 'book', 'item_id' => 2],
        ['user_id' => 4, 'item_type' => 'project', 'item_id' => 5],
        ['user_id' => 5, 'item_type' => 'game', 'item_id' => 2],
        ['user_id' => 6, 'item_type' => 'book', 'item_id' => 7]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO wishlist (user_id, item_type, item_id, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    
    foreach ($wishlists as $wishlist) {
        $stmt->execute([$wishlist['user_id'], $wishlist['item_type'], $wishlist['item_id']]);
    }
}

/**
 * Seed analytics data
 */
function seedAnalytics() {
    global $db;
    
    // Create some activity logs for analytics
    $activities = [
        ['user_id' => 2, 'action' => 'book_purchased', 'resource_type' => 'books', 'resource_id' => 1],
        ['user_id' => 2, 'action' => 'book_downloaded', 'resource_type' => 'books', 'resource_id' => 1],
        ['user_id' => 3, 'action' => 'project_purchased', 'resource_type' => 'projects', 'resource_id' => 2],
        ['user_id' => 4, 'action' => 'game_played', 'resource_type' => 'games', 'resource_id' => 1],
        ['user_id' => 5, 'action' => 'book_viewed', 'resource_type' => 'books', 'resource_id' => 3],
        ['user_id' => 6, 'action' => 'wishlist_added', 'resource_type' => 'books', 'resource_id' => 7]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, resource_type, resource_id, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    foreach ($activities as $activity) {
        $stmt->execute([
            $activity['user_id'], $activity['action'], 
            $activity['resource_type'], $activity['resource_id']
        ]);
    }
}

/**
 * Display login credentials
 */
function displayLoginCredentials() {
    echo "📧 Sample User Accounts Created:\n";
    echo "================================\n";
    echo "👑 Admin Account:\n";
    echo "   Username: admin\n";
    echo "   Email: admin@bitversity.com\n";
    echo "   Password: admin123\n\n";
    
    echo "👥 Sample User Accounts:\n";
    $users = ['john_dev', 'sarah_coder', 'mike_student', 'alex_learner', 'emma_tech'];
    foreach ($users as $user) {
        echo "   Username: {$user}\n";
        echo "   Email: " . str_replace('_', '', $user) . "@example.com\n";
        echo "   Password: password123\n\n";
    }
    
    echo "📊 Sample Data Created:\n";
    echo "• 8 Books (1 free, 7 paid)\n";
    echo "• 6 Projects (1 free, 5 paid)\n";
    echo "• 5 Games (1 free, 4 paid)\n";
    echo "• 5 Orders with various statuses\n";
    echo "• 7 Reviews and ratings\n";
    echo "• 8 Wishlist items\n";
    echo "• 8 Categories\n";
    echo "• 5 Regular users + 1 Admin\n\n";
    
    echo "🌐 You can now:\n";
    echo "• Browse content at: /public/books.php, /public/projects.php, /public/games.php\n";
    echo "• Access admin panel at: /admin/\n";
    echo "• Test user registration and login\n";
    echo "• Test purchase flow and cart functionality\n";
    echo "• Explore all admin management features\n\n";
}
?>