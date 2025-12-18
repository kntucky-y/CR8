<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: https://cr8.dcism.org');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check the actual role from database (not just session)
$role_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
$role_check->bind_param('i', $user_id);
$role_check->execute();
$role_result = $role_check->get_result();
$db_role = $role_result->fetch_assoc();
$role_check->close();

error_log("Artist products access - User ID: $user_id, Session Role: " . ($_SESSION['role'] ?? 'not set') . ", DB Role: " . ($db_role ? $db_role['role'] : 'not found'));

if (!$db_role || $db_role['role'] !== 'artist') {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - Not an artist',
        'debug' => [
            'session_role' => $_SESSION['role'] ?? 'not set',
            'db_role' => $db_role ? $db_role['role'] : 'not found'
        ]
    ]);
    exit();
}

// Update session role if it's out of sync
if ($_SESSION['role'] !== $db_role['role']) {
    $_SESSION['role'] = $db_role['role'];
}

// Get artist_id from user_id - check all artists first for debugging
$debug_stmt = $conn->prepare("SELECT id, is_archived, status FROM artists WHERE user_id = ?");
$debug_stmt->bind_param('i', $user_id);
$debug_stmt->execute();
$debug_result = $debug_stmt->get_result();
$debug_artist = $debug_result->fetch_assoc();
$debug_stmt->close();

error_log("Artist lookup for user_id $user_id: " . json_encode($debug_artist));

// Get artist_id from user_id (only active artists)
$artist_stmt = $conn->prepare("SELECT id FROM artists WHERE user_id = ? AND is_archived = 0");
$artist_stmt->bind_param('i', $user_id);
$artist_stmt->execute();
$artist_result = $artist_stmt->get_result();

if ($artist_row = $artist_result->fetch_assoc()) {
    $artist_id = $artist_row['id'];
} else {
    $error_msg = 'Artist profile not found';
    if ($debug_artist) {
        $error_msg .= ' (found artist id=' . $debug_artist['id'] . ', is_archived=' . $debug_artist['is_archived'] . ', status=' . $debug_artist['status'] . ')';
    }
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit();
}
$artist_stmt->close();

// Fetch products for this artist
$products_sql = "SELECT id, product_name, description, price, quantity, image, is_active, deactivation_reason FROM products WHERE artist_id = ? ORDER BY product_name ASC";
$products_stmt = $conn->prepare($products_sql);
$products_stmt->bind_param('i', $artist_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();

$products = [];
while ($row = $products_result->fetch_assoc()) {
    $product_id = $row['id'];
    
    // Fetch variants for this product
    $variants_sql = "SELECT id, variant_name, quantity, price, image FROM variants WHERE product_id = ? ORDER BY id ASC";
    $variants_stmt = $conn->prepare($variants_sql);
    $variants_stmt->bind_param('i', $product_id);
    $variants_stmt->execute();
    $variants_result = $variants_stmt->get_result();
    
    $variants = [];
    while ($variant_row = $variants_result->fetch_assoc()) {
        $variants[] = $variant_row;
    }
    $variants_stmt->close();
    
    $row['variants'] = $variants;
    $products[] = $row;
}

$products_stmt->close();
$conn->close();

echo json_encode(['success' => true, 'products' => $products]);
