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
$input = json_decode(file_get_contents('php://input'), true);

// POST - Bulk restore applications
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'restore_applications') {
    $ids = $input['ids'] ?? [];
    
    if (empty($ids) || !is_array($ids)) {
        sendError('No IDs provided');
    }
    
    // Sanitize IDs
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $conn->begin_transaction();
    
    try {
        // Restore applications
        $stmt = $conn->prepare("UPDATE artist_applications SET is_archived = 0, status = 'Unread' WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to restore applications');
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        // Get affected user_ids and artist_names
        $user_stmt = $conn->prepare("SELECT user_id, artist_name FROM artist_applications WHERE id IN ($placeholders)");
        $user_stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $user_stmt->execute();
        $result = $user_stmt->get_result();
        
        // Restore artists and their products
        while ($row = $result->fetch_assoc()) {
            $user_id = $row['user_id'];
            $artist_name = $row['artist_name'];
            
            // Check if artist exists and is archived
            $check = $conn->prepare("SELECT id FROM artists WHERE user_id = ? AND is_archived = 1");
            $check->bind_param("i", $user_id);
            $check->execute();
            $check_result = $check->get_result();
            
            if ($check_result->num_rows > 0) {
                $artist = $check_result->fetch_assoc();
                $artist_id = $artist['id'];
                
                // Restore artist
                $artist_restore = $conn->prepare("UPDATE artists SET is_archived = 0, status = 'active', artist_name = ?, revoke_reason = '' WHERE user_id = ?");
                $artist_restore->bind_param("si", $artist_name, $user_id);
                $artist_restore->execute();
                $artist_restore->close();
                
                // Restore products
                $products_restore = $conn->prepare("UPDATE products SET is_active = 1, deactivation_reason = '' WHERE artist_id = ? AND deactivation_reason = 'Artist revoked'");
                $products_restore->bind_param("i", $artist_id);
                $products_restore->execute();
                $products_restore->close();
            }
            $check->close();
        }
        
        $user_stmt->close();
        
        $conn->commit();
        sendResponse(['success' => true, 'message' => "$affected application(s) restored successfully"]);
        
    } catch (Exception $e) {
        $conn->rollback();
        sendError($e->getMessage(), 500);
    }
}

// POST - Bulk delete applications
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete_applications') {
    $ids = $input['ids'] ?? [];
    
    if (empty($ids) || !is_array($ids)) {
        sendError('No IDs provided');
    }
    
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $conn->prepare("DELETE FROM artist_applications WHERE id IN ($placeholders) AND is_archived = 1");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        sendResponse(['success' => true, 'message' => "$affected application(s) deleted permanently"]);
    } else {
        sendError('Failed to delete applications', 500);
    }
}

// POST - Bulk restore artists
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'restore_artists') {
    $ids = $input['ids'] ?? [];
    
    if (empty($ids) || !is_array($ids)) {
        sendError('No IDs provided');
    }
    
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $conn->begin_transaction();
    
    try {
        // Restore artists
        $stmt = $conn->prepare("UPDATE artists SET is_archived = 0, status = 'active', revoke_reason = '' WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to restore artists');
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        // Restore products for each artist
        foreach ($ids as $artist_id) {
            $products_restore = $conn->prepare("UPDATE products SET is_active = 1, deactivation_reason = '' WHERE artist_id = ? AND deactivation_reason = 'Artist revoked'");
            $products_restore->bind_param("i", $artist_id);
            $products_restore->execute();
            $products_restore->close();
        }
        
        $conn->commit();
        sendResponse(['success' => true, 'message' => "$affected artist(s) restored successfully"]);
        
    } catch (Exception $e) {
        $conn->rollback();
        sendError($e->getMessage(), 500);
    }
}

// POST - Bulk delete artists
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete_artists') {
    $ids = $input['ids'] ?? [];
    
    if (empty($ids) || !is_array($ids)) {
        sendError('No IDs provided');
    }
    
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // Note: This will cascade delete products if foreign key is set
    $stmt = $conn->prepare("DELETE FROM artists WHERE id IN ($placeholders) AND is_archived = 1");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        sendResponse(['success' => true, 'message' => "$affected artist(s) deleted permanently"]);
    } else {
        sendError('Failed to delete artists', 500);
    }
}

// POST - Bulk archive messages
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'archive_messages') {
    $ids = $input['ids'] ?? [];
    
    if (empty($ids) || !is_array($ids)) {
        sendError('No IDs provided');
    }
    
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $conn->prepare("UPDATE messages SET is_archived = 1 WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        sendResponse(['success' => true, 'message' => "$affected message(s) archived successfully"]);
    } else {
        sendError('Failed to archive messages', 500);
    }
}

// POST - Bulk restore messages
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'restore_messages') {
    $ids = $input['ids'] ?? [];
    
    if (empty($ids) || !is_array($ids)) {
        sendError('No IDs provided');
    }
    
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $conn->prepare("UPDATE messages SET is_archived = 0 WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        sendResponse(['success' => true, 'message' => "$affected message(s) restored successfully"]);
    } else {
        sendError('Failed to restore messages', 500);
    }
}

// POST - Bulk delete messages
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete_messages') {
    $ids = $input['ids'] ?? [];
    
    if (empty($ids) || !is_array($ids)) {
        sendError('No IDs provided');
    }
    
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $conn->prepare("DELETE FROM messages WHERE id IN ($placeholders) AND is_archived = 1");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        sendResponse(['success' => true, 'message' => "$affected message(s) deleted permanently"]);
    } else {
        sendError('Failed to delete messages', 500);
    }
}

sendError('Invalid action', 400);
?>
