<?php
require_once '../includes/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: games.php');
    exit();
}

$game_id = (int)$_GET['id'];

// Fetch game details
$stmt = $db->prepare("
    SELECT g.*, c.name as category_name, c.slug as category_slug
    FROM games g 
    LEFT JOIN categories c ON g.category_id = c.id 
    WHERE g.id = ? AND g.is_active = 1
");
$stmt->execute([$game_id]);
$game = $stmt->fetch();

if (!$game) {
    header('Location: games.php');
    exit();
}

// Get reviews
$stmt = $db->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.item_type = 'game' AND r.item_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$game_id]);
$reviews = $stmt->fetchAll();

// Use database rating (already calculated and stored)
$avg_rating = $game['rating'] ? (float)$game['rating'] : 0;
$total_reviews = $game['total_ratings'] ?? 0;

// Get related games
$stmt = $db->prepare("
    SELECT g.*, c.name as category_name
    FROM games g
    LEFT JOIN categories c ON g.category_id = c.id
    WHERE g.id != ? AND g.is_active = 1 
    AND (g.category_id = ? OR g.genre = ?)
    ORDER BY g.created_at DESC LIMIT 4
");
$stmt->execute([$game_id, $game['category_id'], $game['genre']]);
$related_games = $stmt->fetchAll();

// User status
$user_logged_in = isset($_SESSION['user_id']);
$in_cart = false;
$in_wishlist = false;

if ($user_logged_in) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ? AND item_type = 'game' AND item_id = ?");
    $stmt->execute([$_SESSION['user_id'], $game_id]);
    $in_cart = $stmt->fetchColumn() > 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND item_type = 'game' AND item_id = ?");
    $stmt->execute([$_SESSION['user_id'], $game_id]);
    $in_wishlist = $stmt->fetchColumn() > 0;
}

