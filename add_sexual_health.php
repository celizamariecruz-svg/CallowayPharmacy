<?php
/**
 * Add sexual health products commonly sold in the Philippines
 * Scope: contraceptives, lubricants, pregnancy tests (brands + generics)
 * Run once: php add_sexual_health.php
 */
require_once 'db_connection.php';

echo "<pre>\n=== Adding Sexual Health Products ===\n\n";

// Ensure category exists
$categoryName = 'Sexual Health';
$stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?) ON DUPLICATE KEY UPDATE category_id=LAST_INSERT_ID(category_id)");
$stmt->bind_param("s", $categoryName);
$stmt->execute();
$categoryId = $conn->insert_id ?: $conn->query("SELECT category_id FROM categories WHERE category_name='" . $conn->real_escape_string($categoryName) . "'")->fetch_assoc()['category_id'];

// Load existing product names to avoid duplicates
$existing = [];
$res = $conn->query("SELECT name FROM products");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $existing[strtolower($row['name'])] = true;
    }
}

/**
 * Product data array
 * [name, generic_name, brand_name, dosage_form, strength, age_group, category,
 *  selling_price, cost_price, stock_qty, pieces_per_box, price_per_piece, sell_by_piece,
 *  expiry_date, location, description]
 */
$products = [
    // ===== CONDOMS (brands + variants) =====
    ['Durex Classic 3s', 'Condom', 'Durex', 'Condom', 'Classic', 'adult', $categoryName,
     85.00, 55.00, 40, 3, 28.33, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S1', 'Classic Durex condoms, 3-pack.'],
    ['Durex Fetherlite 3s', 'Condom', 'Durex', 'Condom', 'Ultra Thin', 'adult', $categoryName,
     95.00, 60.00, 35, 3, 31.67, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S1', 'Durex ultra-thin condoms, 3-pack.'],
    ['Trust Classic 3s', 'Condom', 'Trust', 'Condom', 'Classic', 'adult', $categoryName,
     70.00, 45.00, 45, 3, 23.33, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S1', 'Trust classic condoms, 3-pack.'],
    ['Fiesta Ribbed 3s', 'Condom', 'Fiesta', 'Condom', 'Ribbed', 'adult', $categoryName,
     75.00, 48.00, 35, 3, 25.00, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S1', 'Fiesta ribbed condoms, 3-pack.'],
    ['Okamoto 003 3s', 'Condom', 'Okamoto', 'Condom', 'Ultra Thin', 'adult', $categoryName,
     120.00, 78.00, 25, 3, 40.00, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S1', 'Okamoto 003 ultra-thin condoms, 3-pack.'],
    ['Playboy Ultra Thin 3s', 'Condom', 'Playboy', 'Condom', 'Ultra Thin', 'adult', $categoryName,
     80.00, 50.00, 30, 3, 26.67, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S1', 'Playboy ultra-thin condoms, 3-pack.'],

    // ===== LUBRICANTS =====
    ['K-Y Jelly 50g', 'Personal Lubricant', 'K-Y (J&J)', 'Gel', '50g', 'adult', $categoryName,
     220.00, 150.00, 25, 0, 0, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S2', 'Water-based lubricant.'],
    ['K-Y Jelly 100g', 'Personal Lubricant', 'K-Y (J&J)', 'Gel', '100g', 'adult', $categoryName,
     380.00, 260.00, 20, 0, 0, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S2', 'Water-based lubricant, 100g.'],
    ['Durex Play Feel 50mL', 'Personal Lubricant', 'Durex Play', 'Gel', '50mL', 'adult', $categoryName,
     420.00, 280.00, 18, 0, 0, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S2', 'Smooth water-based lubricant.'],
    ['Durex Play Tingling 50mL', 'Personal Lubricant', 'Durex Play', 'Gel', '50mL', 'adult', $categoryName,
     450.00, 300.00, 15, 0, 0, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S2', 'Tingling water-based lubricant.'],
    ['Trust Lubricant 50mL', 'Personal Lubricant', 'Trust', 'Gel', '50mL', 'adult', $categoryName,
     280.00, 185.00, 22, 0, 0, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S2', 'Water-based lubricant, 50mL.'],

    // ===== PREGNANCY TESTS =====
    ['Clearblue Rapid Detection', 'Pregnancy Test', 'Clearblue', 'Test Kit', '1 Test', 'adult', $categoryName,
     280.00, 190.00, 30, 0, 0, 0, date('Y-m-d', strtotime('+3 years')), 'Shelf S3', 'Results in 1-3 minutes.'],
    ['First Response Early Result', 'Pregnancy Test', 'First Response', 'Test Kit', '1 Test', 'adult', $categoryName,
     320.00, 215.00, 25, 0, 0, 0, date('Y-m-d', strtotime('+3 years')), 'Shelf S3', 'Early detection pregnancy test.'],
    ['Sure Check Pregnancy Test', 'Pregnancy Test', 'Sure Check', 'Test Kit', '1 Test', 'adult', $categoryName,
     120.00, 75.00, 40, 0, 0, 0, date('Y-m-d', strtotime('+3 years')), 'Shelf S3', 'Affordable pregnancy test kit.'],
    ['Pregtest Strip', 'Pregnancy Test', 'Generic', 'Test Strip', '1 Test', 'adult', $categoryName,
     45.00, 25.00, 80, 0, 0, 0, date('Y-m-d', strtotime('+3 years')), 'Shelf S3', 'Economy test strip.'],

    // ===== ORAL CONTRACEPTIVES =====
    ['Lady Pills 28s', 'Ethinylestradiol + Levonorgestrel', 'Lady', 'Tablet', '0.03mg/0.15mg', 'adult', $categoryName,
     155.00, 95.00, 60, 28, 5.54, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S4', 'Combined oral contraceptive, 28 tablets.'],
    ['Althea 21s', 'Ethinylestradiol + Cyproterone', 'Althea', 'Tablet', '0.035mg/2mg', 'adult', $categoryName,
     650.00, 420.00, 20, 21, 30.95, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S4', 'Combined oral contraceptive, 21 tablets.'],
    ['Diane-35 21s', 'Ethinylestradiol + Cyproterone', 'Diane-35 (Bayer)', 'Tablet', '0.035mg/2mg', 'adult', $categoryName,
     780.00, 520.00, 18, 21, 37.14, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S4', 'Combined oral contraceptive, 21 tablets.'],
    ['Yaz 28s', 'Ethinylestradiol + Drospirenone', 'Yaz (Bayer)', 'Tablet', '0.02mg/3mg', 'adult', $categoryName,
     920.00, 620.00, 16, 28, 32.86, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S4', 'Low-dose combined oral contraceptive, 28 tablets.'],
    ['Yasmin 21s', 'Ethinylestradiol + Drospirenone', 'Yasmin (Bayer)', 'Tablet', '0.03mg/3mg', 'adult', $categoryName,
     980.00, 660.00, 14, 21, 46.67, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S4', 'Combined oral contraceptive, 21 tablets.'],
    ['Marvelon 21s', 'Ethinylestradiol + Desogestrel', 'Marvelon (MSD)', 'Tablet', '0.03mg/0.15mg', 'adult', $categoryName,
     850.00, 560.00, 16, 21, 40.48, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S4', 'Combined oral contraceptive, 21 tablets.'],

    // ===== EMERGENCY CONTRACEPTION =====
    ['Levonorgestrel 1.5mg', 'Levonorgestrel', 'Generic', 'Tablet', '1.5mg', 'adult', $categoryName,
     350.00, 220.00, 20, 1, 350.00, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S5', 'Emergency contraceptive. Use as directed.'],
    ['Levonorgestrel 0.75mg x2', 'Levonorgestrel', 'Generic', 'Tablet', '0.75mg', 'adult', $categoryName,
     320.00, 200.00, 22, 2, 160.00, 0, date('Y-m-d', strtotime('+2 years')), 'Shelf S5', 'Emergency contraceptive, 2 tablets.'],
];

$insertStmt = $conn->prepare("
    INSERT INTO products (
        name, generic_name, brand_name, dosage_form, strength, age_group,
        category, category_id, selling_price, cost_price, stock_quantity,
        pieces_per_box, price_per_piece, sell_by_piece,
        expiry_date, location, description, is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$count = 0;
$skipped = 0;
$isActive = 1;
foreach ($products as $p) {
    [$name, $generic, $brand, $form, $str, $age, $catName,
     $sellPrice, $costPrice, $stockQty, $perBox, $pricePiece, $byPiece,
     $expiry, $loc, $desc] = $p;

    if (isset($existing[strtolower($name)])) {
        $skipped++;
        continue;
    }

    $insertStmt->bind_param(
        "sssssssiddiidisssi",
        $name, $generic, $brand, $form, $str, $age,
        $catName, $categoryId, $sellPrice, $costPrice, $stockQty,
        $perBox, $pricePiece, $byPiece,
        $expiry, $loc, $desc, $isActive
    );

    if ($insertStmt->execute()) {
        $count++;
    }
}

echo "Added $count products. Skipped $skipped duplicates.\n";
