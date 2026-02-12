<?php
/**
 * Login Handler with Enhanced Security
 * Processes login requests with rate limiting and security checks
 */

require_once 'db_connection.php';
require_once 'Auth.php';
require_once 'Security.php';
require_once 'CSRF.php';

// Initialize security
Security::initSession();

// Set secure headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Handle POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Security::jsonResponse([
        'success' => false,
        'message' => 'Method not allowed'
    ], 405);
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (empty($data['username']) || empty($data['password'])) {
    Security::logEvent('LOGIN_FAILED', 'Missing credentials', ['ip' => Security::getClientIP()]);
    Security::jsonResponse([
        'success' => false,
        'message' => 'Username and password are required'
    ]);
}

// Sanitize inputs
$username = Security::sanitizeInput($data['username']);
$password = $data['password']; // Don't sanitize password

// Check rate limiting
$identifier = Security::getClientIP() . '_' . $username;
$rateLimit = Security::checkRateLimit($identifier);

if (!$rateLimit['allowed']) {
    Security::logEvent('LOGIN_BLOCKED', 'Too many failed attempts', [
        'username' => $username,
        'ip' => Security::getClientIP()
    ]);
    
    Security::jsonResponse([
        'success' => false,
        'message' => "Too many failed login attempts. Please try again in {$rateLimit['wait_minutes']} minutes.",
        'locked_until' => date('Y-m-d H:i:s', $rateLimit['reset_time'])
    ], 429);
}

// Verify CSRF token
if (!CSRF::validateAjax()) {
    Security::logEvent('CSRF_FAILED', 'Invalid CSRF token on login', ['username' => $username]);
    Security::jsonResponse([
        'success' => false,
        'message' => 'Invalid security token. Please refresh the page.'
    ], 403);
}

// Initialize Auth class
$auth = new Auth($conn);

// Attempt login
$result = $auth->login($username, $password);

// Handle result
if ($result['success']) {
    // Reset login attempts on successful login
    Security::resetLoginAttempts($identifier);
    
    // Log successful login
    Security::logEvent('LOGIN_SUCCESS', 'User logged in successfully', [
        'username' => $username,
        'user_id' => $_SESSION['user_id'] ?? 'Unknown'
    ]);
    
    // Regenerate CSRF token
    CSRF::regenerate();
    
    // Add new token to response
    $result['csrf_token'] = CSRF::getToken();

    // Include role_name for client-side redirect logic
    $result['role_name'] = $_SESSION['role_name'] ?? '';
} else {
    // Record failed login attempt
    Security::recordFailedLogin($identifier);
    
    // Log failed login
    Security::logEvent('LOGIN_FAILED', 'Invalid credentials', [
        'username' => $username,
        'attempts_remaining' => $rateLimit['remaining'] - 1
    ]);
    
    // Add remaining attempts to response
    $remainingAttempts = $rateLimit['remaining'] - 1;
    if ($remainingAttempts > 0) {
        $result['message'] .= " You have $remainingAttempts attempts remaining.";
    }
}

// Return result
Security::jsonResponse($result);
?>
