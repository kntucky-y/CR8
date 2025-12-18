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

// GET - Fetch artists
if ($method === 'GET' && !isset($_GET['id'])) {
    $search = $_GET['search'] ?? '';
    $filter = $_GET['filter'] ?? 'active';
    
    $artists_sql = "
        SELECT
            a.id,
            a.artist_name,
            u.email,
            u.created_at as join_date,
            COUNT(p.id) as product_count,
            COALESCE(a.is_archived, 0) as is_archived,
            a.status
        FROM
            artists a
        LEFT JOIN
            users u ON a.user_id = u.id
        LEFT JOIN
            products p ON a.id = p.artist_id
        WHERE 
            1=1
    ";
    
    if ($filter === 'active') {
        $artists_sql .= " AND COALESCE(a.is_archived, 0) = 0 AND a.status = 'active'";
    } elseif ($filter === 'archived') {
        $artists_sql .= " AND a.is_archived = 1";
    }
    
    if (!empty($search)) {
        $searchTerm = "%{$search}%";
        $artists_sql .= " AND (a.artist_name LIKE ? OR u.email LIKE ?)";
    }
    
    $artists_sql .= "
        GROUP BY
            a.id, a.artist_name, u.email, u.created_at
        ORDER BY
            a.artist_name ASC
    ";
    
    if (!empty($search)) {
        $stmt = $conn->prepare($artists_sql);
        $stmt->bind_param('ss', $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($artists_sql);
    }
    
    $artists = [];
    while ($row = $result->fetch_assoc()) {
        $artists[] = $row;
    }

    if (!empty($search)) {
        $stmt->close();
    }
    $conn->close();
    sendResponse(['artists' => $artists]);
}

// GET - Fetch single artist details
if ($method === 'GET' && isset($_GET['id'])) {
    $artist_id = (int)$_GET['id'];
    
    // Get artist info
    $artist_query = "
        SELECT a.*, u.first_name, u.last_name, u.email, u.phone, u.created_at
        FROM artists a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.id = ?
    ";
    $stmt = $conn->prepare($artist_query);
    $stmt->bind_param("i", $artist_id);
    $stmt->execute();
    $artist = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get artist products
    $products_query = "SELECT * FROM products WHERE artist_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($products_query);
    $stmt->bind_param("i", $artist_id);
    $stmt->execute();
    $products = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();

    // Get artist sales stats
    $sales_query = "
        SELECT SUM(oi.price * oi.quantity) as total_revenue, SUM(oi.quantity) as total_items_sold
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE p.artist_id = ?
        AND (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = 'Completed'
    ";
    $stmt = $conn->prepare($sales_query);
    $stmt->bind_param("i", $artist_id);
    $stmt->execute();
    $sales_stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $conn->close();
    sendResponse([
        'artist' => $artist,
        'products' => $products,
        'sales_stats' => $sales_stats
    ]);
}

// POST - Revoke artist (archive)
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'revoke') {
    $data = json_decode(file_get_contents('php://input'), true);
    $artist_id = (int)$data['artist_id'];
    $revoke_reason = $data['revoke_reason'] ?? '';

    // Get artist info for notification
    $artist_info_stmt = $conn->prepare("SELECT user_id, artist_name FROM artists WHERE id = ?");
    $artist_info_stmt->bind_param("i", $artist_id);
    $artist_info_stmt->execute();
    $artist_info_result = $artist_info_stmt->get_result();
    $artist_info = $artist_info_result->fetch_assoc();
    $artist_info_stmt->close();

    $stmt = $conn->prepare("UPDATE artists SET is_archived = 1, status = 'revoked', revoke_reason = ? WHERE id = ?");
    $stmt->bind_param("si", $revoke_reason, $artist_id);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // Update user role back to 'customer'
        if ($artist_info) {
            $role_stmt = $conn->prepare("UPDATE users SET role = 'customer' WHERE id = ?");
            $role_stmt->bind_param("i", $artist_info['user_id']);
            $role_stmt->execute();
            $role_stmt->close();
        }
        
        // Deactivate all products by this artist
        $products_stmt = $conn->prepare("UPDATE products SET is_active = 0, deactivation_reason = 'Artist revoked' WHERE artist_id = ?");
        $products_stmt->bind_param("i", $artist_id);
        $products_stmt->execute();
        $products_stmt->close();
        
        // Send notification to artist
        if ($artist_info) {
            $title = "Artist Privileges Revoked";
            $message = "Your artist privileges have been revoked.\n\nReason: {$revoke_reason}\n\nYou can no longer manage products or access artist features. If you believe this is a mistake, please contact support.";
            $type = "artist_revoked";
            
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)");
            $notif_stmt->bind_param("isssi", $artist_info['user_id'], $type, $title, $message, $artist_id);
            $notif_stmt->execute();
            $notif_stmt->close();
        }
    }

    $conn->close();

    if ($success) {
        sendResponse(['success' => true]);
    } else {
        sendError('Failed to revoke artist', 500);
    }
}

// POST - Restore artist
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'restore') {
    $data = json_decode(file_get_contents('php://input'), true);
    $artist_id = (int)$data['artist_id'];

    $stmt = $conn->prepare("UPDATE artists SET is_archived = 0, status = 'active', revoke_reason = '' WHERE id = ?");
    $stmt->bind_param("i", $artist_id);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // Get user_id to update their role
        $user_stmt = $conn->prepare("SELECT user_id FROM artists WHERE id = ?");
        $user_stmt->bind_param("i", $artist_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $artist_data = $user_result->fetch_assoc();
        $user_stmt->close();
        
        if ($artist_data) {
            // Update user role back to 'artist'
            $role_stmt = $conn->prepare("UPDATE users SET role = 'artist' WHERE id = ?");
            $role_stmt->bind_param("i", $artist_data['user_id']);
            $role_stmt->execute();
            $role_stmt->close();
        }
        
        // Reactivate all products by this artist that were deactivated due to revocation
        $products_stmt = $conn->prepare("UPDATE products SET is_active = 1, deactivation_reason = '' WHERE artist_id = ? AND deactivation_reason = 'Artist revoked'");
        $products_stmt->bind_param("i", $artist_id);
        $products_stmt->execute();
        $products_stmt->close();
    }

    $conn->close();

    if ($success) {
        sendResponse(['success' => true]);
    } else {
        sendError('Failed to restore artist', 500);
    }
}

// POST - Delete artist permanently
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $data = json_decode(file_get_contents('php://input'), true);
    $artist_id = (int)$data['artist_id'];

    // Delete artist record (this will cascade delete products if foreign key is set)
    $stmt = $conn->prepare("DELETE FROM artists WHERE id = ?");
    $stmt->bind_param("i", $artist_id);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();

    if ($success) {
        sendResponse(['success' => true]);
    } else {
        sendError('Failed to delete artist', 500);
    }
}

sendError('Invalid request', 400);
