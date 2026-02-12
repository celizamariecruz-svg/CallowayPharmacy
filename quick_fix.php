<?php
/**
 * Quick Fix Script
 * Clears session and provides clean login link
 */

// Start session
session_start();

// Store if user was logged in
$wasLoggedIn = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? 'Unknown';

// Clear session completely
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy session
session_destroy();

// Start fresh session
session_start();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Reset - Calloway Pharmacy</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #2563eb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 50px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: rotate 2s ease-in-out infinite;
        }

        @keyframes rotate {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-10deg); }
            75% { transform: rotate(10deg); }
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 32px;
        }

        p {
            color: #555;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .status {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            text-align: left;
        }

        .status.info {
            background: #e3f2fd;
            border-left-color: #2196f3;
        }

        .status strong {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .credentials {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
        }

        .credentials h3 {
            color: #856404;
            margin-bottom: 15px;
        }

        .cred-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 5px;
            margin: 10px 0;
        }

        .cred-label {
            font-weight: bold;
            color: #666;
        }

        .cred-value {
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            padding: 5px 15px;
            border-radius: 5px;
            color: #d32f2f;
            font-weight: bold;
        }

        .btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 15px 40px;
            text-decoration: none;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.6);
        }

        .btn:active {
            transform: translateY(0);
        }

        .checkmark {
            color: #4caf50;
            font-size: 24px;
        }

        .note {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .steps {
            text-align: left;
            margin: 20px 0;
        }

        .steps ol {
            padding-left: 20px;
        }

        .steps li {
            margin: 10px 0;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">✨</div>
        <h1>Session Cleared Successfully!</h1>
        
        <div class="status">
            <strong><span class="checkmark">✓</span> Session Reset Complete</strong>
            <?php if ($wasLoggedIn): ?>
            <p>Previous user: <strong><?php echo htmlspecialchars($username); ?></strong></p>
            <?php endif; ?>
            <p>Your session has been completely cleared and is ready for a fresh login.</p>
        </div>

        <div class="status info">
            <strong>🔧 What was fixed:</strong>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>✓ Session data cleared</li>
                <li>✓ Session cookies removed</li>
                <li>✓ Fresh session started</li>
                <li>✓ Ready for new login with updated permissions</li>
            </ul>
        </div>

        <div class="credentials">
            <h3>🔑 Admin Login Credentials</h3>
            <div class="cred-item">
                <span class="cred-label">Username:</span>
                <span class="cred-value">admin</span>
            </div>
            <div class="cred-item">
                <span class="cred-label">Password:</span>
                <span class="cred-value">admin123</span>
            </div>
        </div>

        <div class="steps">
            <strong>📋 Next Steps:</strong>
            <ol>
                <li>Click the "Go to Login Page" button below</li>
                <li>Enter the credentials shown above</li>
                <li>After logging in, products will load correctly</li>
                <li>All features will be accessible</li>
            </ol>
        </div>

        <a href="login.php" class="btn">🚀 Go to Login Page</a>

        <div class="note">
            <strong>💡 Why was this needed?</strong><br>
            We updated the permission system to give admin users full access. Your old session didn't have the required role information. This fresh login will set everything up correctly.
        </div>
    </div>
</body>
</html>
