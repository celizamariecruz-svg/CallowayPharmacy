<?php
/**
 * Migration 001: Initial Schema Fixes
 * Adds missing columns to products table if they don't exist.
 */

require_once __DIR__ . '/../config.php';

// Connect using config credentials
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "Running Migration 001: Initial Schema Fixes...\n";

// 1. Add is_active column
echo "Checking 'is_active' column...\n";
$checkIsActiveQuery = "SHOW COLUMNS FROM products LIKE 'is_active'";
$isActiveExists = $conn->query($checkIsActiveQuery);

if ($isActiveExists && $isActiveExists->num_rows == 0) {
    echo "Adding 'is_active' column...\n";
    $addIsActiveQuery = "ALTER TABLE products ADD COLUMN is_active TINYINT(1) DEFAULT 1";
    if ($conn->query($addIsActiveQuery)) {
        echo "✅ 'is_active' column added.\n";
    } else {
        echo "❌ Failed to add 'is_active': " . $conn->error . "\n";
    }
} else {
    echo "ℹ️ 'is_active' column already exists.\n";
}

// 2. Add location column
echo "Checking 'location' column...\n";
$checkLocationQuery = "SHOW COLUMNS FROM products LIKE 'location'";
$locationExists = $conn->query($checkLocationQuery);

if ($locationExists && $locationExists->num_rows == 0) {
    echo "Adding 'location' column...\n";
    $addLocationQuery = "ALTER TABLE products ADD COLUMN location VARCHAR(255) NULL";
    if ($conn->query($addLocationQuery)) {
        echo "✅ 'location' column added.\n";
    } else {
        echo "❌ Failed to add 'location': " . $conn->error . "\n";
    }
} else {
    echo "ℹ️ 'location' column already exists.\n";
}

// 3. Ensure users table exists (from create_admin.php logic, good to have here too)
echo "Checking 'users' table...\n";
$checkUsers = $conn->query("SHOW TABLES LIKE 'users'");
if ($checkUsers->num_rows == 0) {
    echo "❌ 'users' table missing! Please import database_schema.sql first.\n";
} else {
    echo "ℹ️ 'users' table exists.\n";
}

$conn->close();
echo "Migration 001 complete.\n";
?>