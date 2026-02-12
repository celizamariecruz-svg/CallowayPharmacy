<?php
/**
 * Seed: Real Philippine Pharmacy Medicine Data
 * Includes popular brands with variants, dosage forms, per-piece pricing
 * 
 * Run once:  php seed_ph_medicines.php
 */
require_once 'db_connection.php';

echo "<pre>\n=== Seeding Philippine Pharmacy Products ===\n\n";

// First ensure we have categories
$categories = [
    'Pain Relief & Fever',
    'Antibiotics',
    'Cough & Cold',
    'Vitamins & Supplements',
    'Gastrointestinal',
    'Allergy & Antihistamines',
    'Skin Care & Dermatology',
    'First Aid & Wound Care',
    'Eye & Ear Care',
    'Respiratory',
    'Cardiovascular',
    'Diabetes Care',
    'Personal Care',
    'Baby & Pediatric'
];

$catIds = [];
foreach ($categories as $cat) {
    $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?) ON DUPLICATE KEY UPDATE category_id=LAST_INSERT_ID(category_id)");
    $stmt->bind_param("s", $cat);
    $stmt->execute();
    $catIds[$cat] = $conn->insert_id ?: $conn->query("SELECT category_id FROM categories WHERE category_name='$cat'")->fetch_assoc()['category_id'];
}
echo "✅ Categories seeded (" . count($catIds) . ")\n\n";

// Clear existing sample medicines
$conn->query("DELETE FROM products WHERE product_id > 0");
$conn->query("ALTER TABLE products AUTO_INCREMENT = 1");
echo "🗑️  Cleared old sample products\n\n";

/**
 * Product data array
 * [name, generic_name, brand_name, dosage_form, strength, age_group, category, 
 *  selling_price, cost_price, stock_qty, pieces_per_box, price_per_piece, sell_by_piece,
 *  expiry_date, location, description]
 */
