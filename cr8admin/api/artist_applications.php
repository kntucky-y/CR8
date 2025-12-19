<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    sendError('Unauthorized', 401);
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset();
    session_destroy();
    sendError('Session expired', 401);
}
$_SESSION['LAST_ACTIVITY'] = time();

$conn = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

// GET - Fetch all applications
if ($method === 'GET' && !isset($_GET['id'])) {
    $search = $_GET['search'] ?? '';
    $filter = $_GET['filter'] ?? 'active'; // active, archived, all
    
    $sql = "SELECT id, full_name, product_desc, submitted_at, status, COALESCE(is_archived, 0) as is_archived FROM artist_applications";
    $where_clauses = [];
    
    if ($filter === 'active') {
        // Active = pending/unread/read only (not archived, not accepted, not rejected)
        $where_clauses[] = "COALESCE(is_archived, 0) = 0";
        $where_clauses[] = "status NOT IN ('Approved', 'Rejected')";
    } elseif ($filter === 'archived') {
        // Archived = manually archived OR rejected
        $where_clauses[] = "is_archived = 1";
    } elseif ($filter === 'all') {
        // All = not archived (shows active + accepted, but not rejected)
        $where_clauses[] = "COALESCE(is_archived, 0) = 0";
    }
    
    if (!empty($search)) {
        $where_clauses[] = "full_name LIKE ?";
    }
    
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $sql .= " ORDER BY submitted_at DESC";
    
    if (!empty($search)) {
        $searchTerm = "%{$search}%";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $applications = [];
    while ($row = $result->fetch_assoc()) {
        // Convert submitted_at to ISO 8601 format with timezone
        if (isset($row['submitted_at'])) {
            $date = new DateTime($row['submitted_at'], new DateTimeZone('Asia/Manila'));
            $row['submitted_at'] = $date->format('c'); // ISO 8601 format
        }
        $applications[] = $row;
    }

    if (!empty($search)) {
        $stmt->close();
    }
    $conn->close();
    sendResponse(['applications' => $applications]);
}

// GET - Fetch single application and mark as read
if ($method === 'GET' && isset($_GET['id'])) {
    $app_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM artist_applications WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Mark as read if unread
    if ($application && $application['status'] == 'Unread') {
        $update_stmt = $conn->prepare("UPDATE artist_applications SET status = 'Read' WHERE id = ?");
        $update_stmt->bind_param("i", $app_id);
        $update_stmt->execute();
        $update_stmt->close();
        $application['status'] = 'Read';
    }

    // Convert submitted_at to ISO 8601 format with timezone
    if ($application && isset($application['submitted_at'])) {
        $date = new DateTime($application['submitted_at'], new DateTimeZone('Asia/Manila'));
        $application['submitted_at'] = $date->format('c'); // ISO 8601 format
    }

    $conn->close();
    sendResponse(['application' => $application]);
}

// POST - Update application status
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update_status') {
    $data = json_decode(file_get_contents('php://input'), true);
    $app_id = (int)$data['application_id'];
    $status = $data['status'];
    $rejection_reason = trim($data['rejection_reason'] ?? '');

    // Map lowercase status to proper ENUM values
    $statusMap = [
        'accepted' => 'Approved',
        'rejected' => 'Rejected',
        'pending' => 'Unread'
    ];
    
    $dbStatus = $statusMap[$status] ?? ucfirst($status);
    
    // Debug logging
    error_log("Update status called - App ID: $app_id, Status: $status -> DB Status: $dbStatus, Reason: $rejection_reason");

    // Get application details for notification
    $app_stmt = $conn->prepare("SELECT user_id, artist_name, full_name FROM artist_applications WHERE id = ?");
    $app_stmt->bind_param("i", $app_id);
    $app_stmt->execute();
    $app_result = $app_stmt->get_result();
    $application = $app_result->fetch_assoc();
    $app_stmt->close();

    // Archive rejected applications automatically
    $is_archived = ($status === 'rejected') ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE artist_applications SET status = ?, is_archived = ?, rejection_reason = ? WHERE id = ?");
    $stmt->bind_param("sisi", $dbStatus, $is_archived, $rejection_reason, $app_id);
    $success = $stmt->execute();
    $affected = $stmt->affected_rows;
    
    error_log("Update result - Success: " . ($success ? 'yes' : 'no') . ", Affected rows: $affected");
    
    $stmt->close();

    // Create notification for user
    if ($success && $application) {
        $user_id = $application['user_id'];
        
        if ($status === 'accepted') {
            // Check if artist record exists (including archived ones)
            $artist_check = $conn->prepare("SELECT id, is_archived FROM artists WHERE user_id = ?");
            $artist_check->bind_param("i", $user_id);
            $artist_check->execute();
            $artist_result = $artist_check->get_result();
            $artist_exists = $artist_result->num_rows > 0;
            $existing_artist = $artist_exists ? $artist_result->fetch_assoc() : null;
            $artist_check->close();
            
            error_log("Artist exists check - User ID: $user_id, Exists: " . ($artist_exists ? 'yes' : 'no'));
            
            if ($artist_exists && $existing_artist['is_archived'] == 1) {
                // Restore archived artist
                try {
                    $artist_restore = $conn->prepare("UPDATE artists SET is_archived = 0, status = 'active', artist_name = ?, revoke_reason = '' WHERE user_id = ?");
                    $artist_restore->bind_param("si", $application['artist_name'], $user_id);
                    $restore_result = $artist_restore->execute();
                    error_log("Artist restore result - Success: " . ($restore_result ? 'yes' : 'no') . ", Affected rows: " . $artist_restore->affected_rows);
                    $artist_restore->close();
                    
                    if ($restore_result) {
                        // Update user role to 'artist'
                        $role_update = $conn->prepare("UPDATE users SET role = 'artist' WHERE id = ?");
                        $role_update->bind_param("i", $user_id);
                        $role_result = $role_update->execute();
                        error_log("Role update result - Success: " . ($role_result ? 'yes' : 'no') . ", Affected rows: " . $role_update->affected_rows);
                        $role_update->close();
                        
                        // Reactivate all products that were deactivated due to artist revocation
                        $artist_id = $existing_artist['id'];
                        $products_restore = $conn->prepare("UPDATE products SET is_active = 1, deactivation_reason = '' WHERE artist_id = ? AND deactivation_reason = 'Artist revoked'");
                        $products_restore->bind_param("i", $artist_id);
                        $products_restore->execute();
                        error_log("Products restore - Affected rows: " . $products_restore->affected_rows);
                        $products_restore->close();
                    }
                } catch (Exception $e) {
                    error_log("Error restoring artist: " . $e->getMessage());
                    sendResponse(['success' => false, 'error' => $e->getMessage()]);
                }
            } else if (!$artist_exists) {
                try {
                    $artist_insert = $conn->prepare("INSERT INTO artists (user_id, artist_name, status) VALUES (?, ?, 'active')");
                    $artist_insert->bind_param("is", $user_id, $application['artist_name']);
                    $artist_result = $artist_insert->execute();
                    
                    if (!$artist_result) {
                        error_log("Artist insert FAILED - Error: " . $artist_insert->error . ", Errno: " . $artist_insert->errno);
                    }
                    
                    $artist_id = $conn->insert_id;
                    error_log("Artist insert result - Success: " . ($artist_result ? 'yes' : 'no') . ", New artist ID: $artist_id, User ID: $user_id, Artist name: {$application['artist_name']}");
                    $artist_insert->close();
                    
                    if ($artist_result) {
                        // Update user role to 'artist'
                        $role_update = $conn->prepare("UPDATE users SET role = 'artist' WHERE id = ?");
                        $role_update->bind_param("i", $user_id);
                        $role_result = $role_update->execute();
                        error_log("Role update result - Success: " . ($role_result ? 'yes' : 'no') . ", Affected rows: " . $role_update->affected_rows);
                        $role_update->close();
                    }
                } catch (Exception $e) {
                    error_log("Error creating artist: " . $e->getMessage());
                }
            } else {
                error_log("Artist already exists for user_id: $user_id");
            }
            
            $title = "Artist Application Approved! 🎉";
            $message = "Congratulations! Your application as \"{$application['artist_name']}\" has been approved. You can now start uploading your products!";
            $type = "artist_approved";
        } elseif ($status === 'rejected') {
            $title = "Artist Application Update";
            $reason_text = !empty($rejection_reason) ? "\n\nReason: " . $rejection_reason : "";
            $message = "Thank you for your interest. Unfortunately, your application as \"{$application['artist_name']}\" was not approved at this time. You may reapply in the future." . $reason_text;
            $type = "artist_rejected";
        }

        if (isset($type)) {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)");
            $notif_stmt->bind_param("isssi", $user_id, $type, $title, $message, $app_id);
            $notif_stmt->execute();
            $notif_stmt->close();
        }
    }

    $conn->close();

    if ($success) {
        sendResponse(['success' => true]);
    } else {
        sendError('Failed to update status', 500);
    }
}

// POST - Restore from archive
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'restore') {
    $data = json_decode(file_get_contents('php://input'), true);
    $app_id = (int)$data['application_id'];
    
    // Restore and set back to Unread status
    $stmt = $conn->prepare("UPDATE artist_applications SET is_archived = 0, status = 'Unread' WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    $success = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    $conn->close();

    if ($success && $affected > 0) {
        sendResponse(['success' => true, 'message' => 'Application restored successfully']);
    } else {
        sendError('Failed to restore application - no rows updated', 500);
    }
}

sendError('Invalid request', 400);
