<?php
/**
 * Admin Content Management
 * 
 * Comprehensive interface for managing books, projects, games, and categories
 * with full CRUD operations, search, filtering, and bulk actions.
 */

require_once 'includes/auth.php';

$current_admin = getCurrentAdmin();
$flash_message = getAdminFlashMessage();

// Get current content type and page
$content_type = $_GET['type'] ?? 'books';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$search = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Validate content type
$valid_types = ['books', 'projects', 'games', 'categories'];
if (!in_array($content_type, $valid_types)) {
    $content_type = 'books';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        setAdminFlashMessage('Invalid security token. Please try again.', 'error');
        header('Location: content.php?type=' . $content_type);
        exit();
    }
    
    try {
        switch ($action) {
            case 'toggle_status':
                $item_id = intval($_POST['item_id'] ?? 0);
                $new_status = $_POST['new_status'] === '1' ? 1 : 0;
                
                $stmt = $db->prepare("UPDATE {$content_type} SET is_active = ? WHERE id = ?");
                $stmt->execute([$new_status, $item_id]);
                
                $status_text = $new_status ? 'activated' : 'deactivated';
                logAdminActivity('content_status_change', "Content item {$status_text}", [
                    'content_type' => $content_type,
                    'item_id' => $item_id,
                    'new_status' => $new_status
                ]);
                
                setAdminFlashMessage("Item {$status_text} successfully.", 'success');
                break;
                
            case 'delete_item':
                $item_id = intval($_POST['item_id'] ?? 0);
                
                $stmt = $db->prepare("DELETE FROM {$content_type} WHERE id = ?");
                $stmt->execute([$item_id]);
                
                logAdminActivity('content_deleted', "Content item deleted", [
                    'content_type' => $content_type,
                    'item_id' => $item_id
                ]);
                
                setAdminFlashMessage('Item deleted successfully.', 'success');
                break;
                
            case 'bulk_action':
                $bulk_action = $_POST['bulk_action'] ?? '';
                $selected_items = $_POST['selected_items'] ?? [];
                
                if ($bulk_action && !empty($selected_items)) {
                    $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
                    
                    switch ($bulk_action) {
                        case 'activate':
                            $stmt = $db->prepare("UPDATE {$content_type} SET is_active = 1 WHERE id IN ({$placeholders})");
                            $stmt->execute($selected_items);
                            setAdminFlashMessage(count($selected_items) . ' items activated successfully.', 'success');
                            break;
                            
                        case 'deactivate':
                            $stmt = $db->prepare("UPDATE {$content_type} SET is_active = 0 WHERE id IN ({$placeholders})");
                            $stmt->execute($selected_items);
                            setAdminFlashMessage(count($selected_items) . ' items deactivated successfully.', 'success');
                            break;
                            
                        case 'delete':
                            $stmt = $db->prepare("DELETE FROM {$content_type} WHERE id IN ({$placeholders})");
                            $stmt->execute($selected_items);
                            setAdminFlashMessage(count($selected_items) . ' items deleted successfully.', 'success');
                            break;
                    }
                    
                    logAdminActivity('bulk_action', "Bulk action '{$bulk_action}' performed", [
                        'content_type' => $content_type,
                        'action' => $bulk_action,
                        'items_count' => count($selected_items)
                    ]);
                }
                break;
        }
        
    } catch (PDOException $e) {
        error_log("Content management error: " . $e->getMessage());
        setAdminFlashMessage('An error occurred. Please try again.', 'error');
    }
    
    header('Location: content.php?type=' . $content_type . 
           ($search ? '&search=' . urlencode($search) : '') . 
           ($category_filter ? '&category=' . urlencode($category_filter) : '') .
           ($status_filter ? '&status=' . urlencode($status_filter) : '') .
           '&page=' . $page);
    exit();
}

