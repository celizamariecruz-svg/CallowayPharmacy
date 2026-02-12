<?php
/**
 * Setup Permissions System
 * Creates permissions and assigns them to admin role
 */

require_once 'db_connection.php';

echo "========================================\n";
echo "SETTING UP PERMISSIONS SYSTEM\n";
echo "========================================\n\n";

// Create permissions table if not exists
$create_permissions = "
CREATE TABLE IF NOT EXISTS permissions (
    permission_id INT PRIMARY KEY AUTO_INCREMENT,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($create_permissions)) {
    echo "✅ Permissions table ready\n";
} else {
    echo "❌ Error with permissions table: " . $conn->error . "\n";
}

// Create role_permissions table if not exists
$create_role_permissions = "
CREATE TABLE IF NOT EXISTS role_permissions (
    role_permission_id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id)
)";

if ($conn->query($create_role_permissions)) {
    echo "✅ Role permissions table ready\n\n";
} else {
    echo "❌ Error with role_permissions table: " . $conn->error . "\n";
}

// Define all permissions
$permissions = [
    // Product permissions
    ['products.view', 'View products and inventory', 'Products'],
    ['products.create', 'Add new products', 'Products'],
    ['products.update', 'Edit existing products', 'Products'],
    ['products.delete', 'Delete products', 'Products'],
    
    // Sales permissions
    ['sales.create', 'Process sales/transactions', 'Sales'],
    ['sales.view', 'View sales history', 'Sales'],
    ['sales.refund', 'Process refunds', 'Sales'],
    
    // User management permissions
    ['users.view', 'View users', 'Users'],
    ['users.create', 'Create new users', 'Users'],
    ['users.update', 'Edit users', 'Users'],
    ['users.delete', 'Delete users', 'Users'],
    
    // Reports permissions
    ['reports.view', 'View reports', 'Reports'],
    ['reports.export', 'Export reports', 'Reports'],
    
    // Settings permissions
    ['settings.view', 'View settings', 'Settings'],
    ['settings.update', 'Update settings', 'Settings'],
    
    // System permissions
    ['system.backup', 'Create backups', 'System'],
    ['system.logs', 'View system logs', 'System'],
];

echo "Installing permissions...\n";
$permission_ids = [];

foreach ($permissions as $perm) {
    $insert_perm = $conn->prepare("INSERT INTO permissions (permission_name, description, category) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description), category = VALUES(category)");
    
    if ($insert_perm) {
        $insert_perm->bind_param("sss", $perm[0], $perm[1], $perm[2]);
        if ($insert_perm->execute()) {
            echo "  ✓ " . $perm[0] . "\n";
            
            // Get the permission ID
            $result = $conn->query("SELECT permission_id FROM permissions WHERE permission_name = '{$perm[0]}'");
            if ($result) {
                $row = $result->fetch_assoc();
                $permission_ids[$perm[0]] = $row['permission_id'];
            }
        } else {
            echo "  ✗ Error: " . $insert_perm->error . "\n";
        }
    } else {
        echo "  ✗ Prepare error: " . $conn->error . "\n";
    }
}

echo "\n✅ " . count($permissions) . " permissions installed\n\n";

// Get admin role ID
$admin_role = $conn->query("SELECT role_id FROM roles WHERE role_name = 'admin' LIMIT 1");
if ($admin_role && $admin_role->num_rows > 0) {
    $admin = $admin_role->fetch_assoc();
    $admin_role_id = $admin['role_id'];
    
    echo "Assigning all permissions to admin role...\n";
    
    $assign_perm = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)");
    
    foreach ($permission_ids as $perm_name => $perm_id) {
        $assign_perm->bind_param("ii", $admin_role_id, $perm_id);
        $assign_perm->execute();
    }
    
    echo "✅ All permissions assigned to admin role\n\n";
} else {
    echo "❌ Admin role not found!\n";
}

// Get cashier role ID and assign basic permissions
$cashier_role = $conn->query("SELECT role_id FROM roles WHERE role_name = 'cashier' LIMIT 1");
if ($cashier_role && $cashier_role->num_rows > 0) {
    $cashier = $cashier_role->fetch_assoc();
    $cashier_role_id = $cashier['role_id'];
    
    echo "Assigning cashier permissions...\n";
    
    $cashier_perms = ['products.view', 'sales.create', 'sales.view'];
    foreach ($cashier_perms as $perm_name) {
        if (isset($permission_ids[$perm_name])) {
            $assign_perm->bind_param("ii", $cashier_role_id, $permission_ids[$perm_name]);
            $assign_perm->execute();
            echo "  ✓ " . $perm_name . "\n";
        }
    }
    
    echo "✅ Cashier permissions assigned\n\n";
}

// Get inventory manager role ID and assign permissions
$inv_role = $conn->query("SELECT role_id FROM roles WHERE role_name = 'inventory_manager' LIMIT 1");
if ($inv_role && $inv_role->num_rows > 0) {
    $inv = $inv_role->fetch_assoc();
    $inv_role_id = $inv['role_id'];
    
    echo "Assigning inventory manager permissions...\n";
    
    $inv_perms = ['products.view', 'products.create', 'products.update', 'sales.view', 'reports.view'];
    foreach ($inv_perms as $perm_name) {
        if (isset($permission_ids[$perm_name])) {
            $assign_perm->bind_param("ii", $inv_role_id, $permission_ids[$perm_name]);
            $assign_perm->execute();
            echo "  ✓ " . $perm_name . "\n";
        }
    }
    
    echo "✅ Inventory manager permissions assigned\n\n";
}

echo "========================================\n";
echo "✅ PERMISSIONS SETUP COMPLETE!\n";
echo "========================================\n";

$conn->close();
?>
