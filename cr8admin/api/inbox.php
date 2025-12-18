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

// GET - Fetch all messages
if ($method === 'GET' && !isset($_GET['id'])) {
    $search = $_GET['search'] ?? '';
    $filter = $_GET['filter'] ?? 'active';
    
    $sql = "SELECT id, name, email, message, created_at, status, COALESCE(is_archived, 0) as is_archived FROM messages WHERE 1=1";
    $params = [];
    $types = '';
    
    // Apply filter
    if ($filter === 'active') {
        $sql .= " AND COALESCE(is_archived, 0) = 0";
    } elseif ($filter === 'archived') {
        $sql .= " AND is_archived = 1";
    }
    // 'all' shows everything
    
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR email LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    if (!empty($params)) {
        $stmt->close();
    }
    $conn->close();
    sendResponse(['messages' => $messages]);
}

// GET - Fetch single message and mark as read
if ($method === 'GET' && isset($_GET['id'])) {
    $message_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM messages WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $message = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Mark as read if unread
    if ($message && $message['status'] == 'Unread') {
        $update_stmt = $conn->prepare("UPDATE messages SET status = 'Read' WHERE id = ?");
        $update_stmt->bind_param("i", $message_id);
        $update_stmt->execute();
        $update_stmt->close();
        $message['status'] = 'Read';
    }

    $conn->close();
    sendResponse(['message' => $message]);
}

// POST - Archive message
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    if ($action === 'archive') {
        $message_id = (int)$input['id'];
        
        $stmt = $conn->prepare("UPDATE messages SET is_archived = 1 WHERE id = ?");
        $stmt->bind_param("i", $message_id);
        $success = $stmt->execute();
        $stmt->close();
        $conn->close();

        if ($success) {
            sendResponse(['success' => true]);
        } else {
            sendError('Failed to archive message', 500);
        }
    }
    
    if ($action === 'restore') {
        $message_id = (int)$input['id'];
        
        $stmt = $conn->prepare("UPDATE messages SET is_archived = 0 WHERE id = ?");
        $stmt->bind_param("i", $message_id);
        $success = $stmt->execute();
        $stmt->close();
        $conn->close();

        if ($success) {
            sendResponse(['success' => true]);
        } else {
            sendError('Failed to restore message', 500);
        }
    }
    
    sendError('Invalid action', 400);
}

// DELETE - Delete message (keep for backwards compatibility)
if ($method === 'DELETE' && isset($_GET['id'])) {
    $message_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();

    if ($success) {
        sendResponse(['success' => true]);
    } else {
        sendError('Failed to delete message', 500);
    }
}

sendError('Invalid request', 400);
