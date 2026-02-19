<?php
require_once 'db_connection.php';
require_once 'Auth.php';

$auth = new Auth($conn);
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

header('Content-Type: text/html; charset=UTF-8');

function runCmd($command)
{
    $output = [];
    $code = 0;
    exec($command . ' 2>&1', $output, $code);
    return ['code' => $code, 'output' => implode("\n", $output)];
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$configPath = __DIR__ . DIRECTORY_SEPARATOR . 'pos_printer_config.php';
$config = file_exists($configPath) ? include $configPath : [];

$printerName = $config['printer_name'] ?? '(missing)';
$pythonPath = $config['python_path'] ?? 'python';

$spooler = runCmd('powershell -NoProfile -Command "Get-Service -Name Spooler | Select-Object Name,Status,StartType | Format-Table -AutoSize | Out-String"');
$printers = runCmd('powershell -NoProfile -Command "if (Get-Command Get-Printer -ErrorAction SilentlyContinue) { Get-Printer | Select-Object Name,Default,PrinterStatus,DriverName,PortName | Format-Table -AutoSize | Out-String } else { Write-Output \"Get-Printer not available\" }"');

$pythonCheck = runCmd('powershell -NoProfile -Command "' . str_replace('"', '\\"', $pythonPath) . ' --version"');
$escposCheck = runCmd('powershell -NoProfile -Command "' . str_replace('"', '\\"', $pythonPath) . ' -c \"import importlib.util;print(\\\"escpos=\\\" + str(importlib.util.find_spec(\\\"escpos\\\") is not None))\""');

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printer Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f6fa; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 14px; margin-bottom: 12px; }
        h1 { margin-top: 0; }
        pre { background: #111; color: #f1f1f1; padding: 10px; border-radius: 6px; overflow: auto; white-space: pre-wrap; }
        .ok { color: #0b7a2a; font-weight: 700; }
        .bad { color: #b42318; font-weight: 700; }
    </style>
</head>
<body>
    <h1>POS Printer Diagnostic</h1>

    <div class="card">
        <strong>Configured Printer Name:</strong> <?= h($printerName) ?><br>
        <strong>Configured Python Path:</strong> <?= h($pythonPath) ?><br>
        <strong>Config File:</strong> <?= h($configPath) ?>
    </div>

    <div class="card">
        <h3>Spooler Service</h3>
        <div class="<?= (stripos($spooler['output'], 'Running') !== false) ? 'ok' : 'bad' ?>">
            <?= (stripos($spooler['output'], 'Running') !== false) ? 'Running' : 'Not Running / Disabled' ?>
        </div>
        <pre><?= h($spooler['output']) ?></pre>
    </div>

    <div class="card">
        <h3>Installed Printers</h3>
        <pre><?= h($printers['output']) ?></pre>
    </div>

    <div class="card">
        <h3>Python Runtime Check</h3>
        <div class="<?= $pythonCheck['code'] === 0 ? 'ok' : 'bad' ?>">Exit Code: <?= h($pythonCheck['code']) ?></div>
        <pre><?= h($pythonCheck['output']) ?></pre>
    </div>

    <div class="card">
        <h3>python-escpos Check</h3>
        <div class="<?= (stripos($escposCheck['output'], 'escpos=True') !== false) ? 'ok' : 'bad' ?>">
            <?= (stripos($escposCheck['output'], 'escpos=True') !== false) ? 'escpos installed' : 'escpos missing' ?>
        </div>
        <pre><?= h($escposCheck['output']) ?></pre>
    </div>
</body>
</html>
