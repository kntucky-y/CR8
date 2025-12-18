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

// GET - Fetch inventory with filters
if ($method === 'GET') {
    $filter_artist_id = $_GET['artist_id'] ?? 'all';
    $filter_stock = $_GET['stock'] ?? 'all';
    $search_term = $_GET['search'] ?? '';
    $filter_status = $_GET['status'] ?? 'active';

    // Get all artists for filter
    $artists = [];
    $artists_result = $conn->query("SELECT id, artist_name FROM artists ORDER BY artist_name ASC");
    while ($row = $artists_result->fetch_assoc()) {
        $artists[] = $row;
    }

    $sql = "
        SELECT 
            p.id, p.product_name, p.base_variant_name, p.image, p.quantity, p.is_active,
            a.artist_name
        FROM products p
        LEFT JOIN artists a ON p.artist_id = a.id
    ";

    $where_clauses = [];
    $params = [];
    $types = '';

    if ($filter_status === 'active') {
        $where_clauses[] = "p.is_active = 1";
    } elseif ($filter_status === 'inactive') {
        $where_clauses[] = "p.is_active = 0";
    }

    if ($filter_artist_id !== 'all') {
        $where_clauses[] = "p.artist_id = ?";
        $params[] = $filter_artist_id;
        $types .= 'i';
    }

    if ($filter_stock !== 'all') {
        if ($filter_stock === 'instock') {
            $where_clauses[] = "p.quantity > 0";
        } elseif ($filter_stock === 'lowstock') {
            $where_clauses[] = "p.quantity > 0 AND p.quantity <= 10";
        } elseif ($filter_stock === 'outofstock') {
            $where_clauses[] = "p.quantity <= 0";
        }
    }

    if (!empty($search_term)) {
        $where_clauses[] = "p.product_name LIKE ?";
        $params[] = "%{$search_term}%";
        $types .= 's';
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    $sql .= " ORDER BY p.product_name ASC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    $stmt->close();
    $conn->close();
    sendResponse([
        'products' => $products,
        'artists' => $artists
    ]);
}

// POST - Update inventory item
if ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'update') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Debug: Log incoming data
        error_log("Inventory Update Input: " . json_encode($input));
        
        $id = $input['id'] ?? null;
        
        if (!$id) {
            sendError('Product ID is required', 400);
        }
        
        $updates = [];
        $params = [];
        $types = '';
        
        if (isset($input['quantity'])) {
            $updates[] = "quantity = ?";
            $params[] = $input['quantity'];
            $types .= 'i';
        }
        
        if (isset($input['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = $input['is_active'];
            $types .= 'i';
        }
        
        if (isset($input['deactivation_reason'])) {
            $updates[] = "deactivation_reason = ?";
            $params[] = $input['deactivation_reason'];
            $types .= 's';
        }
        
        if (empty($updates)) {
            sendError('No updates provided', 400);
        }
        
        $params[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            // If product is being deactivated, send notification to artist
            if (isset($input['is_active']) && $input['is_active'] == 0 && !empty($input['deactivation_reason'])) {
                error_log("Product deactivation detected - ID: $id, Reason: " . $input['deactivation_reason']);
                
                // Get product and artist details
                $product_query = $conn->prepare("SELECT p.product_name, p.artist_id, a.user_id FROM products p LEFT JOIN artists a ON p.artist_id = a.id WHERE p.id = ?");
                $product_query->bind_param("i", $id);
                $product_query->execute();
                $product_result = $product_query->get_result();
                
                if ($product_row = $product_result->fetch_assoc()) {
                    $product_name = $product_row['product_name'];
                    $user_id = $product_row['user_id'];
                    $reason = $input['deactivation_reason'];
                    
                    error_log("Product details - Name: $product_name, Artist User ID: $user_id");
                    
                    // Create notification for artist
                    if ($user_id) {
                        $notification_title = "Product Deactivated";
                        $notification_message = "Your product '{$product_name}' has been deactivated by admin. Reason: {$reason}";
                        $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'product_deactivation', NOW())");
                        $notification_stmt->bind_param("iss", $user_id, $notification_title, $notification_message);
                        
                        if ($notification_stmt->execute()) {
                            error_log("Notification created successfully for user_id: $user_id");
                        } else {
                            error_log("Failed to create notification: " . $notification_stmt->error);
                        }
                        $notification_stmt->close();
                    } else {
                        error_log("No user_id found for artist - cannot send notification");
                    }
                } else {
                    error_log("Product not found or no artist associated - ID: $id");
                }
                $product_query->close();
            }
            
            // If product is being activated, send notification to artist
            if (isset($input['is_active']) && $input['is_active'] == 1) {
                error_log("Product activation detected - ID: $id");
                
                // Get product and artist details
                $product_query = $conn->prepare("SELECT p.product_name, p.artist_id, a.user_id FROM products p LEFT JOIN artists a ON p.artist_id = a.id WHERE p.id = ?");
                $product_query->bind_param("i", $id);
                $product_query->execute();
                $product_result = $product_query->get_result();
                
                if ($product_row = $product_result->fetch_assoc()) {
                    $product_name = $product_row['product_name'];
                    $user_id = $product_row['user_id'];
                    
                    error_log("Product activation - Name: $product_name, Artist User ID: $user_id");
                    
                    // Create notification for artist
                    if ($user_id) {
                        $notification_title = "Product Activated";
                        $notification_message = "Your product '{$product_name}' has been reactivated by admin. It is now visible to customers.";
                        $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'product_activation', NOW())");
                        $notification_stmt->bind_param("iss", $user_id, $notification_title, $notification_message);
                        
                        if ($notification_stmt->execute()) {
                            error_log("Activation notification created successfully for user_id: $user_id");
                        } else {
                            error_log("Failed to create activation notification: " . $notification_stmt->error);
                        }
                        $notification_stmt->close();
                    } else {
                        error_log("No user_id found for artist - cannot send activation notification");
                    }
                } else {
                    error_log("Product not found or no artist associated for activation - ID: $id");
                }
                $product_query->close();
            }
            
            $stmt->close();
            $conn->close();
            sendResponse(['success' => true, 'message' => 'Product updated successfully']);
        } else {
            $stmt->close();
            $conn->close();
            sendError('Failed to update product', 500);
        }
    }
}

sendError('Invalid request', 400);
