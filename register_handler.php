<?php
/**
 * Registration Handler
 * Creates a new customer account with email verification
 */

require_once 'db_connection.php';
require_once 'Security.php';
require_once 'CSRF.php';

Security::initSession();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    http_response_code(405);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
$fullName  = trim($data['full_name'] ?? '');
$username  = trim($data['username'] ?? '');
$email     = trim($data['email'] ?? '');
$password  = $data['password'] ?? '';

if (!$fullName || !$username || !$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// Validate username length
if (strlen($username) < 3 || strlen($username) > 50) {
    echo json_encode(['success' => false, 'message' => 'Username must be between 3 and 50 characters.']);
    exit;
}

// Validate password strength: 8+ chars, 1 uppercase, 1 special character
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}
if (!preg_match('/[A-Z]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter.']);
    exit;
}
if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one special character.']);
    exit;
}

// Sanitize
$fullName = Security::sanitizeInput($fullName);
$username = Security::sanitizeInput($username);
$email    = Security::sanitizeInput($email);

// Helper: check column exists
function registerColumnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

try {
    // Ensure verification columns exist
    if (!registerColumnExists($conn, 'users', 'email_verify_code')) {
        $conn->query("ALTER TABLE users ADD COLUMN email_verify_code VARCHAR(10) DEFAULT NULL");
    }
    if (!registerColumnExists($conn, 'users', 'email_verify_expires')) {
        $conn->query("ALTER TABLE users ADD COLUMN email_verify_expires DATETIME DEFAULT NULL");
    }
    if (!registerColumnExists($conn, 'users', 'email_verified')) {
        $conn->query("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0");
    }

    // Ensure the 'customer' role exists; create it if not
    $roleCheck = $conn->prepare("SELECT role_id FROM roles WHERE role_name = 'customer' LIMIT 1");
    $roleCheck->execute();
    $roleResult = $roleCheck->get_result();

    if ($roleResult->num_rows === 0) {
        $insertRole = $conn->prepare("INSERT INTO roles (role_name, description) VALUES ('customer', 'Customer with online ordering access')");
        $insertRole->execute();
        $customerRoleId = $conn->insert_id;
    } else {
        $customerRoleId = $roleResult->fetch_assoc()['role_id'];
    }

    // Check for existing user (username or email)
    // We want to allow re-registration if the previous attempt was never verified
    $checkStmt = $conn->prepare("SELECT user_id, username, email, is_active, email_verified FROM users WHERE username = ? OR email = ?");
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // If account is active or verified, we strictly block
        if ($row['is_active'] == 1 || $row['email_verified'] == 1) {
            if (strtolower($row['username']) === strtolower($username)) {
                echo json_encode(['success' => false, 'message' => 'Username is already taken.']);
                exit;
            }
            if (strtolower($row['email']) === strtolower($email)) {
                echo json_encode(['success' => false, 'message' => 'Email is already registered. Please login.']);
                exit;
            }
        } else {
            // Account is inactive AND unverified - it's a stale/failed registration
            // Delete it so we can create the new one
            $delParams = $row['user_id'];
            $conn->query("DELETE FROM users WHERE user_id = $delParams");
        }
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Generate 6-digit verification code
    $verifyCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $verifyExpires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // Insert the new customer user (inactive until verified)
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role_id, is_active, email_verify_code, email_verify_expires, email_verified) VALUES (?, ?, ?, ?, ?, 0, ?, ?, 0)");
    $stmt->bind_param("ssssiss", $username, $email, $passwordHash, $fullName, $customerRoleId, $verifyCode, $verifyExpires);

    if ($stmt->execute()) {
        $newUserId = $conn->insert_id;

        // Try to send verification email
        $emailSent = false;
        try {
            if (file_exists(__DIR__ . '/email_service.php')) {
                require_once 'email_service.php';
                $emailService = new EmailService($conn);
                $emailBody = "
                    <div style='font-family:Segoe UI,system-ui,sans-serif;max-width:500px;margin:0 auto;'>
                        <h2 style='color:#2563eb;'>Verify Your Email</h2>
                        <p>Hi {$fullName},</p>
                        <p>Thank you for registering at Calloway Pharmacy! Your verification code is:</p>
                        <div style='text-align:center;margin:1.5rem 0;'>
                            <span style='font-size:2rem;font-weight:800;letter-spacing:8px;color:#2563eb;background:#f0f4f8;padding:0.75rem 1.5rem;border-radius:12px;display:inline-block;'>{$verifyCode}</span>
                        </div>
                        <p>This code expires in 30 minutes.</p>
                        <p style='color:#666;font-size:0.9rem;'>If you didn't register, please ignore this email.</p>
                    </div>
                ";
                $emailSent = $emailService->sendCustomEmail($email, 'Verify your Calloway Pharmacy account', $emailBody);
            }
        } catch (Exception $emailErr) {
            error_log("Verification email failed: " . $emailErr->getMessage());
        }

        echo json_encode([
            'success' => true,
            'needs_verification' => true,
            'email_sent' => $emailSent,
            'email_hint' => $emailSent ? '' : $verifyCode, // Show code if email failed (dev mode)
            'user_email' => $email,
            'message' => $emailSent
                ? 'Registration successful! Check your email for the verification code.'
                : 'Registration successful! Enter the verification code to activate your account.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
?>
