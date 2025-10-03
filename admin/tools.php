<?php
/**
 * Advanced Admin Tools Interface
 * 
 * Comprehensive toolset for admin productivity including:
 * - Template library management
 * - Workflow automation rules
 * - Task management system
 * - Admin collaboration tools
 * - Performance analytics
 */

require_once 'includes/auth.php';

$current_admin = getCurrentAdmin();
$flash_message = getAdminFlashMessage();

// Get current tab/section
$active_tab = $_GET['tab'] ?? 'templates';
$valid_tabs = ['templates', 'workflows', 'tasks', 'collaboration', 'analytics', 'settings'];

if (!in_array($active_tab, $valid_tabs)) {
    $active_tab = 'templates';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        setAdminFlashMessage('Invalid security token. Please try again.', 'error');
        header('Location: tools.php?tab=' . $active_tab);
        exit();
    }
    
    try {
        $db->beginTransaction();
        
        switch ($action) {
            case 'create_template':
                $name = trim($_POST['template_name'] ?? '');
                $subject = trim($_POST['template_subject'] ?? '');
                $content = trim($_POST['template_content'] ?? '');
                $category = $_POST['template_category'] ?? 'general';
                $is_global = isset($_POST['is_global']) ? 1 : 0;
                $variables = $_POST['template_variables'] ?? [];
                
                if (empty($name) || empty($content)) {
                    throw new Exception('Template name and content are required.');
                }
                
                $stmt = $db->prepare("INSERT INTO admin_message_templates (admin_id, name, subject, content, category, variables, is_global, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$_SESSION['user_id'], $name, $subject, $content, $category, json_encode($variables), $is_global, $_SESSION['user_id']]);
                
                logAdminActivity('template_created', "Created message template: $name", ['template_id' => $db->lastInsertId()]);
                setAdminFlashMessage('Template created successfully!', 'success');
                break;
                
            case 'update_template':
                $template_id = intval($_POST['template_id'] ?? 0);
                $name = trim($_POST['template_name'] ?? '');
                $subject = trim($_POST['template_subject'] ?? '');
                $content = trim($_POST['template_content'] ?? '');
                $category = $_POST['template_category'] ?? 'general';
                $is_global = isset($_POST['is_global']) ? 1 : 0;
                $variables = $_POST['template_variables'] ?? [];
                
                $stmt = $db->prepare("UPDATE admin_message_templates SET name = ?, subject = ?, content = ?, category = ?, variables = ?, is_global = ?, updated_at = NOW() WHERE id = ? AND (admin_id = ? OR is_global = 1)");
                $stmt->execute([$name, $subject, $content, $category, json_encode($variables), $is_global, $template_id, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    logAdminActivity('template_updated', "Updated message template: $name", ['template_id' => $template_id]);
                    setAdminFlashMessage('Template updated successfully!', 'success');
                } else {
                    throw new Exception('Template not found or permission denied.');
                }
                break;
                
            case 'delete_template':
                $template_id = intval($_POST['template_id'] ?? 0);
                
                $stmt = $db->prepare("DELETE FROM admin_message_templates WHERE id = ? AND (admin_id = ? OR is_global = 1)");
                $stmt->execute([$template_id, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    logAdminActivity('template_deleted', "Deleted message template", ['template_id' => $template_id]);
                    setAdminFlashMessage('Template deleted successfully!', 'success');
                } else {
                    throw new Exception('Template not found or permission denied.');
                }
                break;
                
            case 'create_workflow':
                $name = trim($_POST['workflow_name'] ?? '');
                $description = trim($_POST['workflow_description'] ?? '');
                $trigger_event = $_POST['trigger_event'] ?? '';
                $conditions = $_POST['conditions'] ?? '{}';
                $actions = $_POST['actions'] ?? '{}';
                
                if (empty($name) || empty($trigger_event)) {
                    throw new Exception('Workflow name and trigger event are required.');
                }
                
                $stmt = $db->prepare("INSERT INTO admin_workflow_rules (name, description, trigger_event, conditions, actions, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $description, $trigger_event, $conditions, $actions, $_SESSION['user_id']]);
                
                logAdminActivity('workflow_created', "Created workflow rule: $name", ['workflow_id' => $db->lastInsertId()]);
                setAdminFlashMessage('Workflow rule created successfully!', 'success');
                break;
                
            case 'create_task':
                $title = trim($_POST['task_title'] ?? '');
                $description = trim($_POST['task_description'] ?? '');
                $assign_to = intval($_POST['assign_to'] ?? $_SESSION['user_id']);
                $order_id = intval($_POST['order_id'] ?? 0);
                $task_type = $_POST['task_type'] ?? 'other';
                $priority = $_POST['task_priority'] ?? 'medium';
                $due_date = $_POST['due_date'] ?? null;
                
                if (empty($title)) {
                    throw new Exception('Task title is required.');
                }
                
                $stmt = $db->prepare("INSERT INTO admin_tasks (admin_id, assigned_by, order_id, title, description, task_type, priority, due_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$assign_to, $_SESSION['user_id'], $order_id ?: null, $title, $description, $task_type, $priority, $due_date]);
                
                // Create notification for assigned admin
                if ($assign_to != $_SESSION['user_id']) {
                    createAdminNotification($assign_to, 'task_reminder', "New task assigned: $title", [
                        'task_id' => $db->lastInsertId(),
                        'assigned_by' => $_SESSION['user_id']
                    ]);
                }
                
                logAdminActivity('task_created', "Created task: $title", ['task_id' => $db->lastInsertId()]);
                setAdminFlashMessage('Task created successfully!', 'success');
                break;
                
            case 'update_task_status':
                $task_id = intval($_POST['task_id'] ?? 0);
                $status = $_POST['task_status'] ?? '';
                $actual_hours = floatval($_POST['actual_hours'] ?? 0);
                
                $update_data = ['status' => $status];
                if ($status === 'completed') {
                    $update_data['completed_at'] = 'NOW()';
                    if ($actual_hours > 0) {
                        $update_data['actual_hours'] = $actual_hours;
                    }
                }
                
                $set_clause = implode(', ', array_map(fn($k) => "$k = ?", array_keys($update_data)));
                $values = array_values($update_data);
                $values[] = $task_id;
                $values[] = $_SESSION['user_id'];
                
                $stmt = $db->prepare("UPDATE admin_tasks SET $set_clause, updated_at = NOW() WHERE id = ? AND admin_id = ?");
                $stmt->execute($values);
                
                if ($stmt->rowCount() > 0) {
                    logAdminActivity('task_updated', "Updated task status to: $status", ['task_id' => $task_id]);
                    setAdminFlashMessage('Task updated successfully!', 'success');
                }
                break;
                
            case 'add_note':
                $order_id = intval($_POST['order_id'] ?? 0);
                $note = trim($_POST['note_content'] ?? '');
                $note_type = $_POST['note_type'] ?? 'general';
                $priority = $_POST['note_priority'] ?? 'medium';
                $is_private = isset($_POST['is_private']) ? 1 : 0;
                $mentioned_admins = $_POST['mentioned_admins'] ?? [];
                
                if (empty($note) || $order_id <= 0) {
                    throw new Exception('Note content and order ID are required.');
                }
                
                $stmt = $db->prepare("INSERT INTO admin_internal_notes (order_id, admin_id, note, note_type, priority, is_private, mentioned_admins, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$order_id, $_SESSION['user_id'], $note, $note_type, $priority, $is_private, json_encode($mentioned_admins)]);
                
                $note_id = $db->lastInsertId();
                
                // Create mentions
                foreach ($mentioned_admins as $admin_id) {
                    if ($admin_id != $_SESSION['user_id']) {
                        $stmt = $db->prepare("INSERT INTO admin_mentions (mentioned_admin_id, mentioning_admin_id, context_type, context_id, order_id, created_at) VALUES (?, ?, 'note', ?, ?, NOW())");
                        $stmt->execute([$admin_id, $_SESSION['user_id'], $note_id, $order_id]);
                        
                        createAdminNotification($admin_id, 'mention', "You were mentioned in a note", [
                            'note_id' => $note_id,
                            'order_id' => $order_id,
                            'mentioning_admin' => $_SESSION['user_id']
                        ]);
                    }
                }
                
                logAdminActivity('note_added', "Added internal note to order #$order_id", ['note_id' => $note_id, 'order_id' => $order_id]);
                setAdminFlashMessage('Note added successfully!', 'success');
                break;
                
            case 'create_quick_action':
                $name = trim($_POST['action_name'] ?? '');
                $description = trim($_POST['action_description'] ?? '');
                $action_type = $_POST['action_type'] ?? '';
                $action_config = $_POST['action_config'] ?? '{}';
                $icon = $_POST['action_icon'] ?? 'fa-cog';
                $color = $_POST['action_color'] ?? '#6c757d';
                
                if (empty($name) || empty($action_type)) {
                    throw new Exception('Action name and type are required.');
                }
                
                $stmt = $db->prepare("INSERT INTO admin_quick_actions (admin_id, name, description, action_type, action_config, icon, color, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$_SESSION['user_id'], $name, $description, $action_type, $action_config, $icon, $color]);
                
                logAdminActivity('quick_action_created', "Created quick action: $name", ['action_id' => $db->lastInsertId()]);
                setAdminFlashMessage('Quick action created successfully!', 'success');
                break;
                
            case 'update_preferences':
                $preferences = $_POST['preferences'] ?? [];
                
                foreach ($preferences as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO admin_preferences (admin_id, preference_key, preference_value, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value), updated_at = NOW()");
                    $stmt->execute([$_SESSION['user_id'], $key, json_encode($value)]);
                }
                
                logAdminActivity('preferences_updated', "Updated admin preferences", ['keys' => array_keys($preferences)]);
                setAdminFlashMessage('Preferences updated successfully!', 'success');
                break;
        }
        
        $db->commit();
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Admin tools error: " . $e->getMessage());
        setAdminFlashMessage('Error: ' . $e->getMessage(), 'error');
    }
    
    header('Location: tools.php?tab=' . $active_tab);
    exit();
}

// Fetch data based on active tab
$templates = [];
$workflows = [];
$tasks = [];
$notes = [];
$quick_actions = [];
$preferences = [];
$analytics = [];

try {
    switch ($active_tab) {
        case 'templates':
            $stmt = $db->prepare("SELECT amt.*, u.full_name as creator_name FROM admin_message_templates amt LEFT JOIN users u ON amt.created_by = u.id WHERE amt.admin_id = ? OR amt.is_global = 1 ORDER BY amt.is_global DESC, amt.usage_count DESC, amt.created_at DESC");
            $stmt->execute([$_SESSION['user_id']]);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'workflows':
            $stmt = $db->prepare("SELECT awr.*, u.full_name as creator_name FROM admin_workflow_rules awr LEFT JOIN users u ON awr.created_by = u.id ORDER BY awr.is_active DESC, awr.created_at DESC");
            $stmt->execute();
            $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'tasks':
            $stmt = $db->prepare("SELECT at.*, u_assigned.full_name as assigned_to_name, u_by.full_name as assigned_by_name, cor.title as order_title FROM admin_tasks at LEFT JOIN users u_assigned ON at.admin_id = u_assigned.id LEFT JOIN users u_by ON at.assigned_by = u_by.id LEFT JOIN custom_order_requests cor ON at.order_id = cor.id WHERE at.admin_id = ? OR at.assigned_by = ? ORDER BY FIELD(at.status, 'pending', 'in_progress', 'completed', 'cancelled'), at.due_date ASC, at.created_at DESC");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'collaboration':
            // Get recent notes and mentions
            $stmt = $db->prepare("SELECT ain.*, u.full_name as admin_name, cor.title as order_title FROM admin_internal_notes ain LEFT JOIN users u ON ain.admin_id = u.id LEFT JOIN custom_order_requests cor ON ain.order_id = cor.id WHERE ain.is_private = 0 OR ain.admin_id = ? ORDER BY ain.created_at DESC LIMIT 20");
            $stmt->execute([$_SESSION['user_id']]);
            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'analytics':
            // Get performance metrics
            $stmt = $db->prepare("SELECT * FROM admin_performance_metrics WHERE admin_id = ? ORDER BY metric_date DESC LIMIT 30");
            $stmt->execute([$_SESSION['user_id']]);
            $analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'settings':
            $stmt = $db->prepare("SELECT * FROM admin_preferences WHERE admin_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("SELECT * FROM admin_quick_actions WHERE admin_id = ? ORDER BY sort_order, usage_count DESC");
            $stmt->execute([$_SESSION['user_id']]);
            $quick_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (PDOException $e) {
    error_log("Admin tools data fetch error: " . $e->getMessage());
}

// Helper functions
function createAdminNotification($admin_id, $type, $message, $metadata = []) {
    global $db;
    $stmt = $db->prepare("INSERT INTO admin_notifications (admin_id, type, title, message, metadata, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$admin_id, $type, $type, $message, json_encode($metadata)]);
}

require_once 'includes/layout.php';
renderAdminHeader('Admin Tools', 'Advanced productivity tools and automation');
?>

<link href="https://cdn.jsdelivr.net/npm/codemirror@5.65.12/lib/codemirror.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.12/lib/codemirror.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.12/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.12/mode/javascript/javascript.min.js"></script>

<style>
    :root {
        --tools-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --tools-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --tools-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        --tools-warning: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }

    .admin-tools-container {
        background: linear-gradient(120deg, #f6f9fc 0%, #ffffff 100%);
        min-height: 100vh;
    }

    .tools-header {
        background: var(--tools-primary);
        color: white;
        padding: 2rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .tools-header::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
        transform: translate(50%, -50%);
    }

    .nav-tabs-custom {
        border: none;
        margin-bottom: 2rem;
    }

    .nav-tabs-custom .nav-item {
        margin-bottom: -1px;
    }

    .nav-tabs-custom .nav-link {
        border: none;
        background: transparent;
        color: #6c757d;
        padding: 1rem 1.5rem;
        border-radius: 12px 12px 0 0;
        transition: all 0.3s ease;
        position: relative;
    }

    .nav-tabs-custom .nav-link:hover {
        color: #495057;
        background: rgba(102, 126, 234, 0.1);
    }

    .nav-tabs-custom .nav-link.active {
        color: #667eea;
        background: white;
        box-shadow: 0 -2px 12px rgba(0,0,0,0.1);
        font-weight: 600;
    }

    .nav-tabs-custom .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--tools-primary);
        border-radius: 3px 3px 0 0;
    }

    .tab-content-custom {
        background: white;
        border-radius: 0 12px 12px 12px;
        padding: 2rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        min-height: 500px;
    }

    .template-card, .workflow-card, .task-card, .note-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .template-card:hover, .workflow-card:hover, .task-card:hover, .note-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .template-card .card-header {
        background: var(--tools-primary);
        color: white;
        border: none;
        padding: 1.25rem;
    }

    .workflow-card .card-header {
        background: var(--tools-secondary);
        color: white;
        border: none;
        padding: 1.25rem;
    }

    .task-card .card-header {
        background: var(--tools-success);
        color: white;
        border: none;
        padding: 1.25rem;
    }

    .note-card .card-header {
        background: var(--tools-warning);
        color: white;
        border: none;
        padding: 1.25rem;
    }

    .card-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .action-btn {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
        color: white;
        border-radius: 6px;
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
    }

    .action-btn:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
        color: white;
        transform: translateY(-1px);
    }

    .usage-badge {
        background: rgba(255,255,255,0.2);
        color: white;
        border-radius: 12px;
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .template-preview {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1rem;
        margin-top: 0.5rem;
        border-left: 4px solid #667eea;
        font-family: monospace;
        font-size: 0.875rem;
        white-space: pre-wrap;
        max-height: 200px;
        overflow-y: auto;
    }

    .workflow-status {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .workflow-status.active {
        background: #d4edda;
        color: #155724;
    }

    .workflow-status.inactive {
        background: #f8d7da;
        color: #721c24;
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: currentColor;
    }

    .task-priority {
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .priority-low { background: #d1ecf1; color: #0c5460; }
    .priority-medium { background: #fff3cd; color: #856404; }
    .priority-high { background: #f8d7da; color: #721c24; }
    .priority-urgent { background: #f5c6cb; color: #721c24; animation: pulse-urgent 2s infinite; }

    @keyframes pulse-urgent {
        0%, 100% { background-color: #f5c6cb; }
        50% { background-color: #f8d7da; }
    }

    .task-status {
        padding: 0.375rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .status-pending { background: #e2e3e5; color: #6c757d; }
    .status-in_progress { background: #cce5ff; color: #004085; }
    .status-completed { background: #d4edda; color: #155724; }
    .status-cancelled { background: #f8d7da; color: #721c24; }

    .note-type-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-right: 0.5rem;
    }

    .type-general { background: #e2e3e5; color: #6c757d; }
    .type-technical { background: #cce5ff; color: #004085; }
    .type-priority { background: #f8d7da; color: #721c24; }
    .type-warning { background: #fff3cd; color: #856404; }
    .type-follow_up { background: #d1ecf1; color: #0c5460; }

    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .analytics-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .analytics-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--tools-primary);
    }

    .analytics-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 0.5rem;
    }

    .analytics-label {
        color: #6b7280;
        font-size: 0.9rem;
        margin-bottom: 1rem;
    }

    .analytics-change {
        font-size: 0.8rem;
        font-weight: 600;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
    }

    .change-positive { background: #d4edda; color: #155724; }
    .change-negative { background: #f8d7da; color: #721c24; }
    .change-neutral { background: #e2e3e5; color: #6c757d; }

    .quick-action-builder {
        border: 2px dashed #dee2e6;
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .quick-action-builder:hover {
        border-color: #667eea;
        background: rgba(102, 126, 234, 0.05);
    }

    .preferences-section {
        border-bottom: 1px solid #dee2e6;
        padding: 1.5rem 0;
        margin-bottom: 1.5rem;
    }

    .preferences-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }

    .floating-fab {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        z-index: 1000;
    }

    .fab-btn {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--tools-primary);
        border: none;
        color: white;
        font-size: 1.5rem;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        transition: all 0.3s ease;
    }

    .fab-btn:hover {
        transform: translateY(-3px) scale(1.1);
        box-shadow: 0 8px 30px rgba(102, 126, 234, 0.4);
    }

    .modal-enhanced .modal-content {
        border: none;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    }

    .modal-enhanced .modal-header {
        background: var(--tools-primary);
        color: white;
        border-radius: 16px 16px 0 0;
        padding: 1.5rem;
    }

    .code-editor {
        border: 1px solid #dee2e6;
        border-radius: 8px;
    }

    .mention-input {
        position: relative;
    }

    .mention-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        z-index: 1000;
        max-height: 200px;
        overflow-y: auto;
    }

    .mention-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid #f8f9fa;
    }

    .mention-item:hover {
        background: #f8f9fa;
    }

    .mention-item:last-child {
        border-bottom: none;
    }
</style>

<div class="admin-tools-container">
    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_message['type'] === 'error' ? 'danger' : $flash_message['type'] ?> alert-dismissible fade show">
            <?= htmlspecialchars($flash_message['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="tools-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-6 mb-2">
                    <i class="fas fa-tools me-3"></i>Advanced Admin Tools
                </h1>
                <p class="mb-0 opacity-75">Streamline your workflow with powerful automation and collaboration tools</p>
            </div>
            <div class="col-md-4 text-end">
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn btn-light btn-sm" onclick="importTools()">
                        <i class="fas fa-upload me-1"></i>Import
                    </button>
                    <button class="btn btn-light btn-sm" onclick="exportTools()">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                    <button class="btn btn-warning btn-sm" onclick="showTutorial()">
                        <i class="fas fa-question-circle me-1"></i>Help
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab === 'templates' ? 'active' : '' ?>" href="?tab=templates" role="tab">
                <i class="fas fa-file-alt me-2"></i>Templates
                <span class="badge bg-primary ms-1"><?= count($templates) ?></span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab === 'workflows' ? 'active' : '' ?>" href="?tab=workflows" role="tab">
                <i class="fas fa-sitemap me-2"></i>Workflows
                <span class="badge bg-primary ms-1"><?= count($workflows) ?></span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab === 'tasks' ? 'active' : '' ?>" href="?tab=tasks" role="tab">
                <i class="fas fa-tasks me-2"></i>Tasks
                <span class="badge bg-primary ms-1"><?= count($tasks) ?></span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab === 'collaboration' ? 'active' : '' ?>" href="?tab=collaboration" role="tab">
                <i class="fas fa-users me-2"></i>Collaboration
                <span class="badge bg-primary ms-1"><?= count($notes) ?></span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab === 'analytics' ? 'active' : '' ?>" href="?tab=analytics" role="tab">
                <i class="fas fa-chart-line me-2"></i>Analytics
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab === 'settings' ? 'active' : '' ?>" href="?tab=settings" role="tab">
                <i class="fas fa-cog me-2"></i>Settings
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content tab-content-custom">
        
        <!-- Templates Tab -->
        <?php if ($active_tab === 'templates'): ?>
        <div class="tab-pane fade show active">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3>Message Templates</h3>
                    <p class="text-muted mb-0">Create and manage reusable message templates for faster communication</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal">
                    <i class="fas fa-plus me-2"></i>New Template
                </button>
            </div>
            
            <div class="row">
                <?php if (empty($templates)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                        <h4>No Templates Yet</h4>
                        <p class="text-muted">Create your first message template to get started</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal">
                            <i class="fas fa-plus me-2"></i>Create Template
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="template-card card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($template['name']) ?></h6>
                                    <small class="opacity-75"><?= ucfirst($template['category']) ?> Template</small>
                                </div>
                                <div class="card-actions">
                                    <span class="usage-badge"><?= $template['usage_count'] ?> uses</span>
                                    <?php if ($template['is_global']): ?>
                                        <span class="usage-badge"><i class="fas fa-globe"></i></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($template['subject']): ?>
                                    <p class="mb-2"><strong>Subject:</strong> <?= htmlspecialchars($template['subject']) ?></p>
                                <?php endif; ?>
                                <div class="template-preview"><?= htmlspecialchars(substr($template['content'], 0, 200)) ?><?= strlen($template['content']) > 200 ? '...' : '' ?></div>
                                
                                <?php if ($template['variables']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Variables:</small>
                                        <?php foreach (json_decode($template['variables'], true) as $var): ?>
                                            <span class="badge bg-light text-dark me-1">{{<?= $var ?>}}</span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary" onclick="editTemplate(<?= $template['id'] ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="useTemplate(<?= $template['id'] ?>)">
                                    <i class="fas fa-paper-plane"></i> Use
                                </button>
                                <button class="btn btn-sm btn-outline-info" onclick="duplicateTemplate(<?= $template['id'] ?>)">
                                    <i class="fas fa-copy"></i> Duplicate
                                </button>
                                <?php if (!$template['is_global'] || $current_admin['user_type'] === 'super_admin'): ?>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(<?= $template['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Workflows Tab -->
        <?php if ($active_tab === 'workflows'): ?>
        <div class="tab-pane fade show active">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3>Automated Workflows</h3>
                    <p class="text-muted mb-0">Set up rules to automatically handle common scenarios</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#workflowModal">
                    <i class="fas fa-plus me-2"></i>New Workflow
                </button>
            </div>
            
            <div class="row">
                <?php foreach ($workflows as $workflow): ?>
                <div class="col-lg-6">
                    <div class="workflow-card card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($workflow['name']) ?></h6>
                                <small class="opacity-75">Trigger: <?= ucfirst(str_replace('_', ' ', $workflow['trigger_event'])) ?></small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="workflow-status <?= $workflow['is_active'] ? 'active' : 'inactive' ?>">
                                    <span class="status-indicator"></span>
                                    <?= $workflow['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="text-muted"><?= htmlspecialchars($workflow['description']) ?></p>
                            <div class="row text-center mt-3">
                                <div class="col-6">
                                    <div class="fw-bold"><?= $workflow['execution_count'] ?></div>
                                    <small class="text-muted">Executions</small>
                                </div>
                                <div class="col-6">
                                    <div class="fw-bold"><?= $workflow['last_executed_at'] ? date('M j', strtotime($workflow['last_executed_at'])) : 'Never' ?></div>
                                    <small class="text-muted">Last Run</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="editWorkflow(<?= $workflow['id'] ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-<?= $workflow['is_active'] ? 'warning' : 'success' ?>" onclick="toggleWorkflow(<?= $workflow['id'] ?>, <?= $workflow['is_active'] ? 'false' : 'true' ?>)">
                                <i class="fas fa-<?= $workflow['is_active'] ? 'pause' : 'play' ?>"></i> <?= $workflow['is_active'] ? 'Disable' : 'Enable' ?>
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="testWorkflow(<?= $workflow['id'] ?>)">
                                <i class="fas fa-play"></i> Test
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($workflows)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-sitemap fa-4x text-muted mb-3"></i>
                        <h4>No Workflows Yet</h4>
                        <p class="text-muted">Create automated workflows to handle repetitive tasks</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#workflowModal">
                            <i class="fas fa-plus me-2"></i>Create Workflow
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tasks Tab -->
        <?php if ($active_tab === 'tasks'): ?>
        <div class="tab-pane fade show active">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3>Task Management</h3>
                    <p class="text-muted mb-0">Track and manage your administrative tasks</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal">
                    <i class="fas fa-plus me-2"></i>New Task
                </button>
            </div>
            
            <!-- Task Filters -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary active" onclick="filterTasks('all')">All</button>
                        <button type="button" class="btn btn-outline-primary" onclick="filterTasks('pending')">Pending</button>
                        <button type="button" class="btn btn-outline-primary" onclick="filterTasks('in_progress')">In Progress</button>
                        <button type="button" class="btn btn-outline-primary" onclick="filterTasks('completed')">Completed</button>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-outline-secondary btn-sm" onclick="exportTasks()">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                </div>
            </div>
            
            <div class="row">
                <?php foreach ($tasks as $task): ?>
                <div class="col-lg-6" data-task-status="<?= $task['status'] ?>">
                    <div class="task-card card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($task['title']) ?></h6>
                                <small class="opacity-75"><?= ucfirst(str_replace('_', ' ', $task['task_type'])) ?></small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="task-priority priority-<?= $task['priority'] ?>"><?= ucfirst($task['priority']) ?></span>
                                <span class="task-status status-<?= $task['status'] ?>"><?= ucfirst(str_replace('_', ' ', $task['status'])) ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($task['description']): ?>
                                <p class="text-muted mb-2"><?= htmlspecialchars($task['description']) ?></p>
                            <?php endif; ?>
                            
                            <div class="row text-sm">
                                <?php if ($task['order_title']): ?>
                                    <div class="col-12 mb-2">
                                        <i class="fas fa-file me-1"></i>
                                        <a href="custom-orders-enhanced.php?search=<?= urlencode($task['order_title']) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($task['order_title']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="col-6">
                                    <small class="text-muted">Assigned by:</small><br>
                                    <small><?= htmlspecialchars($task['assigned_by_name'] ?? 'System') ?></small>
                                </div>
                                
                                <?php if ($task['due_date']): ?>
                                    <div class="col-6">
                                        <small class="text-muted">Due:</small><br>
                                        <small class="<?= strtotime($task['due_date']) < time() && $task['status'] !== 'completed' ? 'text-danger' : '' ?>">
                                            <?= date('M j, Y', strtotime($task['due_date'])) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($task['estimated_hours'] || $task['actual_hours']): ?>
                                    <div class="col-12 mt-2">
                                        <small class="text-muted">
                                            <?php if ($task['estimated_hours']): ?>Est: <?= $task['estimated_hours'] ?>h<?php endif; ?>
                                            <?php if ($task['actual_hours']): ?> | Actual: <?= $task['actual_hours'] ?>h<?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-flex gap-2">
                                <?php if ($task['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-primary" onclick="updateTaskStatus(<?= $task['id'] ?>, 'in_progress')">
                                        <i class="fas fa-play"></i> Start
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (in_array($task['status'], ['pending', 'in_progress'])): ?>
                                    <button class="btn btn-sm btn-success" onclick="completeTask(<?= $task['id'] ?>)">
                                        <i class="fas fa-check"></i> Complete
                                    </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-outline-primary" onclick="editTask(<?= $task['id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTask(<?= $task['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($tasks)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-tasks fa-4x text-muted mb-3"></i>
                        <h4>No Tasks Yet</h4>
                        <p class="text-muted">Create tasks to organize your workflow</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal">
                            <i class="fas fa-plus me-2"></i>Create Task
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Collaboration Tab -->
        <?php if ($active_tab === 'collaboration'): ?>
        <div class="tab-pane fade show active">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3>Team Collaboration</h3>
                    <p class="text-muted mb-0">Internal notes and team communication</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#noteModal">
                    <i class="fas fa-plus me-2"></i>Add Note
                </button>
            </div>
            
            <div class="row">
                <?php foreach ($notes as $note): ?>
                <div class="col-lg-6">
                    <div class="note-card card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($note['admin_name']) ?></h6>
                                <small class="opacity-75"><?= date('M j, Y g:i A', strtotime($note['created_at'])) ?></small>
                            </div>
                            <div>
                                <span class="note-type-badge type-<?= $note['note_type'] ?>"><?= ucfirst($note['note_type']) ?></span>
                                <span class="task-priority priority-<?= $note['priority'] ?>"><?= ucfirst($note['priority']) ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><?= nl2br(htmlspecialchars($note['note'])) ?></p>
                            
                            <?php if ($note['order_title']): ?>
                                <div class="border-top pt-2">
                                    <small class="text-muted">Related to:</small>
                                    <a href="custom-orders-enhanced.php?search=<?= urlencode($note['order_title']) ?>" class="d-block text-decoration-none">
                                        <?= htmlspecialchars($note['order_title']) ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="replyToNote(<?= $note['id'] ?>)">
                                <i class="fas fa-reply"></i> Reply
                            </button>
                            <?php if ($note['admin_id'] == $_SESSION['user_id']): ?>
                                <button class="btn btn-sm btn-outline-secondary" onclick="editNote(<?= $note['id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($notes)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                        <h4>No Team Notes Yet</h4>
                        <p class="text-muted">Start collaborating with internal notes</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#noteModal">
                            <i class="fas fa-plus me-2"></i>Add Note
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Analytics Tab -->
        <?php if ($active_tab === 'analytics'): ?>
        <div class="tab-pane fade show active">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3>Performance Analytics</h3>
                    <p class="text-muted mb-0">Track your productivity and performance metrics</p>
                </div>
                <div class="d-flex gap-2">
                    <select class="form-select" style="width: auto;" onchange="updateAnalyticsPeriod(this.value)">
                        <option value="7">Last 7 days</option>
                        <option value="30" selected>Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </select>
                    <button class="btn btn-outline-primary" onclick="exportAnalytics()">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                </div>
            </div>
            
            <!-- Analytics Cards -->
            <div class="analytics-grid">
                <?php
                $total_orders = array_sum(array_column($analytics, 'orders_handled'));
                $total_approved = array_sum(array_column($analytics, 'orders_approved'));
                $total_completed = array_sum(array_column($analytics, 'orders_completed'));
                $total_revenue = array_sum(array_column($analytics, 'total_revenue_generated'));
                $avg_response = count($analytics) > 0 ? array_sum(array_column($analytics, 'avg_response_time_hours')) / count($analytics) : 0;
                ?>
                
                <div class="analytics-card">
                    <div class="analytics-value"><?= number_format($total_orders) ?></div>
                    <div class="analytics-label">Orders Handled</div>
                    <div class="analytics-change change-positive">
                        <i class="fas fa-arrow-up me-1"></i>+12% vs last period
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="analytics-value"><?= $total_orders > 0 ? number_format(($total_approved / $total_orders) * 100, 1) : 0 ?>%</div>
                    <div class="analytics-label">Approval Rate</div>
                    <div class="analytics-change change-positive">
                        <i class="fas fa-arrow-up me-1"></i>+5% vs last period
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="analytics-value">$<?= number_format($total_revenue, 0) ?></div>
                    <div class="analytics-label">Revenue Generated</div>
                    <div class="analytics-change change-positive">
                        <i class="fas fa-arrow-up me-1"></i>+18% vs last period
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="analytics-value"><?= $avg_response ? number_format($avg_response, 1) . 'h' : 'N/A' ?></div>
                    <div class="analytics-label">Avg Response Time</div>
                    <div class="analytics-change <?= $avg_response < 24 ? 'change-positive' : 'change-negative' ?>">
                        <i class="fas fa-<?= $avg_response < 24 ? 'arrow-down' : 'arrow-up' ?> me-1"></i>Target: <24h
                    </div>
                </div>
            </div>
            
            <!-- Performance Chart -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Daily Performance Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="performanceChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Settings Tab -->
        <?php if ($active_tab === 'settings'): ?>
        <div class="tab-pane fade show active">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3>Admin Settings & Preferences</h3>
                    <p class="text-muted mb-0">Customize your admin experience and quick actions</p>
                </div>
                <button class="btn btn-success" onclick="saveAllSettings()">
                    <i class="fas fa-save me-2"></i>Save All Changes
                </button>
            </div>
            
            <form id="settingsForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="update_preferences">
                
                <!-- Notification Preferences -->
                <div class="preferences-section">
                    <h5><i class="fas fa-bell me-2"></i>Notification Preferences</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="emailNewOrders" name="preferences[email_notifications][new_orders]" checked>
                                <label class="form-check-label" for="emailNewOrders">
                                    Email notifications for new orders
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="emailStatusChanges" name="preferences[email_notifications][status_changes]" checked>
                                <label class="form-check-label" for="emailStatusChanges">
                                    Email notifications for status changes
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="emailMentions" name="preferences[email_notifications][mentions]" checked>
                                <label class="form-check-label" for="emailMentions">
                                    Email notifications for mentions
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="emailOverdue" name="preferences[email_notifications][overdue_alerts]" checked>
                                <label class="form-check-label" for="emailOverdue">
                                    Email notifications for overdue orders
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Dashboard Preferences -->
                <div class="preferences-section">
                    <h5><i class="fas fa-tachometer-alt me-2"></i>Dashboard Preferences</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="defaultViewMode" class="form-label">Default View Mode</label>
                                <select class="form-select" id="defaultViewMode" name="preferences[dashboard_layout][view_mode]">
                                    <option value="kanban">Kanban Board</option>
                                    <option value="list">List View</option>
                                    <option value="timeline">Timeline View</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="itemsPerPage" class="form-label">Items Per Page</label>
                                <select class="form-select" id="itemsPerPage" name="preferences[dashboard_layout][items_per_page]">
                                    <option value="10">10</option>
                                    <option value="15" selected>15</option>
                                    <option value="20">20</option>
                                    <option value="25">25</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="showCompleted" name="preferences[dashboard_layout][show_completed]">
                                <label class="form-check-label" for="showCompleted">
                                    Show completed orders by default
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="autoRefresh" name="preferences[dashboard_layout][auto_refresh]" checked>
                                <label class="form-check-label" for="autoRefresh">
                                    Auto-refresh dashboard
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="preferences-section">
                    <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    <p class="text-muted">Customize your quick action buttons for faster workflow</p>
                    
                    <div class="row">
                        <?php foreach ($quick_actions as $action): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="mb-2" style="color: <?= htmlspecialchars($action['color']) ?>;">
                                        <i class="<?= htmlspecialchars($action['icon']) ?> fa-2x"></i>
                                    </div>
                                    <h6 class="mb-1"><?= htmlspecialchars($action['name']) ?></h6>
                                    <small class="text-muted"><?= $action['usage_count'] ?> uses</small>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editQuickAction(<?= $action['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteQuickAction(<?= $action['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <div class="quick-action-builder" data-bs-toggle="modal" data-bs-target="#quickActionModal">
                                <i class="fas fa-plus fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Add Quick Action</p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Floating Action Button -->
<div class="floating-fab">
    <button class="fab-btn" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-plus"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#templateModal">
            <i class="fas fa-file-alt me-2"></i>New Template
        </a></li>
        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#taskModal">
            <i class="fas fa-tasks me-2"></i>New Task
        </a></li>
        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#noteModal">
            <i class="fas fa-sticky-note me-2"></i>Add Note
        </a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#workflowModal">
            <i class="fas fa-sitemap me-2"></i>New Workflow
        </a></li>
    </ul>
</div>

<!-- Template Modal -->
<div class="modal fade modal-enhanced" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt me-2"></i>Create Message Template
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="templateForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="create_template">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="templateName" class="form-label">Template Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="templateName" name="template_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="templateCategory" class="form-label">Category</label>
                                <select class="form-select" id="templateCategory" name="template_category">
                                    <option value="general">General</option>
                                    <option value="approval">Approval</option>
                                    <option value="rejection">Rejection</option>
                                    <option value="follow_up">Follow-up</option>
                                    <option value="payment">Payment</option>
                                    <option value="completion">Completion</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="templateSubject" class="form-label">Email Subject</label>
                        <input type="text" class="form-control" id="templateSubject" name="template_subject" placeholder="Optional: Email subject line">
                    </div>
                    
                    <div class="mb-3">
                        <label for="templateContent" class="form-label">Message Content <span class="text-danger">*</span></label>
                        <div class="code-editor">
                            <textarea class="form-control" id="templateContent" name="template_content" rows="10" required placeholder="Enter your template content here. Use {{variable_name}} for dynamic content."></textarea>
                        </div>
                        <small class="text-muted">
                            Available variables: {{customer_name}}, {{order_title}}, {{order_type}}, {{custom_price}}, {{admin_name}}
                        </small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="isGlobal" name="is_global">
                                <label class="form-check-label" for="isGlobal">
                                    Make this template available to all admins
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="previewTemplate()">
                                <i class="fas fa-eye me-1"></i>Preview
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Create Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div class="modal fade modal-enhanced" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-tasks me-2"></i>Create New Task
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="taskForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="create_task">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="taskTitle" class="form-label">Task Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="taskTitle" name="task_title" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="taskPriority" class="form-label">Priority</label>
                                <select class="form-select" id="taskPriority" name="task_priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="taskDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="taskDescription" name="task_description" rows="3" placeholder="Detailed task description..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="taskType" class="form-label">Task Type</label>
                                <select class="form-select" id="taskType" name="task_type">
                                    <option value="review">Review</option>
                                    <option value="follow_up">Follow-up</option>
                                    <option value="contact_customer">Contact Customer</option>
                                    <option value="technical_check">Technical Check</option>
                                    <option value="pricing">Pricing</option>
                                    <option value="documentation">Documentation</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="assignTo" class="form-label">Assign To</label>
                                <select class="form-select" id="assignTo" name="assign_to">
                                    <option value="<?= $_SESSION['user_id'] ?>">Myself</option>
                                    <!-- Add other admins here -->
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="dueDate" class="form-label">Due Date</label>
                                <input type="datetime-local" class="form-control" id="dueDate" name="due_date">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="relatedOrder" class="form-label">Related Order (Optional)</label>
                        <input type="text" class="form-control" id="relatedOrder" placeholder="Search for order by title or ID...">
                        <input type="hidden" id="orderId" name="order_id">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Note Modal -->
<div class="modal fade modal-enhanced" id="noteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sticky-note me-2"></i>Add Internal Note
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="noteForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="add_note">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="relatedOrderNote" class="form-label">Related Order <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="relatedOrderNote" placeholder="Search for order by title or ID..." required>
                        <input type="hidden" id="orderIdNote" name="order_id" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="noteType" class="form-label">Note Type</label>
                                <select class="form-select" id="noteType" name="note_type">
                                    <option value="general">General</option>
                                    <option value="technical">Technical</option>
                                    <option value="priority">Priority</option>
                                    <option value="warning">Warning</option>
                                    <option value="follow_up">Follow-up</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="notePriority" class="form-label">Priority</label>
                                <select class="form-select" id="notePriority" name="note_priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="noteContent" class="form-label">Note Content <span class="text-danger">*</span></label>
                        <div class="mention-input">
                            <textarea class="form-control" id="noteContent" name="note_content" rows="5" required placeholder="Enter your note here. Use @username to mention other admins."></textarea>
                            <div class="mention-dropdown" id="mentionDropdown" style="display: none;"></div>
                        </div>
                        <small class="text-muted">Use @username to mention other administrators</small>
                    </div>
                    
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="isPrivate" name="is_private">
                        <label class="form-check-label" for="isPrivate">
                            Private note (only visible to me)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Admin Tools JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeCodeEditors();
    initializeAutocomplete();
    initializeMentions();
    initializeAnalyticsChart();
    loadUserPreferences();
});

// Initialize code editors for template content
function initializeCodeEditors() {
    if (typeof CodeMirror !== 'undefined') {
        const templateEditor = CodeMirror.fromTextArea(document.getElementById('templateContent'), {
            mode: 'htmlmixed',
            lineNumbers: true,
            lineWrapping: true,
            theme: 'default'
        });
        
        // Store editor instance for later use
        window.templateEditor = templateEditor;
    }
}

// Initialize autocomplete for order selection
function initializeAutocomplete() {
    const orderInputs = ['relatedOrder', 'relatedOrderNote'];
    
    orderInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', debounce(function() {
                searchOrders(this.value, inputId);
            }, 300));
        }
    });
}

// Initialize mention system for notes
function initializeMentions() {
    const noteContent = document.getElementById('noteContent');
    if (noteContent) {
        noteContent.addEventListener('input', function(e) {
            const text = this.value;
            const cursorPos = this.selectionStart;
            const textBeforeCursor = text.substring(0, cursorPos);
            const lastAtIndex = textBeforeCursor.lastIndexOf('@');
            
            if (lastAtIndex !== -1 && lastAtIndex === cursorPos - 1) {
                showMentionDropdown();
            } else if (lastAtIndex !== -1) {
                const searchTerm = textBeforeCursor.substring(lastAtIndex + 1);
                if (searchTerm.length > 0 && !searchTerm.includes(' ')) {
                    searchAdminsForMention(searchTerm);
                } else {
                    hideMentionDropdown();
                }
            } else {
                hideMentionDropdown();
            }
        });
    }
}

// Search orders for autocomplete
function searchOrders(query, inputId) {
    if (query.length < 2) return;
    
    fetch(`../api/search-orders.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showOrderSuggestions(data.orders, inputId);
            }
        })
        .catch(error => console.error('Order search error:', error));
}

// Show order suggestions
function showOrderSuggestions(orders, inputId) {
    const input = document.getElementById(inputId);
    const hiddenInput = document.getElementById(inputId === 'relatedOrder' ? 'orderId' : 'orderIdNote');
    
    // Remove existing dropdown
    const existingDropdown = document.getElementById(inputId + 'Dropdown');
    if (existingDropdown) {
        existingDropdown.remove();
    }
    
    // Create new dropdown
    const dropdown = document.createElement('div');
    dropdown.id = inputId + 'Dropdown';
    dropdown.className = 'mention-dropdown';
    dropdown.style.display = 'block';
    
    orders.forEach(order => {
        const item = document.createElement('div');
        item.className = 'mention-item';
        item.innerHTML = `
            <strong>#${order.id}</strong> - ${order.title}
            <small class="d-block text-muted">${order.status} | ${order.user_name}</small>
        `;
        item.onclick = () => {
            input.value = `#${order.id} - ${order.title}`;
            hiddenInput.value = order.id;
            dropdown.remove();
        };
        dropdown.appendChild(item);
    });
    
    input.parentNode.appendChild(dropdown);
}

// Mention system functions
function showMentionDropdown() {
    // Fetch available admins and show dropdown
    fetch('../api/get-admins.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMentionOptions(data.admins);
            }
        });
}

function searchAdminsForMention(query) {
    fetch(`../api/search-admins.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMentionOptions(data.admins);
            }
        });
}

function displayMentionOptions(admins) {
    const dropdown = document.getElementById('mentionDropdown');
    dropdown.innerHTML = '';
    dropdown.style.display = 'block';
    
    admins.forEach(admin => {
        const item = document.createElement('div');
        item.className = 'mention-item';
        item.innerHTML = `
            <strong>@${admin.username}</strong>
            <small class="d-block text-muted">${admin.full_name}</small>
        `;
        item.onclick = () => insertMention(admin.username, admin.id);
        dropdown.appendChild(item);
    });
}

function insertMention(username, adminId) {
    const noteContent = document.getElementById('noteContent');
    const text = noteContent.value;
    const cursorPos = noteContent.selectionStart;
    const textBeforeCursor = text.substring(0, cursorPos);
    const lastAtIndex = textBeforeCursor.lastIndexOf('@');
    
    if (lastAtIndex !== -1) {
        const newText = text.substring(0, lastAtIndex) + `@${username} ` + text.substring(cursorPos);
        noteContent.value = newText;
        noteContent.focus();
        noteContent.setSelectionRange(lastAtIndex + username.length + 2, lastAtIndex + username.length + 2);
    }
    
    hideMentionDropdown();
    
    // Store mentioned admin ID
    const mentionedInput = document.createElement('input');
    mentionedInput.type = 'hidden';
    mentionedInput.name = 'mentioned_admins[]';
    mentionedInput.value = adminId;
    document.getElementById('noteForm').appendChild(mentionedInput);
}

function hideMentionDropdown() {
    document.getElementById('mentionDropdown').style.display = 'none';
}

// Initialize analytics chart
function initializeAnalyticsChart() {
    const ctx = document.getElementById('performanceChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(fn($item) => date('M j', strtotime($item['metric_date'])), array_slice($analytics, 0, 7))) ?>,
                datasets: [{
                    label: 'Orders Handled',
                    data: <?= json_encode(array_map(fn($item) => $item['orders_handled'], array_slice($analytics, 0, 7))) ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Orders Completed',
                    data: <?= json_encode(array_map(fn($item) => $item['orders_completed'], array_slice($analytics, 0, 7))) ?>,
                    borderColor: '#4facfe',
                    backgroundColor: 'rgba(79, 172, 254, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

// Template functions
function editTemplate(templateId) {
    // Fetch template data and populate modal
    fetch(`../api/get-template.php?id=${templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateTemplateModal(data.template, true);
                const modal = new bootstrap.Modal(document.getElementById('templateModal'));
                modal.show();
            }
        });
}

function useTemplate(templateId) {
    // Redirect to compose message with template
    window.location.href = `compose.php?template_id=${templateId}`;
}

function duplicateTemplate(templateId) {
    fetch(`../api/get-template.php?id=${templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const template = data.template;
                template.name = template.name + ' (Copy)';
                populateTemplateModal(template, false);
                const modal = new bootstrap.Modal(document.getElementById('templateModal'));
                modal.show();
            }
        });
}

function deleteTemplate(templateId) {
    if (confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="delete_template">
            <input type="hidden" name="template_id" value="${templateId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function previewTemplate() {
    const content = window.templateEditor ? window.templateEditor.getValue() : document.getElementById('templateContent').value;
    const subject = document.getElementById('templateSubject').value;
    
    // Show preview in a new modal
    const previewModal = document.createElement('div');
    previewModal.className = 'modal fade';
    previewModal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Template Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    ${subject ? `<div class="mb-3"><strong>Subject:</strong> ${subject}</div>` : ''}
                    <div class="border rounded p-3" style="white-space: pre-wrap; background: #f8f9fa;">${content}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(previewModal);
    const modal = new bootstrap.Modal(previewModal);
    modal.show();
    
    previewModal.addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(previewModal);
    });
}

// Task functions
function filterTasks(status) {
    const taskCards = document.querySelectorAll('[data-task-status]');
    const filterButtons = document.querySelectorAll('.btn-group .btn');
    
    // Update active button
    filterButtons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter tasks
    taskCards.forEach(card => {
        if (status === 'all' || card.dataset.taskStatus === status) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function updateTaskStatus(taskId, status) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" value="update_task_status">
        <input type="hidden" name="task_id" value="${taskId}">
        <input type="hidden" name="task_status" value="${status}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function completeTask(taskId) {
    const hours = prompt('How many hours did this task take? (optional)');
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" value="update_task_status">
        <input type="hidden" name="task_id" value="${taskId}">
        <input type="hidden" name="task_status" value="completed">
        ${hours ? `<input type="hidden" name="actual_hours" value="${hours}">` : ''}
    `;
    document.body.appendChild(form);
    form.submit();
}

// Workflow functions
function editWorkflow(workflowId) {
    // Implementation for workflow editing
    alert('Workflow editing will be implemented in the next version.');
}

function toggleWorkflow(workflowId, isActive) {
    if (confirm(`Are you sure you want to ${isActive ? 'enable' : 'disable'} this workflow?`)) {
        // Implementation for workflow toggle
        location.reload();
    }
}

function testWorkflow(workflowId) {
    if (confirm('This will execute the workflow with test data. Continue?')) {
        fetch(`../api/test-workflow.php?id=${workflowId}`, { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Workflow test completed successfully!');
                } else {
                    alert('Workflow test failed: ' + data.message);
                }
            });
    }
}

// Settings functions
function saveAllSettings() {
    document.getElementById('settingsForm').submit();
}

function loadUserPreferences() {
    // Load and apply user preferences
    const preferences = <?= json_encode(array_column($preferences, 'preference_value', 'preference_key')) ?>;
    
    // Apply email notification preferences
    if (preferences.email_notifications) {
        const emailPrefs = JSON.parse(preferences.email_notifications);
        Object.keys(emailPrefs).forEach(key => {
            const checkbox = document.querySelector(`input[name="preferences[email_notifications][${key}]"]`);
            if (checkbox) {
                checkbox.checked = emailPrefs[key];
            }
        });
    }
    
    // Apply dashboard preferences
    if (preferences.dashboard_layout) {
        const dashboardPrefs = JSON.parse(preferences.dashboard_layout);
        Object.keys(dashboardPrefs).forEach(key => {
            const element = document.querySelector(`[name="preferences[dashboard_layout][${key}]"]`);
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = dashboardPrefs[key];
                } else {
                    element.value = dashboardPrefs[key];
                }
            }
        });
    }
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function exportTools() {
    window.open('../api/export-admin-tools.php', '_blank');
}

function importTools() {
    alert('Import functionality will be available in the next version.');
}

function showTutorial() {
    alert('Interactive tutorial coming soon!');
}

// Initialize tooltips
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<?php renderAdminFooter(); ?>