try {
    // Build query based on content type
    $where_conditions = ['1=1'];
    $params = [];
    
    // Search functionality
    if ($search) {
        if ($content_type === 'categories') {
            $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        } else {
            $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
    }
    
    // Category filter (for books, projects, games)
    if ($category_filter && $content_type !== 'categories') {
        $where_conditions[] = "category_id = ?";
        $params[] = intval($category_filter);
    }
    
    // Status filter
    if ($status_filter !== '') {
        $where_conditions[] = "is_active = ?";
        $params[] = intval($status_filter);
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Count total items for pagination
    $count_sql = "SELECT COUNT(*) FROM {$content_type} WHERE {$where_clause}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_items = $stmt->fetchColumn();
    
    // Calculate pagination
    $pagination = paginate($total_items, $page, $per_page);
    
    // Get content items
    $order_by = match($content_type) {
        'categories' => 'sort_order ASC, name ASC',
        default => 'created_at DESC'
    };
    
    $sql = "SELECT * FROM {$content_type} WHERE {$where_clause} ORDER BY {$order_by} LIMIT {$per_page} OFFSET {$pagination['offset']}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $content_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories for filter dropdown
    $categories = [];
    if ($content_type !== 'categories') {
        $stmt = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get content statistics
    $stats = [];
    foreach ($valid_types as $type) {
        $stmt = $db->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent
            FROM {$type}");
        $stats[$type] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    error_log("Content fetch error: " . $e->getMessage());
    $content_items = [];
    $categories = [];
    $total_items = 0;
    $pagination = paginate(0, 1, $per_page);
    $stats = [];
}

// Define column headers for each content type
$columns = [
    'books' => ['Title', 'Author', 'Category', 'Price', 'Views', 'Downloads', 'Status', 'Created'],
    'projects' => ['Title', 'Category', 'Price', 'Difficulty', 'Views', 'Downloads', 'Status', 'Created'],
    'games' => ['Title', 'Category', 'Price', 'Type', 'Views', 'Plays', 'Status', 'Created'],
    'categories' => ['Name', 'Slug', 'Icon', 'Sort Order', 'Status', 'Created']
];

require_once 'includes/layout.php';
renderAdminHeader('Content Management', 'Manage books, projects, games, and categories');
?>

<style>
        .content-tabs {
            border-bottom: 2px solid var(--secondary-color);
            background: white;
            padding: 1rem 0 0;
            margin-bottom: 2rem;
        }

        .content-tabs .nav-link {
            border: none;
            border-radius: 10px 10px 0 0;
            padding: 1rem 1.5rem;
            color: #6b7280;
            font-weight: 600;
            position: relative;
            margin-right: 0.5rem;
        }

        .content-tabs .nav-link.active {
            background: white;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        .content-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .content-table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--secondary-color);
            display: flex;
            justify-content: between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .search-filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input {
            width: 300px;
            border: 2px solid var(--secondary-color);
            border-radius: 8px;
            padding: 0.5rem 1rem;
        }

        .search-input:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .filter-select {
            min-width: 150px;
            border: 2px solid var(--secondary-color);
            border-radius: 8px;
            padding: 0.5rem;
        }

        .bulk-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }

        .content-table {
            width: 100%;
            margin: 0;
        }

        .content-table th {
            background: var(--light-color);
            color: var(--dark-color);
            font-weight: 600;
            padding: 1rem;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .content-table td {
            padding: 1rem;
            border-top: 1px solid var(--secondary-color);
            vertical-align: middle;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .action-btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            margin: 0 0.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: var(--info-color);
            color: white;
        }

        .btn-toggle {
            background: var(--warning-color);
            color: white;
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
        }

        .content-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .pagination-container {
            padding: 1.5rem;
            border-top: 1px solid var(--secondary-color);
            display: flex;
            justify-content: between;
            align-items: center;
        }

        @media (max-width: 768px) {
            .search-filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                width: 100%;
            }
        }
    </style>
</head>
<?php if ($flash_message): ?>
    <div class="alert alert-<?php echo $flash_message['type'] === 'error' ? 'danger' : $flash_message['type']; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($flash_message['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

            <!-- Content Type Tabs -->
            <div class="content-tabs">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $content_type === 'books' ? 'active' : ''; ?>" 
                           href="?type=books">
                            <i class="fas fa-book me-2"></i>Books
                            <span class="badge bg-primary ms-2"><?php echo $stats['books']['total'] ?? 0; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $content_type === 'projects' ? 'active' : ''; ?>" 
                           href="?type=projects">
                            <i class="fas fa-code me-2"></i>Projects
                            <span class="badge bg-primary ms-2"><?php echo $stats['projects']['total'] ?? 0; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $content_type === 'games' ? 'active' : ''; ?>" 
                           href="?type=games">
                            <i class="fas fa-gamepad me-2"></i>Games
                            <span class="badge bg-primary ms-2"><?php echo $stats['games']['total'] ?? 0; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $content_type === 'categories' ? 'active' : ''; ?>" 
                           href="?type=categories">
                            <i class="fas fa-folder me-2"></i>Categories
                            <span class="badge bg-primary ms-2"><?php echo $stats['categories']['total'] ?? 0; ?></span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Statistics -->
            <div class="content-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats[$content_type]['total'] ?? 0); ?></div>
                    <div class="stat-label">Total <?php echo ucfirst($content_type); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats[$content_type]['active'] ?? 0); ?></div>
                    <div class="stat-label">Active Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats[$content_type]['recent'] ?? 0); ?></div>
                    <div class="stat-label">Added This Month</div>
                </div>
            </div>

            <!-- Content Table -->
            <div class="content-table-container">
                <div class="table-header">
                    <div class="search-filters">
                        <form method="GET" class="d-flex gap-3 align-items-center">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($content_type); ?>">
                            
                            <input type="text" 
                                   name="search" 
                                   class="search-input" 
                                   placeholder="Search <?php echo $content_type; ?>..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            
                            <?php if ($content_type !== 'categories' && !empty($categories)): ?>
                                <select name="category" class="filter-select">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                                <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            
                            <select name="status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            
                            <a href="?type=<?php echo $content_type; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </form>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="content_form.php?type=<?php echo $content_type; ?>" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Add New
                        </a>
                    </div>
                </div>

                <form method="POST" id="bulkForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="bulk_action">
                    
                    <div class="bulk-actions px-3 py-2 border-bottom">
                        <select name="bulk_action" class="form-select" style="width: 200px;">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="btn btn-outline-primary btn-sm">Apply</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table content-table">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <?php foreach ($columns[$content_type] as $column): ?>
                                        <th><?php echo $column; ?></th>
                                    <?php endforeach; ?>
                                    <th width="150">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($content_items as $item): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_items[]" value="<?php echo $item['id']; ?>" class="item-checkbox">
                                        </td>
                                        
                                        <?php if ($content_type === 'books'): ?>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($item['cover_image']): ?>
                                                        <img src="../<?php echo htmlspecialchars($item['cover_image']); ?>" 
                                                             alt="Cover" class="content-image me-3">
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($item['title']); ?></div>
                                                        <small class="text-muted">ISBN: <?php echo htmlspecialchars($item['isbn'] ?: 'N/A'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['author']); ?></td>
                                            <td>
                                                <?php
                                                // Get category name
                                                if ($item['category_id']) {
                                                    $cat_stmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
                                                    $cat_stmt->execute([$item['category_id']]);
                                                    $cat_name = $cat_stmt->fetchColumn();
                                                    echo htmlspecialchars($cat_name ?: 'Unknown');
                                                } else {
                                                    echo 'No Category';
                                                }
                                                ?>
                                            </td>
                                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                                            <td><?php echo number_format($item['views_count']); ?></td>
                                            <td><?php echo number_format($item['downloads_count']); ?></td>
                                            
                                        <?php elseif ($content_type === 'projects'): ?>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($item['title']); ?></div>
                                                <small class="text-muted">Duration: <?php echo $item['duration_hours']; ?>h</small>
                                            </td>
                                            <td>
                                                <?php
                                                if ($item['category_id']) {
                                                    $cat_stmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
                                                    $cat_stmt->execute([$item['category_id']]);
                                                    $cat_name = $cat_stmt->fetchColumn();
                                                    echo htmlspecialchars($cat_name ?: 'Unknown');
                                                } else {
                                                    echo 'No Category';
                                                }
                                                ?>
                                            </td>
                                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo ucfirst($item['difficulty']); ?></span>
                                            </td>
                                            <td><?php echo number_format($item['views_count']); ?></td>
                                            <td><?php echo number_format($item['downloads_count']); ?></td>
                                            
                                        <?php elseif ($content_type === 'games'): ?>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($item['thumbnail']): ?>
                                                        <img src="../<?php echo htmlspecialchars($item['thumbnail']); ?>" 
                                                             alt="Thumbnail" class="content-image me-3">
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($item['title']); ?></div>
                                                        <small class="text-muted">Platform: <?php echo ucfirst($item['platform']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                if ($item['category_id']) {
                                                    $cat_stmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
                                                    $cat_stmt->execute([$item['category_id']]);
                                                    $cat_name = $cat_stmt->fetchColumn();
                                                    echo htmlspecialchars($cat_name ?: 'Unknown');
                                                } else {
                                                    echo 'No Category';
                                                }
                                                ?>
                                            </td>
                                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-success"><?php echo ucfirst($item['game_type']); ?></span>
                                            </td>
                                            <td><?php echo number_format($item['views_count']); ?></td>
                                            <td><?php echo number_format($item['plays_count'] ?? 0); ?></td>
                                            
                                        <?php else: // categories ?>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($item['icon']): ?>
                                                        <i class="fas fa-<?php echo htmlspecialchars($item['icon']); ?> me-3" 
                                                           style="color: <?php echo htmlspecialchars($item['color']); ?>"></i>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($item['name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($item['description'] ?: '', 0, 50)); ?>...</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($item['slug']); ?></code></td>
                                            <td>
                                                <?php if ($item['icon']): ?>
                                                    <i class="fas fa-<?php echo htmlspecialchars($item['icon']); ?>" 
                                                       style="color: <?php echo htmlspecialchars($item['color']); ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $item['sort_order']; ?></td>
                                        <?php endif; ?>
                                        
                                        <td>
                                            <span class="status-badge <?php echo $item['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($item['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="content_form.php?type=<?php echo $content_type; ?>&id=<?php echo $item['id']; ?>" 
                                                   class="action-btn btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo $item['is_active'] ? '0' : '1'; ?>">
                                                    <button type="submit" class="action-btn btn-toggle" 
                                                            title="<?php echo $item['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas fa-<?php echo $item['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Are you sure you want to delete this item?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="action-btn btn-delete" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($content_items)): ?>
                                    <tr>
                                        <td colspan="<?php echo count($columns[$content_type]) + 3; ?>" class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No <?php echo $content_type; ?> found</p>
                                            <a href="content_form.php?type=<?php echo $content_type; ?>" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Add First Item
                                            </a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination-container">
                        <div>
                            Showing <?php echo number_format($pagination['offset'] + 1); ?> to 
                            <?php echo number_format(min($pagination['offset'] + $per_page, $total_items)); ?> of 
                            <?php echo number_format($total_items); ?> results
                        </div>
                        <nav>
                            <ul class="pagination mb-0">
                                <?php if ($pagination['has_previous']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?type=<?php echo $content_type; ?>&page=<?php echo $pagination['previous_page']; ?><?php 
                                            echo $search ? '&search=' . urlencode($search) : '';
                                            echo $category_filter ? '&category=' . urlencode($category_filter) : '';
                                            echo $status_filter ? '&status=' . urlencode($status_filter) : '';
                                        ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?type=<?php echo $content_type; ?>&page=<?php echo $i; ?><?php 
                                            echo $search ? '&search=' . urlencode($search) : '';
                                            echo $category_filter ? '&category=' . urlencode($category_filter) : '';
                                            echo $status_filter ? '&status=' . urlencode($status_filter) : '';
                                        ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($pagination['has_next']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?type=<?php echo $content_type; ?>&page=<?php echo $pagination['next_page']; ?><?php 
                                            echo $search ? '&search=' . urlencode($search) : '';
                                            echo $category_filter ? '&category=' . urlencode($category_filter) : '';
                                            echo $status_filter ? '&status=' . urlencode($status_filter) : '';
                                        ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>

    <script>
        // Select all checkbox functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        document.querySelectorAll('.item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.item-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
                const selectAll = document.getElementById('selectAll');
                
                selectAll.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
                selectAll.checked = checkedCheckboxes.length === allCheckboxes.length;
            });
        });

        // Bulk actions form validation
        document.getElementById('bulkForm').addEventListener('submit', function(e) {
            const selectedItems = document.querySelectorAll('.item-checkbox:checked');
            const bulkAction = document.querySelector('[name="bulk_action"]').value;
            
            if (!bulkAction) {
                e.preventDefault();
                alert('Please select a bulk action.');
                return;
            }
            
            if (selectedItems.length === 0) {
                e.preventDefault();
                alert('Please select at least one item.');
                return;
            }
            
            if (bulkAction === 'delete') {
                if (!confirm(`Are you sure you want to delete ${selectedItems.length} selected item(s)?`)) {
                    e.preventDefault();
                    return;
                }
            }
        });
    </script>

<?php renderAdminFooter(); ?>
