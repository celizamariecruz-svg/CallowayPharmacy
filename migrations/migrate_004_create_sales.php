<?php
require_once __DIR__ . '/../config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Migrating Sales Tables...\n";

// 1. Create Sales Table
$sqlSales = "
CREATE TABLE IF NOT EXISTS sales (
    sale_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_reference VARCHAR(50) NOT NULL UNIQUE,
    total DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    paid_amount DECIMAL(10,2) NOT NULL,
    change_amount DECIMAL(10,2) NOT NULL,
    cashier VARCHAR(100) NOT NULL, -- Storing name directly for history
    -- Enhanced columns
    customer_name VARCHAR(100) NULL,
    status ENUM('completed', 'voided', 'refunded') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ref (sale_reference),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sqlSales)) {
    echo "✅ Sales table created/verified.\n";
} else {
    echo "❌ Error creating sales table: " . $conn->error . "\n";
}

// 2. Create Sale Items Table
$sqlItems = "
CREATE TABLE IF NOT EXISTS sale_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE,
    -- No foreign key to products to allow keeping history even if product deleted
    INDEX idx_sale (sale_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sqlItems)) {
    echo "✅ Sale Items table created/verified.\n";
} else {
    echo "❌ Error creating sale_items table: " . $conn->error . "\n";
}

echo "Migration Complete.\n";
?>