<?php
/**
 * Get Loyalty Members API
 * Returns all loyalty members for admin view
 */

require_once '../db_connection.php';
require_once '../Auth.php';

$auth = new Auth($conn);
$auth->requireAuth('../login.php');

if (!$auth->hasPermission('pos.access')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

// Handle POST requests (add member)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Name is required']);
            exit;
        }

        try {
            // Check for duplicate by email if provided
            if ($email !== '') {
                $check = $conn->prepare("SELECT member_id FROM loyalty_members WHERE email = ?");
                $check->bind_param('s', $email);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'A member with this email already exists']);
                    $check->close();
                    exit;
                }
                $check->close();
            }

            $stmt = $conn->prepare("INSERT INTO loyalty_members (name, email, phone, points, member_since) VALUES (?, ?, ?, 0, NOW())");
            $stmt->bind_param('sss', $name, $email, $phone);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Loyalty member added successfully',
                'member' => [
                    'id' => $newId,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'points' => 0,
                    'memberSince' => date('Y-m-d')
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to add loyalty member']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// Handle GET requests (list members)
try {
    $stmt = $conn->prepare("
        SELECT 
            member_id as id,
            name,
            email,
            phone,
            points,
            member_since as memberSince
        FROM loyalty_members
        ORDER BY member_since DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = [
            'id' => intval($row['id']),
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?? '',
            'points' => round(floatval($row['points']), 2),
            'memberSince' => $row['memberSince']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'members' => $members
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch loyalty members'
    ]);
}
?>
