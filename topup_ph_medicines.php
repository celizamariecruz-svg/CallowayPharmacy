<?php
/**
 * Top-up Philippine medicine catalog without adding categories.
 * Goal: ensure each ACTIVE category has at least 21 products.
 *
 * Run: php topup_ph_medicines.php
 */

require_once 'db_connection.php';

echo "\n=== CATEGORY TOP-UP (PH PRODUCTS) ===\n";
echo "Rule: no new categories, target is 21 products per active category.\n\n";

$targetPerCategory = 21;

$candidatePool = [
    'Antibiotics' => [
        'Co-Trimoxazole 800mg/160mg Tablet',
        'Clindamycin 300mg Capsule',
        'Cefuroxime 500mg Tablet',
        'Doxycycline 100mg Capsule',
        'Levofloxacin 500mg Tablet',
        'Cefixime 200mg Capsule',
    ],
    'Gastrointestinal' => [
        'Pantoprazole 40mg Tablet',
        'Esomeprazole 40mg Capsule',
        'Rabeprazole 20mg Tablet',
        'Domperidone 10mg Tablet',
        'Mosapride 5mg Tablet',
        'Simethicone 40mg Chewable Tablet',
        'Lactulose Syrup 120mL',
        'Oral Probiotic Capsule 10 Billion CFU',
    ],
    'Skin Care & Dermatology' => [
        'Ketoconazole 2% Cream 15g',
        'Miconazole 2% Cream 15g',
        'Terbinafine 1% Cream 10g',
        'Mupirocin 2% Ointment 5g',
        'Clobetasol Propionate 0.05% Cream 15g',
        'Calamine Lotion 120mL',
        'Benzoyl Peroxide 5% Gel 30g',
        'Clindamycin 1% Topical Solution 30mL',
        'Permethrin 5% Cream 30g',
        'Selenium Sulfide 2.5% Shampoo 120mL',
    ],
    'Cardiovascular' => [
        'Amlodipine 10mg Tablet',
        'Losartan 100mg Tablet',
        'Valsartan 80mg Tablet',
        'Telmisartan 40mg Tablet',
        'Metoprolol 50mg Tablet',
        'Bisoprolol 5mg Tablet',
        'Hydrochlorothiazide 25mg Tablet',
        'Clopidogrel 75mg Tablet',
        'Rosuvastatin 10mg Tablet',
        'Simvastatin 20mg Tablet',
    ],
    'First Aid & Wound Care' => [
        'Povidone-Iodine 10% Solution 120mL',
        'Hydrogen Peroxide 3% 120mL',
        'Ethyl Alcohol 70% 500mL',
        'Sterile Gauze Pads 4x4 10s',
        'Micropore Surgical Tape 1in',
        'Elastic Bandage 2in',
        'Triangular Bandage 1pc',
        'Digital Thermometer',
        'Instant Cold Compress Pack',
        'Wound Dressing Set',
        'Cotton Balls 100s',
        'Cotton Buds 200s',
        'Adhesive Plaster Roll',
        'Silver Sulfadiazine 1% Cream 25g',
        'Chlorhexidine 4% Skin Cleanser 120mL',
    ],
    'Diabetes Care' => [
        'Metformin 1000mg Tablet',
        'Gliclazide MR 30mg Tablet',
        'Gliclazide 80mg Tablet',
        'Vildagliptin 50mg Tablet',
        'Sitagliptin 100mg Tablet',
        'Linagliptin 5mg Tablet',
        'Empagliflozin 10mg Tablet',
        'Dapagliflozin 10mg Tablet',
        'Pioglitazone 15mg Tablet',
        'Acarbose 50mg Tablet',
        'Blood Glucose Test Strips 50s',
        'Lancets 100s',
        'Insulin Syringe 1mL 10s',
        'Alcohol Swabs 100s',
        'Glucose Meter Device Kit',
    ],
    'Respiratory' => [
        'Salbutamol Inhaler 100mcg 200 Doses',
        'Budesonide Inhaler 200mcg 120 Doses',
        'Ipratropium Bromide Nebules 0.5mg',
        'Ambroxol 30mg Tablet',
        'Ambroxol 15mg/5mL Syrup',
        'Acetylcysteine 600mg Effervescent Tablet',
        'Carbocisteine 375mg Capsule',
        'Guaifenesin 100mg/5mL Syrup',
        'Levocetirizine 5mg Tablet',
        'Desloratadine 5mg Tablet',
        'Fluticasone Nasal Spray 120 Doses',
        'Oxymetazoline Nasal Spray 0.05%',
        'Sodium Chloride 0.9% Nasal Drops',
        'Nebulizer Mask Adult',
        'Nebulizer Mask Pediatric',
        'Peak Flow Meter',
    ],
    'Allergy & Antihistamines' => [
        'Loratadine 10mg Tablet',
        'Cetirizine 10mg Tablet',
        'Levocetirizine 5mg Tablet',
        'Fexofenadine 120mg Tablet',
        'Fexofenadine 180mg Tablet',
        'Desloratadine 5mg Tablet',
        'Chlorphenamine 4mg Tablet',
        'Diphenhydramine 25mg Capsule',
        'Ketotifen 1mg Tablet',
        'Mometasone Nasal Spray 120 Doses',
        'Fluticasone Propionate Nasal Spray 120 Doses',
        'Cetirizine Syrup 5mg/5mL',
        'Loratadine Syrup 5mg/5mL',
        'Allergy Relief Eye Drops 10mL',
        'Hydroxyzine 10mg Tablet',
        'Bilastine 20mg Tablet',
    ],
    'Feminine Care' => [
        'GynePro Feminine Wash 100mL',
        'Lactacyd Pro Sensitive 100mL',
        'Betadine Feminine Wash 100mL',
        'Canesten 500mg Vaginal Tablet',
        'Clotrimazole Vaginal Cream 20g',
        'Miconazole Vaginal Suppository 1200mg',
        'Fluconazole 150mg Capsule',
        'Napkin Pads Regular 8s',
        'Napkin Pads Overnight 8s',
        'Pantyliner 20s',
        'Tampons Regular 8s',
        'Menstrual Cup Medium',
        'Vaginal pH Test Kit',
        'Probiotic for Women 30 Capsules',
        'Cranberry Extract 500mg Capsule',
        'Intimate Wipes 20s',
    ],
    'Eye & Ear Care' => [
        'Tears Naturale Eye Drops 15mL',
        'Systane Ultra Eye Drops 10mL',
        'Optive Lubricant Eye Drops 10mL',
        'Artificial Tears Eye Drops 15mL',
        'Naphazoline Eye Drops 15mL',
        'Olopatadine Eye Drops 5mL',
        'Moxifloxacin Eye Drops 5mL',
        'Tobramycin Eye Drops 5mL',
        'Earwax Softener Drops 10mL',
        'Ciprofloxacin Ear Drops 10mL',
        'Ofloxacin Ear Drops 10mL',
        'Acetic Acid Ear Drops 10mL',
        'Sterile Eye Wash 100mL',
        'Eye Cup Wash Set',
        'Sterile Cotton Applicators 100s',
        'Night Eye Ointment 5g',
    ],
    'Baby & Pediatric' => [
        'Pediatrin Drops 30mL',
        'Pediatrin Syrup 120mL',
        'Infant Zinc Syrup 60mL',
        'Pediatric Multivitamin Drops 30mL',
        'Pediatric Multivitamin Syrup 120mL',
        'Oral Rehydration Salts Sachet',
        'Pediatric Paracetamol 120mg/5mL Syrup',
        'Pediatric Ibuprofen 100mg/5mL Syrup',
        'Nasal Aspirator Infant',
        'Digital Thermometer Flexible Tip',
        'Baby Saline Drops 15mL',
        'Diaper Rash Cream 30g',
        'Baby Probiotic Drops 10mL',
        'Infant Colic Drops 30mL',
        'Pediatric Cough Syrup 60mL',
        'Pediatric Allergy Syrup 60mL',
    ],
    'Personal Care' => [
        'Ethyl Alcohol 70% 250mL',
        'Hand Sanitizer 70% 100mL',
        'Hand Sanitizer 70% 500mL',
        'Antibacterial Hand Soap 250mL',
        'Body Wash Sensitive Skin 250mL',
        'Moisturizing Lotion 200mL',
        'Petroleum Jelly 50g',
        'Lip Balm SPF 15',
        'Anti-Dandruff Shampoo 170mL',
        'Mouth Gargle Antiseptic 250mL',
        'Deodorant Roll-On 50mL',
        'Facial Cleanser Gentle 100mL',
        'Sunscreen SPF50 50mL',
        'Insect Repellent Lotion 50mL',
        'Head Lice Treatment Shampoo 60mL',
        'Intimate Wash pH Balanced 100mL',
    ],
    'Oral Care' => [
        'Colgate Total Toothpaste 150g',
        'Colgate Sensitive Pro-Relief 110g',
        'Sensodyne Repair & Protect 100g',
        'Sensodyne Fresh Mint 100g',
        'Hapee Toothpaste 150g',
        'Closeup Toothpaste 150g',
        'Listerine Cool Mint 250mL',
        'Listerine Total Care 250mL',
        'Oracare Mouthwash 120mL',
        'Hexetidine Mouthwash 120mL',
        'Dental Floss 50m',
        'Interdental Brush Set',
        'Soft Bristle Toothbrush Adult',
        'Soft Bristle Toothbrush Kids',
        'Oral B Toothbrush Medium',
        'Biotene Oral Rinse 237mL',
        'Benzocaine Oral Gel 10g',
        'Chlorhexidine Mouthwash 250mL',
        'Fluoride Mouth Rinse 250mL',
        'Orthodontic Wax 5g',
    ],
];

