<?php
session_start();
// Standard admin login check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}
$current_page = 'inventory';

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

// --- Get filter values ---
$filter_artist_id = $_GET['artist_id'] ?? 'all';
$filter_stock = $_GET['stock'] ?? 'all';
$search_term = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'active'; 

// --- Fetch artists for the filter dropdown ---
$all_artists_result = $conn->query("SELECT id, artist_name FROM artists ORDER BY artist_name ASC");

// --- Build the main query ---
$sql = "
    SELECT 
        p.id, p.product_name, p.base_variant_name, p.image, p.quantity, p.is_active,
        a.artist_name
    FROM products p
    LEFT JOIN artists a ON p.artist_id = a.id
";

$where_clauses = [];
$params = [];
$types = '';

if ($filter_status === 'active') {
    $where_clauses[] = "p.is_active = 1";
} elseif ($filter_status === 'inactive') {
    $where_clauses[] = "p.is_active = 0";
}

if ($filter_artist_id !== 'all') {
    $where_clauses[] = "p.artist_id = ?";
    $params[] = $filter_artist_id;
    $types .= 'i';
}

if ($filter_stock !== 'all') {
    if ($filter_stock === 'instock') { $where_clauses[] = "p.quantity > 0"; } 
    elseif ($filter_stock === 'lowstock') { $where_clauses[] = "p.quantity > 0 AND p.quantity <= 10"; } 
    elseif ($filter_stock === 'outofstock') { $where_clauses[] = "p.quantity <= 0"; }
}

if (!empty($search_term)) {
    $where_clauses[] = "p.product_name LIKE ?";
    $params[] = "%{$search_term}%";
    $types .= 's';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY p.product_name ASC";

$products_stmt = $conn->prepare($sql);
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
    <title>Inventory | CR8 Admin</title>
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
            <h1 class="font-bold text-2xl text-gray-800">Inventory Management</h1>
        </header>

        <div class="px-8 py-4 border-b">
            <form method="GET" class="bg-white p-4 rounded-xl shadow flex flex-row items-center gap-4 flex-wrap">
                <input type="text" name="search" value="<?= htmlspecialchars($search_term) ?>" placeholder="Search by product name..." class="border-gray-300 rounded-md shadow-sm w-auto flex-grow">
                <select name="artist_id" class="border-gray-300 rounded-md shadow-sm">
                    <option value="all">All Artists</option>
                    <?php mysqli_data_seek($all_artists_result, 0); while($artist = $all_artists_result->fetch_assoc()): ?>
                        <option value="<?= $artist['id'] ?>" <?= ($filter_artist_id == $artist['id']) ? 'selected' : '' ?>><?= htmlspecialchars($artist['artist_name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <select name="stock" class="border-gray-300 rounded-md shadow-sm">
                    <option value="all" <?= ($filter_stock == 'all') ? 'selected' : '' ?>>All Stock Levels</option>
                    <option value="instock" <?= ($filter_stock == 'instock') ? 'selected' : '' ?>>In Stock</option>
                    <option value="lowstock" <?= ($filter_stock == 'lowstock') ? 'selected' : '' ?>>Low Stock (≤10)</option>
                    <option value="outofstock" <?= ($filter_stock == 'outofstock') ? 'selected' : '' ?>>Out of Stock</option>
                </select>
                <select name="status" class="border-gray-300 rounded-md shadow-sm">
                    <option value="active" <?= ($filter_status == 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($filter_status == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    <option value="all" <?= ($filter_status == 'all') ? 'selected' : '' ?>>All</option>
                </select>
                <button type="submit" class="bg-purple-600 text-white font-semibold px-5 py-2 rounded-md hover:bg-purple-700">Filter</button>
            </form>
        </div>
    </div>

    <section class="p-8">
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table id="inventory-table" class="w-full text-left">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-4 font-semibold text-gray-600">Product</th>
                            <th class="p-4 font-semibold text-gray-600">Artist</th>
                            <th class="p-4 font-semibold text-gray-600 text-center">Stock</th>
                            <th class="p-4 font-semibold text-gray-600 text-center">Status</th>
                            <th class="p-4 font-semibold text-gray-600 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($products_result && $products_result->num_rows > 0): ?>
                            <?php while($product = $products_result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50" id="product-row-<?= $product['id'] ?>">
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <img src="https://cr8.dcism.org/<?= htmlspecialchars(str_replace('\\', '/', $product['image'])) ?>" class="w-12 h-12 object-cover rounded-md border" onerror="this.src='https://cr8.dcism.org/img/default-product.png'">
                                            <div>
                                                <p class="font-bold text-gray-800"><?= htmlspecialchars($product['product_name']) ?></p>
                                                <p class="text-sm text-gray-500"><?= htmlspecialchars($product['base_variant_name']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-600"><?= htmlspecialchars($product['artist_name']) ?></td>
                                    <td class="p-4 text-center">
                                        <input type="number" value="<?= $product['quantity'] ?>" min="0" class="stock-input w-20 text-center border-gray-300 rounded-md shadow-sm" data-id="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['product_name']) ?>">
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="px-2 py-1 font-semibold leading-tight text-xs rounded-full <?= $product['is_active'] ? 'text-green-700 bg-green-100' : 'text-gray-700 bg-gray-100' ?>"><?= $product['is_active'] ? 'Active' : 'Inactive' ?></span>
                                    </td>
                                    <td class="p-4 text-center space-x-2">
                                        <button data-id="<?= $product['id'] ?>" class="update-stock-btn text-blue-600 hover:text-blue-800 font-semibold">Update Stock</button>
                                        <button data-id="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['product_name']) ?>" data-action="<?= $product['is_active'] ? 'deactivate' : 'reactivate' ?>" class="update-status-btn font-semibold <?= $product['is_active'] ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800' ?>"><?= $product['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center p-8 text-gray-500">No products found for the selected filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const inventoryTable = document.getElementById('inventory-table');
    if (!inventoryTable) return;

    inventoryTable.addEventListener('click', async (e) => {
        const target = e.target;
        let payload = {};
        let confirmMessage = '';

        if (target.classList.contains('update-status-btn')) {
            const productId = target.dataset.id;
            const productName = target.dataset.name;
            const action = target.dataset.action;
            confirmMessage = `Are you sure you want to ${action} "${productName}"?`;
            payload = { product_id: productId, status: (action === 'deactivate') ? 0 : 1 };
        } else if (target.classList.contains('update-stock-btn')) {
            const productId = target.dataset.id;
            const stockInput = inventoryTable.querySelector(`.stock-input[data-id='${productId}']`);
            if (!stockInput) return;
            confirmMessage = `Update stock for "${stockInput.dataset.name}" to ${stockInput.value}?`;
            payload = { product_id: productId, quantity: stockInput.value };
        } else {
            return;
        }

        if (confirm(confirmMessage)) {
            try {
                // MODIFIED: Use a clear endpoint name like 'update_product.php'
                const response = await fetch('update_product.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                alert(`Error: ${error.message}`);
            }
        }
    });
});
</script>
</body>
</html>