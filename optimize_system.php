<?php
/**
 * System Optimization Script
 * Run this script to apply all optimizations
 */

require_once 'config.php';
require_once 'db_connection.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>System Optimization - Calloway Pharmacy IMS</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2563eb; }
        h2 { color: #333; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
        .success { color: #16a34a; background: #dcfce7; padding: 10px; border-radius: 5px; margin: 5px 0; }
        .error { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 5px; margin: 5px 0; }
        .warning { color: #d97706; background: #fef3c7; padding: 10px; border-radius: 5px; margin: 5px 0; }
        .info { color: #2563eb; background: #dbeafe; padding: 10px; border-radius: 5px; margin: 5px 0; }
        pre { background: #1e1e1e; color: #ddd; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .step { font-weight: bold; margin-top: 15px; }
    </style>
</head>
<body>
    <h1>🚀 Calloway Pharmacy IMS - System Optimization</h1>";

$results = [];

// 1. Create necessary directories
echo "<div class='card'><h2>1. Creating Directories</h2>";

$directories = ['logs', 'cache', 'cache/rate_limits', 'backups'];
foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        if (mkdir($path, 0755, true)) {
            echo "<div class='success'>✓ Created directory: $dir</div>";
        } else {
            echo "<div class='error'>✗ Failed to create directory: $dir</div>";
        }
    } else {
        echo "<div class='info'>ℹ Directory already exists: $dir</div>";
    }
    
    // Create .htaccess for protection
    $htaccess = $path . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all");
        echo "<div class='success'>✓ Protected directory: $dir</div>";
    }
}
echo "</div>";

// 2. Database optimizations
echo "<div class='card'><h2>2. Database Optimizations</h2>";

// Check if indexes exist and create them
$indexes = [
    ['products', 'idx_products_active_name', '(is_active, name)'],
    ['products', 'idx_products_category', '(category)'],
    ['products', 'idx_products_expiry', '(expiry_date)'],
    ['products', 'idx_products_stock', '(stock_quantity)'],
    ['sales', 'idx_sales_created', '(created_at)'],
    ['sales', 'idx_sales_reference', '(sale_reference)'],
    ['sale_items', 'idx_sale_items_product', '(product_id)'],
    ['employees', 'idx_employees_role', '(role)'],
    ['employees', 'idx_employees_leave', '(on_leave)']
];

foreach ($indexes as $idx) {
    list($table, $name, $columns) = $idx;
    
    // Check if index exists
    $result = $conn->query("SHOW INDEX FROM $table WHERE Key_name = '$name'");
    if ($result && $result->num_rows === 0) {
        $sql = "ALTER TABLE $table ADD INDEX $name $columns";
        if ($conn->query($sql)) {
            echo "<div class='success'>✓ Created index: $table.$name</div>";
        } else {
            echo "<div class='warning'>⚠ Could not create index: $table.$name - " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='info'>ℹ Index already exists: $table.$name</div>";
    }
}

// Optimize tables
echo "<p class='step'>Optimizing tables...</p>";
$tables = ['products', 'sales', 'sale_items', 'employees'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        if ($conn->query("OPTIMIZE TABLE $table")) {
            echo "<div class='success'>✓ Optimized table: $table</div>";
        }
    }
}

// Analyze tables
echo "<p class='step'>Analyzing tables...</p>";
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $conn->query("ANALYZE TABLE $table");
        echo "<div class='success'>✓ Analyzed table: $table</div>";
    }
}

echo "</div>";

// 3. Create summary tables for reports
echo "<div class='card'><h2>3. Creating Summary Tables</h2>";

