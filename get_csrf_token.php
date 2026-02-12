<?php
/**
 * Get CSRF Token
 * Returns CSRF token for client-side use
 */

session_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

header('Content-Type: application/json');
echo json_encode([
    'token' => $_SESSION['csrf_token']
]);
?>
