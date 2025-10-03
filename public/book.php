<?php
require_once __DIR__ . '/../includes/config.php';

$bookId = intval($_GET['id'] ?? 0);
if (!$bookId) {
    header('Location: ' . BASE_PATH . '/public/books.php');
    exit;
}

// Get book details
$stmt = $db->prepare("
    SELECT b.*, c.name as category_name, c.slug as category_slug,
           AVG(r.rating) as avg_rating, COUNT(r.id) as review_count
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN reviews r ON r.item_id = b.id AND r.item_type = 'book'
    WHERE b.id = ? AND b.is_active = 1
    GROUP BY b.id
");
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    header('Location: ' . BASE_PATH . '/public/books.php');
    exit;
}

// Get reviews
$reviewsStmt = $db->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.item_id = ? AND r.item_type = 'book'
    ORDER BY r.created_at DESC
    LIMIT 10
");
$reviewsStmt->execute([$bookId]);
$reviews = $reviewsStmt->fetchAll();

// Get related books
$relatedStmt = $db->prepare("
    SELECT * FROM books 
    WHERE category_id = ? AND id != ? AND is_active = 1
    ORDER BY RAND()
    LIMIT 4
");
$relatedStmt->execute([$book['category_id'], $bookId]);
$relatedBooks = $relatedStmt->fetchAll();

// Check if user owns this book
$userOwnsBook = false;
if (isLoggedIn()) {
    $ownStmt = $db->prepare("
        SELECT 1 FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.user_id = ? AND oi.item_id = ? AND oi.item_type = 'book' AND o.status = 'completed'
    ");
    $ownStmt->execute([$_SESSION['user_id'], $bookId]);
    $userOwnsBook = $ownStmt->fetchColumn();
}

// Check wishlist status
$inWishlist = false;
if (isLoggedIn()) {
    $wishStmt = $db->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND item_id = ? AND item_type = 'book'");
    $wishStmt->execute([$_SESSION['user_id'], $bookId]);
    $inWishlist = $wishStmt->fetchColumn();
}

$pageTitle = $book['title'] . ' - Book Details';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_PATH; ?>/">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_PATH; ?>/public/books.php">Books</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_PATH; ?>/public/books.php?category=<?php echo $book['category_id']; ?>"><?php echo sanitize($book['category_name']); ?></a></li>
            <li class="breadcrumb-item active"><?php echo sanitize($book['title']); ?></li>
        </ol>
    </nav>

    <div class="row g-5">
        <!-- Book Image -->
        <div class="col-lg-4">
            <div class="position-sticky" style="top: 2rem;">
                <div class="card border-0 shadow-lg">
                    <img src="<?php echo $book['cover_image'] ? upload('books/' . $book['cover_image']) : 'https://via.placeholder.com/400x600/0d6efd/ffffff?text=Book'; ?>" 
                         class="card-img-top" 
                         alt="<?php echo sanitize($book['title']); ?>"
                         style="height: 400px; object-fit: cover;">
                    <div class="card-body">
                        <!-- Price -->
                        <div class="text-center mb-3">
                            <span class="display-6 fw-bold text-primary"><?php echo formatPrice($book['price']); ?></span>
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-grid gap-2">
                            <?php if ($userOwnsBook): ?>
                                <button class="btn btn-success btn-lg">
                                    <i class="fas fa-download me-2"></i>Download Now
                                </button>
                                <small class="text-muted text-center">You own this book</small>
                            <?php else: ?>
                                <button class="btn btn-primary btn-lg btn-add-cart" 
                                        data-item-id="<?php echo $book['id']; ?>" 
                                        data-item-type="book">
                                    <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                </button>
                            <?php endif; ?>
                            
                            <?php if (isLoggedIn()): ?>
                                <button class="btn btn-outline-danger btn-wishlist <?php echo $inWishlist ? 'active' : ''; ?>" 
                                        data-item-id="<?php echo $book['id']; ?>" 
                                        data-item-type="book">
                                    <i class="fas fa-heart me-2"></i>
                                    <?php echo $inWishlist ? 'In Wishlist' : 'Add to Wishlist'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Book Info -->
                        <hr>
                        <div class="small">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Author:</strong>
                                <span><?php echo sanitize($book['author']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Category:</strong>
                                <span><?php echo sanitize($book['category_name']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Pages:</strong>
                                <span><?php echo $book['pages'] ?? 'N/A'; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Language:</strong>
                                <span><?php echo $book['language'] ?? 'English'; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Format:</strong>
                                <span>PDF</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Book Details -->
        <div class="col-lg-8">
            <!-- Title and Rating -->
            <div class="mb-4">
                <h1 class="display-5 fw-bold mb-2"><?php echo sanitize($book['title']); ?></h1>
                <p class="lead text-muted mb-3">by <?php echo sanitize($book['author']); ?></p>
                
                <!-- Rating -->
                <?php if ($book['review_count'] > 0): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3">
                            <?php 
                            $avgRating = round($book['avg_rating'], 1);
                            for ($i = 1; $i <= 5; $i++): 
                            ?>
                                <i class="fas fa-star <?php echo $i <= $avgRating ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="fw-bold"><?php echo $avgRating; ?></span>
                        <span class="text-muted ms-2">(<?php echo $book['review_count']; ?> reviews)</span>
                    </div>
                <?php endif; ?>
                
                <!-- Tags -->
                <?php if ($book['tags']): ?>
                    <div class="mb-3">
                        <?php foreach (explode(',', $book['tags']) as $tag): ?>
                            <span class="badge bg-light text-dark me-2"><?php echo sanitize(trim($tag)); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="mb-5">
                <h3>About This Book</h3>
                <div class="lead"><?php echo nl2br(sanitize($book['description'])); ?></div>
            </div>

            <!-- What You'll Learn -->
            <?php if ($book['features']): ?>
            <div class="mb-5">
                <h3>What You'll Learn</h3>
                <div class="row g-3">
                    <?php foreach (explode(',', $book['features']) as $feature): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span><?php echo sanitize(trim($feature)); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reviews Section -->
            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Reviews (<?php echo $book['review_count']; ?>)</h3>
                    <?php if (isLoggedIn() && !$userOwnsBook): ?>
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reviewModal">
                            Write a Review
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($reviews)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-comment fa-3x mb-3"></i>
                        <p>No reviews yet. Be the first to review this book!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong><?php echo sanitize($review['first_name'] . ' ' . $review['last_name']); ?></strong>
                                        <div class="small text-muted"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></div>
                                    </div>
                                    <div>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?> small"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p class="mb-0"><?php echo nl2br(sanitize($review['comment'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Related Books -->
            <?php if (!empty($relatedBooks)): ?>
            <div class="mb-5">
                <h3>More Books in <?php echo sanitize($book['category_name']); ?></h3>
                <div class="row g-4">
                    <?php foreach ($relatedBooks as $related): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="card h-100">
                                <img src="<?php echo $related['cover_image'] ? upload('books/' . $related['cover_image']) : 'https://via.placeholder.com/200x300/0d6efd/ffffff?text=Book'; ?>" 
                                     class="card-img-top" style="height: 200px; object-fit: cover;" alt="<?php echo sanitize($related['title']); ?>">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title"><?php echo sanitize($related['title']); ?></h6>
                                    <p class="text-muted small"><?php echo sanitize($related['author']); ?></p>
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-primary"><?php echo formatPrice($related['price']); ?></span>
                                            <a href="book.php?id=<?php echo $related['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Review Modal -->
<?php if (isLoggedIn()): ?>
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Write a Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="reviewForm">
                <div class="modal-body">
                    <input type="hidden" name="item_id" value="<?php echo $book['id']; ?>">
                    <input type="hidden" name="item_type" value="book">
                    
                    <div class="mb-3">
                        <label class="form-label">Rating</label>
                        <div class="rating-select">
                            <input type="radio" name="rating" value="5" id="star5"><label for="star5">★</label>
                            <input type="radio" name="rating" value="4" id="star4"><label for="star4">★</label>
                            <input type="radio" name="rating" value="3" id="star3"><label for="star3">★</label>
                            <input type="radio" name="rating" value="2" id="star2"><label for="star2">★</label>
                            <input type="radio" name="rating" value="1" id="star1"><label for="star1">★</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Your Review</label>
                        <textarea class="form-control" name="comment" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo asset('js/main.js'); ?>"></script>

<style>
.rating-select {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
}
.rating-select input {
    display: none;
}
.rating-select label {
    cursor: pointer;
    width: 30px;
    height: 30px;
    display: block;
    background: url("data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ddd'><path d='M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z'/></svg>") no-repeat center;
    background-size: 100%;
}
.rating-select input:checked ~ label,
.rating-select label:hover,
.rating-select label:hover ~ label {
    background: url("data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ffc107'><path d='M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z'/></svg>") no-repeat center;
    background-size: 100%;
}
</style>

<script>
document.getElementById('reviewForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('/Bitversity/api/reviews.php', {
            method: 'POST',
            headers: {'X-CSRF-Token': '<?php echo generateCsrfToken(); ?>'},
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    } catch (error) {
        alert('Error submitting review');
    }
});
</script>