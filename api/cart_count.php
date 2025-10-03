<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $count = $stmt->fetchColumn();
    
    echo json_encode(['count' => intval($count)]);
    
} catch (Exception $e) {
    echo json_encode(['count' => 0]);
}
?>