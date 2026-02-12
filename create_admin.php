<?php
/**
 * Create Test Admin User
 * Run this file once to create a test admin account
 */

require_once 'db_connection.php';
require_once 'Security.php';

// Check if run from command line or strictly allowed context
if (php_sapi_name() !== 'cli' && !defined('ALLOW_SETUP')) {
    // blocked by .htaccess usually, but good secondary check
    die("This script can only be run from the command line or authorized setup flow.");
}

// Default credentials (CHANGE THESE IN PRODUCTION)
$username = 'admin';
$password = 'admin123';
$email = 'admin@callowaypharmacy.com';
$full_name = 'System Administrator';

echo "⚠️  SECURITY WARNING: Using default credentials.\n";
echo "   User: $username\n";
echo "   Pass: $password\n";
echo "   Please change these immediately after logging in.\n\n";

// Hash the password using bcrypt (cost 12)
$password_hash = Security::hashPassword($password);

// Check if users table exists
$check_table = $conn->query("SHOW TABLES LIKE 'users'");

if ($check_table->num_rows == 0) {
    echo "❌ ERROR: 'users' table does not exist in the database!\n";
    echo "Please import database_schema.sql first.\n";
    exit;
}

// Check if roles table exists
$check_roles = $conn->query("SHOW TABLES LIKE 'roles'");

if ($check_roles->num_rows == 0) {
    // Create roles table if it doesn't exist
    echo "Creating 'roles' table...\n";
    $create_roles = "
    CREATE TABLE roles (
        role_id INT PRIMARY KEY AUTO_INCREMENT,
        role_name VARCHAR(50) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if ($conn->query($create_roles)) {
        echo "✅ Roles table created successfully\n";

        // Insert default roles
        $insert_roles = "
        INSERT INTO roles (role_name, description) VALUES
        ('admin', 'System Administrator with full access'),
        ('cashier', 'Cashier with POS access'),
        ('inventory_manager', 'Inventory Manager with stock management access')
        ";

        if ($conn->query($insert_roles)) {
            echo "✅ Default roles inserted\n";
        }
    } else {
        echo "❌ Error creating roles table: " . $conn->error . "\n";
        exit;
    }
}

// Check if users table exists and has proper structure
$check_users = $conn->query("SHOW TABLES LIKE 'users'");

if ($check_users->num_rows == 0) {
    // Create users table
    echo "Creating 'users' table...\n";
    $create_users = "
    CREATE TABLE users (
        user_id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role_id INT NOT NULL DEFAULT 2,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES roles(role_id)
    )";

    if ($conn->query($create_users)) {
        echo "✅ Users table created successfully\n";
    } else {
        echo "❌ Error creating users table: " . $conn->error . "\n";
        exit;
    }
}

// Check if admin user already exists
$check_admin = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$check_admin->bind_param("s", $username);
$check_admin->execute();
$result = $check_admin->get_result();

if ($result->num_rows > 0) {
    // Update existing admin password
    echo "⚠️  Admin user already exists. Updating password...\n";
    $update = $conn->prepare("UPDATE users SET password_hash = ?, email = ?, full_name = ? WHERE username = ?");
    $update->bind_param("ssss", $password_hash, $email, $full_name, $username);

    if ($update->execute()) {
        echo "✅ Admin password updated successfully!\n";
    } else {
        echo "❌ Error updating admin: " . $conn->error . "\n";
    }
} else {
    // Get admin role ID
    $role_result = $conn->query("SELECT role_id FROM roles WHERE role_name = 'admin' LIMIT 1");
    $role = $role_result->fetch_assoc();
    $role_id = $role ? $role['role_id'] : 1;

    // Insert new admin user
    echo "Creating new admin user...\n";
    $insert = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role_id, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $insert->bind_param("ssssi", $username, $email, $password_hash, $full_name, $role_id);

    if ($insert->execute()) {
        echo "✅ Admin user created successfully!\n";
    } else {
        echo "❌ Error creating admin: " . $conn->error . "\n";
    }
}

echo "\n";
echo "========================================\n";
echo "🎉 TEST ADMIN ACCOUNT READY!\n";
echo "========================================\n";
echo "Username: $username\n";
echo "Password: $password\n";
echo "Email: $email\n";
echo "========================================\n";
echo "\n";
echo "You can now login at: http://localhost:8000/login.php\n";
echo "\n";
echo "⚠️  IMPORTANT: Change the password after first login!\n";

$conn->close();
?>