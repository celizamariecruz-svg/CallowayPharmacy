<?php
/**
 * Get Loyalty Points API
 * Returns available loyalty points for logged-in user
 */

session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'points' => 0, 'message' => 'Not logged in']);
    exit;
}

$userId = intval($_SESSION['user_id']);

// Get user email
$stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || empty($user['email'])) {
    echo json_encode(['success' => false, 'points' => 0, 'message' => 'User not found']);
    exit;
}

$email = $user['email'];

// Get loyalty member points
$stmt = $conn->prepare("SELECT member_id, points FROM loyalty_members WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($member) {
    echo json_encode([
        'success' => true,
        'points' => round((float)$member['points'], 2),
        'member_id' => intval($member['member_id'])
    ]);
} else {
    echo json_encode([
        'success' => true,
        'points' => 0,
        'message' => 'No loyalty account yet'
    ]);
}
?>
