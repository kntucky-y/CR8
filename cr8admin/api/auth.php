<?php
session_start();
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Login
if ($method === 'POST' && !isset($_GET['action'])) {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    if (empty($username) || empty($password)) {
        sendError('Username and password are required', 400);
    }

    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id, username, password, is_superadmin FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin && $password === $admin['password']) {
        // Update last_signed_in
        $update_stmt = $conn->prepare("UPDATE admins SET last_signed_in = NOW() WHERE id = ?");
        $update_stmt->bind_param("i", $admin['id']);
        $update_stmt->execute();
        $update_stmt->close();

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['is_superadmin'] = $admin['is_superadmin'];
        $_SESSION['LAST_ACTIVITY'] = time();

        sendResponse([
            'success' => true,
            'admin' => [
                'id' => $admin['id'],
                'username' => $admin['username'],
                'is_superadmin' => $admin['is_superadmin']
            ]
        ]);
    } else {
        sendError('Invalid username or password', 401);
    }
}

// Logout
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_SESSION['admin_id'])) {
        $conn = getDbConnection();
        $stmt = $conn->prepare("UPDATE admins SET last_signed_out = NOW() WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }
    session_unset();
    session_destroy();
    sendResponse(['success' => true]);
}

// Check session
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check') {
    // Check inactivity timeout (10 minutes)
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
        session_unset();
        session_destroy();
        sendResponse(['authenticated' => false]);
    }
    $_SESSION['LAST_ACTIVITY'] = time();

    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        sendResponse([
            'authenticated' => true,
            'admin' => [
                'id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'is_superadmin' => $_SESSION['is_superadmin']
            ]
        ]);
    } else {
        sendResponse(['authenticated' => false]);
    }
}

sendError('Invalid request', 400);
