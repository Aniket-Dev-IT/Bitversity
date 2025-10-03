<?php
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit;
    }
    
    $db->beginTransaction();
    
    $savedCount = 0;
    
    // Get all existing settings
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings");
    $stmt->execute();
    $existingSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach ($input as $key => $value) {
        // Skip empty keys
        if (empty($key)) continue;
        
        // Handle password fields - don't update if value is masked
        if (strpos($key, 'password') !== false && (str_starts_with($value, '••••') || empty($value))) {
            continue;
        }
        
        // Handle secret/key fields - don't update if value is masked
        if ((strpos($key, 'secret') !== false || strpos($key, 'key') !== false) && 
            (str_starts_with($value, '••••') || empty($value))) {
            continue;
        }
        
        // Check if setting exists
        if (array_key_exists($key, $existingSettings)) {
            // Only update if value has changed
            if ($existingSettings[$key] !== $value) {
                $updateStmt = $db->prepare("
                    UPDATE system_settings 
                    SET setting_value = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE setting_key = ?
                ");
                $updateStmt->execute([$value, $key]);
                $savedCount++;
            }
        } else {
            // Insert new setting
            $insertStmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type, category) 
                VALUES (?, ?, 'string', 'general')
            ");
            $insertStmt->execute([$key, $value]);
            $savedCount++;
        }
    }
    
    $db->commit();
    
    // Log the activity
    logActivity($_SESSION['user_id'], 'settings_updated', 'admin_settings', null, [
        'settings_updated' => $savedCount,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully saved {$savedCount} settings",
        'saved_count' => $savedCount
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Settings save error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save settings: ' . $e->getMessage()
    ]);
}
?>