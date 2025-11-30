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
$product_id = $data['product_id'] ?? null;
$new_status = $data['status'] ?? null;

// --- MODIFIED: Validate all inputs ---
if (!$product_id || !is_numeric($product_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Invalid Product ID.']);
    exit;
}

if ($new_status === null || !in_array($new_status, [0, 1])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Invalid status provided.']);
    exit;
}


$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// --- MODIFIED: Dynamic UPDATE query ---
$sql = "UPDATE products SET is_active = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
// Bind the new status (0 or 1) and the product ID
$stmt->bind_param("ii", $new_status, $product_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // --- MODIFIED: Dynamic success message ---
        $message = ($new_status == 1) ? 'Product has been reactivated.' : 'Product has been deactivated.';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Product not found or status is already unchanged.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update the product status.']);
}

$stmt->close();
$conn->close();
?>
