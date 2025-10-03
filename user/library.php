<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();

// Filter options
$filterType = $_GET['type'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$validTypes = ['all', 'book', 'project', 'game'];

if (!in_array($filterType, $validTypes)) {
    $filterType = 'all';
}

// Get user's purchased items from completed orders - Fixed for ONLY_FULL_GROUP_BY
$sql = "SELECT 
    oi.item_type,
    oi.item_id,
    oi.item_title,
    oi.unit_price,
    oi.quantity,
    MAX(o.created_at) as purchase_date,
    CASE 
        WHEN oi.item_type = 'book' THEN MAX(b.cover_image)
        WHEN oi.item_type = 'project' THEN MAX(p.cover_image)
        WHEN oi.item_type = 'game' THEN MAX(g.thumbnail)
    END as image,
    CASE 
        WHEN oi.item_type = 'book' THEN MAX(b.file_path)
        WHEN oi.item_type = 'project' THEN MAX(p.file_path)
        WHEN oi.item_type = 'game' THEN MAX(g.file_path)
    END as download_link,
    CASE 
        WHEN oi.item_type = 'book' THEN MAX(b.description)
        WHEN oi.item_type = 'project' THEN MAX(p.description)
        WHEN oi.item_type = 'game' THEN MAX(g.description)
    END as description,
    CASE 
        WHEN oi.item_type = 'book' THEN MAX(b.author)
        WHEN oi.item_type = 'project' THEN 'Project'
        WHEN oi.item_type = 'game' THEN 'Game'
    END as author,
    CASE 
        WHEN oi.item_type = 'book' THEN MAX(cat1.name)
        WHEN oi.item_type = 'project' THEN MAX(cat2.name)
        WHEN oi.item_type = 'game' THEN MAX(cat3.name)
    END as category_name,
    CASE 
        WHEN oi.item_type = 'book' THEN MAX(b.file_size)
        WHEN oi.item_type = 'project' THEN MAX(p.file_size)
        WHEN oi.item_type = 'game' THEN MAX(g.file_size)
    END as file_size
FROM order_items oi
JOIN orders o ON oi.order_id = o.id
LEFT JOIN books b ON oi.item_type = 'book' AND oi.item_id = b.id
LEFT JOIN projects p ON oi.item_type = 'project' AND oi.item_id = p.id
LEFT JOIN games g ON oi.item_type = 'game' AND oi.item_id = g.id
LEFT JOIN categories cat1 ON oi.item_type = 'book' AND b.category_id = cat1.id
LEFT JOIN categories cat2 ON oi.item_type = 'project' AND p.category_id = cat2.id
LEFT JOIN categories cat3 ON oi.item_type = 'game' AND g.category_id = cat3.id
WHERE o.user_id = ? AND o.status = 'completed'";

$params = [$_SESSION['user_id']];

// Add type filter
if ($filterType !== 'all') {
    $sql .= " AND oi.item_type = ?";
    $params[] = $filterType;
}

// Add search filter
if (!empty($search)) {
    $sql .= " AND oi.item_title LIKE ?";
    $params[] = "%$search%";
}

$sql .= " GROUP BY oi.item_type, oi.item_id, oi.item_title, oi.unit_price, oi.quantity ORDER BY purchase_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$libraryItems = $stmt->fetchAll();

// Get counts by type
$countSql = "SELECT oi.item_type, COUNT(DISTINCT CONCAT(oi.item_type, '-', oi.item_id)) as count
             FROM order_items oi
             JOIN orders o ON oi.order_id = o.id
             WHERE o.user_id = ? AND o.status = 'completed'
             GROUP BY oi.item_type";

$countStmt = $db->prepare($countSql);
$countStmt->execute([$_SESSION['user_id']]);
$typeCounts = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalCount = array_sum($typeCounts);

// Downloads are now handled by the dedicated download.php file
// No special processing needed here as download links go directly to download.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Library - <?php echo APP_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <style>
        .library-item {
            border: none;
            border-radius: 15px;
            padding: 1.5rem;
            background: white;
            transition: all 0.4s ease;
            height: 100%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }
        
        .library-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }
        
        .library-item:hover::before {
            left: 100%;
        }
        
        .library-item:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            transform: translateY(-8px) scale(1.02);
        }
        
        .item-image {
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .download-btn {
            transition: all 0.3s ease;
        }
        
        .download-btn:hover {
            transform: scale(1.05);
        }
        
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .filter-badge:hover {
            transform: scale(1.05);
            text-decoration: none;
        }
        
        .filter-badge.active {
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .search-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
        }
        
        .quick-action-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            background: white;
        }
        
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
    </style>
