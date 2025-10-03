<?php
/**
 * Notification Service
 * 
 * Manages user email notification preferences and unsubscribe functionality
 */

class NotificationService {
    private $db;
    private $emailService;
    
    public function __construct($database) {
        $this->db = $database;
        $this->emailService = new EmailService($database);
    }
    
    /**
     * Check if user has opted out of specific notification types
     */
    public function canSendNotification($userId, $notificationType) {
        try {
            $stmt = $this->db->prepare("
                SELECT notification_preferences 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) return false;
            
            // If no preferences set, default to all enabled
            if (empty($user['notification_preferences'])) {
                return true;
            }
            
            $preferences = json_decode($user['notification_preferences'], true);
            
            // Default to enabled if preference not set
            return $preferences[$notificationType] ?? true;
            
        } catch (Exception $e) {
            error_log("Error checking notification preferences: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send custom order notification if user allows it
     */
    public function sendCustomOrderNotification($customOrderId, $notificationType, $additionalData = []) {
        try {
            // Get custom order and user info
            $stmt = $this->db->prepare("
                SELECT cor.*, u.id as user_id, u.email, u.full_name
                FROM custom_order_requests cor
                JOIN users u ON cor.user_id = u.id
                WHERE cor.id = ?
            ");
            $stmt->execute([$customOrderId]);
            $customOrder = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customOrder) return false;
            
            // Check if user allows this type of notification
            if (!$this->canSendNotification($customOrder['user_id'], $notificationType)) {
                error_log("User {$customOrder['user_id']} has opted out of {$notificationType} notifications");
                return true; // Return true as it's not an error, user just opted out
            }
            
            // Send appropriate notification
            switch ($notificationType) {
                case 'custom_order_submission':
                    return $this->emailService->sendCustomOrderSubmissionConfirmation($customOrderId);
                    
                case 'custom_order_status_update':
                    $oldStatus = $additionalData['old_status'] ?? '';
                    $newStatus = $additionalData['new_status'] ?? '';
                    $reason = $additionalData['reason'] ?? '';
                    return $this->emailService->sendCustomOrderStatusUpdate($customOrderId, $oldStatus, $newStatus, $reason);
                    
                case 'custom_order_approval':
                    return $this->emailService->sendCustomOrderApproval($customOrderId);
                    
                case 'custom_order_rejection':
                    $rejectionReason = $additionalData['rejection_reason'] ?? '';
                    return $this->emailService->sendCustomOrderRejection($customOrderId, $rejectionReason);
                    
                default:
                    error_log("Unknown notification type: {$notificationType}");
                    return false;
            }
            
        } catch (Exception $e) {
            error_log("Error sending custom order notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user notification preferences
     */
    public function updateNotificationPreferences($userId, $preferences) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET notification_preferences = ? 
                WHERE id = ?
            ");
            
            $preferencesJson = json_encode($preferences);
            $stmt->execute([$preferencesJson, $userId]);
            
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            error_log("Error updating notification preferences: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user notification preferences
     */
    public function getNotificationPreferences($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT notification_preferences 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || empty($user['notification_preferences'])) {
                // Return default preferences
                return [
                    'custom_order_submission' => true,
                    'custom_order_status_update' => true,
                    'custom_order_approval' => true,
                    'custom_order_rejection' => true,
                    'order_confirmations' => true,
                    'marketing_emails' => false
                ];
            }
            
            return json_decode($user['notification_preferences'], true);
            
        } catch (Exception $e) {
            error_log("Error getting notification preferences: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate unsubscribe token for user
     */
    public function generateUnsubscribeToken($userId, $notificationType = 'all') {
        $data = [
            'user_id' => $userId,
            'type' => $notificationType,
            'timestamp' => time()
        ];
        
        $dataString = json_encode($data);
        $token = base64_encode($dataString);
        
        // Simple verification hash (in production, use proper encryption)
        $hash = hash_hmac('sha256', $token, ENCRYPTION_KEY);
        
        return $token . '.' . $hash;
    }
    
    /**
     * Verify and process unsubscribe token
     */
    public function processUnsubscribeToken($token) {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 2) return false;
            
            [$dataToken, $hash] = $parts;
            
            // Verify hash
            $expectedHash = hash_hmac('sha256', $dataToken, ENCRYPTION_KEY);
            if (!hash_equals($expectedHash, $hash)) return false;
            
            // Decode data
            $dataString = base64_decode($dataToken);
            $data = json_decode($dataString, true);
            
            if (!$data || !isset($data['user_id'])) return false;
            
            $userId = $data['user_id'];
            $notificationType = $data['type'] ?? 'all';
            
            // Get current preferences
            $currentPrefs = $this->getNotificationPreferences($userId);
            
            if ($notificationType === 'all') {
                // Unsubscribe from all email notifications
                foreach ($currentPrefs as $key => $value) {
                    $currentPrefs[$key] = false;
                }
            } else {
                // Unsubscribe from specific notification type
                $currentPrefs[$notificationType] = false;
            }
            
            // Update preferences
            return $this->updateNotificationPreferences($userId, $currentPrefs);
            
        } catch (Exception $e) {
            error_log("Error processing unsubscribe token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add unsubscribe link to email
     */
    public function addUnsubscribeLink($emailBody, $userId, $notificationType = 'all') {
        $token = $this->generateUnsubscribeToken($userId, $notificationType);
        $unsubscribeUrl = APP_URL . "/unsubscribe.php?token=" . urlencode($token);
        
        $unsubscribeFooter = '
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999; text-align: center;">
            <p>Don\'t want to receive these emails? <a href="' . $unsubscribeUrl . '" style="color: #007bff;">Unsubscribe here</a></p>
            <p>Or manage your <a href="' . APP_URL . '/dashboard.php?section=notifications" style="color: #007bff;">notification preferences</a></p>
        </div>';
        
        // Insert before the closing container div
        $emailBody = str_replace('</div></body>', $unsubscribeFooter . '</div></body>', $emailBody);
        
        return $emailBody;
    }
}
?>