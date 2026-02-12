<?php
/**
 * Online Order Notification API for POS
 * Actions: get_notifications, mark_read, get_order_details, get_online_orders, etc.
 */

// Prevent any stray output from breaking JSON
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'db_connection.php';
require_once 'Auth.php';

$auth = new Auth($conn);

// For API calls, check auth but return JSON error instead of redirect
if (!$auth->isLoggedIn()) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    ob_end_flush();
    exit;
}

// Clean any stray output from includes
ob_clean();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Read-only actions only need login; state-changing actions need pos.access permission
$stateChangingActions = ['update_order_status', 'mark_read', 'mark_all_read'];
if (in_array($action, $stateChangingActions) && !$auth->hasPermission('pos.access')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied: POS access required']);
    ob_end_flush();
    exit;
}

switch ($action) {
    case 'get_notifications':
        getNotifications($conn);
        break;
    case 'get_unread_count':
        getUnreadCount($conn);
        break;
    case 'mark_read':
        markRead($conn);
        break;
    case 'mark_all_read':
        markAllRead($conn);
        break;
    case 'get_order_details':
        getOrderDetails($conn);
        break;
    case 'get_online_orders':
        getOnlineOrders($conn);
        break;
    case 'get_pending_count':
        getPendingCount($conn);
        break;
    case 'update_order_status':
        updateOrderStatus($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getNotifications($conn) {
    $limit = intval($_GET['limit'] ?? 20);
    $stmt = $conn->prepare("
        SELECT n.*, o.customer_name, o.total_amount, o.payment_method, o.status, o.created_at as order_time
        FROM pos_notifications n
        LEFT JOIN online_orders o ON n.order_id = o.order_id
        ORDER BY n.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'notifications' => $notifications]);
}

function getUnreadCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM pos_notifications WHERE is_read = 0");
    $row = $result->fetch_assoc();
    echo json_encode(['success' => true, 'count' => intval($row['count'])]);
}

