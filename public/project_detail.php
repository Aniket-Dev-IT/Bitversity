<?php
require_once '../includes/config.php';

// Check if project ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: projects.php');
    exit();
}

$project_id = (int)$_GET['id'];

// Fetch project details with category information
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug
    FROM projects p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ? AND p.is_active = 1
");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: projects.php');
    exit();
}

// Decode technologies JSON
$technologies = $project['technologies'] ? json_decode($project['technologies'], true) : [];
if (!$technologies || !is_array($technologies)) {
    $technologies = [];
}

// Get project reviews with user information
$stmt = $db->prepare("
    SELECT r.*, u.first_name, u.last_name, u.email
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.item_type = 'project' AND r.item_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$project_id]);
$reviews = $stmt->fetchAll();

// Use database rating (already calculated and stored)
$avg_rating = $project['rating'] ? (float)$project['rating'] : 0;
$total_reviews = $project['total_ratings'] ?? 0;

// Get related projects (same category or similar technologies)
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name
    FROM projects p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id != ? AND p.is_active = 1 
    AND (p.category_id = ? OR JSON_OVERLAPS(p.technologies, ?))
    ORDER BY p.created_at DESC
    LIMIT 4
");
$stmt->execute([$project_id, $project['category_id'], json_encode($technologies)]);
$related_projects = $stmt->fetchAll();

// Check if user is logged in
$user_logged_in = isset($_SESSION['user_id']);
$in_cart = false;
$in_wishlist = false;

if ($user_logged_in) {
    // Check if project is in cart
    $stmt = $db->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ? AND item_type = 'project' AND item_id = ?");
    $stmt->execute([$_SESSION['user_id'], $project_id]);
    $in_cart = $stmt->fetchColumn() > 0;
    
    // Check if project is in wishlist
    $stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND item_type = 'project' AND item_id = ?");
    $stmt->execute([$_SESSION['user_id'], $project_id]);
    $in_wishlist = $stmt->fetchColumn() > 0;
}

