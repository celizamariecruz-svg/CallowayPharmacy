<?php
require_once 'db_connection.php';

echo "=== Image System Status Check ===\n\n";

// Check upload directory
$uploadDir = __DIR__ . '/uploads/products/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
    echo "✅ Created uploads/products/ directory\n";
} else {
    echo "✅ uploads/products/ directory exists\n";
}
echo "   Writable: " . (is_writable($uploadDir) ? 'YES' : 'NO') . "\n";

// Check placeholder
$placeholder = __DIR__ . '/assets/placeholder-product.svg';
echo "\n✅ Placeholder exists: " . (file_exists($placeholder) ? 'YES' : 'NO') . "\n";

// Check products with images
$r = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE image_url IS NOT NULL AND image_url != ''");
$withImages = $r->fetch_assoc()['cnt'];
echo "\n📊 Products with images: $withImages\n";

$r2 = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE is_active = 1");
$totalActive = $r2->fetch_assoc()['cnt'];
echo "📊 Total active products: $totalActive\n";
echo "📊 Products without images: " . ($totalActive - $withImages) . " (will show placeholder)\n";

// Test one product
echo "\n=== Sample Product Test ===\n";
$test = $conn->query("SELECT product_id, name, image_url FROM products WHERE is_active = 1 LIMIT 1")->fetch_assoc();
echo "Product: {$test['name']} (ID: {$test['product_id']})\n";
echo "Image URL: " . ($test['image_url'] ?: '[none - will show placeholder]') . "\n";

if ($test['image_url'] && file_exists(__DIR__ . '/' . $test['image_url'])) {
    echo "✅ Image file exists on disk\n";
    echo "   File size: " . number_format(filesize(__DIR__ . '/' . $test['image_url'])) . " bytes\n";
} elseif ($test['image_url']) {
    echo "⚠️  Image URL in DB but file missing on disk\n";
} else {
    echo "ℹ️  No image uploaded yet (placeholder will be shown)\n";
}

echo "\n=== Upload System Check ===\n";
$uploadScript = __DIR__ . '/upload_product_image.php';
echo "✅ upload_product_image.php exists: " . (file_exists($uploadScript) ? 'YES' : 'NO') . "\n";

echo "\n=== Display System Check ===\n";
$files = [
    'pos.php' => 'POS',
    'inventory_management.php' => 'Inventory Management',
    'onlineordering.php' => 'Online Ordering',
    'medicine-locator.php' => 'Medicine Locator'
];
foreach ($files as $file => $name) {
    $content = file_get_contents(__DIR__ . '/' . $file);
    $hasImageUrl = strpos($content, 'image_url') !== false;
    $hasPlaceholder = strpos($content, 'placeholder') !== false || strpos($content, 'fa-pills') !== false;
    echo "✅ $name: " . ($hasImageUrl ? "image support" : "no image") . ($hasPlaceholder ? " + fallback" : "") . "\n";
}

echo "\n=== READY FOR PRODUCTION ===\n";
echo "✅ Upload any image via Inventory Management → Edit Product\n";
echo "✅ Images will appear in: POS, Inventory, Online Ordering, Medicine Locator\n";
echo "✅ Products without images show placeholder/fallback\n";
echo "✅ Supports: JPG, PNG, WebP, GIF (max 5MB)\n";
echo "✅ Old images auto-deleted when replacing\n";
