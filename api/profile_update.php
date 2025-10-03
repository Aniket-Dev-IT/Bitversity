<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Validate required fields
    if (!isset($_POST['first_name']) || !isset($_POST['last_name']) || !isset($_POST['email']) || !isset($_POST['current_password'])) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    // Get current user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Verify current password
    if (!password_verify($_POST['current_password'], $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }
    
    // Check if email is already taken by another user
    if ($_POST['email'] !== $user['email']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$_POST['email'], $user_id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already in use']);
            exit();
        }
    }
    
    // Update user info
    $update_password = '';
    $params = [$_POST['first_name'], $_POST['last_name'], $_POST['email']];
    
    // Check if password should be updated
    if (!empty($_POST['new_password'])) {
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit();
        }
        $update_password = ', password = ?';
        $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    }
    
    $params[] = $user_id;
    
    $stmt = $db->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?, email = ?, updated_at = NOW() $update_password
        WHERE id = ?
    ");
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $e->getMessage()]);
}
?>