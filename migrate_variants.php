<?php
/**
 * Migration: Add variant fields, per-piece selling, and image support to products
 * Run once: php migrate_variants.php  OR  visit in browser
 */
require_once 'db_connection.php';

echo "<pre>\n=== Migration: Product Variants & Per-Piece Selling ===\n\n";

$alterations = [
    "ADD COLUMN IF NOT EXISTS generic_name VARCHAR(150) NULL AFTER name",
    "ADD COLUMN IF NOT EXISTS brand_name VARCHAR(150) NULL AFTER generic_name",
    "ADD COLUMN IF NOT EXISTS dosage_form VARCHAR(80) NULL AFTER brand_name",
    "ADD COLUMN IF NOT EXISTS strength VARCHAR(80) NULL AFTER dosage_form",
    "ADD COLUMN IF NOT EXISTS age_group ENUM('all','adult','pediatric') DEFAULT 'all' AFTER strength",
    "ADD COLUMN IF NOT EXISTS pieces_per_box INT DEFAULT 0 AFTER reorder_level",
    "ADD COLUMN IF NOT EXISTS price_per_piece DECIMAL(10,2) DEFAULT 0.00 AFTER pieces_per_box",
    "ADD COLUMN IF NOT EXISTS sell_by_piece TINYINT(1) DEFAULT 0 AFTER price_per_piece",
    "ADD COLUMN IF NOT EXISTS image_url VARCHAR(500) NULL AFTER sell_by_piece"
];

foreach ($alterations as $alt) {
    $sql = "ALTER TABLE products $alt";
    if ($conn->query($sql)) {
        echo "✅  $alt\n";
    } else {
        if (strpos($conn->error, 'Duplicate column') !== false) {
            echo "⏭️  Column already exists — skipped: $alt\n";
        } else {
            echo "❌  Error: {$conn->error} — $alt\n";
        }
    }
}

// Create uploads directory
$uploadDir = __DIR__ . '/uploads/products';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
    echo "\n✅  Created directory: uploads/products/\n";
} else {
    echo "\n⏭️  Directory uploads/products/ already exists\n";
}

echo "\n=== Migration Complete ===\n</pre>";
$conn->close();
