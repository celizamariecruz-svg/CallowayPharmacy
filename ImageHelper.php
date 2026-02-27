<?php

function normalizeProductImageUrl(string $url): string
{
    $url = trim($url);
    if ($url === '' || stripos($url, 'data:') === 0) {
        return $url;
    }

    $url = str_replace('\\', '/', $url);
    $url = preg_replace('#/+#', '/', $url);

    if (preg_match('#^(https?:)?//#i', $url)) {
        return $url;
    }

    if (defined('BASE_URL')) {
        $basePath = trim((string)BASE_URL);
        if ($basePath !== '' && $basePath !== '/') {
            $basePath = '/' . trim($basePath, '/');
            if (stripos($url, $basePath . '/') === 0) {
                $url = ltrim(substr($url, strlen($basePath)), '/');
            }
        }
    }

    return ltrim($url, '/');
}

function sanitizeMedicineImageFolderName(string $name): string
{
    $cleaned = preg_replace('/[<>:"\/\\|?*\x00-\x1f]/', '_', $name);
    $cleaned = preg_replace('/\s+/', ' ', (string)$cleaned);
    $cleaned = trim((string)$cleaned, ' .');
    if ($cleaned === '') {
        return 'unnamed_medicine';
    }
    if (strlen($cleaned) > 120) {
        $cleaned = substr($cleaned, 0, 120);
        $cleaned = rtrim($cleaned, ' .');
    }
    return $cleaned;
}

function productImageFileExists(string $normalizedUrl): bool
{
    if ($normalizedUrl === '' || preg_match('#^(https?:)?//#i', $normalizedUrl) || stripos($normalizedUrl, 'data:') === 0) {
        return $normalizedUrl !== '';
    }

    $decoded = rawurldecode($normalizedUrl);
    $fullPath = __DIR__ . '/' . ltrim($decoded, '/');
    return is_file($fullPath);
}

function firstImageInMedicineFolder(string $productName): string
{
    static $folderCache = null;

    if ($folderCache === null) {
        $folderCache = [];
        $baseDir = __DIR__ . '/medicine_images';
        if (is_dir($baseDir)) {
            $folders = scandir($baseDir);
            if (is_array($folders)) {
                foreach ($folders as $folder) {
                    if ($folder === '.' || $folder === '..') {
                        continue;
                    }
                    $folderPath = $baseDir . '/' . $folder;
                    if (!is_dir($folderPath)) {
                        continue;
                    }

                    $files = scandir($folderPath);
                    if (!is_array($files)) {
                        continue;
                    }

                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') {
                            continue;
                        }
                        if (!preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $file)) {
                            continue;
                        }
                        if (!is_file($folderPath . '/' . $file)) {
                            continue;
                        }

                        $folderCache[$folder] = 'medicine_images/' . rawurlencode($folder) . '/' . rawurlencode($file);
                        break;
                    }
                }
            }
        }
    }

    $folder = sanitizeMedicineImageFolderName($productName);
    return $folderCache[$folder] ?? '';
}

function resolveProductImageUrl(string $currentImageUrl, string $productName = ''): string
{
    $normalized = normalizeProductImageUrl($currentImageUrl);
    if (productImageFileExists($normalized)) {
        return $normalized;
    }

    if ($productName !== '') {
        $fallback = firstImageInMedicineFolder($productName);
        if ($fallback !== '') {
            return $fallback;
        }
    }

    return $normalized;
}
