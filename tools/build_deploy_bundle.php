<?php
require_once __DIR__ . '/../config.php';

function isExcludedPath(string $relativePath): bool
{
    $normalized = str_replace('\\', '/', $relativePath);
    $parts = explode('/', $normalized);
    $topLevel = $parts[0] ?? '';

    $excludedTopLevelDirs = [
        '.git', '.vscode', '.venv',
        '_archive_dev', '_deploy_bundle',
        'backups', 'cache', 'logs',
    ];

    if (in_array($topLevel, $excludedTopLevelDirs, true)) {
        return true;
    }

    // Exclude most docs and analysis artifacts from deploy package.
    $base = basename($normalized);
    $excludedExtensions = ['md', 'html', 'txt', 'log'];
    $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    if (in_array($ext, $excludedExtensions, true)) {
        return true;
    }

    // Exclude debug/test/check/fix scripts from deployment package.
    $lower = strtolower($base);
    if (
        strpos($lower, 'debug_') === 0 ||
        strpos($lower, 'test_') === 0 ||
        strpos($lower, 'check_') === 0 ||
        strpos($lower, 'fix_') === 0 ||
        strpos($lower, 'tmp_') === 0 ||
        str_ends_with($lower, '.backup') ||
        str_ends_with($lower, '.broken')
    ) {
        return true;
    }

    // Keep only defense tools from tools folder.
    if (strpos($normalized, 'tools/') === 0) {
        $allowedTools = [
            'tools/defense_backup.php',
            'tools/defense_restore.php',
            'tools/defense_preflight.php',
            'tools/build_deploy_bundle.php',
        ];
        return !in_array($normalized, $allowedTools, true);
    }

    return false;
}

function ensureDir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

$sourceRoot = realpath(__DIR__ . '/..');
if ($sourceRoot === false) {
    echo "Source root not found.\n";
    exit(1);
}

$timestamp = date('Ymd_His');
$bundleRoot = $sourceRoot . '/_deploy_bundle/' . $timestamp . '/CALLOWAYBACKUP';
ensureDir($bundleRoot);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$copied = 0;
$skipped = 0;
$bytes = 0;

foreach ($iterator as $item) {
    $fullPath = $item->getPathname();
    $relativePath = ltrim(str_replace(str_replace('\\', '/', $sourceRoot), '', str_replace('\\', '/', $fullPath)), '/');

    if ($relativePath === '') {
        continue;
    }

    if (isExcludedPath($relativePath)) {
        $skipped++;
        continue;
    }

    $targetPath = $bundleRoot . '/' . $relativePath;

    if ($item->isDir()) {
        ensureDir($targetPath);
        continue;
    }

    ensureDir(dirname($targetPath));
    if (copy($fullPath, $targetPath)) {
        $copied++;
        $bytes += filesize($fullPath);
    } else {
        echo "Failed to copy: {$relativePath}\n";
    }
}

$sizeMb = round($bytes / 1024 / 1024, 2);

echo "Deploy bundle created.\n";
echo "Path: {$bundleRoot}\n";
echo "Files copied: {$copied}\n";
echo "Entries skipped: {$skipped}\n";
echo "Total size: {$sizeMb} MB\n";
