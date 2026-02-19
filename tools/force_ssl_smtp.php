<?php
require_once __DIR__ . '/../db_connection.php';

$pairs = [
    'email_host' => 'smtp.gmail.com',
    'email_port' => '465',
    'email_encryption' => 'ssl',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => '465',
];

$stmt = $conn->prepare(
    "INSERT INTO settings (setting_key, setting_value, category) VALUES (?, ?, 'email')
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
);

if (!$stmt) {
    echo "Failed to prepare statement: " . $conn->error . PHP_EOL;
    exit(1);
}

foreach ($pairs as $key => $value) {
    $stmt->bind_param('ss', $key, $value);
    if (!$stmt->execute()) {
        echo "Failed to update {$key}: " . $stmt->error . PHP_EOL;
        exit(1);
    }
}

echo "SMTP settings forced to SSL/465 successfully." . PHP_EOL;
