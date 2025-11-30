<?php
session_start();
// Standard admin login check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}
$current_page = 'inventory';

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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management | CR8 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="../img/favicon.png" type="image/png">
    <style> body { font-family: 'Outfit', sans-serif; } </style>
</head>
<body class="bg-gray-100 min-h-screen flex">
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

<main class="flex-1 flex flex-col min-h-screen">
    <header class="flex items-center justify-between px-8 py-4 border-b bg-white">
         <h1 class="font-bold text-2xl text-gray-800">Inventory Management</h1>
    </header>

    <section class="p-4 md:p-8">
        <form method="GET" class="bg-white p-4 rounded-xl shadow mb-8 flex flex-col md:flex-row items-center gap-4 flex-wrap">
            <input type="text" name="search" value="<?= htmlspecialchars($search_term) ?>" placeholder="Search by product name..." class="border-gray-300 rounded-md shadow-sm w-full md:w-auto flex-grow">
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