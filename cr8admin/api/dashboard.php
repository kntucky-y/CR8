<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    sendError('Unauthorized', 401);
}

// Update last activity
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset();
    session_destroy();
    sendError('Session expired', 401);
}
$_SESSION['LAST_ACTIVITY'] = time();

$conn = getDbConnection();

// Get dashboard data
$response = [];

// Artist Applications (only pending, not archived)
$app_count = $conn->query("SELECT COUNT(*) as count FROM artist_applications WHERE status NOT IN ('Approved', 'Rejected') AND COALESCE(is_archived, 0) = 0")->fetch_assoc()['count'];
$unread_count = $conn->query("SELECT COUNT(*) as count FROM artist_applications WHERE status='Unread' AND COALESCE(is_archived, 0) = 0")->fetch_assoc()['count'];

// Sales Total (only completed orders)
$sales_total_query = "
    SELECT SUM(oi.price * oi.quantity) as total
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
";
$sales_total = $conn->query($sales_total_query)->fetch_assoc()['total'] ?? 0;

// Top Selling Artist (completed orders only)
$top_seller_query = "
    SELECT a.artist_name, SUM(oi.price * oi.quantity) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN artists a ON p.artist_id = a.id
    WHERE (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
    GROUP BY a.id
    ORDER BY total_revenue DESC
    LIMIT 1
";
$top_seller_result = $conn->query($top_seller_query);
$top_seller = $top_seller_result->fetch_assoc();

// Top Selling Product (completed orders only)
$top_product_query = "
    SELECT p.product_name, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 1
";
$top_product_result = $conn->query($top_product_query);
$top_product = $top_product_result->fetch_assoc();

// Latest Individual Completed Sales
$latest_sales_query = "
    SELECT
        a.artist_name,
        p.product_name,
        (oi.price * oi.quantity) as sale_total,
        o.created_at
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN artists a ON p.artist_id = a.id
    JOIN orders o ON oi.order_id = o.id
    WHERE (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
    ORDER BY o.created_at DESC
    LIMIT 5
";
$latest_sales_result = $conn->query($latest_sales_query);
$latest_sales = [];
while ($row = $latest_sales_result->fetch_assoc()) {
    $latest_sales[] = $row;
}

// Get filter parameters for chart
$filter_artist_id = $_GET['artist_id'] ?? 'all';
$filter_period = $_GET['period'] ?? 'month';

// Get all artists for filter
$artists = [];
$artists_result = $conn->query("SELECT id, artist_name FROM artists ORDER BY artist_name ASC");
while ($row = $artists_result->fetch_assoc()) {
    $artists[] = $row;
}

// Build chart query
$join_clause = "";
$where_clause = "1=1";
switch ($filter_period) {
    case 'week':
        $date_format = "DATE_FORMAT(o.created_at, '%Y-%m-%d')";
        $where_clause .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        break;
    case 'year':
        $date_format = "DATE_FORMAT(o.created_at, '%Y-%m')";
        $where_clause .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        break;
    default:
        $date_format = "DATE_FORMAT(o.created_at, '%Y-%m-%d')";
        $where_clause .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        break;
}

if ($filter_artist_id !== 'all') {
    $artist_id = (int)$filter_artist_id;
    $join_clause = "JOIN products p ON oi.product_id = p.id";
    $where_clause .= " AND p.artist_id = " . $artist_id;
}

$chart_query = "
    SELECT $date_format AS label, SUM(oi.price * oi.quantity) AS revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    $join_clause
    WHERE $where_clause 
      AND (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
    GROUP BY label
    ORDER BY o.created_at ASC
";
$chart_result = $conn->query($chart_query);
$chart_data = [];
while ($row = $chart_result->fetch_assoc()) {
    $chart_data[] = $row;
}

$response = [
    'cards' => [
        'app_count' => $app_count,
        'unread_count' => $unread_count,
        'sales_total' => $sales_total,
        'top_seller' => $top_seller,
        'top_product' => $top_product
    ],
    'latest_sales' => $latest_sales,
    'chart_data' => $chart_data,
    'artists' => $artists
];

$conn->close();
sendResponse($response);
