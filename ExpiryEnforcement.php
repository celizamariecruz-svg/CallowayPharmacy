<?php
/**
 * ExpiryEnforcement Class
 * Handles expiry date checking and FIFO (First-In-First-Out) enforcement
 */

class ExpiryEnforcement {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Check if a product is expired
     */
    public function isExpired($product_id) {
        $stmt = $this->conn->prepare("
            SELECT expiry_date 
            FROM products 
            WHERE product_id = ? AND expiry_date IS NOT NULL
        ");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $expiry = strtotime($row['expiry_date']);
            $today = strtotime(date('Y-m-d'));
            
            return $expiry < $today;
        }
        
        return false;
    }
    
    /**
     * Check if product will expire soon (within days threshold)
     */
    public function isExpiringSoon($product_id, $days_threshold = 30) {
        $stmt = $this->conn->prepare("
            SELECT expiry_date,
                   DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
            FROM products 
            WHERE product_id = ? AND expiry_date IS NOT NULL
        ");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $days = (int)$row['days_until_expiry'];
            return $days > 0 && $days <= $days_threshold;
        }
        
        return false;
    }
    
    /**
     * Block sale if product is expired
     * @return array ['can_sell' => bool, 'message' => string, 'expiry_date' => string]
     */
    public function canSellProduct($product_id) {
        $stmt = $this->conn->prepare("
            SELECT name, expiry_date, stock_quantity,
                   DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
            FROM products 
            WHERE product_id = ?
        ");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $expiry_date = $row['expiry_date'];
            
            // No expiry date = can sell
            if (empty($expiry_date)) {
                return [
                    'can_sell' => true,
                    'message' => 'No expiry date set',
                    'expiry_date' => null,
                    'days_until_expiry' => null
                ];
            }
            
            $days = (int)$row['days_until_expiry'];
            
            // EXPIRED: BLOCK SALE
            if ($days < 0) {
                return [
                    'can_sell' => false,
                    'message' => 'EXPIRED: Cannot sell expired product "' . $row['name'] . '" (expired ' . abs($days) . ' days ago)',
                    'expiry_date' => $expiry_date,
                    'days_until_expiry' => $days,
                    'product_name' => $row['name']
                ];
            }
            
            // EXPIRES TODAY: WARN BUT ALLOW (optional: can block if strict)
            if ($days == 0) {
                return [
                    'can_sell' => true,
                    'message' => 'WARNING: Product "' . $row['name'] . '" expires TODAY. Verify with pharmacist.',
                    'expiry_date' => $expiry_date,
                    'days_until_expiry' => 0,
                    'product_name' => $row['name'],
                    'warning' => true
                ];
            }
            
            // EXPIRING SOON (within 30 days): WARN
            if ($days <= 30) {
                return [
                    'can_sell' => true,
                    'message' => 'NOTICE: Product "' . $row['name'] . '" expires in ' . $days . ' days',
                    'expiry_date' => $expiry_date,
                    'days_until_expiry' => $days,
                    'product_name' => $row['name'],
                    'warning' => true
                ];
            }
            
            // OK TO SELL
            return [
                'can_sell' => true,
                'message' => 'OK',
                'expiry_date' => $expiry_date,
                'days_until_expiry' => $days
            ];
        }
        
        return [
            'can_sell' => false,
            'message' => 'Product not found',
            'expiry_date' => null
        ];
    }
    
