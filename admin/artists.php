<?php
session_start();
$current_page = 'artists';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Connect to the database
$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Fetch all artists with their user data and product count
$artists_sql = "
    SELECT
        a.id,
        a.artist_name,
        u.email,
        u.created_at as join_date,
        COUNT(p.id) as product_count
    FROM
        artists a
    LEFT JOIN
        users u ON a.user_id = u.id
    LEFT JOIN
        products p ON a.id = p.artist_id
    GROUP BY
        a.id, a.artist_name, u.email, u.created_at
    ORDER BY
        a.artist_name ASC;
";
$artists_result = $conn->query($artists_sql);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Artists | CR8 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="../img/favicon.png" type="image/png">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .modal-enter { opacity: 0; transform: scale(0.95); }
        .modal-enter-active { opacity: 1; transform: scale(1); transition: all 300ms; }
        .modal-leave-active { opacity: 0; transform: scale(0.95); transition: all 300ms; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex">
<aside class="w-64 bg-white border-r flex-shrink-0 hidden md:flex flex-col justify-between">
        <div>
            <div class="flex items-center gap-2 px-6 py-6 border-b">
                <img src="../img/cr8-logo.png" alt="Logo" class="w-10 h-10 rounded-full">
                <span class="font-bold text-xl text-purple-800">CR8 Cebu</span>
            </div>
            <!-- *** FIX APPLIED: Restored the "Orders" link and corrected all conditional classes *** -->
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
             <h1 class="font-bold text-2xl text-gray-800">All Artists</h1>
        </header>

        <section class="p-8">
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="artists-table" class="w-full text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-4 font-semibold text-gray-600">Artist Name</th>
                                <th class="p-4 font-semibold text-gray-600">Email Address</th>
                                <th class="p-4 font-semibold text-gray-600 text-center">Products Listed</th>
                                <th class="p-4 font-semibold text-gray-600">Date Joined</th>
                                <th class="p-4 font-semibold text-gray-600 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($artists_result && $artists_result->num_rows > 0): ?>
                                <?php while($artist = $artists_result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-4 font-medium text-gray-800"><?= htmlspecialchars($artist['artist_name']) ?></td>
                                        <td class="p-4 text-gray-600"><?= htmlspecialchars($artist['email']) ?></td>
                                        <td class="p-4 text-gray-600 text-center"><?= $artist['product_count'] ?></td>
                                        <td class="p-4 text-gray-600"><?= date('F j, Y', strtotime($artist['join_date'])) ?></td>
                                        <td class="p-4 text-center space-x-4">
                                            <button data-id="<?= $artist['id'] ?>" class="view-profile-btn text-purple-600 hover:text-purple-800 font-semibold">View Profile</button>
                                            <!-- *** NEW: Revoke Button *** -->
                                            <button data-id="<?= $artist['id'] ?>" data-name="<?= htmlspecialchars($artist['artist_name']) ?>" class="revoke-artist-btn text-red-600 hover:text-red-800 font-semibold">Revoke</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center p-8 text-gray-500">No artists found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <div id="artist-profile-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
        <div id="modal-content-wrapper" class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto relative modal-enter">
            <button id="modal-close-btn" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 z-10">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
            <div id="modal-content" class="p-8">
                <p class="text-center text-gray-500">Loading artist details...</p>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('artists-table');
    const modal = document.getElementById('artist-profile-modal');
    const modalContentWrapper = document.getElementById('modal-content-wrapper');
    const modalContent = document.getElementById('modal-content');
    const closeModalBtn = document.getElementById('modal-close-btn');

    const openModal = () => {
        modal.classList.remove('hidden');
        setTimeout(() => modalContentWrapper.classList.add('modal-enter-active'), 10);
    };

    const closeModal = () => {
        modalContentWrapper.classList.remove('modal-enter-active');
        modalContentWrapper.classList.add('modal-leave-active');
        setTimeout(() => {
            modal.classList.add('hidden');
            modalContentWrapper.classList.remove('modal-leave-active');
        }, 300);
    };

    table.addEventListener('click', async (e) => {
        const target = e.target;
        if (target.classList.contains('view-profile-btn')) {
            const artistId = target.dataset.id;
            modalContent.innerHTML = '<p class="text-center text-gray-500">Loading artist details...</p>';
            openModal();
            try {
                const response = await fetch(`get_artist_details.php?id=${artistId}`);
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                const data = await response.json();
                if (data.error) throw new Error(data.error);

                let productsHTML = '';
                if (data.products && data.products.length > 0) {
                    data.products.forEach(p => {
                        const imageUrl = `https://cr8.dcism.org/${p.image.replace(/\\/g, '/')}`;
                        const fallbackUrl = `https://cr8.dcism.org/img/default-product.png`;
                        productsHTML += `
                            <tr class="border-b">
                                <td class="p-2"><img src="${imageUrl}" class="w-12 h-12 object-cover rounded-md" onerror="this.src='${fallbackUrl}'"></td>
                                <td class="p-2 font-semibold">${p.product_name}</td>
                                <td class="p-2 text-center">${p.units_sold}</td>
                                <td class="p-2 text-right">₱${Number(p.product_revenue).toFixed(2)}</td>
                            </tr>`;
                    });
                } else {
                    productsHTML = '<tr><td colspan="4" class="text-center p-4 text-gray-500">No products sold yet.</td></tr>';
                }

                modalContent.innerHTML = `
                    <div class="flex items-center justify-between pb-4 border-b mb-4">
                        <div>
                            <h2 class="text-3xl font-bold text-gray-800">${data.artist.artist_name}</h2>
                            <p class="text-sm text-gray-500">Joined on ${new Date(data.artist.join_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-500">Total Revenue</p>
                            <p class="text-2xl font-bold text-green-600">₱${Number(data.artist.total_revenue).toFixed(2)}</p>
                        </div>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">Product Sales</h3>
                    <div class="overflow-y-auto max-h-64">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100"><tr><th class="p-2 text-left"></th><th class="p-2 text-left">Product</th><th class="p-2 text-center">Units Sold</th><th class="p-2 text-right">Revenue</th></tr></thead>
                            <tbody>${productsHTML}</tbody>
                        </table>
                    </div>`;
            } catch (error) {
                modalContent.innerHTML = `<p class="text-center text-red-500">Failed to load details: ${error.message}</p>`;
            }
        }
        
        // *** NEW: Handle Revoke Button Click ***
        if (target.classList.contains('revoke-artist-btn')) {
            const artistId = target.dataset.id;
            const artistName = target.dataset.name;
            
            if (confirm(`Are you sure you want to revoke artist privileges for "${artistName}"? This cannot be undone.`)) {
                try {
                    const response = await fetch('revoke_artist.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ artist_id: artistId })
                    });
                    const data = await response.json();
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        throw new Error(data.error);
                    }
                } catch (error) {
                    alert(`An error occurred: ${error.message}`);
                }
            }
        }
    });

    closeModalBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });
});
</script>

</body>
</html>
