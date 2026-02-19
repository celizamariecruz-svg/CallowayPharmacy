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
    case 'mark_picked_up':
        markPickedUp($conn);
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
    if (!isset($_GET['status'])) {
        // Default behavior if status not set or empty
    }
    $statusFilter = $_GET['status'] ?? '';
    $limit = intval($_GET['limit'] ?? 50);

    // Be robust: Check if table exists first (optional, but good practice if schema is in flux)
    $chk = $conn->query("SHOW TABLES LIKE 'online_orders'");
    if ($chk->num_rows == 0) {
        echo json_encode(['success' => true, 'orders' => []]);
        return;
    }

    $sql = "
        SELECT o.*, 
               (SELECT COUNT(*) FROM online_order_items oi WHERE oi.order_id = o.order_id) as item_count
        FROM online_orders o
    ";

    $stmt = null; // Initialize variable

    if ($statusFilter && $statusFilter !== 'all') {
        $sql .= " WHERE o.status = ?";
        $sql .= " ORDER BY o.created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            // Handle prepare error
            error_log("Prepare failed: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            return;
        }
        $stmt->bind_param("si", $statusFilter, $limit);
    } else {
        // Using FIELD for custom sort order
        $sql .= " ORDER BY FIELD(o.status, 'Pending','Confirmed','Preparing','Ready','Completed','Cancelled'), o.created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
             error_log("Prepare failed: " . $conn->error);
             echo json_encode(['success' => false, 'message' => 'Database error']);
             return;
        }
        $stmt->bind_param("i", $limit);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $row['order_ref'] = 'ONL-' . str_pad($row['order_id'], 6, '0', STR_PAD_LEFT);
        if (!isset($row['item_count'])) $row['item_count'] = 0; // Fallback
        $orders[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'orders' => $orders]);
}

function getPendingCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM online_orders WHERE status IN ('Pending','Confirmed','Preparing','Ready')");
    if (!$result) {
        // Log SQL error and return 0
        // echo json_encode(['success' => false, 'message' => $conn->error]); 
        // Better yet, return 0 to avoid breaking UI
        echo json_encode(['success' => true, 'count' => 0]); 
        return;
    }
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

