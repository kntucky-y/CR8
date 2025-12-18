<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    sendError('Unauthorized', 401);
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset();
    session_destroy();
    sendError('Session expired', 401);
}
$_SESSION['LAST_ACTIVITY'] = time();

$conn = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Fetch sales data with filters
if ($method === 'GET') {
    $filter_artist_id = $_GET['artist_id'] ?? 'all';
    $sort_order = $_GET['sort'] ?? 'revenue_desc';

    // Get artist card data
    if ($filter_artist_id !== 'all') {
        $artist_card_sql = "
            SELECT a.artist_name, SUM(oi.price * oi.quantity) as total_revenue
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN products p ON oi.product_id = p.id
            JOIN artists a ON p.artist_id = a.id
            WHERE a.id = ? 
              AND (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
            GROUP BY a.id
        ";
        $stmt = $conn->prepare($artist_card_sql);
        $stmt->bind_param("i", $filter_artist_id);
        $stmt->execute();
        $artist_card_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $artist_card_sql = "
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
        $artist_card_data = $conn->query($artist_card_sql)->fetch_assoc();
    }

    // Get all artists for filter
    $artists = [];
    $artists_result = $conn->query("SELECT id, artist_name FROM artists ORDER BY artist_name ASC");
    while ($row = $artists_result->fetch_assoc()) {
        $artists[] = $row;
    }

    // Build products query
    $products_sql = "
        SELECT 
            p.product_name,
            a.artist_name, a.id as artist_id,
            COALESCE(v.variant_name, p.base_variant_name) as variant_name,
            COALESCE(v.image, p.image) as item_image,
            COALESCE(v.price, p.price) as item_price,
            SUM(oi.quantity) as total_sold,
            SUM(oi.price * oi.quantity) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN variants v ON oi.variant_id = v.id
        LEFT JOIN artists a ON p.artist_id = a.id
        WHERE (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
    ";

    if ($filter_artist_id !== 'all') {
        $products_sql .= " AND a.id = " . (int)$filter_artist_id;
    }

    $products_sql .= " GROUP BY p.product_name, a.artist_name, a.id, variant_name, item_image, item_price";

    // Add sorting
    switch ($sort_order) {
        case 'revenue_asc':
            $products_sql .= " ORDER BY total_revenue ASC";
            break;
        case 'sold_desc':
            $products_sql .= " ORDER BY total_sold DESC";
            break;
        case 'sold_asc':
            $products_sql .= " ORDER BY total_sold ASC";
            break;
        case 'revenue_desc':
        default:
            $products_sql .= " ORDER BY total_revenue DESC";
            break;
    }

    $products_result = $conn->query($products_sql);
    $products = [];
    while ($row = $products_result->fetch_assoc()) {
        $products[] = $row;
    }

    $conn->close();
    sendResponse([
        'artist_card_data' => $artist_card_data,
        'artists' => $artists,
        'products' => $products
    ]);
}

sendError('Invalid request', 400);
