<?php
require_once __DIR__ . '/../db_connection.php';

$username = 'pharmacycalloway@gmail.com';
$values = [
    'email_username' => $username,
    'smtp_username' => $username,
    'email_from_address' => $username,
    'smtp_from_email' => $username,
    'email_host' => 'smtp.gmail.com',
    'smtp_host' => 'smtp.gmail.com',
    'email_port' => '465',
    'smtp_port' => '465',
    'email_encryption' => 'ssl',
];

$stmt = $conn->prepare(
    "INSERT INTO settings (setting_key, setting_value, category) VALUES (?, ?, 'email')
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
);

if (!$stmt) {
    echo "Prepare failed: " . $conn->error . PHP_EOL;
    exit(1);
}

foreach ($values as $key => $value) {
    $stmt->bind_param('ss', $key, $value);
    if (!$stmt->execute()) {
        echo "Failed {$key}: " . $stmt->error . PHP_EOL;
        exit(1);
    }
}

echo "SMTP identity defaults repaired." . PHP_EOL;
