<?php
include 'db_connection.php';

// Clear existing sample data - remove dependent records first
$conn->query("DELETE FROM sale_items");
$conn->query("DELETE FROM sales");
$conn->query("DELETE FROM stock_movements");
$conn->query("DELETE FROM products");

// Read and execute the import file using multi_query
$importSql = file_get_contents('import_products.sql');

if ($conn->multi_query($importSql)) {
    echo "Import executed successfully!\n";
    // Consume all results to avoid "Commands out of sync" error
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
} else {
    echo "Error executing import: " . $conn->error . "\n";
}

echo "Import completed!\n";
?>
