<?php
require_once 'includes/auth.php';
require_once '../includes/upload.php';

$current_admin = getCurrentAdmin();
$flash_message = getAdminFlashMessage();

$upload = new UploadService();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                case 'edit':
                    $title = trim($_POST['title']);
                    $author = trim($_POST['author']);
                    $isbn = trim($_POST['isbn']);
                    $description = trim($_POST['description']);
                    $price = floatval($_POST['price']);
                    $category_id = intval($_POST['category_id']);
                    $pages = intval($_POST['pages']);
                    $published_date = $_POST['published_date'];
                    $stock = intval($_POST['stock']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($title) || empty($author) || $price <= 0) {
                        throw new Exception('Title, author, and valid price are required');
                    }
                    
                    $cover_image = null;
                    $file_path = null;
                    
                    // Handle cover image upload
                    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['size'] > 0) {
                        $upload_result = $upload->uploadFile($_FILES['cover_image'], 'image', 'books');
                        if (!$upload_result['success']) {
                            throw new Exception('Cover image upload failed: ' . $upload_result['error']);
                        }
                        $cover_image = $upload_result['path'];
                    }
                    
                    // Handle file upload
                    if (isset($_FILES['book_file']) && $_FILES['book_file']['size'] > 0) {
                        $upload_result = $upload->uploadFile($_FILES['book_file'], 'document', 'books');
                        if (!$upload_result['success']) {
                            throw new Exception('Book file upload failed: ' . $upload_result['error']);
                        }
                        $file_path = $upload_result['path'];
                    }
                    
                    if ($_POST['action'] === 'add') {
                        $sql = "INSERT INTO books (title, author, isbn, description, price, category_id, pages, published_date, stock, is_active, created_at, updated_at";
                        $params = [$title, $author, $isbn, $description, $price, $category_id, $pages, $published_date, $stock, $is_active];
                        
                        if ($cover_image) {
                            $sql .= ", cover_image";
                            $params[] = $cover_image;
                        }
                        if ($file_path) {
                            $sql .= ", file_path";
                            $params[] = $file_path;
                        }
                        
                        $sql .= ") VALUES (" . str_repeat('?,', count($params) - 1) . "?, NOW(), NOW())";
                        
                        $stmt = $db->prepare($sql);
                        $stmt->execute($params);
                        
                        setAdminFlashMessage('Book added successfully!', 'success');
                    } else {
                        $book_id = intval($_POST['book_id']);
                        $sql = "UPDATE books SET title=?, author=?, isbn=?, description=?, price=?, category_id=?, pages=?, published_date=?, stock=?, is_active=?, updated_at=NOW()";
                        $params = [$title, $author, $isbn, $description, $price, $category_id, $pages, $published_date, $stock, $is_active];
                        
                        if ($cover_image) {
                            $sql .= ", cover_image=?";
                            $params[] = $cover_image;
                        }
                        if ($file_path) {
                            $sql .= ", file_path=?";
                            $params[] = $file_path;
                        }
                        
                        $sql .= " WHERE id=?";
                        $params[] = $book_id;
                        
                        $stmt = $db->prepare($sql);
                        $stmt->execute($params);
                        
                        setAdminFlashMessage('Book updated successfully!', 'success');
                    }
                    break;
                    
                case 'delete':
                    $book_id = intval($_POST['book_id']);
                    
                    // Get book details for file cleanup
                    $stmt = $db->prepare("SELECT cover_image, file_path FROM books WHERE id = ?");
                    $stmt->execute([$book_id]);
                    $book = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Delete files
                    if ($book['cover_image']) $upload->deleteFile($book['cover_image']);
                    if ($book['file_path']) $upload->deleteFile($book['file_path']);
                    
                    // Delete from database
                    $stmt = $db->prepare("DELETE FROM books WHERE id = ?");
                    $stmt->execute([$book_id]);
                    
                    setAdminFlashMessage('Book deleted successfully!', 'success');
                    break;
                    
                case 'toggle_status':
                    $book_id = intval($_POST['book_id']);
                    $stmt = $db->prepare("UPDATE books SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$book_id]);
                    
                    setAdminFlashMessage('Book status updated!', 'success');
                    break;
            }
        }
    } catch (Exception $e) {
        setAdminFlashMessage($e->getMessage(), 'error');
    }
    
    // Redirect to prevent form resubmission
    header('Location: books.php');
    exit();
}

// Get books with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where_conditions[] = "category_id = ?";
    $params[] = $category_filter;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) FROM books b " . $where_clause;
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_books = $stmt->fetchColumn();
$total_pages = ceil($total_books / $per_page);

// Get books
$sql = "
    SELECT b.*, c.name as category_name 
    FROM books b 
    LEFT JOIN categories c ON b.category_id = c.id 
    $where_clause
    ORDER BY b.created_at DESC 
    LIMIT $per_page OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for dropdown
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/layout.php';

// Render admin header
renderAdminHeader('Manage Books', 'Manage your book collection and inventory');
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

