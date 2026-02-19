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

function runSqlFile(mysqli $conn, string $filePath): array
{
    $sql = file_get_contents($filePath);
    if ($sql === false || trim($sql) === '') {
        return ['success' => false, 'message' => 'Backup file is empty or unreadable.'];
    }

    if (!$conn->multi_query($sql)) {
        return ['success' => false, 'message' => 'Restore failed: ' . $conn->error];
    }

    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    return ['success' => true, 'message' => 'Restore complete.'];
}

if (!isLocalRequest()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$files = glob($backupDir . '/full_backup_*.sql') ?: [];
rsort($files);

$message = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = basename($_POST['backup_file'] ?? '');
    $confirm = trim($_POST['confirm_text'] ?? '');

    if ($confirm !== 'RESTORE') {
        $message = 'Restore blocked: type RESTORE exactly to continue.';
    } else {
        $target = realpath($backupDir . '/' . $selected);
        $basePath = realpath($backupDir);

        if (!$target || !$basePath || strpos($target, $basePath) !== 0 || !file_exists($target)) {
            $message = 'Invalid backup file selected.';
        } else {
            // Create a current snapshot before restoring
            $snapshot = __DIR__ . '/defense_backup.php';
            if (file_exists($snapshot)) {
                $phpBin = PHP_BINARY ?: 'php';
                $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($snapshot);
                @exec($cmd);
            }

            $result = runSqlFile($conn, $target);
            $ok = $result['success'];
            $message = $result['message'];
        }
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Defense Restore Tool</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; margin: 24px; }
        .box { max-width: 760px; padding: 16px; border: 1px solid #ddd; border-radius: 10px; }
        .ok { color: #166534; }
        .err { color: #991b1b; }
        select, input, button { width: 100%; padding: 10px; margin-top: 10px; }
        button { cursor: pointer; }
    </style>
</head>
<body>
    <h2>Defense Restore Tool</h2>
    <div class="box">
        <p><strong>Warning:</strong> Restoring will replace current database content.</p>
        <p>Before restore, this tool creates a fresh full backup automatically.</p>

        <?php if ($message): ?>
            <p class="<?php echo $ok ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form method="post">
            <label>Select backup file:</label>
            <select name="backup_file" required>
                <option value="">-- choose backup --</option>
                <?php foreach ($files as $file): $name = basename($file); ?>
                    <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
            </select>

            <label>Type RESTORE to confirm:</label>
            <input type="text" name="confirm_text" placeholder="RESTORE" required>

            <button type="submit">Run Restore</button>
        </form>
    </div>
</body>
</html>
