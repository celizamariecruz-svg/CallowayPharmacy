<?php
/**
 * Migration 007: Add POS Sale Link to Online Orders
 * 
 * Adds pos_sale_id column to link online orders with POS sales
 * This allows tracking which POS sale was created when processing pickup payments
 */

require_once __DIR__ . '/../config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

try {
    echo "Starting migration 007: POS Sale Link\n\n";

    // Add pos_sale_id column
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'online_orders' 
            AND COLUMN_NAME = 'pos_sale_id'";
    $result = $conn->query($sql);

    if ($result->num_rows === 0) {
        echo "Adding pos_sale_id column to online_orders...\n";
        $sql = "ALTER TABLE online_orders ADD COLUMN pos_sale_id INT NULL AFTER picked_up_by";
        if ($conn->query($sql)) {
            echo "✓ Added pos_sale_id column\n";
        } else {
            throw new Exception("Failed to add pos_sale_id: " . $conn->error);
        }
    } else {
        echo "✓ pos_sale_id column already exists\n";
    }

    // Add foreign key constraint
    $sql = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'online_orders' 
            AND CONSTRAINT_NAME = 'fk_online_orders_sale'";
    $result = $conn->query($sql);

    if ($result->num_rows === 0) {
        echo "Adding foreign key constraint...\n";
        $sql = "ALTER TABLE online_orders 
                ADD CONSTRAINT fk_online_orders_sale 
                FOREIGN KEY (pos_sale_id) REFERENCES sales(sale_id) 
                ON DELETE SET NULL";
        if ($conn->query($sql)) {
            echo "✓ Added foreign key constraint\n";
        } else {
            echo "⚠ Could not add foreign key (this is optional): " . $conn->error . "\n";
        }
    } else {
        echo "✓ Foreign key constraint already exists\n";
    }

    echo "\n✅ Migration 007 completed successfully!\n";

} catch (Exception $e) {
    echo "\n❌ Migration 007 failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
?>
