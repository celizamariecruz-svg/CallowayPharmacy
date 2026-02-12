<?php
/**
 * Automatic Backup Cron Job
 * Run this script daily via cron or task scheduler
 * 
 * Cron example: 0 0 * * * cd /path/to/project && php backup_cron.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('CRON_ALLOWED')) {
    die('This script can only be run from command line');
}

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/BackupManager.php';

// Create automatic backup
$result = BackupManager::automaticBackup();

// Log result
$log_message = date('Y-m-d H:i:s') . ' - ';
$log_message .= $result['success'] ? 'SUCCESS: ' : 'FAILED: ';
$log_message .= $result['message'] . "\n";

// Write to log file
file_put_contents(__DIR__ . '/backup_cron.log', $log_message, FILE_APPEND);

// Output for cron email notification
echo $log_message;

// Exit with appropriate code
exit($result['success'] ? 0 : 1);
