<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

// 1. Security: Check if an admin is logged in.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $response['error'] = 'Permission Denied. Administrator access required.';
    echo json_encode($response);
    exit;
}

// 2. Get data from the POST request
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['product_id'])) {
    $response['error'] = 'Invalid request data.';
    echo json_encode($response);
    exit;
}

$product_id = $data['product_id'];

// 3. Determine which field to update
$field_to_update = '';
$new_value = null;
$param_type = '';

if (isset($data['quantity'])) {
    $field_to_update = 'quantity';
    $new_value = (int)$data['quantity'];
    $param_type = 'i';
} elseif (isset($data['status'])) {
    $field_to_update = 'is_active';
    $new_value = (int)$data['status'];
    $param_type = 'i';
} else {
    $response['error'] = 'No update field specified (quantity or status).';
    echo json_encode($response);
    exit;
}

$conn = new mysqli('localhost', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');
if ($conn->connect_error) {
    $response['error'] = "Database connection failed: " . $conn->connect_error;
    echo json_encode($response);
    exit;
}

try {
    // 4. Prepare and execute the UPDATE statement for the admin
    $sql = "UPDATE products SET $field_to_update = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param($param_type . 'i', $new_value, $product_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = "Product updated successfully!";
        unset($response['error']);
    } else {
        // This can happen if the value submitted is the same as the one in the DB
        $response['success'] = true;
        $response['message'] = "No changes were made to the product.";
        unset($response['error']);
    }

    $stmt->close();
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>