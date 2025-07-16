<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Set the current page for sidebar highlighting
$current_page = 'inbox';

// Connect to the database
$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- Mark a message as Read when it is viewed ---
$selected_message = null;
if (isset($_GET['view'])) {
    $view_id = intval($_GET['view']);
    
    // Prepare a statement to fetch the selected message
    $stmt = $conn->prepare("SELECT id, name, email, message, created_at, status FROM messages WHERE id = ?");
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $selected_message = $result->fetch_assoc();
        
        // If the message is currently 'Unread', update it to 'Read'
        if ($selected_message['status'] == 'Unread') {
            $update_stmt = $conn->prepare("UPDATE messages SET status = 'Read' WHERE id = ?");
            $update_stmt->bind_param("i", $view_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }
    $stmt->close();
}

// --- Delete a message if the delete action is triggered ---
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $delete_stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    $delete_stmt->bind_param("i", $delete_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Redirect to the base inbox page to prevent re-deletion on refresh
    header("Location: inbox.php");
    exit;
}

// Fetch all messages to display in the list, including their status
$messages = [];
$result = $conn->query("SELECT id, name, email, message, created_at, status FROM messages ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox | CR8 Admin</title>
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
<body class="bg-gray-100 min-h-screen flex flex-col md:flex-row">
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
    
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-30 z-30 hidden md:hidden"></div>

    <main class="flex-1 flex flex-col min-h-screen pt-16 md:pt-0">
        <header class="flex items-center justify-between px-8 py-4 border-b bg-white">
            <h1 class="font-bold text-2xl text-gray-800">Inbox</h1>
        </header>
        <section class="flex-1 flex bg-white">
            <div class="w-96 border-r flex flex-col">
                <div class="p-4 border-b">
                    <h2 class="font-bold text-lg">Customer Messages <span class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded-full"><?= count($messages) ?></span></h2>
                </div>
                <div class="overflow-y-auto flex-1">
                    <?php if (empty($messages)): ?>
                        <div class="p-4 text-gray-400 text-center mt-10">No messages found.</div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                        <a href="inbox.php?view=<?= $msg['id'] ?>" class="block border-b px-4 py-3 hover:bg-purple-50 cursor-pointer <?= (isset($_GET['view']) && $_GET['view'] == $msg['id']) ? 'message-active' : '' ?>">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <?php if ($msg['status'] == 'Unread'): ?>
                                        <span class="w-2.5 h-2.5 bg-blue-500 rounded-full" title="Unread"></span>
                                    <?php endif; ?>
                                    <span class="font-bold text-sm"><?= htmlspecialchars($msg['name']) ?></span>
                                </div>
                                <span class="text-xs text-gray-400"><?= date('M d, Y', strtotime($msg['created_at'])) ?></span>
                            </div>
                            <p class="text-xs text-gray-500 truncate pl-5"><?= htmlspecialchars($msg['message']) ?></p>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex-1 flex flex-col p-8 bg-gray-50 overflow-y-auto">
                 <?php if ($selected_message): ?>
                    <div class="bg-white shadow-xl rounded-xl p-8 w-full max-w-3xl mx-auto">
                        <div class="flex items-center justify-between mb-6 pb-4 border-b">
                            <div>
                                <h2 class="text-2xl font-bold"><?= htmlspecialchars($selected_message['name']) ?></h2>
                                <a href="mailto:<?= htmlspecialchars($selected_message['email']) ?>" class="text-blue-600"><?= htmlspecialchars($selected_message['email']) ?></a>
                            </div>
                            <span class="text-sm text-gray-500"><?= date('F j, Y, g:i a', strtotime($selected_message['created_at'])) ?></span>
                        </div>
                        <div>
                            <h3 class="font-semibold mb-2">Message:</h3>
                            <div class="bg-gray-100 rounded p-4 text-gray-800 prose max-w-none">
                                <?= nl2br(htmlspecialchars($selected_message['message'])) ?>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-between">
                            <a href="mailto:<?= htmlspecialchars($selected_message['email']) ?>" class="bg-purple-600 text-white font-semibold px-5 py-2 rounded-lg hover:bg-purple-700">Reply via Email</a>
                            <a href="inbox.php?delete=<?= $selected_message['id'] ?>" class="bg-red-600 text-white font-semibold px-5 py-2 rounded-lg hover:bg-red-700" onclick="return confirm('Are you sure you want to delete this message?')">Delete</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center self-center">
                        <svg class="w-16 h-16 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <p class="text-gray-400 mt-4">Select a message to view it.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <script>
        // Basic sidebar toggle for mobile view.
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        // This is a simplified version, assuming a single sidebar element will be created or is already present.
        const sidebar = document.querySelector('aside'); 

        if (sidebarToggle && sidebar && sidebarOverlay) {
            sidebarToggle.addEventListener('click', () => {
                // This is a generic toggle and may need adjustment based on your full sidebar implementation
                sidebar.classList.toggle('hidden'); 
                sidebarOverlay.classList.toggle('hidden');
            });
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.add('hidden');
                sidebarOverlay.classList.add('hidden');
            });
        }
    </script>
</body>
</html>