    /**
     * Validate entire cart for expired products
     * @param array $items - Array of cart items with product_id or id and quantity
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
     */
    public function validateCart($items) {
        $errors = [];
        $warnings = [];
        
        foreach ($items as $item) {
            $product_id = (int)($item['product_id'] ?? $item['id'] ?? 0);
            $check = $this->canSellProduct($product_id);
            
            if (!$check['can_sell']) {
                $errors[] = $check['message'];
            } elseif (isset($check['warning']) && $check['warning']) {
                $warnings[] = $check['message'];
            }
        }
        
        return [
            'valid' => count($errors) == 0,
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Get oldest batch of a product (FIFO logic)
     * If product has multiple batches with different expiry dates,
     * return the one expiring soonest
     */
    public function getOldestBatch($product_id) {
        $sql = "
            SELECT product_id, batch_number, expiry_date, stock_quantity
            FROM products
            WHERE product_id = ? 
              AND expiry_date IS NOT NULL
              AND stock_quantity > 0
              AND is_active = 1
            ORDER BY expiry_date ASC, batch_number ASC
            LIMIT 1
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row;
        }
        
        return null;
    }
    
    /**
     * Get all expired products
     */
    public function getExpiredProducts() {
        $sql = "
            SELECT 
                product_id,
                name,
                batch_number,
                expiry_date,
                stock_quantity,
                DATEDIFF(CURDATE(), expiry_date) as days_expired
            FROM products
            WHERE expiry_date < CURDATE()
              AND is_active = 1
              AND stock_quantity > 0
            ORDER BY expiry_date ASC
        ";
        
        $result = $this->conn->query($sql);
        $expired = [];
        
        while ($row = $result->fetch_assoc()) {
            $expired[] = $row;
        }
        
        return $expired;
    }
    
    /**
     * Get products expiring within X days
     */
    public function getExpiringProducts($days_threshold = 30) {
        $sql = "
            SELECT 
                product_id,
                name,
                batch_number,
                expiry_date,
                stock_quantity,
                DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
            FROM products
            WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
              AND is_active = 1
              AND stock_quantity > 0
            ORDER BY expiry_date ASC
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $days_threshold);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $expiring = [];
        while ($row = $result->fetch_assoc()) {
            $expiring[] = $row;
        }
        
        return $expiring;
    }
    
    /**
     * Mark expired products as inactive
     * @return array - Count of products deactivated
     */
    public function deactivateExpiredProducts() {
        $sql = "
            UPDATE products 
            SET is_active = 0
            WHERE expiry_date < CURDATE()
              AND is_active = 1
              AND stock_quantity > 0
        ";
        
        $result = $this->conn->query($sql);
        
        return [
            'success' => true,
            'count' => $this->conn->affected_rows,
            'message' => 'Deactivated ' . $this->conn->affected_rows . ' expired product(s)'
        ];
    }
    
    /**
     * Generate expiry report
     */
    public function getExpiryReport() {
        $sql = "
            SELECT 
                'Expired' as status,
                COUNT(*) as count,
                SUM(stock_quantity) as total_units,
                SUM(stock_quantity * price) as estimated_value
            FROM products
            WHERE expiry_date < CURDATE()
              AND is_active = 1
              AND stock_quantity > 0
            
            UNION ALL
            
            SELECT 
                'Expiring Critical (0-30 days)' as status,
                COUNT(*) as count,
                SUM(stock_quantity) as total_units,
                SUM(stock_quantity * price) as estimated_value
            FROM products
            WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              AND is_active = 1
              AND stock_quantity > 0
            
            UNION ALL
            
            SELECT 
                'Expiring Warning (31-90 days)' as status,
                COUNT(*) as count,
                SUM(stock_quantity) as total_units,
                SUM(stock_quantity * price) as estimated_value
            FROM products
            WHERE expiry_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 31 DAY) 
                                  AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
              AND is_active = 1
              AND stock_quantity > 0
        ";
        
        $result = $this->conn->query($sql);
        $report = [];
        
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        
        return $report;
    }
    
    /**
     * Check if stock deduction should be blocked due to expiry
     * Called before processing sale/order
     */
    public function blockSaleIfExpired($product_id, $quantity) {
        $check = $this->canSellProduct($product_id);
        
        if (!$check['can_sell']) {
            return [
                'blocked' => true,
                'reason' => $check['message'],
                'expiry_date' => $check['expiry_date']
            ];
        }
        
        return [
            'blocked' => false,
            'warning' => isset($check['warning']) ? $check['message'] : null
        ];
    }
}
?>
