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

// Handle file upload
$target_dir = __DIR__ . "/../cr8images/";
error_log("Target directory: $target_dir");
error_log("Directory exists: " . (is_dir($target_dir) ? 'yes' : 'no'));
error_log("Directory writable: " . (is_writable($target_dir) ? 'yes' : 'no'));

if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
    error_log("Created directory: $target_dir");
}

$image_path = null;

// Debug all uploaded files
error_log("All FILES: " . print_r($_FILES, true));

if (isset($_FILES['image'])) {
    error_log("Image file error code: " . $_FILES['image']['error']);
}

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    error_log("File received: " . $_FILES['image']['name'] . ", size: " . $_FILES['image']['size']);
    $filename = time() . '_' . basename($_FILES["image"]["name"]);
    $target_file = $target_dir . $filename;
    error_log("Target file: $target_file");
    
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
        error_log("Image uploaded successfully to: $image_path (full path: $target_file)");
    } else {
        error_log("Failed to move uploaded file. Target: $target_file, Temp: " . $_FILES["image"]["tmp_name"]);
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

// Log received data for debugging
error_log("Product data received: name=$product_name, price=$price, quantity=$quantity, has_variants=" . ($has_variants ? 'true' : 'false'));

if ($has_variants && isset($_POST['variants'])) {
    $variants = json_decode($_POST['variants'], true);
    if (!is_array($variants)) {
        $variants = [];
    }
    error_log("Variants decoded: " . print_r($variants, true));
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

// For products without variants, image and quantity are required
if (!$has_variants) {
    if (!$image_path) {
        echo json_encode(['success' => false, 'message' => 'Product image is required']);
        exit();
    }
    if ($quantity < 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity cannot be negative']);
        exit();
    }
} else {
    // For products with variants, main image is still required
    if (!$image_path) {
        echo json_encode(['success' => false, 'message' => 'Main product image is required']);
        exit();
    }
}

// Insert product
$sql = "INSERT INTO products (product_name, description, price, quantity, image, artist_id) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssdisi", $product_name, $description, $price, $quantity, $image_path, $artist_id);

if ($stmt->execute()) {
    $product_id = $conn->insert_id;
    error_log("Product inserted successfully with ID: $product_id");
    
    // Insert variants if any
    if ($has_variants && !empty($variants)) {
        $variant_sql = "INSERT INTO variants (product_id, variant_name, quantity, price, image) VALUES (?, ?, ?, ?, ?)";
        $variant_stmt = $conn->prepare($variant_sql);
        
        if (!$variant_stmt) {
            error_log("Failed to prepare variant statement: " . $conn->error);
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
                                error_log("Variant image uploaded: $variant_image_path");
                            }
                        }
                    }
                }
                
                $variant_stmt->bind_param("isids", $product_id, $variant_name, $variant_quantity, $variant_price, $variant_image_path);
                if (!$variant_stmt->execute()) {
                    error_log("Failed to insert variant: " . $variant_stmt->error);
                }
            }
        }
        $variant_stmt->close();
    }
    
    echo json_encode(['success' => true, 'message' => 'Product added successfully']);
} else {
    error_log("Failed to insert product: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
