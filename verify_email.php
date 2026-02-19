<?php
/**
 * Email Verification Handler
 * POST { email, code } → verifies code and activates account
 * POST { email, action: 'resend' } → resends verification code
 */
// Prevent any output before JSON
ob_start();

// Ensure PHP warnings/notices do not leak as HTML into JSON responses
ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// Set header but don't output anything yet
header('Content-Type: application/json');

// Error handling function to ensure JSON response
function sendError($message, $debug = null) {
    ob_clean(); // Clear any previous output
    echo json_encode([
        'success' => false, 
        'message' => $message,
        'debug' => $debug
    ]);
    exit;
}

// Exception handler
set_exception_handler(function($e) {
    ob_clean();
    echo json_encode([
        'success' => false, 
        'message' => 'An internal error occurred.',
        'debug' => $e->getMessage()
    ]);
    exit;
});

// Convert runtime warnings/notices into exceptions so output stays valid JSON
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed');
}

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendError('Invalid JSON input.');
}

$email = trim($input['email'] ?? '');
$code  = trim($input['code'] ?? '');
$action = $input['action'] ?? 'verify';

if (!$email) {
    sendError('Email is required.');
}

// Helper to check column
function veColExists($conn, $table, $col) {
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return $r && $r->num_rows > 0;
}

try {
    // Ensure columns exist in database 
    // (This should have been done in register, but double check to avoid crash)
    if (!veColExists($conn, 'users', 'email_verify_code')) {
        sendError('Verification system not fully configured. Please contact support.');
    }

    if ($action === 'resend') {
        // Resend verification code
        $stmt = $conn->prepare("SELECT user_id, full_name, email_verified, is_active FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Email not found.']);
            exit;
        }

        $user = $result->fetch_assoc();
        if ($user['email_verified'] || $user['is_active']) {
            echo json_encode(['success' => false, 'message' => 'Account is already verified.']);
            exit;
        }

        // Generate new code
        $newCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $newExpires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $upd = $conn->prepare("UPDATE users SET email_verify_code = ?, email_verify_expires = ? WHERE user_id = ?");
        $upd->bind_param("ssi", $newCode, $newExpires, $user['user_id']);
        $upd->execute();

        // Try sending email
        $emailSent = false;
        try {
            if (file_exists(__DIR__ . '/email_service.php')) {
                require_once 'email_service.php';
                $emailService = new EmailService($conn);
                $emailBody = "
                    <div style='font-family:Segoe UI,system-ui,sans-serif;max-width:500px;margin:0 auto;'>
                        <h2 style='color:#2563eb;'>Your New Verification Code</h2>
                        <p>Hi {$user['full_name']},</p>
                        <p>Your new verification code is:</p>
                        <div style='text-align:center;margin:1.5rem 0;'>
                            <span style='font-size:2rem;font-weight:800;letter-spacing:8px;color:#2563eb;background:#f0f4f8;padding:0.75rem 1.5rem;border-radius:12px;display:inline-block;'>{$newCode}</span>
                        </div>
                        <p>This code expires in 30 minutes.</p>
                    </div>
                ";
                $emailSent = $emailService->sendCustomEmail($email, 'Your new verification code - Calloway Pharmacy', $emailBody);
            }
        } catch (Exception $e) {
            error_log("Resend verification email failed: " . $e->getMessage());
        }

        ob_clean();
        echo json_encode([
            'success' => true,
            'email_sent' => $emailSent,
            'email_hint' => $emailSent ? '' : $newCode,
            'message' => $emailSent ? 'A new code has been sent to your email.' : 'New verification code generated.'
        ]);
        exit;
    }

    // Verify code validation
    if (!$code || strlen($code) !== 6) {
        sendError('Please enter a valid 6-digit code.');
    }

    $stmt = $conn->prepare("SELECT user_id, email_verify_code, email_verify_expires, email_verified, is_active FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        sendError('Database error (stmt): ' . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        sendError('Database error (exec): ' . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendError('Email address not found in our records.');
    }

    $user = $result->fetch_assoc();

    // Check if already active/verified
    if ($user['email_verified'] == 1 || $user['is_active'] == 1) {
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Account is already verified. You can log in.']);
        exit;
    }

    // Check code match
    if (trim((string)$user['email_verify_code']) !== trim((string)$code)) {
        sendError('Invalid verification code. Please check and try again.');
    }

    // Check expiry
    if (!empty($user['email_verify_expires']) && strtotime($user['email_verify_expires']) < time()) {
        sendError('Verification code has expired. Please request a new one.');
    }

    // Activate account
    $activate = $conn->prepare("UPDATE users SET is_active = 1, email_verified = 1, email_verify_code = NULL, email_verify_expires = NULL WHERE user_id = ?");
    if ($activate) {
        $activate->bind_param("i", $user['user_id']);
        $activate->execute();
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully! You can now log in.'
    ]);

} catch (Exception $e) {
    error_log("Email verification error: " . $e->getMessage());
    sendError('An error occurred during verification.', $e->getMessage());
}
?>
