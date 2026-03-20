<?php
/**
 * Online Orders API
 * Tracks online orders by reference for customer-facing lookups
 */
require_once 'db_connection.php';
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'track':
        $ref = trim($_GET['ref'] ?? '');
        if (empty($ref)) {
            echo json_encode(['success' => false, 'message' => 'Reference code required']);
            exit;
        }

        // Check online_orders table
        $stmt = $conn->prepare("
            SELECT order_id, customer_name, status, total_amount, created_at
            FROM online_orders
            WHERE order_id = ? OR CAST(order_id AS CHAR) = ?
            LIMIT 1
        ");
        $stmt->bind_param("is", $orderId, $ref);
        $orderId = intval($ref);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();

        if (!$order) {
            // Also check sales table by reference
            $stmt2 = $conn->prepare("
                SELECT sale_id, sale_reference, total, status, created_at,
                       (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.sale_id) as item_count
                FROM sales s
                WHERE sale_reference = ? OR sale_reference LIKE ?
                LIMIT 1
            ");
            $like = '%' . $ref . '%';
            $stmt2->bind_param("ss", $ref, $like);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $sale = $result2->fetch_assoc();

            if ($sale) {
                echo json_encode([
                    'success' => true,
                    'order' => [
                        'reference' => $sale['sale_reference'],
                        'status' => ucfirst($sale['status'] ?? 'completed'),
                        'total' => $sale['total'],
                        'date' => $sale['created_at'],
                        'item_count' => $sale['item_count']
                    ]
                ]);
                exit;
            }

            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        // Check if online_order_items table exists for item count
        $itemCount = 0;
        $checkTable = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'online_order_items' LIMIT 1");
        if ($checkTable && $checkTable->num_rows > 0) {
            $cntStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM online_order_items WHERE order_id = ?");
            $cntStmt->bind_param("i", $order['order_id']);
            $cntStmt->execute();
            $cntRow = $cntStmt->get_result()->fetch_assoc();
            $itemCount = $cntRow['cnt'] ?? 0;
        }

        echo json_encode([
            'success' => true,
            'order' => [
                'reference' => 'ORD-' . str_pad($order['order_id'], 6, '0', STR_PAD_LEFT),
                'status' => ucfirst($order['status'] ?? 'Pending'),
                'total' => $order['total_amount'],
                'date' => $order['created_at'],
                'item_count' => $itemCount
            ]
        ]);
        break;

    case 'my_orders':
        // Returns all orders for the currently logged-in customer
        if (session_status() === PHP_SESSION_NONE) session_start();
        $userId = $_SESSION['user_id'] ?? 0;
        $roleName = $_SESSION['role_name'] ?? 'customer';
        $isStaff = in_array(strtolower($roleName), ['admin', 'cashier', 'inventory_manager', 'staff']);
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please log in to view your orders']);
            exit;
        }

        $orders = [];

        // Check column availability once for both branches
        $hasCustId = false;
        $hasNotes = false;
        $hasPayment = false;
        try {
            $chk = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='online_orders' AND COLUMN_NAME='customer_id' LIMIT 1");
            $hasCustId = ($chk && $chk->num_rows > 0);
            $chk2 = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='online_orders' AND COLUMN_NAME='notes' LIMIT 1");
            $hasNotes = ($chk2 && $chk2->num_rows > 0);
            $chk3 = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='online_orders' AND COLUMN_NAME='payment_method' LIMIT 1");
            $hasPayment = ($chk3 && $chk3->num_rows > 0);
        } catch (Exception $e) {}
        
        // Staff/admin can see all orders; customers see only their own
        if ($isStaff) {
            $cols = "o.order_id, o.customer_name, o.status, o.total_amount, o.created_at, o.updated_at";
            if ($hasCustId) $cols .= ", o.customer_id";
            if ($hasNotes) $cols .= ", o.notes";
            if ($hasPayment) $cols .= ", o.payment_method";
            
            $stmt = $conn->prepare("SELECT $cols FROM online_orders o ORDER BY o.created_at DESC LIMIT 100");
            $stmt->execute();
        } else {
            // Customer query - need customer_id column to filter
            if (!$hasCustId) {
                // customer_id column doesn't exist - try alternative lookup
                echo json_encode(['success' => true, 'orders' => [], 'is_staff' => false]);
                exit;
            }
            $custCols = "o.order_id, o.customer_name, o.status, o.total_amount, o.created_at, o.updated_at";
            if ($hasPayment) $custCols .= ", o.payment_method";
            if ($hasNotes) $custCols .= ", o.notes";
            $stmt = $conn->prepare("SELECT $custCols FROM online_orders o WHERE o.customer_id = ? ORDER BY o.created_at DESC LIMIT 50");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        }
        $result = $stmt->get_result();

        // Get user email for loyalty lookup
        $userEmail = '';
        $emailStmt = $conn->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
        $emailStmt->bind_param("i", $userId);
        $emailStmt->execute();
        $emailRow = $emailStmt->get_result()->fetch_assoc();
        if ($emailRow) $userEmail = $emailRow['email'];
        $emailStmt->close();
        
        while ($row = $result->fetch_assoc()) {
            $orderId = $row['order_id'];
            $row['order_ref'] = 'ONL-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
            
            // Fetch items for this order
            $itemStmt = $conn->prepare("
                SELECT product_name, quantity, price, subtotal 
                FROM online_order_items 
                WHERE order_id = ?
            ");
            $itemStmt->bind_param("i", $orderId);
            $itemStmt->execute();
            $itemResult = $itemStmt->get_result();
            $items = [];
            while ($itemRow = $itemResult->fetch_assoc()) {
                $items[] = $itemRow;
            }
            $itemStmt->close();
            
            $row['items'] = $items;
            $row['item_count'] = count($items);

            // Look up loyalty points earned for this order
            $refId = 'ORDER-' . $orderId;
            $row['points_earned'] = 0;
            $row['points_redeemed'] = 0;
            try {
                $logStmt = $conn->prepare("SELECT points, transaction_type FROM loyalty_points_log WHERE reference_id = ?");
                $logStmt->bind_param("s", $refId);
                $logStmt->execute();
                $logResult = $logStmt->get_result();
                while ($logRow = $logResult->fetch_assoc()) {
                    if ($logRow['transaction_type'] === 'EARN') {
                        $row['points_earned'] = abs(floatval($logRow['points']));
                    } elseif ($logRow['transaction_type'] === 'REDEEM') {
                        $row['points_redeemed'] = abs(floatval($logRow['points']));
                    }
                }
                $logStmt->close();
            } catch (Exception $e) {
                // Loyalty table may not exist
            }

            // Admin extra details
            if ($isStaff) {
                try {
                    $custStmt = $conn->prepare("SELECT email, full_name FROM users WHERE user_id = ? LIMIT 1");
                    $custUserId = intval($row['customer_id'] ?? 0);
                    $custStmt->bind_param("i", $custUserId);
                    $custStmt->execute();
                    $custRow = $custStmt->get_result()->fetch_assoc();
                    $custStmt->close();
                    if ($custRow) {
                        $row['customer_email'] = $custRow['email'];
                        $row['customer_full_name'] = $custRow['full_name'];
                    }
                } catch (Exception $e) {}
            }

            $orders[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'orders' => $orders, 'is_staff' => $isStaff]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
