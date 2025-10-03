<?php
/**
 * Migration Runner - Add notification preferences to users table
 */

require_once __DIR__ . '/includes/config.php';

if (!$db) {
    die("Database connection not available\n");
}

echo "Running migration: Add notification preferences to users table\n";
echo "================================================================\n";

try {
    // Check if column exists first
    $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'notification_preferences'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        echo "Adding notification_preferences column to users table...\n";
        $db->exec("ALTER TABLE users ADD COLUMN notification_preferences TEXT DEFAULT NULL COMMENT 'JSON string storing user email notification preferences'");
        echo "✓ Column added successfully\n";
    } else {
        echo "✓ notification_preferences column already exists\n";
    }
    
    // Update existing users with default preferences
    echo "Setting default notification preferences for existing users...\n";
    $stmt = $db->prepare("
        UPDATE users 
        SET notification_preferences = ? 
        WHERE notification_preferences IS NULL
    ");
    
    $defaultPreferences = json_encode([
        'custom_order_submission' => true,
        'custom_order_status_update' => true,
        'custom_order_approval' => true,
        'custom_order_rejection' => true,
        'order_confirmations' => true,
        'marketing_emails' => false
    ]);
    
    $stmt->execute([$defaultPreferences]);
    $affectedRows = $stmt->rowCount();
    echo "✓ Updated {$affectedRows} users with default preferences\n";
    
    // Create index if it doesn't exist
    try {
        $db->exec("CREATE INDEX idx_users_notification_prefs ON users(notification_preferences(100))");
        echo "✓ Created index for notification preferences\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "✓ Index for notification preferences already exists\n";
        } else {
            echo "⚠ Could not create index: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n================================================================\n";
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>