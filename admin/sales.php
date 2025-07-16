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
        <?php include '_sidebar.php'; // Using the reusable sidebar ?>
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