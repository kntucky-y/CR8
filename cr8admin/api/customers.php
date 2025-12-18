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

// GET - Fetch customers with filter
if ($method === 'GET' && !isset($_GET['id'])) {
    $filter = $_GET['filter'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    $sql = "";
    $whereSearch = "";
    
    if (!empty($search)) {
        $searchTerm = "%{$search}%";
        $whereSearch = " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    }
    
    switch ($filter) {
        case 'has_orders':
            $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.created_at, u.role 
                    FROM users u 
                    WHERE u.role IN ('customer', 'artist') 
                    AND EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = u.id)
                    {$whereSearch}
                    ORDER BY u.created_at DESC";
            break;
        case 'no_orders':
            $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.created_at, u.role 
                    FROM users u 
                    WHERE u.role IN ('customer', 'artist') 
                    AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = u.id)
                    {$whereSearch}
                    ORDER BY u.created_at DESC";
            break;
        case 'all':
        default:
            $sql = "SELECT id, first_name, last_name, email, created_at, role 
                    FROM users u
                    WHERE role IN ('customer', 'artist')
                    {$whereSearch}
                    ORDER BY created_at DESC";
            break;
    }

    if (!empty($search)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }

    if (!empty($search)) {
        $stmt->close();
    }
    $conn->close();
    sendResponse(['customers' => $customers]);
}

// GET - Fetch single customer details
if ($method === 'GET' && isset($_GET['id'])) {
    $customer_id = (int)$_GET['id'];
    
    // Get customer info
    $customer_query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($customer_query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get customer addresses
    $addresses_query = "SELECT * FROM addresses WHERE user_id = ?";
    $stmt = $conn->prepare($addresses_query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $addresses = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $addresses[] = $row;
    }
    $stmt->close();

    // Get customer orders
    $orders_query = "
        SELECT o.*, 
        (SELECT status FROM delivery d WHERE d.order_id = o.id ORDER BY d.id DESC LIMIT 1) as delivery_status
        FROM orders o
        WHERE o.customer_id = ?
        ORDER BY o.created_at DESC
    ";
    $stmt = $conn->prepare($orders_query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $orders = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();

    $conn->close();
    sendResponse([
        'customer' => $customer,
        'addresses' => $addresses,
        'orders' => $orders
    ]);
}

sendError('Invalid request', 400);
