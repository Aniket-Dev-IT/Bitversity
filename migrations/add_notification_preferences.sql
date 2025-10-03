-- Migration: Add notification preferences to users table
-- Date: 2024-12-19
-- Description: Add notification_preferences column to support email unsubscribe functionality

-- Check if the column doesn't exist and add it
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS notification_preferences TEXT DEFAULT NULL 
COMMENT 'JSON string storing user email notification preferences';

-- Update existing users to have default preferences (all enabled except marketing)
UPDATE users 
SET notification_preferences = JSON_OBJECT(
    'custom_order_submission', true,
    'custom_order_status_update', true, 
    'custom_order_approval', true,
    'custom_order_rejection', true,
    'order_confirmations', true,
    'marketing_emails', false
) 
WHERE notification_preferences IS NULL;

-- Add index for better performance when checking preferences
CREATE INDEX IF NOT EXISTS idx_users_notification_prefs ON users(notification_preferences(100));