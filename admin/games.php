<?php
require_once 'includes/auth.php';
require_once '../includes/upload.php';

$current_admin = getCurrentAdmin();
$flash_message = getAdminFlashMessage();

$upload = new UploadService();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        setAdminFlashMessage('Invalid security token. Please try again.', 'error');
        header('Location: games.php');
        exit();
    }
    
    try {
        if ($action === 'add' || $action === 'edit') {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $category_id = (int)$_POST['category_id'];
            $price = (float)$_POST['price'];
            $genre = trim($_POST['genre']);
            $platform = trim($_POST['platform']);
            $game_file_url = trim($_POST['game_file_url']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($title) || empty($description) || $category_id <= 0) {
                throw new Exception('Please fill in all required fields.');
            }
            
            $cover_image = null;
            $game_file = null;
            
            // Handle cover image upload
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['size'] > 0) {
                $upload_result = $upload->uploadFile($_FILES['cover_image'], 'image', 'games');
                if (!$upload_result['success']) {
                    throw new Exception('Cover image upload failed: ' . $upload_result['error']);
                }
                $cover_image = $upload_result['path'];
            }
            
            // Handle game file upload
            if (isset($_FILES['game_file']) && $_FILES['game_file']['size'] > 0) {
                $upload_result = $upload->uploadFile($_FILES['game_file'], 'document', 'games');
                if (!$upload_result['success']) {
                    throw new Exception('Game file upload failed: ' . $upload_result['error']);
                }
                $game_file = $upload_result['path'];
            }
                
            if ($action === 'add') {
                $sql = "INSERT INTO games (title, description, category_id, price, genre, platform, game_file_url, is_active, created_at, updated_at";
                $params = [$title, $description, $category_id, $price, $genre, $platform, $game_file_url, $is_active];
                
                if ($cover_image) {
                    $sql .= ", cover_image";
                    $params[] = $cover_image;
                }
                if ($game_file) {
                    $sql .= ", file_path";
                    $params[] = $game_file;
                }
                
                $sql .= ") VALUES (" . str_repeat('?,', count($params) - 1) . "?, NOW(), NOW())";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                logAdminActivity('game_created', 'Game created', ['game_title' => $title]);
                setAdminFlashMessage('Game added successfully!', 'success');
            } else {
                $game_id = (int)$_POST['game_id'];
                $sql = "UPDATE games SET title=?, description=?, category_id=?, price=?, genre=?, platform=?, game_file_url=?, is_active=?, updated_at=NOW()";
                $params = [$title, $description, $category_id, $price, $genre, $platform, $game_file_url, $is_active];
                
                if ($cover_image) {
                    $sql .= ", cover_image=?";
                    $params[] = $cover_image;
                }
                if ($game_file) {
                    $sql .= ", file_path=?";
                    $params[] = $game_file;
                }
                
                $sql .= " WHERE id=?";
                $params[] = $game_id;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                logAdminActivity('game_updated', 'Game updated', ['game_id' => $game_id, 'game_title' => $title]);
                setAdminFlashMessage('Game updated successfully!', 'success');
            }
        } else if ($action === 'delete') {
            $game_id = (int)$_POST['game_id'];
            
            // Get game details for file cleanup
            $stmt = $db->prepare("SELECT cover_image, file_path FROM games WHERE id = ?");
            $stmt->execute([$game_id]);
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete files
            if ($game['cover_image']) $upload->deleteFile($game['cover_image']);
            if ($game['file_path']) $upload->deleteFile($game['file_path']);
            
            // Delete from database
            $stmt = $db->prepare("DELETE FROM games WHERE id = ?");
            $stmt->execute([$game_id]);
            
            logAdminActivity('game_deleted', 'Game deleted', ['game_id' => $game_id]);
            setAdminFlashMessage('Game deleted successfully!', 'success');
        } else if ($action === 'toggle_status') {
            $game_id = (int)$_POST['game_id'];
            $stmt = $db->prepare("UPDATE games SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$game_id]);
            
            logAdminActivity('game_status_toggled', 'Game status toggled', ['game_id' => $game_id]);
            setAdminFlashMessage('Game status updated!', 'success');
        }
        
    } catch (Exception $e) {
        error_log("Game management error: " . $e->getMessage());
        setAdminFlashMessage('An error occurred: ' . $e->getMessage(), 'error');
    }
    
    header('Location: games.php');
    exit();
}

// Get games with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$search = trim($_GET['search'] ?? '');
$category_filter = intval($_GET['category'] ?? 0);
$status_filter = $_GET['status'] ?? '';

