<?php
session_start();
$current_page = 'dashboard';

// --- (Keep all your existing PHP logic for logout, inactivity, and data fetching here) ---
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

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset(); session_destroy(); header("Location: index.php"); exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) { die("Database connection failed: " . $conn->connect_error); }
$app_count = $conn->query("SELECT COUNT(*) FROM artist_applications")->fetch_row()[0] ?? 0;
$unread_count = $conn->query("SELECT COUNT(*) FROM artist_applications WHERE status='unread'")->fetch_row()[0] ?? 0;
$sales_total = $conn->query("SELECT SUM(oi.price * oi.quantity) FROM order_items oi")->fetch_row()[0] ?? 0;
$top_seller_query = "SELECT a.artist_name, SUM(oi.price * oi.quantity) as total_revenue FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN artists a ON p.artist_id = a.id GROUP BY a.id ORDER BY total_revenue DESC LIMIT 1";
$top_seller_result = $conn->query($top_seller_query);
$top_seller = $top_seller_result->fetch_assoc();
$top_product_query = "SELECT p.product_name, SUM(oi.quantity) as total_sold FROM order_items oi JOIN products p ON oi.product_id = p.id GROUP BY p.id ORDER BY total_sold DESC LIMIT 1";
$top_product_result = $conn->query($top_product_query);
$top_product = $top_product_result->fetch_assoc();
$latest_sales_query = "SELECT a.artist_name, p.product_name, (oi.price * oi.quantity) as sale_total, o.created_at FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN artists a ON p.artist_id = a.id JOIN orders o ON oi.order_id = o.id ORDER BY o.created_at DESC LIMIT 5";
$latest_sales_result = $conn->query($latest_sales_query);
$artists_for_filter = $conn->query("SELECT id, artist_name FROM artists ORDER BY artist_name ASC");
$filter_artist_id_raw = $_GET['artist_id'] ?? 'all';
$filter_period = $_GET['period'] ?? 'month';
$chart_query_base = "SELECT %s AS label, SUM(oi.price * oi.quantity) AS revenue FROM order_items oi JOIN orders o ON oi.order_id = o.id %s WHERE %s GROUP BY label ORDER BY o.created_at ASC";
$join_clause = ""; $where_clause = "1=1"; 
switch ($filter_period) {
    case 'week': $date_format = "DATE_FORMAT(o.created_at, '%Y-%m-%d')"; $where_clause .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)"; break;
    case 'year': $date_format = "DATE_FORMAT(o.created_at, '%Y-%m')"; $where_clause .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)"; break;
    default: $date_format = "DATE_FORMAT(o.created_at, '%Y-%m-%d')"; $where_clause .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)"; break;
}
if ($filter_artist_id_raw !== 'all') { $filter_artist_id = (int)$filter_artist_id_raw; $join_clause = "JOIN products p ON oi.product_id = p.id"; $where_clause .= " AND p.artist_id = " . $filter_artist_id; }
$chart_query = sprintf($chart_query_base, $date_format, $join_clause, $where_clause);
$chart_result = $conn->query($chart_query);
$chart_labels = []; $chart_data = [];
if($chart_result){ while ($row = $chart_result->fetch_assoc()) { $chart_labels[] = $row['label']; $chart_data[] = $row['revenue']; } }
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CR8 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <style>
        body { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col md:flex-row">
    
    <?php include 'navbar.php'; ?>

    <main class="flex-1 flex flex-col min-h-screen pt-16 md:pt-0">
        <section class="p-4 md:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
                    <h3 class="text-gray-500 font-semibold">Total Revenue</h3>
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
                        <h2 class="text-xl font-bold text-gray-800">Revenue Overview</h2>
                        <form id="filterForm" method="GET" class="flex items-center gap-2 mt-4 sm:mt-0">
                             <select name="artist_id" class="px-3 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm focus:outline-purple-400">
                                <option value="all" <?= ($filter_artist_id_raw == 'all') ? 'selected' : '' ?>>All Artists</option>
                                <?php if($artists_for_filter) while($artist = $artists_for_filter->fetch_assoc()): ?>
                                    <option value="<?= $artist['id'] ?>" <?= ($filter_artist_id_raw == $artist['id']) ? 'selected' : '' ?>><?= htmlspecialchars($artist['artist_name']) ?></option>
                                <?php endwhile; ?>
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
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Latest Items Sold</h2>
                    <ul class="space-y-3">
                        <?php if ($latest_sales_result && $latest_sales_result->num_rows > 0): ?>
                            <?php while($sale = $latest_sales_result->fetch_assoc()): ?>
                                <li class="flex items-center justify-between py-2 border-b last:border-b-0 min-w-0">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-700 text-sm truncate" title="<?= htmlspecialchars($sale['product_name']) ?>"><?= htmlspecialchars($sale['product_name']) ?></p>
                                        <p class="text-xs text-gray-500 truncate">by <?= htmlspecialchars($sale['artist_name']) ?></p>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="font-bold text-green-600 text-sm">₱<?= number_format($sale['sale_total'], 2) ?></p>
                                        <p class="text-xs text-gray-400"><?= date('M d', strtotime($sale['created_at'])) ?></p>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="text-gray-400 text-sm">No recent sales to display.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </section>
    </main>
<script>
    // --- (Keep all your existing JS logic for the sidebar toggle and chart here) ---
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    if(sidebarToggle && sidebarOverlay){
        sidebarToggle.addEventListener('click', () => {
            const sidebar = document.querySelector('aside');
            sidebar.classList.toggle('hidden');
            sidebarOverlay.classList.toggle('hidden');
        });
        sidebarOverlay.addEventListener('click', () => {
            const sidebar = document.querySelector('aside');
            sidebar.classList.add('hidden');
            sidebarOverlay.classList.add('hidden');
        });
    }
    document.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('salesChart').getContext('2d');
        let gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(124, 58, 237, 0.4)');
        gradient.addColorStop(1, 'rgba(124, 58, 237, 0)');
        const salesChart = new Chart(ctx, { type: 'line',
            data: { labels: <?= json_encode($chart_labels) ?>, datasets: [{ label: 'Revenue', data: <?= json_encode($chart_data) ?>, backgroundColor: gradient, borderColor: '#7c3aed', borderWidth: 2, pointBackgroundColor: '#7c3aed', pointRadius: 4, fill: true, tension: 0.3 }] },
            options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { callback: (value) => '₱' + value.toLocaleString() } } }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => `${context.dataset.label || ''}: ₱${context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}` } } } }
        });
    });
</script>
</body>
</html>