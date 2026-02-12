<?php
require_once 'db_connection.php';

$path = 'uploads/products/ACICLOVIR-200MG-25CPR-NOVS.jpg';
$stmt = $conn->prepare('UPDATE products SET image_url = ? WHERE product_id = 238');
$stmt->bind_param('s', $path);
$stmt->execute();
echo "Updated: {$stmt->affected_rows} row(s)\n";

$r = $conn->query('SELECT product_id, name, image_url FROM products WHERE product_id = 238');
$row = $r->fetch_assoc();
echo "{$row['name']} => {$row['image_url']}\n";
