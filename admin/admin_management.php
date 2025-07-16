<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['is_superadmin'] != 1) {
    header("Location: dashboard.php");
    exit;
}
$current_page = 'admin_management';

$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Handle add admin
$add_error = '';
if (isset($_POST['add_admin'])) {
    $new_username = trim($_POST['username']);
    $new_password = trim($_POST['password']);
    if ($new_username && $new_password) {
        // Prevent duplicate usernames
        $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->bind_param("s", $new_username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $add_error = "Username already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO admins (username, password, is_superadmin) VALUES (?, ?, 0)");
            $stmt->bind_param("ss", $new_username, $new_password);
            $stmt->execute();
        }
        $stmt->close();
    } else {
        $add_error = "Username and password are required.";
    }
}

// Handle delete admin (cannot delete superadmin)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    // Prevent deleting superadmin
    $stmt = $conn->prepare("SELECT is_superadmin FROM admins WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->bind_result($is_superadmin);
    $stmt->fetch();
    $stmt->close();
    if ($is_superadmin == 0) {
        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();

        // Reset auto-increment value
        $conn->query("ALTER TABLE admins AUTO_INCREMENT = 1");
    }
}

// Fetch all admins (now also fetch password)
$admins = [];
$result = $conn->query("SELECT id, username, password, is_superadmin, last_signed_in, last_signed_out FROM admins ORDER BY is_superadmin DESC, id ASC");
while ($row = $result->fetch_assoc()) {
    $admins[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management | CR8 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    <aside class="w-64 bg-white border-r flex-shrink-0 hidden md:flex flex-col justify-between">
        <div>
            <div class="flex items-center gap-2 px-6 py-6 border-b">
                <img src="img/cr8-logo.png" alt="Logo" class="w-10 h-10 rounded-full">
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
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
                <h1 class="text-2xl font-bold text-gray-800">Admin Management</h1>
                <form method="post" class="flex flex-col md:flex-row items-center gap-2 bg-white rounded-lg shadow px-4 py-3">
                    <input type="text" name="username" placeholder="Username" required class="px-3 py-2 border border-gray-200 rounded-md focus:outline-purple-400 text-sm">
                    <input type="text" name="password" placeholder="Password" required class="px-3 py-2 border border-gray-200 rounded-md focus:outline-purple-400 text-sm">
                    <button type="submit" name="add_admin" class="px-4 py-2 bg-purple-600 text-white font-semibold rounded-md text-sm hover:bg-purple-700">Add Admin</button>
                    <?php if ($add_error): ?>
                        <span class="text-red-600 text-sm ml-2"><?= htmlspecialchars($add_error) ?></span>
                    <?php endif; ?>
                </form>
            </div>
            <div class="bg-white rounded-xl shadow p-6 overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Username</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Password</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Superadmin</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Last Signed In</th>
                             <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Last Signed Out</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                        <tr class="border-b last:border-b-0">
                            <td class="px-4 py-2"><?= $admin['id'] ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($admin['username']) ?></td>
                            <td class="px-4 py-2 font-mono"><?= htmlspecialchars($admin['password']) ?></td>
                            <td class="px-4 py-2"><?= $admin['is_superadmin'] ? '<span class="text-green-600 font-semibold">Yes</span>' : 'No' ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($admin['last_signed_in'] ?? 'Never') ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($admin['last_signed_out'] ?? 'Never') ?></td>
                            <td class="px-4 py-2">
                                <?php if (!$admin['is_superadmin']): ?>
                                    <a href="?delete=<?= $admin['id'] ?>" class="text-red-600 hover:underline font-semibold" onclick="return confirm('Delete this admin?')">Delete</a>
                                <?php else: ?>
                                    <span class="text-gray-400">Protected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
</body>
</html>