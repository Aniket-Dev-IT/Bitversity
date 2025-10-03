<?php
require_once __DIR__ . '/../includes/config.php';

$pageTitle = 'Search Results';

// Get search parameters
$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all'; // all, books, projects, games
$category = $_GET['category'] ?? '';
$sortBy = $_GET['sort'] ?? 'relevance'; // relevance, price_low, price_high, name, newest
$minPrice = floatval($_GET['min_price'] ?? 0);
$maxPrice = floatval($_GET['max_price'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Get categories for filter
$categoriesStmt = $db->prepare("SELECT * FROM categories ORDER BY name");
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll();

// Build search query
$results = [];
$totalResults = 0;
$searchExecuted = false;

if (!empty($query) || !empty($category) || ($minPrice > 0) || ($maxPrice > 0) || ($type !== 'all')) {
    $searchExecuted = true;
    
    // Log search query for analytics
    if (isLoggedIn() && !empty($query)) {
        logSearchQuery($query, $type, $_SESSION['user_id']);
    }
    
    // Build WHERE conditions
    $conditions = [];
    $params = [];
    
    if (!empty($query)) {
        $searchTerm = '%' . $query . '%';
        // Different search fields for each table - will be handled per table
    }
    
    if (!empty($category)) {
        $conditions[] = "category_id = ?";
        $params[] = $category;
    }
    
    if ($minPrice > 0) {
        $conditions[] = "price >= ?";
        $params[] = $minPrice;
    }
    
    if ($maxPrice > 0) {
        $conditions[] = "price <= ?";
        $params[] = $maxPrice;
    }
    
    $conditions[] = "is_active = 1";
    
    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : 'WHERE is_active = 1';
    
    // Build ORDER BY clause
    $orderBy = match($sortBy) {
        'price_low' => 'price ASC',
        'price_high' => 'price DESC',
        'name' => 'title ASC',
        'newest' => 'created_at DESC',
        default => 'created_at DESC'
    };
    
    // Build individual search queries for each table
    $searchQueries = [];
    $allResults = [];
    
    if ($type === 'all' || $type === 'books') {
        $bookConditions = [];
        $bookParams = [];
        
        if (!empty($query)) {
            $bookConditions[] = "(title LIKE ? OR description LIKE ? OR author LIKE ?)";
            $bookParams = array_merge($bookParams, [$searchTerm, $searchTerm, $searchTerm]);
        }
        
        if (!empty($category)) {
            $bookConditions[] = "category_id = ?";
            $bookParams[] = $category;
        }
        
        if ($minPrice > 0) {
            $bookConditions[] = "price >= ?";
            $bookParams[] = $minPrice;
        }
        
        if ($maxPrice > 0) {
            $bookConditions[] = "price <= ?";
            $bookParams[] = $maxPrice;
        }
        
        $bookConditions[] = "is_active = 1";
        $bookWhere = implode(' AND ', $bookConditions);
        
        $bookQuery = "
            SELECT 'book' as content_type, id, title, author as subtitle, price, 
                   cover_image as image, description, created_at, category_id
            FROM books 
            WHERE $bookWhere
            ORDER BY $orderBy
        ";
        
        try {
            $stmt = $db->prepare($bookQuery);
            $stmt->execute($bookParams);
            $bookResults = $stmt->fetchAll();
            $allResults = array_merge($allResults, $bookResults);
        } catch (PDOException $e) {
            error_log("Books search error: " . $e->getMessage());
        }
    }
    
    if ($type === 'all' || $type === 'projects') {
        $projectConditions = [];
        $projectParams = [];
        
        if (!empty($query)) {
            $projectConditions[] = "(title LIKE ? OR description LIKE ?)";
            $projectParams = array_merge($projectParams, [$searchTerm, $searchTerm]);
        }
        
        if (!empty($category)) {
            $projectConditions[] = "category_id = ?";
            $projectParams[] = $category;
        }
        
        if ($minPrice > 0) {
            $projectConditions[] = "price >= ?";
            $projectParams[] = $minPrice;
        }
        
        if ($maxPrice > 0) {
            $projectConditions[] = "price <= ?";
            $projectParams[] = $maxPrice;
        }
        
        $projectConditions[] = "is_active = 1";
        $projectWhere = implode(' AND ', $projectConditions);
        
        $projectQuery = "
            SELECT 'project' as content_type, id, title, difficulty_level as subtitle, price,
                   cover_image as image, description, created_at, category_id
            FROM projects 
            WHERE $projectWhere
            ORDER BY $orderBy
        ";
        
        try {
            $stmt = $db->prepare($projectQuery);
            $stmt->execute($projectParams);
            $projectResults = $stmt->fetchAll();
            $allResults = array_merge($allResults, $projectResults);
        } catch (PDOException $e) {
            error_log("Projects search error: " . $e->getMessage());
        }
    }
    
    if ($type === 'all' || $type === 'games') {
        $gameConditions = [];
        $gameParams = [];
        
        if (!empty($query)) {
            $gameConditions[] = "(title LIKE ? OR description LIKE ? OR genre LIKE ?)";
            $gameParams = array_merge($gameParams, [$searchTerm, $searchTerm, $searchTerm]);
        }
        
        if (!empty($category)) {
            $gameConditions[] = "category_id = ?";
            $gameParams[] = $category;
        }
        
        if ($minPrice > 0) {
            $gameConditions[] = "price >= ?";
            $gameParams[] = $minPrice;
        }
        
        if ($maxPrice > 0) {
            $gameConditions[] = "price <= ?";
            $gameParams[] = $maxPrice;
        }
        
        $gameConditions[] = "is_active = 1";
        $gameWhere = implode(' AND ', $gameConditions);
        
        $gameQuery = "
            SELECT 'game' as content_type, id, title, genre as subtitle, price,
                   thumbnail as image, description, created_at, category_id
            FROM games 
            WHERE $gameWhere
            ORDER BY $orderBy
        ";
        
        try {
            $stmt = $db->prepare($gameQuery);
            $stmt->execute($gameParams);
            $gameResults = $stmt->fetchAll();
            $allResults = array_merge($allResults, $gameResults);
        } catch (PDOException $e) {
            error_log("Games search error: " . $e->getMessage());
        }
    }
    
    // Apply pagination to combined results
    $totalResults = count($allResults);
    $results = array_slice($allResults, $offset, $perPage);
}

// Calculate pagination
$totalPages = ceil($totalResults / $perPage);

// Get price range for filter
$priceRangeStmt = $db->query("
    SELECT MIN(min_price) as min_price, MAX(max_price) as max_price FROM (
        SELECT MIN(price) as min_price, MAX(price) as max_price FROM books WHERE is_active = 1
        UNION ALL
        SELECT MIN(price) as min_price, MAX(price) as max_price FROM projects WHERE is_active = 1
        UNION ALL
        SELECT MIN(price) as min_price, MAX(price) as max_price FROM games WHERE is_active = 1
    ) as combined_prices
");
$priceRange = $priceRangeStmt->fetch();

// Helper function to log search queries
function logSearchQuery($query, $type, $userId = null) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO search_logs (search_query, search_type, user_id, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$query, $type, $userId]);
    } catch (PDOException $e) {
        error_log("Search logging error: " . $e->getMessage());
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Hero Search Section -->
<div class="position-relative text-white py-5 mb-4" style="background-image: url('https://images.unsplash.com/photo-1481627834876-b7833e8f5570?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center; background-repeat: no-repeat;">
    <!-- Dark overlay for better text readability -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.4); z-index: 1;"></div>
    
    <div class="container" style="position: relative; z-index: 2;">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h1 class="display-5 fw-bold mb-3" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Find Your Perfect Learning Resource</h1>
                <p class="lead mb-4" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">Search through thousands of books, projects, and games</p>
                
                <!-- Enhanced Search Bar -->
                <form method="GET" class="d-flex gap-2 justify-content-center mb-4">
                    <div class="flex-grow-1" style="max-width: 500px;">
                        <input type="text" class="form-control form-control-lg shadow" 
                               name="q" 
                               value="<?php echo sanitize($query); ?>" 
                               placeholder="What do you want to learn today?"
                               autocomplete="off"
                               style="border: none; font-size: 1.1rem; padding: 15px 20px;">
                    </div>
                    <button type="submit" class="btn btn-warning btn-lg px-4 shadow" style="font-weight: 600;">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </form>
                
                <!-- Quick Filters -->
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="?type=books" class="btn <?php echo $type === 'books' ? 'btn-light' : 'btn-outline-light'; ?> shadow" style="padding: 8px 20px; font-weight: 500; border-width: 2px;">
                        <i class="fas fa-book me-2"></i>Books
                    </a>
                    <a href="?type=projects" class="btn <?php echo $type === 'projects' ? 'btn-light' : 'btn-outline-light'; ?> shadow" style="padding: 8px 20px; font-weight: 500; border-width: 2px;">
                        <i class="fas fa-code me-2"></i>Projects
                    </a>
                    <a href="?type=games" class="btn <?php echo $type === 'games' ? 'btn-light' : 'btn-outline-light'; ?> shadow" style="padding: 8px 20px; font-weight: 500; border-width: 2px;">
                        <i class="fas fa-gamepad me-2"></i>Games
                    </a>
                    <?php if ($searchExecuted): ?>
                    <a href="search.php" class="btn btn-outline-light shadow" style="padding: 8px 20px; font-weight: 500; border-width: 2px;">
                        <i class="fas fa-times me-2"></i>Clear All
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container pb-4">
    <!-- Results Header -->
    <?php if ($searchExecuted): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1">
                <?php if (!empty($query)): ?>
                    Results for "<?php echo sanitize($query); ?>"
                <?php else: ?>
                    Browse <?php echo $type !== 'all' ? ucfirst($type) : 'All Content'; ?>
                <?php endif; ?>
            </h3>
            <p class="text-muted mb-0">
                <?php if ($totalResults > 0): ?>
                    <?php echo number_format($totalResults); ?> item<?php echo $totalResults !== 1 ? 's' : ''; ?> found
                <?php else: ?>
                    No results found
                <?php endif; ?>
            </p>
        </div>
        
        <?php if ($totalResults > 0): ?>
        <div class="d-flex align-items-center gap-3">
            <!-- Simple Sort -->
            <select class="form-select" id="sortSelect" style="width: auto;">
                <option value="relevance" <?php echo $sortBy === 'relevance' ? 'selected' : ''; ?>>Most Relevant</option>
                <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>>Lowest Price</option>
                <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>A to Z</option>
            </select>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Main Content Area -->
    <div class="row justify-content-center">
        <div class="col-12">
            <?php if (!$searchExecuted): ?>
                <!-- Popular Topics -->
                <div class="text-center py-4">
                    <h4 class="mb-4">Popular Topics</h4>
                    <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                        <a href="?q=javascript" class="btn btn-outline-primary">JavaScript</a>
                        <a href="?q=python" class="btn btn-outline-primary">Python</a>
                        <a href="?q=react" class="btn btn-outline-primary">React</a>
                        <a href="?q=machine+learning" class="btn btn-outline-primary">Machine Learning</a>
                        <a href="?q=web+development" class="btn btn-outline-primary">Web Development</a>
                        <a href="?q=data+science" class="btn btn-outline-primary">Data Science</a>
                    </div>
                    
                    <p class="text-muted">Or browse by content type using the buttons above</p>
                </div>

            <?php elseif (empty($results)): ?>
                <!-- No Results -->
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-search text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                    <h4>No Results Found</h4>
                    <p class="text-muted mb-4">
                        We couldn't find anything matching your search. Try different keywords or browse by content type.
                    </p>
                    
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="?type=books" class="btn btn-outline-primary">
                            <i class="fas fa-book me-1"></i>Browse Books
                        </a>
                        <a href="?type=projects" class="btn btn-outline-primary">
                            <i class="fas fa-code me-1"></i>View Projects
                        </a>
                        <a href="?type=games" class="btn btn-outline-primary">
                            <i class="fas fa-gamepad me-1"></i>Play Games
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Search Results Grid -->
                <div class="row g-4">
                    <?php foreach ($results as $item): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="card h-100 shadow-sm border-0">
                                <!-- Content Type Badge -->
                                <span class="badge position-absolute top-0 start-0 m-2 bg-<?php 
                                    echo match($item['content_type']) {
                                        'book' => 'primary',
                                        'project' => 'success', 
                                        'game' => 'warning',
                                        default => 'secondary'
                                    };
                                ?>" style="z-index: 5;"><?php echo ucfirst($item['content_type']); ?></span>
                                
                                <!-- Wishlist Button -->
                                <?php if (isLoggedIn()): ?>
                                <button class="btn btn-wishlist position-absolute top-0 end-0 m-2" 
                                        data-item-id="<?php echo $item['id']; ?>" 
                                        data-item-type="<?php echo $item['content_type']; ?>"
                                        style="z-index: 5; background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 40px; height: 40px;">
                                    <i class="far fa-heart text-danger"></i>
                                </button>
                                <?php endif; ?>
                                
                                <!-- Image -->
                                <div class="position-relative">
                                    <a href="<?php echo BASE_PATH; ?>/public/<?php echo $item['content_type']; ?>_detail.php?id=<?php echo $item['id']; ?>">
                                        <img src="<?php 
                                            if ($item['image']) {
                                                if ($item['content_type'] === 'game') {
                                                    $imagePath = upload('games/' . $item['image']);
                                                } else {
                                                    $imagePath = upload($item['content_type'] . 's/' . $item['image']);
                                                }
                                            } else {
                                                $imagePath = 'https://via.placeholder.com/300x400/0d6efd/ffffff?text=' . urlencode(substr($item['title'], 0, 15));
                                            }
                                            echo $imagePath;
                                        ?>"
                                             class="card-img-top" 
                                             alt="<?php echo sanitize($item['title']); ?>"
                                             style="height: 220px; object-fit: cover;">
                                    </a>
                                </div>
                                
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title mb-2" style="line-height: 1.3;">
                                        <a href="<?php echo BASE_PATH; ?>/public/<?php echo $item['content_type']; ?>_detail.php?id=<?php echo $item['id']; ?>" 
                                           class="text-decoration-none text-dark" title="<?php echo sanitize($item['title']); ?>">
                                            <?php echo sanitize(substr($item['title'], 0, 45)) . (strlen($item['title']) > 45 ? '...' : ''); ?>
                                        </a>
                                    </h6>
                                    
                                    <p class="text-muted small mb-2"><?php echo sanitize($item['subtitle']); ?></p>
                                    
                                    <p class="card-text text-muted small flex-grow-1" style="font-size: 0.85rem;">
                                        <?php echo sanitize(substr($item['description'], 0, 80)) . (strlen($item['description']) > 80 ? '...' : ''); ?>
                                    </p>
                                    
                                    <!-- Price and Action -->
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <div class="fw-bold <?php echo $item['price'] > 0 ? 'text-primary' : 'text-success'; ?>">
                                            <?php echo $item['price'] > 0 ? formatPrice($item['price']) : 'Free'; ?>
                                        </div>
                                        
                                        <?php if (isLoggedIn()): ?>
                                        <button class="btn btn-primary btn-sm add-to-cart" 
                                                data-item-id="<?php echo $item['id']; ?>"
                                                data-item-type="<?php echo $item['content_type']; ?>">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                        <?php else: ?>
                                        <a href="<?php echo BASE_PATH; ?>/auth/login.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-sign-in-alt"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Search results pagination" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo buildSearchUrl($query, $type, $category, $sortBy, $minPrice, $maxPrice, $page - 1); ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo buildSearchUrl($query, $type, $category, $sortBy, $minPrice, $maxPrice, 1); ?>">1</a>
                                </li>
                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo buildSearchUrl($query, $type, $category, $sortBy, $minPrice, $maxPrice, $i); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo buildSearchUrl($query, $type, $category, $sortBy, $minPrice, $maxPrice, $totalPages); ?>">
                                        <?php echo $totalPages; ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo buildSearchUrl($query, $type, $category, $sortBy, $minPrice, $maxPrice, $page + 1); ?>">
                                        Next
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?php echo asset('js/main.js'); ?>"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit when sort changes
    document.getElementById('sortSelect')?.addEventListener('change', function() {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('sort', this.value);
        urlParams.set('page', '1');
        window.location.href = '?' + urlParams.toString();
    });
    
    // Focus search input on load if no search was performed
    <?php if (!$searchExecuted): ?>
    const searchInput = document.querySelector('input[name="q"]');
    if (searchInput) {
        searchInput.focus();
    }
    <?php endif; ?>
});
</script>

<?php
// Helper function to build search URLs for pagination
function buildSearchUrl($query, $type, $category, $sortBy, $minPrice, $maxPrice, $page) {
    $params = [];
    
    if (!empty($query)) $params['q'] = $query;
    if ($type !== 'all') $params['type'] = $type;
    if (!empty($category)) $params['category'] = $category;
    if ($sortBy !== 'relevance') $params['sort'] = $sortBy;
    if ($minPrice > 0) $params['min_price'] = $minPrice;
    if ($maxPrice > 0) $params['max_price'] = $maxPrice;
    if ($page > 1) $params['page'] = $page;
    
    return '?' . http_build_query($params);
}
?>