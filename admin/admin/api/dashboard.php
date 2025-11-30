
<?php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");

session_start();
require_once __DIR__ . '/../config.php';

// Require login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

// Auto logout after 10 minutes of inactivity
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset();
    session_destroy();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'session_expired']);
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// DB connection
$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'db_connection_failed']);
    exit;
}

// ---------- CARD DATA ----------

// Artist applications
$app_count = $conn->query("SELECT COUNT(*) FROM artist_applications")->fetch_row()[0] ?? 0;
$unread_count = $conn->query("SELECT COUNT(*) FROM artist_applications WHERE status='unread'")->fetch_row()[0] ?? 0;

// Total sales revenue
$sales_total = $conn->query("SELECT SUM(oi.price * oi.quantity) FROM order_items oi")->fetch_row()[0] ?? 0;

// Top selling artist
$top_seller_query = "
    SELECT a.artist_name, SUM(oi.price * oi.quantity) as total_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN artists a ON p.artist_id = a.id
    GROUP BY a.id
    ORDER BY total_revenue DESC
    LIMIT 1
";
$top_seller_result = $conn->query($top_seller_query);
$top_seller = $top_seller_result ? $top_seller_result->fetch_assoc() : null;

// Top selling product
$top_product_query = "
    SELECT p.product_name, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 1
";
$top_product_result = $conn->query($top_product_query);
$top_product = $top_product_result ? $top_product_result->fetch_assoc() : null;

// ---------- LATEST SALES LIST ----------

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
    ORDER BY o.created_at DESC
    LIMIT 5
";
$latest_sales_result = $conn->query($latest_sales_query);
$latest_sales = [];
if ($latest_sales_result) {
    while ($row = $latest_sales_result->fetch_assoc()) {
        $latest_sales[] = $row;
    }
}

// ---------- FILTER OPTIONS ----------

$artists_for_filter = $conn->query("SELECT id, artist_name FROM artists ORDER BY artist_name ASC");
$artists = [];
if ($artists_for_filter) {
    while ($artist = $artists_for_filter->fetch_assoc()) {
        $artists[] = $artist;
    }
}

// ---------- CHART DATA ----------

// Filters from query params
$filter_artist_id_raw = $_GET['artist_id'] ?? 'all';
$filter_period = $_GET['period'] ?? 'month';

$chart_query_base = "
    SELECT %s AS label, SUM(oi.price * oi.quantity) AS revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    %s
    WHERE %s
    GROUP BY label
    ORDER BY o.created_at ASC
";

$join_clause = "";
$where_clause = "1=1";

// Time period filter
switch ($filter_period) {
    case 'week':
        $date_format = "DATE_FORMAT(o.created_at, '%Y-%m-%d')";
        $where_clause .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        break;
    case 'year':
        $date_format = "DATE_FORMAT(o.created_at, '%Y-%m')";
        $where_clause .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        break;
    case 'month':
    default:
        $date_format = "DATE_FORMAT(o.created_at, '%Y-%m-%d')";
        $where_clause .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        break;
}

// Artist filter
if ($filter_artist_id_raw !== 'all') {
    $filter_artist_id = (int)$filter_artist_id_raw;
    $join_clause = "JOIN products p ON oi.product_id = p.id";
    $where_clause .= " AND p.artist_id = " . $filter_artist_id;
}

$chart_query = sprintf($chart_query_base, $date_format, $join_clause, $where_clause);
$chart_result = $conn->query($chart_query);

$chart_labels = [];
$chart_data = [];
if ($chart_result) {
    while ($row = $chart_result->fetch_assoc()) {
        $chart_labels[] = $row['label'];
        $chart_data[] = (float)$row['revenue'];
    }
}

$conn->close();

// ---------- OUTPUT JSON ----------


header('Content-Type: application/json');
echo json_encode([
    'cards' => [
        'sales_total'   => (float)$sales_total,
        'top_seller'    => $top_seller,
        'top_product'   => $top_product,
        'app_count'     => (int)$app_count,
        'unread_count'  => (int)$unread_count,
    ],
    'latest_sales' => $latest_sales,
    'filters' => [
        'artists' => $artists,
        'selected_artist_id' => $filter_artist_id_raw,
        'period' => $filter_period,
    ],
    'chart' => [
        'labels' => $chart_labels,
        'data'   => $chart_data,
    ],
]);
