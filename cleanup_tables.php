<?php
include 'db_connection.php';

try {
    // Start transaction
    $conn->begin_transaction();

    // Clean up tables in the correct order due to foreign key constraints
    $conn->query('DELETE FROM transactions');
    $conn->query('DELETE FROM expiry_monitoring');
    $conn->query('DELETE FROM medicine_inventory');
    $conn->query('DELETE FROM products');
    
    // Reset auto-increment values
    $conn->query('ALTER TABLE transactions AUTO_INCREMENT = 1');
    $conn->query('ALTER TABLE expiry_monitoring AUTO_INCREMENT = 1');
    $conn->query('ALTER TABLE medicine_inventory AUTO_INCREMENT = 1');
    $conn->query('ALTER TABLE products AUTO_INCREMENT = 1');
    
    // Commit transaction
    $conn->commit();
    echo "Tables cleaned up successfully!\n";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}
?> 
