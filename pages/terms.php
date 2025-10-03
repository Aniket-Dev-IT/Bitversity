<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

$pageTitle = 'Terms of Service';
$pageDescription = 'Read our terms and conditions for using the Bitversity platform.';
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
    
    <div class="page-header" style="background-image: url('https://images.unsplash.com/photo-1589829545856-d10d557cf95f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(102, 126, 234, 0.2); z-index: 1;"></div>
        <div class="container" style="position: relative; z-index: 2;">
            <div class="text-center text-white">
                <h1 class="display-4 mb-3" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                    <i class="fas fa-file-contract me-3"></i>Terms of Service
                </h1>
                <p class="lead" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                    The terms and conditions for using Bitversity
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
                        
                        <h3>1. Acceptance of Terms</h3>
                        <p>By accessing and using Bitversity, you accept and agree to be bound by the terms and provision of this agreement.</p>
                        
                        <h3>2. Use License</h3>
                        <p>Permission is granted to temporarily access Bitversity for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license you may not:</p>
                        <ul>
                            <li>Modify or copy the materials</li>
                            <li>Use the materials for any commercial purpose</li>
                            <li>Attempt to decompile or reverse engineer any software</li>
                            <li>Remove any copyright or other proprietary notations</li>
                        </ul>
                        
                        <h3>3. User Accounts</h3>
                        <p>When you create an account with us, you must provide information that is accurate, complete, and current at all times. You are responsible for:</p>
                        <ul>
                            <li>Safeguarding your password and account information</li>
                            <li>All activities that occur under your account</li>
                            <li>Notifying us immediately of any unauthorized use</li>
                        </ul>
                        
                        <h3>4. Content and Conduct</h3>
                        <p>You agree not to use Bitversity to:</p>
                        <ul>
                            <li>Upload or share inappropriate, offensive, or illegal content</li>
                            <li>Violate any laws or regulations</li>
                            <li>Infringe on intellectual property rights</li>
                            <li>Harass or harm other users</li>
                            <li>Distribute spam or malicious software</li>
                        </ul>
                        
                        <h3>5. Payments and Refunds</h3>
                        <p>Our payment and refund policies:</p>
                        <ul>
                            <li>All payments are processed securely through trusted payment providers</li>
                            <li>Refunds may be requested within 30 days of purchase</li>
                            <li>Digital content that has been accessed may not be eligible for refund</li>
                            <li>Subscription cancellations take effect at the end of the billing period</li>
                        </ul>
                        
                        <h3>6. Intellectual Property</h3>
                        <p>All content on Bitversity, including text, graphics, logos, images, and software, is the property of Bitversity or its licensors and is protected by copyright and other intellectual property laws.</p>
                        
                        <h3>7. Privacy Policy</h3>
                        <p>Your privacy is important to us. Please review our <a href="<?= BASE_PATH ?>/pages/privacy.php">Privacy Policy</a>, which also governs your use of the Service.</p>
                        
                        <h3>8. Disclaimers</h3>
                        <p>The information on this website is provided on an "as is" basis. To the fullest extent permitted by law, this Company:</p>
                        <ul>
                            <li>Excludes all representations and warranties relating to this website</li>
                            <li>Does not warrant that the website will be constantly available</li>
                            <li>Does not guarantee the accuracy of content</li>
                        </ul>
                        
                        <h3>9. Limitations of Liability</h3>
                        <p>In no event shall Bitversity or its suppliers be liable for any damages arising out of the use or inability to use the materials on the website.</p>
                        
                        <h3>10. Termination</h3>
                        <p>We may terminate or suspend your account and bar access to the Service immediately, without prior notice, for any reason whatsoever, including without limitation if you breach the Terms.</p>
                        
                        <h3>11. Changes to Terms</h3>
                        <p>We reserve the right to modify these terms at any time. We will notify users of significant changes via email or through the platform.</p>
                        
                        <h3>12. Governing Law</h3>
                        <p>These terms shall be governed and construed in accordance with the laws of [Your Jurisdiction], without regard to its conflict of law provisions.</p>
                        
                        <h3>13. Contact Information</h3>
                        <p>If you have any questions about these Terms of Service, please contact us at:</p>
                        <div class="alert alert-light">
                            <strong>Email:</strong> legal@bitversity.com<br>
                            <strong>Address:</strong> Bitversity Legal Team<br>
                            123 Learning Street, Education City, EC 12345
                        </div>
                        
                        <hr class="my-4">
                        <div class="text-center">
                            <a href="<?= BASE_PATH ?>/pages/contact.php" class="btn btn-primary">
                                <i class="fas fa-envelope me-2"></i>Contact Us
                            </a>
                            <a href="<?= BASE_PATH ?>/pages/privacy.php" class="btn btn-outline-primary">
                                <i class="fas fa-shield-alt me-2"></i>Privacy Policy
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