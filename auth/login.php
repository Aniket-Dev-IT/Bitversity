<?php
require_once __DIR__ . '/../includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(BASE_PATH . '/user/dashboard.php');
}

$errors = [];
$success = '';

// Check for registration success message
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Registration successful! Please log in to your account.';
}

// Check for logout message
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $success = 'You have been successfully logged out.';
}

// Check for password reset success
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $success = 'Password reset successful! Please log in with your new password.';
}

if ($_POST) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    // Validation - accept both username and email
    if (empty($email)) {
        $errors[] = 'Username or email address is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // If no validation errors, attempt login
    if (empty($errors)) {
        try {
            // Rate limiting - prevent brute force attacks
            $rateLimitKey = 'login_attempts_' . $_SERVER['REMOTE_ADDR'];
            $attempts = $_SESSION[$rateLimitKey] ?? 0;
            $lastAttempt = $_SESSION[$rateLimitKey . '_time'] ?? 0;
            
            // Reset attempts if more than 15 minutes have passed
            if (time() - $lastAttempt > 900) {
                $attempts = 0;
            }
            
            if ($attempts >= 5) {
                $errors[] = 'Too many failed login attempts. Please try again in 15 minutes.';
            } else {
                // Get user from database (allow both email and username)
                $stmt = $db->prepare("SELECT id, first_name, last_name, email, username, password, is_active, failed_login_attempts, locked_until FROM users WHERE email = ? OR username = ?");
                $stmt->execute([$email, $email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    // Check if account is locked
                    if (isset($user['locked_until']) && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $lockTime = date('g:i A', strtotime($user['locked_until']));
                        $errors[] = "Account is temporarily locked until {$lockTime} due to multiple failed login attempts.";
                    }
                    // Check if account is active
                    elseif (!$user['is_active']) {
                        $errors[] = 'Your account has been deactivated. Please contact support.';
                    }
                    else {
                        // Successful login
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                        $_SESSION['logged_in'] = true;
                        $_SESSION['login_time'] = time();
                        
                        // Clear rate limiting
                        unset($_SESSION[$rateLimitKey]);
                        unset($_SESSION[$rateLimitKey . '_time']);
                        
                        // Clear failed login attempts
                        $stmt = $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        
                        // Handle remember me
                        if ($rememberMe) {
                            $token = generateToken(64);
                            $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
                            
                            // Store remember token in database
                            $stmt = $db->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)");
                            $stmt->execute([$user['id'], hash('sha256', $token), $expires]);
                            
                            // Set cookie
                            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                        }
                        
                        // Log successful login
                        error_log("User logged in: {$user['email']} from IP: {$_SERVER['REMOTE_ADDR']}");
                        
                        // Redirect to intended page or dashboard
                        $redirectTo = $_SESSION['intended_url'] ?? BASE_PATH . '/user/dashboard.php';
                        unset($_SESSION['intended_url']);
                        
                        redirect($redirectTo, 'Welcome back, ' . trim($user['first_name'] . ' ' . $user['last_name']) . '!', 'success');
                    }
                } else {
                    // Failed login
                    $_SESSION[$rateLimitKey] = $attempts + 1;
                    $_SESSION[$rateLimitKey . '_time'] = time();
                    
                    // Update failed attempts in database if user exists
                    if ($user) {
                        $failedAttempts = $user['failed_login_attempts'] + 1;
                        $lockUntil = null;
                        
                        // Lock account after 5 failed attempts
                        if ($failedAttempts >= 5) {
                            $lockUntil = date('Y-m-d H:i:s', time() + (30 * 60)); // Lock for 30 minutes
                        }
                        
                        $stmt = $db->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
                        $stmt->execute([$failedAttempts, $lockUntil, $user['id']]);
                    }
                    
                    $errors[] = 'Invalid username/email or password';
                    
                    // Log failed login attempt
                    error_log("Failed login attempt for: {$email} from IP: {$_SERVER['REMOTE_ADDR']}");
                }
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $errors[] = 'Login failed. Please try again later.';
        }
    }
}

// Check for remember me token
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $hashedToken = hash('sha256', $token);
    
    $stmt = $db->prepare("SELECT rt.user_id, u.first_name, u.last_name, u.email FROM remember_tokens rt 
                         JOIN users u ON rt.user_id = u.id 
                         WHERE rt.token = ? AND rt.expires_at > NOW() AND u.is_active = 1");
    $stmt->execute([$hashedToken]);
    $tokenData = $stmt->fetch();
    
    if ($tokenData) {
        // Auto-login user
        $_SESSION['user_id'] = $tokenData['user_id'];
        $_SESSION['user_email'] = $tokenData['email'];
        $_SESSION['user_name'] = trim($tokenData['first_name'] . ' ' . $tokenData['last_name']);
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Update last login
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$tokenData['user_id']]);
        
        redirect(BASE_PATH . '/user/dashboard.php');
    } else {
        // Invalid or expired token, remove cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
}
?>
<?php
$pageTitle = 'Sign In - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<style>
        .auth-container {
            min-height: calc(100vh - 120px);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
            margin-top: 0;
        }
        
        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 400px;
            width: 100%;
            margin: 0 1rem;
        }
        
        .auth-header {
            text-align: center;
            padding: 2rem 2rem 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .auth-body {
            padding: 2rem;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .social-login {
            border-top: 1px solid #eee;
            padding-top: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .divider {
            position: relative;
            text-align: center;
            margin: 1.5rem 0;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #ddd;
        }
        
        .divider span {
            background: rgba(255, 255, 255, 0.95);
            padding: 0 1rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .password-toggle {
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
    </style>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2 class="mb-3">Welcome Back</h2>
                <p class="text-muted mb-0">Sign in to your <?php echo APP_NAME; ?> account</p>
            </div>
            
            <div class="auth-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h6>Login Failed:</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo sanitize($success); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="loginForm">
                    <div class="mb-3">
                        <label for="email" class="form-label">Username or Email</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="email" name="email" 
                                   value="<?php echo sanitize($_POST['email'] ?? ''); ?>" 
                                   placeholder="Enter username or email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter your password" required>
                            <button class="btn btn-outline-secondary password-toggle" type="button" id="togglePassword">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">
                                    Remember me for 30 days
                                </label>
                            </div>
                        </div>
                        <div class="col-auto">
                            <a href="<?php echo BASE_PATH; ?>/auth/forgot-password.php" class="text-decoration-none">
                                Forgot Password?
                            </a>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                    
                    <div class="text-center">
                        <p class="mb-0">
                            Don't have an account? 
                            <a href="<?php echo BASE_PATH; ?>/auth/register.php" class="text-decoration-none">
                                Create one here
                            </a>
                        </p>
                    </div>
                </form>
                
                <div class="divider">
                    <span>or continue with</span>
                </div>
                
                <div class="social-login">
                    <div class="row">
                        <div class="col-6">
                            <button class="btn btn-outline-danger w-100" disabled>
                                <i class="fab fa-google me-2"></i>Google
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-primary w-100" disabled>
                                <i class="fab fa-facebook me-2"></i>Facebook
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        });
        
        // Form submission handling
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            
            // Show loading state
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
            submitButton.disabled = true;
            
            // Re-enable button after 5 seconds in case of slow response
            setTimeout(function() {
                if (submitButton.disabled) {
                    submitButton.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Sign In';
                    submitButton.disabled = false;
                }
            }, 5000);
        });
        
        // Auto-focus email input
        document.getElementById('email').focus();
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+Enter or Cmd+Enter to submit
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
        
        // Clear any previous error states on input
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const feedback = this.parentNode.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.remove();
                }
            });
        });
    </script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
