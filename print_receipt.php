<?php
require_once 'db_connection.php';
require_once 'Auth.php';

$auth = new Auth($conn);
$auth->requireAuth('login.php');

header('Content-Type: application/json');

$configPath = __DIR__ . DIRECTORY_SEPARATOR . 'pos_printer_config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Printer config missing']);
    exit;
}

$config = include $configPath;
$printerName = $config['printer_name'] ?? '';
$pythonPath = $config['python_path'] ?? 'python';

if (empty($printerName) || $printerName === 'REPLACE_WITH_PRINTER_NAME') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Printer name not configured']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid receipt payload']);
    exit;
}

$tmpFile = tempnam(sys_get_temp_dir(), 'pos_receipt_');
file_put_contents($tmpFile, json_encode($data));

$scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'print_receipt_escpos.py';
if (!file_exists($scriptPath)) {
    @unlink($tmpFile);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Print script missing']);
    exit;
}

$command = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($tmpFile) . ' ' . escapeshellarg($printerName);
exec($command . ' 2>&1', $output, $exitCode);
@unlink($tmpFile);

if ($exitCode !== 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Print failed',
        'details' => implode("\n", $output)
    ]);
    exit;
}

echo json_encode(['success' => true]);
