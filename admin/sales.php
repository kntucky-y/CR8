<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}
$current_page = 'sales';

// Auto logout after 10 minutes of inactivity
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- Get filter and sort values from the URL ---
$filter_artist_id = $_GET['artist_id'] ?? 'all';
$sort_order = $_GET['sort'] ?? 'revenue_desc';

// --- Get data for the Artist card ---
$artist_card_label = "Top Artist (Completed Sales)";
if ($filter_artist_id !== 'all') {
    // If a specific artist is filtered, get their total completed sales
    $artist_card_label = "Artist Sales (Completed)";
    // MODIFIED: This query now only counts sales from orders with a 'Completed' status.
    $artist_card_sql = "
        SELECT a.artist_name, SUM(oi.price * oi.quantity) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        JOIN artists a ON p.artist_id = a.id
        WHERE a.id = ? 
          AND (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
        GROUP BY a.id";
    $artist_card_stmt = $conn->prepare($artist_card_sql);
    $artist_card_stmt->bind_param("i", $filter_artist_id);
    $artist_card_stmt->execute();
    $artist_card_result = $artist_card_stmt->get_result();
    $artist_card_data = $artist_card_result ? $artist_card_result->fetch_assoc() : null;
    $artist_card_stmt->close();
} else {
    // If showing all, get the top artist overall from completed sales
    // MODIFIED: This query now only counts sales from orders with a 'Completed' status.
    $artist_card_sql = "
        SELECT a.artist_name, SUM(oi.price * oi.quantity) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        JOIN artists a ON p.artist_id = a.id
        WHERE (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
        GROUP BY a.id
        ORDER BY total_revenue DESC
        LIMIT 1";
    $artist_card_result = $conn->query($artist_card_sql);
    $artist_card_data = $artist_card_result ? $artist_card_result->fetch_assoc() : null;
}


// --- Fetch all artists for the filter dropdown ---
$all_artists_result = $conn->query("SELECT id, artist_name FROM artists ORDER BY artist_name ASC");


// --- Build the main query for the product list ---
// MODIFIED: This query now only sums items from orders with a 'Completed' status.
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
";

$where_clauses = ["(SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'"];
$params = [];
$types = '';

if ($filter_artist_id !== 'all') {
    $where_clauses[] = "a.id = ?";
    $params[] = $filter_artist_id;
    $types .= 'i';
}

if (!empty($where_clauses)) {
    $products_sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$products_sql .= " GROUP BY p.id, v.id, p.product_name, a.artist_name, item_image, item_price, variant_name";

$order_by_clause = match ($sort_order) {
    'revenue_asc' => ' ORDER BY total_revenue ASC',
    'units_desc' => ' ORDER BY total_sold DESC',
    'units_asc' => ' ORDER BY total_sold ASC',
    'price_desc' => ' ORDER BY item_price DESC',
    'price_asc' => ' ORDER BY item_price ASC',
    default => ' ORDER BY total_revenue DESC',
};
$products_sql .= $order_by_clause;

$products_stmt = $conn->prepare($products_sql);
if ($products_stmt && !empty($params)) {
    $products_stmt->bind_param($types, ...$params);
}
$products_stmt->execute();
$products_result = $products_stmt->get_result();

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
    <title>Sales | CR8 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .message-active {
            background-color: #f3e8ff; /* Light purple for active item */
            border-right: 4px solid #7A1CAC; /* Darker purple indicator */
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-row">
<aside class="w-64 bg-white border-r flex-shrink-0 flex flex-col justify-between sticky top-0 h-screen">
       <div>
            <div class="flex items-center gap-2 px-6 py-6 border-b">
                <img src="img/cr8-logo.png" alt="Logo" class="w-10 h-10 rounded-full">
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
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-h-screen">
        <div class="sticky top-0 z-30 bg-white">
            <header class="flex items-center justify-between px-8 py-4 border-b">
                <h1 class="font-bold text-2xl text-gray-800">Sales Performance</h1>
            </header>

            <div class="px-8 py-4 border-b">
                <form method="GET" class="bg-white p-4 rounded-xl shadow flex flex-row items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label for="artist_id" class="font-semibold text-gray-600">Artist:</label>
                        <select name="artist_id" id="artist_id" class="border-gray-300 rounded-md shadow-sm">
                            <option value="all">All Artists</option>
                            <?php 
                            // Reset pointer and loop through artists for the dropdown
                            $all_artists_result->data_seek(0); 
                            while($artist = $all_artists_result->fetch_assoc()): 
                            ?>
                                <option value="<?= $artist['id'] ?>" <?= ($filter_artist_id == $artist['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($artist['artist_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <label for="sort" class="font-semibold text-gray-600">Sort By:</label>
                        <select name="sort" id="sort" class="border-gray-300 rounded-md shadow-sm">
                            <option value="revenue_desc" <?= ($sort_order == 'revenue_desc') ? 'selected' : '' ?>>Highest Revenue</option>
                            <option value="revenue_asc" <?= ($sort_order == 'revenue_asc') ? 'selected' : '' ?>>Lowest Revenue</option>
                            <option value="units_desc" <?= ($sort_order == 'units_desc') ? 'selected' : '' ?>>Most Units Sold</option>
                            <option value="units_asc" <?= ($sort_order == 'units_asc') ? 'selected' : '' ?>>Fewest Units Sold</option>
                            <option value="price_desc" <?= ($sort_order == 'price_desc') ? 'selected' : '' ?>>Highest Price</option>
                            <option value="price_asc" <?= ($sort_order == 'price_asc') ? 'selected' : '' ?>>Lowest Price</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-purple-600 text-white font-semibold px-5 py-2 rounded-md hover:bg-purple-700">Apply</button>
                </form>
            </div>
        </div>

        <section class="p-4 md:p-8">
            <div class="mb-8">
                <div class="bg-white rounded-xl shadow p-6 flex flex-col md:flex-row items-center gap-6">
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold text-gray-500 mb-1"><?= $artist_card_label ?></h2>
                        <div class="flex items-center gap-3">
                            <span class="text-2xl font-bold text-purple-700 truncate" title="<?= htmlspecialchars($artist_card_data['artist_name'] ?? 'N/A') ?>"><?= htmlspecialchars($artist_card_data['artist_name'] ?? 'N/A') ?></span>
                            <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full font-semibold text-lg shadow">
                                ₱<?= number_format($artist_card_data['total_revenue'] ?? 0, 2) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php if ($products_result && $products_result->num_rows > 0): ?>
                    <?php while($item = $products_result->fetch_assoc()): ?>
                        <?php
                            $image_path = !empty($item['item_image'])
                                ? 'https://cr8.dcism.org/' . htmlspecialchars(str_replace('\\', '/', $item['item_image']))
                                : 'https://cr8.dcism.org/img/default-product.png';
                        ?>
                        <div class="bg-white rounded-xl shadow p-5 flex flex-col h-full">
                            <div class="flex items-start gap-4 mb-4">
                                <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="w-16 h-16 object-cover rounded-lg border flex-shrink-0" onerror="this.onerror=null;this.src='https://cr8.dcism.org/img/default-product.png';">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold text-gray-800 truncate" title="<?= htmlspecialchars($item['product_name']) ?>">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </h3>
                                    <p class="text-sm font-semibold text-purple-600 truncate" title="<?= htmlspecialchars($item['variant_name']) ?>">
                                        <?= htmlspecialchars($item['variant_name']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500 truncate" title="by <?= htmlspecialchars($item['artist_name'] ?? 'Unknown') ?>">
                                        by <?= htmlspecialchars($item['artist_name'] ?? 'Unknown') ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex-grow"></div>
                            <div class="flex flex-col gap-2 mt-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 text-sm">Units Sold</span>
                                    <span class="font-bold text-purple-700 text-lg"><?= number_format($item['total_sold']) ?></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 text-sm">Revenue</span>
                                    <span class="font-bold text-green-600 text-lg">₱<?= number_format($item['total_revenue'], 2) ?></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 text-sm">Price</span>
                                    <span class="font-semibold text-gray-700 text-lg">₱<?= number_format($item['item_price'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full text-center text-gray-400 py-12">No product sales data found for the selected filters.</div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>