<?php
require_once 'includes/auth.php';
require_once 'includes/layout.php';

$pageTitle = 'System Settings';

// Get all system settings grouped by category
$settingsStmt = $db->prepare("SELECT * FROM system_settings ORDER BY category, setting_key");
$settingsStmt->execute();
$allSettings = $settingsStmt->fetchAll();

// Group settings by category
$settings = [];
foreach ($allSettings as $setting) {
    $settings[$setting['category']][] = $setting;
}

// Get email templates
$templatesStmt = $db->prepare("SELECT * FROM email_templates ORDER BY category, template_name");
$templatesStmt->execute();
$emailTemplates = $templatesStmt->fetchAll();

// Group templates by category
$templates = [];
foreach ($emailTemplates as $template) {
    $templates[$template['category']][] = $template;
}

// Handle success/error messages
$message = $_SESSION['message'] ?? null;
$messageType = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);

// Get system info
$systemInfo = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'database_version' => $db->query('SELECT VERSION()')->fetchColumn(),
    'max_upload_size' => ini_get('upload_max_filesize'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'disk_free_space' => disk_free_space('.'),
    'disk_total_space' => disk_total_space('.'),
];

// Helper function to format bytes
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

// Render admin header
renderAdminHeader('System Settings', 'Configure application settings and manage email templates');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div></div>
    <div>
        <button type="button" class="btn btn-success" onclick="saveAllSettings()">
            <i class="fas fa-save me-2"></i>Save All Settings
        </button>
    </div>
