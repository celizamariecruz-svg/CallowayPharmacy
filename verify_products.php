<?php
include 'db_connection.php';

// Count total products
$result = $conn->query('SELECT COUNT(*) as count FROM products');
$row = $result->fetch_assoc();
echo "Total Products Imported: " . $row['count'] . "\n\n";

// Show sample products
echo "Sample Products:\n";
echo str_repeat("-", 80) . "\n";
echo sprintf("%-30s | %-20s | %-8s | %-8s\n", "Product Name", "Category", "Price", "Stock");
echo str_repeat("-", 80) . "\n";

$result = $conn->query('SELECT name, category, price, stock_quantity FROM products ORDER BY category, name LIMIT 15');
while($row = $result->fetch_assoc()) {
    echo sprintf("%-30s | %-20s | ₱%-7.2f | %-8d\n", 
        substr($row['name'], 0, 29), 
        substr($row['category'], 0, 19), 
        $row['price'], 
        $row['stock_quantity']
    );
}

echo "\n" . str_repeat("-", 80) . "\n";

// Show category breakdown
echo "\nProducts by Category:\n";
$result = $conn->query('SELECT category, COUNT(*) as count, SUM(stock_quantity) as total_stock FROM products GROUP BY category ORDER BY category');
while($row = $result->fetch_assoc()) {
    echo sprintf("%-25s: %3d products, %5d units in stock\n", $row['category'], $row['count'], $row['total_stock']);
}
?>
