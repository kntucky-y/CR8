<?php
// Set the response header to JSON
header('Content-Type: application/json');

// --- 1. Get Input Data ---
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !in_array($data['status'], ['accepted', 'rejected'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input provided.']);
    exit;
}

$application_id = intval($data['id']);
$new_status = $data['status'];

// --- 2. Database Connection ---
$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// --- 3. Fetch Application Details ---
$stmt = $conn->prepare("SELECT email, artist_name FROM artist_applications WHERE id = ?");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Application not found.']);
    $stmt->close();
    $conn->close();
    exit;
}
$application = $result->fetch_assoc();
$applicant_email = $application['email'];
$artist_name = $application['artist_name'];
$stmt->close();


// --- 4. Process Status Change ---
$conn->begin_transaction();

try {
    // If accepted, perform the database updates.
    if ($new_status === 'accepted') {
        // Find the user_id from the `users` table based on their email.
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $applicant_email);
        $stmt->execute();
        $user_result = $stmt->get_result();
        if ($user_result->num_rows === 0) {
            throw new Exception("User account not found for email: " . $applicant_email);
        }
        $user = $user_result->fetch_assoc();
        $user_id = $user['id'];
        $stmt->close();

        // Update the user's role to 'artist'
        $stmt = $conn->prepare("UPDATE users SET role = 'artist' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Create a new entry in the 'artists' table
        $stmt = $conn->prepare("INSERT INTO artists (user_id, artist_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE artist_name = VALUES(artist_name)");
        $stmt->bind_param("is", $user_id, $artist_name);
        $stmt->execute();
        $stmt->close();
    }
    
    // *** NEW: Delete the application from the inbox after processing ***
    // This runs for both 'accepted' and 'rejected' statuses.
    $stmt = $conn->prepare("DELETE FROM artist_applications WHERE id = ?");
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $stmt->close();

    // If all database queries were successful, save the changes.
    $conn->commit();

} catch (Exception $e) {
    // If any step fails, undo all changes.
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $e->getMessage()]);
    $conn->close();
    exit;
}

// --- 5. Final Success Response ---
echo json_encode(['success' => true, 'message' => "Database updated successfully. Application has been processed and removed from inbox."]);

$conn->close();
