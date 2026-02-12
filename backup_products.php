<?php
/**
 * Automatic Product Backup System
 * Backs up all products to prevent data loss
 * Run this manually or via cron job
 */

require_once 'db_connection.php';

// Create backups directory if not exists
$backup_dir = __DIR__ . '/backups';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Generate backup filename with timestamp
$timestamp = date('Y-m-d_His');
$backup_file = $backup_dir . '/products_backup_' . $timestamp . '.sql';

// Export products table
$query = "SELECT * FROM products WHERE is_active = 1 ORDER BY product_id";
$result = $conn->query($query);

if (!$result) {
    die("Error fetching products: " . $conn->error);
}

// Start building SQL backup
$sql_backup = "-- Calloway Pharmacy Products Backup\n";
$sql_backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sql_backup .= "-- Total Products: " . $result->num_rows . "\n\n";
$sql_backup .= "USE calloway_pharmacy;\n\n";

// Create backup insert statements
$sql_backup .= "-- Backup existing products\n";
$sql_backup .= "-- To restore: Run this file with mysql -u root < products_backup_XXXX.sql\n\n";

while ($row = $result->fetch_assoc()) {
    $values = [];
    foreach ($row as $key => $value) {
        if ($value === null) {
            $values[] = 'NULL';
        } else {
            $values[] = "'" . $conn->real_escape_string($value) . "'";
        }
    }
    
    $sql_backup .= "INSERT INTO products (" . implode(', ', array_keys($row)) . ") VALUES (" . implode(', ', $values) . ") ON DUPLICATE KEY UPDATE ";
    
    $updates = [];
    foreach (array_keys($row) as $column) {
        if ($column !== 'product_id') {
            $updates[] = "$column = VALUES($column)";
        }
    }
    $sql_backup .= implode(', ', $updates) . ";\n";
}

// Save to file
if (file_put_contents($backup_file, $sql_backup)) {
    echo "✓ Products backed up successfully!\n";
    echo "  Backup file: $backup_file\n";
    echo "  Products saved: " . $result->num_rows . "\n";
    
    // Also create a "latest" backup for quick restore
    $latest_backup = $backup_dir . '/products_backup_latest.sql';
    copy($backup_file, $latest_backup);
    echo "  Latest backup: $latest_backup\n";
    
    // Keep only last 10 backups (cleanup old ones)
    $backups = glob($backup_dir . '/products_backup_*.sql');
    if (count($backups) > 10) {
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $to_delete = array_slice($backups, 0, count($backups) - 10);
        foreach ($to_delete as $old_backup) {
            if (basename($old_backup) !== 'products_backup_latest.sql') {
                unlink($old_backup);
            }
        }
    }
} else {
    echo "✗ Failed to create backup file\n";
}
?>
