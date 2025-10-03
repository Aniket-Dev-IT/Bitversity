<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

$pageTitle = 'Contact Us';
$pageDescription = 'Get in touch with our team at Bitversity. We\'re here to help with any questions about our books, projects, and games.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    
    $errors = [];
    
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (empty($subject)) $errors[] = 'Subject is required';
    if (empty($message)) $errors[] = 'Message is required';
    
    if (empty($errors)) {
        // Here you would typically send an email or save to database
        $success = "Thank you for contacting us! We'll get back to you within 24 hours.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <meta name="description" content="<?= $pageDescription ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="page-header" style="background-image: url('https://images.unsplash.com/photo-1423666639041-f56000c27a9a?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(102, 126, 234, 0.2); z-index: 1;"></div>
        <div class="container" style="position: relative; z-index: 2;">
            <div class="text-center text-white">
                <h1 class="display-4 mb-3" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                    <i class="fas fa-envelope me-3"></i>Contact Us
                </h1>
                <p class="lead" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                    We'd love to hear from you. Send us a message and we'll respond as soon as possible.
                </p>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?= $success ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body p-5">
                        <h3 class="mb-4">Send us a message</h3>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject *</label>
                                <input type="text" class="form-control" id="subject" name="subject" 
                                       value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="message" class="form-label">Message *</label>
                                <textarea class="form-control" id="message" name="message" rows="6" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-5">
            <div class="col-md-4 text-center">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h5>Email Us</h5>
                    <p class="text-muted">hello@bitversity.com<br>support@bitversity.com</p>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h5>Response Time</h5>
                    <p class="text-muted">We typically respond<br>within 24 hours</p>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h5>Support Hours</h5>
                    <p class="text-muted">Monday - Friday<br>9:00 AM - 6:00 PM EST</p>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>