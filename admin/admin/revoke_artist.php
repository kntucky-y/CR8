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
    // --- REMOVED ---
    // The old safety check that prevented revocation is no longer needed.

    // --- NEW: Step 1 ---
    // Deactivate all of the artist's products by setting is_active to 0.
    $deactivate_sql = "UPDATE products SET is_active = 0 WHERE artist_id = ?";
    $deactivate_stmt = $conn->prepare($deactivate_sql);
    $deactivate_stmt->bind_param("i", $artist_id);
    $deactivate_stmt->execute();
    $deactivate_stmt->close();

    // --- Step 2: Get the user_id associated with this artist profile ---
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

    // --- Step 3: "Soft delete" the artist by revoking their status ---
    $revoke_sql = "UPDATE artists SET status = 'revoked' WHERE id = ?";
    $revoke_stmt = $conn->prepare($revoke_sql);
    $revoke_stmt->bind_param("i", $artist_id);
    $revoke_stmt->execute();
    $revoke_stmt->close();

    // --- Step 4: Update the user's role back to 'customer' ---
    $update_user_sql = "UPDATE users SET role = 'customer' WHERE id = ?";
    $update_user_stmt = $conn->prepare($update_user_sql);
    $update_user_stmt->bind_param("i", $user_id);
    $update_user_stmt->execute();
    $update_user_stmt->close();

    // If all steps succeeded, commit the changes
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Artist has been revoked and all their products have been deactivated.']);

} catch (Exception $e) {
    // If any step failed, roll back all database changes
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>