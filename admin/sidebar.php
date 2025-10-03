<div class="col-md-3 col-lg-2">
    <div class="list-group">
        <a href="index.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>
        <a href="books.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'books.php' ? 'active' : '' ?>">
            <i class="fas fa-book me-2"></i>Books
        </a>
        <a href="projects.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'projects.php' ? 'active' : '' ?>">
            <i class="fas fa-code me-2"></i>Projects
        </a>
        <a href="games.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'games.php' ? 'active' : '' ?>">
            <i class="fas fa-gamepad me-2"></i>Games
        </a>
        <a href="users.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-users me-2"></i>Users
        </a>
        <a href="orders.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart me-2"></i>Orders
        </a>
        <a href="custom-orders.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'custom-orders.php' ? 'active' : '' ?>">
            <i class="fas fa-hammer me-2"></i>Custom Orders
            <?php
            // Show pending custom orders count
            try {
                $pending_custom_orders = $db->query("SELECT COUNT(*) FROM custom_order_requests WHERE status IN ('pending', 'under_review')")->fetchColumn();
                if ($pending_custom_orders > 0) {
                    echo '<span class="badge bg-warning rounded-pill ms-2">' . $pending_custom_orders . '</span>';
                }
            } catch (Exception $e) {
                // Silently ignore errors
            }
            ?>
        </a>
        <a href="reviews.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'reviews.php' ? 'active' : '' ?>">
            <i class="fas fa-star me-2"></i>Reviews
        </a>
        <a href="categories.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'active' : '' ?>">
            <i class="fas fa-tags me-2"></i>Categories
        </a>
        <a href="analytics.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line me-2"></i>Analytics
        </a>
        <a href="logs.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'logs.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list me-2"></i>Activity Logs
        </a>
        <a href="settings.php" class="list-group-item list-group-item-action <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog me-2"></i>Settings
        </a>
        <hr>
        <a href="../index.php" class="list-group-item list-group-item-action">
            <i class="fas fa-home me-2"></i>View Site
        </a>
        <a href="../logout.php" class="list-group-item list-group-item-action text-danger">
            <i class="fas fa-sign-out-alt me-2"></i>Logout
        </a>
    </div>
</div>