<?php
/**
 * List Backups API
 * Returns list of available backup files
 */

$backupDir = __DIR__ . '/backups/';
$backups = [];

if (is_dir($backupDir)) {
    $files = scandir($backupDir, SCANDIR_SORT_DESCENDING);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'sql') continue;
        
        $filePath = $backupDir . $file;
        $fileSize = filesize($filePath);
        $fileTime = filemtime($filePath);
        
        // Format file size
        if ($fileSize < 1024) {
            $size = $fileSize . ' B';
        } elseif ($fileSize < 1048576) {
            $size = round($fileSize / 1024, 2) . ' KB';
        } else {
            $size = round($fileSize / 1048576, 2) . ' MB';
        }
        
        $backups[] = [
            'name' => $file,
            'size' => $size,
            'date' => date('Y-m-d H:i:s', $fileTime),
            'timestamp' => $fileTime
        ];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'backups' => $backups,
    'count' => count($backups)
]);
?>
