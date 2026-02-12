<?php
require 'db_connection.php';

try {
    // Drop the existing foreign key constraint
    $conn->query('ALTER TABLE transactions DROP FOREIGN KEY transactions_ibfk_1');

    // Add a new foreign key constraint to reference the products table
    $conn->query('ALTER TABLE transactions ADD CONSTRAINT transactions_ibfk_1 FOREIGN KEY (medicine_id) REFERENCES products(product_id)');

    echo "Foreign key updated successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
