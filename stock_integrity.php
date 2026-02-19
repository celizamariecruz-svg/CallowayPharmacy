<?php
/**
 * Stock Deduction Maintenance & Safety Functions
 * 
 * Handles edge cases and ensures stock integrity.
 * Current architecture: Stock is deducted at order PLACEMENT (Pending status)
 * and restored only on CANCELLATION.
 * 
 * This script provides safeguards:
 * 1. Auto-cancel abandoned pending orders (>24h without confirmation)
 * 2. Verify stock consistency and fix anomalies
 * 3. Generate stock deduction audit reports
 */

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/ActivityLogger.php';

class StockIntegrityManager
{
    private $conn;
    private $logger;
    
    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
        $this->logger = new ActivityLogger($db_connection);
    }
    
    /**
     * Auto-cancel pending orders older than specified hours without confirmation
     * This restores their held stock automatically.
     * 
     * @param int $hoursThreshold Hours to wait before auto-canceling (default: 24)
     * @return array Results of cancellation operation
     */
    public function autoCancelAbandonedOrders($hoursThreshold = 24)
    {
        $results = [
            'success' => false,
            'cancelled_count' => 0,
            'stock_restored' => 0,
            'errors' => []
        ];
        
        try {
            // Find pending orders older than threshold
            $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hoursThreshold} hours"));
            
            $stmt = $this->conn->prepare("
                SELECT o.order_id, o.customer_name, o.total
                FROM online_orders o
                WHERE o.status = 'Pending' 
                AND o.created_at < ?
                LIMIT 100
            ");
            
            if (!$stmt) {
                $results['errors'][] = "Prepare failed: " . $this->conn->error;
                return $results;
            }
            
            $stmt->bind_param("s", $cutoffTime);
            $stmt->execute();
            $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Process each abandoned order
            foreach ($orders as $order) {
                if ($this->cancelOrderAndRestoreStock($order['order_id'])) {
                    $results['cancelled_count']++;
                    
                    // Count items in cancelled order to track stock restoration
                    $itemStmt = $this->conn->prepare("
                        SELECT COUNT(*) as item_count 
                        FROM online_order_items 
                        WHERE order_id = ?
                    ");
                    $itemStmt->bind_param("i", $order['order_id']);
                    $itemStmt->execute();
                    $itemCount = $itemStmt->get_result()->fetch_assoc()['item_count'];
                    $itemStmt->close();
                    
                    $results['stock_restored'] += $itemCount;
                    
                    // Log this automated action
                    $this->logger->log(
                        0, // system user
                        'auto_cancel_abandoned_order',
                        'OrderManagement',
                        "Auto-cancelled abandoned pending order #{$order['order_id']} (placed {$order['created_at']}), stock restored"
                    );
                }
            }
            
            $results['success'] = true;
            return $results;
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            return $results;
        }
    }
    
    /**
     * Cancel a specific order and restore its stock
     * 
     * @param int $orderId Order ID to cancel
     * @return bool True on success
     */
    private function cancelOrderAndRestoreStock($orderId)
    {
        try {
            $this->conn->begin_transaction();
            
            // Get order items with quantities
            $stmt = $this->conn->prepare("
                SELECT product_id, quantity 
                FROM online_order_items 
                WHERE order_id = ?
            ");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Restore stock for each item
            foreach ($items as $item) {
                $restoreStmt = $this->conn->prepare("
                    UPDATE products 
                    SET stock_quantity = stock_quantity + ? 
                    WHERE product_id = ?
                ");
                $restoreStmt->bind_param("ii", $item['quantity'], $item['product_id']);
                $restoreStmt->execute();
                $restoreStmt->close();
            }
            
            // Update order status to cancelled
            $cancelStatus = 'Cancelled';
            $cancelStmt = $this->conn->prepare("
                UPDATE online_orders 
                SET status = ?, cancelled_at = NOW()
                WHERE order_id = ?
            ");
            $cancelStmt->bind_param("si", $cancelStatus, $orderId);
            $cancelStmt->execute();
            $cancelStmt->close();
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Failed to cancel order $orderId: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify stock quantity consistency
     * Checks for negative stock (shouldn't exist with proper guards)
     * 
     * @return array Status of stock verification
     */
    public function verifyStockConsistency()
    {
        $results = [
            'valid' => true,
            'negative_stock_products' => [],
            'discrepancies' => []
        ];
        
        try {
            // Check for negative stock
            $stmt = $this->conn->prepare("
                SELECT product_id, name, stock_quantity 
                FROM products 
                WHERE stock_quantity < 0
            ");
            
            if (!$stmt) {
                $results['discrepancies'][] = "Query prepare failed: " . $this->conn->error;
                return $results;
            }
            
            $stmt->execute();
            $negativeStocks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            if (!empty($negativeStocks)) {
                $results['valid'] = false;
                $results['negative_stock_products'] = $negativeStocks;
            }
            
            return $results;
            
        } catch (Exception $e) {
            $results['discrepancies'][] = $e->getMessage();
            return $results;
        }
    }
    
    /**
     * Generate audit report of stock deductions by order
     * Useful for reconciliation and auditing
     * 
     * @param string $startDate Y-m-d format
     * @param string $endDate Y-m-d format
     * @return array Deduction audit data
     */
    public function getStockDeductionAudit($startDate = null, $endDate = null)
    {
        if (!$startDate) {
            $startDate = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$endDate) {
            $endDate = date('Y-m-d');
        }
        
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    o.order_id,
                    o.status,
                    o.customer_name,
                    o.created_at,
                    COALESCE(o.cancelled_at, 'N/A') as cancelled_at,
                    GROUP_CONCAT(CONCAT(p.name, ' x', oi.quantity) SEPARATOR ', ') as items,
                    SUM(oi.quantity) as total_items_deducted
                FROM online_orders o
                JOIN online_order_items oi ON o.order_id = oi.order_id
                JOIN products p ON oi.product_id = p.product_id
                WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?
                GROUP BY o.order_id
                ORDER BY o.created_at DESC
            ");
            
            if (!$stmt) {
                return ['error' => "Query prepare failed: " . $this->conn->error];
            }
            
            $stmt->bind_param("ss", $startDate, $endDate);
            $stmt->execute();
            $audit = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            return [
                'success' => true,
                'period' => "$startDate to $endDate",
                'records' => $audit,
                'total_deductions' => count($audit)
            ];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

// CLI execution support
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $action = $argv[1];
    $manager = new StockIntegrityManager($conn);
    
    switch ($action) {
        case 'auto-cancel':
            $hours = $argv[2] ?? 24;
            $result = $manager->autoCancelAbandonedOrders($hours);
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'verify':
            $result = $manager->verifyStockConsistency();
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'audit':
            $start = $argv[2] ?? null;
            $end = $argv[3] ?? null;
            $result = $manager->getStockDeductionAudit($start, $end);
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            break;
            
        default:
            echo "Unknown action: $action\n";
            echo "Usage: php stock_integrity.php [auto-cancel|verify|audit] [args]\n";
    }
}
?>
