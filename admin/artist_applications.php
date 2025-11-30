<?php
session_start();

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

// Set the current page for sidebar highlighting
$current_page = 'artist_applications';

// Connect to the database
$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Fetch selected artist application for detail view and mark as read
$selected_artist = null;
if (isset($_GET['view'])) {
    $view_id = intval($_GET['view']);

    // Fetch the single application
    $stmt = $conn->prepare("SELECT * FROM artist_applications WHERE id = ?");
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $selected_artist = $result->fetch_assoc();

        // If the status is 'Unread', update it to 'Read'
        if ($selected_artist['status'] == 'Unread') {
            $update_stmt = $conn->prepare("UPDATE artist_applications SET status = 'Read' WHERE id = ?");
            $update_stmt->bind_param("i", $view_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }

    $stmt->close();
}


// Fetch all artist applications to display in the list
$artist_applications = [];
$result = $conn->query("SELECT id, full_name, product_desc, submitted_at, status FROM artist_applications ORDER BY submitted_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $artist_applications[] = $row;
    }
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
    <title>Artist Application | CR8 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }

        .message-active {
            background-color: #f3e8ff;
            /* Light purple for active item */
            border-right: 4px solid #7A1CAC;
            /* Darker purple indicator */
        }
    </style>
</head>

<body class="bg-gray-100 flex">
    <aside class="w-64 bg-white border-r flex-shrink-0 flex flex-col justify-between sticky top-0 h-screen">
        <div class="flex-grow overflow-y-auto">
            <div class="flex items-center gap-2 px-6 py-6 border-b">
                <img src="../img/cr8-logo.png" alt="Logo" class="w-10 h-10 rounded-full">
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
        <div class="flex-shrink-0 mb-6 px-2">
            <a href="dashboard.php?logout=1" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold bg-red-50 text-red-700 hover:bg-red-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
                Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="flex items-center justify-between px-8 py-4 border-b bg-white flex-shrink-0">
            <h1 class="font-bold text-2xl text-gray-800">Artist Applications</h1>
        </header>
        <section class="flex-1 flex bg-white overflow-hidden">
            <div class="w-96 border-r flex flex-col">
                <div class="p-4 border-b flex-shrink-0">
                    <h2 class="font-bold text-lg">Pending Applications <span class="bg-purple-100 text-purple-700 text-xs px-2 py-1 rounded-full"><?= count($artist_applications) ?></span></h2>
                </div>
                <div class="overflow-y-auto flex-1">
                    <?php if (empty($artist_applications)): ?>
                        <div class="p-4 text-gray-400 text-center mt-10">No new applications.</div>
                    <?php else: ?>
                        <?php foreach ($artist_applications as $app): ?>
                            <a href="artist_applications.php?view=<?= $app['id'] ?>"
                                draggable="true" ondragstart="handleDragStart(event, <?= $app['id'] ?>)" id="app-card-<?= $app['id'] ?>"
                                class="block border-b px-4 py-3 hover:bg-purple-50 cursor-pointer flex flex-col <?= (isset($_GET['view']) && $_GET['view'] == $app['id']) ? 'message-active' : '' ?>">
                                <div class="flex items-center gap-2">
                                    <span title="Drag to Accept/Reject" class="cursor-grab text-gray-400 hover:text-purple-500">
                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <circle cx="6" cy="12" r="1.5" />
                                            <circle cx="12" cy="12" r="1.5" />
                                            <circle cx="18" cy="12" r="1.5" />
                                        </svg>
                                    </span>
                                    <span class="font-bold text-sm"><?= htmlspecialchars($app['full_name']) ?></span>
                                    <?php if ($app['status'] == 'Unread'): ?>
                                        <span class="w-2.5 h-2.5 bg-purple-500 rounded-full" title="Unread"></span>
                                    <?php endif; ?>
                                    <span class="ml-auto text-xs text-gray-400"><?= date('M d, Y', strtotime($app['submitted_at'])) ?></span>
                                </div>
                                <div class="text-xs text-gray-500 truncate pl-7"><?= htmlspecialchars($app['product_desc']) ?></div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex-1 flex flex-col p-8 bg-gray-50">
                <?php if ($selected_artist): ?>
                    <div class="bg-white shadow-xl rounded-xl p-8 w-full max-w-3xl mx-auto">
                        <div class="flex items-center gap-4 mb-6 pb-4 border-b">
                            <div>
                                <h2 class="text-2xl font-bold"><?= htmlspecialchars($selected_artist['full_name']) ?></h2>
                                <p class="text-purple-700 font-semibold"><?= htmlspecialchars($selected_artist['artist_name']) ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 mb-4">
                            <div><label class="text-xs text-gray-500 font-semibold">Email</label>
                                <p><a href="mailto:<?= htmlspecialchars($selected_artist['email']) ?>" class="text-blue-600"><?= htmlspecialchars($selected_artist['email']) ?></a></p>
                            </div>
                            <div><label class="text-xs text-gray-500 font-semibold">Contact</label>
                                <p><?= htmlspecialchars($selected_artist['contact_number']) ?></p>
                            </div>
                            <div><label class="text-xs text-gray-500 font-semibold">Portfolio</label>
                                <p><a href="<?= htmlspecialchars($selected_artist['portfolio']) ?>" class="text-blue-600 underline" target="_blank">View Portfolio</a></p>
                            </div>
                        </div>
                        <div class="mt-4"><label class="text-xs text-gray-500 font-semibold">Product Description</label>
                            <div class="bg-gray-100 rounded p-4 mt-1 text-sm"><?= nl2br(htmlspecialchars($selected_artist['product_desc'])) ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center self-center">
                        <svg class="w-16 h-16 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        <p class="text-gray-400 mt-4">Select an application to view details.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <footer class="flex gap-4 p-4 border-t bg-white flex-shrink-0">
            <div id="drop-accepted" class="flex-1 py-4 rounded-lg text-center font-semibold bg-green-50 text-green-700 border-2 border-green-200 border-dashed cursor-pointer hover:bg-green-100" ondragover="event.preventDefault(); this.classList.add('bg-green-100')" ondragleave="this.classList.remove('bg-green-100')" ondrop="handleDrop(event, 'accepted')">
                <span class="inline-flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7" />
                    </svg>Drag to Accept</span>
            </div>
            <div id="drop-rejected" class="flex-1 py-4 rounded-lg text-center font-semibold bg-red-50 text-red-700 border-2 border-red-200 border-dashed cursor-pointer hover:bg-red-100" ondragover="event.preventDefault(); this.classList.add('bg-red-100')" ondragleave="this.classList.remove('bg-red-100')" ondrop="handleDrop(event, 'rejected')">
                <span class="inline-flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M6 18L18 6M6 6l12 12" />
                    </svg>Drag to Reject</span>
            </div>
        </footer>
    </main>
    <script>
        // Sidebar toggle for mobile
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        if (sidebar && sidebarToggle && sidebarOverlay) {
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

        let draggedAppId = null;

        function handleDragStart(event, appId) {
            draggedAppId = appId;
            event.dataTransfer.effectAllowed = "move";
        }

        function handleDrop(event, status) {
            event.preventDefault();
            if (!draggedAppId) return;
            const card = document.getElementById('app-card-' + draggedAppId);
            if (card) {
                card.style.opacity = 0.5;
            }
            fetch('update_application_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: draggedAppId,
                        status: status
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Action successful! The application has been processed.');
                        window.location.reload();
                    } else {
                        alert('An error occurred: ' + (data.error || 'Please try again.'));
                        if (card) {
                            card.style.opacity = 1;
                        }
                    }
                })
                .catch(error => {
                    alert('A network error occurred. Please check the console.');
                    if (card) {
                        card.style.opacity = 1;
                    }
                });
            draggedAppId = null;
        }
    </script>
</body>

</html>