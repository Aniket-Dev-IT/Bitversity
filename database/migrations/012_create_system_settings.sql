-- System Settings and Email Templates Tables
-- Migration: 012_create_system_settings.sql

-- System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'text', 'number', 'boolean', 'json') DEFAULT 'string',
    category ENUM('general', 'email', 'payment', 'seo', 'social', 'features', 'maintenance') DEFAULT 'general',
    description TEXT,
    is_public BOOLEAN DEFAULT 0, -- Whether setting can be accessed publicly
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Email Templates Table
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL UNIQUE,
    template_name VARCHAR(200) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    variables TEXT, -- JSON array of available variables
    category ENUM('auth', 'orders', 'notifications', 'marketing', 'system') DEFAULT 'system',
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert Default System Settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, category, description, is_public) VALUES
-- General Settings
('site_name', 'Bitversity', 'string', 'general', 'Website name displayed in header and emails', 1),
('site_description', 'Premium educational content for developers and learners', 'text', 'general', 'Site description for meta tags and about pages', 1),
('site_logo', '', 'string', 'general', 'URL or path to site logo', 1),
('contact_email', 'admin@bitversity.com', 'string', 'general', 'Primary contact email address', 1),
('support_email', 'support@bitversity.com', 'string', 'general', 'Support email address', 1),
('timezone', 'UTC', 'string', 'general', 'Default timezone for the application', 0),
('date_format', 'Y-m-d', 'string', 'general', 'Default date format', 0),
('time_format', 'H:i:s', 'string', 'general', 'Default time format', 0),
('items_per_page', '12', 'number', 'general', 'Default items per page for listings', 0),
('max_upload_size', '10485760', 'number', 'general', 'Maximum file upload size in bytes (10MB)', 0),

-- Email Settings
('smtp_host', '', 'string', 'email', 'SMTP server hostname', 0),
('smtp_port', '587', 'number', 'email', 'SMTP server port', 0),
('smtp_username', '', 'string', 'email', 'SMTP username', 0),
('smtp_password', '', 'string', 'email', 'SMTP password (encrypted)', 0),
('smtp_encryption', 'tls', 'string', 'email', 'SMTP encryption method (tls/ssl)', 0),
('email_from_name', 'Bitversity', 'string', 'email', 'Default sender name for emails', 0),
('email_from_address', 'noreply@bitversity.com', 'string', 'email', 'Default sender email address', 0),

-- Payment Settings
('stripe_publishable_key', '', 'string', 'payment', 'Stripe publishable key', 0),
('stripe_secret_key', '', 'string', 'payment', 'Stripe secret key (encrypted)', 0),
('paypal_client_id', '', 'string', 'payment', 'PayPal client ID', 0),
('paypal_client_secret', '', 'string', 'payment', 'PayPal client secret (encrypted)', 0),
('payment_currency', 'USD', 'string', 'payment', 'Default payment currency', 1),
('tax_rate', '0', 'number', 'payment', 'Default tax rate percentage', 1),

-- SEO Settings
('meta_title', 'Bitversity - Premium Educational Content', 'string', 'seo', 'Default meta title', 1),
('meta_description', 'Discover premium books, projects, and games for developers and learners', 'text', 'seo', 'Default meta description', 1),
('meta_keywords', 'programming, development, education, books, projects, games', 'text', 'seo', 'Default meta keywords', 1),
('google_analytics_id', '', 'string', 'seo', 'Google Analytics tracking ID', 1),
('google_search_console', '', 'string', 'seo', 'Google Search Console verification code', 1),

-- Social Media Settings
('facebook_url', '', 'string', 'social', 'Facebook page URL', 1),
('twitter_url', '', 'string', 'social', 'Twitter profile URL', 1),
('linkedin_url', '', 'string', 'social', 'LinkedIn page URL', 1),
('github_url', '', 'string', 'social', 'GitHub organization URL', 1),
('youtube_url', '', 'string', 'social', 'YouTube channel URL', 1),

-- Feature Settings
('user_registration', '1', 'boolean', 'features', 'Allow new user registration', 0),
('email_verification', '1', 'boolean', 'features', 'Require email verification for new users', 0),
('social_login', '1', 'boolean', 'features', 'Enable social media login', 0),
('wishlist_enabled', '1', 'boolean', 'features', 'Enable wishlist functionality', 0),
('reviews_enabled', '1', 'boolean', 'features', 'Enable user reviews and ratings', 0),
('comments_enabled', '1', 'boolean', 'features', 'Enable comments on content', 0),
('download_limits', '0', 'boolean', 'features', 'Enable download limits for free content', 0),

-- Maintenance Settings
('maintenance_mode', '0', 'boolean', 'maintenance', 'Enable maintenance mode', 0),
('maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon.', 'text', 'maintenance', 'Message displayed during maintenance', 0),
('backup_frequency', 'weekly', 'string', 'maintenance', 'Automatic backup frequency', 0),
('log_retention_days', '30', 'number', 'maintenance', 'Number of days to keep log files', 0),
('cache_enabled', '1', 'boolean', 'maintenance', 'Enable application caching', 0),
('debug_mode', '0', 'boolean', 'maintenance', 'Enable debug mode (development only)', 0);

