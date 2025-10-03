<footer class="footer" style="display: block !important; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%) !important;">
    <div class="container py-4">
        <div class="row">
            <div class="col-md-4">
                <h5 class="text-white mb-3"><i class="fas fa-graduation-cap me-2"></i><?php echo APP_NAME; ?></h5>
                <p class="text-white-50">Your digital learning platform for books, projects, and interactive games. Master new skills with our comprehensive resources.</p>
                <div class="social-links mt-3">
                    <a href="#" class="text-white me-3" style="font-size: 1.2rem;"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-white me-3" style="font-size: 1.2rem;"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-white me-3" style="font-size: 1.2rem;"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-white me-3" style="font-size: 1.2rem;"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            
            <div class="col-md-2">
                <h6 class="text-white mb-3">Quick Links</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/index.php" class="text-white-50 text-decoration-none hover-text-white">Home</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/public/books.php" class="text-white-50 text-decoration-none hover-text-white">Books</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/public/projects.php" class="text-white-50 text-decoration-none hover-text-white">Projects</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/public/games.php" class="text-white-50 text-decoration-none hover-text-white">Games</a></li>
                </ul>
            </div>
            
            <div class="col-md-2">
                <h6 class="text-white mb-3">Categories</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/public/books.php?category=programming" class="text-white-50 text-decoration-none hover-text-white">Programming</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/public/books.php?category=web-development" class="text-white-50 text-decoration-none hover-text-white">Web Development</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/public/books.php?category=mobile-development" class="text-white-50 text-decoration-none hover-text-white">Mobile Dev</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/public/books.php?category=artificial-intelligence" class="text-white-50 text-decoration-none hover-text-white">AI & ML</a></li>
                </ul>
            </div>
            
            <div class="col-md-2">
                <h6 class="text-white mb-3">Support</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/pages/help.php" class="text-white-50 text-decoration-none hover-text-white">Help Center</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/pages/contact.php" class="text-white-50 text-decoration-none hover-text-white">Contact Us</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/pages/privacy.php" class="text-white-50 text-decoration-none hover-text-white">Privacy Policy</a></li>
                    <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/pages/terms.php" class="text-white-50 text-decoration-none hover-text-white">Terms of Service</a></li>
                </ul>
            </div>
            
            <div class="col-md-2">
                <h6 class="text-white mb-3">Account</h6>
                <ul class="list-unstyled">
                    <?php if (isLoggedIn()): ?>
                        <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/user/dashboard.php" class="text-white-50 text-decoration-none hover-text-white">Dashboard</a></li>
                        <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/user/library.php" class="text-white-50 text-decoration-none hover-text-white">My Library</a></li>
                        <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/user/orders.php" class="text-white-50 text-decoration-none hover-text-white">Orders</a></li>
                    <?php else: ?>
                        <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/auth/login.php" class="text-white-50 text-decoration-none hover-text-white">Login</a></li>
                        <li class="mb-2"><a href="<?php echo BASE_PATH; ?>/auth/register.php" class="text-white-50 text-decoration-none hover-text-white">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="border-top" style="border-color: rgba(255,255,255,0.2) !important;">
        <div class="container py-2">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-0 text-white-50">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>
</footer>

</body>
</html>
