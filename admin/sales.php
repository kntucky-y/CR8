<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}
$current_page = 'sales';

$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- Get filter and sort values from the URL ---
$filter_artist_id = $_GET['artist_id'] ?? 'all';
$sort_order = $_GET['sort'] ?? 'revenue_desc';

// --- Get data for the Artist card ---
$artist_card_label = "Top Artist";
if ($filter_artist_id !== 'all') {
    // If a specific artist is filtered, get their total sales
    $artist_card_label = "Artist Sales";
    $artist_card_sql = "
        SELECT a.artist_name, SUM(oi.price * oi.quantity) as total_revenue
        FROM artists a
        LEFT JOIN products p ON a.id = p.artist_id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE a.id = ?
        GROUP BY a.id";
    $artist_card_stmt = $conn->prepare($artist_card_sql);
    $artist_card_stmt->bind_param("i", $filter_artist_id);
    $artist_card_stmt->execute();
    $artist_card_result = $artist_card_stmt->get_result();
    $artist_card_data = $artist_card_result ? $artist_card_result->fetch_assoc() : null;
    $artist_card_stmt->close();
} else {
    // If showing all, get the top artist overall
    $artist_card_sql = "
        SELECT a.artist_name, SUM(oi.price * oi.quantity) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN artists a ON p.artist_id = a.id
        GROUP BY a.id
        ORDER BY total_revenue DESC
        LIMIT 1";
    $artist_card_result = $conn->query($artist_card_sql);
    $artist_card_data = $artist_card_result ? $artist_card_result->fetch_assoc() : null;
}


// --- Fetch all artists for the filter dropdown ---
$all_artists_result = $conn->query("SELECT id, artist_name FROM artists ORDER BY artist_name ASC");


// --- Build the main query for the product list ---
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
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN variants v ON oi.variant_id = v.id
    LEFT JOIN artists a ON p.artist_id = a.id
";

$where_clauses = [];
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
    <link rel="icon" href="../img/favicon.png" type="image/png">
    <style>
        body { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex">
<aside class="sticky top-0 h-screen z-40 w-64 bg-white border-r flex-shrink-0 hidden md:flex flex-col justify-between">
        <div>
            <div class="flex items-center gap-2 px-6 py-6 border-b">
                <img src="../img/cr8-logo.png" alt="Logo" class="w-10 h-10 rounded-full">
                <span class="font-bold text-xl text-purple-800">CR8 Cebu</span>
            </div>
            <!-- *** FIX APPLIED: Restored the "Orders" link and corrected all conditional classes *** -->
            <nav class="flex flex-col gap-1 mt-6 px-2">
    <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'dashboard') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"></path><path d="M16 10l-4 4-4-4"></path></svg>
        Dashboard
    </a>
    <a href="artist_applications.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'artist_applications') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
        Artist Applications
    </a>
    <a href="inbox.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'inbox') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16h6m2 4H7a2 2 0 01-2-2V7a2 2 0 012-2h3.28a2 2 0 011.42.59l.3.3a2 2 0 001.42.59H17a2 2 0 012 2v11a2 2 0 01-2 2z"></path></svg>
        Inbox
    </a>
    <a href="artists.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'artists') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        All Artists
    </a>
    <a href="customers.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'customers') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21v-3a3 3 0 00-3-3H6a3 3 0 00-3 3v3"></path></svg>
        All Customers
    </a>
    <a href="orders.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'orders') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/></svg>
        Orders
    </a>
    <a href="sales.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'sales') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
        Sales
    </a>
    <a href="inventory.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'inventory') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4M4 7l8 4.5M12 11.5V21M20 7l-8 4.5"></path></svg>
        Inventory
    </a>
    <?php if (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] == 1): ?>
    <a href="admin_management.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'admin_management') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Admin Management
    </a>
    <?php endif; ?>
</nav>
        </div>
        <div class="mb-6 px-2">
            <a href="dashboard.php?logout=1" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold bg-red-50 text-red-700 hover:bg-red-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h4a2 2 0 012 2v1"></path></svg>
                Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-h-screen">
        <header class="flex items-center justify-between px-8 py-4 border-b bg-white">
             <h1 class="font-bold text-2xl text-gray-800">Sales Performance</h1>
        </header>

        <section class="p-4 md:p-8">
            <form method="GET" class="bg-white p-4 rounded-xl shadow mb-8 flex flex-col md:flex-row items-center gap-4">
                <div class="flex items-center gap-2">
                    <label for="artist_id" class="font-semibold text-gray-600">Artist:</label>
                    <select name="artist_id" id="artist_id" class="border-gray-300 rounded-md shadow-sm">
                        <option value="all">All Artists</option>
                        <?php while($artist = $all_artists_result->fetch_assoc()): ?>
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

            <div class="mb-8">
                <div class="bg-white rounded-xl shadow p-6 flex flex-col md:flex-row items-center gap-6">
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold text-gray-500 mb-1"><?= $artist_card_label ?></h2>
                        <div class="flex items-center gap-3">
                            <span class="text-2xl font-bold text-purple-700 truncate" title="<?= htmlspecialchars($artist_card_data['artist_name'] ?? 'N/A') ?>"><?= htmlspecialchars($artist_card_data['artist_name'] ?? 'N/A') ?></span>
                            <!-- *** FIX: Increased font size from text-sm to text-lg *** -->
                            <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full font-semibold text-lg shadow">
                                ₱<?= number_format($artist_card_data['total_revenue'] ?? 0, 2) ?> in sales
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
