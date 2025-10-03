<?php
require_once __DIR__ . '/../includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(BASE_PATH . '/user/dashboard.php');
}

$errors = [];
$success = '';

if ($_POST) {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $agreeTerms = isset($_POST['agree_terms']);
    
    // Validation
    if (empty($fullName)) {
        $errors[] = 'Full name is required';
    } elseif (strlen($fullName) < 2) {
        $errors[] = 'Full name must be at least 2 characters long';
    }
    
    if (empty($email)) {
        $errors[] = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    } else {
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email address is already registered';
        }
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number';
    }
    
    if (empty($confirmPassword)) {
        $errors[] = 'Please confirm your password';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!$agreeTerms) {
        $errors[] = 'You must agree to the Terms of Service and Privacy Policy';
    }
    
    // If no errors, create the user
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $verificationToken = generateToken();
            
            $stmt = $db->prepare("INSERT INTO users (full_name, email, password, email_verification_token, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$fullName, $email, $hashedPassword, $verificationToken]);
            
            $userId = $db->lastInsertId();
            
            // In a real application, you would send a verification email here
            // For now, we'll just activate the account immediately
            $stmt = $db->prepare("UPDATE users SET email_verified = 1, email_verified_at = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
            
            // Auto-login the user
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $fullName;
            $_SESSION['logged_in'] = true;
            
            // Log the registration
            error_log("New user registered: {$email}");
            
            redirect(BASE_PATH . '/user/dashboard.php', 'Welcome to ' . APP_NAME . '! Your account has been created successfully.', 'success');
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $errors[] = 'Registration failed. Please try again later.';
        }
    }
}
?>
<?php
$pageTitle = 'Sign Up - ' . APP_NAME;
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
            max-width: 450px;
            width: 100%;
            margin: 0 1rem;
        }
        
        .auth-header {
            text-align: center;
            padding: 2rem 2rem 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .auth-header .text-start {
            text-align: left !important;
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
        
        .password-strength {
            height: 4px;
            background: #eee;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            width: 0%;
        }
        
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #fd7e14; width: 50%; }
        .strength-good { background: #ffc107; width: 75%; }
        .strength-strong { background: #198754; width: 100%; }
        
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
    </style>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2 class="mb-3">Create Your Account</h2>
                <p class="text-muted mb-0">Join <?php echo APP_NAME; ?> and start your learning journey</p>
            </div>
            
            <div class="auth-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h6>Please fix the following errors:</h6>
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
                
                <form method="POST" id="registerForm">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo sanitize($_POST['full_name'] ?? ''); ?>" 
                                   placeholder="Enter your full name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo sanitize($_POST['email'] ?? ''); ?>" 
                                   placeholder="Enter your email address" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Create a strong password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <small class="text-muted">
                            Password must be at least 8 characters with uppercase, lowercase, and number
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm your password" required>
                            <span class="input-group-text" id="matchIcon">
                                <i class="fas fa-times text-danger" style="display: none;"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="agree_terms" name="agree_terms" required>
                            <label class="form-check-label" for="agree_terms">
                                I agree to the <a href="<?php echo BASE_PATH; ?>/public/terms.php" target="_blank">Terms of Service</a> 
                                and <a href="<?php echo BASE_PATH; ?>/public/privacy.php" target="_blank">Privacy Policy</a>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                    
                    <div class="text-center">
                        <p class="mb-0">
                            Already have an account? 
                            <a href="<?php echo BASE_PATH; ?>/auth/login.php" class="text-decoration-none">
                                Sign in here
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
        
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            
            if (strength >= 4) {
                strengthBar.classList.add('strength-strong');
            } else if (strength >= 3) {
                strengthBar.classList.add('strength-good');
            } else if (strength >= 2) {
                strengthBar.classList.add('strength-fair');
            } else if (strength >= 1) {
                strengthBar.classList.add('strength-weak');
            }
        });
        
        // Password confirmation validation
        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchIcon = document.getElementById('matchIcon').querySelector('i');
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    matchIcon.className = 'fas fa-check text-success';
                    matchIcon.style.display = 'block';
                } else {
                    matchIcon.className = 'fas fa-times text-danger';
                    matchIcon.style.display = 'block';
                }
            } else {
                matchIcon.style.display = 'none';
            }
        }
        
        document.getElementById('password').addEventListener('input', validatePasswordMatch);
        document.getElementById('confirm_password').addEventListener('input', validatePasswordMatch);
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const agreeTerms = document.getElementById('agree_terms').checked;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                e.preventDefault();
                return;
            }
            
            if (!agreeTerms) {
                alert('You must agree to the Terms of Service and Privacy Policy');
                e.preventDefault();
                return;
            }
            
            // Show loading state
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
            submitButton.disabled = true;
        });
        
        // Auto-focus first input
        document.getElementById('full_name').focus();
    </script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
