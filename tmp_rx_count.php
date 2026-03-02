<?php
require 'db_connection.php';
$r = $conn->query('SELECT COUNT(*) c FROM rx_approval_log');
echo "Total rx_approval_log entries: " . $r->fetch_assoc()['c'] . "\n";
