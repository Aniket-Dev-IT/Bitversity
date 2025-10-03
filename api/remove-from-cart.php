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
    echo json_encode(["error" => "Please log in"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input["cart_id"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing cart item ID"]);
    exit;
}

try {
    $stmt = $db->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$input["cart_id"], $_SESSION["user_id"]]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Item removed from cart"]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Cart item not found"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>