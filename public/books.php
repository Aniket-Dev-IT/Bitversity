<?php
require_once __DIR__ . '/../includes/config.php';

// Get filters from URL
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));

// Build WHERE clause
$whereConditions = ["b.is_active = 1"];
$params = [];

if ($category) {
    $whereConditions[] = "c.slug = ?";
    $params[] = $category;
}

if ($search) {
    $whereConditions[] = "(b.title LIKE ? OR b.author LIKE ? OR b.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $whereConditions);

// Sort options
$sortOptions = [
    'newest' => 'b.created_at DESC',
    'oldest' => 'b.created_at ASC',
    'price_low' => 'b.price ASC',
    'price_high' => 'b.price DESC',
    'title' => 'b.title ASC',
    'author' => 'b.author ASC'
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

// Get total count for pagination
$countSql = "SELECT COUNT(*) as count FROM books b 
             LEFT JOIN categories c ON b.category_id = c.id 
             WHERE $whereClause";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$totalBooks = $stmt->fetch()['count'];

$pagination = paginate($page, $totalBooks, ITEMS_PER_PAGE);

// Get books
$booksSql = "SELECT b.*, c.name as category_name, c.slug as category_slug,
                    COALESCE(b.rating, 0) as rating,
                    COALESCE(b.total_ratings, 0) as total_ratings,
                    COALESCE(b.views_count, 0) as views_count,
                    COALESCE(b.downloads_count, 0) as downloads_count,
                    COALESCE(b.favorites_count, 0) as favorites_count
             FROM books b 
             LEFT JOIN categories c ON b.category_id = c.id 
             WHERE $whereClause
             ORDER BY $orderBy
             LIMIT {$pagination['items_per_page']} OFFSET {$pagination['offset']}";
$stmt = $db->prepare($booksSql);
$stmt->execute($params);
$books = $stmt->fetchAll();

// Get categories for filter
$categoriesStmt = $db->prepare("SELECT * FROM categories ORDER BY name");
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll();

// Check user wishlist if logged in
$userWishlist = [];
if (isLoggedIn()) {
    $stmt = $db->prepare("SELECT item_id FROM wishlist WHERE user_id = ? AND item_type = 'book'");
    $stmt->execute([$_SESSION['user_id']]);
    $userWishlist = array_column($stmt->fetchAll(), 'item_id');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books - <?php echo APP_NAME; ?></title>
    <meta name="description" content="Browse our extensive collection of programming and technology books. Learn from industry experts and advance your skills.">
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
</head>
<body data-logged-in="<?php echo isLoggedIn() ? 'true' : 'false'; ?>">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <!-- Page Header -->
    <div class="page-header" style="background-image: url('https://images.unsplash.com/photo-1481627834876-b7833e8f5570?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center;">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(102, 126, 234, 0.15); z-index: 1;"></div>
        <div class="container" style="position: relative; z-index: 2;">
            <div class="row align-items-center">
                <div class="col-12">
                    <div class="text-center text-white">
                        <h1 class="display-4 mb-3" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                            <i class="fas fa-book me-3"></i>Books Library
                        </h1>
                        <p class="lead mb-0" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                            <?php if ($search): ?>
                                Search results for "<?php echo sanitize($search); ?>" - 
                            <?php endif; ?>
                            Discover <?php echo number_format($totalBooks); ?> carefully curated books to advance your programming and technology skills
                        </p>
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
                                        <label for="search" class="form-label">Search Books</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo sanitize($search); ?>" placeholder="Title, author, or keyword...">
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
                                <a href="books.php" class="btn btn-outline-secondary btn-sm w-100">
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
                                <option value="author" <?php echo $sort === 'author' ? 'selected' : ''; ?>>Author A-Z</option>
                                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price Low to High</option>
                                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price High to Low</option>
                            </select>
                        </div>
                        
                        <div class="text-muted">
                            Showing <?php echo $pagination['offset'] + 1; ?>-<?php echo min($pagination['offset'] + $pagination['items_per_page'], $totalBooks); ?> of <?php echo number_format($totalBooks); ?> books
                        </div>
                    </div>

                    <!-- Books Grid -->
                    <?php if (empty($books)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-book fa-4x text-muted mb-3"></i>
                        <h3>No books found</h3>
                        <p class="text-muted">Try adjusting your search or filters</p>
                        <a href="books.php" class="btn btn-primary">View All Books</a>
                    </div>
                    <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($books as $book): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card product-card h-100 position-relative shadow-sm border-0">
                                <!-- Wishlist Button -->
                                <?php if (isLoggedIn()): ?>
                                <button class="btn btn-wishlist <?php echo in_array($book['id'], $userWishlist) ? 'active' : ''; ?>" 
                                        data-item-id="<?php echo $book['id']; ?>" 
                                        data-item-type="book"
                                        title="<?php echo in_array($book['id'], $userWishlist) ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                    <i class="<?php echo in_array($book['id'], $userWishlist) ? 'fas' : 'far'; ?> fa-heart"></i>
                                </button>
                                <?php endif; ?>
                                
                                <!-- Category Badge -->
                                <?php if ($book['category_name']): ?>
                                <span class="badge bg-primary position-absolute top-0 start-0 m-2" style="z-index: 5;">
                                    <?php echo sanitize($book['category_name']); ?>
                                </span>
                                <?php endif; ?>
                                
                                <!-- Book Cover -->
                                <div class="product-image-wrapper">
                                    <img src="<?php 
                                        if ($book['cover_image']) {
                                            echo (strpos($book['cover_image'], 'http') === 0) ? 
                                                $book['cover_image'] : 
                                                upload($book['cover_image']);
                                        } else {
                                            echo 'https://via.placeholder.com/300x400/0d6efd/ffffff?text=' . urlencode(substr($book['title'], 0, 20));
                                        }
                                    ?>"
                                         class="card-img-top product-image" 
                                         alt="<?php echo sanitize($book['title']); ?>"
                                         style="height: 280px; object-fit: cover; cursor: pointer;"
                                         onclick="window.location.href='book_detail.php?id=<?php echo $book['id']; ?>'">
                                    
                                    <!-- Quick actions overlay -->
                                    <div class="product-overlay">
                                        <div class="d-flex gap-2">
                                            <a href="book_detail.php?id=<?php echo $book['id']; ?>" class="btn btn-primary btn-sm" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (isLoggedIn()): ?>
                                            <button class="btn btn-outline-light btn-sm btn-wishlist-toggle" 
                                                    data-item-id="<?php echo $book['id']; ?>" 
                                                    data-item-type="book"
                                                    title="Add to Wishlist">
                                                <i class="<?php echo in_array($book['id'], $userWishlist) ? 'fas' : 'far'; ?> fa-heart"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-body d-flex flex-column">
                                    <!-- Book Title and Author -->
                                    <h5 class="card-title text-truncate" title="<?php echo sanitize($book['title']); ?>">
                                        <a href="book_detail.php?id=<?php echo $book['id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo sanitize($book['title']); ?>
                                        </a>
                                    </h5>
                                    <p class="text-muted small mb-2">by <?php echo sanitize($book['author']); ?></p>
                                    
                                    <!-- Description -->
                                    <p class="card-text text-muted small flex-grow-1" style="font-size: 0.85rem; line-height: 1.4;">
                                        <?php echo sanitize(substr($book['description'] ?? '', 0, 100)) . (strlen($book['description'] ?? '') > 100 ? '...' : ''); ?>
                                    </p>
                                    
                                    <!-- Rating and Stats -->
                                    <?php if ($book['rating'] > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="text-warning">
                                            <?php 
                                            $rating = (float)$book['rating'];
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <i class="<?php echo $i <= round($rating) ? 'fas' : 'far'; ?> fa-star"></i>
                                            <?php endfor; ?>
                                            <span class="text-muted ms-1"><?php echo number_format($rating, 1); ?> (<?php echo $book['total_ratings']; ?>)</span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-download me-1"></i><?php echo number_format($book['downloads_count']); ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Price and Actions -->
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <?php if ($book['price'] > 0): ?>
                                                <h5 class="text-primary mb-0">
                                                    $<?php echo number_format($book['price'], 2); ?>
                                                </h5>
                                            <?php else: ?>
                                                <h5 class="text-success mb-0">Free</h5>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (isLoggedIn()): ?>
                                        <button class="btn btn-primary btn-sm add-to-cart" 
                                                data-item-id="<?php echo $book['id']; ?>" 
                                                data-item-type="book"
                                                style="position: relative; z-index: 10;">
                                            <i class="fas fa-cart-plus me-1"></i>Add to Cart
                                        </button>
                                        <?php else: ?>
                                        <a href="<?php echo BASE_PATH; ?>/auth/login.php" 
                                           class="btn btn-primary btn-sm"
                                           style="position: relative; z-index: 10;">
                                            <i class="fas fa-sign-in-alt me-1"></i>Login to Purchase
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
                    <nav aria-label="Books pagination" class="mt-5">
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


    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/main.js'); ?>"></script>
    <script>
        function updateSort(sortValue) {
            const url = new URL(window.location);
            url.searchParams.set('sort', sortValue);
            url.searchParams.set('page', '1');
            window.location = url;
        }


        // Enhanced wishlist functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Handle wishlist buttons
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-wishlist, .btn-wishlist-toggle')) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const button = e.target.closest('.btn-wishlist, .btn-wishlist-toggle');
                    const itemId = button.dataset.itemId;
                    const itemType = button.dataset.itemType;
                    
                    if (!document.body.dataset.loggedIn === 'true') {
                        window.location.href = '<?php echo BASE_PATH; ?>/auth/login.php';
                        return;
                    }
                    
                    toggleWishlist(itemId, itemType, button);
                }
            });
        });

        function toggleWishlist(itemId, itemType, button) {
            const icon = button.querySelector('i');
            const isActive = button.classList.contains('active');
            
            // Optimistic update
            button.classList.toggle('active');
            icon.classList.toggle('fas');
            icon.classList.toggle('far');
            
            fetch('<?php echo BASE_PATH; ?>/api/wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    action: isActive ? 'remove' : 'add',
                    item_id: itemId,
                    item_type: itemType
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    // Revert on error
                    button.classList.toggle('active');
                    icon.classList.toggle('fas');
                    icon.classList.toggle('far');
                    
                    showAlert('error', data.message || 'Failed to update wishlist');
                } else {
                    const action = isActive ? 'removed from' : 'added to';
                    showAlert('success', `Item ${action} wishlist`);
                    
                    // Update title
                    button.title = isActive ? 'Add to Wishlist' : 'Remove from Wishlist';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Revert on error
                button.classList.toggle('active');
                icon.classList.toggle('fas');
                icon.classList.toggle('far');
                
                showAlert('error', 'Failed to update wishlist');
            });
        }
    </script>
</body>
</html>