<?php
session_start();
require_once "../includes/config.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Please log in to add items to cart"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input["item_type"]) || !isset($input["item_id"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

try {
    $stmt = $db->prepare("
        INSERT INTO cart (user_id, item_type, item_id, quantity)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ");
    
    $quantity = $input["quantity"] ?? 1;
    $stmt->execute([$_SESSION["user_id"], $input["item_type"], $input["item_id"], $quantity]);
    
    echo json_encode(["success" => true, "message" => "Item added to cart"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>