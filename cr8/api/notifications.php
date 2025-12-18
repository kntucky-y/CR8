<?php
require_once 'config.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '.dcism.org',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);
    session_start();
}

$conn = getDbConnection();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// Read JSON input for POST requests
$json_input = file_get_contents('php://input');
$post_data = json_decode($json_input, true) ?? [];

// Create notifications table if not exists
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
    INDEX idx_user_id (user_id)
)";
$conn->query($create_table);

switch ($action) {
    case 'list':
        $stmt = $conn->prepare("
            SELECT id, type, title, message, related_id, is_read, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        
        while ($row = $result->fetch_assoc()) {
            $row['is_read'] = (bool)$row['is_read'];
            $notifications[] = $row;
        }
        
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        $stmt->close();
        break;

    case 'unread-count':
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM notifications
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        echo json_encode(['success' => true, 'count' => (int)$row['count']]);
        $stmt->close();
        break;

    case 'mark-read':
        $notification_id = $post_data['notification_id'] ?? $_POST['notification_id'] ?? 0;
        
        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = TRUE
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        $stmt->close();
        break;

    case 'mark-all-read':
        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = TRUE
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
?>
