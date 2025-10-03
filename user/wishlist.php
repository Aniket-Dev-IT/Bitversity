<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();

// Handle wishlist updates
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $itemId = intval($_POST['item_id'] ?? 0);
    $itemType = $_POST['item_type'] ?? '';
    
    try {
        $db->beginTransaction();
        
        switch ($action) {
            case 'remove_item':
                $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND item_type = ? AND item_id = ?");
                $stmt->execute([$_SESSION['user_id'], $itemType, $itemId]);
                $message = 'Item removed from wishlist';
                break;
                
            case 'add_to_cart':
                // Check if already in cart
                $checkStmt = $db->prepare("SELECT id FROM cart WHERE user_id = ? AND item_type = ? AND item_id = ?");
                $checkStmt->execute([$_SESSION['user_id'], $itemType, $itemId]);
                
                if ($checkStmt->fetch()) {
                    // Update quantity
                    $stmt = $db->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND item_type = ? AND item_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $itemType, $itemId]);
                    $message = 'Item quantity updated in cart';
                } else {
                    // Add to cart
                    $stmt = $db->prepare("INSERT INTO cart (user_id, item_type, item_id, quantity) VALUES (?, ?, ?, 1)");
                    $stmt->execute([$_SESSION['user_id'], $itemType, $itemId]);
                    $message = 'Item added to cart';
                }
                
                // Remove from wishlist
                $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND item_type = ? AND item_id = ?");
                $stmt->execute([$_SESSION['user_id'], $itemType, $itemId]);
                break;
                
            case 'clear_wishlist':
                $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $message = 'Wishlist cleared';
                break;
                
            case 'move_all_to_cart':
                // Get all wishlist items
                $wishlistStmt = $db->prepare("SELECT item_type, item_id FROM wishlist WHERE user_id = ?");
                $wishlistStmt->execute([$_SESSION['user_id']]);
                $wishlistItems = $wishlistStmt->fetchAll();
                
                foreach ($wishlistItems as $item) {
                    // Check if already in cart
                    $checkStmt = $db->prepare("SELECT id FROM cart WHERE user_id = ? AND item_type = ? AND item_id = ?");
                    $checkStmt->execute([$_SESSION['user_id'], $item['item_type'], $item['item_id']]);
                    
                    if ($checkStmt->fetch()) {
                        // Update quantity
                        $stmt = $db->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND item_type = ? AND item_id = ?");
                        $stmt->execute([$_SESSION['user_id'], $item['item_type'], $item['item_id']]);
                    } else {
                        // Add to cart
                        $stmt = $db->prepare("INSERT INTO cart (user_id, item_type, item_id, quantity) VALUES (?, ?, ?, 1)");
                        $stmt->execute([$_SESSION['user_id'], $item['item_type'], $item['item_id']]);
                    }
                }
                
                // Clear wishlist
                $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $message = 'All items moved to cart';
                break;
        }
        
        $db->commit();
        redirect(BASE_PATH . '/user/wishlist.php', $message, 'success');
        
    } catch (Exception $e) {
        $db->rollBack();
        redirect(BASE_PATH . '/user/wishlist.php', 'Error updating wishlist: ' . $e->getMessage(), 'error');
    }
}

// Filter by item type
$filterType = $_GET['type'] ?? 'all';
$validTypes = ['all', 'book', 'project', 'game'];
if (!in_array($filterType, $validTypes)) {
    $filterType = 'all';
}

// Get wishlist items with full details
$sql = "SELECT w.*, 
               CASE 
                   WHEN w.item_type = 'book' THEN b.title
                   WHEN w.item_type = 'project' THEN p.title
                   WHEN w.item_type = 'game' THEN g.title
               END as title,
               CASE 
                   WHEN w.item_type = 'book' THEN b.author
                   WHEN w.item_type = 'project' THEN 'Project'
                   WHEN w.item_type = 'game' THEN 'Game'
               END as author,
               CASE 
                   WHEN w.item_type = 'book' THEN b.price
                   WHEN w.item_type = 'project' THEN p.price
                   WHEN w.item_type = 'game' THEN g.price
               END as price,
               CASE 
                   WHEN w.item_type = 'book' THEN b.cover_image
                   WHEN w.item_type = 'project' THEN p.cover_image
                   WHEN w.item_type = 'game' THEN g.thumbnail
               END as image,
               CASE 
                   WHEN w.item_type = 'book' THEN b.description
                   WHEN w.item_type = 'project' THEN p.description
                   WHEN w.item_type = 'game' THEN g.description
               END as description,
               CASE 
                   WHEN w.item_type = 'book' THEN cat1.name
                   WHEN w.item_type = 'project' THEN cat2.name
                   WHEN w.item_type = 'game' THEN cat3.name
               END as category_name
        FROM wishlist w 
        LEFT JOIN books b ON w.item_type = 'book' AND w.item_id = b.id
        LEFT JOIN projects p ON w.item_type = 'project' AND w.item_id = p.id
        LEFT JOIN games g ON w.item_type = 'game' AND w.item_id = g.id
        LEFT JOIN categories cat1 ON w.item_type = 'book' AND b.category_id = cat1.id
        LEFT JOIN categories cat2 ON w.item_type = 'project' AND p.category_id = cat2.id
        LEFT JOIN categories cat3 ON w.item_type = 'game' AND g.category_id = cat3.id
        WHERE w.user_id = ?";

