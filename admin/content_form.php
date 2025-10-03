<?php
/**
 * Admin Content Form
 * 
 * Universal form for creating and editing books, projects, games, and categories
 * with comprehensive validation, file uploads, and CRUD operations.
 */

require_once 'includes/auth.php';

$current_admin = getCurrentAdmin();
$flash_message = getAdminFlashMessage();

// Get parameters
$content_type = $_GET['type'] ?? 'books';
$item_id = intval($_GET['id'] ?? 0);
$is_edit = $item_id > 0;

// Validate content type
$valid_types = ['books', 'projects', 'games', 'categories'];
if (!in_array($content_type, $valid_types)) {
    header('Location: content.php');
    exit();
}

// Get item data for editing
$item = null;
if ($is_edit) {
    try {
        $stmt = $db->prepare("SELECT * FROM {$content_type} WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            setAdminFlashMessage('Item not found.', 'error');
            header('Location: content.php?type=' . $content_type);
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error fetching item: " . $e->getMessage());
        setAdminFlashMessage('Error loading item.', 'error');
        header('Location: content.php?type=' . $content_type);
        exit();
    }
}

// Get categories for dropdown
$categories = [];
try {
    $stmt = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        setAdminFlashMessage('Invalid security token. Please try again.', 'error');
    } else {
        $errors = [];
        $data = [];
        
        try {
            // Common validation and sanitization
            switch ($content_type) {
                case 'categories':
                    $data['name'] = trim($_POST['name'] ?? '');
                    $data['description'] = trim($_POST['description'] ?? '');
                    $data['slug'] = trim($_POST['slug'] ?? '');
                    $data['icon'] = trim($_POST['icon'] ?? '');
                    $data['color'] = trim($_POST['color'] ?? '#6366f1');
                    $data['sort_order'] = intval($_POST['sort_order'] ?? 0);
                    $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;
                    
                    // Auto-generate slug if empty
                    if (empty($data['slug']) && !empty($data['name'])) {
                        $data['slug'] = createSlug($data['name']);
                    }
                    
                    // Validation
                    if (empty($data['name'])) {
                        $errors[] = 'Category name is required.';
                    }
                    
                    if (empty($data['slug'])) {
                        $errors[] = 'Category slug is required.';
                    }
                    
                    // Check for duplicate slug
                    $slug_check_sql = "SELECT id FROM categories WHERE slug = ?";
                    if ($is_edit) {
                        $slug_check_sql .= " AND id != ?";
                    }
                    $stmt = $db->prepare($slug_check_sql);
                    $params = [$data['slug']];
                    if ($is_edit) {
                        $params[] = $item_id;
                    }
                    $stmt->execute($params);
                    if ($stmt->fetchColumn()) {
                        $errors[] = 'Category slug already exists. Please choose a different one.';
                    }
                    break;
                    
                case 'books':
                    $data['title'] = trim($_POST['title'] ?? '');
                    $data['author'] = trim($_POST['author'] ?? '');
                    $data['isbn'] = trim($_POST['isbn'] ?? '');
                    $data['description'] = trim($_POST['description'] ?? '');
                    $data['category_id'] = intval($_POST['category_id'] ?? 0) ?: null;
                    $data['price'] = floatval($_POST['price'] ?? 0);
                    $data['file_path'] = trim($_POST['file_path'] ?? '');
                    $data['file_size'] = intval($_POST['file_size'] ?? 0);
                    $data['pages'] = intval($_POST['pages'] ?? 0);
                    $data['language'] = trim($_POST['language'] ?? 'English');
                    $data['publisher'] = trim($_POST['publisher'] ?? '');
                    $data['publication_year'] = intval($_POST['publication_year'] ?? date('Y'));
                    $data['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
                    $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;
                    
                    // Validation
                    if (empty($data['title'])) {
                        $errors[] = 'Book title is required.';
                    }
                    if (empty($data['author'])) {
                        $errors[] = 'Author name is required.';
                    }
                    if ($data['price'] < 0) {
                        $errors[] = 'Price cannot be negative.';
                    }
                    break;
                    
                case 'projects':
                    $data['title'] = trim($_POST['title'] ?? '');
                    $data['description'] = trim($_POST['description'] ?? '');
                    $data['category_id'] = intval($_POST['category_id'] ?? 0) ?: null;
                    $data['price'] = floatval($_POST['price'] ?? 0);
                    $data['difficulty'] = trim($_POST['difficulty'] ?? 'beginner');
                    $data['duration_hours'] = intval($_POST['duration_hours'] ?? 0);
                    $data['file_path'] = trim($_POST['file_path'] ?? '');
                    $data['file_size'] = intval($_POST['file_size'] ?? 0);
                    $data['requirements'] = trim($_POST['requirements'] ?? '');
                    $data['learning_outcomes'] = trim($_POST['learning_outcomes'] ?? '');
                    $data['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
                    $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;
                    
                    // Validation
                    if (empty($data['title'])) {
                        $errors[] = 'Project title is required.';
                    }
                    if ($data['price'] < 0) {
                        $errors[] = 'Price cannot be negative.';
                    }
                    if (!in_array($data['difficulty'], ['beginner', 'intermediate', 'advanced'])) {
                        $data['difficulty'] = 'beginner';
                    }
                    break;
                    
                case 'games':
                    $data['title'] = trim($_POST['title'] ?? '');
                    $data['description'] = trim($_POST['description'] ?? '');
                    $data['category_id'] = intval($_POST['category_id'] ?? 0) ?: null;
                    $data['price'] = floatval($_POST['price'] ?? 0);
                    $data['game_type'] = trim($_POST['game_type'] ?? 'quiz');
                    $data['platform'] = trim($_POST['platform'] ?? 'web');
                    $data['file_path'] = trim($_POST['file_path'] ?? '');
                    $data['file_size'] = intval($_POST['file_size'] ?? 0);
                    $data['min_age'] = intval($_POST['min_age'] ?? 0);
                    $data['max_players'] = intval($_POST['max_players'] ?? 1);
                    $data['instructions'] = trim($_POST['instructions'] ?? '');
                    $data['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
                    $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;
                    
                    // Validation
                    if (empty($data['title'])) {
                        $errors[] = 'Game title is required.';
                    }
                    if ($data['price'] < 0) {
                        $errors[] = 'Price cannot be negative.';
                    }
                    if (!in_array($data['game_type'], ['quiz', 'puzzle', 'simulation', 'adventure'])) {
                        $data['game_type'] = 'quiz';
                    }
                    if (!in_array($data['platform'], ['web', 'mobile', 'desktop'])) {
                        $data['platform'] = 'web';
                    }
                    break;
            }
            
            // Handle file uploads
            $uploaded_files = [];
            if (isset($_FILES) && !empty($_FILES)) {
                foreach ($_FILES as $field_name => $file) {
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/' . $content_type . '/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = [
                            'cover_image' => ['jpg', 'jpeg', 'png', 'gif'],
                            'thumbnail' => ['jpg', 'jpeg', 'png', 'gif'],
                            'file_path' => ['pdf', 'zip', 'rar', 'epub', 'mobi', 'html', 'js']
                        ];
                        
                        if (isset($allowed_extensions[$field_name]) && 
                            in_array($file_extension, $allowed_extensions[$field_name])) {
                            
                            $filename = uniqid() . '_' . time() . '.' . $file_extension;
                            $file_path = $upload_dir . $filename;
                            
                            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                                $uploaded_files[$field_name] = 'uploads/' . $content_type . '/' . $filename;
                                if ($field_name === 'file_path') {
                                    $data['file_size'] = $file['size'];
                                }
                            }
                        }
                    }
                }
            }
            
            // Merge uploaded files into data
            $data = array_merge($data, $uploaded_files);
            
            if (empty($errors)) {
                if ($is_edit) {
                    // Update existing item
                    $update_fields = [];
                    $params = [];
                    
                    foreach ($data as $field => $value) {
                        $update_fields[] = "{$field} = ?";
                        $params[] = $value;
                    }
                    $params[] = $item_id;
                    
                    $sql = "UPDATE {$content_type} SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    
                    logAdminActivity('content_updated', "Content item updated", [
                        'content_type' => $content_type,
                        'item_id' => $item_id,
                        'title' => $data['title'] ?? $data['name'] ?? 'Unknown'
                    ]);
                    
                    setAdminFlashMessage(ucfirst($content_type) . ' updated successfully.', 'success');
                } else {
                    // Create new item
                    $fields = array_keys($data);
                    $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                    
                    $sql = "INSERT INTO {$content_type} (" . implode(', ', $fields) . ", created_at, updated_at) VALUES ({$placeholders}, NOW(), NOW())";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(array_values($data));
                    
                    $new_id = $db->lastInsertId();
                    
                    logAdminActivity('content_created', "Content item created", [
                        'content_type' => $content_type,
                        'item_id' => $new_id,
                        'title' => $data['title'] ?? $data['name'] ?? 'Unknown'
                    ]);
                    
                    setAdminFlashMessage(ucfirst($content_type) . ' created successfully.', 'success');
                }
                
                header('Location: content.php?type=' . $content_type);
                exit();
            } else {
                foreach ($errors as $error) {
                    setAdminFlashMessage($error, 'error');
                }
            }
            
        } catch (PDOException $e) {
            error_log("Content form error: " . $e->getMessage());
            setAdminFlashMessage('Database error occurred. Please try again.', 'error');
        }
    }
}

$page_title = $is_edit ? 'Edit ' . ucfirst(rtrim($content_type, 's')) : 'Add New ' . ucfirst(rtrim($content_type, 's'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Bitversity Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4338ca;
            --secondary-color: #e5e7eb;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --sidebar-width: 280px;
        }

        body {
            background-color: var(--light-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid var(--secondary-color);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            overflow-y: auto;
        }

        .admin-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .admin-header {
            background: white;
            border-bottom: 1px solid var(--secondary-color);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid var(--secondary-color);
            text-align: center;
        }

        .sidebar-brand h4 {
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .sidebar-nav .nav-link {
            color: #6b7280;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.1);
        }

        .sidebar-nav .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .form-header {
            padding: 2rem;
            border-bottom: 1px solid var(--secondary-color);
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }

        .form-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 2px solid var(--secondary-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        .form-check-input {
            width: 1.2em;
            height: 1.2em;
        }

        .btn-group-custom {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .file-upload-area {
            border: 2px dashed var(--secondary-color);
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .file-upload-area:hover {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.05);
        }

        .file-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .admin-user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-weight: 600;
        }

        @media (max-width: 1200px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-main {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div class="sidebar-brand">
            <h4><i class="fas fa-graduation-cap me-2"></i>Bitversity</h4>
            <small class="text-muted">Admin Panel</small>
        </div>

        <nav class="sidebar-nav">
            <ul class="nav nav-pills flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-chart-bar"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i>
                        Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="content.php">
                        <i class="fas fa-book"></i>
                        Content
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">
                        <i class="fas fa-shopping-cart"></i>
                        Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="analytics.php">
                        <i class="fas fa-chart-line"></i>
                        Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logs.php">
                        <i class="fas fa-file-alt"></i>
                        Activity Logs
                    </a>
                </li>
                <li class="nav-item mt-auto">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-main">
        <!-- Header -->
        <header class="admin-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><?php echo $page_title; ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="content.php?type=<?php echo $content_type; ?>">Content</a></li>
                            <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                        </ol>
                    </nav>
                </div>
                <div class="admin-user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($current_admin['full_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($current_admin['full_name']); ?></div>
                        <small class="text-muted">Administrator</small>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="p-4">
            <?php if ($flash_message): ?>
                <div class="alert alert-<?php echo $flash_message['type'] === 'error' ? 'danger' : $flash_message['type']; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flash_message['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h3 class="mb-0">
                            <i class="fas fa-<?php echo $content_type === 'categories' ? 'folder' : ($content_type === 'projects' ? 'code' : ($content_type === 'games' ? 'gamepad' : 'book')); ?> me-2"></i>
                            <?php echo $page_title; ?>
                        </h3>
                    </div>

                    <div class="form-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <?php if ($content_type === 'categories'): ?>
                                <!-- Categories Form -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Category Name *</label>
                                            <input type="text" name="name" class="form-control" 
                                                   value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" 
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Slug *</label>
                                            <input type="text" name="slug" class="form-control" 
                                                   value="<?php echo htmlspecialchars($item['slug'] ?? ''); ?>"
                                                   pattern="[a-z0-9\-]+" title="Only lowercase letters, numbers, and hyphens">
                                            <small class="text-muted">URL-friendly version (auto-generated if empty)</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Icon</label>
                                            <input type="text" name="icon" class="form-control" 
                                                   value="<?php echo htmlspecialchars($item['icon'] ?? ''); ?>"
                                                   placeholder="book, code, gamepad, etc.">
                                            <small class="text-muted">FontAwesome icon name (without 'fa-')</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Color</label>
                                            <input type="color" name="color" class="form-control" 
                                                   value="<?php echo htmlspecialchars($item['color'] ?? '#6366f1'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Sort Order</label>
                                            <input type="number" name="sort_order" class="form-control" 
                                                   value="<?php echo intval($item['sort_order'] ?? 0); ?>" min="0">
                                        </div>
                                    </div>
                                </div>

                            <?php elseif ($content_type === 'books'): ?>
                                <!-- Books Form -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Book Title *</label>
                                            <input type="text" name="title" class="form-control" 
                                                   value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" 
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Author *</label>
                                            <input type="text" name="author" class="form-control" 
                                                   value="<?php echo htmlspecialchars($item['author'] ?? ''); ?>" 
                                                   required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">ISBN</label>
                                            <input type="text" name="isbn" class="form-control" 
                                                   value="<?php echo htmlspecialchars($item['isbn'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Category</label>
                                            <select name="category_id" class="form-select">
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo $cat['id']; ?>" 
                                                            <?php echo ($item['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cat['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Price ($)</label>
                                            <input type="number" name="price" class="form-control" 
                                                   value="<?php echo number_format($item['price'] ?? 0, 2, '.', ''); ?>" 
                                                   step="0.01" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Pages</label>
                                            <input type="number" name="pages" class="form-control" 
                                                   value="<?php echo intval($item['pages'] ?? 0); ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Language</label>
                                            <input type="text" name="language" class="form-control" 
                                                   value="<?php echo htmlspecialchars($item['language'] ?? 'English'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Publication Year</label>
                                            <input type="number" name="publication_year" class="form-control" 
                                                   value="<?php echo intval($item['publication_year'] ?? date('Y')); ?>" 
                                                   min="1900" max="<?php echo date('Y') + 5; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Publisher</label>
                                            <input type="text" name="publisher" class="form-control" 
                                                   value="<?php echo htmlspecialchars($item['publisher'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Book File</label>
                                            <input type="file" name="file_path" class="form-control" 
                                                   accept=".pdf,.epub,.mobi">
                                            <?php if ($item['file_path'] ?? ''): ?>
                                                <small class="text-muted">Current: <?php echo basename($item['file_path']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Cover Image</label>
                                    <input type="file" name="cover_image" class="form-control" 
                                           accept="image/*">
                                    <?php if ($item['cover_image'] ?? ''): ?>
                                        <img src="../<?php echo htmlspecialchars($item['cover_image']); ?>" 
                                             alt="Current cover" class="file-preview">
                                    <?php endif; ?>
                                </div>

                            <?php elseif ($content_type === 'projects'): ?>
                                <!-- Projects Form -->
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label class="form-label">Project Title *</label>
                                            <input type="text" name="title" class="form-control" 
                                                   value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" 
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Category</label>
                                            <select name="category_id" class="form-select">
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo $cat['id']; ?>" 
                                                            <?php echo ($item['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cat['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Price ($)</label>
                                            <input type="number" name="price" class="form-control" 
                                                   value="<?php echo number_format($item['price'] ?? 0, 2, '.', ''); ?>" 
                                                   step="0.01" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Difficulty</label>
                                            <select name="difficulty" class="form-select">
                                                <option value="beginner" <?php echo ($item['difficulty'] ?? '') === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                                <option value="intermediate" <?php echo ($item['difficulty'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                                <option value="advanced" <?php echo ($item['difficulty'] ?? '') === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Duration (Hours)</label>
                                            <input type="number" name="duration_hours" class="form-control" 
                                                   value="<?php echo intval($item['duration_hours'] ?? 0); ?>" min="0">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Requirements</label>
                                    <textarea name="requirements" class="form-control" rows="3" 
                                              placeholder="List any prerequisites or requirements for this project"><?php echo htmlspecialchars($item['requirements'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Learning Outcomes</label>
                                    <textarea name="learning_outcomes" class="form-control" rows="3" 
                                              placeholder="What will students learn from this project?"><?php echo htmlspecialchars($item['learning_outcomes'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Project Files</label>
                                    <input type="file" name="file_path" class="form-control" 
                                           accept=".zip,.rar">
                                    <?php if ($item['file_path'] ?? ''): ?>
                                        <small class="text-muted">Current: <?php echo basename($item['file_path']); ?></small>
                                    <?php endif; ?>
                                </div>

                            <?php elseif ($content_type === 'games'): ?>
                                <!-- Games Form -->
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label class="form-label">Game Title *</label>
                                            <input type="text" name="title" class="form-control" 
                                                   value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" 
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Category</label>
                                            <select name="category_id" class="form-select">
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo $cat['id']; ?>" 
                                                            <?php echo ($item['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cat['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Price ($)</label>
                                            <input type="number" name="price" class="form-control" 
                                                   value="<?php echo number_format($item['price'] ?? 0, 2, '.', ''); ?>" 
                                                   step="0.01" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Game Type</label>
                                            <select name="game_type" class="form-select">
                                                <option value="quiz" <?php echo ($item['game_type'] ?? '') === 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                                                <option value="puzzle" <?php echo ($item['game_type'] ?? '') === 'puzzle' ? 'selected' : ''; ?>>Puzzle</option>
                                                <option value="simulation" <?php echo ($item['game_type'] ?? '') === 'simulation' ? 'selected' : ''; ?>>Simulation</option>
                                                <option value="adventure" <?php echo ($item['game_type'] ?? '') === 'adventure' ? 'selected' : ''; ?>>Adventure</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Platform</label>
                                            <select name="platform" class="form-select">
                                                <option value="web" <?php echo ($item['platform'] ?? '') === 'web' ? 'selected' : ''; ?>>Web</option>
                                                <option value="mobile" <?php echo ($item['platform'] ?? '') === 'mobile' ? 'selected' : ''; ?>>Mobile</option>
                                                <option value="desktop" <?php echo ($item['platform'] ?? '') === 'desktop' ? 'selected' : ''; ?>>Desktop</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Max Players</label>
                                            <input type="number" name="max_players" class="form-control" 
                                                   value="<?php echo intval($item['max_players'] ?? 1); ?>" min="1" max="50">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Minimum Age</label>
                                            <input type="number" name="min_age" class="form-control" 
                                                   value="<?php echo intval($item['min_age'] ?? 0); ?>" min="0" max="100">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Instructions</label>
                                    <textarea name="instructions" class="form-control" rows="3" 
                                              placeholder="How to play this game"><?php echo htmlspecialchars($item['instructions'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Game Files</label>
                                            <input type="file" name="file_path" class="form-control" 
                                                   accept=".zip,.rar,.html,.js">
                                            <?php if ($item['file_path'] ?? ''): ?>
                                                <small class="text-muted">Current: <?php echo basename($item['file_path']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Thumbnail</label>
                                            <input type="file" name="thumbnail" class="form-control" 
                                                   accept="image/*">
                                            <?php if ($item['thumbnail'] ?? ''): ?>
                                                <img src="../<?php echo htmlspecialchars($item['thumbnail']); ?>" 
                                                     alt="Current thumbnail" class="file-preview">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                            <?php endif; ?>

                            <!-- Common Fields for All Content Types -->
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active" 
                                               <?php echo ($item['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            Active (visible to users)
                                        </label>
                                    </div>
                                </div>

                                <?php if ($content_type !== 'categories'): ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_featured" class="form-check-input" id="is_featured" 
                                                   <?php echo ($item['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_featured">
                                                Featured (highlight on homepage)
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="btn-group-custom">
                                <a href="content.php?type=<?php echo $content_type; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $is_edit ? 'Update' : 'Create'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-generate slug for categories
        document.querySelector('input[name="name"]')?.addEventListener('input', function() {
            if ('<?php echo $content_type; ?>' === 'categories') {
                const slug = this.value.toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .trim('-');
                
                const slugInput = document.querySelector('input[name="slug"]');
                if (slugInput && !slugInput.value) {
                    slugInput.value = slug;
                }
            }
        });

        // File upload preview
        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = document.getElementById(previewId);
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = previewId;
                        preview.className = 'file-preview';
                        input.parentNode.appendChild(preview);
                    }
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Add preview functionality to image inputs
        document.querySelector('input[name="cover_image"]')?.addEventListener('change', function() {
            previewImage(this, 'cover-preview');
        });

        document.querySelector('input[name="thumbnail"]')?.addEventListener('change', function() {
            previewImage(this, 'thumbnail-preview');
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>