<?php
// Start output buffering to prevent any output before headers
ob_start();

// Suppress warnings and errors for AJAX
error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/../includes/config.php';
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Configuration error']);
    exit;
}

// Clear any output that might have occurred
ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$itemId = intval($_GET['id'] ?? 0);
$itemType = $_GET['type'] ?? '';

if (!$itemId || !in_array($itemType, ['book', 'project', 'game'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $item = null;
    
    switch ($itemType) {
        case 'book':
            $stmt = $db->prepare("
                SELECT b.*, c.name as category_name 
                FROM books b 
                LEFT JOIN categories c ON b.category_id = c.id 
                WHERE b.id = ? AND b.is_active = 1
            ");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            break;
            
        case 'project':
            $stmt = $db->prepare("
                SELECT p.*, c.name as category_name 
                FROM projects p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.id = ? AND p.is_active = 1
            ");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            break;
            
        case 'game':
            $stmt = $db->prepare("
                SELECT g.*, c.name as category_name 
                FROM games g 
                LEFT JOIN categories c ON g.category_id = c.id 
                WHERE g.id = ? AND g.is_active = 1
            ");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            break;
    }
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    // Check if user owns this item
    $userOwnsItem = false;
    if (isLoggedIn()) {
        $stmt = $db->prepare("
            SELECT 1 FROM user_purchases up
            JOIN orders o ON up.order_id = o.id
            WHERE up.user_id = ? AND up.item_id = ? AND up.item_type = ? AND o.status = 'completed'
        ");
        $stmt->execute([$_SESSION['user_id'], $itemId, $itemType]);
        $userOwnsItem = (bool) $stmt->fetch();
    }
    
    // Check if item is in wishlist
    $inWishlist = false;
    if (isLoggedIn()) {
        $stmt = $db->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND item_id = ? AND item_type = ?");
        $stmt->execute([$_SESSION['user_id'], $itemId, $itemType]);
        $inWishlist = (bool) $stmt->fetch();
    }
    
    // Generate HTML content based on item type
    ob_start();
    ?>
    <div class="row">
        <div class="col-md-4">
            <img src="<?php 
                if ($itemType === 'book') {
                    $imagePath = $item['cover_image'] ? upload('books/' . $item['cover_image']) : 'https://via.placeholder.com/300x400/0d6efd/ffffff?text=Book';
                } elseif ($itemType === 'project') {
                    $imagePath = $item['cover_image'] ? upload('projects/' . $item['cover_image']) : 'https://via.placeholder.com/400x250/0d6efd/ffffff?text=Project';
                } else {
                    $imagePath = $item['thumbnail'] ? upload('games/' . $item['thumbnail']) : 'https://via.placeholder.com/400x250/0d6efd/ffffff?text=Game';
                }
                echo $imagePath;
            ?>"
                 class="img-fluid rounded shadow-sm" 
                 alt="<?php echo sanitize($item['title']); ?>"
                 style="max-height: 300px; object-fit: cover;">
        </div>
        <div class="col-md-8">
            <h4 class="mb-3"><?php echo sanitize($item['title']); ?></h4>
            
            <?php if ($itemType === 'book' && $item['author']): ?>
            <p class="text-muted mb-2"><strong>Author:</strong> <?php echo sanitize($item['author']); ?></p>
            <?php endif; ?>
            
            <?php if ($item['category_name']): ?>
            <p class="mb-2">
                <span class="badge bg-primary"><?php echo sanitize($item['category_name']); ?></span>
                
                <?php if ($itemType === 'project' && $item['difficulty_level']): ?>
                <span class="badge <?php 
                    echo $item['difficulty_level'] === 'beginner' ? 'bg-success' : 
                        ($item['difficulty_level'] === 'intermediate' ? 'bg-warning' : 'bg-danger'); 
                ?>"><?php echo ucfirst($item['difficulty_level']); ?></span>
                <?php endif; ?>
                
                <?php if ($itemType === 'game' && $item['genre']): ?>
                <span class="badge bg-info"><?php echo sanitize($item['genre']); ?></span>
                <?php endif; ?>
            </p>
            <?php endif; ?>
            
            <?php if ($itemType === 'project'): ?>
            <div class="mb-3">
                <strong>Technologies:</strong><br>
                <span class="badge bg-light text-dark me-1 mb-1">Modern Framework</span>
                <span class="badge bg-light text-dark me-1 mb-1">Database</span>
                <span class="badge bg-light text-dark me-1 mb-1">API Integration</span>
            </div>
            <?php endif; ?>
            
            <!-- Rating -->
            <div class="d-flex align-items-center mb-3">
                <div class="text-warning me-3">
                    <?php 
                    $rating = $item['rating'] ?? 0;
                    for ($i = 1; $i <= 5; $i++): 
                    ?>
                        <i class="<?php echo $i <= $rating ? 'fas' : 'far'; ?> fa-star"></i>
                    <?php endfor; ?>
                    <span class="text-muted ms-2"><?php echo number_format($rating, 1); ?> (<?php echo $item['reviews_count'] ?? 0; ?> reviews)</span>
                </div>
            </div>
            
            <!-- Price -->
            <div class="mb-3">
                <?php if ($item['price'] > 0): ?>
                    <h4 class="text-primary mb-0">$<?php echo number_format($item['price'], 2); ?></h4>
                <?php else: ?>
                    <h4 class="text-success mb-0">Free</h4>
                <?php endif; ?>
            </div>
            
            <!-- Actions -->
            <div class="d-flex gap-2 mb-3">
                <?php if ($item['file_path']): ?>
                    <a href="<?php echo upload($itemType . 's/' . $item['file_path']); ?>" 
                       class="btn btn-success" target="_blank">
                        <i class="fas fa-download me-2"></i>Preview/Download
                    </a>
                <?php endif; ?>
                
                <?php if ($userOwnsItem): ?>
                    <button class="btn btn-info" disabled>
                        <i class="fas fa-check me-2"></i>Owned
                    </button>
                <?php else: ?>
                    <?php if (isLoggedIn()): ?>
                    <button class="btn btn-primary add-to-cart" 
                            data-item-id="<?php echo $item['id']; ?>" 
                            data-item-type="<?php echo $itemType; ?>">
                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                    </button>
                    <?php else: ?>
                    <a href="<?php echo BASE_PATH; ?>/auth/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Login to Purchase
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (isLoggedIn()): ?>
                <button class="btn btn-outline-danger btn-wishlist-toggle <?php echo $inWishlist ? 'active' : ''; ?>" 
                        data-item-id="<?php echo $item['id']; ?>" 
                        data-item-type="<?php echo $itemType; ?>"
                        title="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                    <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart me-2"></i>
                    <?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Description -->
    <?php if ($item['description']): ?>
    <div class="row mt-4">
        <div class="col-12">
            <h5>Description</h5>
            <p class="text-muted"><?php echo nl2br(sanitize($item['description'])); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Additional Information -->
    <div class="row mt-4">
        <div class="col-12">
            <h5>Details</h5>
            <div class="row">
                <?php if ($itemType === 'book'): ?>
                    <?php if ($item['pages']): ?>
                    <div class="col-sm-6">
                        <strong>Pages:</strong> <?php echo $item['pages']; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($item['language']) && $item['language']): ?>
                    <div class="col-sm-6">
                        <strong>Language:</strong> <?php echo sanitize($item['language']); ?>
                    </div>
                    <?php endif; ?>
                    <div class="col-sm-6">
                        <strong>Downloads:</strong> <?php echo number_format($item['downloads_count'] ?? 0); ?>
                    </div>
                <?php elseif ($itemType === 'project'): ?>
                    <?php if ($item['demo_url']): ?>
                    <div class="col-sm-6">
                        <strong>Demo:</strong> 
                        <a href="<?php echo sanitize($item['demo_url']); ?>" target="_blank" rel="noopener">
                            View Demo <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="col-sm-6">
                        <strong>Downloads:</strong> <?php echo number_format($item['downloads_count'] ?? 0); ?>
                    </div>
                <?php elseif ($itemType === 'game'): ?>
                    <div class="col-sm-6">
                        <strong>Plays:</strong> <?php echo number_format($item['plays_count'] ?? 0); ?>
                    </div>
                    <?php if ($item['min_players'] || $item['max_players']): ?>
                    <div class="col-sm-6">
                        <strong>Players:</strong> 
                        <?php 
                        if ($item['min_players'] && $item['max_players']) {
                            echo $item['min_players'] . '-' . $item['max_players'];
                        } elseif ($item['min_players']) {
                            echo $item['min_players'] . '+';
                        } else {
                            echo 'Up to ' . $item['max_players'];
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="col-sm-6">
                    <strong>Added:</strong> <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                </div>
                <div class="col-sm-6">
                    <strong>Last Updated:</strong> <?php echo date('M j, Y', strtotime($item['updated_at'])); ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    $html = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    error_log("Quick view error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while loading the content'
    ]);
}
?>