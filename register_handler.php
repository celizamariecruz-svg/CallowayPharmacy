<?php
/**
 * Registration Handler
 * Creates a new customer account and inserts into users table
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

// Validate password length
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit;
}

// Sanitize
$fullName = Security::sanitizeInput($fullName);
$username = Security::sanitizeInput($username);
$email    = Security::sanitizeInput($email);

try {
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

    // Check if username already exists
    $checkUser = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
    $checkUser->bind_param("s", $username);
    $checkUser->execute();
    if ($checkUser->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username is already taken. Please choose another.']);
        exit;
    }

    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    if ($checkEmail->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email is already registered. Please use another or login.']);
        exit;
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert the new customer user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role_id, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("ssssi", $username, $email, $passwordHash, $fullName, $customerRoleId);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! You can now log in.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
?>
