<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: https://cr8.dcism.org');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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

// Check if user is logged in and is an artist
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'artist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get artist_id from user_id
$artist_stmt = $conn->prepare("SELECT id FROM artists WHERE user_id = ?");
$artist_stmt->bind_param('i', $user_id);
$artist_stmt->execute();
$artist_result = $artist_stmt->get_result();

if ($artist_row = $artist_result->fetch_assoc()) {
    $artist_id = $artist_row['id'];
} else {
    echo json_encode(['success' => false, 'message' => 'Artist profile not found']);
    exit();
}
$artist_stmt->close();

// Get product ID
$product_id = intval($_POST['product_id'] ?? 0);

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit();
}

// Verify this product belongs to this artist
$check_stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND artist_id = ?");
$check_stmt->bind_param('ii', $product_id, $artist_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found or unauthorized']);
    exit();
}
$check_stmt->close();

// Check if product has any orders that are not completed or cancelled
$order_check = $conn->prepare("
    SELECT COUNT(*) as pending_orders 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.product_id = ? 
    AND o.id NOT IN (
        SELECT order_id 
        FROM delivery 
        WHERE status IN ('Completed', 'Cancelled')
    )
");
$order_check->bind_param('i', $product_id);
$order_check->execute();
$order_result = $order_check->get_result();
$order_row = $order_result->fetch_assoc();
$order_check->close();

if ($order_row['pending_orders'] > 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Cannot delete product with pending or in-progress orders. Only products with all orders completed or cancelled can be deleted.'
    ]);
    exit();
}

// Start transaction for safe deletion
$conn->begin_transaction();

try {
    // Delete order_items for cancelled/completed orders only
    $delete_order_items = $conn->prepare("
        DELETE FROM order_items 
        WHERE product_id = ? 
        AND order_id IN (
            SELECT order_id 
            FROM delivery 
            WHERE status IN ('Completed', 'Cancelled')
        )
    ");
    $delete_order_items->bind_param('i', $product_id);
    $delete_order_items->execute();
    $delete_order_items->close();

    // Delete reviews first
    $delete_reviews = $conn->prepare("DELETE FROM reviews WHERE product_id = ?");
    $delete_reviews->bind_param('i', $product_id);
    $delete_reviews->execute();
    $delete_reviews->close();

    // Delete from wishlist
    $delete_wishlist = $conn->prepare("DELETE FROM wishlist WHERE product_id = ?");
    $delete_wishlist->bind_param('i', $product_id);
    $delete_wishlist->execute();
    $delete_wishlist->close();

    // Delete from cart
    $delete_cart = $conn->prepare("DELETE FROM carts WHERE product_id = ?");
    $delete_cart->bind_param('i', $product_id);
    $delete_cart->execute();
    $delete_cart->close();

    // Delete variants
    $delete_variants = $conn->prepare("DELETE FROM variants WHERE product_id = ?");
    $delete_variants->bind_param('i', $product_id);
    $delete_variants->execute();
    $delete_variants->close();

    // Delete product
    $delete_product = $conn->prepare("DELETE FROM products WHERE id = ?");
    $delete_product->bind_param('i', $product_id);
    $delete_product->execute();
    $delete_product->close();

    // Commit transaction
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error deleting product: ' . $e->getMessage()]);
}

$conn->close();