if ($filterType !== 'all') {
    $sql .= " AND w.item_type = ?";
}

$sql .= " ORDER BY w.added_at DESC";

$stmt = $db->prepare($sql);
if ($filterType !== 'all') {
    $stmt->execute([$_SESSION['user_id'], $filterType]);
} else {
    $stmt->execute([$_SESSION['user_id']]);
}
$wishlistItems = $stmt->fetchAll();

// Get count by type for filter badges
$typeCountStmt = $db->prepare("SELECT item_type, COUNT(*) as count FROM wishlist WHERE user_id = ? GROUP BY item_type");
$typeCountStmt->execute([$_SESSION['user_id']]);
$typeCounts = $typeCountStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalCount = array_sum($typeCounts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - <?php echo APP_NAME; ?></title>
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <style>
        .wishlist-item {
            transition: all 0.4s ease;
            border: none;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: white;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }
        
        .wishlist-item::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        
        .wishlist-item:hover::after {
            transform: scaleY(1);
        }
        
        .wishlist-item:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transform: translateY(-4px);
        }
        .item-image {
            height: 150px;
            width: 120px;
            object-fit: cover;
            border-radius: 8px;
        }
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .filter-badge:hover {
            transform: scale(1.05);
        }
        .filter-badge.active {
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body data-logged-in="true">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="main-content">
        <div class="container py-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="display-6">
                        <i class="fas fa-heart text-danger me-3"></i>My Wishlist
                    </h1>
                    <p class="text-muted"><?php echo $totalCount; ?> item<?php echo $totalCount !== 1 ? 's' : ''; ?> saved for later</p>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <span class="fw-bold">Filter by type:</span>
                        
                        <a href="?type=all" class="text-decoration-none">
                            <span class="badge bg-secondary filter-badge <?php echo $filterType === 'all' ? 'active' : ''; ?>">
                                All <span class="badge bg-light text-dark ms-1"><?php echo $totalCount; ?></span>
                            </span>
                        </a>
                        
                        <a href="?type=book" class="text-decoration-none">
                            <span class="badge bg-primary filter-badge <?php echo $filterType === 'book' ? 'active' : ''; ?>">
                                Books <span class="badge bg-light text-dark ms-1"><?php echo $typeCounts['book'] ?? 0; ?></span>
                            </span>
                        </a>
                        
                        <a href="?type=project" class="text-decoration-none">
                            <span class="badge bg-success filter-badge <?php echo $filterType === 'project' ? 'active' : ''; ?>">
                                Projects <span class="badge bg-light text-dark ms-1"><?php echo $typeCounts['project'] ?? 0; ?></span>
                            </span>
                        </a>
                        
                        <a href="?type=game" class="text-decoration-none">
                            <span class="badge bg-warning filter-badge <?php echo $filterType === 'game' ? 'active' : ''; ?>">
                                Games <span class="badge bg-light text-dark ms-1"><?php echo $typeCounts['game'] ?? 0; ?></span>
                            </span>
                        </a>
                    </div>
                </div>
            </div>

            <?php if (empty($wishlistItems)): ?>
            <!-- Empty Wishlist -->
            <div class="row">
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-heart-broken fa-4x text-muted mb-4"></i>
                        <h3>Your wishlist is empty</h3>
                        <?php if ($filterType !== 'all'): ?>
                        <p class="text-muted mb-4">No <?php echo $filterType; ?>s in your wishlist yet</p>
                        <div class="d-flex flex-wrap gap-3 justify-content-center">
                            <a href="?type=all" class="btn btn-outline-secondary">
                                <i class="fas fa-filter me-2"></i>View All Items
                            </a>
                        </div>
                        <?php else: ?>
                        <p class="text-muted mb-4">Start adding items you love to save them for later</p>
                        <div class="d-flex flex-wrap gap-3 justify-content-center">
                            <a href="<?php echo BASE_PATH; ?>/public/books.php" class="btn btn-primary">
                                <i class="fas fa-book me-2"></i>Browse Books
                            </a>
                            <a href="<?php echo BASE_PATH; ?>/public/projects.php" class="btn btn-success">
                                <i class="fas fa-code me-2"></i>Explore Projects
                            </a>
                            <a href="<?php echo BASE_PATH; ?>/public/games.php" class="btn btn-warning">
                                <i class="fas fa-gamepad me-2"></i>Play Games
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Wishlist Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex flex-wrap gap-3">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="move_all_to_cart">
                            <button type="submit" class="btn btn-primary" 
                                    onclick="return confirm('Move all items to cart?')">
                                <i class="fas fa-shopping-cart me-2"></i>Move All to Cart
                            </button>
                        </form>
                        
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="clear_wishlist">
                            <button type="submit" class="btn btn-outline-danger" 
                                    onclick="return confirm('Are you sure you want to clear your wishlist?')">
                                <i class="fas fa-trash me-2"></i>Clear Wishlist
                            </button>
                        </form>
                        
                        <a href="<?php echo BASE_PATH; ?>/user/cart.php" class="btn btn-outline-primary">
                            <i class="fas fa-shopping-cart me-2"></i>View Cart
                        </a>
                    </div>
                </div>
            </div>

            <!-- Wishlist Items -->
            <div class="row">
                <?php foreach ($wishlistItems as $item): ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="wishlist-item h-100">
                        <div class="row h-100">
                            <!-- Item Image -->
                            <div class="col-4">
                                <?php 
                                // Create professional placeholders with item title
                                $itemTitle = urlencode(substr($item['title'], 0, 15));
                                switch ($item['item_type']) {
                                    case 'book':
                                        $default = 'https://via.placeholder.com/150x200/4f46e5/ffffff?text=' . $itemTitle;
                                        break;
                                    case 'project':
                                        $default = 'https://via.placeholder.com/150x200/059669/ffffff?text=' . $itemTitle;
                                        break;
                                    case 'game':
                                        $default = 'https://via.placeholder.com/150x200/d97706/ffffff?text=' . $itemTitle;
                                        break;
                                }
                                
                                // Fix image path construction - database already contains folder paths
                                if ($item['image']) {
                                    $imagePath = $item['image'];
                                    
                                    // Database already contains proper paths like 'books/filename.jpg'
                                    $imageSrc = upload($imagePath);
                                    
                                    // Fallback check if file doesn't exist
                                    $actualPath = __DIR__ . '/../uploads/' . $imagePath;
                                    if (!file_exists($actualPath)) {
                                        // Try alternative paths as backup
                                        $altPaths = [
                                            $item['item_type'] . 's/' . basename($imagePath),
                                            basename($imagePath)
                                        ];
                                        
                                        $found = false;
                                        foreach ($altPaths as $altPath) {
                                            if (file_exists(__DIR__ . '/../uploads/' . $altPath)) {
                                                $imageSrc = upload($altPath);
                                                $found = true;
                                                break;
                                            }
                                        }
                                        
                                        if (!$found) {
                                            $imageSrc = $default;
                                        }
                                    }
                                } else {
                                    $imageSrc = $default;
                                }
                                
                                // Debug information (remove in production)
                                if (DEVELOPMENT_MODE && isset($_GET['debug'])) {
                                    echo "<!-- Debug: Item Type: {$item['item_type']}, Image: {$item['image']}, Using: $imageSrc -->";
                                }
                                ?>
                                <img src="<?php echo $imageSrc; ?>" 
                                     class="item-image w-100" 
                                     alt="<?php echo sanitize($item['title']); ?>"
                                     onerror="this.src='<?php echo $default; ?>'; this.onerror=null;"
                                     loading="lazy">
                            </div>
                            
                            <!-- Item Details -->
                            <div class="col-8 d-flex flex-column">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-1 fw-bold"><?php echo sanitize($item['title']); ?></h6>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="remove_item">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <input type="hidden" name="item_type" value="<?php echo $item['item_type']; ?>">
                                            <button type="submit" class="btn btn-link text-danger p-0" title="Remove from wishlist">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <p class="text-muted small mb-1"><?php echo sanitize($item['author']); ?></p>
                                    
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge bg-<?php 
                                            echo match($item['item_type']) {
                                                'book' => 'primary',
                                                'project' => 'success',
                                                'game' => 'warning',
                                                default => 'secondary'
                                            };
                                        ?>"><?php echo ucfirst($item['item_type']); ?></span>
                                        
                                        <?php if ($item['category_name']): ?>
                                        <span class="badge bg-light text-dark"><?php echo sanitize($item['category_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($item['description']): ?>
                                    <p class="text-muted small mb-2">
                                        <?php echo sanitize(substr($item['description'], 0, 60)); ?>...
                                    </p>
                                    <?php endif; ?>
                                    
                                    <div class="text-primary fw-bold mb-2">
                                        <?php echo formatPrice($item['price']); ?>
                                    </div>
                                    
                                    <small class="text-muted">
                                        Added <?php echo date('M j, Y', strtotime($item['added_at'])); ?>
                                    </small>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="mt-3">
                                    <form method="POST" class="d-inline w-100">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                        <input type="hidden" name="item_type" value="<?php echo $item['item_type']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/main.js'); ?>"></script>
</body>
</html>