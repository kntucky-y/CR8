<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Order ID']);
    exit;
}

$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$response = [];

// 1. Get main order details
$order_sql = "
    SELECT o.*, u.first_name, u.last_name, u.email, d.status as delivery_status 
    FROM orders o
    JOIN users u ON o.customer_id = u.id
    LEFT JOIN delivery d ON o.id = d.order_id
    WHERE o.id = ?
";
$order_stmt = $conn->prepare($order_sql);
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$response['details'] = $order_stmt->get_result()->fetch_assoc();
$order_stmt->close();

if (!$response['details']) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

// 2. Get all items in the order
$items_sql = "
    SELECT 
        oi.quantity, 
        oi.price, 
        p.product_name, 
        COALESCE(v.variant_name, p.base_variant_name) as variant_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN variants v ON oi.variant_id = v.id
    WHERE oi.order_id = ?
";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$response['items'] = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

$conn->close();
echo json_encode($response);
