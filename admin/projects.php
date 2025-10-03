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
        header('Location: projects.php');
        exit();
    }
    
    try {
        if ($action === 'add' || $action === 'edit') {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $category_id = (int)$_POST['category_id'];
            $price = (float)$_POST['price'];
            $difficulty_level = $_POST['difficulty_level'];
            $technologies = trim($_POST['technologies']);
            $demo_url = trim($_POST['demo_url']);
            $source_file_url = trim($_POST['source_file_url']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($title) || empty($description) || $category_id <= 0) {
                throw new Exception('Please fill in all required fields.');
            }
            
            $cover_image = null;
            $project_file = null;
            
            // Handle cover image upload
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['size'] > 0) {
                $upload_result = $upload->uploadFile($_FILES['cover_image'], 'image', 'projects');
                if (!$upload_result['success']) {
                    throw new Exception('Cover image upload failed: ' . $upload_result['error']);
                }
                $cover_image = $upload_result['path'];
            }
            
            // Handle project file upload
            if (isset($_FILES['project_file']) && $_FILES['project_file']['size'] > 0) {
                $upload_result = $upload->uploadFile($_FILES['project_file'], 'document', 'projects');
                if (!$upload_result['success']) {
                    throw new Exception('Project file upload failed: ' . $upload_result['error']);
                }
                $project_file = $upload_result['path'];
            }
                
            if ($action === 'add') {
                $sql = "INSERT INTO projects (title, description, category_id, price, difficulty_level, technologies, demo_url, source_file_url, is_active, created_at, updated_at";
                $params = [$title, $description, $category_id, $price, $difficulty_level, $technologies, $demo_url, $source_file_url, $is_active];
                
                if ($cover_image) {
                    $sql .= ", cover_image";
                    $params[] = $cover_image;
                }
                if ($project_file) {
                    $sql .= ", file_path";
                    $params[] = $project_file;
                }
                
                $sql .= ") VALUES (" . str_repeat('?,', count($params) - 1) . "?, NOW(), NOW())";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                logAdminActivity('project_created', 'Project created', ['project_title' => $title]);
                setAdminFlashMessage('Project added successfully!', 'success');
            } else {
                $project_id = (int)$_POST['project_id'];
                $sql = "UPDATE projects SET title=?, description=?, category_id=?, price=?, difficulty_level=?, technologies=?, demo_url=?, source_file_url=?, is_active=?, updated_at=NOW()";
                $params = [$title, $description, $category_id, $price, $difficulty_level, $technologies, $demo_url, $source_file_url, $is_active];
                
                if ($cover_image) {
                    $sql .= ", cover_image=?";
                    $params[] = $cover_image;
                }
                if ($project_file) {
                    $sql .= ", file_path=?";
                    $params[] = $project_file;
                }
                
                $sql .= " WHERE id=?";
                $params[] = $project_id;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                logAdminActivity('project_updated', 'Project updated', ['project_id' => $project_id, 'project_title' => $title]);
                setAdminFlashMessage('Project updated successfully!', 'success');
            }
        } else if ($action === 'delete') {
            $project_id = (int)$_POST['project_id'];
            
            // Get project details for file cleanup
            $stmt = $db->prepare("SELECT cover_image, file_path FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete files
            if ($project['cover_image']) $upload->deleteFile($project['cover_image']);
            if ($project['file_path']) $upload->deleteFile($project['file_path']);
            
            // Delete from database
            $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            
            logAdminActivity('project_deleted', 'Project deleted', ['project_id' => $project_id]);
            setAdminFlashMessage('Project deleted successfully!', 'success');
        } else if ($action === 'toggle_status') {
            $project_id = (int)$_POST['project_id'];
            $stmt = $db->prepare("UPDATE projects SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$project_id]);
            
            logAdminActivity('project_status_toggled', 'Project status toggled', ['project_id' => $project_id]);
            setAdminFlashMessage('Project status updated!', 'success');
        }
        
    } catch (Exception $e) {
        error_log("Project management error: " . $e->getMessage());
        setAdminFlashMessage('An error occurred: ' . $e->getMessage(), 'error');
    }
    
    header('Location: projects.php');
    exit();
}

// Get projects with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$search = trim($_GET['search'] ?? '');
$category_filter = intval($_GET['category'] ?? 0);
$status_filter = $_GET['status'] ?? '';

$where_conditions = ['1=1'];
$params = [];

