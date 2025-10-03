<?php
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/config.php';
}

$currentUser = null;
$cartCount = 0;

// Only get user data if logged in
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
}

// Get cart count for logged in users
if (isLoggedIn()) {
    $stmt = $db->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $cartCount = $result['count'] ?? 0;
}

// Set default page title if not set
if (!isset($pageTitle) && !isset($page_title)) {
    $pageTitle = APP_NAME . ' - Digital Learning Platform';
} elseif (isset($page_title) && !isset($pageTitle)) {
    $pageTitle = $page_title;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
        }
        
        .navbar {
            padding: 0.75rem 0;
            backdrop-filter: blur(10px);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .navbar-brand:hover {
            transform: scale(1.05);
        }
        
        .navbar-nav .nav-link {
            font-weight: 500;
            color: #495057 !important;
            padding: 0.5rem 0.8rem !important;
            border-radius: 8px;
            margin: 0 0.1rem;
            transition: all 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            color: #0d6efd !important;
            background-color: rgba(13, 110, 253, 0.1);
            transform: translateY(-2px);
        }
        
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-radius: 12px;
            padding: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .dropdown-item {
            border-radius: 8px;
            padding: 0.7rem 1rem;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 25px;
            transition: all 0.3s ease;
            padding: 0.6rem 1rem;
        }
        
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }
        
        .input-group .btn {
            border-radius: 0 25px 25px 0;
            border: 2px solid #0d6efd;
            border-left: none;
        }
        
        .btn {
            border-radius: 8px;
            font-weight: 500;
        }
        
        .card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .rating {
            color: #ffc107;
        }
        
        .rating .text-muted {
            color: #dee2e6 !important;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.5em 0.75em;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body class="bg-light" data-logged-in="<?= isLoggedIn() ? 'true' : 'false' ?>">
<nav class="navbar navbar-expand-lg navbar-light shadow-sm" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%), radial-gradient(circle at 20% 50%, rgba(13, 110, 253, 0.03) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(13, 110, 253, 0.02) 0%, transparent 50%); border-bottom: 2px solid #e3f2fd;">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="<?php echo BASE_PATH; ?>/index.php" style="font-size: 1.5rem;">
            <i class="fas fa-graduation-cap me-2"></i><?php echo APP_NAME; ?>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_PATH; ?>/index.php">
                        <i class="fas fa-home me-1"></i>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_PATH; ?>/public/books.php">
                        <i class="fas fa-book me-1"></i>Books
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_PATH; ?>/public/projects.php">
                        <i class="fas fa-code me-1"></i>Projects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_PATH; ?>/public/games.php">
                        <i class="fas fa-gamepad me-1"></i>Games
                    </a>
                </li>
                <?php if (isLoggedIn()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_PATH; ?>/user/custom-orders.php">
                        <i class="fas fa-hammer me-1"></i>Custom Orders
                    </a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_PATH; ?>/custom-orders.php" title="Learn about our custom development services">
                        <i class="fas fa-hammer me-1"></i>Custom Orders
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <!-- Search Form -->
            <form class="d-flex me-3 position-relative" method="GET" action="<?php echo BASE_PATH; ?>/public/search.php">
                <div class="input-group">
                    <input class="form-control form-control-sm" 
                           type="search" 
                           placeholder="Search books, projects, games..." 
                           name="q" 
                           id="searchInput"
                           autocomplete="off"
                           value="<?php echo isset($_GET['q']) ? sanitize($_GET['q']) : ''; ?>">
                    <button class="btn btn-primary btn-sm" type="submit" style="padding: 6px 12px;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                
                <!-- Autocomplete Suggestions -->
                <div id="searchSuggestions" class="search-suggestions position-absolute w-100" style="display: none; top: 100%; z-index: 1050;">
                    <!-- Suggestions will be populated by JavaScript -->
                </div>
            </form>
            
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <!-- Cart -->
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="<?php echo BASE_PATH; ?>/user/cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($cartCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $cartCount; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- User Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if ($currentUser['profile_photo']): ?>
                                <img src="<?php echo BASE_PATH; ?>/<?php echo htmlspecialchars($currentUser['profile_photo']); ?>" 
                                     alt="Profile" class="rounded-circle me-2" width="32" height="32" 
                                     style="object-fit: cover; border: 2px solid #e9ecef;">
                            <?php else: ?>
                                <i class="fas fa-user me-2"></i>
                            <?php endif; ?>
                            <?php echo sanitize($currentUser['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/user/dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/user/library.php">
                                <i class="fas fa-book-reader me-2"></i>My Library
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/user/wishlist.php">
                                <i class="fas fa-heart me-2"></i>Wishlist
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/user/orders.php">
                                <i class="fas fa-receipt me-2"></i>Orders
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/user/custom-orders.php">
                                <i class="fas fa-hammer me-2"></i>Custom Orders
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/user/profile.php">
                                <i class="fas fa-user-cog me-2"></i>Profile
                            </a></li>
                            <?php if (isAdmin()): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/admin/dashboard.php">
                                    <i class="fas fa-cogs me-2"></i>Admin Panel
                                </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_PATH; ?>/auth/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_PATH; ?>/auth/register.php">
                            <i class="fas fa-user-plus me-1"></i>Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php
// Display flash messages
$flashMessage = getFlashMessage();
if ($flashMessage):
?>
<div class="container mt-3">
    <div class="alert alert-<?php echo $flashMessage['type'] === 'error' ? 'danger' : $flashMessage['type']; ?> alert-dismissible fade show">
        <?php echo sanitize($flashMessage['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Initialize Bootstrap Components -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Bootstrap initialization starting...');
    console.log('Bootstrap available:', typeof bootstrap !== 'undefined');
    
    // Wait a bit to ensure everything is loaded
    setTimeout(function() {
        try {
            // Check for dropdowns
            const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
            console.log('Found', dropdownElementList.length, 'dropdown toggles');
            
            if (dropdownElementList.length > 0 && typeof bootstrap !== 'undefined') {
                // Initialize each dropdown individually with error handling
                dropdownElementList.forEach((dropdownToggleEl, index) => {
                    try {
                        const dropdown = new bootstrap.Dropdown(dropdownToggleEl);
                        console.log('Initialized dropdown', index + 1);
                        
                        // Add manual click handler as backup
                        dropdownToggleEl.addEventListener('click', function(e) {
                            console.log('Dropdown clicked, preventing default and toggling');
                            e.preventDefault();
                            e.stopPropagation();
                            dropdown.toggle();
                        });
                        
                    } catch (error) {
                        console.error('Error initializing dropdown', index + 1, ':', error);
                    }
                });
                
                // Check dropdown menu visibility
                const dropdownMenus = document.querySelectorAll('.dropdown-menu');
                dropdownMenus.forEach((menu, index) => {
                    const computedStyle = getComputedStyle(menu);
                    console.log('Dropdown menu', index + 1, 'display:', computedStyle.display, 'visibility:', computedStyle.visibility);
                });
                
            } else {
                console.log('No dropdowns found or Bootstrap not available');
            }
        } catch (error) {
            console.error('Bootstrap initialization error:', error);
        }
    }, 100); // Small delay to ensure DOM is ready
});

</script>

<!-- Search functionality -->
<script>
// Auto-complete search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const suggestions = document.getElementById('searchSuggestions');
    let searchTimeout;
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                suggestions.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch('<?= BASE_PATH ?>/api/search_suggestions.php?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.suggestions.length > 0) {
                            let html = '';
                            data.suggestions.forEach(item => {
                                html += `<div class="search-suggestion-item" onclick="selectSuggestion('${item.title}', '${item.type}', ${item.id})">`;
                                html += `<div class="fw-medium">${item.title}</div>`;
                                html += `<small class="text-muted">${item.type}</small>`;
                                html += `</div>`;
                            });
                            suggestions.innerHTML = html;
                            suggestions.style.display = 'block';
                        } else {
                            suggestions.style.display = 'none';
                        }
                    })
                    .catch(() => {
                        suggestions.style.display = 'none';
                    });
            }, 300);
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestions.contains(e.target)) {
                suggestions.style.display = 'none';
            }
        });
    }
});

function selectSuggestion(title, type, id) {
    let url = '<?= BASE_PATH ?>/';
    switch(type.toLowerCase()) {
        case 'book':
            url += 'public/book_detail.php?id=' + id;
            break;
        case 'project':
            url += 'public/project_detail.php?id=' + id;
            break;
        case 'game':
            url += 'public/game_detail.php?id=' + id;
            break;
        default:
            url += 'public/search.php?q=' + encodeURIComponent(title);
    }
    window.location.href = url;
}
</script>
