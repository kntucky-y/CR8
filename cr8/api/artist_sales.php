<?php
require_once 'config.php';

header('Access-Control-Allow-Origin: https://cr8.dcism.org');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
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
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check the actual role from database
$role_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
$role_check->bind_param('i', $user_id);
$role_check->execute();
$role_result = $role_check->get_result();
$db_role = $role_result->fetch_assoc();
$role_check->close();

if (!$db_role || $db_role['role'] !== 'artist') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Not an artist']);
    exit;
}

// Get artist_id from user_id
$artist_stmt = $conn->prepare("SELECT id FROM artists WHERE user_id = ? AND is_archived = 0");
$artist_stmt->bind_param('i', $user_id);
$artist_stmt->execute();
$artist_result = $artist_stmt->get_result();

if (!($artist_row = $artist_result->fetch_assoc())) {
    echo json_encode(['success' => false, 'message' => 'Artist profile not found']);
    exit;
}
$artist_id = $artist_row['id'];
$artist_stmt->close();

// Get sales statistics
$sales_sql = "
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(oi.quantity * oi.price), 0) as total_sales,
        COALESCE(SUM(oi.quantity), 0) as products_sold
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE p.artist_id = ? 
      AND (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
";

$sales_stmt = $conn->prepare($sales_sql);
$sales_stmt->bind_param('i', $artist_id);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();
$sales_data = $sales_result->fetch_assoc();
$sales_stmt->close();

// Get recent orders with details
$orders_sql = "
    SELECT 
        o.id,
        o.order_no,
        o.created_at,
        o.total,
        CONCAT(u.first_name, ' ', u.last_name) as customer_name,
        (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) as order_status,
        GROUP_CONCAT(CONCAT(p.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ') as items,
        SUM(oi.quantity * oi.price) as artist_earnings
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    LEFT JOIN users u ON o.customer_id = u.id
    WHERE p.artist_id = ?
      AND (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 20
";

$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param('i', $artist_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

$recent_orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $recent_orders[] = $row;
}
$orders_stmt->close();

// Get top selling products
$products_sql = "
    SELECT 
        p.id,
        p.product_name,
        p.image,
        SUM(oi.quantity) as units_sold,
        SUM(oi.quantity * oi.price) as revenue
    FROM products p
    INNER JOIN order_items oi ON p.id = oi.product_id
    INNER JOIN orders o ON oi.order_id = o.id
    WHERE p.artist_id = ?
      AND (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
    GROUP BY p.id
    ORDER BY units_sold DESC
    LIMIT 5
";

$products_stmt = $conn->prepare($products_sql);
$products_stmt->bind_param('i', $artist_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();

$top_products = [];
while ($row = $products_result->fetch_assoc()) {
    $top_products[] = $row;
}
$products_stmt->close();

$conn->close();

echo json_encode([
    'success' => true,
    'data' => [
        'total_sales' => (float)$sales_data['total_sales'],
        'total_orders' => (int)$sales_data['total_orders'],
        'products_sold' => (int)$sales_data['products_sold'],
        'recent_orders' => $recent_orders,
        'top_products' => $top_products
    ]
]);
