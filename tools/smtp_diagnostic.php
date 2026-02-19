<?php
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../email_service.php';

header('Content-Type: text/plain');

echo "SMTP Diagnostic\n";
echo "================\n";

try {
    $svc = new EmailService($conn);

    $q = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('email_host','email_port','email_username','email_from_address','email_encryption','smtp_host','smtp_port','smtp_username') ORDER BY setting_key");
    while ($q && $row = $q->fetch_assoc()) {
        echo $row['setting_key'] . '=' . $row['setting_value'] . "\n";
    }

    $to = $_GET['to'] ?? ($argv[1] ?? '');
    if ($to === '') {
        echo "\nNo test recipient provided. Append ?to=you@example.com to run live send.\n";
        exit(0);
    }

    $ok = $svc->sendTestEmail($to);
    if ($ok) {
        echo "\nSEND RESULT: SUCCESS\n";
    } else {
        echo "\nSEND RESULT: FAILED\n";
        echo "ERROR: " . $svc->getLastError() . "\n";
    }
} catch (Throwable $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
}
