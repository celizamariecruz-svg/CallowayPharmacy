<?php
require 'db_connection.php';
$conn->query("DELETE FROM rx_approval_log WHERE action='POS Dispensed'");
echo "Deleted: " . $conn->affected_rows . "\n";
