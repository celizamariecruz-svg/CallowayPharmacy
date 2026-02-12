<?php
/**
 * Setup online ordering tables
 * Creates: online_orders, online_order_items, pos_notifications
 */
require_once 'db_connection.php';

// Create online_orders table
$conn->query("
    CREATE TABLE IF NOT EXISTS online_orders (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NULL,
        customer_name VARCHAR(100) NOT NULL,
        contact_number VARCHAR(20) NULL,
        email VARCHAR(100) NULL,
        status ENUM('Pending', 'Confirmed', 'Preparing', 'Ready', 'Completed', 'Cancelled') DEFAULT 'Pending',
        total_amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'Cash on Pickup',
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Create online_order_items table
$conn->query("
    CREATE TABLE IF NOT EXISTS online_order_items (
        item_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES online_orders(order_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Create pos_notifications table
$conn->query("
    CREATE TABLE IF NOT EXISTS pos_notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        type VARCHAR(50) DEFAULT 'online_order',
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES online_orders(order_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

echo "✅ Online ordering tables created successfully.\n";
?>
