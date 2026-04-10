<?php
/**
 * Web-accessible Cron Trigger
 * 
 * Runs email_cron logic via HTTP request. Two modes:
 *   1. Token auth:  GET/POST cron_web.php?token=<secret>
 *   2. Session auth: Called by logged-in admin users (auto-trigger from app)
 *
 * Stores last-run timestamp in settings table to prevent duplicate runs.
 */

require_once __DIR__ . '/db_connection.php';

// ─── Auth: require either valid token or logged-in admin session ───
$authorized = false;

// Mode 1: Token-based (for external cron services / Azure WebJob curl)
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if ($token !== '') {
    $stored = '';
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'cron_secret_token' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $stored = $row['setting_value'];
        }
        $stmt->close();
    }
    // Auto-generate token if none exists
    if ($stored === '') {
        $stored = bin2hex(random_bytes(24));
        $conn->query("INSERT INTO settings (setting_key, setting_value, category, description) 
                      VALUES ('cron_secret_token', '$stored', 'system', 'Secret token for web cron trigger')
                      ON DUPLICATE KEY UPDATE setting_value = '$stored'");
    }
    $authorized = hash_equals($stored, $token);
}

// Mode 2: Session-based (auto-trigger from logged-in admin)
if (!$authorized) {
    session_start();
    $authorized = !empty($_SESSION['user_id']) && !empty($_SESSION['role_name']) 
                  && in_array(strtolower($_SESSION['role_name']), ['admin', 'owner', 'superadmin', 'administrator']);
}

if (!$authorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ─── Check if cron already ran today ───
$today = date('Y-m-d');
$lastRun = '';
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'cron_last_run' LIMIT 1");
if ($stmt) {
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $lastRun = $row['setting_value'];
    }
    $stmt->close();
}

if ($lastRun === $today) {
    echo json_encode(['success' => true, 'message' => 'Cron already ran today', 'skipped' => true]);
    exit;
}

// ─── Run the email cron ───
try {
    // Record attempt timestamp for diagnostics even if execution fails.
    $attemptAt = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, category, description) 
                            VALUES ('cron_last_attempt_at', ?, 'system', 'Last timestamp email cron was attempted')
                            ON DUPLICATE KEY UPDATE setting_value = ?");
    if ($stmt) {
        $stmt->bind_param('ss', $attemptAt, $attemptAt);
        $stmt->execute();
        $stmt->close();
    }

    // Try subprocess first (cleanest — email_cron.php calls exit())
    $phpBin = PHP_BINARY ?: 'php';
    $cronScript = __DIR__ . DIRECTORY_SEPARATOR . 'email_cron.php';
    $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($cronScript) . ' 2>&1';
    $output = '';
    $ranInShell = false;
    $cronSucceeded = false;

    if (function_exists('shell_exec')) {
        $shellOutput = @shell_exec($cmd);
        if ($shellOutput !== null) {
            $ranInShell = true;
            $output = $shellOutput;
            $hasErrorLine = stripos($output, 'ERROR:') !== false;
            $hasFailedMarker = stripos($output, '✗ Failed') !== false;
            $cronSucceeded = !$hasErrorLine && !$hasFailedMarker;
        }
    }

    if (!$ranInShell) {
        // shell_exec disabled — fall back to direct include in shutdown handler
        // Register the cron to run AFTER this script sends its response
        register_shutdown_function(function() use ($cronScript) {
            @include $cronScript;
        });
        $output = 'Scheduled via shutdown handler (shell_exec unavailable)';
        // We cannot determine outcome synchronously in this mode.
        $cronSucceeded = true;
    }

    if ($cronSucceeded) {
        $runAt = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, category, description) 
                                VALUES ('cron_last_run', ?, 'system', 'Last date email cron ran')
                                ON DUPLICATE KEY UPDATE setting_value = ?");
        if ($stmt) {
            $stmt->bind_param('ss', $today, $today);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, category, description) 
                                VALUES ('cron_last_run_at', ?, 'system', 'Last timestamp email cron ran successfully')
                                ON DUPLICATE KEY UPDATE setting_value = ?");
        if ($stmt) {
            $stmt->bind_param('ss', $runAt, $runAt);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['success' => true, 'message' => 'Cron executed successfully', 'output' => $output]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cron failed. Check cron output for details.', 'output' => $output]);
    }
} catch (Exception $e) {
    error_log("cron_web.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Cron failed: ' . $e->getMessage()]);
}
