<?php
/**
 * Migration 006: Critical Business Logic Fixes
 * 
 * Implements:
 * 1. Prescription (Rx) medication tracking
 * 2. Pharmacist approval workflow
 * 3. Pickup confirmation tracking
 * 4. Return/refund workflow
 * 5. Batch/lot number tracking for expiry FIFO
 */

require_once __DIR__ . '/../config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== MIGRATION 006: CRITICAL BUSINESS LOGIC FIXES ===\n\n";

// Helper function to check if column exists
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// Helper function to check if table exists
function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

// ==========================================
// 1. PRODUCTS TABLE - ADD RX AND BATCH TRACKING
// ==========================================
echo "1. Updating products table...\n";

if (!columnExists($conn, 'products', 'is_prescription')) {
    $sql = "ALTER TABLE products ADD COLUMN is_prescription TINYINT(1) DEFAULT 0 AFTER category";
    if ($conn->query($sql)) {
        echo "   ✅ Added is_prescription column\n";
    } else {
        echo "   ❌ Error adding is_prescription: " . $conn->error . "\n";
    }
}

if (!columnExists($conn, 'products', 'batch_number')) {
    $sql = "ALTER TABLE products ADD COLUMN batch_number VARCHAR(100) NULL AFTER barcode";
    if ($conn->query($sql)) {
        echo "   ✅ Added batch_number column\n";
    } else {
        echo "   ❌ Error adding batch_number: " . $conn->error . "\n";
    }
}

if (!columnExists($conn, 'products', 'expiry_date')) {
    $sql = "ALTER TABLE products ADD COLUMN expiry_date DATE NULL AFTER batch_number";
    if ($conn->query($sql)) {
        echo "   ✅ Added expiry_date column\n";
    } else {
        echo "   ❌ Error adding expiry_date: " . $conn->error . "\n";
    }
}

// Add index for expiry date queries (FIFO support)
$result = $conn->query("SHOW INDEX FROM products WHERE Key_name = 'idx_expiry'");
if (!$result || $result->num_rows == 0) {
    $sql = "CREATE INDEX idx_expiry ON products(expiry_date, is_active)";
    if ($conn->query($sql)) {
        echo "   ✅ Added expiry_date index for FIFO queries\n";
    } else {
        echo "   ⚠️  Index may already exist: " . $conn->error . "\n";
    }
}

// ==========================================
// 2. ONLINE ORDERS - ADD RX APPROVAL TRACKING
// ==========================================
echo "\n2. Updating online_orders table...\n";

