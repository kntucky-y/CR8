<?php
session_start();
header('Content-Type: application/json');

// Security: Ensure only a logged-in admin can perform this action
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$order_id = $data['order_id'] ?? null;
$status = $data['status'] ?? null;

if (!$order_id || !in_array($status, ['Processing', 'Shipped', 'Completed', 'Cancelled'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Invalid data provided.']);
    exit;
}

// Connect to the database directly
$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// Use INSERT ... ON DUPLICATE KEY UPDATE to either create or update the status
// This is safe because if a status already exists, it updates it. If not, it creates it.
$sql = "INSERT INTO delivery (order_id, status) VALUES (?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $order_id, $status);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Order status updated successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update status in the database.']);
}

$stmt->close();
$conn->close();
