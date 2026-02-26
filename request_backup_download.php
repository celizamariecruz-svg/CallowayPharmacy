<?php
header('Content-Type: application/json');

require_once 'db_connection.php';
require_once 'Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth($conn);
$auth->requireAuth('login.php');

if (!$auth->hasPermission('settings.backup')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

function ensureBackupDownloadRequestTable($conn) {
    $sql = "
        CREATE TABLE IF NOT EXISTS backup_download_requests (
            request_id INT AUTO_INCREMENT PRIMARY KEY,
            request_token VARCHAR(80) NOT NULL UNIQUE,
            approve_token VARCHAR(80) NOT NULL UNIQUE,
            deny_token VARCHAR(80) NOT NULL UNIQUE,
            file_name VARCHAR(255) NOT NULL,
            requested_by_user_id INT NOT NULL,
            requested_by_name VARCHAR(255) NOT NULL,
            requested_by_role VARCHAR(100) DEFAULT 'unknown',
            requester_ip VARCHAR(64) DEFAULT NULL,
            requester_user_agent VARCHAR(255) DEFAULT NULL,
            status ENUM('pending','approved','denied','expired','completed') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            decided_at DATETIME NULL,
            completed_at DATETIME NULL,
            owner_action_ip VARCHAR(64) DEFAULT NULL,
            INDEX idx_request_token (request_token),
            INDEX idx_status_expires (status, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $conn->query($sql);
}

function getApprovalRecipients($conn) {
    $recipients = ['pharmacycalloway@gmail.com'];

    $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key IN ('alert_email','company_email','smtp_from_email','email_from_address')");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $email = strtolower(trim((string)($row['setting_value'] ?? '')));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $email;
            }
        }
    }

    return array_values(array_unique($recipients));
}

function getBaseUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($dir === '' || $dir === '.') {
        return $scheme . '://' . $host;
    }
    return $scheme . '://' . $host . $dir;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $file = basename((string)($input['file'] ?? ''));

    if ($file === '' || pathinfo($file, PATHINFO_EXTENSION) !== 'sql') {
        throw new Exception('Invalid backup file.');
    }

    $backupDir = __DIR__ . '/backups';
    $basePath = realpath($backupDir);
    $targetPath = realpath($backupDir . '/' . $file);
    if ($basePath === false || $targetPath === false || strpos($targetPath, $basePath) !== 0 || !is_file($targetPath)) {
        throw new Exception('Backup file not found.');
    }

    ensureBackupDownloadRequestTable($conn);

    $requestToken = bin2hex(random_bytes(24));
    $approveToken = bin2hex(random_bytes(24));
    $denyToken = bin2hex(random_bytes(24));

    $userId = intval($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new Exception('Invalid session user.');
    }

    $requestedBy = (string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown User');
    $role = (string)($_SESSION['role_name'] ?? 'unknown');
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);

    $createdAt = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    $stmt = $conn->prepare("INSERT INTO backup_download_requests (request_token, approve_token, deny_token, file_name, requested_by_user_id, requested_by_name, requested_by_role, requester_ip, requester_user_agent, status, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
    $stmt->bind_param('ssssissssss', $requestToken, $approveToken, $denyToken, $file, $userId, $requestedBy, $role, $ip, $ua, $createdAt, $expiresAt);
    $stmt->execute();
    $stmt->close();

    $baseUrl = getBaseUrl();
    $approveUrl = $baseUrl . '/backup_download_action.php?action=approve&token=' . urlencode($approveToken);
    $denyUrl = $baseUrl . '/backup_download_action.php?action=deny&token=' . urlencode($denyToken);

    $subject = 'Backup Download Approval Needed - Calloway Pharmacy';
    $body = '<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;">'
        . '<h2 style="color:#1e3a5f;">Backup Download Approval Required</h2>'
        . '<p>A staff member requested a backup download. Please review and choose an action:</p>'
        . '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:0.9rem;">'
        . '<tr><th align="left" style="background:#f1f5f9;">Requested By</th><td>' . htmlspecialchars($requestedBy, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><th align="left" style="background:#f1f5f9;">Role</th><td>' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><th align="left" style="background:#f1f5f9;">File</th><td>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><th align="left" style="background:#f1f5f9;">IP Address</th><td>' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><th align="left" style="background:#f1f5f9;">Requested At</th><td>' . htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><th align="left" style="background:#f1f5f9;">Expires</th><td>' . htmlspecialchars($expiresAt, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '</table>'
        . '<div style="margin:24px 0;display:flex;gap:12px;">'
        . '<a href="' . htmlspecialchars($approveUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 18px;background:#16a34a;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;">ALLOW DOWNLOAD</a>'
        . '<a href="' . htmlspecialchars($denyUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 18px;background:#dc2626;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;">DENY DOWNLOAD</a>'
        . '</div>'
        . '<p style="font-size:0.84rem;color:#64748b;">These links are one-time decision links for this request and expire in 1 hour.</p>'
        . '</div>';

    $sentCount = 0;
    try {
        require_once 'email_service.php';
        $mailer = new EmailService($conn);
        foreach (getApprovalRecipients($conn) as $recipient) {
            if ($mailer->sendCustomEmail($recipient, $subject, $body)) {
                $sentCount++;
            }
        }
    } catch (Throwable $mailErr) {
        error_log('Backup approval request email error: ' . $mailErr->getMessage());
    }

    if ($sentCount <= 0) {
        $stmt = $conn->prepare("UPDATE backup_download_requests SET status = 'expired' WHERE request_token = ?");
        $stmt->bind_param('s', $requestToken);
        $stmt->execute();
        $stmt->close();
        throw new Exception('Could not send approval email to owner. Download request cancelled.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Approval request sent to owner. Waiting for decision...',
        'request_token' => $requestToken
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
