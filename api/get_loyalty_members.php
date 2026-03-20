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

function columnExists($conn, $table, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return intval($row['cnt'] ?? 0) > 0;
}

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
    $action = $_GET['action'] ?? '';

    if ($action === 'member_details') {
        $memberId = intval($_GET['member_id'] ?? 0);
        if ($memberId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid member id']);
            exit;
        }

        $memberStmt = $conn->prepare("SELECT member_id, user_id, name, email, phone, points, member_since FROM loyalty_members WHERE member_id = ? LIMIT 1");
        $memberStmt->bind_param('i', $memberId);
        $memberStmt->execute();
        $member = $memberStmt->get_result()->fetch_assoc();
        $memberStmt->close();

        if (!$member) {
            echo json_encode(['success' => false, 'message' => 'Member not found']);
            exit;
        }

        $pointsLogs = [];
        $pointsEarned = 0.0;
        $pointsRedeemed = 0.0;
        $logCount = 0;

        if ($conn->query("SHOW TABLES LIKE 'loyalty_points_log'")->num_rows > 0) {
            $summaryStmt = $conn->prepare("SELECT 
                    COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END), 0) AS earned,
                    COALESCE(SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END), 0) AS redeemed,
                    COUNT(*) AS log_count
                FROM loyalty_points_log
                WHERE member_id = ?");
            $summaryStmt->bind_param('i', $memberId);
            $summaryStmt->execute();
            $summary = $summaryStmt->get_result()->fetch_assoc();
            $summaryStmt->close();

            $pointsEarned = round(floatval($summary['earned'] ?? 0), 2);
            $pointsRedeemed = round(floatval($summary['redeemed'] ?? 0), 2);
            $logCount = intval($summary['log_count'] ?? 0);

            $logsStmt = $conn->prepare("SELECT log_id, points, transaction_type, reference_id, description, created_at FROM loyalty_points_log WHERE member_id = ? ORDER BY created_at DESC LIMIT 150");
            $logsStmt->bind_param('i', $memberId);
            $logsStmt->execute();
            $logsResult = $logsStmt->get_result();
            while ($row = $logsResult->fetch_assoc()) {
                $pointsLogs[] = [
                    'log_id' => intval($row['log_id']),
                    'points' => round(floatval($row['points']), 2),
                    'transaction_type' => $row['transaction_type'],
                    'reference_id' => $row['reference_id'],
                    'description' => $row['description'],
                    'created_at' => $row['created_at']
                ];
            }
            $logsStmt->close();
        }

        $transactions = [];

        if ($conn->query("SHOW TABLES LIKE 'sales'")->num_rows > 0 && columnExists($conn, 'sales', 'loyalty_member_id')) {
            $salesCols = ['sale_id', 'sale_reference', 'total', 'payment_method', 'cashier', 'created_at'];
            if (columnExists($conn, 'sales', 'points_redeemed')) {
                $salesCols[] = 'points_redeemed';
            }
            $salesColsSql = implode(', ', $salesCols);

            $salesStmt = $conn->prepare("SELECT $salesColsSql FROM sales WHERE loyalty_member_id = ? ORDER BY created_at DESC LIMIT 100");
            $salesStmt->bind_param('i', $memberId);
            $salesStmt->execute();
            $salesResult = $salesStmt->get_result();
            while ($row = $salesResult->fetch_assoc()) {
                $transactions[] = [
                    'source' => 'POS',
                    'reference' => $row['sale_reference'] ?? ('SALE-' . intval($row['sale_id'] ?? 0)),
                    'amount' => round(floatval($row['total'] ?? 0), 2),
                    'points_redeemed' => round(floatval($row['points_redeemed'] ?? 0), 2),
                    'payment_method' => $row['payment_method'] ?? 'N/A',
                    'actor' => $row['cashier'] ?? 'N/A',
                    'created_at' => $row['created_at'] ?? null
                ];
            }
            $salesStmt->close();
        }

        if ($conn->query("SHOW TABLES LIKE 'online_orders'")->num_rows > 0) {
            $onlineHasCustomerId = columnExists($conn, 'online_orders', 'customer_id');
            $onlineHasCustomerEmail = columnExists($conn, 'online_orders', 'customer_email');
            $onlineHasEmail = columnExists($conn, 'online_orders', 'email');
            $onlineHasOrderRef = columnExists($conn, 'online_orders', 'order_ref');
            $onlineHasPaymentMethod = columnExists($conn, 'online_orders', 'payment_method');
            $onlineHasUpdatedAt = columnExists($conn, 'online_orders', 'updated_at');

            $whereParts = [];
            $bindTypes = '';
            $bindValues = [];

            if ($onlineHasCustomerId && !empty($member['user_id'])) {
                $whereParts[] = 'customer_id = ?';
                $bindTypes .= 'i';
                $bindValues[] = intval($member['user_id']);
            }
            if ($onlineHasCustomerEmail && !empty($member['email'])) {
                $whereParts[] = 'customer_email = ?';
                $bindTypes .= 's';
                $bindValues[] = $member['email'];
            }
            if ($onlineHasEmail && !empty($member['email'])) {
                $whereParts[] = 'email = ?';
                $bindTypes .= 's';
                $bindValues[] = $member['email'];
            }

            if (!empty($whereParts)) {
                $onlineCols = ['order_id', 'total_amount', 'status', 'created_at'];
                if ($onlineHasOrderRef) $onlineCols[] = 'order_ref';
                if ($onlineHasPaymentMethod) $onlineCols[] = 'payment_method';
                if ($onlineHasUpdatedAt) $onlineCols[] = 'updated_at';
                $onlineColsSql = implode(', ', $onlineCols);

                $sql = "SELECT $onlineColsSql FROM online_orders WHERE " . implode(' OR ', $whereParts) . " ORDER BY created_at DESC LIMIT 100";
                $onlineStmt = $conn->prepare($sql);
                if ($onlineStmt) {
                    $onlineStmt->bind_param($bindTypes, ...$bindValues);
                    $onlineStmt->execute();
                    $onlineResult = $onlineStmt->get_result();
                    while ($row = $onlineResult->fetch_assoc()) {
                        $transactions[] = [
                            'source' => 'ONLINE',
                            'reference' => $row['order_ref'] ?? ('ONL-' . intval($row['order_id'] ?? 0)),
                            'amount' => round(floatval($row['total_amount'] ?? 0), 2),
                            'status' => $row['status'] ?? 'N/A',
                            'payment_method' => $row['payment_method'] ?? 'N/A',
                            'actor' => 'Online Customer',
                            'created_at' => $row['created_at'] ?? null
                        ];
                    }
                    $onlineStmt->close();
                }
            }
        }

        usort($transactions, function ($a, $b) {
            return strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01');
        });

        $totalTransactionValue = 0.0;
        foreach ($transactions as $tx) {
            $totalTransactionValue += floatval($tx['amount'] ?? 0);
        }

        echo json_encode([
            'success' => true,
            'member' => [
                'id' => intval($member['member_id']),
                'user_id' => !empty($member['user_id']) ? intval($member['user_id']) : null,
                'name' => $member['name'],
                'email' => $member['email'],
                'phone' => $member['phone'],
                'points' => round(floatval($member['points'] ?? 0), 2),
                'member_since' => $member['member_since']
            ],
            'summary' => [
                'points_earned' => $pointsEarned,
                'points_redeemed' => $pointsRedeemed,
                'net_points' => round(floatval($member['points'] ?? 0), 2),
                'log_count' => $logCount,
                'transaction_count' => count($transactions),
                'total_transaction_value' => round($totalTransactionValue, 2)
            ],
            'points_log' => $pointsLogs,
            'transactions' => $transactions
        ]);
        exit;
    }

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
