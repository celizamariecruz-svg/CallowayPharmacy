<?php
/**
 * Secure Backup Download
 * Download is only allowed after owner email approval.
 */

require_once 'db_connection.php';
require_once 'Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth($conn);
$auth->requireAuth('login.php');

if (!$auth->hasPermission('settings.backup')) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$requestToken = trim((string)($_GET['request_token'] ?? ''));
if ($requestToken === '') {
    http_response_code(400);
    echo 'Missing request token.';
    exit;
}

$userId = intval($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo 'Invalid session user.';
    exit;
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("SELECT request_id, file_name, status, expires_at FROM backup_download_requests WHERE request_token = ? AND requested_by_user_id = ? LIMIT 1 FOR UPDATE");
    $stmt->bind_param('si', $requestToken, $userId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request) {
        $conn->rollback();
        http_response_code(404);
        echo 'Download request not found.';
        exit;
    }

    if (strtotime($request['expires_at']) < time()) {
        $stmt = $conn->prepare("UPDATE backup_download_requests SET status = 'expired' WHERE request_id = ? AND status = 'pending'");
        $stmt->bind_param('i', $request['request_id']);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        http_response_code(403);
        echo 'Download request expired.';
        exit;
    }

    if ($request['status'] !== 'approved') {
        $conn->rollback();
        http_response_code(403);
        echo 'Download not approved yet.';
        exit;
    }

    $stmt = $conn->prepare("UPDATE backup_download_requests SET status = 'completed', completed_at = NOW() WHERE request_id = ? AND status = 'approved'");
    $stmt->bind_param('i', $request['request_id']);
    $stmt->execute();
    $updated = $stmt->affected_rows;
    $stmt->close();

    if ($updated <= 0) {
        $conn->rollback();
        http_response_code(409);
        echo 'Download request already used or no longer approved.';
        exit;
    }

    $conn->commit();

    $file = basename((string)$request['file_name']);
    if ($file === '' || pathinfo($file, PATHINFO_EXTENSION) !== 'sql') {
        http_response_code(400);
        echo 'Invalid backup file.';
        exit;
    }

    $backupDir = __DIR__ . '/backups';
    $basePath = realpath($backupDir);
    $targetPath = realpath($backupDir . '/' . $file);
    if ($basePath === false || $targetPath === false || strpos($targetPath, $basePath) !== 0 || !is_file($targetPath)) {
        http_response_code(404);
        echo 'Backup file not found.';
        exit;
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($targetPath) . '"');
    header('Content-Length: ' . filesize($targetPath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: public');

    readfile($targetPath);
    exit;
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignore) {
    }
    http_response_code(500);
    echo 'Download failed: ' . $e->getMessage();
    exit;
}
