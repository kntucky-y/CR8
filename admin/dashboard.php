<?php
session_start();

// Handle logout via GET parameter
if (isset($_GET['logout'])) {
    if (isset($_SESSION['admin_id'])) {
        $conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
        if (!$conn->connect_error) {
            $stmt = $conn->prepare("UPDATE admins SET last_signed_out = NOW() WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['admin_id']);
            $stmt->execute();
            $stmt->close();
            $conn->close();
        }
    }
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Auto logout after 10 minutes of inactivity
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

$current_page = 'dashboard';
$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- DATA FETCHING FOR DASHBOARD ---

// -- CARD 1: Artist Applications --
$app_count = $conn->query("SELECT COUNT(*) FROM artist_applications")->fetch_row()[0] ?? 0;
$unread_count = $conn->query("SELECT COUNT(*) FROM artist_applications WHERE status='unread'")->fetch_row()[0] ?? 0;

// -- CARD 2: Sales Overview --
// MODIFIED: Only sums revenue from completed orders.
$sales_total_query = "
    SELECT SUM(oi.price * oi.quantity) 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
";
$sales_total = $conn->query($sales_total_query)->fetch_row()[0] ?? 0;

// -- CARD 3: Top Selling Artist --
// MODIFIED: Calculation is now based on completed orders.
$top_seller_query = "
    SELECT a.artist_name, SUM(oi.price * oi.quantity) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN artists a ON p.artist_id = a.id
    WHERE (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
    GROUP BY a.id
    ORDER BY total_revenue DESC
    LIMIT 1";
$top_seller_result = $conn->query($top_seller_query);
$top_seller = $top_seller_result->fetch_assoc();

// -- CARD 4: Top Selling Product --
// MODIFIED: Calculation is now based on completed orders.
$top_product_query = "
    SELECT p.product_name, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 1";
$top_product_result = $conn->query($top_product_query);
$top_product = $top_product_result->fetch_assoc();


// -- LIST: Latest Individual Completed Sales --
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
    LIMIT 5";
$latest_sales_result = $conn->query($latest_sales_query);


// -- CHART DATA & FILTERS --
$artists_for_filter = $conn->query("SELECT id, artist_name FROM artists ORDER BY artist_name ASC");

$filter_artist_id_raw = $_GET['artist_id'] ?? 'all';
$filter_period = $_GET['period'] ?? 'month';

// MODIFIED: The base query now only includes completed orders.
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
// MODIFIED: The base WHERE clause now filters for 'Completed' status.
$where_clause = "(SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'";

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
        $chart_data[] = $row['revenue'];
    }
}

// Fetch all messages
$messages = [];
$result = $conn->query("SELECT id, name, email, message, created_at, status FROM messages ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
}

// Count unread messages
$unread_messages_count = 0;
$result = $conn->query("SELECT COUNT(*) AS unread_count FROM messages WHERE status = 'Unread'");
if ($result) {
    $row = $result->fetch_assoc();
    $unread_messages_count = $row['unread_count'];
}

// Count artist applications
$pending_apps_count = 0;
$result = $conn->query("SELECT COUNT(*) AS pending_count FROM artist_applications");
if ($result) {
    $row = $result->fetch_assoc();
    $pending_apps_count = $row['pending_count'];
}

// Count pending orders
$pending_orders_count = 0;
$result = $conn->query("SELECT COUNT(*) AS pending_orders FROM delivery WHERE status = 'For Review'");
if ($result) {
    $row = $result->fetch_assoc();
    $pending_orders_count = $row['pending_orders'];
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | CR8 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }

        .message-active {
            background-color: #f3e8ff;
            /* Light purple for active item */
            border-right: 4px solid #7A1CAC;
            /* Darker purple indicator */
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex flex-col md:flex-row">
    <aside class="sticky top-0 h-screen z-40 w-64 bg-white border-r flex-shrink-0 hidden md:flex flex-col justify-between">
        <div>
            <div class="flex items-center gap-2 px-6 py-6 border-b">
                <img src="../img/cr8-logo.png" alt="Logo" class="w-10 h-10 rounded-full">
                <span class="font-bold text-xl text-purple-800">CR8 Cebu</span>
            </div>
            <nav class="flex flex-col gap-1 mt-6 px-2">
                <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'dashboard') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    Dashboard
                </a>
                <a href="artist_applications.php" class="flex items-center justify-between gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'artist_applications') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Artist Applications
                    </div>
                    <?php if ($pending_apps_count > 0): ?>
                        <span class="bg-purple-500 text-white text-xs px-2 py-0.5 rounded-full"><?= $pending_apps_count ?></span>
                    <?php endif; ?>
                </a>

                <a href="inbox.php" class="flex items-center justify-between gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'inbox') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                        </svg>
                        Inbox
                    </div>
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="bg-purple-500 text-white text-xs px-2 py-0.5 rounded-full"><?= $unread_messages_count ?></span>
                    <?php endif; ?>
                </a>

                <a href="artists.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'artists') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    All Artists
                </a>
                <a href="customers.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'customers') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                    </svg>
                    All Customers
                </a>
                <a href="orders.php" class="flex items-center justify-between gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'orders') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.658-.463 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                        </svg>
                        Orders
                    </div>
                    <?php if ($pending_orders_count > 0): ?>
                        <span class="bg-yellow-500 text-white text-xs px-2 py-0.5 rounded-full"><?= $pending_orders_count ?></span>
                    <?php endif; ?>
                </a>

                <a href="sales.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'sales') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    Sales
                </a>
                <a href="reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'reports') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                    </svg>
                    Reports
                </a>
                <a href="inventory.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'inventory') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.03 1.121 0 1.131.094 1.976 1.057 1.976 2.192V7.5m-9 7.5h9v-1.5h-9v1.5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 21h17.25a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H3.375A2.25 2.25 0 001.125 6.75v12A2.25 2.25 0 003.375 21z" />
                    </svg>
                    Inventory
                </a>
                <?php if (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] == 1): ?>
                    <a href="admin_management.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'admin_management') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.286zm0 13.036h.008v.008h-.008v-.008z" />
                        </svg>
                        Admin Management
                    </a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="mb-6 px-2">
            <a href="dashboard.php?logout=1" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold bg-red-50 text-red-700 hover:bg-red-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
                Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-h-screen">
        <header class="flex items-center justify-between px-8 py-4 border-b bg-white">
            <h1 class="font-bold text-2xl text-gray-800">Dashboard</h1>
        </header>

        <section class="p-4 md:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
                    <h3 class="text-gray-500 font-semibold">Total Completed Revenue</h3>
                    <p class="text-3xl font-bold text-green-600 mt-2">₱<?= number_format($sales_total, 2) ?></p>
                </div>
                <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
                    <h3 class="text-gray-500 font-semibold">Top Selling Artist</h3>
                    <p class="text-2xl font-bold text-purple-700 mt-2 truncate" title="<?= htmlspecialchars($top_seller['artist_name'] ?? 'N/A') ?>"><?= htmlspecialchars($top_seller['artist_name'] ?? 'N/A') ?></p>
                    <p class="text-sm text-gray-400">₱<?= number_format($top_seller['total_revenue'] ?? 0, 2) ?> in sales</p>
                </div>
                <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
                    <h3 class="text-gray-500 font-semibold">Top Selling Product</h3>
                    <p class="text-2xl font-bold text-blue-600 mt-2 truncate" title="<?= htmlspecialchars($top_product['product_name'] ?? 'N/A') ?>"><?= htmlspecialchars($top_product['product_name'] ?? 'N/A') ?></p>
                    <p class="text-sm text-gray-400"><?= number_format($top_product['total_sold'] ?? 0) ?> units sold</p>
                </div>
                <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
                    <h3 class="text-gray-500 font-semibold">Artist Applications</h3>
                    <p class="text-3xl font-bold text-yellow-600 mt-2"><?= $app_count ?></p>
                    <p class="text-sm text-gray-400">Unread: <span class="font-bold"><?= $unread_count ?></span></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 bg-white rounded-xl shadow p-6">
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-800">Completed Revenue Overview</h2>
                        <form id="filterForm" method="GET" class="flex items-center gap-2 mt-4 sm:mt-0">
                            <select name="artist_id" class="px-3 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm focus:outline-purple-400">
                                <option value="all" <?= ($filter_artist_id_raw == 'all') ? 'selected' : '' ?>>All Artists</option>
                                <?php if ($artists_for_filter) {
                                    $artists_for_filter->data_seek(0);
                                    while ($artist = $artists_for_filter->fetch_assoc()): ?>
                                        <option value="<?= $artist['id'] ?>" <?= ($filter_artist_id_raw == $artist['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($artist['artist_name']) ?>
                                        </option>
                                <?php endwhile;
                                } ?>
                            </select>
                            <select name="period" class="px-3 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm focus:outline-purple-400">
                                <option value="week" <?= ($filter_period == 'week') ? 'selected' : '' ?>>This Week</option>
                                <option value="month" <?= ($filter_period == 'month') ? 'selected' : '' ?>>This Month</option>
                                <option value="year" <?= ($filter_period == 'year') ? 'selected' : '' ?>>This Year</option>
                            </select>
                            <button type="submit" class="px-4 py-2 bg-purple-600 text-white font-semibold rounded-md text-sm hover:bg-purple-700">Apply</button>
                        </form>
                    </div>
                    <canvas id="salesChart"></canvas>
                </div>
                <div class="bg-white rounded-xl shadow p-6 overflow-x-auto">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Latest Completed Sales</h2>
                    <ul class="space-y-3">
                        <?php if ($latest_sales_result && $latest_sales_result->num_rows > 0): ?>
                            <?php while ($sale = $latest_sales_result->fetch_assoc()): ?>
                                <li class="flex items-center justify-between py-2 border-b last:border-b-0 min-w-0">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-700 text-sm truncate" title="<?= htmlspecialchars($sale['product_name']) ?>">
                                            <?= htmlspecialchars($sale['product_name']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500 truncate">
                                            by <?= htmlspecialchars($sale['artist_name']) ?>
                                        </p>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="font-bold text-green-600 text-sm">₱<?= number_format($sale['sale_total'], 2) ?></p>
                                        <p class="text-xs text-gray-400"><?= date('M d', strtotime($sale['created_at'])) ?></p>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="text-gray-400 text-sm">No completed sales to display.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </section>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const ctx = document.getElementById('salesChart').getContext('2d');
            let gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(124, 58, 237, 0.4)');
            gradient.addColorStop(1, 'rgba(124, 58, 237, 0)');
            const salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'Revenue',
                        data: <?= json_encode($chart_data) ?>,
                        backgroundColor: gradient,
                        borderColor: '#7c3aed',
                        borderWidth: 2,
                        pointBackgroundColor: '#7c3aed',
                        pointRadius: 4,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => '₱' + value.toLocaleString()
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.dataset.label || ''}: ₱${context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>