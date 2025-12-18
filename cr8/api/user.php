<?php
require_once 'config.php';

header('Access-Control-Allow-Origin: https://cr8.dcism.org');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'update-profile':
        $data = json_decode(file_get_contents('php://input'), true);
        $first_name = $data['first_name'] ?? '';
        $last_name = $data['last_name'] ?? '';
        $username = $data['username'] ?? '';
        $address = $data['address'] ?? '';
        $phone = $data['phone'] ?? '';

        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, address = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $first_name, $last_name, $username, $address, $phone, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['username'] = $username;
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Update failed']);
        }
        $stmt->close();
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
?>