.books-table-container {
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

.books-table {
    width: 100%;
    margin: 0;
}

.books-table th {
    background: #f8fafc;
    color: #1f2937;
    font-weight: 600;
    padding: 1rem;
    border: none;
    position: sticky;
    top: 0;
    z-index: 10;
}

.books-table td {
    padding: 1rem;
    border-top: 1px solid #e5e7eb;
    vertical-align: middle;
}

.book-cover {
    width: 50px;
    height: 70px;
    object-fit: cover;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.book-cover-placeholder {
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

.book-title {
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

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-value" style="color: var(--primary-color);"><?php echo number_format($total_books); ?></div>
        <div class="stat-label">Total Books</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--success-color);">
            <?php 
            $active_books = 0;
            foreach($books as $book) {
                if($book['is_active']) $active_books++;
            }
            echo number_format($active_books); 
            ?>
        </div>
        <div class="stat-label">Active Books</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--warning-color);">
            <?php 
            $total_categories = count($categories);
            echo number_format($total_categories); 
            ?>
        </div>
        <div class="stat-label">Categories</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--info-color);">
            <?php 
            $total_revenue = 0;
            foreach($books as $book) {
                $total_revenue += $book['price'];
            }
            echo '$' . number_format($total_revenue, 2); 
            ?>
        </div>
        <div class="stat-label">Total Value</div>
    </div>
</div>

<!-- Books Table -->
<div class="books-table-container">
    <div class="table-header">
        <div class="search-filters">
            <form method="GET" class="d-flex gap-3 align-items-center">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search books..." 
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
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                </button>
                
                <a href="books.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </form>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookModal" onclick="resetBookForm()">
                <i class="fas fa-plus me-2"></i>Add New Book
            </button>
        </div>
    </div>

    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
        <table class="table books-table">
            <thead>
                <tr>
                    <th>Cover</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($books as $book): ?>
                    <tr>
                        <td>
                            <?php if ($book['cover_image']): ?>
                                <img src="../uploads/<?php echo htmlspecialchars($book['cover_image']); ?>" alt="Cover" class="book-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div class="book-cover-placeholder" style="display:none;">
                                    <i class="fas fa-book"></i>
                                </div>
                            <?php else: ?>
                                <div class="book-cover-placeholder">
                                    <i class="fas fa-book"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="book-title"><?php echo htmlspecialchars($book['title'] ?? ''); ?></div>
                            <?php if ($book['isbn']): ?>
                                <small class="text-muted">ISBN: <?php echo htmlspecialchars($book['isbn'] ?? ''); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($book['author'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($book['category_name'] ?: 'Uncategorized'); ?></td>
                        <td class="fw-bold text-success">$<?php echo number_format($book['price'], 2); ?></td>
                        <td>
                            <span class="badge bg-info"><?php echo $book['stock'] ?? 0; ?></span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $book['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $book['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button type="button" class="action-btn btn-edit" title="Edit" onclick="editBook(<?php echo htmlspecialchars(json_encode($book)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="action-btn btn-toggle" title="<?php echo $book['is_active'] ? 'Deactivate' : 'Activate'; ?>" onclick="toggleBookStatus(<?php echo $book['id']; ?>)">
                                    <i class="fas fa-<?php echo $book['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                </button>
                                <button type="button" class="action-btn btn-delete" title="Delete" onclick="deleteBook(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title'] ?? ''); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if (empty($books)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="fas fa-book fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No books found</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookModal" onclick="resetBookForm()">
                                <i class="fas fa-plus me-2"></i>Add First Book
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
                <?php echo number_format(min($page * $per_page, $total_books)); ?> of 
                <?php echo number_format($total_books); ?> books
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Book Modal -->
<div class="modal fade" id="bookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="bookForm" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add/Edit Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="book_id" id="bookId">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title *</label>
                                <input type="text" class="form-control" name="title" id="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="author" class="form-label">Author *</label>
                                <input type="text" class="form-control" name="author" id="author" required>
                            </div>
                            <div class="mb-3">
                                <label for="isbn" class="form-label">ISBN</label>
                                <input type="text" class="form-control" name="isbn" id="isbn">
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
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="4"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label">Price *</label>
                                <input type="number" class="form-control" name="price" id="price" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" name="category_id" id="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="pages" class="form-label">Pages</label>
                                <input type="number" class="form-control" name="pages" id="pages" min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="published_date" class="form-label">Published Date</label>
                                <input type="date" class="form-control" name="published_date" id="published_date">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="stock" class="form-label">Stock</label>
                                <input type="number" class="form-control" name="stock" id="stock" min="0" value="1">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="book_file" class="form-label">Book File</label>
                                <input type="file" class="form-control" name="book_file" id="book_file" accept=".pdf,.doc,.docx,.epub">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Book</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetBookForm() {
    document.getElementById('bookForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('bookId').value = '';
    document.querySelector('.modal-title').textContent = 'Add New Book';
    document.getElementById('currentCover').innerHTML = '';
}

function editBook(book) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('bookId').value = book.id;
    document.getElementById('title').value = book.title;
    document.getElementById('author').value = book.author;
    document.getElementById('isbn').value = book.isbn || '';
    document.getElementById('description').value = book.description || '';
    document.getElementById('price').value = book.price;
    document.getElementById('category_id').value = book.category_id || '';
    document.getElementById('pages').value = book.pages || '';
    document.getElementById('published_date').value = book.published_date || '';
    document.getElementById('stock').value = book.stock || '';
    document.getElementById('is_active').checked = book.is_active == 1;
    
    document.querySelector('.modal-title').textContent = 'Edit Book';
    
    if (book.cover_image) {
        document.getElementById('currentCover').innerHTML = 
            '<img src="../uploads/' + book.cover_image + '" alt="Current cover" style="max-width: 100px; max-height: 150px;" class="rounded" onerror="this.style.display=\'none\';">';
    }
    
    new bootstrap.Modal(document.getElementById('bookModal')).show();
}

function toggleBookStatus(bookId) {
    if (confirm('Toggle book status?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="book_id" value="${bookId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteBook(bookId, title) {
    if (confirm(`Delete book "${title}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="book_id" value="${bookId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php renderAdminFooter(); ?>
