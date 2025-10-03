<?php
/**
 * Admin Login Page
 * 
 * Provides secure authentication for administrators with enhanced security features
 * and redirection to admin dashboard upon successful login.
 */

session_start();
require_once '../includes/config.php';

// Redirect if user is already logged in as admin
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle flash messages
if (isset($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    if ($flash['type'] === 'error') {
        $error_message = $flash['message'];
    } else if ($flash['type'] === 'success') {
        $success_message = $flash['message'];
    }
    unset($_SESSION['flash_message']);
}

// Handle admin login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']) ? 1 : 0;
    
    // Input validation
    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        try {
            // Check for user by email with admin role only
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Check if account is locked
                if ($user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
                    $locked_until = new DateTime($user['locked_until']);
                    $error_message = 'Account is temporarily locked until ' . 
                                   $locked_until->format('Y-m-d H:i:s') . ' due to multiple failed login attempts.';
                } 
                // Check if account is active
                elseif (!$user['is_active']) {
                    $error_message = 'This admin account has been deactivated. Please contact system administrator.';
                }
                // Check if email is verified (skip check for now since all users are verified)
                // elseif (!$user['email_verified']) {
                //     $error_message = 'Email address not verified. Please verify your email before logging in.';
                // }
                // Verify password
                elseif (password_verify($password, $user['password'])) {
                    // Successful login - reset failed attempts and clear any lock
                    $stmt = $db->prepare("UPDATE users SET 
                                        failed_login_attempts = 0, 
                                        locked_until = NULL, 
                                        last_login = NOW() 
                                        WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['last_activity'] = time();
                    $_SESSION['login_time'] = time();
                    
                    // Handle remember me functionality
                    if ($remember_me) {
                        // Generate secure remember token
                        $remember_token = bin2hex(random_bytes(32));
                        $token_hash = password_hash($remember_token, PASSWORD_DEFAULT);
                        $expires_at = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days
                        
                        // Store token in database
                        $stmt = $db->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) 
                                            VALUES (?, ?, ?) 
                                            ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)");
                        $stmt->execute([$user['id'], $token_hash, $expires_at]);
                        
                        // Set secure cookie
                        setcookie('remember_admin', $user['id'] . ':' . $remember_token, 
                                time() + (86400 * 30), '/', '', true, true);
                    }
                    
                    // Log successful admin login
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                                        VALUES (?, 'admin_login', 'Admin logged in successfully', ?, ?)");
                    $stmt->execute([
                        $user['id'],
                        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                    ]);
                    
                    // Redirect to admin dashboard
                    header('Location: dashboard.php');
                    exit();
                    
                } else {
                    // Wrong password - increment failed attempts
                    $failed_attempts = $user['failed_login_attempts'] + 1;
                    $lock_until = null;
                    
                    // Lock account after 5 failed attempts for 30 minutes
                    if ($failed_attempts >= 5) {
                        $lock_until = date('Y-m-d H:i:s', time() + (30 * 60));
                        $error_message = 'Too many failed login attempts. Account locked for 30 minutes.';
                    } else {
                        $remaining_attempts = 5 - $failed_attempts;
                        $error_message = "Invalid email or password. {$remaining_attempts} attempt(s) remaining before account lock.";
                    }
                    
                    // Update failed attempts and lock status
                    $stmt = $db->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
                    $stmt->execute([$failed_attempts, $lock_until, $user['id']]);
                    
                    // Log failed admin login attempt
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                                        VALUES (?, 'failed_admin_login', 'Failed admin login attempt', ?, ?)");
                    $stmt->execute([
                        $user['id'],
                        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                    ]);
                }
            } else {
                $error_message = 'Invalid email or password, or insufficient privileges.';
                
                // Log unknown admin login attempt
                $stmt = $db->prepare("INSERT INTO activity_logs (action, description, ip_address, user_agent, details) 
                                    VALUES ('failed_admin_login', 'Failed admin login attempt - unknown email', ?, ?, ?)");
                $stmt->execute([
                    $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                    json_encode(['email' => $email])
                ]);
            }
            
        } catch (PDOException $e) {
            $error_message = 'Database error occurred. Please try again.';
            error_log("Admin login error: " . $e->getMessage());
        }
    }
}
?>

<?php
$pageTitle = 'Admin Login - Bitversity';
include __DIR__ . '/../includes/header.php';
?>

<style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4338ca;
            --secondary-color: #e5e7eb;
            --danger-color: #ef4444;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
        }

        .admin-login-page {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            min-height: calc(100vh - 120px);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .admin-login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .admin-login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 440px;
            padding: 0;
            overflow: hidden;
            position: relative;
        }

        .admin-login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
        }

        .admin-header {
            background: var(--light-color);
            padding: 40px 40px 20px;
            text-align: center;
            border-bottom: 1px solid var(--secondary-color);
        }

        .admin-logo {
            width: 80px;
            height: 80px;
            background: var(--primary-color);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .admin-logo i {
            font-size: 2.5rem;
            color: white;
        }

        .admin-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 8px;
        }

        .admin-subtitle {
            color: #6b7280;
            font-size: 1rem;
            margin-bottom: 0;
        }

        .admin-form {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-control {
            height: 50px;
            border: 2px solid var(--secondary-color);
            border-radius: 12px;
            padding: 0 20px 0 50px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            z-index: 10;
        }

        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            z-index: 10;
            padding: 5px;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            margin-right: 10px;
        }

        .btn-admin-login {
            background: var(--primary-color);
            color: white;
            border: none;
            height: 50px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-admin-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
        }

        .btn-admin-login:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .admin-footer {
            padding: 20px 40px;
            background: var(--light-color);
            border-top: 1px solid var(--secondary-color);
            text-align: center;
        }

        .admin-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .admin-footer a:hover {
            text-decoration: underline;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .security-notice {
            background: rgba(251, 191, 36, 0.1);
            color: #92400e;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }

        .security-notice i {
            margin-right: 10px;
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .admin-form, .admin-header, .admin-footer {
                padding: 30px;
            }
            
            .admin-login-container {
                padding: 15px;
            }
        }
    </style>
<div class="admin-login-page">
    <div class="admin-login-container">
        <div class="admin-login-card">
            <div class="admin-header">
                <div class="admin-logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="admin-title">Admin Panel</h1>
                <p class="admin-subtitle">Secure access to Bitversity administration</p>
            </div>

            <form method="POST" action="" class="admin-form">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <div class="security-notice">
                    <i class="fas fa-info-circle"></i>
                    This is a secure admin area. Only authorized administrators may access this system.
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-2"></i>Email Address
                    </label>
                    <div class="input-group">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               placeholder="admin@bitversity.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="Enter your admin password"
                               required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-toggle-icon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="remember_me" name="remember_me" class="form-check-input">
                    <label for="remember_me" class="form-check-label">
                        Keep me signed in for 30 days
                    </label>
                </div>

                <button type="submit" class="btn btn-admin-login">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Access Admin Panel
                </button>
            </form>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password visibility toggle
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '-toggle-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Auto-focus on first empty field
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            if (!emailInput.value) {
                emailInput.focus();
            } else {
                passwordInput.focus();
            }
        });

        // Form validation
        document.querySelector('.admin-form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
        });
    </script>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
