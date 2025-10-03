<?php
/**
 * =====================================================
 * BITVERSITY - PROPRIETARY SOFTWARE
 * =====================================================
 * 
 * © Copyright 2024 Aniket Kumar. All Rights Reserved.
 * 
 * CONFIDENTIAL AND PROPRIETARY
 * 
 * This source code is the intellectual property of 
 * Aniket Kumar and is protected by copyright law and 
 * international treaties.
 * 
 * UNAUTHORIZED USE, COPYING, MODIFICATION, OR 
 * DISTRIBUTION IS STRICTLY PROHIBITED AND WILL 
 * RESULT IN LEGAL ACTION.
 * 
 * Contact Information:
 * Email: aniket.kumar.devpro@gmail.com
 * WhatsApp: +91 8318601925
 * GitHub: @Aniket-Dev-IT
 * 
 * For licensing inquiries, please contact the owner.
 * 
 * =====================================================
 * 
 * Bitversity - Digital Learning Platform
 * Main homepage displaying featured content, platform statistics,
 * category navigation, and promotional sections.
 * 
 * @package    Bitversity
 * @author     Aniket Kumar
 * @version    2.1.0
 * @since      2024
 */

require_once __DIR__ . '/includes/config.php';

// Check if database is available
if ($db === null) {
    // Database not available - redirect to setup
    if (file_exists(__DIR__ . '/setup.php')) {
        header('Location: setup.php');
        exit;
    } else {
        die('<h1>Database Error</h1><p>Database connection failed. Please check your configuration and try again.</p>');
    }
}

