<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artist Applications | CR8 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        /* Style for the currently selected application */
        .message-active {
            background-color: #f3e8ff; /* Light purple */
            border-right: 4px solid #7A1CAC; /* Darker purple */
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
    <aside class="w-64 bg-white border-r flex-shrink-0 hidden md:flex flex-col justify-between">
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
            <h1 class="font-bold text-2xl text-gray-800">Artist Applications</h1>
        </header>
        <section class="flex-1 flex bg-white">
            <div class="w-96 border-r flex flex-col">
                <div class="p-4 border-b">
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
                                    <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="6" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="18" cy="12" r="1.5"/></svg>
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
            <div class="flex-1 flex flex-col p-8 bg-gray-50 overflow-y-auto">
                <?php if ($selected_artist): ?>
                    <div class="bg-white shadow-xl rounded-xl p-8 w-full max-w-3xl mx-auto">
                        <div class="flex items-center gap-4 mb-6 pb-4 border-b">
                            <div>
                                <h2 class="text-2xl font-bold"><?= htmlspecialchars($selected_artist['full_name']) ?></h2>
                                <p class="text-purple-700 font-semibold"><?= htmlspecialchars($selected_artist['artist_name']) ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 mb-4">
                            <div><label class="text-xs text-gray-500 font-semibold">Email</label><p><a href="mailto:<?= htmlspecialchars($selected_artist['email']) ?>" class="text-blue-600"><?= htmlspecialchars($selected_artist['email']) ?></a></p></div>
                            <div><label class="text-xs text-gray-500 font-semibold">Contact</label><p><?= htmlspecialchars($selected_artist['contact_number']) ?></p></div>
                            <div><label class="text-xs text-gray-500 font-semibold">Portfolio</label><p><a href="<?= htmlspecialchars($selected_artist['portfolio']) ?>" class="text-blue-600 underline" target="_blank">View Portfolio</a></p></div>
                        </div>
                        <div class="mt-4"><label class="text-xs text-gray-500 font-semibold">Product Description</label><div class="bg-gray-100 rounded p-4 mt-1 text-sm"><?= nl2br(htmlspecialchars($selected_artist['product_desc'])) ?></div></div>
                    </div>
                <?php else: ?>
                    <div class="text-center self-center">
                        <svg class="w-16 h-16 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <p class="text-gray-400 mt-4">Select an application to view details.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <footer class="flex gap-4 p-4 border-t bg-white">
            <div id="drop-accepted" class="flex-1 py-4 rounded-lg text-center font-semibold bg-green-50 text-green-700 border-2 border-green-200 border-dashed cursor-pointer hover:bg-green-100" ondragover="event.preventDefault(); this.classList.add('bg-green-100')" ondragleave="this.classList.remove('bg-green-100')" ondrop="handleDrop(event, 'accepted')">
                <span class="inline-flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>Drag to Accept</span>
            </div>
            <div id="drop-rejected" class="flex-1 py-4 rounded-lg text-center font-semibold bg-red-50 text-red-700 border-2 border-red-200 border-dashed cursor-pointer hover:bg-red-100" ondragover="event.preventDefault(); this.classList.add('bg-red-100')" ondragleave="this.classList.remove('bg-red-100')" ondrop="handleDrop(event, 'rejected')">
                <span class="inline-flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>Drag to Reject</span>
            </div>
        </footer>
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

let draggedAppId = null;
function handleDragStart(event, appId) { draggedAppId = appId; event.dataTransfer.effectAllowed = "move"; }
function handleDrop(event, status) {
    event.preventDefault();
    if (!draggedAppId) return;
    const card = document.getElementById('app-card-' + draggedAppId);
    if (card) { card.style.opacity = 0.5; }
    fetch('update_application_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id: draggedAppId, status: status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Action successful! The application has been processed.');
            window.location.reload(); 
        } else {
            alert('An error occurred: ' + (data.error || 'Please try again.'));
            if (card) { card.style.opacity = 1; }
        }
    })
    .catch(error => {
        alert('A network error occurred. Please check the console.');
        if (card) { card.style.opacity = 1; }
    });
    draggedAppId = null; 
}
</script>
</body>
</html>