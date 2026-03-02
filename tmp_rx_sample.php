<?php
require 'db_connection.php';
$r = $conn->query('SELECT * FROM rx_approval_log ORDER BY created_at DESC LIMIT 5');
while ($row = $r->fetch_assoc()) {
    echo $row['log_id'] . ' | ' . $row['sale_reference'] . ' | ' . $row['product_id'] . ' | ' . $row['action'] . ' | ' . $row['created_at'] . "\n";
}
