<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['is_superadmin'] != 1) {
    header("Location: dashboard.php");
    exit;
}
$current_page = 'admin_management';

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
    <title>Admin | CR8 Admin</title>
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
<aside class="sticky top-0 h-screen z-40 w-64 bg-white border-r flex-shrink-0 hidden md:flex flex-col justify-between">
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
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
            </svg>
            Logout
        </a>
    </div>
</aside>

<main class="flex-1 flex flex-col min-h-screen">
    <header class="flex items-center justify-between px-8 py-4 border-b bg-white sticky top-0 z-30">
        <h1 class="font-bold text-2xl text-gray-800">Admin Management</h1>
    </header>
    <section class="p-8">
        <div class="flex items-center justify-between mb-8 gap-4">
            <form method="post" class="flex items-center gap-2 bg-white rounded-lg shadow px-4 py-3">
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
</body>
</html>