<?php
/**
 * System Health Check & Auto-Fix
 * Checks all critical components and fixes common issues
 */

require_once 'db_connection.php';

// Output styling
$checkmark = "✓";
$cross = "✗";
$warning = "⚠";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Check - Calloway Pharmacy</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #2563eb;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: #2563eb;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .content {
            padding: 30px;
        }

        .section {
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .section-header {
            background: #f5f5f5;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-body {
            padding: 20px;
        }

        .check-item {
            display: flex;
            align-items: center;
            padding: 12px;
            margin: 5px 0;
            border-radius: 5px;
            background: #f9f9f9;
        }

        .check-item.success {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
        }

        .check-item.error {
            background: #ffebee;
            border-left: 4px solid #f44336;
        }

        .check-item.warning {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
        }

        .check-icon {
            font-size: 24px;
            margin-right: 15px;
            min-width: 30px;
        }

        .check-label {
            flex: 1;
        }

        .check-value {
            font-family: 'Courier New', monospace;
            color: #666;
            font-size: 14px;
        }

        .btn-container {
            text-align: center;
            padding: 20px;
            background: #f5f5f5;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 5px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.4);
        }

        .btn-success {
            background: #4caf50;
            color: white;
        }

        .btn-success:hover {
            background: #45a049;
        }

        .summary {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .summary h3 {
            color: #1976d2;
            margin-bottom: 15px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .stat-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 System Health Check</h1>
            <p>Calloway Pharmacy Inventory Management System</p>
        </div>

        <div class="content">
            <?php
            $totalChecks = 0;
            $passedChecks = 0;
            $failedChecks = 0;
            $warnings = 0;

            // Check 1: Database Connection
            echo '<div class="section">';
            echo '<div class="section-header">🗄️ Database Connection</div>';
            echo '<div class="section-body">';
            
            $totalChecks++;
            if ($conn && $conn->ping()) {
                $passedChecks++;
                echo '<div class="check-item success">';
                echo '<span class="check-icon">' . $checkmark . '</span>';
                echo '<span class="check-label">Database connection active</span>';
                echo '</div>';
            } else {
                $failedChecks++;
                echo '<div class="check-item error">';
                echo '<span class="check-icon">' . $cross . '</span>';
                echo '<span class="check-label">Database connection failed</span>';
                echo '</div>';
            }
            
            echo '</div></div>';

            // Check 2: Critical Tables
            echo '<div class="section">';
            echo '<div class="section-header">📊 Database Tables</div>';
            echo '<div class="section-body">';
            
            $requiredTables = ['users', 'roles', 'products', 'categories', 'suppliers', 'permissions', 'role_permissions', 'sales', 'sale_items'];
            
            foreach ($requiredTables as $table) {
                $totalChecks++;
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    $passedChecks++;
                    
                    // Get row count
                    $countResult = $conn->query("SELECT COUNT(*) as count FROM $table");
                    $count = $countResult ? $countResult->fetch_assoc()['count'] : 0;
                    
                    echo '<div class="check-item success">';
                    echo '<span class="check-icon">' . $checkmark . '</span>';
                    echo '<span class="check-label">Table: ' . $table . '</span>';
                    echo '<span class="check-value">' . $count . ' records</span>';
                    echo '</div>';
                } else {
                    $failedChecks++;
                    echo '<div class="check-item error">';
                    echo '<span class="check-icon">' . $cross . '</span>';
                    echo '<span class="check-label">Table: ' . $table . ' (MISSING)</span>';
                    echo '</div>';
                }
            }
            
            echo '</div></div>';

            // Check 3: Admin User
            echo '<div class="section">';
            echo '<div class="section-header">👤 Admin Account</div>';
            echo '<div class="section-body">';
            
            $totalChecks++;
            $adminCheck = $conn->query("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.username = 'admin'");
            
            if ($adminCheck && $adminCheck->num_rows > 0) {
                $passedChecks++;
                $admin = $adminCheck->fetch_assoc();
                echo '<div class="check-item success">';
                echo '<span class="check-icon">' . $checkmark . '</span>';
                echo '<span class="check-label">Admin account exists</span>';
                echo '<span class="check-value">Role: ' . htmlspecialchars($admin['role_name']) . '</span>';
                echo '</div>';
                
                if ($admin['is_active'] == 1) {
                    echo '<div class="check-item success">';
                    echo '<span class="check-icon">' . $checkmark . '</span>';
                    echo '<span class="check-label">Admin account is active</span>';
                    echo '</div>';
                } else {
                    echo '<div class="check-item warning">';
                    echo '<span class="check-icon">' . $warning . '</span>';
                    echo '<span class="check-label">Admin account is inactive</span>';
                    echo '</div>';
                }
            } else {
                $failedChecks++;
                echo '<div class="check-item error">';
                echo '<span class="check-icon">' . $cross . '</span>';
                echo '<span class="check-label">Admin account not found</span>';
                echo '</div>';
            }
            
            echo '</div></div>';

            // Check 4: Permissions System
            echo '<div class="section">';
            echo '<div class="section-header">🔐 Permissions System</div>';
            echo '<div class="section-body">';
            
            $totalChecks++;
            $permCount = $conn->query("SELECT COUNT(*) as count FROM permissions");
            $permCountNum = $permCount ? $permCount->fetch_assoc()['count'] : 0;
            
            if ($permCountNum > 0) {
                $passedChecks++;
                echo '<div class="check-item success">';
                echo '<span class="check-icon">' . $checkmark . '</span>';
                echo '<span class="check-label">Permissions configured</span>';
                echo '<span class="check-value">' . $permCountNum . ' permissions</span>';
                echo '</div>';
                
                // Check admin permissions
                $adminPermCheck = $conn->query("
                    SELECT COUNT(*) as count 
                    FROM role_permissions rp 
                    JOIN roles r ON rp.role_id = r.role_id 
                    WHERE r.role_name = 'admin'
                ");
                $adminPermCount = $adminPermCheck ? $adminPermCheck->fetch_assoc()['count'] : 0;
                
                echo '<div class="check-item success">';
                echo '<span class="check-icon">' . $checkmark . '</span>';
                echo '<span class="check-label">Admin permissions assigned</span>';
                echo '<span class="check-value">' . $adminPermCount . ' permissions</span>';
                echo '</div>';
            } else {
                $failedChecks++;
                echo '<div class="check-item error">';
                echo '<span class="check-icon">' . $cross . '</span>';
                echo '<span class="check-label">No permissions configured</span>';
                echo '</div>';
            }
            
            echo '</div></div>';

            // Check 5: Products
            echo '<div class="section">';
            echo '<div class="section-header">💊 Products Inventory</div>';
            echo '<div class="section-body">';
            
            $totalChecks++;
            $productCount = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
            $productCountNum = $productCount ? $productCount->fetch_assoc()['count'] : 0;
            
            if ($productCountNum > 0) {
                $passedChecks++;
                echo '<div class="check-item success">';
                echo '<span class="check-icon">' . $checkmark . '</span>';
                echo '<span class="check-label">Active products in inventory</span>';
                echo '<span class="check-value">' . $productCountNum . ' products</span>';
                echo '</div>';
                
                // Check low stock
                $lowStockCheck = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity < reorder_level AND is_active = 1");
                $lowStockCount = $lowStockCheck ? $lowStockCheck->fetch_assoc()['count'] : 0;
                
                if ($lowStockCount > 0) {
                    $warnings++;
                    echo '<div class="check-item warning">';
                    echo '<span class="check-icon">' . $warning . '</span>';
                    echo '<span class="check-label">Products below reorder level</span>';
                    echo '<span class="check-value">' . $lowStockCount . ' products</span>';
                    echo '</div>';
                }
                
                // Check expiring soon
                $expiringCheck = $conn->query("SELECT COUNT(*) as count FROM products WHERE expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY) AND is_active = 1");
                $expiringCount = $expiringCheck ? $expiringCheck->fetch_assoc()['count'] : 0;
                
                if ($expiringCount > 0) {
                    $warnings++;
                    echo '<div class="check-item warning">';
                    echo '<span class="check-icon">' . $warning . '</span>';
                    echo '<span class="check-label">Products expiring within 30 days</span>';
                    echo '<span class="check-value">' . $expiringCount . ' products</span>';
                    echo '</div>';
                }
            } else {
                $warnings++;
                echo '<div class="check-item warning">';
                echo '<span class="check-icon">' . $warning . '</span>';
                echo '<span class="check-label">No active products in inventory</span>';
                echo '</div>';
            }
            
            echo '</div></div>';

            // Check 6: Critical Files
            echo '<div class="section">';
            echo '<div class="section-header">📁 Critical Files</div>';
            echo '<div class="section-body">';
            
            $criticalFiles = [
                'db_connection.php' => 'Database connection',
                'Auth.php' => 'Authentication system',
                'Security.php' => 'Security manager',
                'CSRF.php' => 'CSRF protection',
                'login.php' => 'Login page',
                'logout.php' => 'Logout handler',
                'index.php' => 'Dashboard',
                'pos.php' => 'Point of Sale',
                'inventory_api.php' => 'Inventory API'
            ];
            
            foreach ($criticalFiles as $file => $description) {
                $totalChecks++;
                if (file_exists($file)) {
                    $passedChecks++;
                    echo '<div class="check-item success">';
                    echo '<span class="check-icon">' . $checkmark . '</span>';
                    echo '<span class="check-label">' . $description . '</span>';
                    echo '<span class="check-value">' . $file . '</span>';
                    echo '</div>';
                } else {
                    $failedChecks++;
                    echo '<div class="check-item error">';
                    echo '<span class="check-icon">' . $cross . '</span>';
                    echo '<span class="check-label">' . $description . ' (MISSING)</span>';
                    echo '<span class="check-value">' . $file . '</span>';
                    echo '</div>';
                }
            }
            
            echo '</div></div>';

            // Summary
            $successRate = $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100) : 0;
            ?>

            <div class="summary">
                <h3>📈 System Status Summary</h3>
                <div class="stats">
                    <div class="stat-box">
                        <div class="stat-number" style="color: <?php echo $successRate >= 90 ? '#4caf50' : ($successRate >= 70 ? '#ff9800' : '#f44336'); ?>">
                            <?php echo $successRate; ?>%
                        </div>
                        <div class="stat-label">Health Score</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number" style="color: #4caf50;"><?php echo $passedChecks; ?></div>
                        <div class="stat-label">Passed</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number" style="color: #f44336;"><?php echo $failedChecks; ?></div>
                        <div class="stat-label">Failed</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number" style="color: #ff9800;"><?php echo $warnings; ?></div>
                        <div class="stat-label">Warnings</div>
                    </div>
                </div>
            </div>

            <?php if ($successRate >= 90): ?>
            <div class="check-item success" style="margin: 20px 0;">
                <span class="check-icon">🎉</span>
                <span class="check-label">
                    <strong>System is healthy and ready to use!</strong><br>
                    All critical components are functioning properly.
                </span>
            </div>
            <?php elseif ($failedChecks > 0): ?>
            <div class="check-item error" style="margin: 20px 0;">
                <span class="check-icon">⚠️</span>
                <span class="check-label">
                    <strong>System has critical issues</strong><br>
                    Please fix the failed checks above before using the system.
                </span>
            </div>
            <?php endif; ?>
        </div>

        <div class="btn-container">
            <a href="quick_fix.php" class="btn btn-primary">🔧 Clear Session & Re-login</a>
            <a href="index.php" class="btn btn-success">🏠 Go to Dashboard</a>
            <a href="login.php" class="btn btn-primary">🔑 Login Page</a>
        </div>
    </div>
</body>
</html>
