<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die('Unauthorized');
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset();
    session_destroy();
    http_response_code(401);
    die('Session expired');
}
$_SESSION['LAST_ACTIVITY'] = time();

$conn = getDbConnection();

$report_type = $_GET['type'] ?? '';
$artist_id = $_GET['artist_id'] ?? 'all';

if ($report_type === 'sales') {
    // Sales Report
    $sql = "
        SELECT 
            p.product_name,
            a.artist_name,
            COALESCE(v.variant_name, p.base_variant_name) as variant_name,
            COALESCE(v.price, p.price) as item_price,
            SUM(oi.quantity) as total_sold,
            SUM(oi.price * oi.quantity) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        JOIN artists a ON p.artist_id = a.id
        LEFT JOIN variants v ON oi.variant_id = v.id
        WHERE (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
    ";
    
    if ($artist_id !== 'all') {
        $sql .= " AND p.artist_id = " . intval($artist_id);
    }
    
    $sql .= "
        GROUP BY p.product_name, a.artist_name, variant_name, item_price
        ORDER BY total_revenue DESC
    ";
    
    $result = $conn->query($sql);
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'Product' => $row['product_name'],
            'Artist' => $row['artist_name'],
            'Variant' => $row['variant_name'],
            'Price (PHP)' => number_format($row['item_price'], 2),
            'Units Sold' => $row['total_sold'],
            'Total Revenue (PHP)' => number_format($row['total_revenue'], 2)
        ];
    }
    
    $filename = 'sales_report_' . date('Y-m-d') . '.csv';
    
} elseif ($report_type === 'inventory') {
    // Inventory Report
    $sql = "
        SELECT 
            p.product_name,
            a.artist_name,
            p.quantity as stock,
            p.is_active
        FROM products p
        JOIN artists a ON p.artist_id = a.id
        ORDER BY a.artist_name, p.product_name
    ";
    
    $result = $conn->query($sql);
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'Product' => $row['product_name'],
            'Artist' => $row['artist_name'],
            'Stock' => $row['stock'],
            'Status' => $row['is_active'] ? 'Active' : 'Inactive'
        ];
    }
    
    $filename = 'inventory_report_' . date('Y-m-d') . '.csv';
    
} else {
    http_response_code(400);
    die('Invalid report type');
}

$conn->close();

// Generate CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Write headers
if (!empty($data)) {
    fputcsv($output, array_keys($data[0]));
    
    // Write data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit();
