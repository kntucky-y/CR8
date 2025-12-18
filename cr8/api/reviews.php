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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $order_id = intval($input['order_id'] ?? 0);
    $product_id = intval($input['product_id'] ?? 0);
    $rating = intval($input['rating'] ?? 0);
    $comments = trim($input['comments'] ?? '');
    
    if ($order_id <= 0 || $product_id <= 0 || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }
    
    // Verify order belongs to user and is completed
    $order_check = $conn->prepare("
        SELECT o.id
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.id = ? AND o.customer_id = ? AND oi.product_id = ?
        AND o.id IN (SELECT order_id FROM delivery WHERE status = 'Completed')
        LIMIT 1
    ");
    $order_check->bind_param('iii', $order_id, $user_id, $product_id);
    $order_check->execute();
    $order_result = $order_check->get_result();
    
    if ($order_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found or not completed']);
        exit();
    }
    
    $order_check->close();
    
    // Check if user already reviewed this product from this order
    $review_check = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ? AND order_id = ?");
    $review_check->bind_param('iii', $user_id, $product_id, $order_id);
    $review_check->execute();
    $review_result = $review_check->get_result();
    
    if ($review_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this product from this order']);
        exit();
    }
    $review_check->close();
    
    // Insert review
    $insert_review = $conn->prepare("INSERT INTO reviews (user_id, product_id, order_id, rating, comments, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $insert_review->bind_param('iiiis', $user_id, $product_id, $order_id, $rating, $comments);
    
    if ($insert_review->execute()) {
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit review: ' . $conn->error]);
    }
    
    $insert_review->close();
    $conn->close();
} elseif ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $review_id = intval($input['review_id'] ?? 0);
    $order_id = intval($input['order_id'] ?? 0);
    $rating = intval($input['rating'] ?? 0);
    $comments = trim($input['comments'] ?? '');
    
    if ($review_id <= 0 || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }
    
    // Verify review belongs to user
    $review_check = $conn->prepare("SELECT id FROM reviews WHERE id = ? AND user_id = ?");
    $review_check->bind_param('ii', $review_id, $user_id);
    $review_check->execute();
    $review_result = $review_check->get_result();
    
    if ($review_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Review not found or unauthorized']);
        exit();
    }
    $review_check->close();
    
    // Update review
    $update_review = $conn->prepare("UPDATE reviews SET rating = ?, comments = ? WHERE id = ?");
    $update_review->bind_param('isi', $rating, $comments, $review_id);
    
    if ($update_review->execute()) {
        echo json_encode(['success' => true, 'message' => 'Review updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update review: ' . $conn->error]);
    }
    
    $update_review->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