$page_title = htmlspecialchars($project['title']) . ' - Bitversity';
include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Project Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="projects.php">Projects</a></li>
                            <?php if ($project['category_name']): ?>
                                <li class="breadcrumb-item"><a href="projects.php?category=<?= urlencode($project['category_slug']) ?>"><?= htmlspecialchars($project['category_name']) ?></a></li>
                            <?php endif; ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($project['title']) ?></li>
                        </ol>
                    </nav>
                    
                    <!-- Project Title and Rating -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h1 class="mb-2"><?= htmlspecialchars($project['title']) ?></h1>
                            <div class="d-flex align-items-center mb-2">
                                <div class="rating me-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= round($avg_rating) ? 'text-warning' : 'text-muted' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-muted">(<?= $total_reviews ?> review<?= $total_reviews !== 1 ? 's' : '' ?>)</span>
                            </div>
                            
                            <!-- Project Meta -->
                            <div class="row text-muted small mb-3">
                                <div class="col-md-6">
                                    <i class="fas fa-layer-group me-1"></i>
                                    <strong>Category:</strong> <?= htmlspecialchars($project['category_name'] ?: 'Uncategorized') ?>
                                </div>
                                <div class="col-md-6">
                                    <i class="fas fa-signal me-1"></i>
                                    <strong>Difficulty:</strong> 
                                    <span class="badge bg-<?= getDifficultyColor($project['difficulty_level']) ?> text-dark">
                                        <?= ucfirst($project['difficulty_level']) ?>
                                    </span>
                                </div>
                                <?php if ($project['estimated_hours']): ?>
                                <div class="col-md-6">
                                    <i class="fas fa-clock me-1"></i>
                                    <strong>Estimated Hours:</strong> <?= $project['estimated_hours'] ?>h
                                </div>
                                <?php endif; ?>
                                <div class="col-md-6">
                                    <i class="fas fa-calendar me-1"></i>
                                    <strong>Updated:</strong> <?= date('M j, Y', strtotime($project['updated_at'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Price and Actions -->
                        <div class="text-end">
                            <div class="h3 text-primary mb-3">$<?= number_format($project['price'], 2) ?></div>
                            <div class="btn-group-vertical d-grid gap-2">
                                <?php if ($user_logged_in): ?>
                                    <?php if (!$in_cart): ?>
                                        <button type="button" class="btn btn-primary add-to-cart" 
                                                data-item-type="project" data-item-id="<?= $project_id ?>">
                                            <i class="fas fa-cart-plus me-1"></i> Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary" disabled>
                                            <i class="fas fa-check me-1"></i> In Cart
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-outline-danger btn-wishlist <?= $in_wishlist ? 'active' : '' ?>" 
                                            data-item-type="project" data-item-id="<?= $project_id ?>">
                                        <i class="fas fa-heart<?= $in_wishlist ? '' : '-o' ?> me-1"></i>
                                        <?= $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>
                                    </button>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-primary">
                                        <i class="fas fa-user me-1"></i> Login to Purchase
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($project['file_path']): ?>
                                    <a href="<?= upload('projects/' . $project['file_path']) ?>" class="btn btn-outline-success" download>
                                        <i class="fas fa-download me-1"></i> Download ZIP
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($project['demo_url']): ?>
                                    <a href="<?= htmlspecialchars($project['demo_url']) ?>" target="_blank" class="btn btn-outline-info">
                                        <i class="fas fa-external-link-alt me-1"></i> Live Demo
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Image -->
            <?php if ($project['cover_image']): ?>
            <div class="card mb-4">
                <img src="<?= upload($project['cover_image']) ?>" alt="<?= htmlspecialchars($project['title']) ?>" class="card-img-top project-cover">
            </div>
            <?php endif; ?>

            <!-- Project Description -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>Project Description
                    </h5>
                </div>
                <div class="card-body">
                    <div class="project-description">
                        <?= nl2br(htmlspecialchars($project['description'])) ?>
                    </div>
                </div>
            </div>

            <!-- Technologies Used -->
            <?php if (!empty($technologies)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-code me-2"></i>Technologies Used
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($technologies as $tech): ?>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($tech) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

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
                            <p>No reviews yet. Be the first to review this project!</p>
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
            <!-- Project Features -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-list-check me-2"></i>What You'll Get
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Complete source code
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Documentation and setup guide
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <?= ucfirst($project['difficulty_level']) ?> level project
                        </li>
                        <?php if ($project['estimated_hours']): ?>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            ~<?= $project['estimated_hours'] ?> hours to complete
                        </li>
                        <?php endif; ?>
                        <?php if ($project['demo_url']): ?>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Live demo available
                        </li>
                        <?php endif; ?>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Lifetime access
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Related Projects -->
            <?php if (!empty($related_projects)): ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-lightbulb me-2"></i>Related Projects
                    </h6>
                </div>
                <div class="card-body">
                    <?php foreach ($related_projects as $related): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <?php if ($related['cover_image']): ?>
                                    <img src="<?= upload($related['cover_image']) ?>" alt="<?= htmlspecialchars($related['title']) ?>" class="rounded" width="60" height="45" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 60px; height: 45px;">
                                        <i class="fas fa-code text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">
                                    <a href="project_detail.php?id=<?= $related['id'] ?>" class="text-decoration-none">
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
                                  placeholder="Share your thoughts about this project..."></textarea>
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
.project-cover {
    max-height: 400px;
    object-fit: cover;
    width: 100%;
}

.project-description {
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

.rating-input input {
    display: none;
}

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

.difficulty-badge {
    font-size: 0.75rem;
}
</style>

<script src="../assets/js/main.js"></script>
<script>
// Debug: Check if global functions are available
console.log('Project detail page loaded');
console.log('BitversityApp available:', typeof window.BitversityApp !== 'undefined');
console.log('updateCartCount available:', typeof window.updateCartCount !== 'undefined');
console.log('showNotification available:', typeof window.showNotification !== 'undefined');

// Ensure user logged in status is set
if (!document.body.dataset.loggedIn) {
    document.body.dataset.loggedIn = '<?php echo isLoggedIn() ? "true" : "false"; ?>';
    console.log('Set logged in status:', document.body.dataset.loggedIn);
}
// Cart and wishlist functionality is now handled by global main.js

// Review form submission
<?php if ($user_logged_in): ?>
document.getElementById('reviewForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const reviewData = {
        item_type: 'project',
        item_id: <?= $project_id ?>,
        rating: formData.get('rating'),
        comment: formData.get('comment')
    };
    
    fetch('../api/reviews.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(reviewData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
            
            // Reload page to show new review
            location.reload();
        } else {
            showAlert('error', data.message || 'Failed to submit review');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'An error occurred while submitting review');
    });
});
<?php endif; ?>

// Helper functions
function updateCartCounter() {
    // Use global updateCartCount function if available, otherwise fallback
    if (window.updateCartCount) {
        // Get current cart count from API
        fetch('../api/cart_count.php')
            .then(response => response.json())
            .then(data => {
                window.updateCartCount(data.count);
            })
            .catch(error => console.error('Error updating cart count:', error));
    } else {
        // Fallback method
        const cartCounter = document.querySelector('.cart-counter');
        const cartBadge = document.querySelector('.navbar .badge');
        if (cartCounter || cartBadge) {
            fetch('../api/cart_count.php')
                .then(response => response.json())
                .then(data => {
                    if (cartCounter) {
                        cartCounter.textContent = data.count;
                        cartCounter.style.display = data.count > 0 ? 'inline' : 'none';
                    }
                    if (cartBadge) {
                        cartBadge.textContent = data.count;
                        cartBadge.style.display = data.count > 0 ? 'inline' : 'none';
                    }
                })
                .catch(error => console.error('Error updating cart count:', error));
        }
    }
}

function showAlert(type, message) {
    // Create and show a Bootstrap alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

<?php
// Helper function for difficulty color
function getDifficultyColor($level) {
    switch(strtolower($level)) {
        case 'beginner': return 'success';
        case 'intermediate': return 'warning';
        case 'advanced': return 'danger';
        default: return 'secondary';
    }
}

include '../includes/footer.php';
?>