<?php
/**
 * Add condom products (PH popular brands + variants)
 * Run once:  php add_condoms.php
 */
require_once 'db_connection.php';

echo "<pre>\n=== Adding Condom Products ===\n\n";

// Ensure category exists
$categoryName = 'Personal Care';
$stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?) ON DUPLICATE KEY UPDATE category_id=LAST_INSERT_ID(category_id)");
$stmt->bind_param("s", $categoryName);
$stmt->execute();
$categoryId = $conn->insert_id ?: $conn->query("SELECT category_id FROM categories WHERE category_name='" . $conn->real_escape_string($categoryName) . "'")->fetch_assoc()['category_id'];

$brands = [
    'Durex',
    'Trust',
    'Fiesta',
    'Okamoto',
    'Playboy'
];

$variants = [
    ['Regular', 3, 75.00, 48.00],
    ['Regular', 12, 280.00, 185.00],
    ['Ultra Thin', 3, 85.00, 55.00],
    ['Ultra Thin', 12, 320.00, 210.00],
    ['Ribbed', 3, 80.00, 52.00],
    ['Ribbed', 12, 300.00, 195.00]
];

$products = [];
foreach ($brands as $brand) {
    foreach ($variants as $v) {
        [$type, $pack, $sellPrice, $costPrice] = $v;
        $name = $brand . ' ' . $type . ' ' . $pack . 's';
        $generic = 'Condom';
        $dosageForm = 'Condom';
        $strength = $type;
        $ageGroup = 'adult';
        $stockQty = 40;
        $piecesPerBox = $pack;
        $pricePerPiece = round($sellPrice / $pack, 2);
        $sellByPiece = 0;
        $expiry = date('Y-m-d', strtotime('+2 years'));
        $location = 'Shelf P1';
        $desc = $brand . ' ' . strtolower($type) . ' condoms. Pack of ' . $pack . '.';

        $products[] = [
            $name, $generic, $brand, $dosageForm, $strength, $ageGroup,
            $categoryName, $categoryId, $sellPrice, $costPrice, $stockQty,
            $piecesPerBox, $pricePerPiece, $sellByPiece,
            $expiry, $location, $desc
        ];
    }
}

$insertStmt = $conn->prepare("
    INSERT INTO products (
        name, generic_name, brand_name, dosage_form, strength, age_group,
        category, category_id, selling_price, cost_price, stock_quantity,
        pieces_per_box, price_per_piece, sell_by_piece,
        expiry_date, location, description, is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$count = 0;
$isActive = 1;
foreach ($products as $p) {
    [$name, $generic, $brand, $form, $str, $age, $catName, $catId,
     $sellPrice, $costPrice, $stockQty, $perBox, $pricePiece, $byPiece,
     $expiry, $loc, $desc] = $p;

    $insertStmt->bind_param(
        "sssssssiddiidisssi",
        $name, $generic, $brand, $form, $str, $age,
        $catName, $catId, $sellPrice, $costPrice, $stockQty,
        $perBox, $pricePiece, $byPiece,
        $expiry, $loc, $desc, $isActive
    );

    if ($insertStmt->execute()) {
        $count++;
    }
}

echo "Added $count products.\n";
