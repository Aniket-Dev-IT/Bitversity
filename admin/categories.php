<?php
require_once '../includes/config.php';
require_once '../includes/session.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name']);
        $slug = trim($_POST['slug']);
        $type = $_POST['type'];
        $description = trim($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name) || empty($slug) || empty($type)) {
            $_SESSION['error_message'] = "Please fill in all required fields.";
        } else {
            try {
                if ($action === 'add') {
                    // Check if slug already exists
                    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
                    $stmt->execute([$slug]);
                    if ($stmt->fetch()) {
                        $_SESSION['error_message'] = "Category with this slug already exists.";
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO categories (name, slug, type, description, is_active, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$name, $slug, $type, $description, $is_active]);
                        $_SESSION['success_message'] = "Category added successfully!";
                    }
                } else {
                    $category_id = (int)$_POST['category_id'];
                    // Check if slug already exists for other categories
                    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
                    $stmt->execute([$slug, $category_id]);
                    if ($stmt->fetch()) {
                        $_SESSION['error_message'] = "Category with this slug already exists.";
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE categories SET name = ?, slug = ?, type = ?, description = ?, is_active = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $slug, $type, $description, $is_active, $category_id]);
                        $_SESSION['success_message'] = "Category updated successfully!";
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }
        }
        
        header('Location: categories.php');
        exit();
    }
    
    if ($action === 'delete') {
        $category_id = (int)$_POST['category_id'];
        try {
            // Check if category is used by any content
            $stmt = $pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM books WHERE category_id = ?) + 
                    (SELECT COUNT(*) FROM projects WHERE category_id = ?) + 
                    (SELECT COUNT(*) FROM games WHERE category_id = ?) as usage_count
            ");
            $stmt->execute([$category_id, $category_id, $category_id]);
            $usageCount = $stmt->fetchColumn();
            
            if ($usageCount > 0) {
                $_SESSION['error_message'] = "Cannot delete category. It is being used by $usageCount item(s).";
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);
                $_SESSION['success_message'] = "Category deleted successfully!";
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error deleting category: " . $e->getMessage();
        }
        
        header('Location: categories.php');
        exit();
    }
}

// Get all categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY type, name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Categories Management';
require_once '../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>Categories Management</h2>
        <p class="text-muted">Organize your content with categories</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
        <i class="fas fa-plus"></i> Add New Category
    </button>
</div>

<!-- Categories Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">All Categories</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Usage</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?= $category['id'] ?></td>
                        <td><strong><?= htmlspecialchars($category['name']) ?></strong></td>
                        <td><code><?= htmlspecialchars($category['slug']) ?></code></td>
                        <td>
                            <?php
                            $typeColors = [
                                'book' => 'primary',
                                'project' => 'success', 
                                'game' => 'warning',
                                'all' => 'info'
                            ];
                            $color = $typeColors[$category['type']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= ucfirst($category['type']) ?></span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?= $category['description'] ? htmlspecialchars(substr($category['description'], 0, 50)) . '...' : 'No description' ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($category['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT 
                                    (SELECT COUNT(*) FROM books WHERE category_id = ?) as books_count,
                                    (SELECT COUNT(*) FROM projects WHERE category_id = ?) as projects_count,
                                    (SELECT COUNT(*) FROM games WHERE category_id = ?) as games_count
                            ");
                            $stmt->execute([$category['id'], $category['id'], $category['id']]);
                            $usage = $stmt->fetch(PDO::FETCH_ASSOC);
                            $totalUsage = $usage['books_count'] + $usage['projects_count'] + $usage['games_count'];
                            ?>
                            <span class="badge bg-info"><?= $totalUsage ?> items</span>
                        </td>
                        <td><?= date('M j, Y', strtotime($category['created_at'])) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-edit" 
                                        data-category="<?= htmlspecialchars(json_encode($category)) ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-delete" 
                                            data-name="<?= htmlspecialchars($category['name']) ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalTitle">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="category_id" id="categoryId">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="invalid-feedback">Please provide a category name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug *</label>
                        <input type="text" class="form-control" id="slug" name="slug" required 
                               placeholder="category-slug" pattern="[a-z0-9-]+">
                        <div class="form-text">URL-friendly version (lowercase, hyphens only)</div>
                        <div class="invalid-feedback">Please provide a valid slug.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Category Type *</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="">Select type</option>
                            <option value="book">Books</option>
                            <option value="project">Projects</option>
                            <option value="game">Games</option>
                            <option value="all">All Content</option>
                        </select>
                        <div class="invalid-feedback">Please select a category type.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Brief description of this category..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Auto-generate slug from name
    $('#name').on('input', function() {
        const name = $(this).val();
        const slug = name.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .trim('-');
        $('#slug').val(slug);
    });
    
    // Edit category
    $('.btn-edit').on('click', function() {
        const category = JSON.parse($(this).data('category'));
        
        $('#categoryModalTitle').text('Edit Category');
        $('#formAction').val('edit');
        $('#categoryId').val(category.id);
        $('#name').val(category.name);
        $('#slug').val(category.slug);
        $('#type').val(category.type);
        $('#description').val(category.description);
        $('#is_active').prop('checked', category.is_active == 1);
        
        $('#categoryModal').modal('show');
    });
    
    // Reset form when adding new category
    $('#categoryModal').on('hidden.bs.modal', function() {
        $('#categoryModalTitle').text('Add New Category');
        $('#formAction').val('add');
        $('#categoryId').val('');
        $(this).find('form')[0].reset();
        $(this).find('form').removeClass('was-validated');
        $('#is_active').prop('checked', true);
    });
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>