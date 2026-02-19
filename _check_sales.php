<?php
require_once 'db_connection.php';

echo "=== sales table ===\n";
$r = $conn->query('SHOW COLUMNS FROM sales');
while ($row = $r->fetch_assoc()) echo '  ' . $row['Field'] . ' | ' . $row['Type'] . "\n";

echo "\n=== sale_items table ===\n";
$r = $conn->query('SHOW COLUMNS FROM sale_items');
while ($row = $r->fetch_assoc()) echo '  ' . $row['Field'] . ' | ' . $row['Type'] . "\n";

echo "\n=== online_orders table ===\n";
$r = $conn->query("SHOW COLUMNS FROM online_orders");
while ($row = $r->fetch_assoc()) echo '  ' . $row['Field'] . ' | ' . $row['Type'] . "\n";
