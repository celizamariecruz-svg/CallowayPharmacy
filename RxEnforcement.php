<?php
/**
 * RxEnforcement Class
 * Handles prescription medication verification and pharmacist approval
 */

class RxEnforcement {
    private $conn;
    private $hasPrescriptionColumn = null;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }

    private function hasPrescriptionColumn() {
        if ($this->hasPrescriptionColumn !== null) {
            return $this->hasPrescriptionColumn;
        }

                $stmt = $this->conn->prepare(" 
                        SELECT 1
                        FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = 'products'
                            AND COLUMN_NAME IN ('requires_prescription', 'is_prescription')
                        LIMIT 1
                ");
        $stmt->execute();
        $result = $stmt->get_result();
        $this->hasPrescriptionColumn = ($result && $result->num_rows > 0);
        $stmt->close();

        return $this->hasPrescriptionColumn;
    }
    
    /**
     * Check if a product is a prescription medication
     */
    public function isRxProduct($product_id) {
        if (!$this->hasPrescriptionColumn()) {
            return false;
        }

        $stmt = $this->conn->prepare("SELECT is_prescription FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return (bool)$row['is_prescription'];
        }
        
        return false;
    }
    
    /**
     * Check if an order contains any prescription medications
     */
    public function orderContainsRxProducts($order_id) {
        if (!$this->hasPrescriptionColumn()) {
            return false;
        }

        $sql = "
            SELECT COUNT(*) as rx_count
            FROM online_order_items oi
            INNER JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = ? AND p.is_prescription = 1
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['rx_count'] > 0;
    }
    
    /**
     * Check if cart items contain prescription medications
     * @param array $items - Array of items with product_id or id
     * @return array - ['has_rx' => bool, 'rx_products' => array]
     */
    public function checkCartForRxProducts($items) {
        if (empty($items)) {
            return ['has_rx' => false, 'rx_products' => []];
        }

        if (!$this->hasPrescriptionColumn()) {
            return ['has_rx' => false, 'rx_products' => []];
        }
        
        $product_ids = array_map(function($item) {
            return (int)($item['product_id'] ?? $item['id'] ?? 0);
        }, $items);
        
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        
        $sql = "
            SELECT product_id, name, is_prescription 
            FROM products 
            WHERE product_id IN ($placeholders) AND is_prescription = 1
        ";
        
        $stmt = $this->conn->prepare($sql);
        $types = str_repeat('i', count($product_ids));
        $stmt->bind_param($types, ...$product_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rx_products = [];
        while ($row = $result->fetch_assoc()) {
            $rx_products[] = $row;
        }
        
        return [
            'has_rx' => count($rx_products) > 0,
            'rx_products' => $rx_products
        ];
    }
    
    /**
     * Flag an order as requiring Rx approval
     */
    public function flagOrderForRxApproval($order_id) {
        $stmt = $this->conn->prepare("
            UPDATE online_orders 
            SET requires_rx_approval = 1 
            WHERE order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        return $stmt->execute();
    }
    
    /**
     * Check if order needs pharmacist approval before pickup
     */
    public function requiresApproval($order_id) {
        $stmt = $this->conn->prepare("
            SELECT requires_rx_approval, pharmacist_approved_by, pharmacist_approved_at
            FROM online_orders
            WHERE order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return [
                'requires' => (bool)$row['requires_rx_approval'],
                'approved' => !is_null($row['pharmacist_approved_by']),
                'approved_at' => $row['pharmacist_approved_at']
            ];
        }
        
        return ['requires' => false, 'approved' => true, 'approved_at' => null];
    }
    
    /**
     * Approve an Rx order (pharmacist action)
     */
    public function approveRxOrder($order_id, $pharmacist_id, $notes = null) {
        if (!$this->hasPrescriptionColumn()) {
            return ['success' => false, 'message' => 'Prescription flag is not configured in products table'];
        }

        $this->conn->begin_transaction();
        
        try {
            // Update order with approval
            $stmt = $this->conn->prepare("
                UPDATE online_orders 
                SET pharmacist_approved_by = ?,
                    pharmacist_approved_at = NOW(),
                    rx_notes = ?
                WHERE order_id = ?
            ");
            $stmt->bind_param("isi", $pharmacist_id, $notes, $order_id);
            $stmt->execute();
            
            // Get all Rx products in this order
            $sql = "
                SELECT oi.product_id 
                FROM online_order_items oi
                INNER JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = ? AND p.is_prescription = 1
            ";
            $stmt2 = $this->conn->prepare($sql);
            $stmt2->bind_param("i", $order_id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            
            // Log approval for each Rx product
            $log_stmt = $this->conn->prepare("
                INSERT INTO rx_approval_log (order_id, product_id, pharmacist_id, action, notes)
                VALUES (?, ?, ?, 'Approved', ?)
            ");
            
            while ($row = $result->fetch_assoc()) {
                $log_stmt->bind_param("iiis", $order_id, $row['product_id'], $pharmacist_id, $notes);
                $log_stmt->execute();
            }
            
            $this->conn->commit();
            return ['success' => true, 'message' => 'Prescription order approved'];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Approval failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Reject an Rx order (pharmacist action)
     */
    public function rejectRxOrder($order_id, $pharmacist_id, $reason) {
        if (!$this->hasPrescriptionColumn()) {
            return ['success' => false, 'message' => 'Prescription flag is not configured in products table'];
        }

        $this->conn->begin_transaction();
        
        try {
            // Update order status to Cancelled
            $stmt = $this->conn->prepare("
                UPDATE online_orders 
                SET status = 'Cancelled',
                    rx_notes = ?
                WHERE order_id = ?
            ");
            $stmt->bind_param("si", $reason, $order_id);
            $stmt->execute();
            
            // Get all Rx products
            $sql = "
                SELECT oi.product_id 
                FROM online_order_items oi
                INNER JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = ? AND p.is_prescription = 1
            ";
            $stmt2 = $this->conn->prepare($sql);
            $stmt2->bind_param("i", $order_id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            
            // Log rejection
            $log_stmt = $this->conn->prepare("
                INSERT INTO rx_approval_log (order_id, product_id, pharmacist_id, action, notes)
                VALUES (?, ?, ?, 'Rejected', ?)
            ");
            
            while ($row = $result->fetch_assoc()) {
                $log_stmt->bind_param("iiis", $order_id, $row['product_id'], $pharmacist_id, $reason);
                $log_stmt->execute();
            }
            
            $this->conn->commit();
            return ['success' => true, 'message' => 'Prescription order rejected and cancelled'];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Rejection failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all orders pending Rx approval
     */
    public function getPendingRxApprovals() {
        if (!$this->hasPrescriptionColumn()) {
            return [];
        }

        $sql = "
            SELECT 
                o.order_id,
                o.customer_name,
                o.contact_number,
                o.status,
                o.total_amount,
                o.created_at,
                COUNT(oi.item_id) as item_count,
                GROUP_CONCAT(p.name SEPARATOR ', ') as rx_products
            FROM online_orders o
            INNER JOIN online_order_items oi ON o.order_id = oi.order_id
            INNER JOIN products p ON oi.product_id = p.product_id
            WHERE o.requires_rx_approval = 1 
              AND o.pharmacist_approved_by IS NULL
              AND o.status NOT IN ('Cancelled', 'Completed')
              AND p.is_prescription = 1
            GROUP BY o.order_id
            ORDER BY o.created_at ASC
        ";
        
        $result = $this->conn->query($sql);
        $orders = [];
        
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        return $orders;
    }
    
    /**
     * Check if user is a pharmacist
     */
    public function isPharmacist($user_id) {
        $stmt = $this->conn->prepare("
            SELECT r.role_name 
            FROM users u 
            INNER JOIN roles r ON u.role_id = r.role_id
            WHERE u.user_id = ? AND r.role_name IN ('Pharmacist', 'Admin', 'Manager')
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    /**
     * Get customer warning message for Rx products
     */
    public function getRxCustomerWarning() {
        return [
            'title' => '⚕️ Prescription Medication Notice',
            'message' => 'Your order contains prescription medication. Please have your valid prescription ready when picking up your order. A licensed pharmacist will need to verify your prescription before releasing these items.',
            'requirements' => [
                '✓ Valid prescription from a licensed physician',
                '✓ Government-issued ID matching prescription name',
                '✓ Original prescription (not photocopy)'
            ]
        ];
    }
}
?>
