<?php
/**
 * Force Re-Login
 * Clears session and redirects to login
 */

session_start();
session_destroy();
session_start();

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Re-Login Required</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #2563eb;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #1e40af;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔄</div>
        <h1>Session Cleared!</h1>
        <p>Your session has been cleared successfully.</p>
        <p>Please login again to continue using the system.</p>
        
        <div class="note">
            <strong>⚠️ Why do I need to re-login?</strong><br>
            We updated the permission system to give admin users full access.
            Re-logging will update your session with the new permissions.
        </div>
        
        <a href="login.php" class="btn">Go to Login Page</a>
        
        <p style="margin-top: 30px; font-size: 14px; color: #999;">
            Use: <strong>admin</strong> / <strong>admin123</strong>
        </p>
    </div>
</body>
</html>
