<?php
require_once '../includes/config.php';
require_once '../includes/session.php';

$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($book_id <= 0) {
    header('Location: books.php');
    exit();
}

// Get book details with category
$stmt = $db->prepare("
    SELECT b.*, c.name as category_name 
    FROM books b 
    LEFT JOIN categories c ON b.category_id = c.id 
    WHERE b.id = ? AND b.is_active = 1
");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    header('Location: books.php');
    exit();
}

// Get book reviews
$stmt = $db->prepare("
    SELECT r.*, u.username, u.profile_photo as avatar
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.item_type = 'book' AND r.item_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$book_id]);
$reviews = $stmt->fetchAll();

// Use database rating (already calculated and stored)
$avgRating = $book['rating'] ? (float)$book['rating'] : 0;
$totalReviews = $book['total_ratings'] ?? 0;

// Get related books
$stmt = $db->prepare("
    SELECT * FROM books 
    WHERE category_id = ? AND id != ? AND is_active = 1 
    ORDER BY RAND() 
    LIMIT 4
");
$stmt->execute([$book['category_id'], $book_id]);
$relatedBooks = $stmt->fetchAll();

// Check if book is in user's cart/wishlist
$inCart = false;
$inWishlist = false;
if (isLoggedIn()) {
    $stmt = $db->prepare("SELECT id FROM cart WHERE user_id = ? AND item_type = 'book' AND item_id = ?");
    $stmt->execute([$_SESSION['user_id'], $book_id]);
    $inCart = $stmt->fetch() ? true : false;
    
    $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND item_type = 'book' AND item_id = ?");
    $stmt->execute([$_SESSION['user_id'], $book_id]);
    $inWishlist = $stmt->fetch() ? true : false;
}

$pageTitle = $book['title'] . ' - Book Details';
require_once '../includes/header.php';
?>

<!-- Book Header -->
<div class="page-header" style="background-image: url('https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb" style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);">
                <li class="breadcrumb-item"><a href="../index.php" class="text-white">Home</a></li>
                <li class="breadcrumb-item"><a href="books.php" class="text-white">Books</a></li>
                <li class="breadcrumb-item active text-white"><?= htmlspecialchars($book['title']) ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="container mt-4">

    <div class="row">
        <!-- Book Details -->
        <div class="col-lg-8">
            <div class="row">
                <!-- Book Cover -->
                <div class="col-md-5">
                    <div class="book-cover-container">
                        <?php if ($book['cover_image']): ?>
                            <img src="<?= upload($book['cover_image']) ?>" 
                                 alt="<?= htmlspecialchars($book['title']) ?>" 
                                 class="img-fluid rounded shadow-lg book-cover">
                        <?php else: ?>
                            <div class="placeholder-cover d-flex align-items-center justify-content-center bg-light rounded shadow-lg">
                                <i class="fas fa-book fa-5x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Book Info -->
                <div class="col-md-7">
                    <div class="book-info">
                        <h1 class="book-title mb-3"><?= htmlspecialchars($book['title']) ?></h1>
                        
                        <div class="book-meta mb-3">
                            <p class="text-muted mb-2">
                                <strong>Author:</strong> <?= htmlspecialchars($book['author']) ?>
                            </p>
                            <p class="text-muted mb-2">
                                <strong>Category:</strong> 
                                <span class="badge bg-primary"><?= htmlspecialchars($book['category_name'] ?? 'Uncategorized') ?></span>
                            </p>
                            <?php if ($book['isbn']): ?>
                                <p class="text-muted mb-2">
                                    <strong>ISBN:</strong> <?= htmlspecialchars($book['isbn']) ?>
                                </p>
                            <?php endif; ?>
                            <p class="text-muted mb-2">
                                <strong>Published:</strong> <?= date('F j, Y', strtotime($book['created_at'])) ?>
                            </p>
                        </div>

                        <!-- Rating -->
                        <div class="rating-section mb-3">
                            <div class="d-flex align-items-center">
                                <div class="stars me-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= round($avgRating) ? 'text-warning' : 'text-muted' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text">
                                    <?= number_format($avgRating, 1) ?> (<?= number_format($totalReviews) ?> reviews)
                                </span>
                            </div>
                        </div>

                        <!-- Price and Actions -->
                        <div class="price-section mb-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="price">
                                    <?php if ($book['price'] > 0): ?>
                                        <span class="h3 text-success mb-0">$<?= number_format($book['price'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="h3 text-success mb-0">FREE</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons mb-4">
                            <?php if (isLoggedIn()): ?>
                                <?php if (!$inCart): ?>
                                    <button class="btn btn-success btn-lg me-2 add-to-cart" 
                                            data-item-type="book" data-item-id="<?= $book['id'] ?>">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-outline-success btn-lg me-2" disabled>
                                        <i class="fas fa-check"></i> In Cart
                                    </button>
                                <?php endif; ?>

                                <?php if (!$inWishlist): ?>
                                    <button class="btn btn-outline-danger btn-lg me-2 btn-wishlist" 
                                            data-item-type="book" data-item-id="<?= $book['id'] ?>">
                                        <i class="far fa-heart"></i> Wishlist
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-danger btn-lg me-2 btn-wishlist active" 
                                            data-item-type="book" data-item-id="<?= $book['id'] ?>">
                                        <i class="fas fa-heart"></i> Wishlisted
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                <a href="../auth/login.php" class="btn btn-success btn-lg me-2">
                                    <i class="fas fa-sign-in-alt"></i> Login to Purchase
                                </a>
                            <?php endif; ?>

                            <?php if ($book['file_path']): ?>
                                <a href="<?= upload('books/' . $book['file_path']) ?>" class="btn btn-outline-primary btn-lg" target="_blank">
                                    <i class="fas fa-download"></i> Download Sample
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Quick Info -->
                        <div class="quick-info">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="info-item">
                                        <i class="fas fa-file-pdf text-danger"></i>
                                        <small class="d-block text-muted">PDF Format</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="info-item">
                                        <i class="fas fa-download text-primary"></i>
                                        <small class="d-block text-muted">Instant Download</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="info-item">
                                        <i class="fas fa-shield-alt text-success"></i>
                                        <small class="d-block text-muted">Secure Payment</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Book Description -->
            <div class="book-description mt-5">
                <h3>About this Book</h3>
                <div class="description-content">
                    <?= nl2br(htmlspecialchars($book['description'])) ?>
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="reviews-section mt-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Customer Reviews (<?= number_format($totalReviews) ?>)</h3>
                    <?php if (isLoggedIn()): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reviewModal">
                            <i class="fas fa-plus"></i> Write Review
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($reviews)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No reviews yet</h5>
                        <p class="text-muted">Be the first to review this book!</p>
                    </div>
                <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item border-bottom py-3">
                                <div class="review-content">
                                        <div class="review-header d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($review['username']) ?></h6>
                                                <div class="stars mb-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-warning' : 'text-muted' ?> small"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <small class="text-muted"><?= date('M j, Y', strtotime($review['created_at'])) ?></small>
                                        </div>
                                        <p class="review-text mb-0"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Related Books -->
            <?php if (!empty($relatedBooks)): ?>
                <div class="related-books">
                    <h4 class="mb-3">Related Books</h4>
                    <div class="row">
                        <?php foreach ($relatedBooks as $relatedBook): ?>
                            <div class="col-6 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <a href="book_detail.php?id=<?= $relatedBook['id'] ?>" class="text-decoration-none">
                                        <div class="related-book-cover">
                                            <?php if ($relatedBook['cover_image']): ?>
                                                <img src="<?= upload($relatedBook['cover_image']) ?>" 
                                                     alt="<?= htmlspecialchars($relatedBook['title']) ?>" 
                                                     class="card-img-top" style="height: 200px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="placeholder-cover-small bg-light d-flex align-items-center justify-content-center" 
                                                     style="height: 200px;">
                                                    <i class="fas fa-book fa-2x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body p-2">
                                            <h6 class="card-title mb-1 small text-dark"><?= htmlspecialchars($relatedBook['title']) ?></h6>
                                            <p class="card-text text-muted small mb-1"><?= htmlspecialchars($relatedBook['author']) ?></p>
                                            <p class="card-text text-success small mb-0">
                                                $<?= number_format($relatedBook['price'], 2) ?>
                                            </p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Book Details Card -->
            <div class="book-details-card mt-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Book Details</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Title:</strong></td>
                                <td><?= htmlspecialchars($book['title']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Author:</strong></td>
                                <td><?= htmlspecialchars($book['author']) ?></td>
                            </tr>
                            <?php if ($book['isbn']): ?>
                            <tr>
                                <td><strong>ISBN:</strong></td>
                                <td><?= htmlspecialchars($book['isbn']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong>Category:</strong></td>
                                <td><?= htmlspecialchars($book['category_name'] ?? 'Uncategorized') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Price:</strong></td>
                                <td class="text-success">$<?= number_format($book['price'], 2) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Rating:</strong></td>
                                <td>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= round($avgRating) ? 'text-warning' : 'text-muted' ?> small"></i>
                                    <?php endfor; ?>
                                    <?= number_format($avgRating, 1) ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
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
                    <input type="hidden" name="item_type" value="book">
                    <input type="hidden" name="item_id" value="<?= $book_id ?>">
                    
                    <div class="mb-3">
                        <label for="rating" class="form-label">Rating *</label>
                        <div class="rating-input">
                            <input type="hidden" name="rating" id="rating" value="5">
                            <div class="stars-input">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star star-input" data-rating="<?= $i ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="comment" class="form-label">Your Review *</label>
                        <textarea class="form-control" id="comment" name="comment" rows="4" 
                                  placeholder="Share your thoughts about this book..." required></textarea>
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

<style>
.book-cover {
    max-height: 400px;
    object-fit: cover;
}

.placeholder-cover {
    height: 400px;
}

.rating-input .stars-input {
    font-size: 1.5rem;
}

.rating-input .star-input {
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s;
}

.rating-input .star-input:hover,
.rating-input .star-input.active {
    color: #ffc107;
}

.book-info h1 {
    color: #2c3e50;
}

.info-item {
    padding: 15px 10px;
    background: #f8f9fa;
    border-radius: 8px;
}

.action-buttons .btn {
    min-width: 120px;
}

.review-item:last-child {
    border-bottom: none !important;
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../assets/js/main.js"></script>
<script>
// Debug: Check if global functions are available
console.log('Book detail page loaded');
console.log('BitversityApp available:', typeof window.BitversityApp !== 'undefined');
console.log('updateCartCount available:', typeof window.updateCartCount !== 'undefined');
console.log('showNotification available:', typeof window.showNotification !== 'undefined');

// Ensure user logged in status is set
if (!document.body.dataset.loggedIn) {
    document.body.dataset.loggedIn = '<?php echo isLoggedIn() ? "true" : "false"; ?>';
    console.log('Set logged in status:', document.body.dataset.loggedIn);
}
$(document).ready(function() {
    // Star rating input
    $('.star-input').on('click', function() {
        const rating = $(this).data('rating');
        $('#rating').val(rating);
        
        $('.star-input').each(function() {
            if ($(this).data('rating') <= rating) {
                $(this).addClass('active');
            } else {
                $(this).removeClass('active');
            }
        });
    });
    
    // Initialize with 5 stars
    $('.star-input').addClass('active');
    
    // Cart and wishlist functionality is handled by global main.js
    // Just ensure compatibility with any old custom handlers
    $('.add-to-cart').off('click').on('click', function(e) {
        // Global main.js will handle this via event delegation
        // This is just for compatibility
        e.stopPropagation();
    });
    
    // Submit review
    $('#reviewForm').on('submit', function(e) {
        e.preventDefault();
        
        $.post('../api/reviews.php', $(this).serialize(), function(response) {
            if (response.success) {
                $('#reviewModal').modal('hide');
                showToast('success', 'Review submitted successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', response.message || 'Failed to submit review');
            }
        });
    });
});

function previewBook() {
    const pdfUrl = '<?= $book['pdf_file_url'] ?? '' ?>';
    if (pdfUrl) {
        window.open(pdfUrl, '_blank');
    }
}

function showToast(type, message) {
    // Simple toast notification
    const toast = $(`
        <div class="toast-notification alert alert-${type === 'success' ? 'success' : 'danger'} 
             position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
            ${message}
        </div>
    `);
    
    $('body').append(toast);
    
    setTimeout(() => {
        toast.fadeOut(() => toast.remove());
    }, 3000);
}
</script>

<?php require_once '../includes/footer.php'; ?>
