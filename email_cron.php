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

echo "Starting email notifications cron job...\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $emailService = new EmailService($conn);
    
    // 1. Check for low stock products
    echo "Checking for low stock products...\n";
    $query = "SELECT product_id, product_name, stock_quantity, reorder_level 
              FROM products 
              WHERE is_active = 1 
              AND stock_quantity <= reorder_level 
              ORDER BY stock_quantity ASC";
    
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
    
    $query = "SELECT product_id, product_name, batch_number, expiry_date, stock_quantity 
              FROM products 
              WHERE is_active = 1 
              AND expiry_date BETWEEN '$today' AND '$futureDate' 
              ORDER BY expiry_date ASC";
    
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