$where_conditions = ['1=1'];
$params = [];

if ($search) {
    $where_conditions[] = "(g.title LIKE ? OR g.description LIKE ? OR g.genre LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($category_filter) {
    $where_conditions[] = "g.category_id = ?";
    $params[] = $category_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "g.is_active = ?";
    $params[] = intval($status_filter);
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM games g WHERE {$where_clause}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_games = $stmt->fetchColumn();
    
    // Get games
    $sql = "SELECT g.*, c.name as category_name 
            FROM games g 
            LEFT JOIN categories c ON g.category_id = c.id 
            WHERE {$where_clause}
            ORDER BY g.created_at DESC 
            LIMIT {$per_page} OFFSET {$offset}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination
    $total_pages = ceil($total_games / $per_page);
    
    // Get categories for dropdown
    $categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get game statistics
    $stats = $db->query("SELECT 
        COUNT(*) as total_games,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_games,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_games,
        SUM(price) as total_value,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_games_30d
        FROM games")->fetch(PDO::FETCH_ASSOC);
        
} catch (PDOException $e) {
    error_log("Game fetch error: " . $e->getMessage());
    $games = [];
    $total_games = 0;
    $total_pages = 1;
    $categories = [];
    $stats = [];
}

require_once 'includes/layout.php';
renderAdminHeader('Games Management', 'Manage your game collection');
?>

<style>
.stats-grid {
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
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 500;
}

.games-table-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.table-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
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
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 0.5rem 1rem;
}

.search-input:focus {
    border-color: #6366f1;
    outline: none;
}

.filter-select {
    min-width: 150px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 0.5rem;
}

.games-table {
    width: 100%;
    margin: 0;
}

.games-table th {
    background: #f8fafc;
    color: #1f2937;
    font-weight: 600;
    padding: 1rem;
    border: none;
    position: sticky;
    top: 0;
    z-index: 10;
}

.games-table td {
    padding: 1rem;
    border-top: 1px solid #e5e7eb;
    vertical-align: middle;
}

.game-cover {
    width: 50px;
    height: 70px;
    object-fit: cover;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.game-cover-placeholder {
    width: 50px;
    height: 70px;
    background: #f3f4f6;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 1.2rem;
}

.game-title {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.2rem;
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
    background: #3b82f6;
    color: white;
}

.btn-toggle {
    background: #f59e0b;
    color: white;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.pagination-container {
    padding: 1.5rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
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
    
    .table-header {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<!-- Statistics -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-value" style="color: var(--primary-color);"><?php echo number_format($stats['total_games'] ?? 0); ?></div>
        <div class="stat-label">Total Games</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--success-color);"><?php echo number_format($stats['active_games'] ?? 0); ?></div>
        <div class="stat-label">Active Games</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--warning-color);"><?php echo number_format(count($categories)); ?></div>
        <div class="stat-label">Categories</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--info-color);"><?php echo '$' . number_format($stats['total_value'] ?? 0, 2); ?></div>
        <div class="stat-label">Total Value</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--danger-color);"><?php echo number_format($stats['new_games_30d'] ?? 0); ?></div>
        <div class="stat-label">New This Month</div>
    </div>
</div>

