<?php
/**
 * Email Notification Cron Job
 * Run this script daily to send automated alerts
 * 
 * Setup: Run via Windows Task Scheduler or cron
 * Command: php email_cron.php
 */

require_once 'db_connection.php';
require_once 'email_service.php';

function columnExists($conn, $table, $column) {
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result && $result->num_rows > 0;
}

echo "Starting email notifications cron job...\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $emailService = new EmailService($conn);

    $productNameCol = columnExists($conn, 'products', 'name') ? 'name' : (columnExists($conn, 'products', 'product_name') ? 'product_name' : null);
    $stockCol = columnExists($conn, 'products', 'stock_quantity') ? 'stock_quantity' : (columnExists($conn, 'products', 'quantity') ? 'quantity' : null);
    $reorderExists = columnExists($conn, 'products', 'reorder_level');
    $expiryCol = columnExists($conn, 'products', 'expiry_date') ? 'expiry_date' : (columnExists($conn, 'products', 'expiration_date') ? 'expiration_date' : null);
    $batchExists = columnExists($conn, 'products', 'batch_number');
    $isActiveExists = columnExists($conn, 'products', 'is_active');

    if ($productNameCol === null || $stockCol === null) {
        throw new Exception('Products table is missing required stock columns for email alerts.');
    }
    
    // 1. Check for low stock products
    echo "Checking for low stock products...\n";
    $reorderSelect = $reorderExists ? 'reorder_level' : '10 AS reorder_level';
    $activeFilter = $isActiveExists ? 'is_active = 1 AND ' : '';
    $lowStockCondition = $reorderExists ? "$stockCol <= reorder_level" : "$stockCol <= 10";

    $query = "SELECT product_id, $productNameCol AS product_name, $stockCol AS stock_quantity, $reorderSelect
              FROM products
              WHERE {$activeFilter}{$lowStockCondition}
              ORDER BY $stockCol ASC";
    
    $result = $conn->query($query);
    $lowStockProducts = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $lowStockProducts[] = $row;
        }
        
        if (!empty($lowStockProducts)) {
            echo "Found " . count($lowStockProducts) . " low stock products. Sending alert...\n";
            if ($emailService->sendLowStockAlert($lowStockProducts)) {
                echo "✓ Low stock alert sent successfully!\n\n";
            } else {
                echo "✗ Failed to send low stock alert.\n\n";
            }
        }
    } else {
        echo "No low stock products found.\n\n";
    }
    
    // 2. Check for expiring products (30 days)
    echo "Checking for expiring products (next 30 days)...\n";
    $today = date('Y-m-d');
    $futureDate = date('Y-m-d', strtotime('+30 days'));
    
    if ($expiryCol !== null) {
        $batchSelect = $batchExists ? 'batch_number' : "'' AS batch_number";
        $expiryActiveFilter = $isActiveExists ? 'is_active = 1 AND ' : '';

        $query = "SELECT product_id, $productNameCol AS product_name, $batchSelect, $expiryCol AS expiry_date, $stockCol AS stock_quantity
                  FROM products
                  WHERE {$expiryActiveFilter}$expiryCol BETWEEN '$today' AND '$futureDate'
                  ORDER BY $expiryCol ASC";

        $result = $conn->query($query);
        $expiringProducts = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $expiringProducts[] = $row;
            }

            if (!empty($expiringProducts)) {
                echo "Found " . count($expiringProducts) . " expiring products. Sending warning...\n";
                if ($emailService->sendExpiryWarning($expiringProducts, 30)) {
                    echo "✓ Expiry warning sent successfully!\n\n";
                } else {
                    echo "✗ Failed to send expiry warning.\n\n";
                }
            }
        } else {
            echo "No products expiring in the next 30 days.\n\n";
        }
    } else {
        echo "Skipping expiry check: no expiry date column found.\n\n";
    }
    
    // 3. Send daily sales summary (for yesterday)
    echo "Generating daily sales summary...\n";
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    if ($emailService->sendDailySummary($yesterday)) {
        echo "✓ Daily summary sent successfully!\n\n";
    } else {
        echo "✗ Failed to send daily summary.\n\n";
    }
    
    echo "\nCron job completed successfully!\n";
    echo "Next run: " . date('Y-m-d H:i:s', strtotime('+1 day')) . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Email cron job error: " . $e->getMessage());
}

$conn->close();
