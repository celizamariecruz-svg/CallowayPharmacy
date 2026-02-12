<?php
require_once 'db_connection.php';

echo "=== PERMISSIONS IN DATABASE ===\n\n";

$result = $conn->query("SELECT permission_name FROM permissions ORDER BY permission_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['permission_name'] . "\n";
    }
    echo "\nTotal: " . $result->num_rows . " permissions\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
