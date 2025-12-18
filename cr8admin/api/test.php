<?php
// Simple test to check if API is working
session_start();

// Handle CORS
$allowed_origins = ['http://localhost:5173', 'http://localhost:5174'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Test database connection
$conn = new mysqli('dbadmin.dcism.org', 's24102191_cr8db', 'cr8db!!!', 's24102191_cr8db');

if ($conn->connect_error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// Test if admin table exists and has data
$result = $conn->query("SELECT id, username, is_superadmin FROM admins LIMIT 1");

if (!$result) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to query admins table: ' . $conn->error
    ]);
    exit();
}

$admin = $result->fetch_assoc();

echo json_encode([
    'status' => 'success',
    'message' => 'API is working correctly',
    'database_connected' => true,
    'sample_admin_exists' => $admin !== null,
    'sample_admin' => $admin ? ['username' => $admin['username']] : null,
    'session_working' => session_status() === PHP_SESSION_ACTIVE,
    'origin_allowed' => in_array($origin, $allowed_origins),
    'received_origin' => $origin
]);

$conn->close();
