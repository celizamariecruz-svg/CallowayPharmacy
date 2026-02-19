<?php
// Prevent stray output from breaking JSON
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'db_connection.php';
require_once 'Auth.php';

$auth = new Auth($conn);

// API-style auth check: return JSON on failure, never redirect
if (!$auth->isLoggedIn()) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    ob_end_flush();
    exit;
}

ob_clean();
header('Content-Type: application/json');

$configPath = __DIR__ . DIRECTORY_SEPARATOR . 'pos_printer_config.php';
if (!file_exists($configPath)) {
    echo json_encode(['success' => false, 'message' => 'Printer config missing. Receipt saved digitally.']);
    exit;
}

$config = include $configPath;
$printerName = $config['printer_name'] ?? '';
$pythonPath = $config['python_path'] ?? 'python';

function sanitizePrinterName($name)
{
    if (!is_string($name)) {
        return '';
    }
    $name = trim($name);
    if ($name === '') {
        return '';
    }
    if (strlen($name) > 255) {
        return '';
    }
    // Allow / and () which appear in common Windows printer names like "Generic / Text Only"
    $sanitized = preg_replace('/[^a-zA-Z0-9\s_\-\.\:\/\(\)]/', '', $name);
    return trim((string)$sanitized);
}

function resolvePythonPath($configuredPath)
{
    $configuredPath = trim((string)$configuredPath);
    if ($configuredPath !== '' && strtolower($configuredPath) !== 'python') {
        return $configuredPath;
    }

    $venvPython = __DIR__ . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
    if (file_exists($venvPython)) {
        return $venvPython;
    }

    return 'python';
}

$printerName = sanitizePrinterName($printerName);
$pythonPath = resolvePythonPath($pythonPath);

if (empty($printerName) || $printerName === 'REPLACE_WITH_PRINTER_NAME') {
    // No printer configured — this is normal for dev environments
    echo json_encode(['success' => false, 'message' => 'No printer configured. Receipt saved digitally.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid receipt payload']);
    exit;
}

$tmpFile = tempnam(sys_get_temp_dir(), 'pos_receipt_');
file_put_contents($tmpFile, json_encode($data));

$scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'print_receipt_escpos.py';
if (!file_exists($scriptPath)) {
    @unlink($tmpFile);
    echo json_encode(['success' => false, 'message' => 'Print script missing']);
    exit;
}

// Run print script directly (cross-platform safe escaping)
$command = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($tmpFile) . ' ' . escapeshellarg($printerName) . ' 2>&1';
$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);
@unlink($tmpFile);

if ($exitCode !== 0) {
    $details = trim(implode("\n", $output));
    $friendly = 'Print failed. Check printer connection and configuration.';

    if (stripos($details, 'No module named') !== false && stripos($details, 'escpos') !== false) {
        $friendly = 'Python dependency missing: install python-escpos in the configured Python environment.';
    } elseif (stripos($details, 'The RPC server is unavailable') !== false || stripos($details, 'spooler') !== false) {
        $friendly = 'Windows Print Spooler is not running or unreachable. Start the Spooler service first.';
    } elseif (stripos($details, 'The printer name is invalid') !== false || stripos($details, 'WinError 1801') !== false) {
        $friendly = 'Configured printer name was not found. Update pos_printer_config.php with the exact Windows printer name.';
    }

    echo json_encode([
        'success' => false,
        'message' => $friendly,
        'details' => $details
    ]);
    exit;
}

echo json_encode(['success' => true]);
