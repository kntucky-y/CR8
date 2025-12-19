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

// Create notifications table if not exists (remove foreign key constraint)
$create_table = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
)";
$conn->query($create_table);

$method = $_SERVER['REQUEST_METHOD'];

// GET - Fetch orders with filters
if ($method === 'GET' && !isset($_GET['id'])) {
    $filter_status = $_GET['status'] ?? 'all';
    $search_order_no = trim($_GET['search'] ?? '');

    $sql = "
        SELECT 
            o.id, 
            o.order_no, 
            o.total, 
            o.created_at, 
            u.first_name, 
            u.last_name, 
            o.proof_path,
            o.proof_delivery,
            (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) as delivery_status,
            (SELECT tracking_number FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) as tracking_number,
            (SELECT cancel_reason FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) as cancel_reason,
            (SELECT refund_status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) as refund_status,
            (SELECT refund_proof FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) as refund_proof
        FROM orders o
        LEFT JOIN users u ON o.customer_id = u.id
    ";

    $where_clauses = [];
    $params = [];
    $types = '';

    if ($filter_status !== 'all') {
        $where_clauses[] = "(SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) = ?";
        $params[] = $filter_status;
        $types .= 's';
    }

    if (!empty($search_order_no)) {
        $where_clauses[] = "o.order_no LIKE ?";
        $params[] = "%{$search_order_no}%";
        $types .= 's';
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    $sql .= " ORDER BY o.created_at DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    $stmt->close();
    $conn->close();
    sendResponse(['orders' => $orders]);
}

// GET - Fetch single order details
if ($method === 'GET' && isset($_GET['id'])) {
    $order_id = (int)$_GET['id'];
    
    // Get order basic info (address is stored directly in orders table)
    $order_query = "
        SELECT o.*, u.first_name, u.last_name, u.email
        FROM orders o
        LEFT JOIN users u ON o.customer_id = u.id
        WHERE o.id = ?
    ";
    $stmt = $conn->prepare($order_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get order items
    $items_query = "
        SELECT oi.*, p.product_name, p.image, 
        COALESCE(v.variant_name, p.base_variant_name) as variant_name,
        COALESCE(v.image, p.image) as item_image,
        a.artist_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN variants v ON oi.variant_id = v.id
        LEFT JOIN artists a ON p.artist_id = a.id
        WHERE oi.order_id = ?
    ";
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    // Get delivery history
    $delivery_query = "SELECT * FROM delivery WHERE order_id = ? ORDER BY id DESC";
    $stmt = $conn->prepare($delivery_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $delivery = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $delivery[] = $row;
    }
    $stmt->close();

    $conn->close();
    sendResponse([
        'order' => $order,
        'items' => $items,
        'delivery' => $delivery
    ]);
}

// POST - Update order status
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    if ($action === 'update_status') {
        $order_id = $input['order_id'] ?? 0;
        $status = $input['status'] ?? '';
        $tracking_number = $input['tracking_number'] ?? null;
        
        error_log("Update Status Request - Order ID: $order_id, Status: $status, Tracking: $tracking_number");
        
        if (empty($order_id) || empty($status)) {
            sendError('Order ID and status are required', 400);
        }
        
        // Check if delivery record exists for this order
        $check = $conn->prepare("SELECT id FROM delivery WHERE order_id = ? LIMIT 1");
        $check->bind_param("i", $order_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();
        
        if ($exists) {
            // Update existing delivery record
            $stmt = $conn->prepare("UPDATE delivery SET status = ?, updated_at = NOW() WHERE order_id = ?");
            $stmt->bind_param("si", $status, $order_id);
        } else {
            // Insert new delivery record
            $stmt = $conn->prepare("INSERT INTO delivery (order_id, status, tracking_number, updated_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iss", $order_id, $status, $tracking_number);
        }
        
        if ($stmt->execute()) {
            error_log("Status update SUCCESS for order $order_id");
            $stmt->close();
            
            // Handle inventory management for Processing status
            if ($status === 'Processing') {
                error_log("Order $order_id moved to Processing - decreasing inventory");
                
                // Get order items
                $items_stmt = $conn->prepare("
                    SELECT oi.product_id, oi.variant_id, oi.quantity 
                    FROM order_items oi 
                    WHERE oi.order_id = ?
                ");
                $items_stmt->bind_param("i", $order_id);
                $items_stmt->execute();
                $items_result = $items_stmt->get_result();
                
                while ($item = $items_result->fetch_assoc()) {
                    $product_id = $item['product_id'];
                    $variant_id = $item['variant_id'];
                    $quantity = $item['quantity'];
                    
                    if ($variant_id) {
                        // Decrease variant quantity
                        $update_variant = $conn->prepare("UPDATE variants SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
                        $update_variant->bind_param("iii", $quantity, $variant_id, $quantity);
                        if ($update_variant->execute()) {
                            error_log("Decreased variant $variant_id quantity by $quantity");
                        } else {
                            error_log("Failed to decrease variant $variant_id: " . $update_variant->error);
                        }
                        $update_variant->close();
                    } else {
                        // Decrease product quantity
                        $update_product = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
                        $update_product->bind_param("iii", $quantity, $product_id, $quantity);
                        if ($update_product->execute()) {
                            error_log("Decreased product $product_id quantity by $quantity");
                        } else {
                            error_log("Failed to decrease product $product_id: " . $update_product->error);
                        }
                        $update_product->close();
                    }
                }
                $items_stmt->close();
            }
            
            // Create notification for Completed or Out for Delivery status
            if ($status === 'Completed' || $status === 'Out for Delivery') {
                $order_stmt = $conn->prepare("SELECT o.customer_id, o.order_no FROM orders o WHERE o.id = ?");
                $order_stmt->bind_param("i", $order_id);
                $order_stmt->execute();
                $order_result = $order_stmt->get_result();
                $order_data = $order_result->fetch_assoc();
                $order_stmt->close();
                
                if ($order_data) {
                    $user_id = $order_data['customer_id'];
                    $order_no = $order_data['order_no'];
                    
                    if ($status === 'Completed') {
                        $title = "Order Completed! 🎉";
                        $message = "Your order {$order_no} has been completed. Thank you for shopping with CR8!";
                        $type = "order_completed";
                    } else {
                        $title = "Order Out for Delivery 🚚";
                        $message = "Your order {$order_no} is now out for delivery! Please prepare to receive your package.";
                        $type = "order_out_for_delivery";
                    }
                    
                    error_log("Creating notification for user_id: $user_id, order: $order_no, type: $type");
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)");
                    $notif_stmt->bind_param("isssi", $user_id, $type, $title, $message, $order_id);
                    if ($notif_stmt->execute()) {
                        error_log("Notification created successfully for order $order_id");
                    } else {
                        error_log("Failed to create notification: " . $notif_stmt->error);
                    }
                    $notif_stmt->close();
                } else {
                    error_log("Failed to get order data for order_id: $order_id");
                }
            }
            
            sendResponse(['success' => true, 'message' => 'Order status updated successfully']);
        } else {
            error_log("Status update FAILED: " . $stmt->error);
            $stmt->close();
            sendError('Failed to update order status: ' . $stmt->error, 500);
        }
        $conn->close();
        exit;
    }
    
    if ($action === 'update_tracking') {
        $order_id = $input['order_id'] ?? 0;
        $tracking_number = $input['tracking_number'] ?? '';
        
        error_log("Update Tracking Request - Order ID: $order_id, Tracking: $tracking_number");
        
        if (empty($order_id)) {
            sendError('Order ID is required', 400);
        }
        
        // Update tracking number for this order
        $stmt = $conn->prepare("UPDATE delivery SET tracking_number = ?, updated_at = NOW() WHERE order_id = ?");
        $stmt->bind_param("si", $tracking_number, $order_id);
        
        if ($stmt->execute()) {
            error_log("Tracking update SUCCESS for order $order_id");
            sendResponse(['success' => true, 'message' => 'Tracking number updated successfully']);
        } else {
            error_log("Tracking update FAILED: " . $stmt->error);
            sendError('Failed to update tracking number: ' . $stmt->error, 500);
        }
        $stmt->close();
        $conn->close();
        exit;
    }
    
    if ($action === 'cancel_order') {
        $order_id = $input['order_id'] ?? 0;
        $cancel_reason = $input['cancel_reason'] ?? '';
        
        error_log("Cancel Order Request - Order ID: $order_id, Reason: $cancel_reason");
        
        if (empty($order_id) || empty($cancel_reason)) {
            sendError('Order ID and cancellation reason are required', 400);
        }
        
        // Update delivery: set status to Cancelled, save cancel reason (keep tracking number)
        $stmt = $conn->prepare("UPDATE delivery SET status = 'Cancelled', cancel_reason = ?, updated_at = NOW() WHERE order_id = ?");
        $stmt->bind_param("si", $cancel_reason, $order_id);
        
        if ($stmt->execute()) {
            error_log("Order cancellation SUCCESS for order $order_id");
            $stmt->close();
            
            // Restore inventory when order is cancelled
            error_log("Order $order_id cancelled - restoring inventory");
            
            // Get order items
            $items_stmt = $conn->prepare("
                SELECT oi.product_id, oi.variant_id, oi.quantity 
                FROM order_items oi 
                WHERE oi.order_id = ?
            ");
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $product_id = $item['product_id'];
                $variant_id = $item['variant_id'];
                $quantity = $item['quantity'];
                
                if ($variant_id) {
                    // Restore variant quantity
                    $restore_variant = $conn->prepare("UPDATE variants SET quantity = quantity + ? WHERE id = ?");
                    $restore_variant->bind_param("ii", $quantity, $variant_id);
                    if ($restore_variant->execute()) {
                        error_log("Restored variant $variant_id quantity by $quantity");
                    } else {
                        error_log("Failed to restore variant $variant_id: " . $restore_variant->error);
                    }
                    $restore_variant->close();
                } else {
                    // Restore product quantity
                    $restore_product = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
                    $restore_product->bind_param("ii", $quantity, $product_id);
                    if ($restore_product->execute()) {
                        error_log("Restored product $product_id quantity by $quantity");
                    } else {
                        error_log("Failed to restore product $product_id: " . $restore_product->error);
                    }
                    $restore_product->close();
                }
            }
            $items_stmt->close();
            
            // Create notification for cancelled order
            $order_stmt = $conn->prepare("SELECT o.customer_id, o.order_no FROM orders o WHERE o.id = ?");
            $order_stmt->bind_param("i", $order_id);
            $order_stmt->execute();
            $order_result = $order_stmt->get_result();
            $order_data = $order_result->fetch_assoc();
            $order_stmt->close();
            
            if ($order_data) {
                $user_id = $order_data['customer_id'];
                $order_no = $order_data['order_no'];
                $title = "Order Cancelled";
                $message = "Your order {$order_no} has been cancelled.\n\nReason: {$cancel_reason}";
                $type = "order_cancelled";
                
                error_log("Creating cancellation notification for user_id: $user_id, order: $order_no");
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)");
                $notif_stmt->bind_param("isssi", $user_id, $type, $title, $message, $order_id);
                if ($notif_stmt->execute()) {
                    error_log("Cancellation notification created successfully for order $order_id");
                } else {
                    error_log("Failed to create cancellation notification: " . $notif_stmt->error);
                }
                $notif_stmt->close();
            } else {
                error_log("Failed to get order data for cancelled order_id: $order_id");
            }
            
            sendResponse(['success' => true, 'message' => 'Order cancelled successfully']);
        } else {
            error_log("Order cancellation FAILED: " . $stmt->error);
            $stmt->close();
            sendError('Failed to cancel order: ' . $stmt->error, 500);
        }
        $conn->close();
        exit;
    }
    
    // Upload refund proof
    if ($action === 'upload_refund') {
        $order_id = intval($_POST['order_id'] ?? 0);
        
        if ($order_id <= 0) {
            sendError('Invalid order ID', 400);
        }
        
        if (!isset($_FILES['refund_proof'])) {
            sendError('No refund proof file uploaded', 400);
        }
        
        $file = $_FILES['refund_proof'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        
        if (!in_array($file['type'], $allowed_types)) {
            sendError('Invalid file type. Only JPG, PNG, and GIF are allowed.', 400);
        }
        
        if ($file['size'] > 5 * 1024 * 1024) {
            sendError('File size too large. Maximum 5MB allowed.', 400);
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = '../uploads/refunds/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'refund_' . $order_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $db_path = 'uploads/refunds/' . $new_filename;
            
            // Update delivery table with refund proof and status
            $stmt = $conn->prepare("
                UPDATE delivery 
                SET refund_proof = ?, refund_status = 'Refunded' 
                WHERE order_id = ?
            ");
            $stmt->bind_param('si', $db_path, $order_id);
            
            if ($stmt->execute()) {
                // Create notification for customer
                $order_stmt = $conn->prepare("SELECT customer_id, order_no FROM orders WHERE id = ?");
                $order_stmt->bind_param('i', $order_id);
                $order_stmt->execute();
                $order_result = $order_stmt->get_result();
                
                if ($order_data = $order_result->fetch_assoc()) {
                    $customer_id = $order_data['customer_id'];
                    $order_no = $order_data['order_no'];
                    
                    $notif_stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, type, title, message, related_id) 
                        VALUES (?, 'refund_processed', 'Refund Processed', ?, ?)
                    ");
                    $notification_message = "Your refund for order $order_no has been processed.";
                    $notif_stmt->bind_param('isi', $customer_id, $notification_message, $order_id);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }
                $order_stmt->close();
                
                sendResponse(['success' => true, 'message' => 'Refund proof uploaded successfully']);
            } else {
                unlink($upload_path); // Delete uploaded file if database update fails
                sendError('Failed to update refund status', 500);
            }
            $stmt->close();
        } else {
            sendError('Failed to upload file', 500);
        }
        
        $conn->close();
        exit;
    }
    
    sendError('Invalid action', 400);
}

sendError('Invalid request', 400);
