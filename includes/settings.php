<?php
/**
 * System Settings Helper Functions
 * Provides easy access to system settings throughout the application
 */

// Cache for settings to avoid multiple database queries
$_settingsCache = null;

/**
 * Load all system settings into cache
 */
function loadSystemSettings() {
    global $db, $_settingsCache;
    
    if ($_settingsCache !== null) {
        return $_settingsCache;
    }
    
    try {
        $stmt = $db->prepare("SELECT setting_key, setting_value, setting_type FROM system_settings");
        $stmt->execute();
        $settings = $stmt->fetchAll();
        
        $_settingsCache = [];
        foreach ($settings as $setting) {
            $value = $setting['setting_value'];
            
            // Convert values based on type
            switch ($setting['setting_type']) {
                case 'boolean':
                    $value = (bool) $value;
                    break;
                case 'number':
                    $value = is_numeric($value) ? (float) $value : 0;
                    break;
                case 'json':
                    $value = json_decode($value, true) ?: [];
                    break;
                default:
                    // string and text remain as-is
                    break;
            }
            
            $_settingsCache[$setting['setting_key']] = $value;
        }
        
    } catch (Exception $e) {
        error_log("Settings load error: " . $e->getMessage());
        $_settingsCache = [];
    }
    
    return $_settingsCache;
}

/**
 * Get a system setting value
 */
function getSetting($key, $default = null) {
    $settings = loadSystemSettings();
    return $settings[$key] ?? $default;
}

/**
 * Update a system setting
 */
function setSetting($key, $value) {
    global $db, $_settingsCache;
    
    try {
        // Determine setting type
        $type = 'string';
        if (is_bool($value)) {
            $type = 'boolean';
            $value = $value ? '1' : '0';
        } elseif (is_numeric($value)) {
            $type = 'number';
        } elseif (is_array($value) || is_object($value)) {
            $type = 'json';
            $value = json_encode($value);
        }
        
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            setting_type = VALUES(setting_type),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([$key, $value, $type]);
        
        // Clear cache to force reload
        $_settingsCache = null;
        
        return true;
        
    } catch (Exception $e) {
        error_log("Setting update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get multiple settings by category
 */
function getSettingsByCategory($category) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT setting_key, setting_value, setting_type 
            FROM system_settings 
            WHERE category = ?
        ");
        $stmt->execute([$category]);
        $settings = $stmt->fetchAll();
        
        $result = [];
        foreach ($settings as $setting) {
            $value = $setting['setting_value'];
            
            // Convert values based on type
            switch ($setting['setting_type']) {
                case 'boolean':
                    $value = (bool) $value;
                    break;
                case 'number':
                    $value = is_numeric($value) ? (float) $value : 0;
                    break;
                case 'json':
                    $value = json_decode($value, true) ?: [];
                    break;
            }
            
            $result[$setting['setting_key']] = $value;
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Settings by category error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get email template by key
 */
function getEmailTemplate($templateKey) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM email_templates 
            WHERE template_key = ? AND is_active = 1
        ");
        $stmt->execute([$templateKey]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Email template error: " . $e->getMessage());
        return null;
    }
}

/**
 * Process email template with variables
 */
function processEmailTemplate($templateKey, $variables = []) {
    $template = getEmailTemplate($templateKey);
    
    if (!$template) {
        return null;
    }
    
    $subject = $template['subject'];
    $body = $template['body'];
    
    // Add common system variables
    $systemVars = [
        'site_name' => getSetting('site_name', 'Bitversity'),
        'contact_email' => getSetting('contact_email', 'admin@bitversity.com'),
        'support_email' => getSetting('support_email', 'support@bitversity.com'),
        'base_url' => BASE_URL ?? 'http://localhost',
        'current_year' => date('Y'),
        'current_date' => date('F j, Y')
    ];
    
    $allVariables = array_merge($systemVars, $variables);
    
    // Replace variables in subject and body
    foreach ($allVariables as $key => $value) {
        $placeholder = '{{' . $key . '}}';
        $subject = str_replace($placeholder, $value, $subject);
        $body = str_replace($placeholder, $value, $body);
    }
    
    return [
        'subject' => $subject,
        'body' => $body,
        'template' => $template
    ];
}

/**
 * Check if a feature is enabled
 */
function isFeatureEnabled($feature) {
    return getSetting($feature, false);
}

/**
 * Get public settings for frontend use
 */
function getPublicSettings() {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT setting_key, setting_value, setting_type 
            FROM system_settings 
            WHERE is_public = 1
        ");
        $stmt->execute();
        $settings = $stmt->fetchAll();
        
        $result = [];
        foreach ($settings as $setting) {
            $value = $setting['setting_value'];
            
            // Convert values based on type
            switch ($setting['setting_type']) {
                case 'boolean':
                    $value = (bool) $value;
                    break;
                case 'number':
                    $value = is_numeric($value) ? (float) $value : 0;
                    break;
                case 'json':
                    $value = json_decode($value, true) ?: [];
                    break;
            }
            
            $result[$setting['setting_key']] = $value;
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Public settings error: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if site is in maintenance mode
 */
function isMaintenanceMode() {
    return getSetting('maintenance_mode', false);
}

/**
 * Get maintenance message
 */
function getMaintenanceMessage() {
    return getSetting('maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon.');
}

/**
 * Format setting value for display
 */
function formatSettingValue($value, $type) {
    switch ($type) {
        case 'boolean':
            return $value ? 'Enabled' : 'Disabled';
        case 'number':
            return number_format($value);
        case 'json':
            return is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value;
        default:
            return $value;
    }
}

/**
 * Validate setting value based on type and constraints
 */
function validateSetting($key, $value, $type) {
    switch ($type) {
        case 'boolean':
            return in_array($value, ['0', '1', 0, 1, true, false], true);
            
        case 'number':
            return is_numeric($value);
            
        case 'json':
            if (is_string($value)) {
                json_decode($value);
                return json_last_error() === JSON_ERROR_NONE;
            }
            return is_array($value) || is_object($value);
            
        case 'email':
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            
        case 'url':
            return empty($value) || filter_var($value, FILTER_VALIDATE_URL) !== false;
            
        default:
            return true; // string and text types are always valid
    }
}

/**
 * Get setting with validation
 */
function getValidatedSetting($key, $default = null, $type = 'string') {
    $value = getSetting($key, $default);
    
    if (validateSetting($key, $value, $type)) {
        return $value;
    }
    
    return $default;
}

/**
 * Encrypt sensitive setting value
 */
function encryptSetting($value) {
    if (empty($value)) return '';
    
    // Simple encryption for sensitive settings
    // In production, use proper encryption with secret keys
    return base64_encode($value);
}

/**
 * Decrypt sensitive setting value
 */
function decryptSetting($value) {
    if (empty($value)) return '';
    
    return base64_decode($value);
}
?>