<?php
/**
 * Migration 002: Create Activity Logs Table
 * Creates the activity_logs table for audit trails.
 */

require_once __DIR__ . '/../config.php';

// Connect using config credentials
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "Running Migration 002: Create Activity Logs Table...\n";

// Check if table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'activity_logs'");

if ($checkTable->num_rows == 0) {
    echo "Creating 'activity_logs' table...\n";

    $sql = "CREATE TABLE activity_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        module VARCHAR(50) NOT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_action (action),
        INDEX idx_module (module),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "✅ 'activity_logs' table created successfully.\n";

        // Add foreign key if users table exists
        $checkUsers = $conn->query("SHOW TABLES LIKE 'users'");
        if ($checkUsers->num_rows > 0) {
            $sqlFK = "ALTER TABLE activity_logs ADD CONSTRAINT fk_activity_log_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL";
            if ($conn->query($sqlFK)) {
                echo "✅ Foreign key to 'users' table added.\n";
            } else {
                echo "⚠️ Failed to add foreign key: " . $conn->error . "\n";
            }
        }

    } else {
        echo "❌ Failed to create table: " . $conn->error . "\n";
    }
} else {
    echo "ℹ️ 'activity_logs' table already exists.\n";
}

$conn->close();
echo "Migration 002 complete.\n";
?>