function markRead($conn) {
    $id = intval($_POST['notification_id'] ?? $_GET['notification_id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE pos_notifications SET is_read = 1 WHERE notification_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['success' => true]);
}

function markAllRead($conn) {
    $conn->query("UPDATE pos_notifications SET is_read = 1 WHERE is_read = 0");
    echo json_encode(['success' => true]);
}

function getOrderDetails($conn) {
    $orderId = intval($_GET['order_id'] ?? 0);
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        return;
    }

    // Get order
    $stmt = $conn->prepare("SELECT * FROM online_orders WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }

    // Get items
    $stmt = $conn->prepare("SELECT * FROM online_order_items WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    $order['items'] = $items;
    $order['order_ref'] = 'ONL-' . str_pad($order['order_id'], 6, '0', STR_PAD_LEFT);

    echo json_encode(['success' => true, 'order' => $order]);
}

function getOnlineOrders($conn) {
    $statusFilter = $_GET['status'] ?? '';
    $limit = intval($_GET['limit'] ?? 50);

    $sql = "
        SELECT o.*, 
               (SELECT COUNT(*) FROM online_order_items oi WHERE oi.order_id = o.order_id) as item_count
        FROM online_orders o
    ";

    if ($statusFilter && $statusFilter !== 'all') {
        $sql .= " WHERE o.status = ?";
        $sql .= " ORDER BY o.created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $statusFilter, $limit);
    } else {
        $sql .= " ORDER BY FIELD(o.status, 'Pending','Confirmed','Preparing','Ready','Completed','Cancelled'), o.created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $row['order_ref'] = 'ONL-' . str_pad($row['order_id'], 6, '0', STR_PAD_LEFT);
        $orders[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'orders' => $orders]);
}

function getPendingCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM online_orders WHERE status IN ('Pending','Confirmed','Preparing','Ready')");
    $row = $result->fetch_assoc();
    echo json_encode(['success' => true, 'count' => intval($row['count'])]);
}

function updateOrderStatus($conn) {
    // Only accept POST — no GET fallback (prevents CSRF via URL/image tags)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'POST required']);
        return;
    }

    $orderId  = intval($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    $validStatuses = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Completed', 'Cancelled'];

    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        return;
    }
    if (!in_array($newStatus, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }

    // Fetch the current order to validate transitions
    $stmt = $conn->prepare("SELECT * FROM online_orders WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }

    $currentStatus = $order['status'];

    // Enforce valid status transitions
    $allowedTransitions = [
        'Pending'   => ['Confirmed', 'Cancelled'],
        'Confirmed' => ['Preparing', 'Cancelled'],
        'Preparing' => ['Ready', 'Cancelled'],
        'Ready'     => ['Completed', 'Cancelled'],
        'Completed' => [],     // terminal
        'Cancelled' => [],     // terminal
    ];

    if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus])) {
        echo json_encode(['success' => false, 'message' => "Cannot change status from '$currentStatus' to '$newStatus'"]);
        return;
    }

    // Wrap everything in a transaction
    $conn->begin_transaction();

    try {
        // 1. Update order status
        $stmt = $conn->prepare("UPDATE online_orders SET status = ?, updated_at = NOW() WHERE order_id = ?");
        $stmt->bind_param("si", $newStatus, $orderId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            throw new Exception('Failed to update order status');
        }

        // 2. Fetch order items (needed for Completed and Cancelled)
        $orderItems = [];
        if ($newStatus === 'Completed' || $newStatus === 'Cancelled') {
            $stmt = $conn->prepare("SELECT * FROM online_order_items WHERE order_id = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $orderItems[] = $row;
            }
            $stmt->close();
        }

        // 3. If COMPLETED → create a sale record so it shows in POS & reports
        if ($newStatus === 'Completed') {
            $orderRef = 'ONL-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
            $receiptNo = $orderRef;
            $totalAmount = floatval($order['total_amount']);
            $paymentMethod = $order['payment_method'] ?? 'Cash on Pickup';
            $cashier = 'Online Order';

            // Insert sale header
            $stmt = $conn->prepare("
                INSERT INTO sales (sale_reference, total, payment_method, paid_amount, change_amount, cashier, created_at)
                VALUES (?, ?, ?, ?, 0.00, ?, NOW())
            ");
            $stmt->bind_param("sdsds", $receiptNo, $totalAmount, $paymentMethod, $totalAmount, $cashier);
            $stmt->execute();
            $saleId = $conn->insert_id;
            $stmt->close();

            // Insert sale items (stock already deducted at order time by order_handler.php)
            $itemStmt = $conn->prepare("
                INSERT INTO sale_items (sale_id, product_id, name, unit_price, quantity, line_total)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($orderItems as $item) {
                $productId = intval($item['product_id']);
                $productName = $item['product_name'] ?? ('Product #' . $productId);
                $price = floatval($item['price']);
                $qty = intval($item['quantity']);
                $lineTotal = $price * $qty;

                $itemStmt->bind_param("iisdid", $saleId, $productId, $productName, $price, $qty, $lineTotal);
                $itemStmt->execute();
            }
            $itemStmt->close();
        }

        // 4. If CANCELLED → restore stock that was deducted at order time
        if ($newStatus === 'Cancelled') {
            $stockStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
            foreach ($orderItems as $item) {
                $qty = intval($item['quantity']);
                $productId = intval($item['product_id']);
                $stockStmt->bind_param("ii", $qty, $productId);
                $stockStmt->execute();
            }
            $stockStmt->close();
        }

        // 5. Add a notification about the status change
        $orderRef = 'ONL-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
        $title = "📋 Order #$orderRef updated to $newStatus";
        $message = "Order #$orderRef status changed to: $newStatus\nUpdated: " . date('M d, Y h:i A');
        $stmt = $conn->prepare("INSERT INTO pos_notifications (order_id, type, title, message) VALUES (?, 'status_update', ?, ?)");
        $stmt->bind_param("iss", $orderId, $title, $message);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Order status updated to $newStatus"]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log('Order status update error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update order: ' . $e->getMessage()]);
    }
}

ob_end_flush();
?>
