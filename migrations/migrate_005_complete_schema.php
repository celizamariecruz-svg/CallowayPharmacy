<?php
require_once __DIR__ . '/../config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== MIGRATING MISSING SCHEMAS ===\n\n";

// ==========================================
// 1. PURCHASE ORDERS
// ==========================================
echo "1. Creating Purchase Orders Tables...\n";
$sql_po = "
CREATE TABLE IF NOT EXISTS purchase_orders (
    po_id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    status ENUM('Pending', 'Ordered', 'Received', 'Cancelled') DEFAULT 'Pending',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT,
    ordered_by INT,
    ordered_date DATETIME,
    received_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
    FOREIGN KEY (ordered_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    po_item_id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    received_quantity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->multi_query($sql_po)) {
    do {
        if ($res = $conn->store_result())
            $res->free();
    } while ($conn->more_results() && $conn->next_result());
    echo "✅ Purchase Orders tables created.\n";
} else {
    echo "❌ Error PO: " . $conn->error . "\n";
}

// ==========================================
// 2. SETTINGS
// ==========================================
echo "\n2. Creating Settings Table...\n";
$sql_settings = "
CREATE TABLE IF NOT EXISTS settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql_settings)) {
    echo "✅ Settings table created.\n";

    // Insert default settings if empty
    $check = $conn->query("SELECT COUNT(*) as count FROM settings");
    if ($check->fetch_assoc()['count'] == 0) {
        $defaults = "
        INSERT INTO settings (setting_key, setting_value, category, description) VALUES
        ('company_name', 'Calloway Pharmacy', 'company', 'Company/Store name'),
        ('tax_rate', '12.00', 'tax', 'Default tax rate percentage'),
        ('currency_symbol', '₱', 'tax', 'Currency symbol'),
        ('low_stock_threshold', '20', 'alerts', 'Low stock alert threshold');
        ";
        $conn->query($defaults);
        echo "   -> Default settings inserted.\n";
    }
} else {
    echo "❌ Error Settings: " . $conn->error . "\n";
}

// ==========================================
// 3. SALE PAYMENTS
// ==========================================
echo "\n3. Creating Sale Payments Table...\n";
$sql_payments = "
CREATE TABLE IF NOT EXISTS sale_payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    payment_method ENUM('CASH', 'GCASH', 'MAYA', 'CARD', 'BANK_TRANSFER') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference_number VARCHAR(100) NULL COMMENT 'Transaction reference',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE,
    INDEX idx_sale (sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql_payments)) {
    echo "✅ Sale Payments table created.\n";
} else {
    echo "❌ Error Payments: " . $conn->error . "\n";
}

// ==========================================
// 4. LOYALTY SYSTEM
// ==========================================
echo "\n4. Creating Loyalty System Tables...\n";
$sql_loyalty = "
CREATE TABLE IF NOT EXISTS loyalty_members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    points INT DEFAULT 0,
    member_since DATE DEFAULT CURRENT_DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loyalty_points_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    points INT NOT NULL,
    transaction_type ENUM('EARN', 'REDEEM') NOT NULL,
    reference_id VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES loyalty_members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->multi_query($sql_loyalty)) {
    do {
        if ($res = $conn->store_result())
            $res->free();
    } while ($conn->more_results() && $conn->next_result());
    echo "✅ Loyalty tables created.\n";
} else {
    echo "❌ Error Loyalty: " . $conn->error . "\n";
}

// ==========================================
// 5. ONLINE ORDERING
// ==========================================
echo "\n5. Creating Online Ordering Tables...\n";
$sql_online = "
CREATE TABLE IF NOT EXISTS online_orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(100) NULL,
    address TEXT NOT NULL,
    status ENUM('Pending', 'Confirmed', 'Preparing', 'Ready', 'Completed', 'Cancelled') DEFAULT 'Pending',
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash on Pickup',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS online_order_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES online_orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->multi_query($sql_online)) {
    do {
        if ($res = $conn->store_result())
            $res->free();
    } while ($conn->more_results() && $conn->next_result());
    echo "✅ Online Ordering tables created.\n";
} else {
    echo "❌ Error Online: " . $conn->error . "\n";
}

echo "\n=== MIGRATION COMPLETE ===\n";
?>