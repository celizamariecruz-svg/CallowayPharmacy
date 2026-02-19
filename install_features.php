<?php
/**
 * Installation Script for New Features
 * Run this once to set up purchase orders tables
 */

require_once 'db_connection.php';

echo "<h1>Calloway Pharmacy - Feature Installation</h1>";
echo "<p>Installing new features...</p>";

// Create purchase_orders table
$sql = "CREATE TABLE IF NOT EXISTS purchase_orders (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'purchase_orders' created successfully<br>";
} else {
    echo "✗ Error creating 'purchase_orders' table: " . $conn->error . "<br>";
}

// Create purchase_order_items table
$sql = "CREATE TABLE IF NOT EXISTS purchase_order_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'purchase_order_items' created successfully<br>";
} else {
    echo "✗ Error creating 'purchase_order_items' table: " . $conn->error . "<br>";
}

// Create indexes
$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_po_status ON purchase_orders(status)",
    "CREATE INDEX IF NOT EXISTS idx_po_supplier ON purchase_orders(supplier_id)",
    "CREATE INDEX IF NOT EXISTS idx_po_date ON purchase_orders(created_at)"
];

foreach ($indexes as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "✓ Index created successfully<br>";
    } else {
        echo "✗ Error creating index: " . $conn->error . "<br>";
    }
}

// Add reorder_level column to products if it doesn't exist
$sql = "SHOW COLUMNS FROM products LIKE 'reorder_level'";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    $sql = "ALTER TABLE products ADD COLUMN reorder_level INT DEFAULT 20 AFTER stock_quantity";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Column 'reorder_level' added to products table<br>";
    } else {
        echo "✗ Error adding 'reorder_level' column: " . $conn->error . "<br>";
    }
} else {
    echo "ℹ Column 'reorder_level' already exists<br>";
}

// Create settings table for email configuration if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'text',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'settings' created successfully<br>";
} else {
    echo "✗ Error creating 'settings' table: " . $conn->error . "<br>";
}

// Insert default SMTP settings if they don't exist
$defaultSettings = [
    ['smtp_host', 'smtp.gmail.com', 'text', 'SMTP server hostname'],
    ['smtp_port', '465', 'number', 'SMTP server port'],
    ['smtp_username', '', 'text', 'SMTP username/email'],
    ['smtp_password', '', 'password', 'SMTP password'],
    ['smtp_from_email', 'noreply@callowaypharmacy.com', 'email', 'From email address'],
    ['smtp_from_name', 'Calloway Pharmacy', 'text', 'From name']
];

$stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");

foreach ($defaultSettings as $setting) {
    $stmt->bind_param('ssss', $setting[0], $setting[1], $setting[2], $setting[3]);
    $stmt->execute();
}

echo "✓ Default settings inserted<br>";

echo "<h2>Installation Complete!</h2>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Run <code>composer install</code> to install PHPMailer and TCPDF</li>";
echo "<li>Configure SMTP settings in the Settings page</li>";
echo "<li>Test email notifications using <code>email_cron.php</code></li>";
echo "<li>Set up Windows Task Scheduler to run <code>email_cron.php</code> daily</li>";
echo "<li>Start creating purchase orders to manage inventory restocking</li>";
echo "</ol>";

echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";

$conn->close();
