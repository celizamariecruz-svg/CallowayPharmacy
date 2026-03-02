<?php
/**
 * Create Demo / Survey User
 * -------------------------
 * Creates a read-only "Demo Viewer" role and a demo account.
 * Run this once from your browser, then DELETE or rename this file.
 *
 * Default credentials:
 *   Username : demo
 *   Password : Demo@1234
 */

// ──────────────────────────────────────────────
// Security gate – only allow local / CLI access
// ──────────────────────────────────────────────
$allowed_ips = ['127.0.0.1', '::1'];
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed_ips)) {
    http_response_code(403);
    die('<h2>403 – Forbidden.</h2><p>This setup script can only be run from localhost.</p>');
}

require_once 'db_connection.php';

// ──────────────────────────────────────────────
// Configuration
// ──────────────────────────────────────────────
$DEMO_USERNAME  = 'demo';
$DEMO_PASSWORD  = 'Demo@1234';
$DEMO_EMAIL     = 'demo@calloway.local';
$DEMO_FULLNAME  = 'Demo Viewer';
$DEMO_ROLE_NAME = 'demo viewer';  // must match the check in header-component.php

$log = [];
$ok  = true;

$step = function (string $msg, bool $success = true) use (&$log, &$ok) {
    $icon = $success ? '✅' : '❌';
    $log[] = "$icon $msg";
    if (!$success) $ok = false;
};

