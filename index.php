<?php
/**
 * Index — Role-based redirect
 * Sends each user to their appropriate landing page
 */
require_once 'Security.php';
Security::initSession();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = strtolower($_SESSION['role_name'] ?? '');

switch ($role) {
    case 'customer':
        header('Location: onlineordering.php');
        break;
    case 'cashier':
        header('Location: pos.php');
        break;
    case 'inventory_manager':
        header('Location: inventory_management.php');
        break;
    default:
        // admin, super_admin, manager → dashboard
        header('Location: dashboard.php');
        break;
}
exit;
