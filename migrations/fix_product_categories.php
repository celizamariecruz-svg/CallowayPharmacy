<?php
/**
 * Fix Product Categories
 * Links string categories to category_id after fresh import
 */

require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

echo "Fixing product categories...\n";

// Get all unique string categories from products
$res = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != ''");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $catName = $row['category'];
        echo "Processing category: '$catName'... ";

        // 1. Check if exists in categories table
        $stmt = $conn->prepare("SELECT category_id FROM categories WHERE category_name = ?");
        $stmt->bind_param("s", $catName);
        $stmt->execute();
        $catResult = $stmt->get_result();

        if ($catResult->num_rows > 0) {
            $catId = $catResult->fetch_assoc()['category_id'];
            echo "Found ID: $catId. ";
        } else {
            // 2. Create it if missing
            $stmtInsert = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
            $stmtInsert->bind_param("s", $catName);
            $stmtInsert->execute();
            $catId = $stmtInsert->insert_id;
            echo "Created ID: $catId. ";
        }

        // 3. Update products
        $stmtUpdate = $conn->prepare("UPDATE products SET category_id = ? WHERE category = ?");
        $stmtUpdate->bind_param("is", $catId, $catName);
        $stmtUpdate->execute();
        echo "Updated products.\n";
    }
}

// Update selling_price if null (fix for import_products.sql which might not set it)
$conn->query("UPDATE products SET selling_price = price WHERE selling_price IS NULL");
echo "Updated NULL selling prices.\n";

echo "Done.\n";
?>