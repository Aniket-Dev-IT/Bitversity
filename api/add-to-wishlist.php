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
    echo json_encode(["error" => "Please log in to add items to wishlist"]);
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
        INSERT IGNORE INTO wishlist (user_id, item_type, item_id)
        VALUES (?, ?, ?)
    ");
    
    $stmt->execute([$_SESSION["user_id"], $input["item_type"], $input["item_id"]]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Item added to wishlist"]);
    } else {
        echo json_encode(["success" => false, "message" => "Item already in wishlist"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>