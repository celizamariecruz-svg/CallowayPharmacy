<?php
/**
 * One-time backfill: Insert rx_approval_log entries for past POS sales
 * that included prescription (Rx) products but were never logged.
 * Safe to run multiple times — skips sales already logged.
 */
require 'db_connection.php';

echo "=== Rx Log Backfill ===\n\n";

// 1. Ensure rx_approval_log table can accept POS entries
$conn->query("CREATE TABLE IF NOT EXISTS rx_approval_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NULL,
    sale_id INT NULL,
    sale_reference VARCHAR(50) NULL,
    product_id INT NOT NULL,
    pharmacist_id INT NOT NULL,
    action VARCHAR(50) NOT NULL DEFAULT 'Approved',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_sale (sale_id),
    INDEX idx_pharmacist (pharmacist_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add columns if missing
$chk = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rx_approval_log' AND COLUMN_NAME='sale_id'");
if ($chk->num_rows === 0) {
    $conn->query("ALTER TABLE rx_approval_log ADD COLUMN sale_id INT NULL AFTER order_id");
    $conn->query("ALTER TABLE rx_approval_log ADD INDEX idx_sale (sale_id)");
    echo "Added sale_id column\n";
}
$chk = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rx_approval_log' AND COLUMN_NAME='sale_reference'");
if ($chk->num_rows === 0) {
    $conn->query("ALTER TABLE rx_approval_log ADD COLUMN sale_reference VARCHAR(50) NULL AFTER sale_id");
    echo "Added sale_reference column\n";
}
$conn->query("ALTER TABLE rx_approval_log MODIFY COLUMN order_id INT NULL");
$conn->query("ALTER TABLE rx_approval_log MODIFY COLUMN action VARCHAR(50) NOT NULL DEFAULT 'Approved'");

// 2. Find all past POS sales that included Rx products
$query = "
    SELECT s.sale_id, s.sale_reference, s.cashier, s.created_at,
           si.product_id, p.name AS product_name,
           COALESCE(
               (SELECT u.user_id FROM users u WHERE u.full_name COLLATE utf8mb4_general_ci = s.cashier COLLATE utf8mb4_general_ci LIMIT 1),
               (SELECT u.user_id FROM users u WHERE u.username COLLATE utf8mb4_general_ci = s.cashier COLLATE utf8mb4_general_ci LIMIT 1),
               (SELECT MIN(u2.user_id) FROM users u2)
           ) AS cashier_user_id
    FROM sales s
    JOIN sale_items si ON s.sale_id = si.sale_id
    JOIN products p ON si.product_id = p.product_id
    WHERE p.requires_prescription = 1
      AND NOT EXISTS (
          SELECT 1 FROM rx_approval_log rl
          WHERE rl.sale_id = s.sale_id AND rl.product_id = si.product_id
      )
    ORDER BY s.created_at ASC
";

$result = $conn->query($query);
$count = 0;

if ($result && $result->num_rows > 0) {
    $insertStmt = $conn->prepare("
        INSERT INTO rx_approval_log (order_id, sale_id, sale_reference, product_id, pharmacist_id, action, notes, created_at)
        VALUES (NULL, ?, ?, ?, ?, 'POS Dispensed', ?, ?)
    ");

    while ($row = $result->fetch_assoc()) {
        $notes = "Prescription verified at POS by {$row['cashier']} (backfilled)";
        $insertStmt->bind_param(
            "isiiss",
            $row['sale_id'],
            $row['sale_reference'],
            $row['product_id'],
            $row['cashier_user_id'],
            $notes,
            $row['created_at']
        );
        $insertStmt->execute();
        $count++;
        echo "  Logged: {$row['product_name']} — Sale {$row['sale_reference']} ({$row['created_at']})\n";
    }
    $insertStmt->close();
} else {
    echo "No unlogged Rx sales found.\n";
}

echo "\n✅ Backfill complete: $count Rx log entries created.\n";

// 3. Show summary
$r = $conn->query("SELECT COUNT(*) c FROM rx_approval_log");
echo "Total rx_approval_log entries now: " . $r->fetch_assoc()['c'] . "\n";
