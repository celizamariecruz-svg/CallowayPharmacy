<?php
/**
 * Online Orders API
 * Tracks online orders by reference for customer-facing lookups
 */
require_once 'db_connection.php';

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
        session_start();
        $userId = $_SESSION['user_id'] ?? 0;
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please log in to view your orders']);
            exit;
        }

        $orders = [];
        
        // Fetch orders placed by this customer
        $stmt = $conn->prepare("
            SELECT o.order_id, o.customer_name, o.status, o.total_amount, 
                   o.payment_method, o.notes, o.created_at, o.updated_at
            FROM online_orders o
            WHERE o.customer_id = ?
            ORDER BY o.created_at DESC
            LIMIT 50
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
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
            $orders[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'orders' => $orders]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
