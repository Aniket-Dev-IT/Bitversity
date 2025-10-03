<?php
require_once __DIR__ . '/../includes/config.php';

$projectId = intval($_GET['id'] ?? 0);
if (!$projectId) {
    header('Location: ' . BASE_PATH . '/public/projects.php');
    exit;
}

// Get project details
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug,
           AVG(r.rating) as avg_rating, COUNT(r.id) as review_count
    FROM projects p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN reviews r ON r.item_id = p.id AND r.item_type = 'project'
    WHERE p.id = ? AND p.is_active = 1
    GROUP BY p.id
");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: ' . BASE_PATH . '/public/projects.php');
    exit;
}

// Get reviews
$reviewsStmt = $db->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.item_id = ? AND r.item_type = 'project' AND r.is_approved = 1
    ORDER BY r.created_at DESC
    LIMIT 10
");
$reviewsStmt->execute([$projectId]);
$reviews = $reviewsStmt->fetchAll();

// Get related projects
$relatedStmt = $db->prepare("
    SELECT * FROM projects 
    WHERE category_id = ? AND id != ? AND is_active = 1
    ORDER BY RAND()
    LIMIT 4
");
$relatedStmt->execute([$project['category_id'], $projectId]);
$relatedProjects = $relatedStmt->fetchAll();

// Check if user owns this project
$userOwnsProject = false;
if (isLoggedIn()) {
    $ownStmt = $db->prepare("
        SELECT 1 FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.user_id = ? AND oi.item_id = ? AND oi.item_type = 'project' AND o.status = 'completed'
    ");
    $ownStmt->execute([$_SESSION['user_id'], $projectId]);
    $userOwnsProject = $ownStmt->fetchColumn();
}

// Check wishlist status
$inWishlist = false;
if (isLoggedIn()) {
    $wishStmt = $db->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND item_id = ? AND item_type = 'project'");
    $wishStmt->execute([$_SESSION['user_id'], $projectId]);
    $inWishlist = $wishStmt->fetchColumn();
}

$pageTitle = $project['title'] . ' - Project Details';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_PATH; ?>/">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_PATH; ?>/public/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_PATH; ?>/public/projects.php?category=<?php echo $project['category_id']; ?>"><?php echo sanitize($project['category_name']); ?></a></li>
            <li class="breadcrumb-item active"><?php echo sanitize($project['title']); ?></li>
        </ol>
    </nav>

    <div class="row g-5">
        <!-- Project Image & Info -->
        <div class="col-lg-4">
            <div class="position-sticky" style="top: 2rem;">
                <div class="card border-0 shadow-lg">
                    <img src="<?php echo $project['cover_image'] ? upload('projects/' . $project['cover_image']) : 'https://via.placeholder.com/400x300/198754/ffffff?text=Project'; ?>" 
                         class="card-img-top" 
                         alt="<?php echo sanitize($project['title']); ?>"
                         style="height: 300px; object-fit: cover;">
                    <div class="card-body">
                        <!-- Price -->
                        <div class="text-center mb-3">
                            <span class="display-6 fw-bold text-success"><?php echo formatPrice($project['price']); ?></span>
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-grid gap-2">
                            <?php if ($userOwnsProject): ?>
                                <button class="btn btn-success btn-lg">
                                    <i class="fas fa-download me-2"></i>Download Source
                                </button>
                                <?php if ($project['demo_url']): ?>
                                    <a href="<?php echo sanitize($project['demo_url']); ?>" target="_blank" class="btn btn-outline-primary">
                                        <i class="fas fa-external-link-alt me-2"></i>View Live Demo
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn btn-success btn-lg btn-add-cart" 
                                        data-item-id="<?php echo $project['id']; ?>" 
                                        data-item-type="project">
                                    <i class="fas fa-cart-plus me-2"></i>Get Project
                                </button>
                                <?php if ($project['demo_url']): ?>
                                    <a href="<?php echo sanitize($project['demo_url']); ?>" target="_blank" class="btn btn-outline-primary">
                                        <i class="fas fa-eye me-2"></i>Preview Demo
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (isLoggedIn()): ?>
                                <button class="btn btn-outline-danger btn-wishlist <?php echo $inWishlist ? 'active' : ''; ?>" 
                                        data-item-id="<?php echo $project['id']; ?>" 
                                        data-item-type="project">
                                    <i class="fas fa-heart me-2"></i>
                                    <?php echo $inWishlist ? 'In Wishlist' : 'Add to Wishlist'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Project Info -->
                        <hr>
                        <div class="small">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Category:</strong>
                                <span><?php echo sanitize($project['category_name']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Language:</strong>
                                <span><?php echo sanitize($project['language'] ?? 'Multiple'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Difficulty:</strong>
                                <span class="badge bg-<?php 
                                    echo match($project['difficulty_level'] ?? '') {
                                        'Beginner' => 'success',
                                        'Intermediate' => 'warning',
                                        'Advanced' => 'danger',
                                        default => 'secondary'
                                    };
                                ?>"><?php echo $project['difficulty_level'] ?? 'Not Specified'; ?></span>
                            </div>
                            <?php if ($project['github_url']): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Repository:</strong>
                                <a href="<?php echo sanitize($project['github_url']); ?>" target="_blank" class="text-decoration-none">
                                    <i class="fab fa-github"></i> GitHub
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Details -->
        <div class="col-lg-8">
            <!-- Title and Rating -->
            <div class="mb-4">
                <h1 class="display-5 fw-bold mb-2"><?php echo sanitize($project['title']); ?></h1>
                <p class="lead text-muted mb-3">Build and Learn</p>
                
                <!-- Rating -->
                <?php if ($project['review_count'] > 0): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3">
                            <?php 
                            $avgRating = round($project['avg_rating'], 1);
                            for ($i = 1; $i <= 5; $i++): 
                            ?>
                                <i class="fas fa-star <?php echo $i <= $avgRating ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="fw-bold"><?php echo $avgRating; ?></span>
                        <span class="text-muted ms-2">(<?php echo $project['review_count']; ?> reviews)</span>
                    </div>
                <?php endif; ?>
                
                <!-- Tags -->
                <?php if ($project['tags']): ?>
                    <div class="mb-3">
                        <?php foreach (explode(',', $project['tags']) as $tag): ?>
                            <span class="badge bg-success bg-opacity-10 text-success me-2"><?php echo sanitize(trim($tag)); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="mb-5">
                <h3>About This Project</h3>
                <div class="lead"><?php echo nl2br(sanitize($project['description'])); ?></div>
            </div>

            <!-- What You'll Build -->
            <?php if ($project['features']): ?>
            <div class="mb-5">
                <h3>What You'll Build</h3>
                <div class="row g-3">
                    <?php foreach (explode(',', $project['features']) as $feature): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-code text-success me-2"></i>
                                <span><?php echo sanitize(trim($feature)); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Technologies -->
            <?php if ($project['technologies']): ?>
            <div class="mb-5">
                <h3>Technologies Used</h3>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach (explode(',', $project['technologies']) as $tech): ?>
                        <span class="badge bg-primary fs-6"><?php echo sanitize(trim($tech)); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- What's Included -->
            <div class="mb-5">
                <h3>What's Included</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-code text-primary me-2"></i>
                            <span>Complete Source Code</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-book text-info me-2"></i>
                            <span>Documentation & Setup Guide</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-database text-warning me-2"></i>
                            <span>Database Schema</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-comments text-success me-2"></i>
                            <span>Support & Updates</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Screenshots -->
            <?php if ($project['screenshots']): ?>
            <div class="mb-5">
                <h3>Screenshots</h3>
                <div class="row g-3">
                    <?php foreach (explode(',', $project['screenshots']) as $screenshot): ?>
                        <div class="col-md-6">
                            <img src="<?php echo upload('projects/screenshots/' . trim($screenshot)); ?>" 
                                 class="img-fluid rounded shadow-sm" 
                                 alt="Screenshot">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reviews Section -->
            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Reviews (<?php echo $project['review_count']; ?>)</h3>
                    <?php if (isLoggedIn()): ?>
                        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#reviewModal">
                            Write a Review
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($reviews)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-comment fa-3x mb-3"></i>
                        <p>No reviews yet. Be the first to review this project!</p>
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

            <!-- Related Projects -->
            <?php if (!empty($relatedProjects)): ?>
            <div class="mb-5">
                <h3>More Projects in <?php echo sanitize($project['category_name']); ?></h3>
                <div class="row g-4">
                    <?php foreach ($relatedProjects as $related): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="card h-100">
                                <img src="<?php echo $related['cover_image'] ? upload('projects/' . $related['cover_image']) : 'https://via.placeholder.com/200x150/198754/ffffff?text=Project'; ?>" 
                                     class="card-img-top" style="height: 150px; object-fit: cover;" alt="<?php echo sanitize($related['title']); ?>">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title"><?php echo sanitize($related['title']); ?></h6>
                                    <p class="text-muted small"><?php echo sanitize($related['language'] ?? 'Multiple'); ?></p>
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-success"><?php echo formatPrice($related['price']); ?></span>
                                            <a href="project.php?id=<?php echo $related['id']; ?>" class="btn btn-sm btn-outline-success">View</a>
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
                    <input type="hidden" name="item_id" value="<?php echo $project['id']; ?>">
                    <input type="hidden" name="item_type" value="project">
                    
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
                    <button type="submit" class="btn btn-success">Submit Review</button>
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