$page_title = htmlspecialchars($game['title']) . ' - Bitversity';
include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8">
            <!-- Game Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="games.php">Games</a></li>
                            <?php if ($game['category_name']): ?>
                                <li class="breadcrumb-item"><a href="games.php?category=<?= urlencode($game['category_slug']) ?>"><?= htmlspecialchars($game['category_name']) ?></a></li>
                            <?php endif; ?>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($game['title']) ?></li>
                        </ol>
                    </nav>
                    
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h1 class="mb-2"><?= htmlspecialchars($game['title']) ?></h1>
                            <div class="d-flex align-items-center mb-2">
                                <div class="rating me-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= round($avg_rating) ? 'text-warning' : 'text-muted' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-muted">(<?= $total_reviews ?> review<?= $total_reviews !== 1 ? 's' : '' ?>)</span>
                            </div>
                            
                            <div class="row text-muted small mb-3">
                                <div class="col-md-6">
                                    <i class="fas fa-gamepad me-1"></i>
                                    <strong>Genre:</strong> <?= htmlspecialchars($game['genre']) ?>
                                </div>
                                <div class="col-md-6">
                                    <i class="fas fa-layer-group me-1"></i>
                                    <strong>Category:</strong> <?= htmlspecialchars($game['category_name'] ?: 'Uncategorized') ?>
                                </div>
                                <?php if ($game['platform']): ?>
                                <div class="col-md-6">
                                    <i class="fas fa-desktop me-1"></i>
                                    <strong>Platform:</strong> <?= ucfirst($game['platform']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($game['min_age']): ?>
                                <div class="col-md-6">
                                    <i class="fas fa-user me-1"></i>
                                    <strong>Min Age:</strong> <?= $game['min_age'] ?>+
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <div class="h3 text-primary mb-3">$<?= number_format($game['price'], 2) ?></div>
                            <div class="btn-group-vertical d-grid gap-2">
                                <?php if ($user_logged_in): ?>
                                    <?php if (!$in_cart): ?>
                                        <button type="button" class="btn btn-primary add-to-cart" 
                                                data-item-type="game" data-item-id="<?= $game_id ?>">
                                            <i class="fas fa-cart-plus me-1"></i> Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary" disabled>
                                            <i class="fas fa-check me-1"></i> In Cart
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-outline-danger btn-wishlist <?= $in_wishlist ? 'active' : '' ?>" 
                                            data-item-type="game" data-item-id="<?= $game_id ?>">
                                        <i class="fas fa-heart<?= $in_wishlist ? '' : '-o' ?> me-1"></i>
                                        <?= $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>
                                    </button>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-primary">
                                        <i class="fas fa-user me-1"></i> Login to Purchase
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($game['file_path']): ?>
                                    <a href="<?= upload('games/' . $game['file_path']) ?>" class="btn btn-outline-success" target="_blank">
                                        <i class="fas fa-play me-1"></i> Play Game
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($game['game_url']): ?>
                                    <a href="<?= htmlspecialchars($game['game_url']) ?>" target="_blank" class="btn btn-outline-info">
                                        <i class="fas fa-external-link-alt me-1"></i> External Game
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Game Screenshot -->
            <?php if ($game['thumbnail']): ?>
            <div class="card mb-4">
                <img src="<?= upload($game['thumbnail']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" class="card-img-top game-screenshot">
            </div>
            <?php endif; ?>

            <!-- Game Description -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>About This Game
                    </h5>
                </div>
                <div class="card-body">
                    <div class="game-description">
                        <?= nl2br(htmlspecialchars($game['description'])) ?>
                    </div>
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-star me-2"></i>Reviews (<?= $total_reviews ?>)
                    </h5>
                    <?php if ($user_logged_in): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal">
                            <i class="fas fa-plus me-1"></i> Write Review
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($reviews)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <p>No reviews yet. Be the first to review this game!</p>
                        </div>
                    <?php else: ?>
                        <div id="reviewsList">
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-item border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong><?= htmlspecialchars($review['first_name'] . ' ' . $review['last_name']) ?></strong>
                                            <div class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-warning' : 'text-muted' ?>" style="font-size: 0.9em;"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <small class="text-muted"><?= timeAgo($review['created_at']) ?></small>
                                    </div>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Game Features -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-list-check me-2"></i>Game Features
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <?= ucfirst($game['genre']) ?> gameplay
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <?= ucfirst($game['platform']) ?> compatible
                        </li>
                        <?php if ($game['min_age']): ?>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Age <?= $game['min_age'] ?>+ suitable
                        </li>
                        <?php endif; ?>
                        <?php if ($game['game_url']): ?>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Instant play available
                        </li>
                        <?php endif; ?>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Regular updates
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Educational content
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Related Games -->
            <?php if (!empty($related_games)): ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-gamepad me-2"></i>Related Games
                    </h6>
                </div>
                <div class="card-body">
                    <?php foreach ($related_games as $related): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <?php if ($related['thumbnail']): ?>
                                    <img src="<?= upload($related['thumbnail']) ?>" alt="<?= htmlspecialchars($related['title']) ?>" class="rounded" width="60" height="45" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 60px; height: 45px;">
                                        <i class="fas fa-gamepad text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">
                                    <a href="game_detail.php?id=<?= $related['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($related['title']) ?>
                                    </a>
                                </h6>
                                <div class="text-muted small">
                                    <?= htmlspecialchars($related['category_name']) ?> • $<?= number_format($related['price'], 2) ?>
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
<?php if ($user_logged_in): ?>
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Write a Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="reviewForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rating</label>
                        <div class="rating-input">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <input type="radio" name="rating" value="<?= $i ?>" id="star<?= $i ?>" required>
                                <label for="star<?= $i ?>" class="star-label">
                                    <i class="fas fa-star"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="comment" class="form-label">Your Review</label>
                        <textarea class="form-control" id="comment" name="comment" rows="4" required 
                                  placeholder="Share your gaming experience..."></textarea>
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
.game-screenshot {
    max-height: 400px;
    object-fit: cover;
    width: 100%;
}
.game-description {
    line-height: 1.6;
    font-size: 1rem;
}
.review-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}
.rating-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}
.rating-input input { display: none; }
.rating-input label {
    cursor: pointer;
    color: #ddd;
    font-size: 1.5rem;
    margin-right: 5px;
    transition: color 0.2s;
}
.rating-input label:hover,
.rating-input label:hover ~ label,
.rating-input input:checked ~ label {
    color: #ffc107;
}
</style>

<script src="../assets/js/main.js"></script>
<script>
// Debug: Check if global functions are available
console.log('Game detail page loaded');
console.log('BitversityApp available:', typeof window.BitversityApp !== 'undefined');
console.log('updateCartCount available:', typeof window.updateCartCount !== 'undefined');
console.log('showNotification available:', typeof window.showNotification !== 'undefined');

// Ensure user logged in status is set
if (!document.body.dataset.loggedIn) {
    document.body.dataset.loggedIn = '<?php echo isLoggedIn() ? "true" : "false"; ?>';
    console.log('Set logged in status:', document.body.dataset.loggedIn);
}
// Cart and wishlist functionality is now handled by global main.js

<?php if ($user_logged_in): ?>
document.getElementById('reviewForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const reviewData = {
        item_type: 'game',
        item_id: <?= $game_id ?>,
        rating: formData.get('rating'),
        comment: formData.get('comment')
    };
    
    fetch('../api/reviews.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(reviewData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
            location.reload();
        } else {
            showAlert('error', data.message || 'Failed to submit review');
        }
    });
});
<?php endif; ?>

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 5000);
}
</script>

<?php include '../includes/footer.php'; ?>
