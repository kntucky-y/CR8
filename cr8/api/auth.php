<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'config.php';

// Add CORS headers for React app
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: https://cr8.dcism.org');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Session configuration for cross-domain cookies
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

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // Check both email and username
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Check if password is hashed (starts with $2y$)
            $isHashed = substr($user['password'], 0, 4) === '$2y$';
            
            $passwordMatch = false;
            if ($isHashed) {
                // Use password_verify for hashed passwords
                $passwordMatch = password_verify($password, $user['password']);
            } else {
                // Direct comparison for plain text passwords (temporary - for migration)
                $passwordMatch = ($password === $user['password']);
            }
            
            if ($passwordMatch) {
                // Check if user is an artist and if they're archived
                $artist_check = $conn->prepare("SELECT is_archived FROM artists WHERE user_id = ?");
                $artist_check->bind_param("i", $user['id']);
                $artist_check->execute();
                $artist_result = $artist_check->get_result();
                
                $is_archived = false;
                if ($artist_data = $artist_result->fetch_assoc()) {
                    $is_archived = $artist_data['is_archived'] == 1;
                }
                $artist_check->close();
                
                // If artist is archived, set role to 'user'
                if ($user['role'] === 'artist' && $is_archived) {
                    $user['role'] = 'user';
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                unset($user['password']);
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Invalid password']);
            }
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        $stmt->close();
        break;

    case 'register':
        $data = json_decode(file_get_contents('php://input'), true);
        $first_name = $data['first_name'] ?? '';
        $last_name = $data['last_name'] ?? '';
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = password_hash($data['password'] ?? '', PASSWORD_DEFAULT);
        $address = $data['address'] ?? '';

        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            $stmt->close();
            break;
        }
        $stmt->close();

        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email, password, address, role) VALUES (?, ?, ?, ?, ?, ?, 'customer')");
        $stmt->bind_param("ssssss", $first_name, $last_name, $username, $email, $password, $address);
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'customer';
            
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'username' => $username,
                    'email' => $email,
                    'address' => $address,
                    'role' => 'user'
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Registration failed']);
        }
        $stmt->close();
        break;

    case 'check':
        if (isset($_SESSION['user_id'])) {
            $stmt = $conn->prepare("
                SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.address, u.phone, u.role,
                       a.id as artist_id, a.artist_name, a.status as artist_status, a.is_archived
                FROM users u
                LEFT JOIN artists a ON u.id = a.user_id
                WHERE u.id = ?
            ");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($user = $result->fetch_assoc()) {
                // If user is an artist but is archived, revoke artist role
                if ($user['role'] === 'artist' && $user['is_archived'] == 1) {
                    $user['role'] = 'user';
                    $_SESSION['role'] = 'user';
                }
                // Clean up artist fields from response if not needed
                unset($user['is_archived']);
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    case 'forgot-password':
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';

        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email is required']);
            break;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Don't reveal if email exists or not for security
            echo json_encode(['success' => true, 'message' => 'If your email is registered, you will receive password reset instructions']);
            $stmt->close();
            break;
        }

        $user = $result->fetch_assoc();
        $stmt->close();

        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $resetToken);
        $resetExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Save token (hashed)
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?");
        $stmt->bind_param("sss", $token_hash, $resetExpiry, $email);
        $stmt->execute();
        $stmt->close();

        // Send email with PHPMailer
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
        }
        
        $mail = new PHPMailer(true);
        try {
            $mail->SMTPDebug = 0; // Set to 2 for debugging
            $mail->isSMTP();
            $mail->Host       = '';
            $mail->SMTPAuth   = true;
            $mail->Username   = '';
            $mail->Password   = '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom('ladykgutz@gmail.com', 'CR8 Cebu');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request for CR8 Shop';
            $reset_link = "https://cr8.dcism.org/#/reset-password?token=" . $resetToken;
            $mail->Body = "Hello,<br><br>Please click the link below to reset your password. This link will expire in 24 hours.<br><br><a href='{$reset_link}' style='display: inline-block; padding: 12px 24px; background-color: #7c3aed; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>Reset Your Password</a><br><br>Or copy this link: {$reset_link}<br><br>If you did not request this, please ignore this email.<br><br>Thank you,<br>CR8 Team";

            if (!$mail->send()) {
                error_log("PHPMailer failed to send to $email: " . $mail->ErrorInfo);
                throw new Exception($mail->ErrorInfo);
            }
            
            error_log("Password reset email sent successfully to: $email");
            
            echo json_encode([
                'success' => true,
                'message' => 'Password reset instructions have been sent to your email'
            ]);
        } catch (Exception $e) {
            error_log("Mailer Exception for $email: " . $e->getMessage());
            // Return actual error for debugging (remove this in production)
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ]);
        }
        break;

    case 'verify-reset-token':
        $data = json_decode(file_get_contents('php://input'), true);
        $token = $data['token'] ?? '';

        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Token is required']);
            break;
        }

        $token_hash = hash('sha256', $token);
        $stmt = $conn->prepare("SELECT id, email FROM users WHERE reset_token = ? AND token_expiry > NOW()");
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo json_encode(['success' => true, 'email' => $user['email']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        }
        $stmt->close();
        break;

    case 'reset-password':
        $data = json_decode(file_get_contents('php://input'), true);
        $token = $data['token'] ?? '';
        $newPassword = $data['password'] ?? '';

        if (empty($token) || empty($newPassword)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Token and password are required']);
            break;
        }

        $token_hash = hash('sha256', $token);
        $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND token_expiry > NOW()");
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
            $stmt->close();
            break;
        }

        $user = $result->fetch_assoc();
        $stmt->close();

        // Hash the new password and update
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $user['id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
        }
        $stmt->close();
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
