<?php
include 'db_connection.php';

try {
    // Add location column if it doesn't exist
    $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS location VARCHAR(255) NULL");
    
    // Add expiry_date column if it doesn't exist
    $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS expiry_date DATE NULL");
    
    echo "Table structure updated successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?> 
