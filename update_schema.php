<?php
require 'db_connection.php';

try {
    // Removed the duplicate column addition for 'on_leave' permanently
    // The script now only applies necessary schema updates.
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}

$conn->close();