function markPickedUp($conn) {
    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'POST required']);
        return;
    }

    $orderId = intval($_POST['order_id'] ?? 0);
    $saleId = intval($_POST['sale_id'] ?? 0);

    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        return;
    }

    // Get current user from session
    session_start();
    $userId = $_SESSION['user_id'] ?? 0;
    $userName = $_SESSION['username'] ?? 'Unknown';

    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        return;
    }

    $conn->begin_transaction();

    try {
        // Check if order exists and is Ready
        $stmt = $conn->prepare("SELECT status FROM online_orders WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            throw new Exception('Order not found');
        }

        if ($order['status'] !== 'Ready' && $order['status'] !== 'Completed') {
            throw new Exception('Order must be in Ready status to process pickup');
        }

        // Update order with pickup info and link to POS sale
        if ($saleId > 0) {
            $stmt = $conn->prepare("
                UPDATE online_orders 
                SET picked_up_at = NOW(), 
                    picked_up_by = ?, 
                    pos_sale_id = ?,
                    status = 'Completed',
                    updated_at = NOW()
                WHERE order_id = ?
            ");
            $stmt->bind_param("iii", $userId, $saleId, $orderId);
        } else {
            $stmt = $conn->prepare("
                UPDATE online_orders 
                SET picked_up_at = NOW(), 
                    picked_up_by = ?, 
                    status = 'Completed',
                    updated_at = NOW()
                WHERE order_id = ?
            ");
            $stmt->bind_param("ii", $userId, $orderId);
        }
        
        $stmt->execute();
        $stmt->close();

        // Create notification
        $orderRef = 'ONL-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
        $title = "✅ Order #$orderRef Picked Up";
        $message = "Order picked up by customer\nProcessed by: $userName\nTime: " . date('M d, Y h:i A');
        $stmt = $conn->prepare("INSERT INTO pos_notifications (order_id, type, title, message) VALUES (?, 'pickup', ?, ?)");
        $stmt->bind_param("iss", $orderId, $title, $message);
        $stmt->execute();
        $stmt->close();

        // ===== AUTO-AWARD LOYALTY POINTS TO ONLINE CUSTOMER =====
        $loyaltyResult = awardLoyaltyForPickup($conn, $orderId);

        $conn->commit();
        
        $responseMsg = 'Order marked as picked up';
        if (!empty($loyaltyResult['awarded'])) {
            $responseMsg .= '. Customer earned ' . $loyaltyResult['points'] . ' loyalty points!';
        }
        echo json_encode(['success' => true, 'message' => $responseMsg, 'loyalty' => $loyaltyResult]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log('Mark picked up error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to mark as picked up: ' . $e->getMessage()]);
    }
}

/**
 * Auto-award loyalty points when a POS cashier processes an online order pickup.
 * Rule: ₱500 spent = 25 points earned (1 point = ₱1 discount when redeemed).
 */
function awardLoyaltyForPickup($conn, $orderId) {
    $result = ['awarded' => false, 'points' => 0, 'reason' => ''];

    try {
        // Get order details including customer info
        $stmt = $conn->prepare("SELECT customer_id, customer_name, total_amount, email FROM online_orders WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order || empty($order['customer_id'])) {
            $result['reason'] = 'No linked customer account';
            return $result;
        }

        $customerId = intval($order['customer_id']);
        $totalAmount = floatval($order['total_amount']);

        // Get user email from users table (customer_id may reference users.user_id)
        $email = $order['email'] ?? '';
        if (empty($email)) {
            $stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $email = $user['email'] ?? '';
        }

        if (empty($email)) {
            $result['reason'] = 'No email found for customer';
            return $result;
        }

        // Check loyalty_members table exists
        $chk = $conn->query("SHOW TABLES LIKE 'loyalty_members'");
        if (!$chk || $chk->num_rows === 0) {
            $result['reason'] = 'Loyalty system not set up';
            return $result;
        }

        // Find or create loyalty member
        $stmt = $conn->prepare("SELECT member_id, points FROM loyalty_members WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $memberId = null;
        if ($member) {
            $memberId = $member['member_id'];
        } else {
            // Auto-create loyalty member
            $customerName = $order['customer_name'] ?? 'Online Customer';
            $stmt = $conn->prepare("INSERT INTO loyalty_members (name, email, points, member_since) VALUES (?, ?, 0, CURDATE())");
            $stmt->bind_param("ss", $customerName, $email);
            $stmt->execute();
            $memberId = $conn->insert_id;
            $stmt->close();
        }

        // Calculate points: floor(total / 500) * 25
        $pointsEarned = floor($totalAmount / 500) * 25;

        if ($pointsEarned <= 0) {
            $result['reason'] = 'Order total below ₱500 threshold';
            return $result;
        }

        // Check if points were already awarded for this order (prevent double-award)
        $refId = 'ORDER-' . $orderId;
        $chk2 = $conn->query("SHOW TABLES LIKE 'loyalty_points_log'");
        if ($chk2 && $chk2->num_rows > 0) {
            $stmt = $conn->prepare("SELECT 1 FROM loyalty_points_log WHERE reference_id = ? AND transaction_type = 'EARN' LIMIT 1");
            $stmt->bind_param("s", $refId);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                $result['reason'] = 'Points already awarded for this order';
                return $result;
            }
        }

        // Award points
        $stmt = $conn->prepare("UPDATE loyalty_members SET points = points + ? WHERE member_id = ?");
        $stmt->bind_param("ii", $pointsEarned, $memberId);
        $stmt->execute();
        $stmt->close();

        // Log the earning
        if ($chk2 && $chk2->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO loyalty_points_log (member_id, points, transaction_type, reference_id) VALUES (?, ?, 'EARN', ?)");
            $stmt->bind_param("iis", $memberId, $pointsEarned, $refId);
            $stmt->execute();
            $stmt->close();
        }

        $result['awarded'] = true;
        $result['points'] = $pointsEarned;
        $result['reason'] = 'Points awarded successfully';

    } catch (Exception $e) {
        error_log('Loyalty award error for order ' . $orderId . ': ' . $e->getMessage());
        $result['reason'] = 'Error: ' . $e->getMessage();
    }

    return $result;
}

ob_end_flush();
?>