</div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo sanitize($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                <i class="fas fa-sliders-h me-2"></i>General
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button">
                <i class="fas fa-envelope me-2"></i>Email
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button">
                <i class="fas fa-credit-card me-2"></i>Payment
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="seo-tab" data-bs-toggle="tab" data-bs-target="#seo" type="button">
                <i class="fas fa-search me-2"></i>SEO
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="social-tab" data-bs-toggle="tab" data-bs-target="#social" type="button">
                <i class="fas fa-share-alt me-2"></i>Social
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="features-tab" data-bs-toggle="tab" data-bs-target="#features" type="button">
                <i class="fas fa-toggle-on me-2"></i>Features
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button">
                <i class="fas fa-file-alt me-2"></i>Email Templates
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button">
                <i class="fas fa-tools me-2"></i>Maintenance
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button">
                <i class="fas fa-server me-2"></i>System Info
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="settingsTabContent">
        
        <!-- General Settings -->
        <div class="tab-pane fade show active" id="general" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">General Settings</h5>
                </div>
                <div class="card-body">
                    <form id="generalSettingsForm">
                        <div class="row">
                            <?php if (isset($settings['general'])): ?>
                                <?php foreach ($settings['general'] as $setting): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" 
                                                   name="<?php echo $setting['setting_key']; ?>" 
                                                   value="1" 
                                                   <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label"><?php echo $setting['description']; ?></label>
                                        </div>
                                    <?php elseif ($setting['setting_type'] === 'text'): ?>
                                        <textarea class="form-control" 
                                                  name="<?php echo $setting['setting_key']; ?>" 
                                                  rows="3"><?php echo sanitize($setting['setting_value']); ?></textarea>
                                    <?php else: ?>
                                        <input type="<?php echo $setting['setting_type'] === 'number' ? 'number' : 'text'; ?>" 
                                               class="form-control" 
                                               name="<?php echo $setting['setting_key']; ?>" 
                                               value="<?php echo sanitize($setting['setting_value']); ?>">
                                    <?php endif; ?>
                                    <?php if ($setting['description']): ?>
                                        <small class="text-muted"><?php echo $setting['description']; ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Email Settings -->
        <div class="tab-pane fade" id="email" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Email Configuration</h5>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="testEmailSettings()">
                        <i class="fas fa-paper-plane me-1"></i>Test Email
                    </button>
                </div>
                <div class="card-body">
                    <form id="emailSettingsForm">
                        <div class="row">
                            <?php if (isset($settings['email'])): ?>
                                <?php foreach ($settings['email'] as $setting): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <?php if (strpos($setting['setting_key'], 'password') !== false): ?>
                                        <input type="password" class="form-control" 
                                               name="<?php echo $setting['setting_key']; ?>" 
                                               value="<?php echo $setting['setting_value'] ? '••••••••' : ''; ?>"
                                               placeholder="Enter new password to change">
                                    <?php elseif ($setting['setting_type'] === 'number'): ?>
                                        <input type="number" class="form-control" 
                                               name="<?php echo $setting['setting_key']; ?>" 
                                               value="<?php echo sanitize($setting['setting_value']); ?>">
                                    <?php else: ?>
                                        <input type="text" class="form-control" 
                                               name="<?php echo $setting['setting_key']; ?>" 
                                               value="<?php echo sanitize($setting['setting_value']); ?>">
                                    <?php endif; ?>
                                    <?php if ($setting['description']): ?>
                                        <small class="text-muted"><?php echo $setting['description']; ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Payment Settings -->
        <div class="tab-pane fade" id="payment" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Payment Configuration</h5>
                </div>
                <div class="card-body">
                    <form id="paymentSettingsForm">
                        <div class="row">
                            <?php if (isset($settings['payment'])): ?>
                                <?php foreach ($settings['payment'] as $setting): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <?php if (strpos($setting['setting_key'], 'secret') !== false || strpos($setting['setting_key'], 'key') !== false): ?>
                                        <input type="password" class="form-control" 
                                               name="<?php echo $setting['setting_key']; ?>" 
                                               value="<?php echo $setting['setting_value'] ? '••••••••••••••••' : ''; ?>"
                                               placeholder="Enter new key to change">
                                    <?php elseif ($setting['setting_type'] === 'number'): ?>
                                        <input type="number" class="form-control" 
                                               name="<?php echo $setting['setting_key']; ?>" 
                                               value="<?php echo sanitize($setting['setting_value']); ?>">
                                    <?php else: ?>
                                        <input type="text" class="form-control" 
                                               name="<?php echo $setting['setting_key']; ?>" 
                                               value="<?php echo sanitize($setting['setting_value']); ?>">
                                    <?php endif; ?>
                                    <?php if ($setting['description']): ?>
                                        <small class="text-muted"><?php echo $setting['description']; ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- SEO Settings -->
        <div class="tab-pane fade" id="seo" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">SEO Configuration</h5>
                </div>
                <div class="card-body">
                    <form id="seoSettingsForm">
                        <div class="row">
                            <?php if (isset($settings['seo'])): ?>
                                <?php foreach ($settings['seo'] as $setting): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <?php if ($setting['setting_type'] === 'text'): ?>
                                        <textarea class="form-control" 
                                                  name="<?php echo $setting['setting_key']; ?>" 
                                                  rows="3"><?php echo sanitize($setting['setting_value']); ?></textarea>
                                    <?php else: ?>
                                        <input type="text" class="form-control" 
                                               name="<?php echo $setting['setting_key']; ?>" 
                                               value="<?php echo sanitize($setting['setting_value']); ?>">
                                    <?php endif; ?>
                                    <?php if ($setting['description']): ?>
                                        <small class="text-muted"><?php echo $setting['description']; ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Social Media Settings -->
        <div class="tab-pane fade" id="social" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Social Media Links</h5>
                </div>
                <div class="card-body">
                    <form id="socialSettingsForm">
                        <div class="row">
                            <?php if (isset($settings['social'])): ?>
                                <?php foreach ($settings['social'] as $setting): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fab fa-<?php echo str_replace('_url', '', $setting['setting_key']); ?> me-2"></i>
                                        <?php echo ucwords(str_replace(['_', 'url'], [' ', ' URL'], $setting['setting_key'])); ?>
                                    </label>
                                    <input type="url" class="form-control" 
                                           name="<?php echo $setting['setting_key']; ?>" 
                                           value="<?php echo sanitize($setting['setting_value']); ?>"
                                           placeholder="https://...">
                                    <?php if ($setting['description']): ?>
                                        <small class="text-muted"><?php echo $setting['description']; ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Feature Settings -->
        <div class="tab-pane fade" id="features" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Feature Toggles</h5>
                </div>
                <div class="card-body">
                    <form id="featuresSettingsForm">
                        <div class="row">
                            <?php if (isset($settings['features'])): ?>
                                <?php foreach ($settings['features'] as $setting): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" 
                                               name="<?php echo $setting['setting_key']; ?>" 
                                               value="1" 
                                               <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label">
                                            <strong><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></strong>
                                            <br><small class="text-muted"><?php echo $setting['description']; ?></small>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Email Templates -->
        <div class="tab-pane fade" id="templates" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Email Templates</h5>
                    <button type="button" class="btn btn-primary btn-sm" onclick="editTemplate(0)">
                        <i class="fas fa-plus me-1"></i>New Template
                    </button>
                </div>
                <div class="card-body">
                    <?php foreach ($templates as $category => $categoryTemplates): ?>
                    <h6 class="text-uppercase text-muted mb-3"><?php echo ucfirst($category); ?> Templates</h6>
                    <div class="row mb-4">
                        <?php foreach ($categoryTemplates as $template): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0"><?php echo sanitize($template['template_name']); ?></h6>
                                        <span class="badge bg-<?php echo $template['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                    <p class="card-text small text-muted mb-3"><?php echo sanitize($template['subject']); ?></p>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                onclick="editTemplate(<?php echo $template['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-outline-info btn-sm" 
                                                onclick="previewTemplate(<?php echo $template['id']; ?>)">
                                            <i class="fas fa-eye"></i> Preview
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Maintenance -->
        <div class="tab-pane fade" id="maintenance" role="tabpanel">
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Maintenance Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="maintenanceSettingsForm">
                                <?php if (isset($settings['maintenance'])): ?>
                                    <?php foreach ($settings['maintenance'] as $setting): ?>
                                    <div class="mb-3">
                                        <label class="form-label"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                        <?php if ($setting['setting_type'] === 'boolean'): ?>
                                            <div class="form-check form-switch">
                                                <input type="checkbox" class="form-check-input" 
                                                       name="<?php echo $setting['setting_key']; ?>" 
                                                       value="1" 
                                                       <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label"><?php echo $setting['description']; ?></label>
                                            </div>
                                        <?php elseif ($setting['setting_type'] === 'text'): ?>
                                            <textarea class="form-control" 
                                                      name="<?php echo $setting['setting_key']; ?>" 
                                                      rows="3"><?php echo sanitize($setting['setting_value']); ?></textarea>
                                        <?php elseif ($setting['setting_type'] === 'number'): ?>
                                            <input type="number" class="form-control" 
                                                   name="<?php echo $setting['setting_key']; ?>" 
                                                   value="<?php echo sanitize($setting['setting_value']); ?>">
                                        <?php else: ?>
                                            <input type="text" class="form-control" 
                                                   name="<?php echo $setting['setting_key']; ?>" 
                                                   value="<?php echo sanitize($setting['setting_value']); ?>">
                                        <?php endif; ?>
                                        <?php if ($setting['description']): ?>
                                            <small class="text-muted"><?php echo $setting['description']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Maintenance Tools</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary" onclick="clearCache()">
                                    <i class="fas fa-broom me-2"></i>Clear Cache
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="optimizeDatabase()">
                                    <i class="fas fa-database me-2"></i>Optimize Database
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="createBackup()">
                                    <i class="fas fa-download me-2"></i>Create Backup
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="viewLogs()">
                                    <i class="fas fa-file-alt me-2"></i>View System Logs
                                </button>
                                <button type="button" class="btn btn-outline-danger" onclick="cleanupFiles()">
                                    <i class="fas fa-trash me-2"></i>Cleanup Old Files
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="tab-pane fade" id="system" role="tabpanel">
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">System Information</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>PHP Version:</strong></td>
                                    <td><?php echo $systemInfo['php_version']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Server Software:</strong></td>
                                    <td><?php echo $systemInfo['server_software']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Database Version:</strong></td>
                                    <td><?php echo $systemInfo['database_version']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Max Upload Size:</strong></td>
                                    <td><?php echo $systemInfo['max_upload_size']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Max Execution Time:</strong></td>
                                    <td><?php echo $systemInfo['max_execution_time']; ?>s</td>
                                </tr>
                                <tr>
                                    <td><strong>Memory Limit:</strong></td>
                                    <td><?php echo $systemInfo['memory_limit']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Disk Free Space:</strong></td>
                                    <td><?php echo formatBytes($systemInfo['disk_free_space']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Disk Total Space:</strong></td>
                                    <td><?php echo formatBytes($systemInfo['disk_total_space']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">System Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>Disk Usage</span>
                                    <span><?php echo round(($systemInfo['disk_total_space'] - $systemInfo['disk_free_space']) / $systemInfo['disk_total_space'] * 100, 1); ?>%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo round(($systemInfo['disk_total_space'] - $systemInfo['disk_free_space']) / $systemInfo['disk_total_space'] * 100, 1); ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6>PHP Extensions</h6>
                                <div class="row">
                                    <?php 
                                    $requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'gd', 'fileinfo', 'json', 'mbstring'];
                                    foreach ($requiredExtensions as $ext):
                                    ?>
                                    <div class="col-6">
                                        <span class="badge bg-<?php echo extension_loaded($ext) ? 'success' : 'danger'; ?>">
                                            <?php echo $ext; ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6>File Permissions</h6>
                                <?php
                                $directories = ['uploads', 'logs', 'cache'];
                                foreach ($directories as $dir):
                                    $path = __DIR__ . '/../' . $dir;
                                    $writable = is_writable($path);
                                ?>
                                <div class="d-flex justify-content-between">
                                    <span><?php echo $dir; ?>/</span>
                                    <span class="badge bg-<?php echo $writable ? 'success' : 'danger'; ?>">
                                        <?php echo $writable ? 'Writable' : 'Not Writable'; ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Email Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="templateForm">
                    <input type="hidden" id="template_id" name="template_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Template Name</label>
                            <input type="text" class="form-control" id="template_name" name="template_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" id="template_category" name="category" required>
                                <option value="auth">Authentication</option>
                                <option value="orders">Orders</option>
                                <option value="notifications">Notifications</option>
                                <option value="marketing">Marketing</option>
                                <option value="system">System</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Line</label>
                        <input type="text" class="form-control" id="template_subject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Template Body</label>
                        <textarea class="form-control" id="template_body" name="body" rows="15" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Available Variables</label>
                        <input type="text" class="form-control" id="template_variables" name="variables" 
                               placeholder='["variable1", "variable2", "variable3"]'>
                        <small class="text-muted">JSON array of variable names available in this template</small>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="template_active" name="is_active" value="1" checked>
                        <label class="form-check-label">Active</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTemplate()">Save Template</button>
            </div>
        </div>
    </div>
</div>

<script>
// Save all settings
function saveAllSettings() {
    const forms = ['generalSettingsForm', 'emailSettingsForm', 'paymentSettingsForm', 
                   'seoSettingsForm', 'socialSettingsForm', 'featuresSettingsForm', 'maintenanceSettingsForm'];
    
    let allData = {};
    
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                allData[key] = value;
            }
        }
    });
    
    // Handle checkboxes that weren't checked
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                if (!checkbox.checked && checkbox.name) {
                    allData[checkbox.name] = '0';
                }
            });
        }
    });
    
    fetch('<?php echo BASE_PATH; ?>/api/admin/save-settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?php echo generateCsrfToken(); ?>'
        },
        body: JSON.stringify(allData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Settings saved successfully!');
        } else {
            showAlert('error', data.message || 'Failed to save settings');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'An error occurred while saving settings');
    });
}

// Template management functions
function editTemplate(templateId) {
    if (templateId === 0) {
        // New template
        document.getElementById('templateForm').reset();
        document.getElementById('template_id').value = '';
        new bootstrap.Modal(document.getElementById('templateModal')).show();
        return;
    }
    
    fetch(`<?php echo BASE_PATH; ?>/api/admin/get-template.php?id=${templateId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const template = data.template;
            document.getElementById('template_id').value = template.id;
            document.getElementById('template_name').value = template.template_name;
            document.getElementById('template_category').value = template.category;
            document.getElementById('template_subject').value = template.subject;
            document.getElementById('template_body').value = template.body;
            document.getElementById('template_variables').value = template.variables || '';
            document.getElementById('template_active').checked = template.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('templateModal')).show();
        }
    });
}

function saveTemplate() {
    const form = document.getElementById('templateForm');
    const formData = new FormData(form);
    
    fetch('<?php echo BASE_PATH; ?>/api/admin/save-template.php', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': '<?php echo generateCsrfToken(); ?>'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Template saved successfully!');
            bootstrap.Modal.getInstance(document.getElementById('templateModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('error', data.message || 'Failed to save template');
        }
    });
}

function showAlert(type, message) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}
</script>

<?php renderAdminFooter(); ?>
