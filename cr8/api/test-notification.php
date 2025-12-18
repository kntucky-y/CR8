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
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$conn = getDbConnection();
$user_id = $_SESSION['user_id'];

// Create table if not exists
$create_table = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$result = $conn->query($create_table);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Failed to create table: ' . $conn->error]);
    exit;
}

// Try to insert a test notification
$title = "Test Notification";
$message = "This is a test notification created at " . date('Y-m-d H:i:s');
$type = "test";

$stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $user_id, $type, $title, $message);

if ($stmt->execute()) {
    $notification_id = $stmt->insert_id;
    echo json_encode([
        'success' => true, 
        'message' => 'Test notification created',
        'notification_id' => $notification_id,
        'user_id' => $user_id
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to insert notification: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
