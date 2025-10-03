<?php
require_once __DIR__ . '/../includes/config.php';

$gameId = intval($_GET['id'] ?? 0);
if (!$gameId) {
    header('Location: ' . BASE_PATH . '/public/games.php');
    exit;
}

// Get game details
$stmt = $db->prepare("
    SELECT g.*, c.name as category_name, c.slug as category_slug,
           AVG(r.rating) as avg_rating, COUNT(r.id) as review_count
    FROM games g
    LEFT JOIN categories c ON g.category_id = c.id
    LEFT JOIN reviews r ON r.item_id = g.id AND r.item_type = 'game'
    WHERE g.id = ? AND g.is_active = 1
    GROUP BY g.id
");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game) {
    header('Location: ' . BASE_PATH . '/public/games.php');
    exit;
}

// Get reviews
$reviewsStmt = $db->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.item_id = ? AND r.item_type = 'game' AND r.is_approved = 1
    ORDER BY r.created_at DESC
    LIMIT 10
");
$reviewsStmt->execute([$gameId]);
$reviews = $reviewsStmt->fetchAll();

// Get related games
$relatedStmt = $db->prepare("
    SELECT * FROM games 
    WHERE category_id = ? AND id != ? AND is_active = 1
    ORDER BY RAND()
    LIMIT 4
");
$relatedStmt->execute([$game['category_id'], $gameId]);
$relatedGames = $relatedStmt->fetchAll();

// Check if user owns this game
$userOwnsGame = false;
if (isLoggedIn()) {
    $ownStmt = $db->prepare("
        SELECT 1 FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.user_id = ? AND oi.item_id = ? AND oi.item_type = 'game' AND o.status = 'completed'
    ");
    $ownStmt->execute([$_SESSION['user_id'], $gameId]);
    $userOwnsGame = $ownStmt->fetchColumn();
}

// Check wishlist status
$inWishlist = false;
if (isLoggedIn()) {
    $wishStmt = $db->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND item_id = ? AND item_type = 'game'");
    $wishStmt->execute([$_SESSION['user_id'], $gameId]);
    $inWishlist = $wishStmt->fetchColumn();
}

$pageTitle = $game['title'] . ' - Game Details';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_PATH; ?>/">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_PATH; ?>/public/games.php">Games</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_PATH; ?>/public/games.php?category=<?php echo $game['category_id']; ?>"><?php echo sanitize($game['category_name']); ?></a></li>
            <li class="breadcrumb-item active"><?php echo sanitize($game['title']); ?></li>
        </ol>
    </nav>

    <div class="row g-5">
        <!-- Game Image & Info -->
        <div class="col-lg-4">
            <div class="position-sticky" style="top: 2rem;">
                <div class="card border-0 shadow-lg">
                    <img src="<?php echo $game['thumbnail'] ? upload('games/' . $game['thumbnail']) : 'https://via.placeholder.com/400x300/ffc107/ffffff?text=Game'; ?>" 
                         class="card-img-top" 
                         alt="<?php echo sanitize($game['title']); ?>"
                         style="height: 300px; object-fit: cover;">
                    <div class="card-body">
                        <!-- Price -->
                        <div class="text-center mb-3">
                            <span class="display-6 fw-bold text-warning"><?php echo formatPrice($game['price']); ?></span>
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-grid gap-2">
                            <?php if ($userOwnsGame): ?>
                                <button class="btn btn-warning btn-lg" onclick="startGame()">
                                    <i class="fas fa-play me-2"></i>Play Now
                                </button>
                                <button class="btn btn-outline-success">
                                    <i class="fas fa-download me-2"></i>Download Game
                                </button>
                            <?php else: ?>
                                <button class="btn btn-warning btn-lg btn-add-cart" 
                                        data-item-id="<?php echo $game['id']; ?>" 
                                        data-item-type="game">
                                    <i class="fas fa-cart-plus me-2"></i>Buy Game
                                </button>
                                <?php if ($game['demo_available']): ?>
                                    <button class="btn btn-outline-warning" onclick="playDemo()">
                                        <i class="fas fa-gamepad me-2"></i>Play Demo
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (isLoggedIn()): ?>
                                <button class="btn btn-outline-danger btn-wishlist <?php echo $inWishlist ? 'active' : ''; ?>" 
                                        data-item-id="<?php echo $game['id']; ?>" 
                                        data-item-type="game">
                                    <i class="fas fa-heart me-2"></i>
                                    <?php echo $inWishlist ? 'In Wishlist' : 'Add to Wishlist'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Game Info -->
                        <hr>
                        <div class="small">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Genre:</strong>
                                <span><?php echo sanitize($game['genre']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Category:</strong>
                                <span><?php echo sanitize($game['category_name']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Platform:</strong>
                                <span>Web Browser</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Players:</strong>
                                <span><?php echo $game['multiplayer'] ? 'Multiplayer' : 'Single Player'; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Age Rating:</strong>
                                <span class="badge bg-info"><?php echo $game['age_rating'] ?? 'E for Everyone'; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Plays:</strong>
                                <span><?php echo number_format($game['plays_count'] ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Game Details -->
        <div class="col-lg-8">
            <!-- Title and Rating -->
            <div class="mb-4">
                <h1 class="display-5 fw-bold mb-2"><?php echo sanitize($game['title']); ?></h1>
                <p class="lead text-muted mb-3"><?php echo sanitize($game['genre']); ?> Game</p>
                
                <!-- Rating -->
                <?php if ($game['review_count'] > 0): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3">
                            <?php 
                            $avgRating = round($game['avg_rating'], 1);
                            for ($i = 1; $i <= 5; $i++): 
                            ?>
                                <i class="fas fa-star <?php echo $i <= $avgRating ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="fw-bold"><?php echo $avgRating; ?></span>
                        <span class="text-muted ms-2">(<?php echo $game['review_count']; ?> reviews)</span>
                    </div>
                <?php endif; ?>
                
                <!-- Tags -->
                <?php if ($game['tags']): ?>
                    <div class="mb-3">
                        <?php foreach (explode(',', $game['tags']) as $tag): ?>
                            <span class="badge bg-warning bg-opacity-10 text-dark me-2"><?php echo sanitize(trim($tag)); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Game Preview/Demo Area -->
            <div class="mb-5">
                <div class="card bg-dark text-white">
                    <div class="card-body text-center p-5">
                        <div id="gameArea" class="d-none">
                            <div id="gameFrame" style="width: 100%; height: 400px; background: #000; border-radius: 10px; position: relative;">
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <div class="text-center">
                                        <i class="fas fa-gamepad fa-4x mb-3"></i>
                                        <h4>Game Loading...</h4>
                                        <div class="spinner-border" role="status"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 text-center">
                                <button class="btn btn-danger" onclick="stopGame()">Stop Game</button>
                                <button class="btn btn-secondary" onclick="toggleFullscreen()">Fullscreen</button>
                            </div>
                        </div>
                        
                        <div id="gamePreview">
                            <i class="fas fa-play-circle fa-5x mb-3"></i>
                            <h3><?php echo sanitize($game['title']); ?></h3>
                            <p class="mb-4"><?php echo sanitize(substr($game['description'], 0, 150)); ?>...</p>
                            <?php if ($userOwnsGame || $game['demo_available']): ?>
                                <button class="btn btn-warning btn-lg" onclick="<?php echo $userOwnsGame ? 'startGame()' : 'playDemo()'; ?>">
                                    <i class="fas fa-play me-2"></i><?php echo $userOwnsGame ? 'Start Game' : 'Play Demo'; ?>
                                </button>
                            <?php else: ?>
                                <p class="text-muted">Purchase this game to play</p>
                                <button class="btn btn-warning btn-add-cart" 
                                        data-item-id="<?php echo $game['id']; ?>" 
                                        data-item-type="game">
                                    <i class="fas fa-shopping-cart me-2"></i>Buy Now
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="mb-5">
                <h3>About This Game</h3>
                <div class="lead"><?php echo nl2br(sanitize($game['description'])); ?></div>
            </div>

            <!-- Game Features -->
            <?php if ($game['features']): ?>
            <div class="mb-5">
                <h3>Game Features</h3>
                <div class="row g-3">
                    <?php foreach (explode(',', $game['features']) as $feature): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-star text-warning me-2"></i>
                                <span><?php echo sanitize(trim($feature)); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- System Requirements -->
            <div class="mb-5">
                <h3>System Requirements</h3>
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-success">✓ Minimum Requirements</h6>
                                <ul class="list-unstyled small">
                                    <li><strong>Browser:</strong> Chrome, Firefox, Safari, Edge</li>
                                    <li><strong>Internet:</strong> Broadband connection</li>
                                    <li><strong>Memory:</strong> 4 GB RAM</li>
                                    <li><strong>Storage:</strong> 100 MB available space</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary">⚡ Recommended</h6>
                                <ul class="list-unstyled small">
                                    <li><strong>Browser:</strong> Latest Chrome/Firefox</li>
                                    <li><strong>Internet:</strong> High-speed connection</li>
                                    <li><strong>Memory:</strong> 8 GB RAM</li>
                                    <li><strong>Graphics:</strong> Dedicated GPU</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Screenshots -->
            <?php if ($game['screenshots']): ?>
            <div class="mb-5">
                <h3>Screenshots</h3>
                <div class="row g-3">
                    <?php foreach (explode(',', $game['screenshots']) as $screenshot): ?>
                        <div class="col-md-4">
                            <img src="<?php echo upload('games/screenshots/' . trim($screenshot)); ?>" 
                                 class="img-fluid rounded shadow-sm" 
                                 alt="Screenshot" 
                                 style="cursor: pointer;" 
                                 onclick="openScreenshot(this.src)">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reviews Section -->
            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Reviews (<?php echo $game['review_count']; ?>)</h3>
                    <?php if (isLoggedIn()): ?>
                        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#reviewModal">
                            Write a Review
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($reviews)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-comment fa-3x mb-3"></i>
                        <p>No reviews yet. Be the first to review this game!</p>
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

            <!-- Related Games -->
            <?php if (!empty($relatedGames)): ?>
            <div class="mb-5">
                <h3>More <?php echo sanitize($game['genre']); ?> Games</h3>
                <div class="row g-4">
                    <?php foreach ($relatedGames as $related): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="card h-100">
                                <img src="<?php echo $related['thumbnail'] ? upload('games/' . $related['thumbnail']) : 'https://via.placeholder.com/200x150/ffc107/ffffff?text=Game'; ?>" 
                                     class="card-img-top" style="height: 150px; object-fit: cover;" alt="<?php echo sanitize($related['title']); ?>">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title"><?php echo sanitize($related['title']); ?></h6>
                                    <p class="text-muted small"><?php echo sanitize($related['genre']); ?></p>
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-warning"><?php echo formatPrice($related['price']); ?></span>
                                            <a href="game.php?id=<?php echo $related['id']; ?>" class="btn btn-sm btn-outline-warning">Play</a>
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

<!-- Screenshot Modal -->
<div class="modal fade" id="screenshotModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-0">
                <img id="screenshotImage" src="" class="img-fluid w-100" alt="Screenshot">
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
                    <input type="hidden" name="item_id" value="<?php echo $game['id']; ?>">
                    <input type="hidden" name="item_type" value="game">
                    
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
                    <button type="submit" class="btn btn-warning">Submit Review</button>
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
// Game functionality
function startGame() {
    document.getElementById('gamePreview').classList.add('d-none');
    document.getElementById('gameArea').classList.remove('d-none');
    
    // Simulate game loading
    setTimeout(() => {
        document.getElementById('gameFrame').innerHTML = `
            <div class="d-flex align-items-center justify-content-center h-100 bg-primary rounded">
                <div class="text-center text-white">
                    <h3>🎮 Game Running</h3>
                    <p>This is where the actual game would load!</p>
                    <small>Game ID: <?php echo $game['id']; ?></small>
                </div>
            </div>
        `;
    }, 2000);
}

function playDemo() {
    document.getElementById('gamePreview').classList.add('d-none');
    document.getElementById('gameArea').classList.remove('d-none');
    
    // Simulate demo loading
    setTimeout(() => {
        document.getElementById('gameFrame').innerHTML = `
            <div class="d-flex align-items-center justify-content-center h-100 bg-warning rounded">
                <div class="text-center text-dark">
                    <h3>🕹️ Demo Mode</h3>
                    <p>Limited demo version of <?php echo sanitize($game['title']); ?></p>
                    <button class="btn btn-dark mt-3" onclick="stopGame()">Buy Full Version</button>
                </div>
            </div>
        `;
    }, 1500);
}

function stopGame() {
    document.getElementById('gameArea').classList.add('d-none');
    document.getElementById('gamePreview').classList.remove('d-none');
}

function toggleFullscreen() {
    const gameFrame = document.getElementById('gameFrame');
    if (!document.fullscreenElement) {
        gameFrame.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

function openScreenshot(src) {
    document.getElementById('screenshotImage').src = src;
    new bootstrap.Modal(document.getElementById('screenshotModal')).show();
}

// Review form submission
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