if ($search) {
    $where_conditions[] = "(p.title LIKE ? OR p.description LIKE ? OR p.technologies LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($category_filter) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "p.is_active = ?";
    $params[] = intval($status_filter);
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM projects p WHERE {$where_clause}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_projects = $stmt->fetchColumn();
    
    // Get projects
    $sql = "SELECT p.*, c.name as category_name 
            FROM projects p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE {$where_clause}
            ORDER BY p.created_at DESC 
            LIMIT {$per_page} OFFSET {$offset}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination
    $total_pages = ceil($total_projects / $per_page);
    
    // Get categories for dropdown
    $categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get project statistics
    $stats = $db->query("SELECT 
        COUNT(*) as total_projects,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_projects,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_projects,
        SUM(price) as total_value,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_projects_30d
        FROM projects")->fetch(PDO::FETCH_ASSOC);
        
} catch (PDOException $e) {
    error_log("Project fetch error: " . $e->getMessage());
    $projects = [];
    $total_projects = 0;
    $total_pages = 1;
    $categories = [];
    $stats = [];
}

require_once 'includes/layout.php';

// Render admin header
renderAdminHeader('Projects Management', 'Manage your project portfolio');
?>

<style>
.action-btn {
    padding: 0.4rem 0.8rem;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    margin: 0 0.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-edit {
    background: #3b82f6;
    color: white;
}

.btn-edit:hover {
    background: #2563eb;
    color: white;
}

.btn-toggle {
    background: #f59e0b;
    color: white;
}

.btn-toggle:hover {
    background: #d97706;
    color: white;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
    color: white;
}

/* Technology badges CSS - ensure always visible */
.badge {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative !important;
    z-index: 10;
}

.badge.bg-primary {
    background-color: #0d6efd !important;
    color: white !important;
}

.badge.bg-secondary {
    background-color: #6c757d !important;
    color: white !important;
}

/* Technology column specific */
td .badge {
    margin-right: 4px !important;
    margin-bottom: 2px !important;
    white-space: nowrap !important;
    font-size: 0.75rem !important;
    padding: 0.25em 0.5em !important;
}
</style>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-primary">
                    <i class="fas fa-code fa-2x mb-2"></i>
                    <h4><?php echo number_format($stats['total_projects'] ?? 0); ?></h4>
                    <small class="text-muted">Total Projects</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-success">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <h4><?php echo number_format($stats['active_projects'] ?? 0); ?></h4>
                    <small class="text-muted">Active Projects</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-info">
                    <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                    <h4>$<?php echo number_format($stats['total_value'] ?? 0, 2); ?></h4>
                    <small class="text-muted">Total Value</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-warning">
                    <i class="fas fa-calendar fa-2x mb-2"></i>
                    <h4><?php echo number_format($stats['new_projects_30d'] ?? 0); ?></h4>
                    <small class="text-muted">New This Month</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search projects..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="projects.php" class="btn btn-outline-secondary">
                        <i class="fas fa-refresh"></i> Clear
                    </a>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#projectModal" onclick="resetProjectForm()">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Projects Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">All Projects</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cover</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Difficulty</th>
                        <th width="180">Technologies</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?= $project['id'] ?></td>
                        <td>
                            <?php if ($project['cover_image']): ?>
                                <img src="../uploads/<?= htmlspecialchars($project['cover_image']) ?>" 
                                     alt="Cover" class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="bg-light d-flex align-items-center justify-content-center" 
                                     style="width: 50px; height: 50px; display: none;">
                                    <i class="fas fa-code text-muted"></i>
                                </div>
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center" 
                                     style="width: 50px; height: 50px;">
                                    <i class="fas fa-code text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($project['title'] ?? '') ?></strong>
                            <br>
                            <small class="text-muted"><?= substr(htmlspecialchars($project['description'] ?? ''), 0, 50) ?>...</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($project['category_name'] ?? 'Uncategorized') ?></span>
                        </td>
                        <td>$<?= number_format($project['price'], 2) ?></td>
                        <td>
                            <?php
                            $difficultyColors = [
                                'beginner' => 'success',
                                'intermediate' => 'warning',
                                'advanced' => 'danger'
                            ];
                            $color = $difficultyColors[$project['difficulty_level']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= ucfirst($project['difficulty_level'] ?? 'beginner') ?></span>
                        </td>
                        <td>
                            <?php if (!empty($project['technologies'])): ?>
                                <?php 
                                $technologies = explode(',', $project['technologies']);
                                foreach (array_slice($technologies, 0, 3) as $tech): // Show max 3 technologies
                                    $tech_clean = trim($tech);
                                    if (!empty($tech_clean)):
                                ?>
                                    <span class="badge bg-primary me-1 mb-1"><?= htmlspecialchars($tech_clean) ?></span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                                <?php if (count($technologies) > 3): ?>
                                    <span class="badge bg-secondary">+<?= count($technologies) - 3 ?> more</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($project['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M j, Y', strtotime($project['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <button type="button" class="action-btn btn-edit" title="Edit" onclick="editProject(<?php echo htmlspecialchars(json_encode($project)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="action-btn btn-toggle" title="<?php echo $project['is_active'] ? 'Deactivate' : 'Activate'; ?>" onclick="toggleProjectStatus(<?php echo $project['id']; ?>)">
                                    <i class="fas fa-<?php echo $project['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                </button>
                                <?php if ($project['demo_url']): ?>
                                    <a href="<?php echo htmlspecialchars($project['demo_url'] ?? ''); ?>" class="action-btn btn-toggle" title="View Demo" target="_blank">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($project['source_file_url']): ?>
                                    <a href="<?php echo htmlspecialchars($project['source_file_url'] ?? ''); ?>" class="action-btn btn-toggle" title="View Source" target="_blank">
                                        <i class="fas fa-code"></i>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="action-btn btn-delete" title="Delete" onclick="deleteProject(<?php echo $project['id']; ?>, '<?php echo addslashes($project['title'] ?? ''); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($projects)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <i class="fas fa-code fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No projects found</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#projectModal" onclick="resetProjectForm()">
                                    <i class="fas fa-plus me-2"></i>Add First Project
                                </button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    Showing <?php echo number_format(($page - 1) * $per_page + 1); ?> to 
                    <?php echo number_format(min($page * $per_page, $total_projects)); ?> of 
                    <?php echo number_format($total_projects); ?> projects
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
        </div>
    <?php endif; ?>
</div>

<!-- Project Modal -->
<div class="modal fade" id="projectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="projectModalTitle">Add New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="projectForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="project_id" id="projectId">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Project Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                                <div class="invalid-feedback">Please provide a project title.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a category.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                        <div class="invalid-feedback">Please provide a description.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label">Price ($)</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       min="0" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="difficulty_level" class="form-label">Difficulty Level</label>
                                <select class="form-select" id="difficulty_level" name="difficulty_level">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="technologies" class="form-label">Technologies Used</label>
                        <input type="text" class="form-control" id="technologies" name="technologies" 
                               placeholder="e.g., HTML, CSS, JavaScript, React">
                        <div class="form-text">Separate multiple technologies with commas</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="demo_url" class="form-label">Demo URL</label>
                                <input type="url" class="form-control" id="demo_url" name="demo_url" 
                                       placeholder="https://demo.example.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="source_file_url" class="form-label">Source Files URL</label>
                                <input type="url" class="form-control" id="source_file_url" name="source_file_url" 
                                       placeholder="https://github.com/user/repo">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="cover_image" class="form-label">Cover Image</label>
                                <input type="file" class="form-control file-input" id="cover_image" name="cover_image" 
                                       accept="image/*">
                                <div class="form-text">Upload a project cover image (optional)</div>
                                <div class="image-preview mt-2"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="project_file" class="form-label">Project Files</label>
                                <input type="file" class="form-control file-input" id="project_file" name="project_file" 
                                       accept=".zip,.rar,.pdf,.doc,.docx">
                                <div class="form-text">Upload project files/source code (ZIP, RAR, PDF)</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Project
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetProjectForm() {
    document.getElementById('projectForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('projectId').value = '';
    document.querySelector('#projectModalTitle').textContent = 'Add New Project';
    document.querySelector('.image-preview').innerHTML = '';
}

function editProject(project) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('projectId').value = project.id;
    document.getElementById('title').value = project.title;
    document.getElementById('description').value = project.description || '';
    document.getElementById('category_id').value = project.category_id || '';
    document.getElementById('price').value = project.price;
    document.getElementById('difficulty_level').value = project.difficulty_level || 'beginner';
    document.getElementById('technologies').value = project.technologies || '';
    document.getElementById('demo_url').value = project.demo_url || '';
    document.getElementById('source_file_url').value = project.source_file_url || '';
    document.getElementById('is_active').checked = project.is_active == 1;
    
    document.querySelector('#projectModalTitle').textContent = 'Edit Project';
    
    if (project.cover_image) {
        document.querySelector('.image-preview').innerHTML = 
            '<img src="../uploads/' + project.cover_image + '" alt="Current cover" style="max-width: 100px; max-height: 150px;" class="rounded" onerror="this.style.display=\'none\';">'
    }
    
    new bootstrap.Modal(document.getElementById('projectModal')).show();
}

function toggleProjectStatus(projectId) {
    if (confirm('Toggle project status?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="project_id" value="${projectId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteProject(projectId, title) {
    if (confirm(`Delete project "${title}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="project_id" value="${projectId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Ensure technology badges remain visible
document.addEventListener('DOMContentLoaded', function() {
    // Force visibility of all badges
    const badges = document.querySelectorAll('.badge');
    badges.forEach(function(badge) {
        badge.style.display = 'inline-block';
        badge.style.visibility = 'visible';
        badge.style.opacity = '1';
    });
    
    // Check if any badges are being hidden and restore them
    setInterval(function() {
        const hiddenBadges = document.querySelectorAll('.badge[style*="display: none"], .badge[style*="visibility: hidden"]');
        hiddenBadges.forEach(function(badge) {
            badge.style.display = 'inline-block';
            badge.style.visibility = 'visible';
            badge.style.opacity = '1';
        });
    }, 1000);
});
</script>

<?php renderAdminFooter(); ?>
