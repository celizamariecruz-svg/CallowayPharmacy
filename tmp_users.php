<?php
require 'db_connection.php';
$r = $conn->query('SELECT user_id, username, full_name FROM users ORDER BY user_id');
while ($row = $r->fetch_assoc()) {
    echo $row['user_id'] . ' | ' . $row['username'] . ' | ' . $row['full_name'] . "\n";
}

// Also show distinct cashier values in sales
echo "\n--- Distinct cashier values in sales ---\n";
$r2 = $conn->query('SELECT DISTINCT cashier FROM sales');
while ($row = $r2->fetch_assoc()) {
    echo $row['cashier'] . "\n";
}
