<?php
require_once 'config.php';

/*
 * DATABASE MIGRATION REQUIRED:
 * Run this SQL to add variant support to carts table:
 * ALTER TABLE carts ADD COLUMN variant_id INT NULL DEFAULT NULL AFTER product_id;
 * ALTER TABLE carts ADD INDEX idx_variant (variant_id);
 */

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
            SELECT c.id, c.product_id, c.variant_id, c.quantity as cart_quantity, 
                   p.product_name, p.price, p.image, p.quantity as stock, a.artist_name,
                   v.variant_name, v.price as variant_price, v.quantity as variant_stock, v.image as variant_image
            FROM carts c
            JOIN products p ON c.product_id = p.id
            JOIN artists a ON p.artist_id = a.id
            LEFT JOIN variants v ON c.variant_id = v.id
            WHERE c.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $cart = [];
        
        while ($row = $result->fetch_assoc()) {
            // Use variant data if variant_id exists
            if ($row['variant_id']) {
                $row['price'] = (float)($row['variant_price'] ?? $row['price']);
                $row['stock'] = (int)($row['variant_stock'] ?? $row['stock']);
                $row['image'] = $row['variant_image'] ?? $row['image'];
                $row['product_name'] = $row['product_name'] . ($row['variant_name'] ? ' - ' . $row['variant_name'] : '');
            } else {
                $row['price'] = (float)$row['price'];
                $row['stock'] = (int)$row['stock'];
            }
            
            $row['quantity'] = (int)$row['cart_quantity']; // Quantity in cart
            $row['image_url'] = $row['image']; // Add image_url for compatibility
            $row['variant_id'] = $row['variant_id'] ? (int)$row['variant_id'] : null;
            
            unset($row['cart_quantity'], $row['variant_price'], $row['variant_stock'], $row['variant_image'], $row['variant_name']);
            $cart[] = $row;
        }
        
        echo json_encode(['success' => true, 'cart' => $cart]);
        $stmt->close();
        break;

    case 'add':
        $data = json_decode(file_get_contents('php://input'), true);
        $product_id = $data['product_id'] ?? 0;
        $quantity = $data['quantity'] ?? 1;
        $variant_id = $data['variant_id'] ?? null;

        // Get product stock - check variant if specified
        if ($variant_id) {
            $stock_stmt = $conn->prepare("SELECT quantity FROM variants WHERE id = ? AND product_id = ?");
            $stock_stmt->bind_param("ii", $variant_id, $product_id);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            
            if (!$stock_row = $stock_result->fetch_assoc()) {
                $stock_stmt->close();
                echo json_encode(['success' => false, 'message' => 'Variant not found']);
                break;
            }
        } else {
            $stock_stmt = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
            $stock_stmt->bind_param("i", $product_id);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            
            if (!$stock_row = $stock_result->fetch_assoc()) {
                $stock_stmt->close();
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                break;
            }
        }
        
        $available_stock = (int)$stock_row['quantity'];
        $stock_stmt->close();

        // Check if already in cart - need to check both product_id and variant_id
        if ($variant_id) {
            // Check for specific variant
            $stmt = $conn->prepare("SELECT id, quantity FROM carts WHERE user_id = ? AND product_id = ? AND variant_id = ?");
            $stmt->bind_param("iii", $user_id, $product_id, $variant_id);
        } else {
            // Check for product without variant (or with NULL variant_id)
            $stmt = $conn->prepare("SELECT id, quantity FROM carts WHERE user_id = ? AND product_id = ? AND variant_id IS NULL");
            $stmt->bind_param("ii", $user_id, $product_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Update quantity
            $new_quantity = $row['quantity'] + $quantity;
            
            // Check if new quantity exceeds stock
            if ($new_quantity > $available_stock) {
                $stmt->close();
                echo json_encode(['success' => false, 'message' => "Cannot add to cart. Only {$available_stock} items available in stock."]);
                break;
            }
            
            $update_stmt = $conn->prepare("UPDATE carts SET quantity = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $new_quantity, $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // Check if quantity exceeds stock
            if ($quantity > $available_stock) {
                $stmt->close();
                echo json_encode(['success' => false, 'message' => "Cannot add to cart. Only {$available_stock} items available in stock."]);
                break;
            }
            
            // Insert new - include variant_id if present
            if ($variant_id) {
                $insert_stmt = $conn->prepare("INSERT INTO carts (user_id, product_id, quantity, variant_id) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("iiii", $user_id, $product_id, $quantity, $variant_id);
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO carts (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
            }
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
        $variant_id = $data['variant_id'] ?? null;

        // Get product stock - check variant if specified
        if ($variant_id) {
            $stock_stmt = $conn->prepare("SELECT quantity FROM variants WHERE id = ? AND product_id = ?");
            $stock_stmt->bind_param("ii", $variant_id, $product_id);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            
            if (!$stock_row = $stock_result->fetch_assoc()) {
                $stock_stmt->close();
                echo json_encode(['success' => false, 'message' => 'Variant not found']);
                break;
            }
        } else {
            $stock_stmt = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
            $stock_stmt->bind_param("i", $product_id);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            
            if (!$stock_row = $stock_result->fetch_assoc()) {
                $stock_stmt->close();
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                break;
            }
        }
        
        $available_stock = (int)$stock_row['quantity'];
        $stock_stmt->close();
        
        // Check if quantity exceeds stock
        if ($quantity > $available_stock) {
            echo json_encode(['success' => false, 'message' => "Cannot update quantity. Only {$available_stock} items available in stock."]);
            break;
        }

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
