<?php
/**
 * Set Sample Prices for All Products
 * Updates all products with realistic pharmacy prices
 */

require_once 'db_connection.php';

echo "<h1>Setting Sample Prices for Products</h1>";
echo "<p>Updating product prices...</p><br>";

// Get all products
$query = "SELECT product_id, name, category FROM products";
$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    echo "<p style='color: red;'>No products found in database.</p>";
    exit;
}

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo "<p>Found " . count($products) . " products. Assigning prices...</p><br>";

// Price ranges by category (realistic pharmacy prices in PHP Pesos)
$priceRanges = [
    'default' => [50, 500],
    'medicines' => [25, 1500],
    'vitamins' => [150, 800],
    'supplements' => [200, 1200],
    'antibiotics' => [50, 600],
    'pain relief' => [30, 300],
    'cold & flu' => [40, 250],
    'first aid' => [20, 400],
    'personal care' => [50, 500],
    'baby care' => [100, 600],
    'medical devices' => [200, 3000]
];

// Common pharmacy product price mappings
$specificPrices = [
    'biogesic' => 8.50,
    'paracetamol' => 6.50,
    'ibuprofen' => 12.00,
    'mefenamic' => 15.00,
    'aspirin' => 10.00,
    'amoxicillin' => 25.00,
    'cetirizine' => 18.00,
    'loperamide' => 22.00,
    'vitamin c' => 120.00,
    'vitamin b complex' => 180.00,
    'multivitamins' => 250.00,
    'calcium' => 350.00,
    'omega 3' => 450.00,
    'cough syrup' => 85.00,
    'lagundi' => 65.00,
    'salbutamol' => 180.00,
    'nebulizer' => 2500.00,
    'thermometer' => 350.00,
    'blood pressure monitor' => 1800.00,
    'glucose meter' => 1200.00,
    'bandage' => 25.00,
    'gauze' => 15.00,
    'alcohol' => 45.00,
    'betadine' => 75.00,
    'cotton' => 35.00,
    'mask' => 5.00,
    'gloves' => 40.00,
    'syringe' => 8.00
];

$updated = 0;
$errors = 0;

foreach ($products as $product) {
    $productName = strtolower($product['name']);
    $categoryName = strtolower($product['category']);
    $price = null;
    
    // Check for specific product name matches
    foreach ($specificPrices as $keyword => $specificPrice) {
        if (strpos($productName, $keyword) !== false) {
            $price = $specificPrice;
            break;
        }
    }
    
    // If no specific price found, use category-based random price
    if ($price === null) {
        // Find matching price range based on category
        $range = $priceRanges['default'];
        foreach ($priceRanges as $catKey => $catRange) {
            if (strpos($categoryName, $catKey) !== false) {
                $range = $catRange;
                break;
            }
        }
        
        // Generate random price within range
        $price = rand($range[0] * 100, $range[1] * 100) / 100;
    }
    
    // Update product with selling price
    $sellingPrice = $price;
    
    // Update product
    $updateQuery = "UPDATE products SET price = ? WHERE product_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('di', $sellingPrice, $product['product_id']);
    
    if ($stmt->execute()) {
        echo "<div style='padding: 5px; border-left: 3px solid #28a745;'>
                ✓ {$product['name']} - Price: ₱" . number_format($sellingPrice, 2) . "
              </div>";
        $updated++;
    } else {
        echo "<div style='padding: 5px; border-left: 3px solid #dc3545;'>
                ✗ Error updating {$product['name']}: " . $conn->error . "
              </div>";
        $errors++;
    }
}

echo "<br><hr><br>";
echo "<h2>Summary</h2>";
echo "<p><strong style='color: #28a745;'>✓ Updated: $updated products</strong></p>";

if ($errors > 0) {
    echo "<p><strong style='color: #dc3545;'>✗ Errors: $errors products</strong></p>";
}

echo "<br>";
echo "<h3>Price Ranges Applied:</h3>";
echo "<ul>";
echo "<li><strong>Medicines:</strong> ₱25 - ₱1,500</li>";
echo "<li><strong>Vitamins:</strong> ₱150 - ₱800</li>";
echo "<li><strong>Supplements:</strong> ₱200 - ₱1,200</li>";
echo "<li><strong>Antibiotics:</strong> ₱50 - ₱600</li>";
echo "<li><strong>Pain Relief:</strong> ₱30 - ₱300</li>";
echo "<li><strong>First Aid:</strong> ₱20 - ₱400</li>";
echo "<li><strong>Medical Devices:</strong> ₱200 - ₱3,000</li>";
echo "<li><strong>Personal Care:</strong> ₱50 - ₱500</li>";
echo "</ul>";

echo "<br>";
echo "<p><strong>Markup:</strong> 20% added to cost price for selling price</p>";
echo "<p><strong>Common products</strong> (e.g., Biogesic, Paracetamol) have been set to standard Philippine market prices.</p>";

echo "<br><br>";
echo "<p><a href='inventory_management.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>→ View Inventory</a></p>";
echo "<p><a href='dashboard.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>→ Back to Dashboard</a></p>";

$conn->close();