$products = [
    // ===== PARACETAMOL BRANDS (5 brands, multiple variants) =====
    // Biogesic
    ['Biogesic 500mg Tablet', 'Paracetamol', 'Biogesic (Unilab)', 'Tablet', '500mg', 'adult', 'Pain Relief & Fever',
     100.00, 65.00, 200, 100, 2.50, 1, '2027-06-15', 'Shelf A1', 'Most trusted paracetamol brand in the Philippines. For fever and mild to moderate pain.'],
    ['Biogesic 250mg Pediatric Drops', 'Paracetamol', 'Biogesic (Unilab)', 'Drops', '100mg/mL', 'pediatric', 'Pain Relief & Fever',
     65.00, 42.00, 80, 0, 0, 0, '2027-03-20', 'Shelf A1', 'Biogesic drops for infants and toddlers. Fever relief for ages 0-2 years.'],
    ['Biogesic for Kids Suspension 120mg/5mL', 'Paracetamol', 'Biogesic (Unilab)', 'Syrup', '120mg/5mL', 'pediatric', 'Pain Relief & Fever',
     85.00, 55.00, 60, 0, 0, 0, '2027-04-10', 'Shelf A1', 'Orange-flavored syrup for children ages 2-12 years.'],

    // Tempra
    ['Tempra 500mg Tablet', 'Paracetamol', 'Tempra (Taisho)', 'Tablet', '500mg', 'adult', 'Pain Relief & Fever',
     105.00, 68.00, 150, 100, 2.50, 1, '2027-08-01', 'Shelf A1', 'Fast-acting paracetamol. Relieves headache, toothache, fever.'],
    ['Tempra Forte 250mg/5mL Syrup', 'Paracetamol', 'Tempra (Taisho)', 'Syrup', '250mg/5mL', 'pediatric', 'Pain Relief & Fever',
     120.00, 78.00, 45, 0, 0, 0, '2027-05-15', 'Shelf A1', 'Strawberry-flavored syrup for children 6-12 years.'],
    ['Tempra Drops 100mg/mL', 'Paracetamol', 'Tempra (Taisho)', 'Drops', '100mg/mL', 'pediatric', 'Pain Relief & Fever',
     75.00, 48.00, 55, 0, 0, 0, '2027-03-01', 'Shelf A1', 'For infants 0-2 years. Fast fever relief with dropper.'],

    // Calpol
    ['Calpol 500mg Tablet', 'Paracetamol', 'Calpol (GSK)', 'Tablet', '500mg', 'adult', 'Pain Relief & Fever',
     95.00, 62.00, 180, 100, 2.00, 1, '2027-09-01', 'Shelf A2', 'Trusted paracetamol from GlaxoSmithKline for adults.'],
    ['Calpol 120mg/5mL Suspension', 'Paracetamol', 'Calpol (GSK)', 'Syrup', '120mg/5mL', 'pediatric', 'Pain Relief & Fever',
     90.00, 58.00, 40, 0, 0, 0, '2027-06-01', 'Shelf A2', 'Strawberry-flavored children\'s suspension. 2-6 years.'],
    ['Calpol 250mg/5mL Suspension', 'Paracetamol', 'Calpol (GSK)', 'Syrup', '250mg/5mL', 'pediatric', 'Pain Relief & Fever',
     110.00, 72.00, 35, 0, 0, 0, '2027-06-01', 'Shelf A2', 'For older children 6+ years. Sugar-free option.'],

    // Tylenol
    ['Tylenol 500mg Caplet', 'Paracetamol', 'Tylenol (Johnson & Johnson)', 'Caplet', '500mg', 'adult', 'Pain Relief & Fever',
     180.00, 120.00, 100, 50, 5.00, 1, '2027-10-01', 'Shelf A2', 'Premium imported paracetamol. Easy-to-swallow caplet form.'],
    ['Tylenol Extra Strength 650mg', 'Paracetamol', 'Tylenol (Johnson & Johnson)', 'Caplet', '650mg', 'adult', 'Pain Relief & Fever',
     220.00, 145.00, 60, 50, 6.00, 1, '2027-10-01', 'Shelf A2', 'Extra strength formula for stronger pain relief.'],

    // RiteMed Paracetamol (generic/budget)
    ['RiteMed Paracetamol 500mg', 'Paracetamol', 'RiteMed', 'Tablet', '500mg', 'adult', 'Pain Relief & Fever',
     48.00, 28.00, 500, 100, 1.50, 1, '2027-12-01', 'Shelf A3', 'Affordable generic paracetamol. Same quality, lower price.'],
    ['RiteMed Paracetamol 250mg/5mL Syrup', 'Paracetamol', 'RiteMed', 'Syrup', '250mg/5mL', 'pediatric', 'Pain Relief & Fever',
     45.00, 25.00, 80, 0, 0, 0, '2027-08-01', 'Shelf A3', 'Budget-friendly children\'s paracetamol syrup.'],

    // ===== IBUPROFEN BRANDS (4 brands) =====
    ['Advil 200mg Tablet', 'Ibuprofen', 'Advil (Pfizer)', 'Tablet', '200mg', 'adult', 'Pain Relief & Fever',
     180.00, 115.00, 120, 100, 4.00, 1, '2027-07-01', 'Shelf A3', 'World\'s #1 ibuprofen brand. For pain, fever, and inflammation.'],
    ['Advil Liqui-Gels 200mg', 'Ibuprofen', 'Advil (Pfizer)', 'Softgel', '200mg', 'adult', 'Pain Relief & Fever',
     250.00, 165.00, 50, 40, 8.00, 1, '2027-07-01', 'Shelf A3', 'Liquid-filled capsule for faster absorption.'],
    ['Medicol Advance 200mg', 'Ibuprofen', 'Medicol (Unilab)', 'Softgel', '200mg', 'adult', 'Pain Relief & Fever',
     120.00, 78.00, 200, 100, 3.50, 1, '2027-09-01', 'Shelf A4', 'Filipino brand. Fast-acting ibuprofen softgel.'],
    ['Medicol Advance 400mg', 'Ibuprofen', 'Medicol (Unilab)', 'Softgel', '400mg', 'adult', 'Pain Relief & Fever',
     180.00, 115.00, 100, 50, 5.50, 1, '2027-09-01', 'Shelf A4', 'Double-strength for severe headache and body pain.'],
    ['Dolfenal 250mg', 'Mefenamic Acid', 'Dolfenal (Unilab)', 'Capsule', '250mg', 'adult', 'Pain Relief & Fever',
     90.00, 58.00, 180, 100, 2.50, 1, '2027-08-15', 'Shelf A4', 'For dysmenorrhea, toothache, and post-operative pain.'],
    ['Dolfenal 500mg', 'Mefenamic Acid', 'Dolfenal (Unilab)', 'Capsule', '500mg', 'adult', 'Pain Relief & Fever',
     150.00, 95.00, 120, 100, 4.00, 1, '2027-08-15', 'Shelf A4', 'Strong mefenamic acid for moderate to severe pain.'],
    ['Flanax 275mg', 'Naproxen Sodium', 'Flanax (Bayer)', 'Tablet', '275mg', 'adult', 'Pain Relief & Fever',
     160.00, 105.00, 80, 50, 5.00, 1, '2027-11-01', 'Shelf A4', 'Long-lasting pain relief (up to 12 hours).'],
    ['Alaxan FR', 'Ibuprofen + Paracetamol', 'Alaxan (Unilab)', 'Caplet', '200mg/325mg', 'adult', 'Pain Relief & Fever',
     115.00, 72.00, 250, 100, 3.00, 1, '2027-10-01', 'Shelf A5', 'Combination analgesic. Body pain + headache + fever.'],

    // ===== ANTIBIOTICS =====
    ['Amoxicillin 500mg (RiteMed)', 'Amoxicillin', 'RiteMed', 'Capsule', '500mg', 'adult', 'Antibiotics',
     96.00, 55.00, 300, 100, 3.50, 1, '2027-05-01', 'Shelf B1', 'Broad-spectrum antibiotic. Prescription required.'],
    ['Amoxicillin 250mg/5mL Suspension', 'Amoxicillin', 'RiteMed', 'Syrup', '250mg/5mL', 'pediatric', 'Antibiotics',
     85.00, 48.00, 60, 0, 0, 0, '2027-04-01', 'Shelf B1', 'Pediatric antibiotic suspension. Bubble gum flavor.'],
    ['Augmentin 625mg', 'Amoxicillin + Clavulanate', 'Augmentin (GSK)', 'Tablet', '500mg/125mg', 'adult', 'Antibiotics',
     950.00, 620.00, 40, 14, 70.00, 1, '2027-06-01', 'Shelf B1', 'Protected antibiotic. For resistant infections.'],
    ['Cefalexin 500mg (RiteMed)', 'Cefalexin', 'RiteMed', 'Capsule', '500mg', 'adult', 'Antibiotics',
     120.00, 72.00, 200, 100, 4.00, 1, '2027-07-01', 'Shelf B2', 'Cephalosporin antibiotic for skin and UTI.'],
    ['Azithromycin 500mg (Zithromax)', 'Azithromycin', 'Zithromax (Pfizer)', 'Tablet', '500mg', 'adult', 'Antibiotics',
     380.00, 245.00, 30, 3, 130.00, 1, '2027-08-01', 'Shelf B2', '3-day course antibiotic for respiratory infections.'],
    ['Co-Amoxiclav 625mg (Curam)', 'Amoxicillin + Clavulanate', 'Curam', 'Tablet', '500mg/125mg', 'adult', 'Antibiotics',
     650.00, 420.00, 50, 14, 48.00, 1, '2027-06-15', 'Shelf B2', 'Cost-effective co-amoxiclav alternative.'],
    ['Metronidazole 500mg', 'Metronidazole', 'Flagyl (Sanofi)', 'Tablet', '500mg', 'adult', 'Antibiotics',
     85.00, 50.00, 150, 100, 3.00, 1, '2027-09-01', 'Shelf B3', 'For anaerobic infections, dental infections.'],

    // ===== COUGH & COLD =====
    ['Neozep Forte', 'Phenylephrine + Chlorphenamine + Paracetamol', 'Neozep (Unilab)', 'Tablet', '10mg/2mg/500mg', 'adult', 'Cough & Cold',
     80.00, 48.00, 300, 100, 2.50, 1, '2027-07-01', 'Shelf C1', 'Complete cold relief. Decongests, dries runny nose, relieves headache.'],
    ['Neozep Non-Drowsy', 'Phenylephrine + Paracetamol', 'Neozep (Unilab)', 'Tablet', '10mg/500mg', 'adult', 'Cough & Cold',
     90.00, 55.00, 200, 100, 3.00, 1, '2027-07-01', 'Shelf C1', 'Daytime cold relief without drowsiness.'],
    ['Bioflu', 'Phenylephrine + Chlorphenamine + Paracetamol', 'Bioflu (Unilab)', 'Tablet', '10mg/2mg/500mg', 'adult', 'Cough & Cold',
     85.00, 50.00, 250, 100, 2.50, 1, '2027-08-01', 'Shelf C1', 'Multi-symptom flu relief. Trusted Filipino brand.'],
    ['Decolgen Forte', 'Phenylpropanolamine + Paracetamol + Chlorphenamine', 'Decolgen (Unilab)', 'Tablet', '25mg/500mg/2mg', 'adult', 'Cough & Cold',
     75.00, 45.00, 180, 100, 2.50, 1, '2027-09-01', 'Shelf C2', 'Strong decongestant. For severe nasal congestion.'],
    ['Tuseran Forte Capsule', 'Dextromethorphan + Phenylpropanolamine + Paracetamol', 'Tuseran (Unilab)', 'Capsule', '15mg/25mg/325mg', 'adult', 'Cough & Cold',
     95.00, 60.00, 150, 100, 3.00, 1, '2027-10-01', 'Shelf C2', 'For dry non-productive cough with cold symptoms.'],
    ['Solmux 500mg', 'Carbocisteine', 'Solmux (Unilab)', 'Capsule', '500mg', 'adult', 'Cough & Cold',
     120.00, 75.00, 200, 100, 3.50, 1, '2027-11-01', 'Shelf C3', '#1 mucolytic brand. Dissolves phlegm for wet cough.'],
    ['Solmux Pediatric 100mg/5mL', 'Carbocisteine', 'Solmux (Unilab)', 'Syrup', '100mg/5mL', 'pediatric', 'Cough & Cold',
     95.00, 60.00, 70, 0, 0, 0, '2027-08-01', 'Shelf C3', 'Children\'s mucolytic. Strawberry flavor.'],
    ['Lagundi 600mg (Ascof)', 'Vitex Negundo Extract', 'Ascof (Pascual)', 'Tablet', '600mg', 'adult', 'Cough & Cold',
     70.00, 40.00, 200, 100, 2.00, 1, '2027-12-01', 'Shelf C3', 'Herbal cough remedy. DOH-approved. No drowsiness.'],
    ['Robitussin DM Syrup', 'Dextromethorphan + Guaifenesin', 'Robitussin (Pfizer)', 'Syrup', '10mg/100mg per 5mL', 'adult', 'Cough & Cold',
     180.00, 115.00, 40, 0, 0, 0, '2027-07-15', 'Shelf C4', 'Imported cough syrup. Expectorant + suppressant.'],

    // ===== VITAMINS & SUPPLEMENTS =====
    ['Centrum Advance', 'Multivitamins + Minerals', 'Centrum (Pfizer)', 'Tablet', 'Multi', 'adult', 'Vitamins & Supplements',
     650.00, 420.00, 40, 100, 8.50, 1, '2028-01-01', 'Shelf D1', 'Complete A to Zinc formula. World\'s #1 multivitamin.'],
    ['Enervon C', 'Multivitamins + Vitamin C', 'Enervon (Unilab)', 'Tablet', 'Multi + 500mg C', 'adult', 'Vitamins & Supplements',
     280.00, 180.00, 200, 100, 4.50, 1, '2028-03-01', 'Shelf D1', 'For energy, immunity, and stress-resistance.'],
    ['Stresstabs 600+Iron', 'B-Complex + Vitamin C + Iron', 'Stresstabs (Pfizer)', 'Tablet', '600mg C + Iron', 'adult', 'Vitamins & Supplements',
     420.00, 275.00, 80, 100, 6.00, 1, '2028-02-01', 'Shelf D1', 'Anti-stress formula with Iron for active adults.'],
    ['Immunpro Vitamin C+Zinc', 'Sodium Ascorbate + Zinc', 'Immunpro (Unilab)', 'Tablet', '500mg + 10mg', 'adult', 'Vitamins & Supplements',
     320.00, 205.00, 150, 100, 5.00, 1, '2028-06-01', 'Shelf D2', 'Double immune booster. Non-acidic Vitamin C formula.'],
    ['Ceelin Plus Drops', 'Ascorbic Acid + Zinc', 'Ceelin (Unilab)', 'Drops', '100mg + 2.5mg/mL', 'pediatric', 'Vitamins & Supplements',
     180.00, 115.00, 50, 0, 0, 0, '2027-12-01', 'Shelf D2', 'Vitamin C + Zinc drops for babies. Apple flavor.'],
    ['Ceelin Plus Syrup 120mL', 'Ascorbic Acid + Zinc', 'Ceelin (Unilab)', 'Syrup', '100mg + 5mg/5mL', 'pediatric', 'Vitamins & Supplements',
     195.00, 125.00, 60, 0, 0, 0, '2027-12-01', 'Shelf D2', 'Popular children\'s vitamin C syrup. 2-12 years.'],
    ['Cherifer PGM 10-22', 'Chlorella GF + Zinc + Taurine', 'Cherifer (Unilab)', 'Capsule', 'CGF + Zinc', 'pediatric', 'Vitamins & Supplements',
     380.00, 245.00, 45, 30, 14.00, 1, '2028-01-01', 'Shelf D3', 'Growth supplement for teens 10-22 years. With Chlorella Growth Factor.'],
    ['Myra E 400 IU', 'Vitamin E (d-Alpha Tocopherol)', 'Myra (Unilab)', 'Softgel', '400 IU', 'adult', 'Vitamins & Supplements',
     280.00, 180.00, 100, 30, 10.00, 1, '2028-04-01', 'Shelf D3', 'Beauty vitamin. Protects skin from free radical damage.'],

    // ===== GASTROINTESTINAL =====
    ['Kremil-S Tablet', 'Aluminum Hydroxide + Magnesium Hydroxide + Simethicone', 'Kremil-S (Unilab)', 'Tablet', '178mg/233mg/30mg', 'adult', 'Gastrointestinal',
     85.00, 50.00, 250, 100, 2.50, 1, '2027-10-01', 'Shelf E1', '#1 antacid in the Philippines. Relieves heartburn, gas, acidity.'],
    ['Buscopan 10mg', 'Hyoscine Butylbromide', 'Buscopan (Sanofi)', 'Tablet', '10mg', 'adult', 'Gastrointestinal',
     150.00, 95.00, 120, 100, 4.50, 1, '2027-11-01', 'Shelf E1', 'Antispasmodic for stomach cramps and abdominal pain.'],
    ['Diatabs', 'Loperamide', 'Diatabs (Unilab)', 'Tablet', '2mg', 'adult', 'Gastrointestinal',
     50.00, 30.00, 300, 100, 2.00, 1, '2027-12-01', 'Shelf E2', 'Anti-diarrheal. Stops diarrhea fast.'],
    ['Loperamide 2mg (RiteMed)', 'Loperamide', 'RiteMed', 'Capsule', '2mg', 'adult', 'Gastrointestinal',
     30.00, 18.00, 400, 100, 1.50, 1, '2027-12-01', 'Shelf E2', 'Affordable generic anti-diarrheal.'],
    ['Omeprazole 20mg (RiteMed)', 'Omeprazole', 'RiteMed', 'Capsule', '20mg', 'adult', 'Gastrointestinal',
     80.00, 45.00, 200, 100, 3.00, 1, '2028-01-01', 'Shelf E3', 'Proton pump inhibitor. For GERD and acid reflux.'],
    ['Gaviscon Advance Syrup', 'Alginate + Potassium Bicarbonate', 'Gaviscon (Reckitt)', 'Syrup', '500mg/mL', 'adult', 'Gastrointestinal',
     350.00, 225.00, 35, 0, 0, 0, '2027-09-01', 'Shelf E3', 'Forms protective barrier on top of stomach acid.'],

    // ===== ALLERGY & ANTIHISTAMINES =====
    ['Claritin 10mg', 'Loratadine', 'Claritin (Bayer)', 'Tablet', '10mg', 'adult', 'Allergy & Antihistamines',
     220.00, 140.00, 80, 30, 9.00, 1, '2027-11-01', 'Shelf F1', 'Non-drowsy 24-hour allergy relief.'],
    ['Zyrtec 10mg', 'Cetirizine', 'Zyrtec (UCB)', 'Tablet', '10mg', 'adult', 'Allergy & Antihistamines',
     180.00, 115.00, 100, 30, 8.00, 1, '2027-10-01', 'Shelf F1', 'Fast-acting antihistamine. For rhinitis, urticaria.'],
    ['Cetirizine 10mg (RiteMed)', 'Cetirizine', 'RiteMed', 'Tablet', '10mg', 'adult', 'Allergy & Antihistamines',
     40.00, 22.00, 350, 100, 1.50, 1, '2028-01-01', 'Shelf F2', 'Budget antihistamine. Same active ingredient as Zyrtec.'],
    ['Benadryl Syrup 60mL', 'Diphenhydramine', 'Benadryl (Johnson & Johnson)', 'Syrup', '12.5mg/5mL', 'all', 'Allergy & Antihistamines',
     145.00, 92.00, 50, 0, 0, 0, '2027-08-01', 'Shelf F2', 'Antihistamine + cough suppressant syrup.'],
    ['Phenylephrine + Chlorphenamine Syrup (Disudrin)', 'Phenylephrine + Chlorphenamine', 'Disudrin (Unilab)', 'Syrup', '2.5mg/1mg per 5mL', 'pediatric', 'Allergy & Antihistamines',
     85.00, 52.00, 45, 0, 0, 0, '2027-09-01', 'Shelf F2', 'Pediatric decongestant + antihistamine for colds.'],

    // ===== SKIN CARE & DERMATOLOGY =====
    ['Betadine Antiseptic Solution 60mL', 'Povidone-Iodine', 'Betadine (Mundipharma)', 'Solution', '10%', 'all', 'Skin Care & Dermatology',
     95.00, 60.00, 100, 0, 0, 0, '2028-01-01', 'Shelf G1', 'Broad-spectrum antiseptic. For wound disinfection.'],
    ['Betadine Ointment 10g', 'Povidone-Iodine', 'Betadine (Mundipharma)', 'Ointment', '10%', 'all', 'Skin Care & Dermatology',
     55.00, 35.00, 80, 0, 0, 0, '2028-01-01', 'Shelf G1', 'Topical antiseptic ointment for cuts and wounds.'],
    ['Canesten Cream 10g', 'Clotrimazole', 'Canesten (Bayer)', 'Cream', '1%', 'adult', 'Skin Care & Dermatology',
     185.00, 120.00, 40, 0, 0, 0, '2027-12-01', 'Shelf G2', 'Antifungal cream. For athlete\'s foot, ringworm.'],
    ['Hydrocortisone 1% Cream (RiteMed)', 'Hydrocortisone', 'RiteMed', 'Cream', '1%', 'adult', 'Skin Care & Dermatology',
     45.00, 28.00, 60, 0, 0, 0, '2027-11-01', 'Shelf G2', 'Mild corticosteroid. For eczema, rash, insect bites.'],
    ['Bepanthen Ointment 30g', 'Dexpanthenol', 'Bepanthen (Bayer)', 'Ointment', '5%', 'all', 'Skin Care & Dermatology',
     320.00, 205.00, 30, 0, 0, 0, '2028-03-01', 'Shelf G3', 'For diaper rash, dry skin, nipple care. pH-balanced.'],

    // ===== EYE & EAR CARE =====
    ['Visine Classic Eye Drops', 'Tetrahydrozoline', 'Visine (Johnson & Johnson)', 'Drops', '0.05%', 'adult', 'Eye & Ear Care',
     165.00, 105.00, 40, 0, 0, 0, '2027-10-01', 'Shelf H1', 'Redness reliever eye drops. Clears red eyes fast.'],
    ['Refresh Tears Lubricant', 'Carboxymethylcellulose', 'Refresh (Allergan)', 'Drops', '0.5%', 'all', 'Eye & Ear Care',
     280.00, 180.00, 35, 0, 0, 0, '2027-09-01', 'Shelf H1', 'Artificial tears for dry eyes. Preservative-free.'],

    // ===== RESPIRATORY =====
    ['Salbutamol 2mg Tablet (RiteMed)', 'Salbutamol', 'RiteMed', 'Tablet', '2mg', 'adult', 'Respiratory',
     35.00, 20.00, 200, 100, 1.50, 1, '2027-08-01', 'Shelf I1', 'Bronchodilator for asthma and wheezing.'],
    ['Salbutamol Nebule 2.5mg/2.5mL', 'Salbutamol', 'Ventolin (GSK)', 'Nebule', '2.5mg/2.5mL', 'all', 'Respiratory',
     320.00, 205.00, 60, 20, 18.00, 1, '2027-07-01', 'Shelf I1', 'For nebulizer use. Acute asthma relief.'],
    ['Montelukast 10mg (RiteMed)', 'Montelukast', 'RiteMed', 'Tablet', '10mg', 'adult', 'Respiratory',
     150.00, 90.00, 80, 30, 6.50, 1, '2027-11-01', 'Shelf I2', 'Leukotriene inhibitor. For asthma maintenance.'],
    ['Montelukast 4mg Chewable', 'Montelukast', 'Singulair (MSD)', 'Chewable', '4mg', 'pediatric', 'Respiratory',
     420.00, 270.00, 30, 28, 16.00, 1, '2027-10-01', 'Shelf I2', 'Cherry-flavored chewable for pediatric asthma.'],

    // ===== CARDIOVASCULAR =====
    ['Amlodipine 5mg (RiteMed)', 'Amlodipine', 'RiteMed', 'Tablet', '5mg', 'adult', 'Cardiovascular',
     45.00, 25.00, 300, 100, 1.50, 1, '2028-01-01', 'Shelf J1', 'Calcium channel blocker for hypertension.'],
    ['Losartan 50mg (RiteMed)', 'Losartan', 'RiteMed', 'Tablet', '50mg', 'adult', 'Cardiovascular',
     80.00, 45.00, 250, 100, 2.50, 1, '2028-01-01', 'Shelf J1', 'ARB for hypertension and kidney protection.'],
    ['Atorvastatin 20mg (RiteMed)', 'Atorvastatin', 'RiteMed', 'Tablet', '20mg', 'adult', 'Cardiovascular',
     95.00, 55.00, 200, 100, 3.00, 1, '2028-02-01', 'Shelf J2', 'Statin for high cholesterol management.'],
    ['Aspirin 80mg EC (Aspilets)', 'Aspirin', 'Aspilets EC (Unilab)', 'Tablet', '80mg', 'adult', 'Cardiovascular',
     65.00, 38.00, 300, 100, 2.00, 1, '2028-06-01', 'Shelf J2', 'Low-dose aspirin for cardiovascular protection.'],

    // ===== DIABETES CARE =====
    ['Metformin 500mg (RiteMed)', 'Metformin', 'RiteMed', 'Tablet', '500mg', 'adult', 'Diabetes Care',
     55.00, 30.00, 400, 100, 2.00, 1, '2028-03-01', 'Shelf K1', 'First-line diabetes medication. Take with meals.'],
    ['Metformin 850mg (RiteMed)', 'Metformin', 'RiteMed', 'Tablet', '850mg', 'adult', 'Diabetes Care',
     75.00, 42.00, 200, 100, 2.50, 1, '2028-03-01', 'Shelf K1', 'Higher-dose metformin for better glycemic control.'],
    ['Glimepiride 2mg (RiteMed)', 'Glimepiride', 'RiteMed', 'Tablet', '2mg', 'adult', 'Diabetes Care',
     120.00, 72.00, 100, 30, 5.00, 1, '2028-01-01', 'Shelf K2', 'Sulfonylurea. Stimulates insulin secretion.'],

    // ===== PERSONAL CARE =====
    ['Betadine Feminine Wash 50mL', 'Povidone-Iodine', 'Betadine (Mundipharma)', 'Wash', '0.5%', 'adult', 'Personal Care',
     85.00, 55.00, 50, 0, 0, 0, '2028-06-01', 'Shelf L1', 'Daily intimate hygiene. Prevents infection.'],
    ['Lactacyd Feminine Wash 60mL', 'Lactic Acid', 'Lactacyd (Sanofi)', 'Wash', '1%', 'adult', 'Personal Care',
     95.00, 60.00, 45, 0, 0, 0, '2028-06-01', 'Shelf L1', 'pH-balanced daily feminine wash.'],

    // ===== FIRST AID =====
    ['Band-Aid Assorted 20s', 'Adhesive Bandage', 'Band-Aid (Johnson & Johnson)', 'Bandage', 'Assorted', 'all', 'First Aid & Wound Care',
     65.00, 40.00, 100, 0, 0, 0, '2029-01-01', 'Shelf M1', 'Flexible fabric adhesive bandages. Assorted sizes.'],
    ['Katinko Ointment 10g', 'Methyl Salicylate + Menthol + Camphor', 'Katinko', 'Ointment', 'Multi', 'adult', 'First Aid & Wound Care',
     35.00, 20.00, 150, 0, 0, 0, '2028-06-01', 'Shelf M1', 'Iconic Filipino analgesic balm. Insect bites, pain, itch.'],
    ['White Flower Embrocation Oil', 'Menthol + Camphor + Eucalyptus', 'White Flower', 'Oil', 'Multi', 'adult', 'First Aid & Wound Care',
     50.00, 30.00, 80, 0, 0, 0, '2028-12-01', 'Shelf M2', 'Traditional remedy for headache, dizziness, motion sickness.'],
    ['Efficascent Oil Extra 50mL', 'Methyl Salicylate + Menthol', 'Efficascent (Unilab)', 'Oil', 'Multi', 'adult', 'First Aid & Wound Care',
     85.00, 52.00, 60, 0, 0, 0, '2028-09-01', 'Shelf M2', 'Liniment oil for muscle and body pain.'],

    // ===== BABY & PEDIATRIC =====
    ['Zinc Drops (RiteMed)', 'Zinc Sulfate', 'RiteMed', 'Drops', '10mg/mL', 'pediatric', 'Baby & Pediatric',
     65.00, 40.00, 50, 0, 0, 0, '2027-10-01', 'Shelf N1', 'WHO-recommended zinc for diarrhea in children.'],
    ['ORS Powder (Oresol)', 'Oral Rehydration Salts', 'Oresol', 'Powder', 'Standard', 'all', 'Baby & Pediatric',
     8.00, 4.00, 500, 0, 0, 0, '2028-06-01', 'Shelf N1', 'Rehydration salts for diarrhea. Mix with 1L water.'],
    ['Polynerv 500 (Neurogen-E)', 'Vitamin B-Complex', 'Neurogen-E (Unilab)', 'Capsule', 'B1+B6+B12', 'adult', 'Vitamins & Supplements',
     350.00, 225.00, 60, 30, 13.00, 1, '2028-01-01', 'Shelf D3', 'Nerve nourishing vitamin. For numbness and nerve pain.'],
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
$isActive = 1;
foreach ($products as $p) {
    [$name, $generic, $brand, $form, $str, $age, $catName, 
     $sellPrice, $costPrice, $stockQty, $perBox, $pricePiece, $byPiece,
     $expiry, $loc, $desc] = $p;

    $catId = $catIds[$catName] ?? null;
    
    $insertStmt->bind_param(
        "sssssssiddiidisssi",
        $name, $generic, $brand, $form, $str, $age,
        $catName, $catId, $sellPrice, $costPrice, $stockQty,
        $perBox, $pricePiece, $byPiece,
        $expiry, $loc, $desc, $isActive
    );
    
    if ($insertStmt->execute()) {
        $count++;
    } else {
        echo "❌  Failed to insert: $name — {$conn->error}\n";
    }
}

echo "✅ Inserted $count products successfully!\n\n";
echo "=== Seed Complete ===\n</pre>";
$conn->close();
