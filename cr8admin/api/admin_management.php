<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    sendError('Unauthorized', 401);
}

// Check if user is superadmin
if ($_SESSION['is_superadmin'] != 1) {
    sendError('Forbidden - Superadmin only', 403);
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset();
    session_destroy();
    sendError('Session expired', 401);
}
$_SESSION['LAST_ACTIVITY'] = time();

$conn = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Fetch all admins
if ($method === 'GET') {
    $result = $conn->query("SELECT id, username, password, is_superadmin, last_signed_in, last_signed_out FROM admins ORDER BY is_superadmin DESC, id ASC");
    $admins = [];
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }

    $conn->close();
    sendResponse(['admins' => $admins]);
}

// POST - Add new admin
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'add') {
    $data = json_decode(file_get_contents('php://input'), true);
    $new_username = trim($data['username'] ?? '');
    $new_password = trim($data['password'] ?? '');

    if (empty($new_username) || empty($new_password)) {
        sendError('Username and password are required', 400);
    }

    // Check for duplicate username
    $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
    $stmt->bind_param("s", $new_username);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $conn->close();
        sendError('Username already exists', 400);
    }
    $stmt->close();

    // Insert new admin
    $stmt = $conn->prepare("INSERT INTO admins (username, password, is_superadmin) VALUES (?, ?, 0)");
    $stmt->bind_param("ss", $new_username, $new_password);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();

    if ($success) {
        sendResponse(['success' => true]);
    } else {
        sendError('Failed to add admin', 500);
    }
}

// DELETE - Delete admin
if ($method === 'DELETE' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];

    // Check if target is superadmin
    $stmt = $conn->prepare("SELECT is_superadmin FROM admins WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->bind_result($is_superadmin);
    $stmt->fetch();
    $stmt->close();

    if ($is_superadmin == 1) {
        $conn->close();
        sendError('Cannot delete superadmin', 400);
    }

    // Delete admin
    $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $success = $stmt->execute();
    $stmt->close();

    // Reset auto-increment
    $conn->query("ALTER TABLE admins AUTO_INCREMENT = 1");
    $conn->close();

    if ($success) {
        sendResponse(['success' => true]);
    } else {
        sendError('Failed to delete admin', 500);
    }
}

sendError('Invalid request', 400);