-- Insert Default Email Templates
INSERT IGNORE INTO email_templates (template_key, template_name, subject, body, variables, category) VALUES
-- Authentication Templates
('welcome_email', 'Welcome Email', 'Welcome to {{site_name}}!', 
'<h2>Welcome to {{site_name}}, {{user_name}}!</h2>
<p>Thank you for joining our community of learners and developers.</p>
<p>Your account has been successfully created. You can now:</p>
<ul>
    <li>Browse our extensive library of books, projects, and games</li>
    <li>Purchase premium content</li>
    <li>Track your learning progress</li>
    <li>Connect with other learners</li>
</ul>
<p><a href="{{login_url}}" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Get Started</a></p>
<p>If you have any questions, feel free to contact our support team.</p>
<p>Happy learning!<br>The {{site_name}} Team</p>',
'["site_name", "user_name", "user_email", "login_url", "support_email"]', 'auth'),

('email_verification', 'Email Verification', 'Verify Your Email Address', 
'<h2>Verify Your Email Address</h2>
<p>Hi {{user_name}},</p>
<p>Please click the link below to verify your email address:</p>
<p><a href="{{verification_url}}" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Verify Email</a></p>
<p>This link will expire in 24 hours.</p>
<p>If you did not create an account, please ignore this email.</p>',
'["user_name", "user_email", "verification_url", "site_name"]', 'auth'),

('password_reset', 'Password Reset', 'Reset Your Password',
'<h2>Password Reset Request</h2>
<p>Hi {{user_name}},</p>
<p>You requested to reset your password. Click the link below to set a new password:</p>
<p><a href="{{reset_url}}" style="background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Reset Password</a></p>
<p>This link will expire in 1 hour.</p>
<p>If you did not request this reset, please ignore this email.</p>',
'["user_name", "user_email", "reset_url", "site_name"]', 'auth'),

-- Order Templates
('order_confirmation', 'Order Confirmation', 'Order Confirmation - #{{order_number}}',
'<h2>Order Confirmation</h2>
<p>Hi {{customer_name}},</p>
<p>Thank you for your order! Here are the details:</p>
<div style="border: 1px solid #ddd; padding: 15px; margin: 15px 0;">
    <h3>Order #{{order_number}}</h3>
    <p><strong>Date:</strong> {{order_date}}</p>
    <p><strong>Total:</strong> {{order_total}}</p>
    <h4>Items Purchased:</h4>
    {{order_items}}
</div>
<p>You can access your purchased content in your <a href="{{account_url}}">account dashboard</a>.</p>
<p>Thank you for choosing {{site_name}}!</p>',
'["customer_name", "order_number", "order_date", "order_total", "order_items", "account_url", "site_name"]', 'orders'),

('payment_confirmation', 'Payment Received', 'Payment Received - #{{order_number}}',
'<h2>Payment Received</h2>
<p>Hi {{customer_name}},</p>
<p>We have successfully received your payment for order #{{order_number}}.</p>
<p><strong>Amount:</strong> {{payment_amount}}</p>
<p><strong>Payment Method:</strong> {{payment_method}}</p>
<p>Your content is now available in your account.</p>
<p><a href="{{download_url}}" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Access Content</a></p>',
'["customer_name", "order_number", "payment_amount", "payment_method", "download_url", "site_name"]', 'orders'),

-- Notification Templates
('new_review_notification', 'New Review Notification', 'New Review on {{content_title}}',
'<h2>New Review Received</h2>
<p>A new review has been posted on "{{content_title}}"</p>
<p><strong>Rating:</strong> {{rating}} stars</p>
<p><strong>Review:</strong></p>
<blockquote>{{review_content}}</blockquote>
<p><strong>Reviewer:</strong> {{reviewer_name}}</p>
<p><a href="{{content_url}}">View Content</a></p>',
'["content_title", "rating", "review_content", "reviewer_name", "content_url", "site_name"]', 'notifications'),

-- System Templates
('contact_form_submission', 'Contact Form Submission', 'New Contact Form Submission',
'<h2>New Contact Form Submission</h2>
<p><strong>Name:</strong> {{sender_name}}</p>
<p><strong>Email:</strong> {{sender_email}}</p>
<p><strong>Subject:</strong> {{subject}}</p>
<p><strong>Message:</strong></p>
<div style="border-left: 3px solid #007bff; padding-left: 15px; margin: 15px 0;">
    {{message}}
</div>
<p><strong>Submitted:</strong> {{submission_date}}</p>',
'["sender_name", "sender_email", "subject", "message", "submission_date"]', 'system');

-- Create indexes for better performance
CREATE INDEX idx_system_settings_category ON system_settings(category);
CREATE INDEX idx_system_settings_public ON system_settings(is_public);
CREATE INDEX idx_email_templates_category ON email_templates(category);
CREATE INDEX idx_email_templates_active ON email_templates(is_active);