<?php
require_once __DIR__ . '/../includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(BASE_PATH . '/user/dashboard.php');
}

$errors = [];
$success = '';

if ($_POST) {
    $email = trim($_POST['email'] ?? '');
    
    // Validation
    if (empty($email)) {
        $errors[] = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($errors)) {
        try {
            // Check if user exists and is active
            $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Check if there's already a recent reset request
                $stmt = $db->prepare("SELECT created_at FROM password_resets WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                $stmt->execute([$email]);
                $recentRequest = $stmt->fetch();
                
                if ($recentRequest) {
                    $errors[] = 'A password reset email was already sent recently. Please check your inbox or try again in 15 minutes.';
                } else {
                    // Generate reset token
                    $token = generateToken(64);
                    $hashedToken = hash('sha256', $token);
                    $expires = date('Y-m-d H:i:s', time() + (60 * 60)); // 1 hour expiry
                    
                    // Clean up old reset requests for this email
                    $stmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
                    $stmt->execute([$email]);
                    
                    // Insert new reset request
                    $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([$email, $hashedToken, $expires]);
                    
                    // In a real application, send email here
                    // For demo purposes, we'll just show a success message
                    // 
                    // Example email content:
                    // $resetLink = BASE_URL . '/auth/reset-password.php?token=' . urlencode($token);
                    // $subject = 'Password Reset - ' . APP_NAME;
                    // $message = "Hello {$user['full_name']},\n\n";
                    // $message .= "You requested a password reset for your " . APP_NAME . " account.\n\n";
                    // $message .= "Click the link below to reset your password:\n";
                    // $message .= $resetLink . "\n\n";
                    // $message .= "This link will expire in 1 hour.\n\n";
                    // $message .= "If you didn't request this, please ignore this email.\n\n";
                    // $message .= "Best regards,\n" . APP_NAME . " Team";
                    // 
                    // mail($email, $subject, $message);
                    
                    // Log the reset request
                    error_log("Password reset requested for: {$email} from IP: {$_SERVER['REMOTE_ADDR']}");
                    
                    $success = 'If an account with that email exists, we\'ve sent you a password reset link.';
                }
            } else {
                // Don't reveal if email exists or not for security
                $success = 'If an account with that email exists, we\'ve sent you a password reset link.';
            }
            
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $errors[] = 'Unable to process password reset request. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <style>
        .auth-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
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
        
        .reset-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <i class="fas fa-key reset-icon"></i>
                <h2 class="mb-3">Forgot Password?</h2>
                <p class="text-muted mb-0">Enter your email address and we'll send you a reset link</p>
            </div>
            
            <div class="auth-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h6>Error:</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo sanitize($success); ?>
                </div>
                
                <div class="text-center mt-4">
                    <p class="text-muted mb-3">
                        <i class="fas fa-envelope me-2"></i>
                        Check your email for the reset link
                    </p>
                    <div class="d-grid gap-2">
                        <a href="<?php echo BASE_PATH; ?>/auth/login.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Login
                        </a>
                    </div>
                </div>
                
                <?php else: ?>
                
                <form method="POST" id="resetForm">
                    <div class="mb-4">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo sanitize($_POST['email'] ?? ''); ?>" 
                                   placeholder="Enter your email address" required>
                        </div>
                        <div class="form-text">
                            We'll send a password reset link to this email address
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                    </button>
                </form>
                
                <div class="text-center">
                    <p class="mb-2">
                        Remember your password? 
                        <a href="<?php echo BASE_PATH; ?>/auth/login.php" class="text-decoration-none">
                            Sign in here
                        </a>
                    </p>
                    <p class="mb-0">
                        Don't have an account? 
                        <a href="<?php echo BASE_PATH; ?>/auth/register.php" class="text-decoration-none">
                            Create one here
                        </a>
                    </p>
                </div>
                
                <?php endif; ?>
                
                <!-- Security Notice -->
                <div class="alert alert-info mt-4">
                    <h6><i class="fas fa-shield-alt me-2"></i>Security Notice</h6>
                    <small class="text-muted">
                        • Password reset links expire in 1 hour<br>
                        • Only the most recent reset link is valid<br>
                        • If you don't receive an email, check your spam folder
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form submission handling
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            
            // Show loading state
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            submitButton.disabled = true;
            
            // Re-enable button after 10 seconds in case of slow response
            setTimeout(function() {
                if (submitButton.disabled) {
                    submitButton.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Reset Link';
                    submitButton.disabled = false;
                }
            }, 10000);
        });
        
        // Auto-focus email input
        document.getElementById('email')?.focus();
        
        // Clear error states on input
        document.getElementById('email')?.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
        
        // Auto-redirect after successful submission
        <?php if ($success): ?>
        setTimeout(function() {
            window.location.href = '<?php echo BASE_PATH; ?>/auth/login.php';
        }, 10000); // Redirect after 10 seconds
        <?php endif; ?>
    </script>
</body>
</html>