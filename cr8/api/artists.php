<?php
require_once 'config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$conn = getDbConnection();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get':
        $stmt = $conn->prepare("SELECT id, artist_name FROM artists WHERE status = 'active' ORDER BY artist_name ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        $artists = [];
        
        while ($row = $result->fetch_assoc()) {
            $artists[] = $row;
        }
        
        echo json_encode(['success' => true, 'artists' => $artists]);
        $stmt->close();
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
