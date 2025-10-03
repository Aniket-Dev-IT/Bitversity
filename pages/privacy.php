<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

$pageTitle = 'Privacy Policy';
$pageDescription = 'Learn how Bitversity protects your privacy and handles your personal information.';
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
    
    <div class="page-header" style="background-image: url('https://images.unsplash.com/photo-1450101499163-c8848c66ca85?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(102, 126, 234, 0.2); z-index: 1;"></div>
        <div class="container" style="position: relative; z-index: 2;">
            <div class="text-center text-white">
                <h1 class="display-4 mb-3" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                    <i class="fas fa-shield-alt me-3"></i>Privacy Policy
                </h1>
                <p class="lead" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                    Your privacy is important to us. Learn how we protect your information.
                </p>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-body p-5">
                        <p class="text-muted">Last updated: <?= date('F j, Y') ?></p>
                        
                        <h3>1. Information We Collect</h3>
                        <p>We collect information you provide directly to us, such as when you create an account, make a purchase, or contact us for support.</p>
                        
                        <h4>Personal Information</h4>
                        <ul>
                            <li>Name and email address</li>
                            <li>Payment information (processed securely)</li>
                            <li>Learning preferences and progress</li>
                            <li>Communications with our support team</li>
                        </ul>
                        
                        <h4>Usage Information</h4>
                        <ul>
                            <li>Pages visited and content accessed</li>
                            <li>Time spent on platform</li>
                            <li>Device and browser information</li>
                            <li>IP address and location data</li>
                        </ul>
                        
                        <h3>2. How We Use Your Information</h3>
                        <p>We use the information we collect to:</p>
                        <ul>
                            <li>Provide, maintain, and improve our services</li>
                            <li>Process transactions and send related information</li>
                            <li>Send technical notices and support messages</li>
                            <li>Respond to your comments and questions</li>
                            <li>Personalize your learning experience</li>
                        </ul>
                        
                        <h3>3. Information Sharing</h3>
                        <p>We do not sell, trade, or otherwise transfer your personal information to third parties without your consent, except as described in this policy:</p>
                        <ul>
                            <li><strong>Service Providers:</strong> We may share information with trusted service providers who assist in operating our platform</li>
                            <li><strong>Legal Requirements:</strong> We may disclose information if required by law or to protect our rights</li>
                            <li><strong>Business Transfers:</strong> Information may be transferred if we are acquired or merged with another company</li>
                        </ul>
                        
                        <h3>4. Data Security</h3>
                        <p>We implement appropriate security measures to protect your personal information:</p>
                        <ul>
                            <li>SSL encryption for data transmission</li>
                            <li>Secure servers and databases</li>
                            <li>Regular security audits</li>
                            <li>Access controls and authentication</li>
                        </ul>
                        
                        <h3>5. Your Rights</h3>
                        <p>You have the right to:</p>
                        <ul>
                            <li>Access and update your personal information</li>
                            <li>Request deletion of your account</li>
                            <li>Opt-out of marketing communications</li>
                            <li>Request a copy of your data</li>
                        </ul>
                        
                        <h3>6. Cookies</h3>
                        <p>We use cookies to enhance your experience on our platform. You can control cookie settings through your browser preferences.</p>
                        
                        <h3>7. Children's Privacy</h3>
                        <p>Our service is not intended for children under 13. We do not knowingly collect personal information from children under 13.</p>
                        
                        <h3>8. Contact Us</h3>
                        <p>If you have questions about this Privacy Policy, please contact us at:</p>
                        <div class="alert alert-light">
                            <strong>Email:</strong> privacy@bitversity.com<br>
                            <strong>Address:</strong> Bitversity Privacy Team<br>
                            123 Learning Street, Education City, EC 12345
                        </div>
                        
                        <hr class="my-4">
                        <div class="text-center">
                            <a href="<?= BASE_PATH ?>/pages/contact.php" class="btn btn-primary">
                                <i class="fas fa-envelope me-2"></i>Contact Us
                            </a>
                            <a href="<?= BASE_PATH ?>/index.php" class="btn btn-outline-primary">
                                <i class="fas fa-home me-2"></i>Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>