<!-- Games Table -->
<div class="games-table-container">
    <div class="table-header">
        <div class="search-filters">
            <form method="GET" class="d-flex gap-3 align-items-center">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search games..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="category" class="filter-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" 
                                <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                </button>
                
                <a href="games.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </form>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#gameModal" onclick="resetGameForm()">
                <i class="fas fa-plus me-2"></i>Add New Game
            </button>
        </div>
    </div>

    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
        <table class="table games-table">
            <thead>
                <tr>
                    <th>Cover</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Genre</th>
                    <th>Platform</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($games as $game): ?>
                    <tr>
                        <td>
                            <?php if ($game['cover_image']): ?>
                                <img src="../uploads/<?php echo htmlspecialchars($game['cover_image']); ?>" alt="Cover" class="game-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div class="game-cover-placeholder" style="display:none;">
                                    <i class="fas fa-gamepad"></i>
                                </div>
                            <?php else: ?>
                                <div class="game-cover-placeholder">
                                    <i class="fas fa-gamepad"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="game-title"><?php echo htmlspecialchars($game['title'] ?? ''); ?></div>
                            <small class="text-muted"><?php echo substr(htmlspecialchars($game['description'] ?? ''), 0, 50); ?>...</small>
                        </td>
                        <td><?php echo htmlspecialchars($game['category_name'] ?: 'Uncategorized'); ?></td>
                        <td>
                            <?php if ($game['genre']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($game['genre'] ?? ''); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $platforms = array_filter(explode(',', $game['platform'] ?? ''));
                            foreach ($platforms as $platform): ?>
                                <span class="badge bg-dark me-1"><?php echo htmlspecialchars(trim($platform)); ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td class="fw-bold text-success">$<?php echo number_format($game['price'], 2); ?></td>
                        <td>
                            <span class="status-badge <?php echo $game['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $game['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($game['created_at'])); ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <button type="button" class="action-btn btn-edit" title="Edit" onclick="editGame(<?php echo htmlspecialchars(json_encode($game)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="action-btn btn-toggle" title="<?php echo $game['is_active'] ? 'Deactivate' : 'Activate'; ?>" onclick="toggleGameStatus(<?php echo $game['id']; ?>)">
                                    <i class="fas fa-<?php echo $game['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                </button>
                                <?php if ($game['game_file_url']): ?>
                                    <a href="<?php echo htmlspecialchars($game['game_file_url'] ?? ''); ?>" class="action-btn btn-toggle" title="Play Game" target="_blank">
                                        <i class="fas fa-play"></i>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="action-btn btn-delete" title="Delete" onclick="deleteGame(<?php echo $game['id']; ?>, '<?php echo addslashes($game['title'] ?? ''); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if (empty($games)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <i class="fas fa-gamepad fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No games found</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#gameModal" onclick="resetGameForm()">
                                <i class="fas fa-plus me-2"></i>Add First Game
                            </button>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <div>
                Showing <?php echo number_format(($page - 1) * $per_page + 1); ?> to 
                <?php echo number_format(min($page * $per_page, $total_games)); ?> of 
                <?php echo number_format($total_games); ?> games
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo urlencode($status_filter); ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo urlencode($status_filter); ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Game Modal -->
<div class="modal fade" id="gameModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="gameForm" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add/Edit Game</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="game_id" id="gameId">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Game Title *</label>
                                <input type="text" class="form-control" name="title" id="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <textarea class="form-control" name="description" id="description" rows="4" required></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="cover_image" class="form-label">Cover Image</label>
                                <input type="file" class="form-control" name="cover_image" id="cover_image" accept="image/*">
                                <div id="currentCover" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-select" name="category_id" id="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="genre" class="form-label">Genre</label>
                                <input type="text" class="form-control" name="genre" id="genre" placeholder="Action, Adventure, RPG">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label">Price</label>
                                <input type="number" class="form-control" name="price" id="price" step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="platform" class="form-label">Platform(s)</label>
                                <input type="text" class="form-control" name="platform" id="platform" placeholder="Web, Windows, Mac, Mobile">
                                <small class="text-muted">Separate multiple platforms with commas</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="game_file_url" class="form-label">Game URL/File</label>
                                <input type="url" class="form-control" name="game_file_url" id="game_file_url" placeholder="https://play.example.com/game">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="game_file" class="form-label">Game Files</label>
                        <input type="file" class="form-control" name="game_file" id="game_file" accept=".zip,.rar,.exe,.apk">
                        <small class="text-muted">Upload game files (ZIP, RAR, EXE, APK)</small>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Game</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetGameForm() {
    document.getElementById('gameForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('gameId').value = '';
    document.querySelector('.modal-title').textContent = 'Add New Game';
    document.getElementById('currentCover').innerHTML = '';
}

function editGame(game) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('gameId').value = game.id;
    document.getElementById('title').value = game.title;
    document.getElementById('description').value = game.description || '';
    document.getElementById('category_id').value = game.category_id || '';
    document.getElementById('genre').value = game.genre || '';
    document.getElementById('platform').value = game.platform || '';
    document.getElementById('price').value = game.price;
    document.getElementById('game_file_url').value = game.game_file_url || '';
    document.getElementById('is_active').checked = game.is_active == 1;
    
    document.querySelector('.modal-title').textContent = 'Edit Game';
    
    if (game.cover_image) {
        document.getElementById('currentCover').innerHTML = 
            '<img src="../uploads/' + game.cover_image + '" alt="Current cover" style="max-width: 100px; max-height: 150px;" class="rounded" onerror="this.style.display=\'none\';">'
    }
    
    new bootstrap.Modal(document.getElementById('gameModal')).show();
}

function toggleGameStatus(gameId) {
    if (confirm('Toggle game status?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="game_id" value="${gameId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteGame(gameId, title) {
    if (confirm(`Delete game "${title}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="game_id" value="${gameId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php renderAdminFooter(); ?>