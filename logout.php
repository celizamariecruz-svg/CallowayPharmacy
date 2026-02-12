<?php
/**
 * Logout Handler
 * Logs out current user and redirects to login page
 */

require_once 'db_connection.php';
require_once 'Auth.php';

// Initialize Auth class
$auth = new Auth($conn);

// Logout user
$auth->logout();

// Redirect to login page
header('Location: login.php');
exit;
?>