$categoryIdMap = [];
$catRes = $conn->query("SELECT category_id, category_name FROM categories");
while ($row = $catRes->fetch_assoc()) {
    $categoryIdMap[$row['category_name']] = (int)$row['category_id'];
}

$countRes = $conn->query("SELECT COALESCE(c.category_name,p.category,'Uncategorized') AS category_name, COUNT(*) AS total FROM products p LEFT JOIN categories c ON p.category_id=c.category_id WHERE (p.is_active=1 OR p.is_active IS NULL) GROUP BY COALESCE(c.category_name,p.category,'Uncategorized') HAVING total > 0");
$currentCounts = [];
while ($row = $countRes->fetch_assoc()) {
    $currentCounts[$row['category_name']] = (int)$row['total'];
}

$insertStmt = $conn->prepare(
    "INSERT INTO products (
        name, generic_name, brand_name, dosage_form, strength, age_group, description,
        type, category, category_id, price, cost_price, selling_price, stock_quantity,
        pieces_per_box, price_per_piece, sell_by_piece, expiring_quantity,
        expiry_date, location, is_active, requires_prescription
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$insertStmt) {
    die("Prepare failed: " . $conn->error . "\n");
}

$insertedTotal = 0;
$perCategoryAdded = [];

foreach ($currentCounts as $categoryName => $count) {
    $need = max(0, $targetPerCategory - $count);
    if ($need <= 0) {
        continue;
    }

    if (!isset($candidatePool[$categoryName])) {
        echo "⚠ No candidate pool configured for: {$categoryName} (need {$need})\n";
        continue;
    }

    $categoryId = $categoryIdMap[$categoryName] ?? null;
    if (!$categoryId) {
        echo "⚠ Missing category_id for: {$categoryName}, skipping\n";
        continue;
    }

    $existing = [];
    $safeCategory = $conn->real_escape_string($categoryName);
    $existingRes = $conn->query("SELECT LOWER(TRIM(name)) AS n FROM products WHERE category = '{$safeCategory}' OR category_id = {$categoryId}");
    while ($er = $existingRes->fetch_assoc()) {
        $existing[$er['n']] = true;
    }

    $added = 0;
    foreach ($candidatePool[$categoryName] as $productName) {
        if ($added >= $need) {
            break;
        }

        $normalized = mb_strtolower(trim($productName));
        if (isset($existing[$normalized])) {
            continue;
        }

        $generic = null;
        $brand = null;
        $dosageForm = null;
        $strength = null;
        $ageGroup = 'all';
        $description = 'PH market top-up medicine item';
        $type = 'medicine';
        $price = 120.00;
        $costPrice = 75.00;
        $sellingPrice = 120.00;
        $stockQty = 40;
        $piecesPerBox = 0;
        $pricePerPiece = 0.00;
        $sellByPiece = 0;
        $expiringQty = 0;
        $expiryDate = '2028-12-31';
        $location = 'Top-up Rack';
        $isActive = 1;
        $requiresRx = (in_array($categoryName, ['Antibiotics', 'Cardiovascular', 'Diabetes Care', 'Respiratory'], true)) ? 1 : 0;

        $insertStmt->bind_param(
            "sssssssssidddiidiissii",
            $productName,
            $generic,
            $brand,
            $dosageForm,
            $strength,
            $ageGroup,
            $description,
            $type,
            $categoryName,
            $categoryId,
            $price,
            $costPrice,
            $sellingPrice,
            $stockQty,
            $piecesPerBox,
            $pricePerPiece,
            $sellByPiece,
            $expiringQty,
            $expiryDate,
            $location,
            $isActive,
            $requiresRx
        );

        if ($insertStmt->execute()) {
            $added++;
            $insertedTotal++;
            $existing[$normalized] = true;
        }
    }

    $perCategoryAdded[$categoryName] = $added;

    if ($added < $need) {
        echo "⚠ {$categoryName}: added {$added}/{$need} (candidate pool exhausted)\n";
    } else {
        echo "✅ {$categoryName}: added {$added}, target reached\n";
    }
}

echo "\nInserted total: {$insertedTotal}\n\n";

echo "--- FINAL ACTIVE COUNTS ---\n";
$finalRes = $conn->query("SELECT COALESCE(c.category_name,p.category,'Uncategorized') AS category_name, COUNT(*) AS total FROM products p LEFT JOIN categories c ON p.category_id=c.category_id WHERE (p.is_active=1 OR p.is_active IS NULL) GROUP BY COALESCE(c.category_name,p.category,'Uncategorized') HAVING total > 0 ORDER BY total DESC, category_name ASC");
while ($fr = $finalRes->fetch_assoc()) {
    $flag = ((int)$fr['total'] >= $targetPerCategory) ? 'OK' : 'LOW';
    echo str_pad($fr['category_name'], 30) . " | " . str_pad((string)$fr['total'], 3, ' ', STR_PAD_LEFT) . " | {$flag}\n";
}

echo "\nDone.\n";
