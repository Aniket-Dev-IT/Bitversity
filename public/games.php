<?php
require_once __DIR__ . '/../includes/config.php';

$page = max(1, intval($_GET['page'] ?? 1));
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build WHERE conditions
$whereConditions = ['1=1'];
$params = [];

if ($category) {
    $whereConditions[] = "c.slug = ?";
    $params[] = $category;
}

if ($search) {
    $whereConditions[] = "(g.title LIKE ? OR g.description LIKE ? OR g.game_type LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $whereConditions);

// Sort options
$sortOptions = [
    'newest' => 'g.created_at DESC',
    'oldest' => 'g.created_at ASC',
    'price_low' => 'g.price ASC',
    'price_high' => 'g.price DESC',
    'title' => 'g.title ASC',
    'rating' => 'g.rating DESC'
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

// Get total count for pagination
$countSql = "SELECT COUNT(*) as count FROM games g 
             LEFT JOIN categories c ON g.category_id = c.id 
             WHERE $whereClause";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$totalGames = $stmt->fetch()['count'];

$pagination = paginate($page, $totalGames, ITEMS_PER_PAGE);

// Get games
$gamesSql = "SELECT g.*, c.name as category_name, c.slug as category_slug,
                    COALESCE(g.rating, 0) as rating,
                    COALESCE(g.total_ratings, 0) as total_ratings,
                    COALESCE(g.views_count, 0) as views_count,
                    COALESCE(g.plays_count, 0) as plays_count,
                    COALESCE(g.downloads_count, 0) as downloads_count,
                    COALESCE(g.favorites_count, 0) as favorites_count
             FROM games g 
             LEFT JOIN categories c ON g.category_id = c.id 
             WHERE $whereClause
             ORDER BY $orderBy
             LIMIT {$pagination['items_per_page']} OFFSET {$pagination['offset']}";
$stmt = $db->prepare($gamesSql);
$stmt->execute($params);
$games = $stmt->fetchAll();

// Get categories for filter
$categoriesStmt = $db->prepare("SELECT * FROM categories ORDER BY name");
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll();

// Check user wishlist if logged in
$userWishlist = [];
if (isLoggedIn()) {
    $stmt = $db->prepare("SELECT item_id FROM wishlist WHERE user_id = ? AND item_type = 'game'");
    $stmt->execute([$_SESSION['user_id']]);
    $userWishlist = array_column($stmt->fetchAll(), 'item_id');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Games - <?php echo APP_NAME; ?></title>
    <meta name="description" content="Discover educational programming games. Learn coding concepts through interactive gameplay and challenges.">
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
</head>
<body data-logged-in="<?php echo isLoggedIn() ? 'true' : 'false'; ?>">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <!-- Page Header -->
    <div class="page-header" style="background-image: url('https://images.unsplash.com/photo-1493711662062-fa541adb3fc8?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center;">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(102, 126, 234, 0.25); z-index: 1;"></div>
        <div class="container" style="position: relative; z-index: 2;">
            <div class="row align-items-center">
                <div class="col-12">
                    <div class="text-center text-white">
                        <h1 class="display-4 mb-3" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                            <i class="fas fa-gamepad me-3"></i>Interactive Games
                        </h1>
                        <p class="lead mb-4" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                            <?php if ($search): ?>
                                Search results for "<?php echo sanitize($search); ?>" - 
                            <?php endif; ?>
                            Play <?php echo number_format($totalGames); ?> engaging educational games to learn coding concepts interactively
                        </p>
                        <?php if (isLoggedIn()): ?>
                        <div class="mt-3">
                            <a href="<?php echo BASE_PATH; ?>/request-game.php" class="btn btn-light btn-lg">
                                <i class="fas fa-plus me-2"></i>Request Custom Game
                            </a>
                            <p class="text-white mt-2" style="opacity: 0.9; font-size: 0.9rem;">Need a specific educational game? Let us create it for you!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="container py-4">
            <!-- Filters Toggle -->
            <div class="row mb-4">
                <div class="col-12 text-end">
                    <button class="btn btn-outline-primary d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                        <i class="fas fa-filter me-2"></i>Filters
                    </button>
                </div>
            </div>

            <div class="row">
                <!-- Sidebar Filters -->
                <div class="col-lg-3 mb-4">
                    <div class="collapse d-lg-block" id="filtersCollapse">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
                            </div>
                            <div class="card-body">
                                <!-- Search -->
                                <form method="GET" class="mb-4">
                                    <div class="mb-3">
                                        <label for="search" class="form-label">Search Games</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo sanitize($search); ?>" placeholder="Title, genre, or keyword...">
                                        <input type="hidden" name="category" value="<?php echo sanitize($category); ?>">
                                        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm w-100">
                                        <i class="fas fa-search me-2"></i>Search
                                    </button>
                                </form>

                                <!-- Categories -->
                                <div class="mb-4">
                                    <h6 class="fw-bold">Categories</h6>
                                    <div class="list-group list-group-flush">
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => '', 'page' => 1])); ?>" 
                                           class="list-group-item list-group-item-action <?php echo !$category ? 'active' : ''; ?>">
                                            All Categories
                                        </a>
                                        <?php foreach ($categories as $cat): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => $cat['slug'], 'page' => 1])); ?>" 
                                           class="list-group-item list-group-item-action <?php echo $category === $cat['slug'] ? 'active' : ''; ?>">
                                            <?php echo sanitize($cat['name']); ?>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Clear Filters -->
                                <?php if ($category || $search): ?>
                                <a href="games.php" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="fas fa-times me-2"></i>Clear Filters
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Sort and View Options -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="d-flex align-items-center">
                            <label for="sort" class="me-2">Sort by:</label>
                            <select class="form-select" id="sort" name="sort" onchange="updateSort(this.value)" style="width: auto;">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title A-Z</option>
                                <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price Low to High</option>
                                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price High to Low</option>
                            </select>
                        </div>
                        
                        <div class="text-muted">
                            Showing <?php echo $pagination['offset'] + 1; ?>-<?php echo min($pagination['offset'] + $pagination['items_per_page'], $totalGames); ?> of <?php echo number_format($totalGames); ?> games
                        </div>
                    </div>

                    <!-- Games Grid -->
                    <?php if (empty($games)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-gamepad fa-4x text-muted mb-3"></i>
                        <h3>No games found</h3>
                        <p class="text-muted">Try adjusting your search or filters</p>
                        <a href="games.php" class="btn btn-primary">View All Games</a>
                    </div>
                    <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($games as $game): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 game-card shadow-sm border-0 position-relative overflow-hidden" 
                                 style="transition: all 0.3s ease;" 
                                 onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 12px 25px rgba(0,0,0,0.15)';"
                                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.08)';">
                                
                                <!-- Game Image -->
                                <div style="height: 200px; overflow: hidden; position: relative;">
                                    <img src="<?php echo $game['image'] ? sanitize($game['image']) : 'https://images.unsplash.com/photo-1493711662062-fa541adb3fc8?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80'; ?>" 
                                         class="card-img-top" alt="<?php echo sanitize($game['title']); ?>"
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                    
                                    <!-- Category Badge -->
                                    <?php if ($game['category_name']): ?>
                                    <span class="badge bg-primary position-absolute top-0 start-0 m-2">
                                        <?php echo sanitize($game['category_name']); ?>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <!-- Quick View Button -->
                                    <button class="btn btn-light btn-sm position-absolute top-0 end-0 m-2 quick-view-btn" 
                                            data-game-id="<?php echo $game['id']; ?>"
                                            title="Quick View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <!-- Wishlist Button -->
                                    <?php if (isLoggedIn()): ?>
                                    <button class="btn btn-light btn-sm position-absolute top-0 end-0 me-5 m-2 wishlist-btn <?php echo in_array($game['id'], $userWishlist) ? 'active' : ''; ?>" 
                                            data-item-id="<?php echo $game['id']; ?>" 
                                            data-item-type="game"
                                            title="<?php echo in_array($game['id'], $userWishlist) ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title mb-2">
                                        <a href="<?php echo BASE_PATH; ?>/public/game_detail.php?id=<?php echo $game['id']; ?>" 
                                           class="text-decoration-none text-dark">
                                            <?php echo sanitize($game['title']); ?>
                                        </a>
                                    </h5>
                                    
                                    <p class="card-text text-muted flex-grow-1" style="font-size: 0.9rem;">
                                        <?php echo sanitize(substr($game['description'], 0, 120)); ?>...
                                    </p>
                                    
                                    <!-- Game Stats -->
                                    <div class="d-flex justify-content-between align-items-center mb-3 small text-muted">
                                        <span title="Rating">
                                            <i class="fas fa-star text-warning"></i>
                                            <?php echo number_format($game['rating'], 1); ?>
                                            (<?php echo number_format($game['total_ratings']); ?>)
                                        </span>
                                        <span title="Plays">
                                            <i class="fas fa-play"></i>
                                            <?php echo number_format($game['plays_count']); ?>
                                        </span>
                                        <span title="Favorites">
                                            <i class="fas fa-heart"></i>
                                            <?php echo number_format($game['favorites_count']); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Price and Action -->
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <?php if ($game['price'] > 0): ?>
                                                <h5 class="text-primary mb-0">
                                                    $<?php echo number_format($game['price'], 2); ?>
                                                </h5>
                                            <?php else: ?>
                                                <h5 class="text-success mb-0">Free</h5>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (isLoggedIn()): ?>
                                        <?php if ($game['price'] > 0): ?>
                                        <button class="btn btn-primary btn-sm add-to-cart" 
                                                data-item-id="<?php echo $game['id']; ?>" 
                                                data-item-type="game"
                                                style="position: relative; z-index: 10;">
                                            <i class="fas fa-cart-plus me-1"></i>Add to Cart
                                        </button>
                                        <?php else: ?>
                                        <a href="<?php echo BASE_PATH; ?>/public/game_detail.php?id=<?php echo $game['id']; ?>" 
                                           class="btn btn-success btn-sm"
                                           style="position: relative; z-index: 10;">
                                            <i class="fas fa-play me-1"></i>Play Now
                                        </a>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <a href="<?php echo BASE_PATH; ?>/auth/login.php" 
                                           class="btn btn-primary btn-sm"
                                           style="position: relative; z-index: 10;">
                                            <i class="fas fa-sign-in-alt me-1"></i>Login to Play
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                    <nav aria-label="Games pagination" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <!-- Previous -->
                            <?php if ($pagination['has_prev']): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($pagination['total_pages'], $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <!-- Next -->
                            <?php if ($pagination['has_next']): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Next <i class="fas fa-chevron-right"></i>
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
    </div>

    <!-- Quick View Modal -->
    <div class="modal fade" id="quickViewModal" tabindex="-1" aria-labelledby="quickViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quickViewModalLabel">Game Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="quickViewContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/main.js'); ?>"></script>

    <script>
        // Sort functionality
        function updateSort(sortValue) {
            const url = new URL(window.location);
            url.searchParams.set('sort', sortValue);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        // Quick view functionality
        document.addEventListener('DOMContentLoaded', function() {
            const quickViewButtons = document.querySelectorAll('.quick-view-btn');
            
            quickViewButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const gameId = this.dataset.gameId;
                    
                    // Load quick view content
                    fetch(`<?php echo BASE_PATH; ?>/api/quickview.php?type=game&id=${gameId}`)
                        .then(response => response.text())
                        .then(html => {
                            document.getElementById('quickViewContent').innerHTML = html;
                            const modal = new bootstrap.Modal(document.getElementById('quickViewModal'));
                            modal.show();
                        })
                        .catch(error => {
                            console.error('Error loading quick view:', error);
                            document.getElementById('quickViewContent').innerHTML = '<p class="text-danger">Error loading content</p>';
                        });
                });
            });
        });
    </script>
</body>
</html>