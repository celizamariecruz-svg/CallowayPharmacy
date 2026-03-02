<?php
require 'db_connection.php';

foreach (['sales', 'sale_items', 'products', 'rx_approval_log'] as $t) {
    $r = $conn->query("SHOW CREATE TABLE $t");
    if ($r) {
        $row = $r->fetch_assoc();
        echo "=== $t ===\n" . $row['Create Table'] . "\n\n";
    } else {
        echo "=== $t === NOT FOUND\n\n";
    }
}
