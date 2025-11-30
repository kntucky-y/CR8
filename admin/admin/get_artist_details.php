<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$artist_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$artist_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid Artist ID']);
    exit;
}

$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    http_response_code(500); // Server Error
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$response = [];

// 1. Get Artist Info and Total Revenue
$artist_sql = "
    SELECT
        a.artist_name,
        u.email,
        u.created_at as join_date,
        COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenue
    FROM artists a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN products p ON a.id = p.artist_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    WHERE a.id = ?
    GROUP BY a.id
";
$artist_stmt = $conn->prepare($artist_sql);
$artist_stmt->bind_param("i", $artist_id);
$artist_stmt->execute();
$artist_result = $artist_stmt->get_result();
$response['artist'] = $artist_result->fetch_assoc();
$artist_stmt->close();

if (!$response['artist']) {
    http_response_code(404); // Not Found
    echo json_encode(['error' => 'Artist not found']);
    exit;
}

// 2. Get Product Breakdown for the artist
$products_sql = "
    SELECT 
        p.product_name,
        p.image,
        COALESCE(SUM(oi.quantity), 0) as units_sold,
        COALESCE(SUM(oi.price * oi.quantity), 0) as product_revenue
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    WHERE p.artist_id = ?
    GROUP BY p.id
    ORDER BY product_revenue DESC
";
$products_stmt = $conn->prepare($products_sql);
$products_stmt->bind_param("i", $artist_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$response['products'] = $products_result->fetch_all(MYSQLI_ASSOC);
$products_stmt->close();

$conn->close();

echo json_encode($response);
