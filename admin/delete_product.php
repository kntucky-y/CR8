<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$product_id = $data['product_id'] ?? null;

if (!$product_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid Product ID.']);
    exit;
}

$conn = @new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// First, delete from carts
$stmt = $conn->prepare("DELETE FROM carts WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$stmt->close();

// Then, delete from products
$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Product deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Product not found or could not be deleted.']);
}
$stmt->close();
$conn->close();

// Fallback: If nothing was output, output a generic error
if (!headers_sent()) {
    echo json_encode(['success' => false, 'error' => 'Unknown error occurred.']);
}
?>

<script>
fetch('delete_product.php', {
    method: 'DELETE',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + yourAuthToken // Replace with your actual token
    },
    body: JSON.stringify({ product_id: yourProductId }) // Replace with the actual product ID
})
.then(response => {
    if (!response.ok) throw new Error('Network error');
    return response.json();
})
.then(data => {
    if (data.success) {
        console.log('Product deleted:', data.message);
        // Optionally, refresh the product list or update the UI
    } else {
        console.error('Error deleting product:', data.error);
    }
})
.catch(err => {
    console.error('Fetch error:', err);
});
</script>