$summaryTables = "
CREATE TABLE IF NOT EXISTS daily_sales_summary (
    summary_date DATE PRIMARY KEY,
    total_sales DECIMAL(12,2) DEFAULT 0,
    transaction_count INT DEFAULT 0,
    items_sold INT DEFAULT 0,
    avg_transaction DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
";

if ($conn->query($summaryTables)) {
    echo "<div class='success'>✓ Created daily_sales_summary table</div>";
} else {
    echo "<div class='warning'>⚠ daily_sales_summary: " . $conn->error . "</div>";
}

echo "</div>";

// 4. Clear expired cache
echo "<div class='card'><h2>4. Cache Management</h2>";

require_once 'CacheManager.php';
$cache = CacheManager::getInstance();
$cleared = $cache->clearExpired();
echo "<div class='success'>✓ Cleared $cleared expired cache entries</div>";

$stats = $cache->getStats();
echo "<div class='info'>Cache stats: {$stats['valid_entries']} valid entries, {$stats['total_size_formatted']} total size</div>";

echo "</div>";

// 5. System check
echo "<div class='card'><h2>5. System Health Check</h2>";

// Check PHP version
$phpVersion = phpversion();
if (version_compare($phpVersion, '7.4', '>=')) {
    echo "<div class='success'>✓ PHP Version: $phpVersion (Good)</div>";
} else {
    echo "<div class='warning'>⚠ PHP Version: $phpVersion (Recommended: 7.4+)</div>";
}

// Check required extensions
$extensions = ['mysqli', 'json', 'mbstring', 'session'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<div class='success'>✓ Extension loaded: $ext</div>";
    } else {
        echo "<div class='error'>✗ Extension missing: $ext</div>";
    }
}

// Check writable directories
$writableDirs = ['logs', 'cache', 'backups'];
foreach ($writableDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_writable($path)) {
        echo "<div class='success'>✓ Writable: $dir</div>";
    } else {
        echo "<div class='warning'>⚠ Not writable: $dir (run: chmod 755 $dir)</div>";
    }
}

// Check database connection
if ($conn->ping()) {
    echo "<div class='success'>✓ Database connection: Active</div>";
} else {
    echo "<div class='error'>✗ Database connection: Failed</div>";
}

echo "</div>";

// 6. Performance recommendations
echo "<div class='card'><h2>6. Performance Recommendations</h2>";

// Get database stats
$result = $conn->query("SHOW TABLE STATUS");
$totalSize = 0;
$tableInfo = [];
while ($row = $result->fetch_assoc()) {
    $size = ($row['Data_length'] + $row['Index_length']);
    $totalSize += $size;
    $tableInfo[$row['Name']] = [
        'rows' => $row['Rows'],
        'size' => round($size / 1024, 2) . ' KB'
    ];
}

echo "<div class='info'>📊 Database size: " . round($totalSize / 1024 / 1024, 2) . " MB</div>";

// Check for large tables
foreach ($tableInfo as $table => $info) {
    if ($info['rows'] > 10000) {
        echo "<div class='warning'>⚠ Large table: $table ({$info['rows']} rows) - Consider archiving old data</div>";
    }
}

echo "
<p><strong>Additional recommendations:</strong></p>
<ul>
    <li>Enable OPcache for PHP (significant performance boost)</li>
    <li>Use a CDN for static assets in production</li>
    <li>Enable GZIP compression in Apache/Nginx</li>
    <li>Set up scheduled backups using backup_cron.php</li>
    <li>Monitor slow queries in MySQL (slow_query_log)</li>
</ul>
";

echo "</div>";

// Summary
echo "<div class='card'>
    <h2>✅ Optimization Complete</h2>
    <p>Your Calloway Pharmacy IMS has been optimized with:</p>
    <ul>
        <li>Database indexes for faster queries</li>
        <li>Table optimization and analysis</li>
        <li>File-based caching system</li>
        <li>Protected directories for logs and cache</li>
        <li>Performance monitoring capabilities</li>
    </ul>
    <p><strong>Next steps:</strong></p>
    <ol>
        <li>Run <code>database_optimization.sql</code> for additional stored procedures</li>
        <li>Set <code>ENVIRONMENT=production</code> for production deployment</li>
        <li>Enable HTTPS and update .htaccess accordingly</li>
        <li>Set up automated backups</li>
    </ol>
</div>";

echo "</body></html>";
?>
