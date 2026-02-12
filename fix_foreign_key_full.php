<?php
require 'db_connection.php';

try {
    // Disable foreign key checks
    $conn->query('SET FOREIGN_KEY_CHECKS=0');

    // Delete invalid rows in transactions where medicine_id does not exist in products
    $conn->query('DELETE t FROM transactions t LEFT JOIN products p ON t.medicine_id = p.product_id WHERE p.product_id IS NULL');

    // Drop existing foreign key constraint
    $conn->query('ALTER TABLE transactions DROP FOREIGN KEY transactions_ibfk_1');

    // Add foreign key constraint referencing products table
    $conn->query('ALTER TABLE transactions ADD CONSTRAINT transactions_ibfk_1 FOREIGN KEY (medicine_id) REFERENCES products(product_id)');

    // Re-enable foreign key checks
    $conn->query('SET FOREIGN_KEY_CHECKS=1');

    echo "Foreign key fixed and invalid data cleaned successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>
