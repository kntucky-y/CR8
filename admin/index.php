
<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Connect to DB
    $conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
    if ($conn->connect_error) {
        $error = "Database connection failed.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, is_superadmin FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        // Plain text password check (not secure, for demo only)
        if ($admin && $password === $admin['password']) {
            // Update last_signed_in field
            $update_stmt = $conn->prepare("UPDATE admins SET last_signed_in = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $admin['id']);
            $update_stmt->execute();
            $update_stmt->close();

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['is_superadmin'] = $admin['is_superadmin'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | CR8 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="img/favicon.png" type="image/png">
    <style>
        body { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col md:flex-row">
    <!-- Logo/Brand Section -->
    <div class="w-full md:w-64 bg-white border-b md:border-b-0 md:border-r flex flex-col items-center justify-center py-8 md:min-h-screen">
        <img src="img/cr8-logo.png" alt="Logo" class="w-20 h-20 rounded-full mb-4">
        <span class="font-bold text-2xl text-purple-800">CR8 Cebu</span>
    </div>
    <!-- Login Form Section -->
    <main class="flex-1 flex items-center justify-center py-8">
        <div class="bg-white rounded-xl shadow-xl p-6 sm:p-10 w-full max-w-md flex flex-col items-center">
            <div class="bg-purple-100 rounded-full p-3 mb-4">
                <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4m0-4h.01" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2 text-center">Admin Login</h1>
            <p class="text-gray-500 text-center mb-6">Access the artist application management system</p>
            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4 w-full text-center"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" class="w-full flex flex-col gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Username</label>
                    <input type="text" name="username" placeholder="Enter your username" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-purple-400" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Password</label>
                    <input type="password" name="password" placeholder="Enter your password" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-purple-400" required>
                </div>
                <button type="submit" class="bg-purple-700 text-white font-bold py-2 rounded-md mt-2 hover:bg-purple-800 transition">Sign In</button>
            </form>
        </div>
    </main>
</body>
</html>