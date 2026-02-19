<?php
/**
 * Notification API
 * Returns alerts for staff: low stock, near-expiry, pending orders
 * GET  ?action=count         → { count: N }
 * GET  ?action=list          → { notifications: [...] }
 * POST ?action=mark_read     → { success: true }
 * POST ?action=mark_all_read → { success: true }
 */
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

// Only staff can access notifications
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$role = $_SESSION['role_name'] ?? '';
if ($role === 'customer') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'count';
$userId = intval($_SESSION['user_id']);

// Helper: check if a column exists
function colExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// Helper: check if a table exists
function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

$notifications = [];

// Persistent read state for computed notifications
$conn->query("CREATE TABLE IF NOT EXISTS notification_reads (
    read_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_key VARCHAR(191) NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_notif (user_id, notification_key),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function notifKey($type, $parts = []) {
    $raw = $type . '|' . implode('|', $parts);
    return hash('sha256', $raw);
}

function loadReadKeys($conn, $userId) {
    $keys = [];
    $stmt = $conn->prepare("SELECT notification_key FROM notification_reads WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $keys[$row['notification_key']] = true;
        }
        $stmt->close();
    }
    return $keys;
}

if ($action === 'mark_read') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $key = trim($payload['notification_key'] ?? $_POST['notification_key'] ?? $_GET['notification_key'] ?? '');
    if ($key === '') {
        echo json_encode(['success' => false, 'message' => 'Missing notification key']);
        exit;
    }
    $stmt = $conn->prepare("INSERT IGNORE INTO notification_reads (user_id, notification_key) VALUES (?, ?)");
    $stmt->bind_param('is', $userId, $key);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_all_read') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $keys = $payload['notification_keys'] ?? $_POST['notification_keys'] ?? [];
    if (!is_array($keys)) {
        $keys = [];
    }

    if (!empty($keys)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO notification_reads (user_id, notification_key) VALUES (?, ?)");
        if ($stmt) {
            foreach ($keys as $key) {
                $key = trim((string) $key);
                if ($key === '') continue;
                $stmt->bind_param('is', $userId, $key);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

try {
    $readKeys = loadReadKeys($conn, $userId);

    // 1. Low Stock Alerts (stock_quantity <= reorder_level)
    if (tableExists($conn, 'products')) {
        $hasReorder = colExists($conn, 'products', 'reorder_level');
        if ($hasReorder) {
            $sql = "SELECT product_id, name, stock_quantity, reorder_level 
                    FROM products 
                    WHERE is_active = 1 AND stock_quantity <= reorder_level 
                    ORDER BY stock_quantity ASC 
                    LIMIT 20";
        } else {
            $sql = "SELECT product_id, name, stock_quantity, 10 AS reorder_level 
                    FROM products 
                    WHERE is_active = 1 AND stock_quantity <= 10 
                    ORDER BY stock_quantity ASC 
                    LIMIT 20";
        }
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $qty = intval($row['stock_quantity']);
                $key = notifKey('low_stock', [intval($row['product_id']), $qty, intval($row['reorder_level'])]);
                if (isset($readKeys[$key])) {
                    continue;
                }
                $notifications[] = [
                    'notification_key' => $key,
                    'type' => 'low_stock',
                    'icon' => 'fa-boxes-stacked',
                    'color' => $qty <= 0 ? '#ef4444' : '#f59e0b',
                    'title' => $qty <= 0 ? 'Out of Stock' : 'Low Stock',
                    'message' => htmlspecialchars($row['name']) . " — {$qty} left (reorder: {$row['reorder_level']})",
                    'link' => 'inventory_management.php'
                ];
            }
        }
    }

    // 2. Near-Expiry Alerts (within 90 days)
    if (tableExists($conn, 'products') && colExists($conn, 'products', 'expiry_date')) {
        $sql = "SELECT product_id, name, expiry_date, stock_quantity 
                FROM products 
                WHERE is_active = 1 
                  AND expiry_date IS NOT NULL 
                  AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                  AND stock_quantity > 0
                ORDER BY expiry_date ASC 
                LIMIT 15";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $expired = strtotime($row['expiry_date']) < time();
                $key = notifKey('expiry', [intval($row['product_id']), (string) $row['expiry_date']]);
                if (isset($readKeys[$key])) {
                    continue;
                }
                $notifications[] = [
                    'notification_key' => $key,
                    'type' => 'expiry',
                    'icon' => 'fa-calendar-xmark',
                    'color' => $expired ? '#ef4444' : '#f59e0b',
                    'title' => $expired ? 'EXPIRED' : 'Expiring Soon',
                    'message' => htmlspecialchars($row['name']) . " — expires " . date('M j, Y', strtotime($row['expiry_date'])),
                    'link' => 'expiry-monitoring.php'
                ];
            }
        }
    }

    // 3. Pending Online Orders
    if (tableExists($conn, 'online_orders')) {
        $statusCol = colExists($conn, 'online_orders', 'order_status') ? 'order_status' : 
                     (colExists($conn, 'online_orders', 'status') ? 'status' : null);
        if ($statusCol) {
            $sql = "SELECT order_id, created_at 
                    FROM online_orders 
                    WHERE $statusCol = 'Pending' 
                    ORDER BY created_at DESC 
                    LIMIT 10";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $time = !empty($row['created_at']) ? date('M j g:ia', strtotime($row['created_at'])) : '';
                    $key = notifKey('pending_order', [intval($row['order_id'])]);
                    if (isset($readKeys[$key])) {
                        continue;
                    }
                    $notifications[] = [
                        'notification_key' => $key,
                        'type' => 'pending_order',
                        'icon' => 'fa-cart-shopping',
                        'color' => '#3b82f6',
                        'title' => 'Pending Order',
                        'message' => "Order #{$row['order_id']} awaiting confirmation" . ($time ? " — $time" : ''),
                        'link' => 'online_order_api.php'
                    ];
                }
            }
        }
    }

} catch (Exception $e) {
    error_log("Notification API error: " . $e->getMessage());
}

if ($action === 'count') {
    echo json_encode(['count' => count($notifications)]);
} else {
    echo json_encode(['notifications' => $notifications, 'count' => count($notifications)]);
}
