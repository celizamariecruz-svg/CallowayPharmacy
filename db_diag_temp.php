<?php
/**
 * Temporary database diagnostic – lists available databases.
 * DELETE THIS FILE after fixing DB_NAME in Azure App Settings.
 */
header('Content-Type: text/plain; charset=utf-8');

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$ssl  = filter_var(getenv('DB_SSL'), FILTER_VALIDATE_BOOLEAN)
     || strpos($host, '.mysql.database.azure.com') !== false;

echo "=== DB Diagnostic ===\n";
echo "Host: {$host}\n";
echo "User: {$user}\n";
echo "SSL:  " . ($ssl ? 'yes' : 'no') . "\n\n";

try {
    if ($ssl) {
        $c = mysqli_init();
        $c->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        $c->ssl_set(null, null, null, null, null);
        $c->real_connect($host, $user, $pass, null, 3306, null, MYSQLI_CLIENT_SSL);
    } else {
        $c = new mysqli($host, $user, $pass);
    }

    $r = $c->query("SHOW DATABASES");
    echo "Databases:\n";
    while ($row = $r->fetch_row()) {
        echo "  - {$row[0]}\n";
    }
    $c->close();
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== File Check ===\n";
$files = ['onlineordering.php', 'login.php', 'dashboard.php', 'ImageHelper.php', 'db_connection.php'];
foreach ($files as $f) {
    $exists = file_exists(__DIR__ . '/' . $f);
    echo "  {$f}: " . ($exists ? 'EXISTS (' . filesize(__DIR__ . '/' . $f) . ' bytes)' : 'MISSING') . "\n";
}
echo "\nPHP version: " . PHP_VERSION . "\n";
echo "Document root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "Script dir: " . __DIR__ . "\n";

echo "\n=== Permissions ===\n";
$path = __DIR__ . '/onlineordering.php';
echo "  readable: " . (is_readable($path) ? 'yes' : 'no') . "\n";
echo "  perms: " . substr(sprintf('%o', fileperms($path)), -4) . "\n";

echo "\n=== DB Tables ===\n";
try {
    if ($ssl) {
        $c2 = mysqli_init();
        $c2->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        $c2->ssl_set(null, null, null, null, null);
        $c2->real_connect($host, $user, $pass, 'pharmacycalloway-database', 3306, null, MYSQLI_CLIENT_SSL);
    } else {
        $c2 = new mysqli($host, $user, $pass, 'pharmacycalloway-database');
    }
    $tr = $c2->query("SHOW TABLES");
    if ($tr && $tr->num_rows > 0) {
        while ($row = $tr->fetch_row()) echo "  - {$row[0]}\n";
    } else {
        echo "  (no tables)\n";
    }
    $c2->close();
} catch (Exception $e) {
    echo "  Error: {$e->getMessage()}\n";
}

echo "\n=== Test require onlineordering ===\n";
ob_start();
try {
    // Don't actually require it - just check if PHP can parse it
    $output = shell_exec('php -l /home/site/wwwroot/onlineordering.php 2>&1');
    echo "Lint: {$output}\n";
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
ob_end_flush();
