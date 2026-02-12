<?php
/**
 * Migration 003: Create Categories and Enhance Products
 * Implements Module 2 of database_migrations.sql
 */

require_once __DIR__ . '/../config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error . "\n");

echo "Running Migration 003: Categories & Product Enhancements...\n";

// 1. Create categories table
echo "Creating 'categories' table...\n";
$sql = "CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql))
    echo "✅ 'categories' table created/verified.\n";
else
    echo "❌ Failed to create 'categories': " . $conn->error . "\n";

// 2. Create suppliers table
echo "Creating 'suppliers' table...\n";
$sql = "CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    address TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_name (supplier_name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if ($conn->query($sql))
    echo "✅ 'suppliers' table created/verified.\n";
else
    echo "❌ Failed to create 'suppliers': " . $conn->error . "\n";

// 3. Enhance products table
echo "Enhancing 'products' table...\n";

// Helper to add column if not exists
function addColumn($conn, $table, $column, $type)
{
    $check = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    if ($check->num_rows == 0) {
        $sql = "ALTER TABLE $table ADD COLUMN $column $type";
        if ($conn->query($sql))
            echo "✅ Added column '$column'.\n";
        else
            echo "❌ Failed to add '$column': " . $conn->error . "\n";
    } else {
        echo "ℹ️ Column '$column' already exists.\n";
    }
}

addColumn($conn, 'products', 'sku', 'VARCHAR(50) UNIQUE NULL AFTER product_id');
addColumn($conn, 'products', 'barcode', 'VARCHAR(100) UNIQUE NULL AFTER sku');
addColumn($conn, 'products', 'category_id', 'INT NULL AFTER category');
addColumn($conn, 'products', 'supplier_id', 'INT NULL AFTER category_id');
addColumn($conn, 'products', 'cost_price', 'DECIMAL(10,2) DEFAULT 0.00 AFTER price');
addColumn($conn, 'products', 'selling_price', 'DECIMAL(10,2) NULL AFTER cost_price');
addColumn($conn, 'products', 'description', 'TEXT NULL AFTER name');
addColumn($conn, 'products', 'reorder_level', 'INT DEFAULT 10 AFTER stock_quantity');
addColumn($conn, 'products', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');

// 4. Update selling_price
echo "Updating selling_price...\n";
$conn->query("UPDATE products SET selling_price = price WHERE selling_price IS NULL");
echo "✅ selling_price updated.\n";

// 5. Add Foreign Keys (safely)
// category_id FK
$checkFK = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'fk_product_category'");
if ($checkFK->num_rows == 0) {
    if ($conn->query("ALTER TABLE products ADD CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL")) {
        echo "✅ Added FK 'fk_product_category'.\n";
    } else {
        echo "⚠️ Failed to add FK 'fk_product_category': " . $conn->error . "\n";
    }
}

// supplier_id FK
$checkFK = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'fk_product_supplier'");
if ($checkFK->num_rows == 0) {
    if ($conn->query("ALTER TABLE products ADD CONSTRAINT fk_product_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL")) {
        echo "✅ Added FK 'fk_product_supplier'.\n";
    } else {
        echo "⚠️ Failed to add FK 'fk_product_supplier': " . $conn->error . "\n";
    }
}

// 6. Insert default categories if empty
$checkCats = $conn->query("SELECT COUNT(*) as c FROM categories");
$row = $checkCats->fetch_assoc();
if ($row['c'] == 0) {
    echo "Inserting default categories...\n";
    $sql = "INSERT INTO categories (category_name, description) VALUES
    ('Pain Relief', 'Analgesics and pain management medications'),
    ('Antibiotics', 'Bacterial infection treatments'),
    ('Cold & Flu', 'Treatments for common cold and influenza'),
    ('Supplements', 'Vitamins and dietary supplements'),
    ('First Aid', 'Wound care and emergency medical supplies'),
    ('Prescription', 'Prescription-only medications'),
    ('OTC', 'Over-the-counter medications')";
    $conn->query($sql);
    echo "✅ Default categories inserted.\n";
}

// 7. Migrate existing string categories to category_id
echo "Migrating existing string categories...\n";
$res = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != ''");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $catName = $row['category'];
        // Check if exists in categories table
        $stmt = $conn->prepare("SELECT category_id FROM categories WHERE category_name = ?");
        $stmt->bind_param("s", $catName);
        $stmt->execute();
        $catResult = $stmt->get_result();

        if ($catResult->num_rows > 0) {
            $catId = $catResult->fetch_assoc()['category_id'];
        } else {
            // Create it
            $stmtInsert = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
            $stmtInsert->bind_param("s", $catName);
            $stmtInsert->execute();
            $catId = $stmtInsert->insert_id;
            echo "Created new category from existing data: $catName\n";
        }

        // Update products
        $stmtUpdate = $conn->prepare("UPDATE products SET category_id = ? WHERE category = ?");
        $stmtUpdate->bind_param("is", $catId, $catName);
        $stmtUpdate->execute();
    }
    echo "✅ Category migration complete.\n";
}

$conn->close();
echo "Migration 003 complete.\n";
?>