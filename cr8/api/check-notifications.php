<?php
require_once 'config.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.dcism.org',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated', 'user_id' => null]);
    exit;
}

$conn = getDbConnection();
$user_id = $_SESSION['user_id'];

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
$table_exists = $table_check->num_rows > 0;

// Get all notifications for this user
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total = $count_result->fetch_assoc()['total'];

echo json_encode([
    'success' => true,
    'user_id' => $user_id,
    'table_exists' => $table_exists,
    'total_notifications' => $total,
    'notifications' => $notifications
]);

$stmt->close();
$count_stmt->close();
$conn->close();
?>
