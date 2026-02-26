<?php
require_once 'db_connection.php';

function renderPage($title, $message, $color = '#1d4ed8') {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</title><style>body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px}.card{max-width:560px;width:100%;background:#1e293b;border-radius:14px;padding:24px;box-shadow:0 20px 50px rgba(0,0,0,.35)}h1{margin:0 0 12px;font-size:1.5rem;color:'
        . $color
        . '}p{line-height:1.6;color:#cbd5e1}small{display:block;margin-top:14px;color:#94a3b8}</style></head><body><div class="card"><h1>'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</h1><p>' . $message . '</p><small>You may close this page now.</small></div></body></html>';
}

try {
    $action = strtolower(trim((string)($_GET['action'] ?? '')));
    $token = trim((string)($_GET['token'] ?? ''));

    if (!in_array($action, ['approve', 'deny'], true) || $token === '') {
        renderPage('Invalid Link', 'This approval link is invalid or incomplete.', '#dc2626');
        exit;
    }

    $column = ($action === 'approve') ? 'approve_token' : 'deny_token';

    $stmt = $conn->prepare("SELECT request_id, request_token, file_name, requested_by_name, status, expires_at FROM backup_download_requests WHERE {$column} = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request) {
        renderPage('Request Not Found', 'This request does not exist or was already handled.', '#dc2626');
        exit;
    }

    if (strtotime($request['expires_at']) < time()) {
        $stmt = $conn->prepare("UPDATE backup_download_requests SET status = 'expired' WHERE request_id = ?");
        $stmt->bind_param('i', $request['request_id']);
        $stmt->execute();
        $stmt->close();

        renderPage('Request Expired', 'This download request already expired.', '#dc2626');
        exit;
    }

    if ($request['status'] !== 'pending') {
        $existing = strtoupper($request['status']);
        renderPage('Already Processed', 'This request is already marked as <strong>' . htmlspecialchars($existing, ENT_QUOTES, 'UTF-8') . '</strong>.', '#f59e0b');
        exit;
    }

    $newStatus = ($action === 'approve') ? 'approved' : 'denied';
    $ownerActionIp = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);
    $decidedAt = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("UPDATE backup_download_requests SET status = ?, decided_at = ?, owner_action_ip = ? WHERE request_id = ? AND status = 'pending'");
    $stmt->bind_param('sssi', $newStatus, $decidedAt, $ownerActionIp, $request['request_id']);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        renderPage('Request Not Updated', 'The request could not be updated. It may have already been processed.', '#dc2626');
        exit;
    }

    if ($newStatus === 'approved') {
        renderPage(
            'Download Approved',
            'You approved backup file <strong>' . htmlspecialchars($request['file_name'], ENT_QUOTES, 'UTF-8')
            . '</strong> requested by <strong>' . htmlspecialchars($request['requested_by_name'], ENT_QUOTES, 'UTF-8')
            . '</strong>. The requester can now download the file.',
            '#16a34a'
        );
    } else {
        renderPage(
            'Download Denied',
            'You denied backup file <strong>' . htmlspecialchars($request['file_name'], ENT_QUOTES, 'UTF-8')
            . '</strong> requested by <strong>' . htmlspecialchars($request['requested_by_name'], ENT_QUOTES, 'UTF-8')
            . '</strong>. The download is blocked.',
            '#dc2626'
        );
    }
} catch (Throwable $e) {
    renderPage('System Error', 'An error occurred while processing the request: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), '#dc2626');
}
