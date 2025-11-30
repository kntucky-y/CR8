<?php
session_start();

// Handle logout via GET parameter
if (isset($_GET['logout'])) {
    // Update last_signed_out in the database
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

// Connect to the main CR8 database
$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- DATA FETCHING FOR DASHBOARD ---

// -- CARD 1: Artist Applications --
$app_count = $conn->query("SELECT COUNT(*) FROM artist_applications")->fetch_row()[0] ?? 0;
$unread_count = $conn->query("SELECT COUNT(*) FROM artist_applications WHERE status='unread'")->fetch_row()[0] ?? 0;

// -- CARD 2: Sales Overview --
$sales_total = $conn->query("SELECT SUM(oi.price * oi.quantity) FROM order_items oi")->fetch_row()[0] ?? 0;

// -- CARD 3: Top Selling Artist --
$top_seller_query = "
    SELECT a.artist_name, SUM(oi.price * oi.quantity) as total_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN artists a ON p.artist_id = a.id
    GROUP BY a.id
    ORDER BY total_revenue DESC
    LIMIT 1";
$top_seller_result = $conn->query($top_seller_query);
$top_seller = $top_seller_result->fetch_assoc();

// -- CARD 4: Top Selling Product --
$top_product_query = "
    SELECT p.product_name, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 1";
$top_product_result = $conn->query($top_product_query);
$top_product = $top_product_result->fetch_assoc();


// -- LIST: Latest Individual Sales (Corrected to show Seller) --
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
    LIMIT 5";
$latest_sales_result = $conn->query($latest_sales_query);


// -- CHART DATA & FILTERS --
$artists_for_filter = $conn->query("SELECT id, artist_name FROM artists ORDER BY artist_name ASC");

// Get filter values from URL
$filter_artist_id_raw = $_GET['artist_id'] ?? 'all';
$filter_period = $_GET['period'] ?? 'month';

// Build Chart Query based on filters
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
if($chart_result){
    while ($row = $chart_result->fetch_assoc()) {
        $chart_labels[] = $row['label'];
        $chart_data[] = $row['revenue'];
    }
}


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
        .sidebar-mobile { transition: left 0.2s; }
        .sidebar-mobile.closed { left: -100%; }
        .sidebar-mobile.open { left: 0; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col md:flex-row">
    <!-- Mobile Header/Navbar -->
    <header class="flex items-center justify-between bg-white border-b px-4 py-3 md:hidden">
        <div class="flex items-center gap-2">
            <img src="img/cr8-logo.png" alt="Logo" class="w-8 h-8 rounded-full">
            <span class="font-bold text-lg text-purple-800">CR8 Cebu</span>
        </div>
        <button id="sidebarToggle" class="text-purple-700 focus:outline-none">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </header>
    <!-- Sidebar -->
    <aside class="sticky top-0 h-screen z-40 w-64 bg-white border-r flex-shrink-0 hidden md:flex flex-col justify-between">
        <div>
            <div class="flex items-center gap-2 px-6 py-6 border-b">
                <img src="../img/cr8-logo.png" alt="Logo" class="w-10 h-10 rounded-full">
                <span class="font-bold text-xl text-purple-800">CR8 Cebu</span>
            </div>
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
                <a href="reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'reports') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V7a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Reports
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
    <!-- Overlay for mobile sidebar -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-30 z-30 hidden md:hidden"></div>
    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-h-screen pt-16 md:pt-0">
        <section class="p-4 md:p-8">
            <!-- Top Stat Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Revenue -->
                <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between dashboard-card">
                    <h3 class="text-gray-500 font-semibold">Total Revenue</h3>
                    <p class="text-3xl font-bold text-green-600 mt-2">₱<?= number_format($sales_total, 2) ?></p>
                </div>
                <!-- Top Seller -->
                <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between dashboard-card">
                    <h3 class="text-gray-500 font-semibold">Top Selling Artist</h3>
                    <p class="text-2xl font-bold text-purple-700 mt-2 truncate" title="<?= htmlspecialchars($top_seller['artist_name'] ?? 'N/A') ?>"><?= htmlspecialchars($top_seller['artist_name'] ?? 'N/A') ?></p>
                    <p class="text-sm text-gray-400">₱<?= number_format($top_seller['total_revenue'] ?? 0, 2) ?> in sales</p>
                </div>
                <!-- Top Product -->
                 <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between dashboard-card">
                    <h3 class="text-gray-500 font-semibold">Top Selling Product</h3>
                    <p class="text-2xl font-bold text-blue-600 mt-2 truncate" title="<?= htmlspecialchars($top_product['product_name'] ?? 'N/A') ?>"><?= htmlspecialchars($top_product['product_name'] ?? 'N/A') ?></p>
                     <p class="text-sm text-gray-400"><?= number_format($top_product['total_sold'] ?? 0) ?> units sold</p>
                </div>
                 <!-- Artist Applications -->
                <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between dashboard-card">
                    <h3 class="text-gray-500 font-semibold">Artist Applications</h3>
                    <p class="text-3xl font-bold text-yellow-600 mt-2"><?= $app_count ?></p>
                    <p class="text-sm text-gray-400">Unread: <span class="font-bold"><?= $unread_count ?></span></p>
                </div>
            </div>

            <!-- Sales Chart and Latest Sales -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Chart -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow p-6">
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-800">Revenue Overview</h2>
                        <!-- Filters -->
                        <form id="filterForm" method="GET" class="flex items-center gap-2 mt-4 sm:mt-0">
                             <select name="artist_id" class="px-3 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm focus:outline-purple-400">
                                <option value="all" <?= ($filter_artist_id_raw == 'all') ? 'selected' : '' ?>>All Artists</option>
                                <?php if($artists_for_filter) while($artist = $artists_for_filter->fetch_assoc()): ?>
                                    <option value="<?= $artist['id'] ?>" <?= ($filter_artist_id_raw == $artist['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($artist['artist_name']) ?>
                                    </option>
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
                <!-- Latest Sales List -->
                <div class="bg-white rounded-xl shadow p-6 overflow-x-auto">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Latest Items Sold</h2>
                    <ul class="space-y-3">
                        <?php if ($latest_sales_result && $latest_sales_result->num_rows > 0): ?>
                            <?php while($sale = $latest_sales_result->fetch_assoc()): ?>
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
                            <li class="text-gray-400 text-sm">No recent sales to display.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </section>
    </main>
<script>
    // Sidebar toggle for mobile
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    if(sidebar && sidebarToggle && sidebarOverlay){
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('translate-x-full');
            sidebarOverlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden', !sidebarOverlay.classList.contains('hidden'));
        });
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.add('translate-x-full');
            sidebarOverlay.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        });
    }
</script>
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
            responsive: true, maintainAspectRatio: true,
            scales: { y: { beginAtZero: true, ticks: { callback: (value) => '₱' + value.toLocaleString() } } },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (context) => `${context.dataset.label || ''}: ₱${context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}` } }
            }
        }
    });
});
</script>
</body>
</html>
