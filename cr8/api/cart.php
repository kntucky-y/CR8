<?php
require_once 'config.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '.dcism.org',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);
    session_start();
}

$conn = getDbConnection();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'session_id' => session_id()]);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'load':
    case 'get':
        $stmt = $conn->prepare("
            SELECT c.id, c.product_id, c.quantity as cart_quantity, 
                   p.product_name, p.price, p.image, p.quantity as stock, a.artist_name
            FROM carts c
            JOIN products p ON c.product_id = p.id
            JOIN artists a ON p.artist_id = a.id
            WHERE c.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $cart = [];
        
        while ($row = $result->fetch_assoc()) {
            $row['price'] = (float)$row['price'];
            $row['quantity'] = (int)$row['cart_quantity']; // Quantity in cart
            $row['stock'] = (int)$row['stock']; // Available stock
            $row['image_url'] = $row['image']; // Add image_url for compatibility
            unset($row['cart_quantity']);
            $cart[] = $row;
        }
        
        echo json_encode(['success' => true, 'cart' => $cart]);
        $stmt->close();
        break;

    case 'add':
        $data = json_decode(file_get_contents('php://input'), true);
        $product_id = $data['product_id'] ?? 0;
        $quantity = $data['quantity'] ?? 1;

        // Check if already in cart
        $stmt = $conn->prepare("SELECT id, quantity FROM carts WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Update quantity
            $new_quantity = $row['quantity'] + $quantity;
            $update_stmt = $conn->prepare("UPDATE carts SET quantity = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $new_quantity, $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // Insert new
            $insert_stmt = $conn->prepare("INSERT INTO carts (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        $stmt->close();
        
        echo json_encode(['success' => true]);
        break;

    case 'update':
        $data = json_decode(file_get_contents('php://input'), true);
        $product_id = $data['product_id'] ?? 0;
        $quantity = $data['quantity'] ?? 1;

        $stmt = $conn->prepare("UPDATE carts SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $quantity, $user_id, $product_id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true]);
        break;

    case 'remove':
        $data = json_decode(file_get_contents('php://input'), true);
        $product_id = $data['product_id'] ?? 0;

        $stmt = $conn->prepare("DELETE FROM carts WHERE user_id = ? AND product_id = ?");
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
