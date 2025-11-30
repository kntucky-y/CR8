<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}
$current_page = 'orders';

// Auto logout after 10 minutes of inactivity
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// --- Database Connection ---
$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- Get filter values from the URL ---
$filter_status = $_GET['status'] ?? 'all';
$search_order_no = trim($_GET['search'] ?? '');

// --- Build the main query with dynamic filtering ---
$sql = "
    SELECT 
        o.id, 
        o.order_no, 
        o.total, 
        o.created_at, 
        u.first_name, 
        u.last_name, 
        (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) as delivery_status
    FROM orders o
    LEFT JOIN users u ON o.customer_id = u.id
";

$where_clauses = [];
$params = [];
$types = '';

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

// Add status filter
if ($filter_status !== 'all') {
    if ($filter_status === 'For Review') {
        $where_clauses[] = "(SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'For Review'";
    } else {
        $where_clauses[] = "(SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = ?";
        $params[] = $filter_status;
        $types .= 's';
    }
}

// Add search filter
if (!empty($search_order_no)) {
    $where_clauses[] = "o.order_no LIKE ?";
    $params[] = "%{$search_order_no}%";
    $types .= 's';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders | CR8 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="../img/favicon.png" type="image/png">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .order-row { cursor: pointer; }
        .order-row:hover { background: #f3e8ff; }
        #notification-modal { transition: opacity 0.3s ease-in-out; }
        .modal-content {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            animation: slide-up 0.4s ease-out;
        }
        @keyframes slide-up {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        /* --- Status Colors --- */
        .status-dropdown { transition: background-color 0.3s, color 0.3s; }
        .status-for-review { background-color: #e0f2fe; color: #0c4a6e; border-color: #7dd3fc !important; }
        .status-processing { background-color: #fef3c7; color: #92400e; border-color: #fcd34d !important; }
        .status-shipped { background-color: #f3e8ff; color: #6b21a8; border-color: #c084fc !important; }
        .status-completed { background-color: #dcfce7; color: #166534; border-color: #86efac !important; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; border-color: #fca5a5 !important; }
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
                <h1 class="font-bold text-2xl text-gray-800">All Orders</h1>
            </header>

            <div class="px-8 py-4 border-b">
                <form method="GET" class="bg-white p-4 rounded-xl shadow flex flex-row items-center gap-4">
                    <div class="flex items-center gap-2 w-auto">
                        <label for="search" class="font-semibold text-gray-600">Search:</label>
                        <input type="text" name="search" id="search" value="<?= htmlspecialchars($search_order_no) ?>" placeholder="Enter Order #" class="border-gray-300 rounded-md shadow-sm w-full">
                    </div>
                    <div class="flex items-center gap-2 w-auto">
                        <label for="status" class="font-semibold text-gray-600">Status:</label>
                        <select name="status" id="status" class="border-gray-300 rounded-md shadow-sm w-full">
                            <option value="all" <?= ($filter_status == 'all') ? 'selected' : '' ?>>All Statuses</option>
                            <option value="For Review" <?= ($filter_status == 'For Review') ? 'selected' : '' ?>>For Review</option>
                            <option value="Processing" <?= ($filter_status == 'Processing') ? 'selected' : '' ?>>Processing</option>
                            <option value="Shipped" <?= ($filter_status == 'Shipped') ? 'selected' : '' ?>>Shipped</option>
                            <option value="Completed" <?= ($filter_status == 'Completed') ? 'selected' : '' ?>>Delivered</option>
                            <option value="Cancelled" <?= ($filter_status == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-purple-600 text-white font-semibold px-5 py-2 rounded-md hover:bg-purple-700 w-auto">Apply Filters</button>
                </form>
            </div>
        </div>

        <section class="p-8">
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="orders-table" class="w-full text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-4 font-semibold text-gray-600">Order #</th>
                                <th class="p-4 font-semibold text-gray-600">Customer</th>
                                <th class="p-4 font-semibold text-gray-600">Date</th>
                                <th class="p-4 font-semibold text-gray-600 text-right">Total</th>
                                <th class="p-4 font-semibold text-gray-600 text-center">Order Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($order = $result->fetch_assoc()):
                                    $status = $order['delivery_status'] ?? 'For Review';
                                    $status_color_class = '';
                                    switch ($status) {
                                        case 'Processing': $status_color_class = 'status-processing'; break;
                                        case 'Shipped': $status_color_class = 'status-shipped'; break;
                                        case 'Completed': $status_color_class = 'status-completed'; break;
                                        case 'Cancelled': $status_color_class = 'status-cancelled'; break;
                                        default: $status_color_class = 'status-for-review'; break;
                                    }
                                ?>
                                    <tr class="order-row" data-order-id="<?= $order['id'] ?>">
                                        <td class="p-4 font-mono text-purple-700 font-bold"><?= htmlspecialchars($order['order_no']) ?></td>
                                        <td class="p-4 font-medium text-gray-800"><?= htmlspecialchars(trim($order['first_name'] . ' ' . $order['last_name'])) ?: 'N/A' ?></td>
                                        <td class="p-4 text-gray-600"><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                        <td class="p-4 text-right font-semibold text-green-600">₱<?= number_format($order['total'], 2) ?></td>
                                        <td class="p-4 text-center">
                                            <select name="status" data-order-id="<?= $order['id'] ?>" data-current-status="<?= $status ?>" class="status-dropdown border-gray-300 rounded-md shadow-sm text-sm font-semibold <?= $status_color_class ?>">
                                                <option value="For Review" <?= $status == 'For Review' ? 'selected' : '' ?>>For Review</option>
                                                <option value="Processing" <?= $status == 'Processing' ? 'selected' : '' ?>>Processing</option>
                                                <option value="Shipped" <?= $status == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                                <option value="Completed" <?= $status == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="Cancelled" <?= $status == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center p-8 text-gray-500">No orders found for the selected filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <div id="order-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
        <div id="modal-content-wrapper" class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] flex flex-col relative">
            <button id="modal-close-btn" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 z-10">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
            <div id="modal-content" class="p-6 md:p-8 overflow-y-auto">
                <p class="text-center text-gray-500">Loading order details...</p>
            </div>
        </div>
    </div>

    <div id="notification-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 hidden z-[100]">
        <div class="modal-content bg-white rounded-lg w-full max-w-sm p-6 text-center">
            <p id="notification-message" class="text-gray-700 mb-6"></p>
            <div id="notification-buttons" class="flex justify-center gap-4">
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const table = document.getElementById('orders-table');
            const orderModal = document.getElementById('order-modal');
            const orderModalContent = document.getElementById('modal-content');
            const closeOrderModalBtn = document.getElementById('modal-close-btn');

            const notificationModal = document.getElementById('notification-modal');
            const notificationMessage = document.getElementById('notification-message');
            const notificationButtons = document.getElementById('notification-buttons');

            const openOrderModal = () => orderModal.classList.remove('hidden');
            const closeOrderModal = () => orderModal.classList.add('hidden');

            const getStatusColorClass = (status) => {
                switch (status) {
                    case 'Processing': return 'status-processing';
                    case 'Shipped': return 'status-shipped';
                    case 'Completed': return 'status-completed';
                    case 'Cancelled': return 'status-cancelled';
                    default: return 'status-for-review';
                }
            };

            const showNotification = (message, type = 'info') => {
                notificationMessage.textContent = message;
                let buttonColor = 'bg-purple-600 hover:bg-purple-700';
                if (type === 'error') buttonColor = 'bg-red-600 hover:bg-red-700';

                notificationButtons.innerHTML = `<button id="notification-ok-btn" class="w-full px-6 py-2 rounded-md text-white font-semibold ${buttonColor}">OK</button>`;
                notificationModal.classList.remove('hidden');

                document.getElementById('notification-ok-btn').addEventListener('click', () => {
                    notificationModal.classList.add('hidden');
                }, { once: true });
            };

            const showConfirmation = (message, onConfirm) => {
                notificationMessage.textContent = message;
                notificationButtons.innerHTML = `
                    <button id="confirm-cancel-btn" class="px-6 py-2 rounded-md bg-gray-200 hover:bg-gray-300 font-semibold">Cancel</button>
                    <button id="confirm-ok-btn" class="px-6 py-2 rounded-md bg-red-600 hover:bg-red-700 text-white font-semibold">Confirm</button>
                `;
                notificationModal.classList.remove('hidden');

                const cancelBtn = document.getElementById('confirm-cancel-btn');
                const okBtn = document.getElementById('confirm-ok-btn');

                const closeConfirm = () => {
                    notificationModal.classList.add('hidden');
                    cancelBtn.removeEventListener('click', closeConfirm);
                    okBtn.removeEventListener('click', handleConfirm);
                };

                const handleConfirm = () => {
                    onConfirm(true);
                    closeConfirm();
                };

                cancelBtn.addEventListener('click', closeConfirm, { once: true });
                okBtn.addEventListener('click', handleConfirm, { once: true });
            };

            table.addEventListener('click', async (e) => {
                const row = e.target.closest('.order-row');
                if (e.target.tagName.toLowerCase() === 'select') return;
                if (!row) return;

                const orderId = row.dataset.orderId;
                orderModalContent.innerHTML = '<p class="text-center text-gray-500 py-10">Loading order details...</p>';
                openOrderModal();

                try {
                    const response = await fetch(`get_order_details.php?id=${orderId}`);
                    if (!response.ok) throw new Error('Network response was not ok.');

                    const data = await response.json();
                    if (data.error) throw new Error(data.error);

                    let itemsHTML = '';
                    data.items.forEach(item => {
                        itemsHTML += `
                        <div class="flex justify-between items-center py-2">
                            <div>
                                <p class="font-semibold">${item.product_name}</p>
                                <p class="text-sm text-gray-600">${item.variant_name || ''}</p>
                            </div>
                            <p class="text-sm text-gray-800">${item.quantity} x ₱${Number(item.price).toFixed(2)}</p>
                        </div>`;
                    });

                    let proofOfPaymentHTML = '';
                    if (data.details.proof_path) {
                        proofOfPaymentHTML = `
                            <a href="${data.details.proof_path}" target="_blank">
                                <img src="${data.details.proof_path}" class="w-full h-auto max-h-48 object-contain border rounded-md cursor-pointer">
                            </a>`;
                    } else {
                        proofOfPaymentHTML = '<p class="text-sm text-gray-500">No proof of payment provided.</p>';
                    }

                    orderModalContent.innerHTML = `
                        <div class="p-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2">Order #${data.details.order_no}</h2>
                            <p class="text-sm text-gray-500 mb-6">${new Date(data.details.created_at).toLocaleString()}</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="font-semibold text-lg mb-2">Customer Details</h3>
                                    <p>${data.details.first_name} ${data.details.last_name}</p>
                                    <p class="text-sm text-gray-600">${data.details.email}</p>
                                    <p class="text-sm text-gray-600 mt-2"><b>Shipping Address:</b><br>${data.details.address.replace(/, /g, '<br>')}</p>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-lg mb-2">Proof of Payment</h3>
                                    ${proofOfPaymentHTML}
                                </div>
                            </div>
                            <div class="mt-6 border-t pt-4">
                                <h3 class="font-semibold text-lg mb-2">Items Ordered</h3>
                                <div class="space-y-2">${itemsHTML}</div>
                            </div>
                            <div class="text-right font-bold text-xl mt-4 pt-4 border-t">
                                Total: <span class="text-green-600">₱${Number(data.details.total).toFixed(2)}</span>
                            </div>
                        </div>`;
                } catch (error) {
                    orderModalContent.innerHTML = `<p class="text-center text-red-500 py-10">Failed to load details: ${error.message}</p>`;
                }
            });

            table.addEventListener('change', async (e) => {
                if (e.target.classList.contains('status-dropdown')) {
                    const selectElement = e.target;
                    const orderId = selectElement.dataset.orderId;
                    const newStatus = selectElement.value;
                    const originalStatus = selectElement.dataset.currentStatus;

                    if (newStatus === 'For Review') {
                        showNotification('You cannot set status to "For Review" again.', 'error');
                        selectElement.value = originalStatus;
                        return;
                    }

                    const updateStatus = async () => {
                        try {
                            const response = await fetch('update_order_status.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    order_id: orderId,
                                    status: newStatus
                                })
                            });
                            const data = await response.json();
                            if (!data.success) throw new Error(data.error || 'Unknown error');

                            selectElement.dataset.currentStatus = newStatus;
                            selectElement.className = 'status-dropdown border-gray-300 rounded-md shadow-sm text-sm font-semibold ';
                            selectElement.classList.add(getStatusColorClass(newStatus));
                            showNotification('Order status updated successfully!');
                        } catch (error) {
                            showNotification(`Error updating status: ${error.message}`, 'error');
                            selectElement.value = originalStatus;
                        }
                    };

                    if (newStatus === 'Cancelled') {
                        showConfirmation('Are you sure you want to cancel this order?', (confirmed) => {
                            if (confirmed) {
                                updateStatus();
                            } else {
                                selectElement.value = originalStatus;
                            }
                        });
                    } else {
                        updateStatus();
                    }
                }
            });

            closeOrderModalBtn.addEventListener('click', closeOrderModal);
            orderModal.addEventListener('click', (e) => {
                if (e.target === orderModal) closeOrderModal();
            });
        });
    </script>
</body>
</html>
