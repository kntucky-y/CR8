<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['action'])) {
    sendError('Action is required');
}

$action = $data['action'];

switch ($action) {
    case 'send':
        sendContactMessage($data);
        break;
    default:
        sendError('Invalid action');
}

function sendContactMessage($data) {
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $subject = $data['subject'] ?? '';
    $message = $data['message'] ?? '';

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        sendError('All fields are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email address');
    }

    $conn = getDbConnection();
    
    // Insert into existing messages table
    $stmt = $conn->prepare("
        INSERT INTO messages (name, email, subject, message, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    if (!$stmt) {
        sendError('Failed to prepare message: ' . $conn->error);
    }

    $stmt->bind_param("ssss", $name, $email, $subject, $message);

    if ($stmt->execute()) {
        sendResponse([
            'success' => true,
            'message' => 'Your message has been sent successfully! We will get back to you soon.'
        ]);
    } else {
        sendError('Failed to send message. Please try again.');
    }
}
