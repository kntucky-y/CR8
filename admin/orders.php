<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}
$current_page = 'orders';

// --- Database Connection ---
// It's good practice to have this in a separate config file, but for now, this is fine.
$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- Get filter values from the URL ---
$filter_status = $_GET['status'] ?? 'all';
$search_order_no = trim($_GET['search'] ?? '');

// --- Build the main query with dynamic filtering ---
// This query now correctly fetches the latest status for each order, preventing duplicates in the view
// even if old duplicate data exists.
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

// Add status filter
if ($filter_status !== 'all') {
    if ($filter_status === 'Processing') {
        // Find orders that either have a 'Processing' status or no status at all (are NULL)
        $where_clauses[] = "((SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = ? OR (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) IS NULL)";
    } else {
        $where_clauses[] = "(SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = ?";
    }
    $params[] = $filter_status;
    $types .= 's';
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
        body {
            font-family: 'Outfit', sans-serif;
        }

        .order-row {
            cursor: pointer;
        }

        .order-row:hover {
            background: #f3e8ff;
        }

        /* Custom styles for the notification modal */
        #notification-modal {
            transition: opacity 0.3s ease-in-out;
        }

        .modal-content {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            animation: slide-up 0.4s ease-out;
        }

        @keyframes slide-up {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
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
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"></path>
                        <path d="M16 10l-4 4-4-4"></path>
                    </svg>
                    Dashboard
                </a>
                <a href="artist_applications.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'artist_applications') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Artist Applications
                </a>
                <a href="inbox.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'inbox') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M8 10h.01M12 10h.01M16 10h.01M9 16h6m2 4H7a2 2 0 01-2-2V7a2 2 0 012-2h3.28a2 2 0 011.42.59l.3.3a2 2 0 001.42.59H17a2 2 0 012 2v11a2 2 0 01-2 2z"></path>
                    </svg>
                    Inbox
                </a>
                <a href="artists.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'artists') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    All Artists
                </a>
                <a href="customers.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'customers') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21v-3a3 3 0 00-3-3H6a3 3 0 00-3 3v3"></path>
                    </svg>
                    All Customers
                </a>
                <a href="orders.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'orders') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
                    </svg>
                    Orders
                </a>
                <a href="sales.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'sales') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    Sales
                </a>
                <a href="inventory.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'inventory') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4M4 7l8 4.5M12 11.5V21M20 7l-8 4.5"></path>
                    </svg>
                    Inventory
                </a>
                <?php if (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] == 1): ?>
                    <a href="admin_management.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold <?= ($current_page == 'admin_management') ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Admin Management
                    </a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="mb-6 px-2">
            <a href="dashboard.php?logout=1" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold bg-red-50 text-red-700 hover:bg-red-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h4a2 2 0 012 2v1"></path>
                </svg>
                Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-h-screen">
        <header class="flex items-center justify-between px-8 py-4 border-b bg-white">
            <h1 class="font-bold text-2xl text-gray-800">All Orders</h1>
        </header>

        <section class="p-8">
            <form method="GET" class="bg-white p-4 rounded-xl shadow mb-8 flex flex-col md:flex-row items-center gap-4">
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <label for="search" class="font-semibold text-gray-600">Search:</label>
                    <input type="text" name="search" id="search" value="<?= htmlspecialchars($search_order_no) ?>" placeholder="Enter Order #" class="border-gray-300 rounded-md shadow-sm w-full">
                </div>
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <label for="status" class="font-semibold text-gray-600">Status:</label>
                    <select name="status" id="status" class="border-gray-300 rounded-md shadow-sm w-full">
                        <option value="all" <?= ($filter_status == 'all') ? 'selected' : '' ?>>All Statuses</option>
                        <option value="Processing" <?= ($filter_status == 'Processing') ? 'selected' : '' ?>>Processing</option>
                        <option value="Shipped" <?= ($filter_status == 'Shipped') ? 'selected' : '' ?>>Shipped</option>
                        <option value="Delivered" <?= ($filter_status == 'Delivered') ? 'selected' : '' ?>>Delivered</option>
                        <option value="Cancelled" <?= ($filter_status == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="bg-purple-600 text-white font-semibold px-5 py-2 rounded-md hover:bg-purple-700 w-full md:w-auto">Apply Filters</button>
            </form>

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
                                    $status = $order['delivery_status'] ?? 'Processing';
                                ?>
                                    <tr class="order-row" data-order-id="<?= $order['id'] ?>">
                                        <td class="p-4 font-mono text-purple-700 font-bold"><?= htmlspecialchars($order['order_no']) ?></td>
                                        <td class="p-4 font-medium text-gray-800"><?= htmlspecialchars(trim($order['first_name'] . ' ' . $order['last_name'])) ?: 'N/A' ?></td>
                                        <td class="p-4 text-gray-600"><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                        <td class="p-4 text-right font-semibold text-green-600">₱<?= number_format($order['total'], 2) ?></td>
                                        <td class="p-4 text-center">
                                            <select name="status" data-order-id="<?= $order['id'] ?>" data-current-status="<?= $status ?>" class="status-dropdown border-gray-300 rounded-md shadow-sm text-sm font-semibold">
                                                <option value="Processing" <?= $status == 'Processing' ? 'selected' : '' ?>>Processing</option>
                                                <option value="Shipped" <?= $status == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                                <option value="Delivered" <?= $status == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
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

    <!-- Order Details Modal -->
    <div id="order-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
        <div id="modal-content-wrapper" class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] flex flex-col relative">
            <button id="modal-close-btn" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 z-10">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <div id="modal-content" class="p-6 md:p-8 overflow-y-auto">
                <p class="text-center text-gray-500">Loading order details...</p>
            </div>
        </div>
    </div>

    <!-- Notification & Confirmation Modal -->
    <div id="notification-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 hidden z-[100]">
        <div class="modal-content bg-white rounded-lg w-full max-w-sm p-6 text-center">
            <p id="notification-message" class="text-gray-700 mb-6"></p>
            <div id="notification-buttons" class="flex justify-center gap-4">
                <!-- Buttons will be injected here by JavaScript -->
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

            // --- Modal Logic ---
            const openOrderModal = () => orderModal.classList.remove('hidden');
            const closeOrderModal = () => orderModal.classList.add('hidden');

            // --- Notification/Confirmation Modal Functions ---
            const showNotification = (message, type = 'info') => {
                notificationMessage.textContent = message;
                let buttonColor = 'bg-purple-600 hover:bg-purple-700';
                if (type === 'error') buttonColor = 'bg-red-600 hover:bg-red-700';

                notificationButtons.innerHTML = `<button id="notification-ok-btn" class="w-full px-6 py-2 rounded-md text-white font-semibold ${buttonColor}">OK</button>`;
                notificationModal.classList.remove('hidden');

                document.getElementById('notification-ok-btn').addEventListener('click', () => {
                    notificationModal.classList.add('hidden');
                }, {
                    once: true
                });
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

                cancelBtn.addEventListener('click', closeConfirm, {
                    once: true
                });
                okBtn.addEventListener('click', handleConfirm, {
                    once: true
                });
            };


            // --- Event Listeners ---
            table.addEventListener('click', async (e) => {
                const row = e.target.closest('.order-row');
                if (e.target.tagName.toLowerCase() === 'select') return; // Ignore clicks on the dropdown
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
                            <a href="../${data.details.proof_path}" target="_blank">
                                <img src="../${data.details.proof_path}" class="w-full h-auto max-h-48 object-contain border rounded-md cursor-pointer">
                            </a>
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

                    const updateStatus = async () => {
                        try {
                            const response = await fetch('update_order_status.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    order_id: orderId,
                                    status: newStatus
                                })
                            });
                            const data = await response.json();
                            if (!data.success) throw new Error(data.error || 'Unknown error');

                            // Update the 'data-current-status' to the new status
                            selectElement.dataset.currentStatus = newStatus;
                            showNotification('Order status updated successfully!');
                        } catch (error) {
                            showNotification(`Error updating status: ${error.message}`, 'error');
                            // Revert dropdown to original value on failure
                            selectElement.value = originalStatus;
                        }
                    };

                    if (newStatus === 'Cancelled') {
                        showConfirmation('Are you sure you want to cancel this order?', (confirmed) => {
                            if (confirmed) {
                                updateStatus();
                            } else {
                                // Revert dropdown if user cancels confirmation
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