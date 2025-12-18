<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    sendError('Unauthorized', 401);
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset();
    session_destroy();
    sendError('Session expired', 401);
}
$_SESSION['LAST_ACTIVITY'] = time();

$conn = getDbConnection();

// Get counts for notifications
$unread_messages_count = $conn->query("SELECT COUNT(*) as count FROM messages WHERE status = 'Unread' AND COALESCE(is_archived, 0) = 0")->fetch_assoc()['count'];
$pending_apps_count = $conn->query("SELECT COUNT(*) as count FROM artist_applications WHERE status NOT IN ('Approved', 'Rejected') AND COALESCE(is_archived, 0) = 0")->fetch_assoc()['count'];
$pending_orders_count = $conn->query("SELECT COUNT(*) as count FROM delivery WHERE status = 'For Review'")->fetch_assoc()['count'];

$conn->close();
sendResponse([
    'unread_messages_count' => $unread_messages_count,
    'pending_apps_count' => $pending_apps_count,
    'pending_orders_count' => $pending_orders_count
]);
