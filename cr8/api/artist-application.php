<?php
require_once 'config.php';

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

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Get action from query parameter or POST body
$action = $_GET['action'] ?? $data['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action is required']);
    exit();
}

switch ($action) {
    case 'submit':
        submitApplication($data, $conn, $_SESSION['user_id']);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
}

function submitApplication($data, $conn, $user_id) {
    $email = $data['email'] ?? '';
    $full_name = $data['full_name'] ?? '';
    $artist_name = $data['artist_name'] ?? '';
    $contact_number = $data['contact_number'] ?? '';
    $portfolio = $data['portfolio'] ?? '';
    $product_desc = $data['product_desc'] ?? '';

    if (empty($email) || empty($full_name) || empty($artist_name) || 
        empty($contact_number) || empty($portfolio) || empty($product_desc)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit();
    }

    // Validate contact number format (09XXXXXXXXX)
    if (!preg_match('/^09\d{9}$/', $contact_number)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Contact number must be 11 digits starting with 09']);
        exit();
    }

    if (!filter_var($portfolio, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid portfolio URL']);
        exit();
    }

    // Check if user already has a pending or approved application
    // But allow reapplication if their artist account was revoked (is_archived = 1)
    $checkStmt = $conn->prepare("
        SELECT aa.id, aa.status, a.is_archived
        FROM artist_applications aa
        LEFT JOIN artists a ON aa.user_id = a.user_id
        WHERE aa.user_id = ? AND aa.status IN ('Unread', 'Read', 'Approved')
        LIMIT 1
    ");
    $checkStmt->bind_param("i", $user_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        
        // Allow reapplication if artist was revoked (archived)
        if ($existing['status'] === 'Approved' && $existing['is_archived'] != 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'You already have an approved artist application']);
            exit();
        } else if ($existing['status'] !== 'Approved') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'You already have a pending application. Please wait for review.']);
            exit();
        }
    }

    // Insert new application
    $stmt = $conn->prepare("
        INSERT INTO artist_applications 
        (user_id, email, full_name, artist_name, contact_number, portfolio, product_desc, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Unread', NOW())
    ");

    $stmt->bind_param(
        "issssss",
        $user_id,
        $email,
        $full_name,
        $artist_name,
        $contact_number,
        $portfolio,
        $product_desc
    );

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Your application has been submitted successfully! We will review it and contact you soon.'
        ]);
        exit();
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to submit application: ' . $stmt->error,
            'debug' => $conn->error
        ]);
        exit();
    }
}
