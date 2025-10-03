<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

$pageTitle = 'Help Center';
$pageDescription = 'Find answers to frequently asked questions and get help with using Bitversity.';
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
    
    <div class="page-header" style="background-image: url('https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(102, 126, 234, 0.2); z-index: 1;"></div>
        <div class="container" style="position: relative; z-index: 2;">
            <div class="text-center text-white">
                <h1 class="display-4 mb-3" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                    <i class="fas fa-question-circle me-3"></i>Help Center
                </h1>
                <p class="lead" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                    Find answers and get the help you need
                </p>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Quick Links</h5>
                        <div class="list-group list-group-flush">
                            <a href="#getting-started" class="list-group-item list-group-item-action">Getting Started</a>
                            <a href="#account" class="list-group-item list-group-item-action">Account Management</a>
                            <a href="#learning" class="list-group-item list-group-item-action">Learning Resources</a>
                            <a href="#billing" class="list-group-item list-group-item-action">Billing & Payments</a>
                            <a href="#technical" class="list-group-item list-group-item-action">Technical Issues</a>
                        </div>
                        <hr>
                        <a href="<?= BASE_PATH ?>/pages/contact.php" class="btn btn-primary w-100">
                            <i class="fas fa-envelope me-2"></i>Contact Support
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <!-- Search Box -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search help articles...">
                            <button class="btn btn-primary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Getting Started -->
                <section id="getting-started" class="mb-5">
                    <h3><i class="fas fa-play-circle text-primary me-2"></i>Getting Started</h3>
                    <div class="accordion" id="gettingStartedAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                    How do I create an account?
                                </button>
                            </h2>
                            <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#gettingStartedAccordion">
                                <div class="accordion-body">
                                    <p>Creating an account is easy:</p>
                                    <ol>
                                        <li>Click the "Sign Up" button in the top navigation</li>
                                        <li>Fill in your name, email, and password</li>
                                        <li>Verify your email address</li>
                                        <li>Complete your profile setup</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                    What learning paths are available?
                                </button>
                            </h2>
                            <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#gettingStartedAccordion">
                                <div class="accordion-body">
                                    <p>We offer learning paths in various categories:</p>
                                    <ul>
                                        <li><strong>Programming:</strong> Python, JavaScript, Java, C++</li>
                                        <li><strong>Web Development:</strong> Frontend, Backend, Full Stack</li>
                                        <li><strong>Mobile Development:</strong> iOS, Android, React Native</li>
                                        <li><strong>AI & Machine Learning:</strong> Data Science, Deep Learning</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Account Management -->
                <section id="account" class="mb-5">
                    <h3><i class="fas fa-user-cog text-primary me-2"></i>Account Management</h3>
                    <div class="accordion" id="accountAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                    How do I reset my password?
                                </button>
                            </h2>
                            <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#accountAccordion">
                                <div class="accordion-body">
                                    <p>To reset your password:</p>
                                    <ol>
                                        <li>Go to the login page</li>
                                        <li>Click "Forgot Password?"</li>
                                        <li>Enter your email address</li>
                                        <li>Check your email for reset instructions</li>
                                        <li>Follow the link to create a new password</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                                    Can I change my email address?
                                </button>
                            </h2>
                            <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#accountAccordion">
                                <div class="accordion-body">
                                    <p>Yes, you can change your email address in your account settings. You'll need to verify the new email address before the change takes effect.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Learning Resources -->
                <section id="learning" class="mb-5">
                    <h3><i class="fas fa-graduation-cap text-primary me-2"></i>Learning Resources</h3>
                    <div class="accordion" id="learningAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5">
                                    How do I track my progress?
                                </button>
                            </h2>
                            <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#learningAccordion">
                                <div class="accordion-body">
                                    <p>Your progress is automatically tracked as you complete lessons and projects. You can view your progress on your dashboard, which shows:</p>
                                    <ul>
                                        <li>Completed courses and lessons</li>
                                        <li>Time spent learning</li>
                                        <li>Certificates earned</li>
                                        <li>Current learning streaks</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6">
                                    Can I download course materials?
                                </button>
                            </h2>
                            <div id="collapse6" class="accordion-collapse collapse" data-bs-parent="#learningAccordion">
                                <div class="accordion-body">
                                    <p>Yes, many course materials are available for download, including:</p>
                                    <ul>
                                        <li>PDF guides and documentation</li>
                                        <li>Code examples and projects</li>
                                        <li>Resource files and assets</li>
                                    </ul>
                                    <p>Look for the download button in each lesson or course section.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Billing & Payments -->
                <section id="billing" class="mb-5">
                    <h3><i class="fas fa-credit-card text-primary me-2"></i>Billing & Payments</h3>
                    <div class="accordion" id="billingAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse7">
                                    What payment methods do you accept?
                                </button>
                            </h2>
                            <div id="collapse7" class="accordion-collapse collapse" data-bs-parent="#billingAccordion">
                                <div class="accordion-body">
                                    <p>We accept the following payment methods:</p>
                                    <ul>
                                        <li>Credit Cards (Visa, MasterCard, American Express)</li>
                                        <li>Debit Cards</li>
                                        <li>PayPal</li>
                                        <li>Bank transfers (in select regions)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse8">
                                    How do refunds work?
                                </button>
                            </h2>
                            <div id="collapse8" class="accordion-collapse collapse" data-bs-parent="#billingAccordion">
                                <div class="accordion-body">
                                    <p>Our refund policy allows for refunds within 30 days of purchase, provided that:</p>
                                    <ul>
                                        <li>Less than 20% of the content has been accessed</li>
                                        <li>The request is made within the refund period</li>
                                        <li>The purchase wasn't made with a promotional discount</li>
                                    </ul>
                                    <p>Contact our support team to request a refund.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Technical Issues -->
                <section id="technical" class="mb-5">
                    <h3><i class="fas fa-tools text-primary me-2"></i>Technical Issues</h3>
                    <div class="accordion" id="technicalAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse9">
                                    Videos won't play properly
                                </button>
                            </h2>
                            <div id="collapse9" class="accordion-collapse collapse" data-bs-parent="#technicalAccordion">
                                <div class="accordion-body">
                                    <p>If videos aren't playing correctly, try these solutions:</p>
                                    <ol>
                                        <li>Refresh the page</li>
                                        <li>Clear your browser cache</li>
                                        <li>Try a different browser</li>
                                        <li>Check your internet connection</li>
                                        <li>Disable browser extensions temporarily</li>
                                    </ol>
                                    <p>If issues persist, contact our technical support team.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse10">
                                    System requirements
                                </button>
                            </h2>
                            <div id="collapse10" class="accordion-collapse collapse" data-bs-parent="#technicalAccordion">
                                <div class="accordion-body">
                                    <p>Bitversity works best with:</p>
                                    <ul>
                                        <li><strong>Browsers:</strong> Chrome 90+, Firefox 88+, Safari 14+, Edge 90+</li>
                                        <li><strong>Internet:</strong> Broadband connection (minimum 5 Mbps for video)</li>
                                        <li><strong>Device:</strong> Desktop, laptop, tablet, or smartphone</li>
                                        <li><strong>JavaScript:</strong> Must be enabled</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Contact Support -->
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h4>Still need help?</h4>
                        <p>Can't find what you're looking for? Our support team is here to help.</p>
                        <a href="<?= BASE_PATH ?>/pages/contact.php" class="btn btn-primary me-2">
                            <i class="fas fa-envelope me-2"></i>Contact Support
                        </a>
                        <a href="mailto:support@bitversity.com" class="btn btn-outline-primary">
                            <i class="fas fa-at me-2"></i>Email Us
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>