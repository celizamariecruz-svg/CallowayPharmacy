<?php
/**
 * Password Reset Page
 * Accepts a token from the email link and lets the user set a new password.
 */

require_once 'db_connection.php';
require_once 'Security.php';

Security::initSession();

$token   = trim($_GET['token'] ?? '');
$message = '';
$validToken = false;
$username = '';

// ── Validate token on page load ──
if (!empty($token)) {
    $stmt = $conn->prepare(
        "SELECT user_id, username FROM users
         WHERE password_reset_token = ?
           AND password_reset_expires > NOW()
           AND is_active = 1
         LIMIT 1"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        $validToken = true;
        $username   = $user['username'];
    }
}

// ── Handle new-password form submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['token'])) {
    $postToken   = trim($_POST['token']);
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPw   = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $message = '<div class="msg error">Password must be at least 6 characters.</div>';
    } elseif ($newPassword !== $confirmPw) {
        $message = '<div class="msg error">Passwords do not match.</div>';
    } else {
        // Re-validate token
        $stmt = $conn->prepare(
            "SELECT user_id, username FROM users
             WHERE password_reset_token = ?
               AND password_reset_expires > NOW()
               AND is_active = 1
             LIMIT 1"
        );
        $stmt->bind_param("s", $postToken);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            $message = '<div class="msg error">This reset link has expired or is invalid. Please request a new one.</div>';
        } else {
            // Update password & clear token
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $conn->prepare(
                "UPDATE users
                 SET password_hash = ?,
                     password_reset_token = NULL,
                     password_reset_expires = NULL
                 WHERE user_id = ?"
            );
            $stmt->bind_param("si", $hash, $user['user_id']);
            $stmt->execute();

            $message = '<div class="msg success">Your password has been reset successfully! You can now <a href="login.php">log in</a>.</div>';
            $validToken = false; // hide form
            $token = ''; // prevent reuse
        }
    }

    // Keep form visible on validation errors
    if ($validToken === false && strpos($message, 'error') !== false) {
        // Re-check if token is still valid for the form
        $stmt = $conn->prepare(
            "SELECT user_id, username FROM users
             WHERE password_reset_token = ?
               AND password_reset_expires > NOW()
             LIMIT 1"
        );
        $stmt->bind_param("s", $postToken);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        if ($u) {
            $validToken = true;
            $username   = $u['username'];
            $token      = $postToken;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Calloway Pharmacy</title>
    <link rel="icon" type="image/png" href="logo-removebg-preview.png">
    <link rel="shortcut icon" type="image/png" href="logo-removebg-preview.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e0ecff 0%, #f0f4ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            max-width: 440px;
            width: 100%;
            padding: 2.5rem;
            text-align: center;
        }
        .card img.logo { height: 80px; margin-bottom: 1rem; }
        .card h1 { font-size: 1.5rem; color: #1e3a5f; margin-bottom: 0.3rem; }
        .card p.subtitle { color: #64748b; font-size: 0.95rem; margin-bottom: 1.5rem; }
        .form-group {
            text-align: left;
            margin-bottom: 1.1rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.35rem;
            font-size: 0.9rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }
        button.submit-btn {
            width: 100%;
            padding: 0.85rem;
            background: #2563eb;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: background 0.2s;
        }
        button.submit-btn:hover { background: #1d4ed8; }
        .msg {
            padding: 0.9rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            font-size: 0.93rem;
            text-align: left;
        }
        .msg.success { background: #dcfce7; color: #166534; }
        .msg.error   { background: #fee2e2; color: #991b1b; }
        .msg a { color: #2563eb; font-weight: 600; }
        .back-link {
            display: inline-block;
            margin-top: 1.2rem;
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.93rem;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <img src="logo.png" alt="Calloway Pharmacy" class="logo">
        <h1>Reset Your Password</h1>

        <?php echo $message; ?>

        <?php if ($validToken): ?>
            <p class="subtitle">Hi <strong><?php echo htmlspecialchars($username); ?></strong>, enter your new password below.</p>
            <form method="POST" action="reset_password.php">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="Min 6 characters">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Re-enter password">
                </div>
                <button type="submit" class="submit-btn">Set New Password</button>
            </form>
        <?php elseif (empty($message)): ?>
            <div class="msg error">
                This password reset link is invalid or has expired.<br>
                Please request a new one from the login page.
            </div>
        <?php endif; ?>

        <a href="login.php" class="back-link">&larr; Back to Login</a>
    </div>
</body>
</html>