// ──────────────────────────────────────────────
// 1. Ensure roles table exists
// ──────────────────────────────────────────────
$tableCheck = $conn->query("SHOW TABLES LIKE 'roles'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    $conn->query("
        CREATE TABLE roles (
            role_id     INT PRIMARY KEY AUTO_INCREMENT,
            role_name   VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $step('Created roles table');
} else {
    $step('Roles table already exists');
}

// ──────────────────────────────────────────────
// 2. Create Demo Viewer role (if not exists)
// ──────────────────────────────────────────────
$roleStmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ? LIMIT 1");
$roleStmt->bind_param("s", $DEMO_ROLE_NAME);
$roleStmt->execute();
$roleResult = $roleStmt->get_result();

if ($roleResult->num_rows === 0) {
    $insertRole = $conn->prepare("INSERT INTO roles (role_name, description) VALUES (?, 'Read-only demo account for user surveys and testing')");
    $insertRole->bind_param("s", $DEMO_ROLE_NAME);
    if ($insertRole->execute()) {
        $demo_role_id = $conn->insert_id;
        $step("Created role: $DEMO_ROLE_NAME (id=$demo_role_id)");
    } else {
        $step("Failed to create role: " . $conn->error, false);
    }
} else {
    $demo_role_id = $roleResult->fetch_assoc()['role_id'];
    $step("Role '$DEMO_ROLE_NAME' already exists (id=$demo_role_id)");
}

// ──────────────────────────────────────────────
// 3. Assign VIEW-ONLY permissions to Demo role
//    (only permissions whose name starts with 'view_' or 'read_')
// ──────────────────────────────────────────────
$permTable = $conn->query("SHOW TABLES LIKE 'permissions'");
if ($permTable && $permTable->num_rows > 0) {
    // Remove old demo permissions first (clean slate)
    $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?")->bind_param("i", $demo_role_id);
    $conn->query("DELETE FROM role_permissions WHERE role_id = $demo_role_id");

    $viewPerms = $conn->query("
        SELECT permission_id, permission_name FROM permissions
        WHERE permission_name LIKE 'view_%'
           OR permission_name LIKE 'read_%'
    ");

    $assigned = 0;
    if ($viewPerms && $viewPerms->num_rows > 0) {
        $insP = $conn->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        while ($p = $viewPerms->fetch_assoc()) {
            $insP->bind_param("ii", $demo_role_id, $p['permission_id']);
            $insP->execute();
            $assigned++;
        }
    }
    $step("Assigned $assigned view-only permissions to demo role");
} else {
    $step("No permissions table found – demo role has no explicit permissions (write-block is handled in header)", true);
}

// ──────────────────────────────────────────────
// 4. Ensure users table exists
// ──────────────────────────────────────────────
$usersTable = $conn->query("SHOW TABLES LIKE 'users'");
if (!$usersTable || $usersTable->num_rows === 0) {
    $step("ERROR: users table does not exist. Run database setup first.", false);
}

// ──────────────────────────────────────────────
// 5. Create (or update) the demo user
// ──────────────────────────────────────────────
if ($ok) {
    $password_hash = password_hash($DEMO_PASSWORD, PASSWORD_BCRYPT, ['cost' => 12]);

    $existStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $existStmt->bind_param("ss", $DEMO_USERNAME, $DEMO_EMAIL);
    $existStmt->execute();
    $existResult = $existStmt->get_result();

    if ($existResult->num_rows === 0) {
        // Insert
        $insUser = $conn->prepare("
            INSERT INTO users (username, email, password_hash, full_name, role_id, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $insUser->bind_param("ssssi", $DEMO_USERNAME, $DEMO_EMAIL, $password_hash, $DEMO_FULLNAME, $demo_role_id);
        if ($insUser->execute()) {
            $step("Created demo user '$DEMO_USERNAME' (user_id=" . $conn->insert_id . ")");
        } else {
            $step("Failed to create user: " . $conn->error, false);
        }
    } else {
        // Update existing (reset password + role in case they changed)
        $demo_user_id = $existResult->fetch_assoc()['user_id'];
        $updUser = $conn->prepare("
            UPDATE users SET password_hash=?, role_id=?, is_active=1, full_name=?
            WHERE user_id=?
        ");
        $updUser->bind_param("siis", $password_hash, $demo_role_id, $DEMO_FULLNAME, $demo_user_id);
        if ($updUser->execute()) {
            $step("Updated existing demo user '$DEMO_USERNAME'");
        } else {
            $step("Failed to update user: " . $conn->error, false);
        }
    }
}

// ──────────────────────────────────────────────
// Output
// ──────────────────────────────────────────────
$statusColor = $ok ? '#16a34a' : '#dc2626';
$statusText  = $ok ? 'Setup Complete' : 'Setup Failed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Demo User Setup</title>
<style>
  body { font-family: system-ui, sans-serif; background: #f1f5f9; display: flex; justify-content: center; align-items: flex-start; padding: 3rem 1rem; min-height: 100vh; margin: 0; }
  .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2rem 2.5rem; max-width: 520px; width: 100%; }
  h1 { margin: 0 0 .25rem; font-size: 1.4rem; color: #0f172a; }
  .status { display: inline-block; margin-bottom: 1.5rem; padding: .3rem .9rem; border-radius: 999px; font-weight: 700; font-size: .85rem; color: #fff; background: <?= $statusColor ?>; }
  .log { list-style: none; padding: 0; margin: 0 0 1.5rem; }
  .log li { padding: .35rem 0; border-bottom: 1px solid #f1f5f9; font-size: .9rem; }
  .creds { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; }
  .creds h3 { margin: 0 0 .5rem; font-size: .95rem; color: #475569; }
  .cred-row { display: flex; justify-content: space-between; margin: .25rem 0; font-size: .9rem; }
  .cred-row strong { color: #1e293b; }
  .cred-row code { background: #e0e7ff; color: #3730a3; padding: .1rem .45rem; border-radius: 4px; font-size: .88rem; }
  .warning { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: .85rem 1rem; font-size: .85rem; color: #92400e; margin-bottom: .5rem; }
  .warning strong { display: block; margin-bottom: .25rem; }
</style>
</head>
<body>
<div class="card">
  <h1>🔒 Demo User Setup</h1>
  <span class="status"><?= htmlspecialchars($statusText) ?></span>

  <ul class="log">
    <?php foreach ($log as $line): ?>
      <li><?= htmlspecialchars($line) ?></li>
    <?php endforeach; ?>
  </ul>

  <?php if ($ok): ?>
  <div class="creds">
    <h3>Demo Account Credentials</h3>
    <div class="cred-row"><strong>Username</strong> <code><?= htmlspecialchars($DEMO_USERNAME) ?></code></div>
    <div class="cred-row"><strong>Password</strong> <code><?= htmlspecialchars($DEMO_PASSWORD) ?></code></div>
    <div class="cred-row"><strong>Role</strong> <code><?= htmlspecialchars($DEMO_ROLE_NAME) ?></code></div>
  </div>

  <div class="warning">
    <strong>⚠️ Security: Delete this file when done!</strong>
    This setup script should be removed or renamed after use so it cannot be re-run by others.
    <br><br>
    Run once, share the credentials above with your survey participants, then delete <code>create_demo_user.php</code>.
  </div>
  <?php endif; ?>
</div>
</body>
</html>
