<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: https://cr8.dcism.org');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '.dcism.org',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);
    session_start();
}

$conn = getDbConnection();

// Check if user is logged in and is an artist
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'artist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get artist_id from user_id
$artist_stmt = $conn->prepare("SELECT id FROM artists WHERE user_id = ?");
$artist_stmt->bind_param('i', $user_id);
$artist_stmt->execute();
$artist_result = $artist_stmt->get_result();

if ($artist_row = $artist_result->fetch_assoc()) {
    $artist_id = $artist_row['id'];
} else {
    echo json_encode(['success' => false, 'message' => 'Artist profile not found']);
    exit();
}
$artist_stmt->close();

// Get product ID
$product_id = intval($_POST['product_id'] ?? 0);
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit();
}

// Verify product belongs to this artist
$verify_stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND artist_id = ?");
$verify_stmt->bind_param('ii', $product_id, $artist_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found or unauthorized']);
    exit();
}
$verify_stmt->close();

// Handle file upload
$target_dir = __DIR__ . "/../cr8images/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

$image_path = null;

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $filename = time() . '_' . basename($_FILES["image"]["name"]);
    $target_file = $target_dir . $filename;
    
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if ($check === false) {
        echo json_encode(['success' => false, 'message' => 'File is not an image']);
        exit();
    }
    
    if ($_FILES["image"]["size"] > 5000000) {
        echo json_encode(['success' => false, 'message' => 'File is too large (Max 5MB)']);
        exit();
    }
    
    $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit();
    }
    
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        $image_path = "cr8images/" . $filename;
    } else {
        echo json_encode(['success' => false, 'message' => 'Error uploading file']);
        exit();
    }
}

// Get form data
$product_name = trim($_POST['product_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 0);
$has_variants = isset($_POST['has_variants']) && $_POST['has_variants'] === '1';
$variants = [];

if ($has_variants && isset($_POST['variants'])) {
    $variants = json_decode($_POST['variants'], true);
    if (!is_array($variants)) {
        $variants = [];
    }
}

// Validate
if (empty($product_name)) {
    echo json_encode(['success' => false, 'message' => 'Product name is required']);
    exit();
}

if (empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Description is required']);
    exit();
}

if ($price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Price must be greater than 0']);
    exit();
}

// Update product
if ($image_path) {
    // Update with new image
    $sql = "UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ?, image = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdisi", $product_name, $description, $price, $quantity, $image_path, $product_id);
} else {
    // Update without changing image
    $sql = "UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdii", $product_name, $description, $price, $quantity, $product_id);
}

if ($stmt->execute()) {
    $stmt->close();
    
    // Delete existing variants for this product
    $delete_variants_stmt = $conn->prepare("DELETE FROM variants WHERE product_id = ?");
    $delete_variants_stmt->bind_param('i', $product_id);
    $delete_variants_stmt->execute();
    $delete_variants_stmt->close();
    
    // Insert new variants if any
    if ($has_variants && !empty($variants)) {
        $variant_sql = "INSERT INTO variants (product_id, variant_name, quantity, price, image) VALUES (?, ?, ?, ?, ?)";
        $variant_stmt = $conn->prepare($variant_sql);
        
        if (!$variant_stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit();
        }
        
        foreach ($variants as $index => $variant) {
            $variant_name = trim($variant['name'] ?? '');
            $variant_quantity = intval($variant['quantity'] ?? 0);
            $variant_price = floatval($variant['price'] ?? 0);
            
            if (!empty($variant_name)) {
                // Handle variant image upload
                $variant_image_path = null;
                $file_key = "variant_image_" . $index;
                
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                    $filename = $product_id . '_variant_' . $index . '_' . time() . '_' . basename($_FILES[$file_key]["name"]);
                    $target_file = $target_dir . $filename;
                    
                    $check = getimagesize($_FILES[$file_key]["tmp_name"]);
                    if ($check !== false && $_FILES[$file_key]["size"] <= 5000000) {
                        $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                            if (move_uploaded_file($_FILES[$file_key]["tmp_name"], $target_file)) {
                                $variant_image_path = "cr8images/" . $filename;
                            }
                        }
                    }
                }
                
                $variant_stmt->bind_param("isids", $product_id, $variant_name, $variant_quantity, $variant_price, $variant_image_path);
                $variant_stmt->execute();
            }
        }
        $variant_stmt->close();
    }
    
    echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating product: ' . $stmt->error]);
}

$conn->close();
