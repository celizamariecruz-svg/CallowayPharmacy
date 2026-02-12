<?php
require_once 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$required_tables = [
    'users',
    'roles',
    'permissions',
    'role_permissions',
    'activity_logs', // Auth
    'products',
    'categories',
    'suppliers',
    'stock_movements', // Inventory
    'sales',
    'sale_items',
    'sale_payments', // POS
    'settings', // System
    'purchase_orders',
    'purchase_order_items', // PO
    'online_orders', // Online Ordering
    'loyalty_cards', // Loyalty
];

$required_files = [
    'dashboard.php',
    'pos.php',
    'inventory_management.php',
    'user_management.php',
    'reports.php',
    'settings_enhanced.php',
    'online_ordering.php',
    'medicine-locator.php',
    'expiry-monitoring.php',
    'header-component.php'
];

echo "=== SYSTEM HEALTH CHECK ===\n\n";

echo "--- DATABSE TABLES ---\n";
foreach ($required_tables as $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    if ($res && $res->num_rows > 0) {
        echo "✅ $table: EXISTS\n";
    } else {
        echo "❌ $table: MISSING\n";
    }
}

echo "\n--- CRITICAL FILES ---\n";
foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file: EXISTS\n";
    } else {
        echo "❌ $file: MISSING\n";
    }
}

echo "\n=== CHECK COMPLETE ===\n";
?>