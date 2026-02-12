<?php
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

echo "Checking 'categories' table...\n";
$res = $conn->query("SHOW TABLES LIKE 'categories'");
if ($res->num_rows > 0)
    echo "✅ 'categories' table exists.\n";
else
    echo "❌ 'categories' table MISSING.\n";

echo "Checking 'products.category_id' column...\n";
$res = $conn->query("SHOW COLUMNS FROM products LIKE 'category_id'");
if ($res->num_rows > 0)
    echo "✅ 'products.category_id' column exists.\n";
else
    echo "❌ 'products.category_id' column MISSING.\n";

echo "Checking 'products.sku' column...\n";
$res = $conn->query("SHOW COLUMNS FROM products LIKE 'sku'");
if ($res->num_rows > 0)
    echo "✅ 'products.sku' column exists.\n";
else
    echo "❌ 'products.sku' column MISSING.\n";

echo "Checking 'products' table count...\n";
$res = $conn->query("SELECT COUNT(*) as c FROM products");
$row = $res->fetch_assoc();
echo "Products count: " . $row['c'] . "\n";

?>