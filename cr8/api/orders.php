<?php
require_once 'config.php';

header('Access-Control-Allow-Origin: https://cr8.dcism.org');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '.dcism.org',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);
    session_start();
}

$conn = getDbConnection();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        // Enable error reporting for debugging
        error_log("Orders API called by user_id: " . $user_id);
        
        // Handle multipart form data
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $contact_number = $_POST['contact_number'] ?? '';
        $street_address = $_POST['street_address'] ?? '';
        $province = $_POST['province_text'] ?? '';
        $city = $_POST['city_text'] ?? '';
        $barangay = $_POST['barangay_text'] ?? '';
        $postal_code = $_POST['postal_code'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        $total = $_POST['total'] ?? 0;
        $cart_items = json_decode($_POST['cart_items'] ?? '[]', true);
        
        error_log("Cart items: " . print_r($cart_items, true));

        if (empty($cart_items)) {
            echo json_encode(['success' => false, 'message' => 'No items in cart']);
            exit;
        }

        // Handle proof of payment upload
        $proof_path = null;
        if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "../uploads/";
            if (!is_dir($target_dir)) { 
                mkdir($target_dir, 0755, true); 
            }
            $proof_filename = time() . '_' . basename($_FILES["proof"]["name"]);
            $target_file = $target_dir . $proof_filename;
            
            if (!move_uploaded_file($_FILES["proof"]["tmp_name"], $target_file)) {
                error_log("Failed to upload file to: " . $target_file);
                echo json_encode(['success' => false, 'message' => 'Failed to upload proof of payment']);
                exit;
            }
            $proof_path = "uploads/" . $proof_filename;
            error_log("Proof uploaded to: " . $proof_path);
        } else {
            $error_msg = isset($_FILES['proof']) ? $_FILES['proof']['error'] : 'No file uploaded';
            error_log("Proof upload failed: " . $error_msg);
            echo json_encode(['success' => false, 'message' => 'Proof of payment is required. Error: ' . $error_msg]);
            exit;
        }

        // Format address like original cr8 (without name and phone)
        $fullAddress = "$street_address, Brgy. $barangay, $city, $province, $postal_code, Philippines";
        
        // Update user profile with latest contact info
        $update_user = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
        $update_user->bind_param("sssi", $first_name, $last_name, $email, $user_id);
        $update_user->execute();
        $update_user->close();
        
        // Generate order number (same as original cr8)
        $order_no = 'ORD' . strtoupper(uniqid());

        // Create order
        $stmt = $conn->prepare("
            INSERT INTO orders (order_no, address, payment_method, proof_path, total, customer_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssdi", 
            $order_no,
            $fullAddress,
            $payment_method,
            $proof_path,
            $total, 
            $user_id
        );
        
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to create order: ' . $stmt->error]);
            exit;
        }

        $order_id = $conn->insert_id;
        $stmt->close();

        // Insert delivery record with "For Review" status
        $delivery_stmt = $conn->prepare("INSERT INTO delivery (order_id, status) VALUES (?, 'For Review')");
        $delivery_stmt->bind_param("i", $order_id);
        $delivery_stmt->execute();
        $delivery_stmt->close();

        // Insert order items
        $item_stmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, variant_id, quantity, price)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($cart_items as $item) {
            $variant_id = isset($item['variant_id']) ? $item['variant_id'] : null;
            $item_stmt->bind_param("iiiid", 
                $order_id, 
                $item['product_id'],
                $variant_id,
                $item['quantity'], 
                $item['price']
            );
            $item_stmt->execute();
        }
        $item_stmt->close();

        // Clear cart
        $clear_stmt = $conn->prepare("DELETE FROM carts WHERE user_id = ?");
        $clear_stmt->bind_param("i", $user_id);
        $clear_stmt->execute();
        $clear_stmt->close();

        echo json_encode(['success' => true, 'order_id' => $order_id, 'order_no' => $order_no]);
        break;

    case 'upload-proof':
        $order_id = $_POST['order_id'] ?? 0;
        
        // Verify order belongs to user
        $verify_stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND customer_id = ?");
        $verify_stmt->bind_param("ii", $order_id, $user_id);
        $verify_stmt->execute();
        $result = $verify_stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }
        $verify_stmt->close();

        // Handle proof of delivery upload
        if (isset($_FILES['proof_delivery']) && $_FILES['proof_delivery']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "../uploads/";
            if (!is_dir($target_dir)) { 
                mkdir($target_dir, 0755, true); 
            }
            $proof_filename = time() . '_delivery_' . basename($_FILES["proof_delivery"]["name"]);
            $target_file = $target_dir . $proof_filename;
            
            if (move_uploaded_file($_FILES["proof_delivery"]["tmp_name"], $target_file)) {
                $proof_path = "uploads/" . $proof_filename;
                
                // Update order with proof of delivery
                $update_stmt = $conn->prepare("UPDATE orders SET proof_delivery = ? WHERE id = ?");
                $update_stmt->bind_param("si", $proof_path, $order_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                echo json_encode(['success' => true, 'message' => 'Proof of delivery uploaded']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        }
        break;

    case 'list':
        $stmt = $conn->prepare("
            SELECT o.*, COUNT(oi.id) as item_count, d.status as delivery_status, d.tracking_number,
                   r.id as review_id, r.rating as review_rating, r.comments as review_comments
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN delivery d ON o.id = d.order_id
            LEFT JOIN reviews r ON o.id = r.order_id AND r.user_id = ?
            WHERE o.customer_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ");
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $orders = [];
        
        while ($row = $result->fetch_assoc()) {
            $row['total'] = (float)$row['total'];
            $row['item_count'] = (int)$row['item_count'];
            $row['status'] = $row['delivery_status'] ?? 'For Review';
            $row['tracking_number'] = $row['tracking_number'] ?? null;
            $row['review_id'] = $row['review_id'] ? (int)$row['review_id'] : null;
            $row['review_rating'] = $row['review_rating'] ? (int)$row['review_rating'] : null;
            $row['review_comments'] = $row['review_comments'] ?? null;
            unset($row['delivery_status']);
            
            // Get order items with product images and reviews
            $order_id = $row['id'];
            $items_stmt = $conn->prepare("
                SELECT oi.*, p.product_name, p.image, v.image as variant_image, v.variant_name,
                       r.id as item_review_id, r.rating as item_review_rating, r.comments as item_review_comments
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                LEFT JOIN variants v ON oi.variant_id = v.id
                LEFT JOIN reviews r ON r.order_id = ? AND r.product_id = oi.product_id AND r.user_id = ?
                WHERE oi.order_id = ?
            ");
            $items_stmt->bind_param("iii", $order_id, $user_id, $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $items = [];
            while ($item = $items_result->fetch_assoc()) {
                $image_path = $item['variant_image'] ?: $item['image'];
                $items[] = [
                    'product_id' => (int)$item['product_id'],
                    'product_name' => $item['product_name'],
                    'image' => $image_path,
                    'image_url' => 'https://cr8.dcism.org/' . $image_path,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'variant_name' => $item['variant_name'] ?? null,
                    'review_id' => $item['item_review_id'] ? (int)$item['item_review_id'] : null,
                    'review_rating' => $item['item_review_rating'] ? (int)$item['item_review_rating'] : null,
                    'review_comments' => $item['item_review_comments'] ?? null
                ];
            }
            $row['items'] = $items;
            $items_stmt->close();
            
            $orders[] = $row;
        }
        
        echo json_encode(['success' => true, 'orders' => $orders]);
        $stmt->close();
        break;

    case 'details':
        $order_id = $_GET['order_id'] ?? 0;
        
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($order = $result->fetch_assoc()) {
            $order['total'] = (float)$order['total'];
            
            // Get order items
            $items_stmt = $conn->prepare("
                SELECT oi.*, p.product_name, p.image
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $items = [];
            while ($item = $items_result->fetch_assoc()) {
                $item['price'] = (float)$item['price'];
                $item['quantity'] = (int)$item['quantity'];
                $items[] = $item;
            }
            $items_stmt->close();
            
            $order['items'] = $items;
            echo json_encode(['success' => true, 'order' => $order]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
?>