// Get featured items for homepage
$stmt = $db->prepare("
    SELECT 'book' as type, id, title, author as subtitle, price, cover_image as image, description,
           COALESCE(rating, 0) as rating, COALESCE(total_ratings, 0) as total_ratings,
           COALESCE(views_count, 0) as views_count, COALESCE(downloads_count, 0) as downloads_count
    FROM books WHERE is_active = 1
    UNION ALL
    SELECT 'project' as type, id, title, 'Project' as subtitle, price, cover_image as image, description,
           COALESCE(rating, 0) as rating, COALESCE(total_ratings, 0) as total_ratings,
           COALESCE(views_count, 0) as views_count, COALESCE(downloads_count, 0) as downloads_count
    FROM projects WHERE is_active = 1
    UNION ALL
    SELECT 'game' as type, id, title, genre as subtitle, price, thumbnail as image, description,
           COALESCE(rating, 0) as rating, COALESCE(total_ratings, 0) as total_ratings,
           COALESCE(views_count, 0) as views_count, COALESCE(downloads_count, 0) as downloads_count
    FROM games WHERE is_active = 1
    ORDER BY RAND()
    LIMIT 8
");
$stmt->execute();
$featuredItems = $stmt->fetchAll();

// Get top 6 categories for homepage display (prioritize those with content)
$stmt = $db->prepare("
    SELECT c.*, 
           COALESCE((
               SELECT COUNT(*) FROM books WHERE category_id = c.id AND is_active = 1
           ), 0) + 
           COALESCE((
               SELECT COUNT(*) FROM projects WHERE category_id = c.id AND is_active = 1
           ), 0) + 
           COALESCE((
               SELECT COUNT(*) FROM games WHERE category_id = c.id AND is_active = 1
           ), 0) as content_count
    FROM categories c 
    WHERE c.is_active = 1
    ORDER BY content_count DESC, c.name ASC
    LIMIT 6
");
$stmt->execute();
$categories = $stmt->fetchAll();

// Get some statistics
$statsQueries = [
    'books' => "SELECT COUNT(*) as count FROM books WHERE is_active = 1",
    'projects' => "SELECT COUNT(*) as count FROM projects WHERE is_active = 1", 
    'games' => "SELECT COUNT(*) as count FROM games WHERE is_active = 1",
    'users' => "SELECT COUNT(*) as count FROM users WHERE is_active = 1"
];

$stats = [];
foreach ($statsQueries as $key => $query) {
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats[$key] = $stmt->fetch()['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Bitversity - Your digital learning platform for books, coding projects, and interactive games. Master new skills with our comprehensive resources.">
    <meta name="keywords" content="learning, books, programming, projects, games, education, coding">
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">
    
    <title><?php echo APP_NAME; ?> - Digital Learning Platform</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#007bff">
    <meta name="msapplication-TileColor" content="#007bff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Bitversity">
    <meta name="application-name" content="Bitversity">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
</head>
<body data-logged-in="<?php echo isLoggedIn() ? 'true' : 'false'; ?>">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Modern Hero Section -->
    <section class="hero-section" style="background-image: url('https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center; background-repeat: no-repeat;">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.4); z-index: 1;"></div>
        <div class="container" style="position: relative; z-index: 2;">
            <div class="row align-items-center">
                <div class="col-12">
                    <div class="text-center text-white">
                        <h1 class="display-2 fw-bold mb-4" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Master Your Skills with <?php echo APP_NAME; ?></h1>
                        <p class="lead mb-4" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5); max-width: 800px; margin: 0 auto;">Discover thousands of books, hands-on projects, and interactive games designed to accelerate your learning journey in programming, design, and technology.</p>
                        
                        <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
                            <a href="<?php echo BASE_PATH; ?>/public/books.php" class="btn btn-light btn-lg px-4">
                                <i class="fas fa-book me-2"></i>Browse Books
                            </a>
                            <a href="<?php echo BASE_PATH; ?>/public/projects.php" class="btn btn-outline-light btn-lg px-4">
                                <i class="fas fa-code me-2"></i>Explore Projects
                            </a>
                            <a href="<?php echo BASE_PATH; ?>/public/games.php" class="btn btn-light btn-lg px-4">
                                <i class="fas fa-gamepad me-2"></i>Explore Games
                            </a>
                        </div>
                        
                        <?php if (!isLoggedIn()): ?>
                        <p class="text-light">
                            <a href="<?php echo BASE_PATH; ?>/auth/register.php" class="text-white text-decoration-underline">Join free</a>
                            and start learning today!
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced Stats Section -->
    <section class="py-5 bg-gradient-light bg-pattern">
        <div class="container">
            <div class="section-header mb-5">
                <h2>Join Thousands of Learners</h2>
                <p class="lead">Be part of our growing community of developers, designers, and tech enthusiasts</p>
            </div>
            <div class="row text-center">
                <div class="col-md-3 mb-4">
                    <div class="dashboard-stat">
                        <div class="dashboard-stat-number"><?php echo number_format($stats['books']); ?>K</div>
                        <h6><i class="fas fa-book me-2 text-primary"></i>Books Available</h6>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="dashboard-stat">
                        <div class="dashboard-stat-number"><?php echo number_format($stats['projects']); ?>K</div>
                        <h6><i class="fas fa-code me-2 text-success"></i>Learning Projects</h6>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="dashboard-stat">
                        <div class="dashboard-stat-number"><?php echo number_format($stats['games']); ?>K</div>
                        <h6><i class="fas fa-gamepad me-2 text-warning"></i>Interactive Games</h6>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="dashboard-stat">
                        <div class="dashboard-stat-number"><?php echo number_format($stats['users']); ?>K+</div>
                        <h6><i class="fas fa-users me-2 text-info"></i>Active Learners</h6>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced Categories Section -->
    <section class="py-5" style="background: white;">
        <div class="container">
            <div class="section-header">
                <h2>Explore by Category</h2>
                <p class="lead">Find learning resources in your area of interest</p>
            </div>
            
            <div class="row g-4">
                <?php foreach ($categories as $category): ?>
                <?php
                    // Determine the best page for this category
                    $categorySlug = strtolower($category['slug']);
                    if (strpos($categorySlug, 'game') !== false || in_array($categorySlug, ['action-games', 'puzzle-games', 'rpg-games', 'simulation-games', 'strategy-games'])) {
                        $targetPage = 'games.php';
                    } elseif (in_array($categorySlug, ['programming', 'web-development', 'mobile-development', 'game-development', 'data-science', 'artificial-intelligence', 'devops'])) {
                        $targetPage = 'projects.php';
                    } else {
                        $targetPage = 'books.php';
                    }
                ?>
                <div class="col-md-4 col-lg-2">
                    <a href="<?php echo BASE_PATH; ?>/public/<?php echo $targetPage; ?>?category=<?php echo urlencode($category['slug']); ?>" class="text-decoration-none">
                        <div class="feature-card h-100" style="cursor: pointer; transition: all 0.3s ease;">
                            <div class="feature-icon">
                                <i class="fas fa-<?php 
                                    echo match(strtolower($category['slug'])) {
                                        'programming' => 'code',
                                        'web-development' => 'globe',
                                        'web development' => 'globe',
                                        'mobile-development' => 'mobile-alt',
                                        'mobile development' => 'mobile-alt',
                                        'game-development' => 'gamepad',
                                        'game development' => 'gamepad',
                                        'data-science' => 'chart-bar',
                                        'data science' => 'chart-bar',
                                        'design' => 'palette',
                                        'ui/ux' => 'paint-brush',
                                        'artificial-intelligence' => 'brain',
                                        'artificial intelligence' => 'brain',
                                        'ai' => 'brain',
                                        'machine learning' => 'robot',
                                        'ml' => 'robot',
                                        'devops' => 'cogs',
                                        default => 'book'
                                    };
                                ?>"></i>
                            </div>
                            <h5 class="text-dark"><?php echo sanitize($category['name']); ?></h5>
                            <p class="text-muted small"><?php echo sanitize($category['description']); ?></p>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Items - COMPLETELY FIXED LAYOUT with Rigid Structure -->
    <!-- 
         MAJOR LAYOUT FIXES IMPLEMENTED:
         ✅ FIXED CARD HEIGHTS: All cards now have exactly 450px height (responsive)
         ✅ FIXED IMAGE HEIGHTS: All images exactly 180px height with object-fit: cover
         ✅ FIXED CONTENT AREAS: Each section has predetermined fixed dimensions:
             - Title area: 2.5rem height (exactly 2 lines max)
             - Subtitle area: 1.25rem height (single line with ellipsis)
             - Description area: 4.5rem height (exactly 3 lines max)
             - Rating area: 1.5rem height (always present, shows 'No ratings yet' if empty)
             - Footer area: 2.5rem height (price + cart button)
         ✅ CONTENT OVERFLOW HANDLING: All text properly truncated with ellipsis
         ✅ FALLBACK DESCRIPTIONS: Auto-generated descriptions for empty content
         ✅ RESPONSIVE SCALING: Fixed dimensions scale proportionally on all devices
         ✅ PERFECT ALIGNMENT: No more inconsistent card sizes or layout issues
         
         RESULT: Every card now has identical structure and dimensions regardless of content!
    -->
    <section class="py-5 bg-gradient-light featured-content">
        <div class="container">
            <div class="section-header">
                <h2>Featured Content</h2>
                <p class="lead">Popular books, projects, and games chosen by our community</p>
            </div>
            
            <div class="row g-4">
                <?php foreach (array_slice($featuredItems, 0, 8) as $item): ?>
                <?php
                    // Properly truncate description with intelligent word breaking
                    $description = $item['description'] ?? '';
                    
                    // Provide comprehensive fallback text for empty descriptions
                    if (empty(trim($description))) {
                        $fallbacks = [
                            'book' => [
                                'Comprehensive learning resource packed with practical insights, real-world examples, and actionable strategies. Perfect for beginners and experienced professionals looking to expand their knowledge and develop essential skills.',
                                'In-depth exploration of essential concepts with detailed explanations, practical applications, and step-by-step guidance. Designed to provide thorough understanding and expertise in this important subject area.',
                                'Expert-level content featuring advanced techniques, industry best practices, and proven methodologies. Ideal for professionals seeking to master their field and achieve outstanding results.',
                            ],
                            'project' => [
                                'Comprehensive project featuring practical implementation, detailed documentation, and real-world applications. Perfect for learning essential skills, building professional portfolio, and demonstrating technical expertise.',
                                'Advanced project with innovative solutions, modern technologies, and industry-standard practices. Ideal for developing expertise, showcasing capabilities, and advancing your professional development.',
                                'Professional-level project combining multiple technologies and best practices. Features comprehensive documentation, testing procedures, deployment strategies, and implementation guides.',
                            ],
                            'game' => [
                                'Engaging gaming experience with innovative mechanics, beautiful graphics, and immersive gameplay. Features multiple game modes, achievement systems, progressive difficulty levels, and hours of entertainment.',
                                'Interactive game featuring creative challenges, intuitive controls, and rewarding progression system. Perfect for gamers of all skill levels seeking fun, engagement, and skill development opportunities.',
                                'Captivating game experience with unique mechanics, stunning visuals, and engaging storylines. Designed to provide entertainment while developing various skills and cognitive abilities.',
                            ]
                        ];
                        $typeDescriptions = $fallbacks[$item['type']] ?? $fallbacks['book'];
                        $description = $typeDescriptions[array_rand($typeDescriptions)];
                    }
                    
                    $maxLength = 120; // Increased for better readability
                    if (strlen($description) > $maxLength) {
                        $description = substr($description, 0, $maxLength);
                        // Find last complete word
                        $lastSpace = strrpos($description, ' ');
                        if ($lastSpace !== false) {
                            $description = substr($description, 0, $lastSpace);
                        }
                        $description .= '...';
                    }
                ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card product-card position-relative h-100">
                        <!-- Wishlist Button -->
                        <?php if (isLoggedIn()): ?>
                        <button class="btn btn-wishlist" 
                                data-item-id="<?php echo $item['id']; ?>" 
                                data-item-type="<?php echo $item['type']; ?>">
                            <i class="far fa-heart"></i>
                        </button>
                        <?php endif; ?>
                        
                        <!-- Product Badge -->
                        <span class="badge bg-<?php 
                            echo match($item['type']) {
                                'book' => 'primary',
                                'project' => 'success', 
                                'game' => 'warning',
                                default => 'secondary'
                            };
                        ?> product-badge"><?php echo ucfirst($item['type']); ?></span>
                        
                        <!-- Image -->
                        <a href="<?php echo BASE_PATH; ?>/public/<?php echo $item['type']; ?>_detail.php?id=<?php echo $item['id']; ?>" class="d-block">
                            <img src="<?php 
                                $imagePath = $item['image'] ? 
                                    upload($item['image']) : 
                                    'https://via.placeholder.com/300x400/0d6efd/ffffff?text=' . ucfirst($item['type']);
                                echo $imagePath;
                                $defaultPlaceholder = 'https://via.placeholder.com/300x400/0d6efd/ffffff?text=' . ucfirst($item['type']);
                            ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo sanitize($item['title']); ?>"
                                 onerror="this.src='<?php echo $defaultPlaceholder; ?>'; this.onerror=null;"
                                 loading="lazy">
                        </a>
                        
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">
                                <a href="<?php echo BASE_PATH; ?>/public/<?php echo $item['type']; ?>_detail.php?id=<?php echo $item['id']; ?>" class="text-decoration-none text-dark">
                                    <?php echo sanitize($item['title']); ?>
                                </a>
                            </h5>
                            
                            <p class="text-muted small mb-2">
                                <?php 
                                    // Display appropriate subtitle based on type
                                    if ($item['type'] === 'book') {
                                        echo '<i class="fas fa-user me-1"></i>' . sanitize($item['subtitle']);
                                    } elseif ($item['type'] === 'project') {
                                        echo '<i class="fas fa-code me-1"></i>' . sanitize($item['subtitle']);
                                    } elseif ($item['type'] === 'game') {
                                        echo '<i class="fas fa-gamepad me-1"></i>' . sanitize($item['subtitle']);
                                    }
                                ?>
                            </p>
                            
                            <p class="card-text mb-3"><?php echo sanitize($description); ?></p>
                            
                            <!-- Rating Display - Always present for consistent layout -->
                            <div class="rating-display mb-2">
                                <?php if ($item['rating'] > 0): ?>
                                <div class="d-flex align-items-center">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= round($item['rating']) ? 'text-warning' : 'text-muted'; ?> small"></i>
                                    <?php endfor; ?>
                                    <span class="text-muted small ms-2"><?php echo number_format($item['rating'], 1); ?> (<?php echo number_format($item['total_ratings']); ?>)</span>
                                </div>
                                <?php else: ?>
                                <div class="d-flex align-items-center">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star text-muted small"></i>
                                    <?php endfor; ?>
                                    <span class="text-muted small ms-2">No ratings yet</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-footer-actions mt-auto">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="product-price fw-bold"><?php echo formatPrice($item['price']); ?></span>
                                    <button class="btn btn-primary btn-sm btn-add-cart" 
                                            data-item-id="<?php echo $item['id']; ?>"
                                            data-item-type="<?php echo $item['type']; ?>"
                                            title="Add to Cart">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5">
                <a href="<?php echo BASE_PATH; ?>/public/search.php" class="btn btn-outline-primary btn-lg">
                    View All Content <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Custom Order Services Section -->
    <section class="py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="display-5 fw-bold mb-3">Need Something Custom?</h2>
                    <p class="lead mb-4">Can't find exactly what you're looking for? Let us build it for you! Our experienced developers can create custom projects, educational games, and learning resources tailored to your specific needs.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-code text-warning me-3 fa-lg"></i>
                                <span><strong>Custom Projects:</strong> Web apps, mobile apps, APIs</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-gamepad text-warning me-3 fa-lg"></i>
                                <span><strong>Educational Games:</strong> Interactive learning experiences</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-tools text-warning me-3 fa-lg"></i>
                                <span><strong>Learning Tools:</strong> Custom tutorials & exercises</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-clock text-warning me-3 fa-lg"></i>
                                <span><strong>Quick Turnaround:</strong> Most projects in 1-4 weeks</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <?php if (isLoggedIn()): ?>
                    <div class="d-grid gap-2">
                        <a href="<?php echo BASE_PATH; ?>/public/projects.php" class="btn btn-light btn-lg" onclick="setTimeout(function(){document.querySelector('[data-bs-target=\"#customProjectModal\"]').click()}, 500)">
                            <i class="fas fa-code me-2"></i>Request Custom Project
                        </a>
                        <a href="<?php echo BASE_PATH; ?>/public/games.php" class="btn btn-outline-light btn-lg" onclick="setTimeout(function(){document.querySelector('[data-bs-target=\"#customGameModal\"]').click()}, 500)">
                            <i class="fas fa-gamepad me-2"></i>Request Custom Game
                        </a>
                        <a href="<?php echo BASE_PATH; ?>/user/custom-orders.php" class="btn btn-warning">
                            <i class="fas fa-list me-2"></i>View My Requests
                        </a>
                    </div>
                    <div class="mt-3">
                        <small class="text-white-50">
                            <i class="fas fa-shield-alt me-1"></i>100% Free consultation • No payment until approved
                        </small>
                    </div>
                    <?php else: ?>
                    <div class="text-center">
                        <h4 class="mb-3">Get Started Today!</h4>
                        <a href="<?php echo BASE_PATH; ?>/auth/register.php" class="btn btn-light btn-lg mb-2">
                            <i class="fas fa-user-plus me-2"></i>Sign Up Free
                        </a>
                        <p class="small text-white-75 mb-0">Already have an account? <a href="<?php echo BASE_PATH; ?>/auth/login.php" class="text-white text-decoration-underline">Sign in</a></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5" style="background: white; background-image: url('https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center; background-attachment: fixed; position: relative;">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.95); z-index: 1;"></div>
        <div class="container" style="position: relative; z-index: 2;">
            <div class="section-header">
                <h2>Why Choose <?php echo APP_NAME; ?>?</h2>
                <p class="lead">Everything you need to accelerate your learning</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-book-reader"></i>
                        </div>
                        <h4>Comprehensive Content</h4>
                        <p>Access thousands of books, practical projects, and interactive games covering all major programming topics and technologies.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-hands-helping"></i>
                        </div>
                        <h4>Hands-on Learning</h4>
                        <p>Learn by doing with real-world projects and interactive exercises that reinforce concepts and build practical skills.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4>Community Driven</h4>
                        <p>Join a community of learners, share your progress, and learn from others on the same journey.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo asset('js/main.js'); ?>"></script>
    
    <!-- PWA Install Button -->
    <div id="pwa-install-banner" class="position-fixed bottom-0 start-50 translate-middle-x mb-3" style="display: none; z-index: 1050;">
        <div class="card shadow-lg">
            <div class="card-body text-center">
                <div class="d-flex align-items-center">
                    <i class="fas fa-mobile-alt fa-2x text-primary me-3"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Install Bitversity App</h6>
                        <small class="text-muted">Get the full app experience</small>
                    </div>
                    <div>
                        <button id="pwa-install-btn" class="btn btn-primary btn-sm me-2">Install</button>
                        <button id="pwa-dismiss-btn" class="btn btn-outline-secondary btn-sm">×</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- PWA Service Worker Registration -->
    <script>
        // Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(registration => {
                        console.log('SW registered: ', registration);
                        
                        // Listen for updates
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // Show update available message
                                    showUpdateAvailable();
                                }
                            });
                        });
                    })
                    .catch(registrationError => {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
        }
        
        // PWA Install Prompt
        let deferredPrompt;
        const installBanner = document.getElementById('pwa-install-banner');
        const installBtn = document.getElementById('pwa-install-btn');
        const dismissBtn = document.getElementById('pwa-dismiss-btn');
        
        // Listen for beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Check if user hasn't dismissed the banner recently
            const dismissed = localStorage.getItem('pwa-install-dismissed');
            const dismissedTime = dismissed ? new Date(dismissed) : null;
            const oneDayAgo = new Date(Date.now() - 24 * 60 * 60 * 1000);
            
            if (!dismissed || dismissedTime < oneDayAgo) {
                installBanner.style.display = 'block';
            }
        });
        
        // Install button click
        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const choiceResult = await deferredPrompt.userChoice;
                
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the install prompt');
                }
                
                deferredPrompt = null;
                installBanner.style.display = 'none';
            }
        });
        
        // Dismiss button click
        dismissBtn.addEventListener('click', () => {
            installBanner.style.display = 'none';
            localStorage.setItem('pwa-install-dismissed', new Date().toISOString());
        });
        
        // Show update available notification
        function showUpdateAvailable() {
            const updateDiv = document.createElement('div');
            updateDiv.className = 'alert alert-info alert-dismissible fade show position-fixed';
            updateDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            updateDiv.innerHTML = `
                <i class="fas fa-sync-alt me-2"></i>New version available!
                <button type="button" class="btn btn-sm btn-primary ms-2" onclick="window.location.reload()">Update</button>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(updateDiv);
        }
        
        // Track app install
        window.addEventListener('appinstalled', () => {
            console.log('PWA was installed');
            installBanner.style.display = 'none';
            
            // Track analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', 'pwa_install', {
                    'event_category': 'engagement',
                    'event_label': 'PWA Install'
                });
            }
        });
    
</body>
</html>