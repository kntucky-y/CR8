<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$customer_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$customer_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid Customer ID']);
    exit;
}

$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    http_response_code(500); // Server Error
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$response = [];

// 1. Get Customer Info
$cust_sql = "SELECT first_name, last_name, email, created_at as join_date FROM users WHERE id = ?";
$cust_stmt = $conn->prepare($cust_sql);
$cust_stmt->bind_param("i", $customer_id);
$cust_stmt->execute();
$cust_result = $cust_stmt->get_result();
$response['customer'] = $cust_result->fetch_assoc();
$cust_stmt->close();

if (!$response['customer']) {
    http_response_code(404); // Not Found
    echo json_encode(['error' => 'Customer not found']);
    exit;
}

// 2. Get Customer's Orders
$orders_sql = "SELECT order_no, total, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC";
$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("i", $customer_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();
$response['orders'] = $orders_result->fetch_all(MYSQLI_ASSOC);
$orders_stmt->close();

// 3. Get Customer's Reviews
$reviews_sql = "
    SELECT r.rating, r.comments, r.created_at, p.product_name
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
";
$reviews_stmt = $conn->prepare($reviews_sql);
$reviews_stmt->bind_param("i", $customer_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
$response['reviews'] = $reviews_result->fetch_all(MYSQLI_ASSOC);
$reviews_stmt->close();


$conn->close();
echo json_encode($response);