</head>
<body data-logged-in="true">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="main-content">
        <div class="container py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="display-6">
                        <i class="fas fa-book-open text-primary me-3"></i>My Digital Library
                    </h1>
                    <p class="text-muted">Access all your purchased books, projects, and games</p>
                </div>
            </div>

            <!-- Library Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="quick-action-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-wrapper bg-primary">
                                    <i class="fas fa-download text-white"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-1"><?php echo $totalCount; ?> Items Ready</h5>
                                    <p class="text-muted mb-0">Available for download</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="quick-action-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-wrapper bg-success">
                                    <i class="fas fa-clock text-white"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-1">Recently Added</h5>
                                    <p class="text-muted mb-0">
                                        <?php 
                                        if (!empty($libraryItems)) {
                                            $latestItem = reset($libraryItems);
                                            echo date('M j', strtotime($latestItem['purchase_date']));
                                        } else {
                                            echo 'No items';
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="quick-action-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="icon-wrapper bg-info">
                                        <i class="fas fa-plus text-white"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h5 class="mb-1">Add More</h5>
                                        <p class="text-muted mb-0">Browse catalog</p>
                                    </div>
                                </div>
                                <a href="<?php echo BASE_PATH; ?>/books.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="search-form">
                        <form method="GET" class="row align-items-end">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="search" class="form-label">Search your library</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Search by title..." value="<?php echo sanitize($search); ?>">
                                    <input type="hidden" name="type" value="<?php echo $filterType; ?>">
                                </div>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Search
                                </button>
                                <?php if (!empty($search)): ?>
                                <a href="?type=<?php echo $filterType; ?>" class="btn btn-outline-secondary ms-2">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <!-- Type Filters -->
                        <div class="mt-3">
                            <div class="d-flex flex-wrap gap-3 align-items-center">
                                <span class="fw-bold">Filter by type:</span>
                                
                                <a href="?type=all&search=<?php echo urlencode($search); ?>" class="filter-badge">
                                    <span class="badge bg-secondary <?php echo $filterType === 'all' ? 'active' : ''; ?>">
                                        All <span class="badge bg-light text-dark ms-1"><?php echo $totalCount; ?></span>
                                    </span>
                                </a>
                                
                                <a href="?type=book&search=<?php echo urlencode($search); ?>" class="filter-badge">
                                    <span class="badge bg-primary <?php echo $filterType === 'book' ? 'active' : ''; ?>">
                                        Books <span class="badge bg-light text-dark ms-1"><?php echo $typeCounts['book'] ?? 0; ?></span>
                                    </span>
                                </a>
                                
                                <a href="?type=project&search=<?php echo urlencode($search); ?>" class="filter-badge">
                                    <span class="badge bg-success <?php echo $filterType === 'project' ? 'active' : ''; ?>">
                                        Projects <span class="badge bg-light text-dark ms-1"><?php echo $typeCounts['project'] ?? 0; ?></span>
                                    </span>
                                </a>
                                
                                <a href="?type=game&search=<?php echo urlencode($search); ?>" class="filter-badge">
                                    <span class="badge bg-warning <?php echo $filterType === 'game' ? 'active' : ''; ?>">
                                        Games <span class="badge bg-light text-dark ms-1"><?php echo $typeCounts['game'] ?? 0; ?></span>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($libraryItems)): ?>
            <!-- Empty Library -->
            <div class="row">
                <div class="col-12">
                    <div class="text-center py-5">
                        <?php if (!empty($search)): ?>
                            <i class="fas fa-search-minus fa-4x text-muted mb-4"></i>
                            <h3>No items found</h3>
                            <p class="text-muted mb-4">No items match your search "<?php echo sanitize($search); ?>"</p>
                            <a href="?type=<?php echo $filterType; ?>" class="btn btn-primary">
                                <i class="fas fa-times me-2"></i>Clear Search
                            </a>
                        <?php elseif ($filterType !== 'all'): ?>
                            <i class="fas fa-folder-open fa-4x text-muted mb-4"></i>
                            <h3>No <?php echo $filterType; ?>s in your library</h3>
                            <p class="text-muted mb-4">You haven't purchased any <?php echo $filterType; ?>s yet</p>
                            <div class="d-flex flex-wrap gap-3 justify-content-center">
                                <a href="?type=all" class="btn btn-outline-secondary">
                                    <i class="fas fa-filter me-2"></i>View All Items
                                </a>
                                <a href="<?php echo BASE_PATH; ?>/public/<?php echo $filterType; ?>s.php" class="btn btn-primary">
                                    <i class="fas fa-shopping-bag me-2"></i>Shop <?php echo ucfirst($filterType); ?>s
                                </a>
                            </div>
                        <?php else: ?>
                            <i class="fas fa-book-open fa-4x text-muted mb-4"></i>
                            <h3>Your library is empty</h3>
                            <p class="text-muted mb-4">Start building your digital collection by purchasing books, projects, and games</p>
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
            <!-- Library Items -->
            <div class="row">
                <?php foreach ($libraryItems as $item): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="library-item">
                        <!-- Item Image -->
                        <div class="text-center mb-3">
                            <?php 
                            // Create more professional placeholders with book title
                            $bookTitle = urlencode(substr($item['item_title'], 0, 20));
                            switch ($item['item_type']) {
                                case 'book':
                                    $default = 'https://via.placeholder.com/200x250/4f46e5/ffffff?text=' . $bookTitle;
                                    break;
                                case 'project':
                                    $default = 'https://via.placeholder.com/200x250/059669/ffffff?text=' . $bookTitle;
                                    break;
                                case 'game':
                                    $default = 'https://via.placeholder.com/200x250/d97706/ffffff?text=' . $bookTitle;
                                    break;
                            }
                            
                            // Fix image path construction - database already contains folder paths
                            if ($item['image']) {
                                $imagePath = $item['image'];
                                
                                // Database already contains proper paths like 'books/filename.jpg'
                                // Just use the upload() function directly
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
                                 class="item-image img-fluid" 
                                 alt="<?php echo sanitize($item['item_title']); ?>"
                                 onerror="this.src='<?php echo $default; ?>'; this.onerror=null;"
                                 loading="lazy">
                        </div>
                        
                        <!-- Item Details -->
                        <div class="text-center mb-3">
                            <h5 class="mb-2"><?php echo sanitize($item['item_title']); ?></h5>
                            <p class="text-muted mb-2"><?php echo sanitize($item['author']); ?></p>
                            
                            <div class="d-flex justify-content-center gap-2 mb-2">
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
                        </div>
                        
                        <!-- Description -->
                        <?php if ($item['description']): ?>
                        <p class="text-muted small mb-3">
                            <?php echo sanitize(substr($item['description'], 0, 100)); ?>...
                        </p>
                        <?php endif; ?>
                        
                        <!-- Purchase Info -->
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                Purchased <?php echo date('M j, Y', strtotime($item['purchase_date'])); ?>
                            </small>
                            <?php if ($item['file_size']): ?>
                            <br>
                            <small class="text-muted">
                                <i class="fas fa-file me-1"></i>
                                <?php echo formatFileSize($item['file_size']); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Download Button -->
                        <div class="text-center">
                            <?php if ($item['download_link']): ?>
                            <a href="<?php echo BASE_PATH; ?>/user/download.php?type=<?php echo $item['item_type']; ?>&id=<?php echo $item['item_id']; ?>" 
                               class="btn btn-success download-btn w-100">
                                <i class="fas fa-download me-2"></i>Download Now
                            </a>
                            <?php else: ?>
                            <button class="btn btn-secondary w-100" disabled>
                                <i class="fas fa-clock me-2"></i>Coming Soon
                            </button>
                            <?php endif; ?>
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
    
    <script>
        // Add some interactivity for better UX
        document.querySelectorAll('.download-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const originalText = this.innerHTML;
                
                // Show loading state
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Preparing Download...';
                this.disabled = true;
                
                // Simulate download preparation
                setTimeout(() => {
                    window.location.href = this.href;
                    
                    // Reset button after delay
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 2000);
                }, 1000);
            });
        });
    </script>
</body>
</html>