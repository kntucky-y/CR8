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
$artist_id = $data['artist_id'] ?? null;

if (!$artist_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Invalid Artist ID.']);
    exit;
}

$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

$conn->begin_transaction();

try {
    // Safety Check: See if the artist has any products.
    $check_sql = "SELECT COUNT(*) as product_count FROM products WHERE artist_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $artist_id);
    $check_stmt->execute();
    $product_count = $check_stmt->get_result()->fetch_assoc()['product_count'];
    $check_stmt->close();

    if ($product_count > 0) {
        // If they have products, do not proceed. Throw an error.
        throw new Exception("Cannot revoke privileges. This artist still has {$product_count} product(s) listed. Please delete or reassign them first.");
    }

    // Get the user_id associated with this artist profile before deleting it
    $user_id_sql = "SELECT user_id FROM artists WHERE id = ?";
    $user_id_stmt = $conn->prepare($user_id_sql);
    $user_id_stmt->bind_param("i", $artist_id);
    $user_id_stmt->execute();
    $user_id_result = $user_id_stmt->get_result();
    if ($user_id_result->num_rows === 0) {
        throw new Exception("Artist profile not found.");
    }
    $user_id = $user_id_result->fetch_assoc()['user_id'];
    $user_id_stmt->close();

    // If no products, proceed with demotion
    // 1. Delete the entry from the 'artists' table
    $delete_sql = "DELETE FROM artists WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $artist_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    // 2. Update the user's role in the 'users' table back to 'customer'
    $update_sql = "UPDATE users SET role = 'customer' WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();

    // If all steps succeeded, commit the changes
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Artist privileges have been revoked.']);

} catch (Exception $e) {
    // If any step failed, roll back all database changes
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
