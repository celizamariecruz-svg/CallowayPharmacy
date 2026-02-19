<?php
/**
 * Forgot Password Handler
 * Receives email via POST, generates a reset token, stores it, and sends a reset link email.
 */

require_once 'db_connection.php';
require_once 'Security.php';

function maskEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return 'your registered email';
    }
    $name = $parts[0];
    $domain = $parts[1];
    if (strlen($name) <= 2) {
        $maskedName = substr($name, 0, 1) . '*';
    } else {
        $maskedName = substr($name, 0, 2) . str_repeat('*', max(1, strlen($name) - 2));
    }
    return $maskedName . '@' . $domain;
}

Security::initSession();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$identifier = trim($input['identifier'] ?? ($input['email'] ?? ''));

if (empty($identifier)) {
    echo json_encode(['success' => false, 'message' => 'Please enter your username or email address.']);
    exit;
}

try {
    // Look up user by email or username
    $stmt = $conn->prepare("SELECT user_id, username, email, is_active FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Always return success to prevent email enumeration, but only send if user exists
    if (!$user) {
        echo json_encode([
            'success' => true,
            'message' => 'If an account with that username or email exists, a password reset link has been sent.'
        ]);
        exit;
    }

    if (!$user['is_active']) {
        echo json_encode([
            'success' => true,
            'message' => 'If an account with that username or email exists, a password reset link has been sent.'
        ]);
        exit;
    }

    // Generate a secure random token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store token in database
    $stmt = $conn->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $token, $expires, $user['user_id']);
    $stmt->execute();

    // Send the reset email via EmailService
    require_once 'email_service.php';
    try {
        $emailService = new EmailService($conn);
        $sent = $emailService->sendPasswordReset($user['email'], $user['username'], $token);
        $sendError = $emailService->getLastError();
    } catch (Throwable $mailErr) {
        error_log("EmailService error: " . $mailErr->getMessage());
        $sent = false;
        $sendError = $mailErr->getMessage();
    }

    if (!$sent) {
        error_log("Failed to send password reset email to: " . $user['email'] . " | Error: " . ($sendError ?? 'sendPasswordReset returned false'));
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send reset email. Error: ' . ($sendError ?: 'Mail delivery failed. SMTP auth/port/encryption may be incorrect on this machine.'),
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Password reset link sent to ' . maskEmail($user['email']) . '. Please check your inbox (and spam folder).'
    ]);

} catch (Throwable $e) {
    error_log("Forgot password error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}
