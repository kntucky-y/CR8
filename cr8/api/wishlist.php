<?php
require_once 'config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$conn = getDbConnection();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'load':
    case 'get':
        $stmt = $conn->prepare("
            SELECT w.*, p.product_name, p.price, p.image, p.quantity, p.is_active, a.artist_name
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            JOIN artists a ON p.artist_id = a.id
            WHERE w.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $wishlist = [];
        
        while ($row = $result->fetch_assoc()) {
            $row['price'] = (float)$row['price'];
            $row['quantity'] = (int)$row['quantity'];
            $wishlist[] = $row;
        }
        
        echo json_encode(['success' => true, 'wishlist' => $wishlist]);
        $stmt->close();
        break;

    case 'add':
        $data = json_decode(file_get_contents('php://input'), true);
        $product_id = $data['product_id'] ?? 0;

        // Check if already in wishlist
        $stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $insert_stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $insert_stmt->bind_param("ii", $user_id, $product_id);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        $stmt->close();
        
        echo json_encode(['success' => true]);
        break;

    case 'remove':
        $data = json_decode(file_get_contents('php://input'), true);
        $product_id = $data['product_id'] ?? 0;

        $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
