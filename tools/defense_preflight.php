<?php
require_once __DIR__ . '/../db_connection.php';

function checkTable(mysqli $conn, string $table): bool
{
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return $res && $res->num_rows > 0;
}

function checkColumn(mysqli $conn, string $table, string $column): bool
{
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($column) . "'");
    return $res && $res->num_rows > 0;
}

function rowCount(mysqli $conn, string $table): int
{
    $res = $conn->query("SELECT COUNT(*) c FROM `{$table}`");
    if (!$res) {
        return -1;
    }
    return (int)($res->fetch_assoc()['c'] ?? 0);
}

$requiredTables = [
    'users', 'employees', 'roles', 'products', 'categories',
    'purchase_orders', 'purchase_order_items', 'sales', 'sale_items'
];

$requiredColumns = [
    ['products', 'selling_price'],
    ['products', 'image_url'],
    ['products', 'category_id'],
    ['users', 'employee_id']
];

$checks = [];

foreach ($requiredTables as $table) {
    $checks[] = [
        'name' => "Table: {$table}",
        'ok' => checkTable($conn, $table)
    ];
}

foreach ($requiredColumns as [$table, $column]) {
    $checks[] = [
        'name' => "Column: {$table}.{$column}",
        'ok' => checkTable($conn, $table) && checkColumn($conn, $table, $column)
    ];
}

$backupDir = __DIR__ . '/../backups';
$uploadDir = __DIR__ . '/../uploads/products';
$checks[] = ['name' => 'Folder writable: backups', 'ok' => is_dir($backupDir) && is_writable($backupDir)];
$checks[] = ['name' => 'Folder writable: uploads/products', 'ok' => is_dir($uploadDir) && is_writable($uploadDir)];

$products = rowCount($conn, 'products');
$users = rowCount($conn, 'users');
$employees = rowCount($conn, 'employees');
$withImages = checkTable($conn, 'products')
    ? (int)($conn->query("SELECT COUNT(*) c FROM products WHERE image_url IS NOT NULL AND image_url <> ''")->fetch_assoc()['c'] ?? 0)
    : -1;

$allOk = !in_array(false, array_column($checks, 'ok'), true);

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Defense Deploy Preflight</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 24px; }
        .ok { color: #166534; }
        .err { color: #991b1b; }
        .box { max-width: 900px; border: 1px solid #ddd; border-radius: 10px; padding: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border-bottom: 1px solid #eee; text-align: left; padding: 8px; }
    </style>
</head>
<body>
    <h2>Defense Deploy Preflight</h2>
    <div class="box">
        <p class="<?php echo $allOk ? 'ok' : 'err'; ?>">
            <?php echo $allOk ? 'PASS: Core deployment checks are healthy.' : 'FAIL: Some required checks are missing.'; ?>
        </p>

        <table>
            <thead>
                <tr><th>Check</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($checks as $check): ?>
                <tr>
                    <td><?php echo htmlspecialchars($check['name']); ?></td>
                    <td class="<?php echo $check['ok'] ? 'ok' : 'err'; ?>"><?php echo $check['ok'] ? 'OK' : 'MISSING'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Data Snapshot</h3>
        <ul>
            <li>Products: <?php echo $products; ?></li>
            <li>Products with images: <?php echo $withImages; ?></li>
            <li>Users: <?php echo $users; ?></li>
            <li>Employees: <?php echo $employees; ?></li>
        </ul>
    </div>
</body>
</html>