if (!columnExists($conn, 'online_orders', 'requires_rx_approval')) {
    $sql = "ALTER TABLE online_orders ADD COLUMN requires_rx_approval TINYINT(1) DEFAULT 0 AFTER status";
    if ($conn->query($sql)) {
        echo "   ✅ Added requires_rx_approval column\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
}

if (!columnExists($conn, 'online_orders', 'pharmacist_approved_by')) {
    $sql = "ALTER TABLE online_orders ADD COLUMN pharmacist_approved_by INT NULL AFTER requires_rx_approval";
    if ($conn->query($sql)) {
        echo "   ✅ Added pharmacist_approved_by column\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
}

if (!columnExists($conn, 'online_orders', 'pharmacist_approved_at')) {
    $sql = "ALTER TABLE online_orders ADD COLUMN pharmacist_approved_at DATETIME NULL AFTER pharmacist_approved_by";
    if ($conn->query($sql)) {
        echo "   ✅ Added pharmacist_approved_at column\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
}

if (!columnExists($conn, 'online_orders', 'rx_notes')) {
    $sql = "ALTER TABLE online_orders ADD COLUMN rx_notes TEXT NULL AFTER pharmacist_approved_at";
    if ($conn->query($sql)) {
        echo "   ✅ Added rx_notes column\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
}

// ==========================================
// 3. ONLINE ORDERS - ADD PICKUP TRACKING
// ==========================================
echo "\n3. Adding pickup confirmation tracking...\n";

if (!columnExists($conn, 'online_orders', 'picked_up_at')) {
    $sql = "ALTER TABLE online_orders ADD COLUMN picked_up_at DATETIME NULL AFTER updated_at";
    if ($conn->query($sql)) {
        echo "   ✅ Added picked_up_at column\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
}

if (!columnExists($conn, 'online_orders', 'picked_up_by')) {
    $sql = "ALTER TABLE online_orders ADD COLUMN picked_up_by VARCHAR(100) NULL AFTER picked_up_at";
    if ($conn->query($sql)) {
        echo "   ✅ Added picked_up_by column (staff who released order)\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
}

// ==========================================
// 4. CREATE RETURNS/REFUNDS TABLE
// ==========================================
echo "\n4. Creating returns/refunds table...\n";

if (!tableExists($conn, 'returns')) {
    $sql = "
    CREATE TABLE returns (
        return_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        return_number VARCHAR(50) NOT NULL UNIQUE,
        reason TEXT NOT NULL,
        status ENUM('Requested', 'Approved', 'Rejected', 'Refunded') DEFAULT 'Requested',
        requested_by VARCHAR(100) NOT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        refund_amount DECIMAL(10,2) DEFAULT 0.00,
        refund_method VARCHAR(50) NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES online_orders(order_id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(user_id),
        INDEX idx_status (status),
        INDEX idx_order (order_id),
        INDEX idx_requested_at (requested_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($sql)) {
        echo "   ✅ Created returns table\n";
    } else {
        echo "   ❌ Error creating returns table: " . $conn->error . "\n";
    }
}

if (!tableExists($conn, 'return_items')) {
    $sql = "
    CREATE TABLE return_items (
        return_item_id INT AUTO_INCREMENT PRIMARY KEY,
        return_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        condition_note TEXT NULL,
        restocked TINYINT(1) DEFAULT 0,
        restocked_at DATETIME NULL,
        restocked_by INT NULL,
        FOREIGN KEY (return_id) REFERENCES returns(return_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(product_id),
        FOREIGN KEY (restocked_by) REFERENCES users(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($sql)) {
        echo "   ✅ Created return_items table\n";
    } else {
        echo "   ❌ Error creating return_items table: " . $conn->error . "\n";
    }
}

// ==========================================
// 5. CREATE RX APPROVAL LOG
// ==========================================
echo "\n5. Creating prescription approval log...\n";

if (!tableExists($conn, 'rx_approval_log')) {
    $sql = "
    CREATE TABLE rx_approval_log (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        pharmacist_id INT NOT NULL,
        action ENUM('Approved', 'Rejected', 'Flagged') NOT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES online_orders(order_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(product_id),
        FOREIGN KEY (pharmacist_id) REFERENCES users(user_id),
        INDEX idx_order (order_id),
        INDEX idx_pharmacist (pharmacist_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($sql)) {
        echo "   ✅ Created rx_approval_log table\n";
    } else {
        echo "   ❌ Error creating rx_approval_log table: " . $conn->error . "\n";
    }
}

// ==========================================
// 6. ADD FOREIGN KEYS FOR RX APPROVAL
// ==========================================
echo "\n6. Adding foreign key constraints...\n";

// Check if FK exists before adding
$result = $conn->query("
    SELECT CONSTRAINT_NAME 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
    AND TABLE_NAME = 'online_orders' 
    AND CONSTRAINT_NAME = 'fk_pharmacist_approval'
");

if (!$result || $result->num_rows == 0) {
    $sql = "ALTER TABLE online_orders 
            ADD CONSTRAINT fk_pharmacist_approval 
            FOREIGN KEY (pharmacist_approved_by) REFERENCES users(user_id) ON DELETE SET NULL";
    if ($conn->query($sql)) {
        echo "   ✅ Added foreign key for pharmacist approval\n";
    } else {
        echo "   ⚠️  FK constraint may already exist or users table issue: " . $conn->error . "\n";
    }
}

// ==========================================
// 7. UPDATE STOCK_MOVEMENTS FOR BATCH TRACKING
// ==========================================
echo "\n7. Updating stock_movements table...\n";

if (!columnExists($conn, 'stock_movements', 'batch_number')) {
    $sql = "ALTER TABLE stock_movements ADD COLUMN batch_number VARCHAR(100) NULL AFTER product_id";
    if ($conn->query($sql)) {
        echo "   ✅ Added batch_number to stock_movements\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
}

if (!columnExists($conn, 'stock_movements', 'expiry_date')) {
    $sql = "ALTER TABLE stock_movements ADD COLUMN expiry_date DATE NULL AFTER batch_number";
    if ($conn->query($sql)) {
        echo "   ✅ Added expiry_date to stock_movements\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
}

// ==========================================
// 8. CREATE VIEW FOR EXPIRING PRODUCTS (FIFO HELPER)
// ==========================================
echo "\n8. Creating helper views...\n";

$conn->query("DROP VIEW IF EXISTS v_expiring_products");
$sql = "
CREATE VIEW v_expiring_products AS
SELECT 
    p.product_id,
    p.name,
    p.batch_number,
    p.expiry_date,
    p.stock_quantity,
    DATEDIFF(p.expiry_date, CURDATE()) as days_until_expiry,
    CASE 
        WHEN p.expiry_date < CURDATE() THEN 'EXPIRED'
        WHEN DATEDIFF(p.expiry_date, CURDATE()) <= 30 THEN 'CRITICAL'
        WHEN DATEDIFF(p.expiry_date, CURDATE()) <= 90 THEN 'WARNING'
        ELSE 'OK'
    END as expiry_status
FROM products p
WHERE p.expiry_date IS NOT NULL 
  AND p.is_active = 1
  AND p.stock_quantity > 0
ORDER BY p.expiry_date ASC, p.name ASC;
";

if ($conn->query($sql)) {
    echo "   ✅ Created v_expiring_products view\n";
} else {
    echo "   ❌ Error creating view: " . $conn->error . "\n";
}

// ==========================================
// SUMMARY
// ==========================================
echo "\n" . str_repeat("=", 60) . "\n";
echo "MIGRATION COMPLETE!\n";
echo str_repeat("=", 60) . "\n\n";

echo "✅ Products table updated with:\n";
echo "   - is_prescription flag\n";
echo "   - batch_number tracking\n";
echo "   - expiry_date (if not exists)\n";
echo "   - Index for FIFO queries\n\n";

echo "✅ Online orders updated with:\n";
echo "   - Rx approval workflow columns\n";
echo "   - Pickup confirmation tracking\n\n";

echo "✅ New tables created:\n";
echo "   - returns (refund workflow)\n";
echo "   - return_items\n";
echo "   - rx_approval_log\n\n";

echo "✅ Stock movements updated with batch/expiry tracking\n\n";

echo "✅ Helper view created: v_expiring_products\n\n";

echo "NEXT STEPS:\n";
echo "1. Run: php implement_rx_enforcement.php\n";
echo "2. Run: php implement_expiry_fifo.php\n";
echo "3. Test online ordering with Rx products\n";
echo "4. Test pickup confirmation flow\n\n";

$conn->close();
?>
