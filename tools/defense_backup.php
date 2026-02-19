<?php
require_once __DIR__ . '/../db_connection.php';

function isLocalRequest(): bool
{
    if (php_sapi_name() === 'cli') {
        return true;
    }

    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($remote, ['127.0.0.1', '::1'], true);
}

function out(string $message): void
{
    if (php_sapi_name() === 'cli') {
        echo $message . PHP_EOL;
        return;
    }
    echo htmlspecialchars($message) . "<br>\n";
}

function buildInsertSql(mysqli $conn, string $table): string
{
    $rows = $conn->query("SELECT * FROM `{$table}`");
    if (!$rows || $rows->num_rows === 0) {
        return "";
    }

    $insertSql = "";
    while ($row = $rows->fetch_assoc()) {
        $values = [];
        foreach ($row as $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . $conn->real_escape_string((string) $value) . "'";
            }
        }
        $insertSql .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
    }

    return $insertSql . "\n";
}

if (!isLocalRequest()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h2>Defense Backup Tool</h2>";
    echo "<p>Creating full database backup...</p>";
}

$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Y-m-d_H-i-s');
$fileName = "full_backup_{$timestamp}.sql";
$filePath = $backupDir . '/' . $fileName;

$dbNameRow = $conn->query('SELECT DATABASE() AS db_name')->fetch_assoc();
$dbName = $dbNameRow['db_name'] ?? 'calloway_pharmacy';

$sql = "-- Calloway Pharmacy FULL backup\n";
$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Database: {$dbName}\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

$tablesRes = $conn->query('SHOW TABLES');
if (!$tablesRes) {
    out('Failed to list tables: ' . $conn->error);
    exit(1);
}

$tableCount = 0;
while ($tableRow = $tablesRes->fetch_array()) {
    $table = $tableRow[0];
    $tableCount++;

    $createRes = $conn->query("SHOW CREATE TABLE `{$table}`");
    if (!$createRes) {
        out("Skipped table {$table}: " . $conn->error);
        continue;
    }

    $createRow = $createRes->fetch_assoc();
    $createSql = $createRow['Create Table'] ?? '';

    $sql .= "-- ----------------------------\n";
    $sql .= "-- Table: {$table}\n";
    $sql .= "-- ----------------------------\n";
    $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
    $sql .= $createSql . ";\n\n";
    $sql .= buildInsertSql($conn, $table);
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

if (file_put_contents($filePath, $sql) === false) {
    out('Backup failed: unable to write file.');
    exit(1);
}

$size = filesize($filePath);
$sizeMb = round($size / 1024 / 1024, 2);

out("Backup created successfully.");
out("File: {$fileName}");
out("Tables: {$tableCount}");
out("Size: {$sizeMb} MB");

if (php_sapi_name() !== 'cli') {
    echo "<p><strong>Done.</strong> Save this file safely before deployment.</p>";
}
