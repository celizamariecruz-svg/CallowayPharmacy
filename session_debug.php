<?php
/**
 * Session Debug - Shows Current Session Status
 */
require_once 'Security.php';
Security::initSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Status</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #252526;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .status.error {
            background: #5a1d1d;
            border-color: #f48771;
        }
        .status.warning {
            background: #4d4a2b;
            border-color: #dcdcaa;
        }
        .status.success {
            background: #1e3a1e;
            border-color: #4ec9b0;
        }
        .key {
            color: #9cdcfe;
        }
        .value {
            color: #ce9178;
        }
        .missing {
            color: #f48771;
            font-weight: bold;
        }
        pre {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            background: #0e639c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .btn:hover {
            background: #1177bb;
        }
        .btn.danger {
            background: #c5392a;
        }
        .btn.danger:hover {
            background: #e81123;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Current Session Status</h1>
        
        <?php if (empty($_SESSION)): ?>
            <div class="status error">
                <strong>❌ NO SESSION DATA</strong><br>
                You are not logged in. Session is empty.
            </div>
        <?php else: ?>
            <div class="status <?php echo isset($_SESSION['role_name']) ? 'success' : 'error'; ?>">
                <strong>Session Active:</strong> 
                <?php echo isset($_SESSION['user_id']) ? '✅ Logged In' : '❌ Not Logged In'; ?>
            </div>
            
            <h2>Session Variables:</h2>
            <pre><?php
foreach ($_SESSION as $key => $value) {
    echo '<span class="key">' . htmlspecialchars($key) . '</span>: ';
    if ($value === null) {
        echo '<span class="missing">NULL (MISSING!)</span>';
    } else {
        echo '<span class="value">' . htmlspecialchars($value) . '</span>';
    }
    echo "\n";
}
?></pre>

            <h2>Permission Check:</h2>
            <?php
            require_once 'db_connection.php';
            require_once 'Auth.php';
            $auth = new Auth($conn);
            ?>
            
            <div class="status">
                <span class="key">isLoggedIn():</span> 
                <span class="value"><?php echo $auth->isLoggedIn() ? '✅ TRUE' : '❌ FALSE'; ?></span>
            </div>
            
            <div class="status">
                <span class="key">hasPermission('products.view'):</span> 
                <span class="value"><?php 
                    $hasPerm = $auth->hasPermission('products.view');
                    echo $hasPerm ? '✅ TRUE' : '❌ FALSE'; 
                ?></span>
            </div>
            
            <?php if (!isset($_SESSION['role_name'])): ?>
            <div class="status error">
                <strong>⚠️ PROBLEM FOUND!</strong><br>
                Your session is missing <span class="missing">role_name</span>.<br>
                This is why products are not loading!<br>
                <br>
                <strong>SOLUTION:</strong> You MUST logout and login again.
            </div>
            <?php elseif ($_SESSION['role_name'] === 'admin'): ?>
            <div class="status success">
                <strong>✅ SESSION IS CORRECT!</strong><br>
                You have <span class="value">role_name = 'admin'</span><br>
                Admin bypass should be working.<br>
                <br>
                If products still don't load, there's a different issue.
            </div>
            <?php endif; ?>
            
            <h2>Database Check:</h2>
            <?php
            $productCount = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
            $count = $productCount ? $productCount->fetch_assoc()['count'] : 0;
            ?>
            <div class="status success">
                <span class="key">Total Products:</span> 
                <span class="value"><?php echo $count; ?> products in database</span>
            </div>
        <?php endif; ?>
        
        <h2>Actions:</h2>
        <a href="logout.php" class="btn danger">🚪 Logout & Clear Session</a>
        <a href="quick_fix.php" class="btn">🔧 Quick Fix (Clear & Relogin)</a>
        <a href="pos.php" class="btn">🛒 Test POS Page</a>
        <a href="inventory_api.php?action=get_products&limit=10" class="btn">📊 Test API Directly</a>
        
        <div style="margin-top: 30px; padding: 15px; background: #2d2d30; border-radius: 5px;">
            <strong>Instructions:</strong><br>
            1. If you see "role_name: NULL (MISSING!)" above, click "Logout & Clear Session"<br>
            2. Then login again with admin/admin123<br>
            3. Come back to this page and verify role_name is set<br>
            4. Then test POS - products should load!
        </div>
    </div>
</body>
</html>
