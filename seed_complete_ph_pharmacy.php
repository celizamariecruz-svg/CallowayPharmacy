<?php
/**
 * COMPLETE Philippine Pharmacy Product Catalog
 * 270+ real products across 16 categories
 * Includes: prescription flag, variant info, per-piece pricing
 *
 * Run once:  php seed_complete_ph_pharmacy.php
 */
require_once 'db_connection.php';

echo "<pre>\n=== Complete Philippine Pharmacy Catalog Seeder ===\n\n";

// --- 1. Migration: add requires_prescription column ---
$conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS requires_prescription TINYINT(1) NOT NULL DEFAULT 0");
echo "✅ Column 'requires_prescription' ensured\n\n";

// --- 2. Ensure categories ---
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
    'Baby & Pediatric',
    'Sexual Health',
    'Feminine Care',
    'Oral Care'
];

$catIds = [];
foreach ($categories as $cat) {
    $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?) ON DUPLICATE KEY UPDATE category_id=LAST_INSERT_ID(category_id)");
    $stmt->bind_param("s", $cat);
    $stmt->execute();
    $catIds[$cat] = $conn->insert_id ?: $conn->query("SELECT category_id FROM categories WHERE category_name='" . $conn->real_escape_string($cat) . "'")->fetch_assoc()['category_id'];
}
echo "✅ Categories seeded (" . count($catIds) . ")\n\n";

// --- 3. Clear existing products ---
$conn->query("SET FOREIGN_KEY_CHECKS=0");
$conn->query("DELETE FROM products WHERE product_id > 0");
$conn->query("ALTER TABLE products AUTO_INCREMENT = 1");
$conn->query("SET FOREIGN_KEY_CHECKS=1");
echo "🗑️  Cleared old products\n\n";

/**
 * Data format per product:
 * [name, generic_name, brand_name, dosage_form, strength, age_group, category_key,
 *  selling_price, cost_price, stock_qty, pieces_per_box, price_per_piece, sell_by_piece,
 *  expiry_date, location, description, requires_prescription]
 */
$products = [

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                   PAIN RELIEF & FEVER                       ║
    // ╚══════════════════════════════════════════════════════════════╝

    // --- Paracetamol ---
    ['Biogesic 500mg Tablet', 'Paracetamol', 'Biogesic (Unilab)', 'Tablet', '500mg', 'adult', 'Pain Relief & Fever',
     100.00, 65.00, 200, 100, 2.50, 1, '2027-06-15', 'Shelf A1', 'Most trusted paracetamol in PH. Fever & mild-moderate pain.', 0],
    ['Biogesic Pediatric Drops 100mg/mL', 'Paracetamol', 'Biogesic (Unilab)', 'Drops', '100mg/mL', 'pediatric', 'Pain Relief & Fever',
     65.00, 42.00, 80, 0, 0, 0, '2027-03-20', 'Shelf A1', 'Drops for infants 0-2 years.', 0],
    ['Biogesic for Kids Suspension 120mg/5mL', 'Paracetamol', 'Biogesic (Unilab)', 'Syrup', '120mg/5mL', 'pediatric', 'Pain Relief & Fever',
     85.00, 55.00, 60, 0, 0, 0, '2027-04-10', 'Shelf A1', 'Orange-flavored syrup for children 2-12 years.', 0],
    ['Tempra 500mg Tablet', 'Paracetamol', 'Tempra (Taisho)', 'Tablet', '500mg', 'adult', 'Pain Relief & Fever',
     105.00, 68.00, 150, 100, 2.50, 1, '2027-08-01', 'Shelf A1', 'Fast-acting. Headache, toothache, fever.', 0],
    ['Tempra Forte 250mg/5mL Syrup', 'Paracetamol', 'Tempra (Taisho)', 'Syrup', '250mg/5mL', 'pediatric', 'Pain Relief & Fever',
     120.00, 78.00, 45, 0, 0, 0, '2027-05-15', 'Shelf A1', 'Strawberry syrup for children 6-12 yrs.', 0],
    ['Tempra Drops 100mg/mL', 'Paracetamol', 'Tempra (Taisho)', 'Drops', '100mg/mL', 'pediatric', 'Pain Relief & Fever',
     75.00, 48.00, 55, 0, 0, 0, '2027-03-01', 'Shelf A1', 'Infant drops with dropper. 0-2 yrs.', 0],
    ['Calpol 500mg Tablet', 'Paracetamol', 'Calpol (GSK)', 'Tablet', '500mg', 'adult', 'Pain Relief & Fever',
     95.00, 62.00, 180, 100, 2.00, 1, '2027-09-01', 'Shelf A2', 'GSK paracetamol for adults.', 0],
    ['Calpol 120mg/5mL Suspension', 'Paracetamol', 'Calpol (GSK)', 'Syrup', '120mg/5mL', 'pediatric', 'Pain Relief & Fever',
     90.00, 58.00, 40, 0, 0, 0, '2027-06-01', 'Shelf A2', 'Strawberry suspension. 2-6 years.', 0],
    ['Calpol 250mg/5mL Suspension', 'Paracetamol', 'Calpol (GSK)', 'Syrup', '250mg/5mL', 'pediatric', 'Pain Relief & Fever',
     110.00, 72.00, 35, 0, 0, 0, '2027-06-01', 'Shelf A2', 'For children 6+ years.', 0],
    ['Tylenol 500mg Caplet', 'Paracetamol', 'Tylenol (J&J)', 'Caplet', '500mg', 'adult', 'Pain Relief & Fever',
     180.00, 120.00, 100, 50, 5.00, 1, '2027-10-01', 'Shelf A2', 'Premium imported caplet.', 0],
    ['Tylenol Extra Strength 650mg', 'Paracetamol', 'Tylenol (J&J)', 'Caplet', '650mg', 'adult', 'Pain Relief & Fever',
     220.00, 145.00, 60, 50, 6.00, 1, '2027-10-01', 'Shelf A2', 'Extra strength for stronger pain.', 0],
    ['RiteMed Paracetamol 500mg', 'Paracetamol', 'RiteMed', 'Tablet', '500mg', 'adult', 'Pain Relief & Fever',
     48.00, 28.00, 500, 100, 1.50, 1, '2027-12-01', 'Shelf A3', 'Affordable generic. Same quality, lower price.', 0],
    ['RiteMed Paracetamol 250mg/5mL Syrup', 'Paracetamol', 'RiteMed', 'Syrup', '250mg/5mL', 'pediatric', 'Pain Relief & Fever',
     45.00, 25.00, 80, 0, 0, 0, '2027-08-01', 'Shelf A3', 'Budget-friendly children\'s syrup.', 0],

    // --- Ibuprofen ---
    ['Advil 200mg Tablet', 'Ibuprofen', 'Advil (Pfizer)', 'Tablet', '200mg', 'adult', 'Pain Relief & Fever',
     180.00, 115.00, 120, 100, 4.00, 1, '2027-07-01', 'Shelf A3', '#1 ibuprofen brand. Pain, fever, inflammation.', 0],
    ['Advil Liqui-Gels 200mg', 'Ibuprofen', 'Advil (Pfizer)', 'Softgel', '200mg', 'adult', 'Pain Relief & Fever',
     250.00, 165.00, 50, 40, 8.00, 1, '2027-07-01', 'Shelf A3', 'Liquid-filled capsule, faster absorption.', 0],
    ['Medicol Advance 200mg', 'Ibuprofen', 'Medicol (Unilab)', 'Softgel', '200mg', 'adult', 'Pain Relief & Fever',
     120.00, 78.00, 200, 100, 3.50, 1, '2027-09-01', 'Shelf A4', 'Fast-acting ibuprofen softgel.', 0],
    ['Medicol Advance 400mg', 'Ibuprofen', 'Medicol (Unilab)', 'Softgel', '400mg', 'adult', 'Pain Relief & Fever',
     180.00, 115.00, 100, 50, 5.50, 1, '2027-09-01', 'Shelf A4', 'Double-strength for severe pain.', 0],
    ['Ibuprofen 200mg (RiteMed)', 'Ibuprofen', 'RiteMed', 'Tablet', '200mg', 'adult', 'Pain Relief & Fever',
     55.00, 32.00, 300, 100, 2.00, 1, '2027-11-01', 'Shelf A4', 'Generic ibuprofen 200mg.', 0],

    // --- Mefenamic Acid ---
    ['Dolfenal 250mg', 'Mefenamic Acid', 'Dolfenal (Unilab)', 'Capsule', '250mg', 'adult', 'Pain Relief & Fever',
     90.00, 58.00, 180, 100, 2.50, 1, '2027-08-15', 'Shelf A4', 'Dysmenorrhea, toothache, post-operative pain.', 1],
    ['Dolfenal 500mg', 'Mefenamic Acid', 'Dolfenal (Unilab)', 'Capsule', '500mg', 'adult', 'Pain Relief & Fever',
     150.00, 95.00, 120, 100, 4.00, 1, '2027-08-15', 'Shelf A4', 'Strong mefenamic acid.', 1],
    ['Ponstan 500mg', 'Mefenamic Acid', 'Ponstan (Pfizer)', 'Capsule', '500mg', 'adult', 'Pain Relief & Fever',
     280.00, 185.00, 60, 100, 7.00, 1, '2027-10-01', 'Shelf A5', 'Premium mefenamic acid brand.', 1],
    ['Mefenamic Acid 500mg (RiteMed)', 'Mefenamic Acid', 'RiteMed', 'Capsule', '500mg', 'adult', 'Pain Relief & Fever',
     65.00, 38.00, 250, 100, 2.00, 1, '2027-09-01', 'Shelf A5', 'Generic mefenamic acid.', 1],

    // --- Other analgesics ---
    ['Flanax 275mg', 'Naproxen Sodium', 'Flanax (Bayer)', 'Tablet', '275mg', 'adult', 'Pain Relief & Fever',
     160.00, 105.00, 80, 50, 5.00, 1, '2027-11-01', 'Shelf A5', 'Long-lasting relief up to 12 hours.', 0],
    ['Alaxan FR', 'Ibuprofen + Paracetamol', 'Alaxan (Unilab)', 'Caplet', '200mg/325mg', 'adult', 'Pain Relief & Fever',
     115.00, 72.00, 250, 100, 3.00, 1, '2027-10-01', 'Shelf A5', 'Combination. Body pain + headache + fever.', 0],
    ['Bayer Aspirin 500mg', 'Aspirin', 'Bayer', 'Tablet', '500mg', 'adult', 'Pain Relief & Fever',
     120.00, 78.00, 80, 50, 3.50, 1, '2028-01-01', 'Shelf A5', 'Classic aspirin for pain and fever.', 0],
    ['Celebrex 200mg', 'Celecoxib', 'Celebrex (Pfizer)', 'Capsule', '200mg', 'adult', 'Pain Relief & Fever',
     48.00, 32.00, 40, 10, 48.00, 1, '2027-09-01', 'Shelf A6', 'COX-2 inhibitor for arthritis and pain.', 1],
    ['Tramadol 50mg', 'Tramadol', 'Generic', 'Capsule', '50mg', 'adult', 'Pain Relief & Fever',
     85.00, 52.00, 30, 30, 6.00, 1, '2027-06-01', 'Shelf A6', 'Moderate to severe pain. Controlled substance.', 1],
    ['Naproxen 500mg (RiteMed)', 'Naproxen Sodium', 'RiteMed', 'Tablet', '500mg', 'adult', 'Pain Relief & Fever',
     72.00, 42.00, 80, 50, 4.00, 1, '2027-10-01', 'Shelf A6', 'Generic naproxen.', 1],
    ['Midol Complete', 'Acetaminophen+Caffeine+Pyrilamine', 'Midol', 'Caplet', '500mg/60mg/15mg', 'adult', 'Pain Relief & Fever',
     320.00, 210.00, 25, 24, 13.33, 0, '2027-12-01', 'Shelf A6', 'For menstrual cramp relief.', 0],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                       ANTIBIOTICS                           ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Amoxicillin 500mg (RiteMed)', 'Amoxicillin', 'RiteMed', 'Capsule', '500mg', 'adult', 'Antibiotics',
     96.00, 55.00, 300, 100, 3.50, 1, '2027-05-01', 'Shelf B1', 'Broad-spectrum antibiotic.', 1],
    ['Amoxicillin 250mg/5mL Suspension', 'Amoxicillin', 'RiteMed', 'Syrup', '250mg/5mL', 'pediatric', 'Antibiotics',
     85.00, 48.00, 60, 0, 0, 0, '2027-04-01', 'Shelf B1', 'Pediatric antibiotic. Bubble gum flavor.', 1],
    ['Amoxicillin 500mg (Amoxil)', 'Amoxicillin', 'Amoxil (GSK)', 'Capsule', '500mg', 'adult', 'Antibiotics',
     180.00, 110.00, 120, 100, 5.50, 1, '2027-06-01', 'Shelf B1', 'Original branded amoxicillin.', 1],
    ['Augmentin 625mg', 'Amoxicillin + Clavulanate', 'Augmentin (GSK)', 'Tablet', '500mg/125mg', 'adult', 'Antibiotics',
     950.00, 620.00, 40, 14, 70.00, 1, '2027-06-01', 'Shelf B1', 'Protected antibiotic for resistant infections.', 1],
    ['Co-Amoxiclav 625mg (Curam)', 'Amoxicillin + Clavulanate', 'Curam', 'Tablet', '500mg/125mg', 'adult', 'Antibiotics',
     650.00, 420.00, 50, 14, 48.00, 1, '2027-06-15', 'Shelf B2', 'Cost-effective co-amoxiclav.', 1],
    ['Cefalexin 500mg (RiteMed)', 'Cefalexin', 'RiteMed', 'Capsule', '500mg', 'adult', 'Antibiotics',
     120.00, 72.00, 200, 100, 4.00, 1, '2027-07-01', 'Shelf B2', 'Cephalosporin for skin/UTI.', 1],
    ['Cefuroxime 500mg (Zinnat)', 'Cefuroxime', 'Zinnat (GSK)', 'Tablet', '500mg', 'adult', 'Antibiotics',
     650.00, 420.00, 30, 14, 48.00, 1, '2027-08-01', 'Shelf B2', '2nd-gen cephalosporin. RTI, UTI, skin infections.', 1],
    ['Cefuroxime 500mg (RiteMed)', 'Cefuroxime', 'RiteMed', 'Tablet', '500mg', 'adult', 'Antibiotics',
     350.00, 220.00, 50, 14, 26.00, 1, '2027-08-01', 'Shelf B2', 'Generic cefuroxime.', 1],
    ['Azithromycin 500mg (Zithromax)', 'Azithromycin', 'Zithromax (Pfizer)', 'Tablet', '500mg', 'adult', 'Antibiotics',
     380.00, 245.00, 30, 3, 130.00, 1, '2027-08-01', 'Shelf B3', '3-day course for respiratory infections.', 1],
    ['Azithromycin 500mg (RiteMed)', 'Azithromycin', 'RiteMed', 'Tablet', '500mg', 'adult', 'Antibiotics',
     180.00, 110.00, 50, 3, 62.00, 1, '2027-08-01', 'Shelf B3', 'Generic azithromycin.', 1],
    ['Ciprofloxacin 500mg', 'Ciprofloxacin', 'Generic', 'Tablet', '500mg', 'adult', 'Antibiotics',
     95.00, 55.00, 150, 100, 3.50, 1, '2027-07-01', 'Shelf B3', 'Fluoroquinolone for UTI, RTI.', 1],
    ['Levofloxacin 500mg', 'Levofloxacin', 'Generic', 'Tablet', '500mg', 'adult', 'Antibiotics',
     120.00, 72.00, 80, 50, 5.50, 1, '2027-07-01', 'Shelf B3', 'Fluoroquinolone. Pneumonia, sinusitis.', 1],
    ['Doxycycline 100mg', 'Doxycycline', 'Generic', 'Capsule', '100mg', 'adult', 'Antibiotics',
     60.00, 35.00, 200, 100, 2.50, 1, '2027-09-01', 'Shelf B4', 'Tetracycline. Acne, chlamydia, malaria prophylaxis.', 1],
    ['Clindamycin 300mg', 'Clindamycin', 'Generic', 'Capsule', '300mg', 'adult', 'Antibiotics',
     180.00, 110.00, 60, 100, 5.50, 1, '2027-08-01', 'Shelf B4', 'Skin/soft tissue infections. Dental infections.', 1],
    ['Erythromycin 500mg', 'Erythromycin', 'Generic', 'Tablet', '500mg', 'adult', 'Antibiotics',
     85.00, 50.00, 100, 100, 3.00, 1, '2027-07-01', 'Shelf B4', 'Macrolide antibiotic. Penicillin-allergic alternative.', 1],
    ['Metronidazole 500mg (Flagyl)', 'Metronidazole', 'Flagyl (Sanofi)', 'Tablet', '500mg', 'adult', 'Antibiotics',
     85.00, 50.00, 150, 100, 3.00, 1, '2027-09-01', 'Shelf B4', 'Anaerobic/dental/parasitic infections.', 1],
    ['Cotrimoxazole 800/160mg (Bactrim)', 'Sulfamethoxazole+Trimethoprim', 'Bactrim (Roche)', 'Tablet', '800/160mg', 'adult', 'Antibiotics',
     120.00, 75.00, 80, 100, 4.00, 1, '2027-08-01', 'Shelf B5', 'UTI, ear infections, bronchitis.', 1],
    ['Nitrofurantoin 100mg', 'Nitrofurantoin', 'Generic', 'Capsule', '100mg', 'adult', 'Antibiotics',
     95.00, 58.00, 60, 100, 3.50, 1, '2027-06-01', 'Shelf B5', 'Specifically for urinary tract infections.', 1],
    ['Fluconazole 150mg', 'Fluconazole', 'Generic', 'Capsule', '150mg', 'adult', 'Antibiotics',
     45.00, 25.00, 80, 1, 45.00, 0, '2027-10-01', 'Shelf B5', 'Single-dose antifungal for yeast infections.', 1],
    ['Clotrimazole 500mg Pessary', 'Clotrimazole', 'Canesten (Bayer)', 'Pessary', '500mg', 'adult', 'Antibiotics',
     350.00, 225.00, 25, 1, 350.00, 0, '2027-10-01', 'Shelf B5', 'Single-dose vaginal antifungal.', 0],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                      COUGH & COLD                           ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Solmux 500mg Capsule', 'Carbocisteine', 'Solmux (Unilab)', 'Capsule', '500mg', 'adult', 'Cough & Cold',
     120.00, 78.00, 250, 100, 3.00, 1, '2027-09-01', 'Shelf C1', 'Mucolytic. Thins & expels phlegm.', 0],
    ['Solmux 250mg/5mL Syrup', 'Carbocisteine', 'Solmux (Unilab)', 'Syrup', '250mg/5mL', 'pediatric', 'Cough & Cold',
     95.00, 60.00, 80, 0, 0, 0, '2027-07-01', 'Shelf C1', 'Mucolytic syrup for kids 2-12 yrs.', 0],
    ['Solmux Kiddie Syrup', 'Carbocisteine', 'Solmux (Unilab)', 'Syrup', '100mg/5mL', 'pediatric', 'Cough & Cold',
     75.00, 48.00, 60, 0, 0, 0, '2027-07-01', 'Shelf C1', 'Lower dose for younger children.', 0],
    ['Mucosolvan 30mg Tablet', 'Ambroxol', 'Mucosolvan (Boehringer)', 'Tablet', '30mg', 'adult', 'Cough & Cold',
     150.00, 95.00, 80, 30, 5.50, 1, '2027-10-01', 'Shelf C1', 'Mucolytic tablet.', 0],
    ['Mucosolvan Syrup 15mg/5mL', 'Ambroxol', 'Mucosolvan (Boehringer)', 'Syrup', '15mg/5mL', 'all', 'Cough & Cold',
     165.00, 105.00, 50, 0, 0, 0, '2027-08-01', 'Shelf C1', 'Ambroxol syrup for cough with phlegm.', 0],
    ['Robitussin DM Syrup', 'Dextromethorphan+Guaifenesin', 'Robitussin (Pfizer)', 'Syrup', '10mg/100mg per 5mL', 'adult', 'Cough & Cold',
     185.00, 120.00, 45, 0, 0, 0, '2027-09-01', 'Shelf C2', 'Cough suppressant + expectorant.', 0],
    ['Benadryl Cough Syrup', 'Diphenhydramine+Dextromethorphan', 'Benadryl (J&J)', 'Syrup', 'Multi', 'adult', 'Cough & Cold',
     165.00, 105.00, 40, 0, 0, 0, '2027-08-01', 'Shelf C2', 'Nighttime cough relief.', 0],
    ['Decolgen Tablet', 'Phenylpropanolamine+Chlorphenamine+Paracetamol', 'Decolgen (Unilab)', 'Tablet', 'Multi', 'adult', 'Cough & Cold',
     65.00, 40.00, 300, 100, 2.00, 1, '2027-11-01', 'Shelf C2', 'For colds, nasal congestion, fever.', 0],
    ['Decolgen No Drowse', 'Phenylephrine+Paracetamol', 'Decolgen (Unilab)', 'Caplet', 'Multi', 'adult', 'Cough & Cold',
     75.00, 48.00, 200, 100, 2.50, 1, '2027-11-01', 'Shelf C2', 'Non-drowsy cold relief.', 0],
    ['Neozep Forte Tablet', 'Phenylephrine+Chlorphenamine+Paracetamol', 'Neozep (Unilab)', 'Tablet', 'Multi', 'adult', 'Cough & Cold',
     70.00, 42.00, 300, 100, 2.00, 1, '2027-10-01', 'Shelf C3', 'Forte formula for severe colds.', 0],
    ['Neozep Non-Drowse', 'Phenylephrine+Paracetamol', 'Neozep (Unilab)', 'Caplet', 'Multi', 'adult', 'Cough & Cold',
     75.00, 48.00, 200, 100, 2.50, 1, '2027-10-01', 'Shelf C3', 'Daytime cold relief.', 0],
    ['Bioflu Tablet', 'Phenylephrine+Chlorphenamine+Paracetamol', 'Bioflu (Unilab)', 'Tablet', 'Multi', 'adult', 'Cough & Cold',
     85.00, 52.00, 250, 100, 2.50, 1, '2027-09-01', 'Shelf C3', 'For flu symptoms: fever, body pain, runny nose.', 0],
    ['Tuseran Forte Capsule', 'Dextromethorphan+Phenylpropanolamine+Paracetamol', 'Tuseran (Unilab)', 'Capsule', 'Multi', 'adult', 'Cough & Cold',
     80.00, 50.00, 150, 100, 2.50, 1, '2027-08-01', 'Shelf C3', 'For dry cough with colds & fever.', 0],
    ['Sinutab Tablet', 'Paracetamol+Phenylephrine', 'Sinutab (J&J)', 'Tablet', '500mg/5mg', 'adult', 'Cough & Cold',
     180.00, 115.00, 40, 20, 9.50, 1, '2027-10-01', 'Shelf C4', 'Sinus pressure & headache relief.', 0],
    ['Disudrin Tablet', 'Phenylpropanolamine+Chlorphenamine', 'Disudrin (Unilab)', 'Tablet', 'Multi', 'adult', 'Cough & Cold',
     55.00, 32.00, 200, 100, 1.50, 1, '2027-11-01', 'Shelf C4', 'Nasal decongestant.', 0],
    ['Fluimucil 600mg Effervescent', 'N-Acetylcysteine', 'Fluimucil (Zambon)', 'Effervescent', '600mg', 'adult', 'Cough & Cold',
     380.00, 245.00, 30, 10, 39.00, 1, '2027-10-01', 'Shelf C4', 'Dissolve in water. Mucolytic.', 0],
    ['Ascof Lagundi 600mg', 'Vitex negundo (Lagundi)', 'Ascof (Pascual)', 'Capsule', '600mg', 'adult', 'Cough & Cold',
     85.00, 52.00, 150, 100, 2.50, 1, '2027-12-01', 'Shelf C4', 'Herbal cough remedy. FDA-approved.', 0],
    ['Strepsils Lozenges', 'Amylmetacresol+Dichlorobenzyl alcohol', 'Strepsils (Reckitt)', 'Lozenge', 'Multi', 'adult', 'Cough & Cold',
     85.00, 55.00, 100, 16, 5.50, 1, '2028-01-01', 'Shelf C5', 'Sore throat relief lozenges.', 0],
    ['Vicks VapoRub 50g', 'Menthol+Camphor+Eucalyptus', 'Vicks (P&G)', 'Ointment', 'Multi', 'all', 'Cough & Cold',
     120.00, 78.00, 80, 0, 0, 0, '2028-06-01', 'Shelf C5', 'Topical cough suppressant & decongestant.', 0],
    ['Betadine Throat Spray', 'Povidone-Iodine', 'Betadine (Mundipharma)', 'Spray', '0.45%', 'adult', 'Cough & Cold',
     250.00, 165.00, 30, 0, 0, 0, '2027-12-01', 'Shelf C5', 'Antiseptic throat spray.', 0],
    ['Bactidol Mouthwash 250mL', 'Hexetidine', 'Bactidol (J&J)', 'Mouthwash', '0.1%', 'adult', 'Cough & Cold',
     180.00, 115.00, 40, 0, 0, 0, '2028-06-01', 'Shelf C5', 'Oral antiseptic for sore throat & mouth ulcers.', 0],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                  VITAMINS & SUPPLEMENTS                     ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Centrum Advance 100s', 'Multivitamins+Minerals', 'Centrum (Pfizer)', 'Tablet', 'Multi', 'adult', 'Vitamins & Supplements',
     1250.00, 830.00, 30, 100, 13.50, 0, '2028-06-01', 'Shelf D1', 'Complete A to Zinc multivitamin.', 0],
    ['Centrum Silver 30s', 'Multivitamins+Minerals', 'Centrum (Pfizer)', 'Tablet', 'Multi', 'adult', 'Vitamins & Supplements',
     650.00, 425.00, 25, 30, 22.00, 0, '2028-06-01', 'Shelf D1', 'For adults 50+. With Lutein & Lycopene.', 0],
    ['Enervon Multivitamins 30s', 'Vitamin B-Complex+C', 'Enervon (Unilab)', 'Tablet', 'Multi', 'adult', 'Vitamins & Supplements',
     240.00, 155.00, 80, 30, 8.50, 0, '2028-03-01', 'Shelf D1', 'Energy and immunity boost.', 0],
    ['Enervon-C 30s', 'Vitamin B-Complex+C+Iron+Zinc', 'Enervon (Unilab)', 'Tablet', 'Multi', 'adult', 'Vitamins & Supplements',
     280.00, 180.00, 60, 30, 10.00, 0, '2028-03-01', 'Shelf D1', 'With iron & zinc for blood health.', 0],
    ['Berocca 10s Effervescent', 'B-Vitamins+C+Calcium+Magnesium+Zinc', 'Berocca (Bayer)', 'Effervescent', 'Multi', 'adult', 'Vitamins & Supplements',
     320.00, 210.00, 40, 10, 33.00, 0, '2028-01-01', 'Shelf D2', 'Fizzy energy vitamin tablet.', 0],
    ['Conzace 30s', 'Vitamins A,C,E + Zinc', 'Conzace (Unilab)', 'Capsule', 'Multi', 'adult', 'Vitamins & Supplements',
     480.00, 310.00, 50, 30, 16.50, 0, '2028-06-01', 'Shelf D2', 'Antioxidant combination. Skin & immunity.', 0],
    ['Myra-E 400 IU 30s', 'Vitamin E', 'Myra (Unilab)', 'Capsule', '400 IU', 'adult', 'Vitamins & Supplements',
     350.00, 225.00, 60, 30, 12.00, 0, '2028-06-01', 'Shelf D2', 'Skin nourishing vitamin E.', 0],
    ['Fern-C 568.18mg 60s', 'Sodium Ascorbate', 'Fern-C', 'Capsule', '568.18mg', 'adult', 'Vitamins & Supplements',
     520.00, 340.00, 50, 60, 9.00, 0, '2028-09-01', 'Shelf D2', 'Non-acidic vitamin C.', 0],
    ['Celin 500mg 100s', 'Ascorbic Acid', 'Celin (GSK)', 'Tablet', '500mg', 'adult', 'Vitamins & Supplements',
     280.00, 180.00, 80, 100, 3.00, 1, '2028-06-01', 'Shelf D3', 'Classic vitamin C tablet.', 0],
    ['Immunpro 30s', 'Vitamin C + Zinc', 'Immunpro (Unilab)', 'Tablet', '500mg/10mg', 'adult', 'Vitamins & Supplements',
     280.00, 180.00, 80, 30, 10.00, 0, '2028-03-01', 'Shelf D3', 'Immunity booster.', 0],
    ['Stresstabs 30s', 'B-Complex+C+E+Zinc+Iron', 'Stresstabs (Pfizer)', 'Tablet', 'Multi', 'adult', 'Vitamins & Supplements',
     350.00, 225.00, 50, 30, 12.00, 0, '2028-06-01', 'Shelf D3', 'Stress formula multivitamin.', 0],
    ['Caltrate Plus 600+D 30s', 'Calcium+Vitamin D', 'Caltrate (Pfizer)', 'Tablet', '600mg/400IU', 'adult', 'Vitamins & Supplements',
     520.00, 340.00, 35, 30, 18.00, 0, '2028-09-01', 'Shelf D3', 'Bone health. Calcium + Vitamin D.', 0],
    ['Sangobion 30s', 'Iron+Folic Acid+B12+C', 'Sangobion (Merck)', 'Capsule', 'Multi', 'adult', 'Vitamins & Supplements',
     380.00, 245.00, 45, 30, 13.00, 0, '2028-06-01', 'Shelf D4', 'For iron-deficiency anemia.', 0],
    ['Iberet Folic 30s', 'Iron+Folic Acid+B-Complex+C', 'Iberet (Abbott)', 'Tablet', 'Multi', 'adult', 'Vitamins & Supplements',
     420.00, 270.00, 40, 30, 14.50, 0, '2028-06-01', 'Shelf D4', 'Iron supplement for pregnancy & anemia.', 0],
    ['Folic Acid 5mg 100s', 'Folic Acid', 'Generic', 'Tablet', '5mg', 'adult', 'Vitamins & Supplements',
     85.00, 48.00, 100, 100, 1.50, 1, '2028-03-01', 'Shelf D4', 'For pregnancy, anemia prevention.', 0],
    ['Ferrous Sulfate 325mg 100s', 'Ferrous Sulfate', 'Generic', 'Tablet', '325mg', 'adult', 'Vitamins & Supplements',
     65.00, 38.00, 120, 100, 1.50, 1, '2028-01-01', 'Shelf D4', 'Iron supplement for anemia.', 0],
    ['Neurogen-E 30s', 'Vitamin B1+B6+B12', 'Neurogen-E (Unilab)', 'Capsule', '300mg/100mg/1mg', 'adult', 'Vitamins & Supplements',
     350.00, 225.00, 60, 30, 13.00, 1, '2028-01-01', 'Shelf D5', 'Nerve nourishing vitamin. Numbness & nerve pain.', 0],
    ['Memo Plus Gold 30s', 'Bacopa monnieri', 'Memo Plus Gold', 'Capsule', '100mg', 'adult', 'Vitamins & Supplements',
     750.00, 490.00, 30, 30, 26.00, 0, '2028-03-01', 'Shelf D5', 'Memory & brain health supplement.', 0],
    ['Kirkland Fish Oil 1000mg 400s', 'Omega-3 Fish Oil', 'Kirkland', 'Softgel', '1000mg', 'adult', 'Vitamins & Supplements',
     980.00, 620.00, 20, 400, 2.50, 0, '2028-06-01', 'Shelf D5', 'Heart health. Omega-3 fatty acids.', 0],
    ['Kirkland Vitamin C 1000mg 500s', 'Ascorbic Acid', 'Kirkland', 'Tablet', '1000mg', 'adult', 'Vitamins & Supplements',
     850.00, 550.00, 20, 500, 1.80, 0, '2028-12-01', 'Shelf D5', 'High-dose vitamin C.', 0],
    ['Nature Made Vitamin D3 1000IU 300s', 'Cholecalciferol', 'Nature Made', 'Softgel', '1000IU', 'adult', 'Vitamins & Supplements',
     780.00, 510.00, 15, 300, 2.80, 0, '2028-09-01', 'Shelf D5', 'Bone, muscle, immune health.', 0],
    ['Osteocare 30s', 'Calcium+Magnesium+Zinc+D3', 'Osteocare (Vitabiotics)', 'Tablet', 'Multi', 'adult', 'Vitamins & Supplements',
     520.00, 340.00, 30, 30, 18.00, 0, '2028-06-01', 'Shelf D6', 'Comprehensive bone support.', 0],
    ['Skelan 500mg 30s', 'Glucosamine Sulfate', 'Skelan', 'Capsule', '500mg', 'adult', 'Vitamins & Supplements',
     450.00, 290.00, 25, 30, 15.50, 0, '2028-03-01', 'Shelf D6', 'Joint health supplement.', 0],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                    GASTROINTESTINAL                         ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Kremil-S Tablet', 'Aluminum/Magnesium Hydroxide+Simethicone', 'Kremil-S (Unilab)', 'Tablet', 'Multi', 'adult', 'Gastrointestinal',
     65.00, 40.00, 300, 100, 2.00, 1, '2027-12-01', 'Shelf E1', 'Antacid + anti-gas. Hyperacidity, ulcer pain.', 0],
    ['Kremil-S Advance', 'Famotidine+Magnesium Hydroxide+Calcium Carbonate', 'Kremil-S (Unilab)', 'Tablet', 'Multi', 'adult', 'Gastrointestinal',
     85.00, 52.00, 150, 60, 3.50, 1, '2027-12-01', 'Shelf E1', 'Stronger acid control formula.', 0],
    ['Maalox Suspension 240mL', 'Aluminum/Magnesium Hydroxide+Simethicone', 'Maalox (Sanofi)', 'Suspension', 'Multi', 'adult', 'Gastrointestinal',
     250.00, 165.00, 40, 0, 0, 0, '2027-10-01', 'Shelf E1', 'Liquid antacid for heartburn.', 0],
    ['Gaviscon Advance 150mL', 'Sodium Alginate+Potassium Bicarbonate', 'Gaviscon (Reckitt)', 'Suspension', 'Multi', 'adult', 'Gastrointestinal',
     380.00, 250.00, 30, 0, 0, 0, '2027-11-01', 'Shelf E1', 'Forms raft on stomach. Acid reflux relief.', 0],
    ['Omeprazole 20mg (RiteMed)', 'Omeprazole', 'RiteMed', 'Capsule', '20mg', 'adult', 'Gastrointestinal',
     85.00, 48.00, 200, 100, 3.00, 1, '2027-09-01', 'Shelf E2', 'Proton pump inhibitor. Ulcers, GERD.', 1],
    ['Omeprazole 40mg (RiteMed)', 'Omeprazole', 'RiteMed', 'Capsule', '40mg', 'adult', 'Gastrointestinal',
     120.00, 72.00, 100, 100, 4.50, 1, '2027-09-01', 'Shelf E2', 'Higher dose for severe GERD.', 1],
    ['Losec MUPS 20mg', 'Omeprazole', 'Losec (AstraZeneca)', 'Tablet', '20mg', 'adult', 'Gastrointestinal',
     380.00, 250.00, 30, 14, 28.00, 1, '2027-10-01', 'Shelf E2', 'Original branded omeprazole.', 1],
    ['Nexium 40mg', 'Esomeprazole', 'Nexium (AstraZeneca)', 'Tablet', '40mg', 'adult', 'Gastrointestinal',
     520.00, 340.00, 20, 14, 38.00, 1, '2027-10-01', 'Shelf E2', 'Next-gen PPI. Stronger acid suppression.', 1],
    ['Imodium 2mg', 'Loperamide', 'Imodium (J&J)', 'Capsule', '2mg', 'adult', 'Gastrointestinal',
     120.00, 78.00, 80, 20, 6.50, 1, '2027-08-01', 'Shelf E3', 'Anti-diarrheal. Fast relief.', 0],
    ['Diatabs 2mg', 'Loperamide', 'Diatabs (Unilab)', 'Tablet', '2mg', 'adult', 'Gastrointestinal',
     65.00, 38.00, 200, 100, 2.00, 1, '2027-12-01', 'Shelf E3', 'Affordable anti-diarrheal.', 0],
    ['Buscopan 10mg', 'Hyoscine Butylbromide', 'Buscopan (Boehringer)', 'Tablet', '10mg', 'adult', 'Gastrointestinal',
     150.00, 95.00, 60, 40, 4.50, 1, '2027-09-01', 'Shelf E3', 'Antispasmodic. Stomach cramps, colic.', 0],
    ['Dulcolax 5mg', 'Bisacodyl', 'Dulcolax (Boehringer)', 'Tablet', '5mg', 'adult', 'Gastrointestinal',
     85.00, 52.00, 100, 30, 3.50, 1, '2027-11-01', 'Shelf E3', 'Laxative for constipation.', 0],
    ['Dulcolax Suppository 10mg', 'Bisacodyl', 'Dulcolax (Boehringer)', 'Suppository', '10mg', 'adult', 'Gastrointestinal',
     150.00, 95.00, 30, 6, 26.00, 0, '2027-09-01', 'Shelf E4', 'Fast constipation relief in 15-60 min.', 0],
    ['Lactulose Syrup 120mL', 'Lactulose', 'Generic', 'Syrup', '10g/15mL', 'all', 'Gastrointestinal',
     180.00, 115.00, 40, 0, 0, 0, '2027-08-01', 'Shelf E4', 'Osmotic laxative for chronic constipation.', 0],
    ['Domperidone 10mg (Motilium)', 'Domperidone', 'Motilium (J&J)', 'Tablet', '10mg', 'adult', 'Gastrointestinal',
     150.00, 95.00, 80, 30, 5.50, 1, '2027-10-01', 'Shelf E4', 'Anti-nausea, vomiting. Prokinetic agent.', 1],
    ['Metoclopramide 10mg', 'Metoclopramide', 'Generic', 'Tablet', '10mg', 'adult', 'Gastrointestinal',
     45.00, 25.00, 100, 100, 1.50, 1, '2027-09-01', 'Shelf E4', 'Anti-emetic. Nausea & vomiting.', 1],
    ['Daflon 500mg', 'Diosmin+Hesperidin', 'Daflon (Servier)', 'Tablet', '450mg/50mg', 'adult', 'Gastrointestinal',
     780.00, 510.00, 20, 30, 27.00, 0, '2028-01-01', 'Shelf E5', 'For hemorrhoids & venous insufficiency.', 0],
    ['Hydrite ORS Sachet', 'Oral Rehydration Salts', 'Hydrite', 'Powder', 'Standard', 'all', 'Gastrointestinal',
     12.00, 6.00, 500, 0, 0, 0, '2028-06-01', 'Shelf E5', 'Rehydration for diarrhea. Mix with water.', 0],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                 ALLERGY & ANTIHISTAMINES                    ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Zyrtec 10mg Tablet', 'Cetirizine', 'Zyrtec (J&J)', 'Tablet', '10mg', 'adult', 'Allergy & Antihistamines',
     180.00, 115.00, 80, 10, 18.50, 1, '2027-12-01', 'Shelf F1', 'Non-drowsy allergy relief.', 0],
    ['Zyrtec Drops 10mL', 'Cetirizine', 'Zyrtec (J&J)', 'Drops', '10mg/mL', 'pediatric', 'Allergy & Antihistamines',
     220.00, 145.00, 35, 0, 0, 0, '2027-10-01', 'Shelf F1', 'Allergy drops for children 1+ years.', 0],
    ['Cetirizine 10mg (RiteMed)', 'Cetirizine', 'RiteMed', 'Tablet', '10mg', 'adult', 'Allergy & Antihistamines',
     35.00, 18.00, 400, 100, 1.50, 1, '2027-12-01', 'Shelf F1', 'Generic cetirizine. Allergic rhinitis.', 0],
    ['Claritin 10mg Tablet', 'Loratadine', 'Claritin (Bayer)', 'Tablet', '10mg', 'adult', 'Allergy & Antihistamines',
     220.00, 145.00, 60, 10, 23.00, 1, '2027-11-01', 'Shelf F1', 'Non-drowsy 24-hour allergy relief.', 0],
    ['Claritin Syrup 60mL', 'Loratadine', 'Claritin (Bayer)', 'Syrup', '5mg/5mL', 'pediatric', 'Allergy & Antihistamines',
     250.00, 165.00, 30, 0, 0, 0, '2027-09-01', 'Shelf F1', 'For kids 2+ years. Grape flavor.', 0],
    ['Loratadine 10mg (RiteMed)', 'Loratadine', 'RiteMed', 'Tablet', '10mg', 'adult', 'Allergy & Antihistamines',
     35.00, 18.00, 300, 100, 1.50, 1, '2027-12-01', 'Shelf F2', 'Generic loratadine.', 0],
    ['Benadryl 25mg Capsule', 'Diphenhydramine', 'Benadryl (J&J)', 'Capsule', '25mg', 'adult', 'Allergy & Antihistamines',
     120.00, 78.00, 60, 20, 6.50, 1, '2027-10-01', 'Shelf F2', 'Fast allergy relief. May cause drowsiness.', 0],
    ['Allegra 120mg', 'Fexofenadine', 'Allegra (Sanofi)', 'Tablet', '120mg', 'adult', 'Allergy & Antihistamines',
     280.00, 185.00, 30, 10, 29.00, 1, '2027-12-01', 'Shelf F2', 'Non-drowsy. Seasonal allergy relief.', 0],
    ['Chlorphenamine 4mg', 'Chlorphenamine Maleate', 'Generic', 'Tablet', '4mg', 'adult', 'Allergy & Antihistamines',
     25.00, 12.00, 300, 100, 0.75, 1, '2027-11-01', 'Shelf F2', 'Classic antihistamine. May cause drowsiness.', 0],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                SKIN CARE & DERMATOLOGY                      ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Betadine Solution 60mL', 'Povidone-Iodine', 'Betadine (Mundipharma)', 'Solution', '10%', 'all', 'Skin Care & Dermatology',
     85.00, 52.00, 120, 0, 0, 0, '2028-06-01', 'Shelf G1', 'Antiseptic for wound care.', 0],
    ['Betadine Solution 120mL', 'Povidone-Iodine', 'Betadine (Mundipharma)', 'Solution', '10%', 'all', 'Skin Care & Dermatology',
     150.00, 95.00, 80, 0, 0, 0, '2028-06-01', 'Shelf G1', 'Antiseptic. Larger size.', 0],
    ['Hydrocortisone 1% Cream 10g', 'Hydrocortisone', 'Generic', 'Cream', '1%', 'all', 'Skin Care & Dermatology',
     45.00, 25.00, 80, 0, 0, 0, '2027-12-01', 'Shelf G1', 'Anti-itch, rash, eczema, insect bites.', 0],
    ['Canesten 1% Cream 15g', 'Clotrimazole', 'Canesten (Bayer)', 'Cream', '1%', 'adult', 'Skin Care & Dermatology',
     220.00, 145.00, 40, 0, 0, 0, '2027-10-01', 'Shelf G2', 'Antifungal. Athletes foot, ringworm, jock itch.', 0],
    ['Lamisil 1% Cream 15g', 'Terbinafine', 'Lamisil (Novartis)', 'Cream', '1%', 'adult', 'Skin Care & Dermatology',
     280.00, 185.00, 30, 0, 0, 0, '2027-10-01', 'Shelf G2', 'Antifungal cream for skin infections.', 0],
    ['Nizoral 2% Cream 15g', 'Ketoconazole', 'Nizoral (J&J)', 'Cream', '2%', 'adult', 'Skin Care & Dermatology',
     250.00, 165.00, 35, 0, 0, 0, '2027-11-01', 'Shelf G2', 'Antifungal. Dandruff, tinea.', 0],
    ['Bactroban 2% Ointment 5g', 'Mupirocin', 'Bactroban (GSK)', 'Ointment', '2%', 'all', 'Skin Care & Dermatology',
     280.00, 185.00, 25, 0, 0, 0, '2027-09-01', 'Shelf G2', 'Topical antibiotic for skin infections.', 1],
    ['Benzoyl Peroxide 5% Gel 25g', 'Benzoyl Peroxide', 'Generic', 'Gel', '5%', 'adult', 'Skin Care & Dermatology',
     85.00, 50.00, 50, 0, 0, 0, '2027-10-01', 'Shelf G3', 'Acne treatment gel.', 0],
    ['Tretinoin 0.025% Cream 20g', 'Tretinoin', 'Generic', 'Cream', '0.025%', 'adult', 'Skin Care & Dermatology',
     180.00, 115.00, 25, 0, 0, 0, '2027-08-01', 'Shelf G3', 'Retinoid. Acne & skin renewal.', 1],
    ['Tretinoin 0.05% Cream 20g', 'Tretinoin', 'Generic', 'Cream', '0.05%', 'adult', 'Skin Care & Dermatology',
     220.00, 145.00, 20, 0, 0, 0, '2027-08-01', 'Shelf G3', 'Higher strength retinoid.', 1],
    ['Clobetasol 0.05% Cream 15g', 'Clobetasol Propionate', 'Generic', 'Cream', '0.05%', 'adult', 'Skin Care & Dermatology',
     180.00, 115.00, 20, 0, 0, 0, '2027-10-01', 'Shelf G3', 'Super-potent steroid. Psoriasis, eczema.', 1],
    ['Nystatin Cream 15g', 'Nystatin', 'Generic', 'Cream', '100,000 IU/g', 'all', 'Skin Care & Dermatology',
     95.00, 58.00, 40, 0, 0, 0, '2027-09-01', 'Shelf G3', 'Antifungal cream. Candidiasis.', 1],
    ['Acyclovir 5% Cream 5g', 'Acyclovir', 'Generic', 'Cream', '5%', 'adult', 'Skin Care & Dermatology',
     120.00, 78.00, 35, 0, 0, 0, '2027-10-01', 'Shelf G4', 'For cold sores / herpes simplex.', 0],
    ['Petroleum Jelly (Vaseline) 100g', 'White Petrolatum', 'Vaseline', 'Ointment', '100g', 'all', 'Skin Care & Dermatology',
     65.00, 40.00, 80, 0, 0, 0, '2029-01-01', 'Shelf G4', 'Skin protectant & moisturizer.', 0],
    ['Calamine Lotion 120mL', 'Calamine+Zinc Oxide', 'Generic', 'Lotion', 'Multi', 'all', 'Skin Care & Dermatology',
     75.00, 45.00, 50, 0, 0, 0, '2028-06-01', 'Shelf G4', 'Itch relief. Rash, chickenpox, insect bites.', 0],
    ['Silver Sulfadiazine 1% Cream 25g', 'Silver Sulfadiazine', 'Dermazin', 'Cream', '1%', 'all', 'Skin Care & Dermatology',
     250.00, 165.00, 20, 0, 0, 0, '2027-09-01', 'Shelf G4', 'Burn wound treatment cream.', 1],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                  FIRST AID & WOUND CARE                     ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Band-Aid Flexible Fabric 30s', 'Adhesive Bandage', 'Band-Aid (J&J)', 'Bandage', 'Assorted', 'all', 'First Aid & Wound Care',
     65.00, 40.00, 100, 30, 2.50, 0, '2029-01-01', 'Shelf H1', 'Flexible fabric adhesive bandages.', 0],
    ['Katinko Ointment 10g', 'Methyl Salicylate+Menthol+Camphor', 'Katinko', 'Ointment', 'Multi', 'adult', 'First Aid & Wound Care',
     35.00, 20.00, 150, 0, 0, 0, '2028-06-01', 'Shelf H1', 'Analgesic balm. Insect bites, pain, itch.', 0],
    ['White Flower Embrocation Oil', 'Menthol+Camphor+Eucalyptus', 'White Flower', 'Oil', 'Multi', 'adult', 'First Aid & Wound Care',
     50.00, 30.00, 80, 0, 0, 0, '2028-12-01', 'Shelf H1', 'Headache, dizziness, motion sickness.', 0],
    ['Efficascent Oil Extra 50mL', 'Methyl Salicylate+Menthol', 'Efficascent (Unilab)', 'Oil', 'Multi', 'adult', 'First Aid & Wound Care',
     85.00, 52.00, 60, 0, 0, 0, '2028-09-01', 'Shelf H1', 'Liniment oil for muscle pain.', 0],
    ['Povidone-Iodine 10% Solution 60mL', 'Povidone-Iodine', 'Generic', 'Solution', '10%', 'all', 'First Aid & Wound Care',
     45.00, 25.00, 100, 0, 0, 0, '2028-06-01', 'Shelf H2', 'Wound antiseptic.', 0],
    ['Hydrogen Peroxide 3% 120mL', 'Hydrogen Peroxide', 'Generic', 'Solution', '3%', 'all', 'First Aid & Wound Care',
     35.00, 18.00, 80, 0, 0, 0, '2028-01-01', 'Shelf H2', 'Wound cleaning agent.', 0],
    ['Green Cross 70% Isopropyl Alcohol 250mL', 'Isopropyl Alcohol', 'Green Cross', 'Solution', '70%', 'all', 'First Aid & Wound Care',
     45.00, 28.00, 200, 0, 0, 0, '2028-12-01', 'Shelf H2', 'Antiseptic rubbing alcohol.', 0],
    ['Green Cross 70% Isopropyl Alcohol 500mL', 'Isopropyl Alcohol', 'Green Cross', 'Solution', '70%', 'all', 'First Aid & Wound Care',
     75.00, 45.00, 150, 0, 0, 0, '2028-12-01', 'Shelf H2', 'Antiseptic rubbing alcohol. Family size.', 0],
    ['Cotton Balls 50g', 'Absorbent Cotton', 'Generic', 'Supplies', '50g', 'all', 'First Aid & Wound Care',
     25.00, 12.00, 100, 0, 0, 0, '2029-06-01', 'Shelf H3', 'Medical-grade cotton balls.', 0],
    ['Gauze Pads 4x4 10s', 'Sterile Gauze', 'Generic', 'Supplies', '4x4 inch', 'all', 'First Aid & Wound Care',
     35.00, 18.00, 80, 10, 3.50, 0, '2029-06-01', 'Shelf H3', 'Sterile gauze pads for wound dressing.', 0],
    ['Medical Tape 1 inch', 'Adhesive Tape', 'Generic', 'Supplies', '1 inch', 'all', 'First Aid & Wound Care',
     45.00, 25.00, 60, 0, 0, 0, '2029-06-01', 'Shelf H3', 'Hypoallergenic medical tape.', 0],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                      EYE & EAR CARE                         ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Visine Original 15mL', 'Tetrahydrozoline', 'Visine (J&J)', 'Eye Drops', '0.05%', 'adult', 'Eye & Ear Care',
     180.00, 115.00, 40, 0, 0, 0, '2027-09-01', 'Shelf I1', 'Redness relief eye drops.', 0],
    ['Refresh Tears 15mL', 'Carboxymethylcellulose', 'Refresh (Allergan)', 'Eye Drops', '0.5%', 'adult', 'Eye & Ear Care',
     280.00, 185.00, 30, 0, 0, 0, '2027-10-01', 'Shelf I1', 'Artificial tears for dry eyes.', 0],
    ['Systane Ultra 10mL', 'Polyethylene Glycol+Propylene Glycol', 'Systane (Alcon)', 'Eye Drops', 'Multi', 'adult', 'Eye & Ear Care',
     380.00, 250.00, 25, 0, 0, 0, '2027-10-01', 'Shelf I1', 'Premium dry eye relief.', 0],
    ['Tobramycin Eye Drops 5mL (Tobrex)', 'Tobramycin', 'Tobrex (Novartis)', 'Eye Drops', '0.3%', 'all', 'Eye & Ear Care',
     280.00, 185.00, 20, 0, 0, 0, '2027-08-01', 'Shelf I2', 'Antibiotic eye drops for conjunctivitis.', 1],
    ['Ciprofloxacin Eye Drops 5mL', 'Ciprofloxacin', 'Generic', 'Eye Drops', '0.3%', 'adult', 'Eye & Ear Care',
     120.00, 78.00, 25, 0, 0, 0, '2027-08-01', 'Shelf I2', 'Antibiotic eye drops.', 1],
    ['Sodium Chloride Eye Drops 15mL', 'Sodium Chloride', 'Generic', 'Eye Drops', '0.9%', 'all', 'Eye & Ear Care',
     65.00, 38.00, 50, 0, 0, 0, '2027-12-01', 'Shelf I2', 'Eye wash / irrigation solution.', 0],
    ['Sofradex Ear Drops 8mL', 'Framycetin+Gramicidin+Dexamethasone', 'Sofradex (Sanofi)', 'Ear Drops', 'Multi', 'adult', 'Eye & Ear Care',
     320.00, 210.00, 15, 0, 0, 0, '2027-09-01', 'Shelf I2', 'Antibiotic+steroid ear drops.', 1],
    ['Polymyxin B Eye/Ear Drops 10mL', 'Polymyxin B+Neomycin+Hydrocortisone', 'Generic', 'Eye/Ear Drops', 'Multi', 'adult', 'Eye & Ear Care',
     150.00, 95.00, 20, 0, 0, 0, '2027-08-01', 'Shelf I2', 'Combination antibiotic for eye/ear infections.', 1],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                       RESPIRATORY                           ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Ventolin Inhaler', 'Salbutamol', 'Ventolin (GSK)', 'Inhaler', '100mcg/dose', 'all', 'Respiratory',
     350.00, 225.00, 40, 0, 0, 0, '2027-10-01', 'Shelf J1', 'Bronchodilator for asthma relief.', 1],
    ['Salbutamol Nebules 2.5mg/2.5mL 20s', 'Salbutamol', 'Generic', 'Nebule', '2.5mg/2.5mL', 'all', 'Respiratory',
     180.00, 110.00, 50, 20, 10.00, 0, '2027-09-01', 'Shelf J1', 'For nebulization. Asthma, bronchospasm.', 1],
    ['Pulmicort Nebules 0.5mg 20s', 'Budesonide', 'Pulmicort (AstraZeneca)', 'Nebule', '0.5mg/2mL', 'all', 'Respiratory',
     650.00, 420.00, 20, 20, 33.00, 0, '2027-08-01', 'Shelf J1', 'Corticosteroid nebules for asthma control.', 1],
    ['Seretide 250 Evohaler', 'Fluticasone+Salmeterol', 'Seretide (GSK)', 'Inhaler', '250/25mcg', 'adult', 'Respiratory',
     1850.00, 1220.00, 10, 0, 0, 0, '2027-10-01', 'Shelf J2', 'Preventer+reliever combo inhaler.', 1],
    ['Symbicort 160/4.5 Turbuhaler', 'Budesonide+Formoterol', 'Symbicort (AstraZeneca)', 'Inhaler', '160/4.5mcg', 'adult', 'Respiratory',
     1650.00, 1080.00, 10, 0, 0, 0, '2027-11-01', 'Shelf J2', 'Maintenance & reliever inhaler.', 1],
    ['Montelukast 10mg (Singulair)', 'Montelukast', 'Singulair (MSD)', 'Tablet', '10mg', 'adult', 'Respiratory',
     380.00, 250.00, 30, 28, 14.00, 0, '2027-12-01', 'Shelf J2', 'Leukotriene blocker. Asthma & allergy.', 1],
    ['Montelukast 4mg Chewable', 'Montelukast', 'Singulair (MSD)', 'Chewable', '4mg', 'pediatric', 'Respiratory',
     320.00, 210.00, 20, 28, 12.00, 0, '2027-12-01', 'Shelf J2', 'For children 2-5 years. Cherry flavor.', 1],
    ['Salbutamol 2mg Tablet', 'Salbutamol', 'Generic', 'Tablet', '2mg', 'adult', 'Respiratory',
     35.00, 18.00, 200, 100, 1.00, 1, '2027-09-01', 'Shelf J3', 'Oral bronchodilator.', 1],
    ['Theophylline 200mg', 'Theophylline', 'Generic', 'Tablet', '200mg', 'adult', 'Respiratory',
     65.00, 38.00, 60, 100, 2.00, 1, '2027-08-01', 'Shelf J3', 'Bronchodilator for asthma & COPD.', 1],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                      CARDIOVASCULAR                         ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Amlodipine 5mg (Norvasc)', 'Amlodipine', 'Norvasc (Pfizer)', 'Tablet', '5mg', 'adult', 'Cardiovascular',
     180.00, 115.00, 80, 30, 6.50, 0, '2027-12-01', 'Shelf K1', 'Calcium channel blocker for hypertension.', 1],
    ['Amlodipine 10mg', 'Amlodipine', 'Generic', 'Tablet', '10mg', 'adult', 'Cardiovascular',
     85.00, 50.00, 100, 100, 2.50, 1, '2027-12-01', 'Shelf K1', 'Generic amlodipine 10mg.', 1],
    ['Amlodipine 5mg (RiteMed)', 'Amlodipine', 'RiteMed', 'Tablet', '5mg', 'adult', 'Cardiovascular',
     42.00, 22.00, 200, 100, 1.50, 1, '2027-12-01', 'Shelf K1', 'Affordable generic amlodipine.', 1],
    ['Losartan 50mg', 'Losartan', 'Generic', 'Tablet', '50mg', 'adult', 'Cardiovascular',
     55.00, 32.00, 200, 100, 2.00, 1, '2027-11-01', 'Shelf K1', 'ARB for hypertension.', 1],
    ['Losartan 100mg', 'Losartan', 'Generic', 'Tablet', '100mg', 'adult', 'Cardiovascular',
     85.00, 50.00, 100, 100, 3.00, 1, '2027-11-01', 'Shelf K2', 'Higher dose ARB.', 1],
    ['Metoprolol 50mg', 'Metoprolol Succinate', 'Generic', 'Tablet', '50mg', 'adult', 'Cardiovascular',
     65.00, 38.00, 80, 100, 2.50, 1, '2027-10-01', 'Shelf K2', 'Beta-blocker for hypertension & heart rate.', 1],
    ['Atorvastatin 20mg (Lipitor)', 'Atorvastatin', 'Lipitor (Pfizer)', 'Tablet', '20mg', 'adult', 'Cardiovascular',
     520.00, 340.00, 30, 30, 18.00, 0, '2027-12-01', 'Shelf K2', 'Statin for high cholesterol.', 1],
    ['Atorvastatin 40mg (RiteMed)', 'Atorvastatin', 'RiteMed', 'Tablet', '40mg', 'adult', 'Cardiovascular',
     180.00, 110.00, 60, 30, 6.50, 0, '2028-01-01', 'Shelf K2', 'Generic statin.', 1],
    ['Simvastatin 20mg', 'Simvastatin', 'Generic', 'Tablet', '20mg', 'adult', 'Cardiovascular',
     65.00, 38.00, 80, 100, 2.50, 1, '2027-11-01', 'Shelf K3', 'Statin for cholesterol control.', 1],
    ['Rosuvastatin 10mg (Crestor)', 'Rosuvastatin', 'Crestor (AstraZeneca)', 'Tablet', '10mg', 'adult', 'Cardiovascular',
     650.00, 420.00, 20, 28, 24.00, 0, '2027-12-01', 'Shelf K3', 'Potent statin for cholesterol.', 1],
    ['Aspirin 80mg (Cardiac)', 'Aspirin', 'Generic', 'Tablet', '80mg', 'adult', 'Cardiovascular',
     35.00, 18.00, 300, 100, 1.00, 1, '2028-06-01', 'Shelf K3', 'Low-dose aspirin for heart protection.', 1],
    ['Clopidogrel 75mg (Plavix)', 'Clopidogrel', 'Plavix (Sanofi)', 'Tablet', '75mg', 'adult', 'Cardiovascular',
     380.00, 250.00, 30, 28, 14.00, 0, '2027-12-01', 'Shelf K3', 'Antiplatelet. Stroke & heart attack prevention.', 1],
    ['Enalapril 10mg', 'Enalapril', 'Generic', 'Tablet', '10mg', 'adult', 'Cardiovascular',
     55.00, 32.00, 100, 100, 2.00, 1, '2027-10-01', 'Shelf K4', 'ACE inhibitor for hypertension.', 1],
    ['Valsartan 80mg', 'Valsartan', 'Generic', 'Tablet', '80mg', 'adult', 'Cardiovascular',
     85.00, 50.00, 80, 100, 3.00, 1, '2027-11-01', 'Shelf K4', 'ARB for hypertension & heart failure.', 1],
    ['Telmisartan 40mg (Micardis)', 'Telmisartan', 'Micardis (Boehringer)', 'Tablet', '40mg', 'adult', 'Cardiovascular',
     480.00, 310.00, 20, 28, 18.00, 0, '2027-12-01', 'Shelf K4', 'Long-acting ARB.', 1],
    ['Nifedipine 30mg ER', 'Nifedipine', 'Generic', 'Tablet', '30mg', 'adult', 'Cardiovascular',
     85.00, 50.00, 60, 100, 3.00, 1, '2027-10-01', 'Shelf K4', 'Extended-release CCB for hypertension.', 1],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                      DIABETES CARE                          ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Metformin 500mg', 'Metformin', 'Generic', 'Tablet', '500mg', 'adult', 'Diabetes Care',
     55.00, 30.00, 200, 100, 2.00, 1, '2027-12-01', 'Shelf L1', 'First-line diabetes medication.', 1],
    ['Metformin 850mg', 'Metformin', 'Generic', 'Tablet', '850mg', 'adult', 'Diabetes Care',
     75.00, 42.00, 120, 100, 2.50, 1, '2027-12-01', 'Shelf L1', 'Higher dose metformin.', 1],
    ['Glucophage 500mg', 'Metformin', 'Glucophage (Merck)', 'Tablet', '500mg', 'adult', 'Diabetes Care',
     180.00, 115.00, 40, 30, 6.50, 0, '2027-12-01', 'Shelf L1', 'Original branded metformin.', 1],
    ['Glimepiride 2mg', 'Glimepiride', 'Generic', 'Tablet', '2mg', 'adult', 'Diabetes Care',
     65.00, 38.00, 80, 100, 2.00, 1, '2027-11-01', 'Shelf L1', 'Sulfonylurea for type 2 diabetes.', 1],
    ['Gliclazide 60mg MR (Diamicron)', 'Gliclazide', 'Diamicron (Servier)', 'Tablet', '60mg', 'adult', 'Diabetes Care',
     520.00, 340.00, 20, 30, 18.00, 0, '2027-12-01', 'Shelf L2', 'Modified-release sulfonylurea.', 1],
    ['Sitagliptin 100mg (Januvia)', 'Sitagliptin', 'Januvia (MSD)', 'Tablet', '100mg', 'adult', 'Diabetes Care',
     1850.00, 1220.00, 10, 28, 68.00, 0, '2027-12-01', 'Shelf L2', 'DPP-4 inhibitor for type 2 diabetes.', 1],
    ['Insulin Syringes 1mL 10s', 'Syringe', 'Generic', 'Supplies', '1mL/100U', 'adult', 'Diabetes Care',
     120.00, 72.00, 40, 10, 12.00, 0, '2029-01-01', 'Shelf L2', 'Insulin syringes with needle.', 0],
    ['Glucometer Test Strips 25s', 'Test Strips', 'One Touch', 'Supplies', 'Standard', 'adult', 'Diabetes Care',
     650.00, 420.00, 25, 25, 26.00, 0, '2028-06-01', 'Shelf L3', 'Blood glucose test strips.', 0],
    ['Alcohol Swabs 100s', 'Isopropyl Alcohol Pad', 'Generic', 'Supplies', '70%', 'all', 'Diabetes Care',
     85.00, 48.00, 50, 100, 1.00, 0, '2029-01-01', 'Shelf L3', 'Pre-injection skin swabs.', 0],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                      SEXUAL HEALTH                          ║
    // ╚══════════════════════════════════════════════════════════════╝

    // --- Condoms: Durex ---
    ['Durex Classic 3s', 'Condom', 'Durex', 'Condom', 'Classic', 'adult', 'Sexual Health',
     85.00, 55.00, 50, 3, 28.33, 0, '2028-06-01', 'Shelf S1', 'Classic natural feeling condom.', 0],
    ['Durex Fetherlite 3s', 'Condom', 'Durex', 'Condom', 'Ultra Thin', 'adult', 'Sexual Health',
     95.00, 60.00, 40, 3, 31.67, 0, '2028-06-01', 'Shelf S1', 'Ultra-thin for more sensitivity.', 0],
    ['Durex Fetherlite 12s', 'Condom', 'Durex', 'Condom', 'Ultra Thin', 'adult', 'Sexual Health',
     340.00, 220.00, 25, 12, 28.33, 0, '2028-06-01', 'Shelf S1', 'Ultra-thin, 12-pack value.', 0],
    ['Durex Extra Safe 3s', 'Condom', 'Durex', 'Condom', 'Extra Safe', 'adult', 'Sexual Health',
     90.00, 58.00, 35, 3, 30.00, 0, '2028-06-01', 'Shelf S1', 'Thicker with extra lube for safety.', 0],
    ['Durex Pleasuremax 3s', 'Condom', 'Durex', 'Condom', 'Ribbed & Dotted', 'adult', 'Sexual Health',
     95.00, 60.00, 35, 3, 31.67, 0, '2028-06-01', 'Shelf S1', 'Ribbed & dotted for extra stimulation.', 0],

    // --- Condoms: Trust ---
    ['Trust Classic 3s', 'Condom', 'Trust', 'Condom', 'Classic', 'adult', 'Sexual Health',
     55.00, 32.00, 60, 3, 18.33, 0, '2028-06-01', 'Shelf S1', 'Affordable classic condom.', 0],
    ['Trust Classic 12s', 'Condom', 'Trust', 'Condom', 'Classic', 'adult', 'Sexual Health',
     190.00, 120.00, 30, 12, 15.83, 0, '2028-06-01', 'Shelf S1', 'Best value 12-pack.', 0],
    ['Trust Ultra Thin 3s', 'Condom', 'Trust', 'Condom', 'Ultra Thin', 'adult', 'Sexual Health',
     65.00, 38.00, 45, 3, 21.67, 0, '2028-06-01', 'Shelf S1', 'Budget ultra-thin condom.', 0],
    ['Trust Ribbed 3s', 'Condom', 'Trust', 'Condom', 'Ribbed', 'adult', 'Sexual Health',
     60.00, 35.00, 45, 3, 20.00, 0, '2028-06-01', 'Shelf S2', 'Ribbed for stimulation.', 0],
    ['Trust Studded 3s', 'Condom', 'Trust', 'Condom', 'Studded', 'adult', 'Sexual Health',
     65.00, 38.00, 40, 3, 21.67, 0, '2028-06-01', 'Shelf S2', 'Studded texture for sensation.', 0],

    // --- Condoms: Premier ---
    ['Premier Classic 3s', 'Condom', 'Premier', 'Condom', 'Classic', 'adult', 'Sexual Health',
     50.00, 28.00, 60, 3, 16.67, 0, '2028-06-01', 'Shelf S2', 'Reliable basic condom.', 0],
    ['Premier Classic 12s', 'Condom', 'Premier', 'Condom', 'Classic', 'adult', 'Sexual Health',
     170.00, 105.00, 30, 12, 14.17, 0, '2028-06-01', 'Shelf S2', '12-pack value.', 0],
    ['Premier Ultra Thin 3s', 'Condom', 'Premier', 'Condom', 'Ultra Thin', 'adult', 'Sexual Health',
     60.00, 35.00, 40, 3, 20.00, 0, '2028-06-01', 'Shelf S2', 'Thin & sensitive.', 0],
    ['Premier Ribbed 3s', 'Condom', 'Premier', 'Condom', 'Ribbed', 'adult', 'Sexual Health',
     55.00, 32.00, 40, 3, 18.33, 0, '2028-06-01', 'Shelf S2', 'Ribbed texture.', 0],
    ['Premier Flavored 3s', 'Condom', 'Premier', 'Condom', 'Flavored', 'adult', 'Sexual Health',
     60.00, 35.00, 35, 3, 20.00, 0, '2028-06-01', 'Shelf S2', 'Assorted flavored condoms.', 0],

    // --- Condoms: Fiesta ---
    ['Fiesta Classic 3s', 'Condom', 'Fiesta', 'Condom', 'Classic', 'adult', 'Sexual Health',
     55.00, 32.00, 50, 3, 18.33, 0, '2028-06-01', 'Shelf S3', 'Fiesta classic condom.', 0],
    ['Fiesta Ultra Thin 3s', 'Condom', 'Fiesta', 'Condom', 'Ultra Thin', 'adult', 'Sexual Health',
     65.00, 38.00, 40, 3, 21.67, 0, '2028-06-01', 'Shelf S3', 'Fiesta ultra-thin.', 0],
    ['Fiesta Ribbed 3s', 'Condom', 'Fiesta', 'Condom', 'Ribbed', 'adult', 'Sexual Health',
     60.00, 35.00, 40, 3, 20.00, 0, '2028-06-01', 'Shelf S3', 'Fiesta ribbed for her pleasure.', 0],
    ['Fiesta Studded 3s', 'Condom', 'Fiesta', 'Condom', 'Studded', 'adult', 'Sexual Health',
     65.00, 38.00, 35, 3, 21.67, 0, '2028-06-01', 'Shelf S3', 'Studded texture condom.', 0],

    // --- Condoms: Okamoto & Playboy ---
    ['Okamoto 003 3s', 'Condom', 'Okamoto', 'Condom', 'Ultra Thin 0.03mm', 'adult', 'Sexual Health',
     120.00, 78.00, 25, 3, 40.00, 0, '2028-06-01', 'Shelf S3', 'Japanese quality. Ultra-thin 0.03mm.', 0],
    ['Okamoto Crown 3s', 'Condom', 'Okamoto', 'Condom', 'Skinless Skin', 'adult', 'Sexual Health',
     95.00, 60.00, 25, 3, 31.67, 0, '2028-06-01', 'Shelf S3', 'Super-thin natural feeling.', 0],
    ['Playboy Ultra Thin 3s', 'Condom', 'Playboy', 'Condom', 'Ultra Thin', 'adult', 'Sexual Health',
     80.00, 50.00, 30, 3, 26.67, 0, '2028-06-01', 'Shelf S3', 'Playboy ultra-thin condom.', 0],
    ['Playboy Ribbed 3s', 'Condom', 'Playboy', 'Condom', 'Ribbed', 'adult', 'Sexual Health',
     85.00, 52.00, 30, 3, 28.33, 0, '2028-06-01', 'Shelf S3', 'Playboy ribbed condom.', 0],

    // --- Lubricants ---
    ['K-Y Jelly 50g', 'Personal Lubricant', 'K-Y (J&J)', 'Gel', '50g', 'adult', 'Sexual Health',
     220.00, 150.00, 30, 0, 0, 0, '2028-06-01', 'Shelf S4', 'Water-based lubricant.', 0],
    ['K-Y Jelly 100g', 'Personal Lubricant', 'K-Y (J&J)', 'Gel', '100g', 'adult', 'Sexual Health',
     380.00, 260.00, 20, 0, 0, 0, '2028-06-01', 'Shelf S4', 'Water-based lubricant. Large size.', 0],
    ['Durex Play Feel 50mL', 'Personal Lubricant', 'Durex Play', 'Gel', '50mL', 'adult', 'Sexual Health',
     420.00, 280.00, 18, 0, 0, 0, '2028-06-01', 'Shelf S4', 'Smooth water-based lube.', 0],
    ['Durex Play Tingling 50mL', 'Personal Lubricant', 'Durex Play', 'Gel', '50mL', 'adult', 'Sexual Health',
     450.00, 300.00, 15, 0, 0, 0, '2028-06-01', 'Shelf S4', 'Tingling sensation lubricant.', 0],
    ['Trust Lubricant 50mL', 'Personal Lubricant', 'Trust', 'Gel', '50mL', 'adult', 'Sexual Health',
     280.00, 185.00, 22, 0, 0, 0, '2028-06-01', 'Shelf S4', 'Affordable water-based lube.', 0],

    // --- Pregnancy Tests ---
    ['Clearblue Rapid Detection', 'Pregnancy Test', 'Clearblue', 'Test Kit', '1 Test', 'adult', 'Sexual Health',
     280.00, 190.00, 35, 0, 0, 0, '2029-06-01', 'Shelf S5', 'Results in 1-3 minutes.', 0],
    ['Clearblue Digital', 'Pregnancy Test', 'Clearblue', 'Test Kit', '1 Test', 'adult', 'Sexual Health',
     450.00, 300.00, 20, 0, 0, 0, '2029-06-01', 'Shelf S5', 'Digital display: Pregnant / Not Pregnant.', 0],
    ['First Response Early Result', 'Pregnancy Test', 'First Response', 'Test Kit', '1 Test', 'adult', 'Sexual Health',
     320.00, 215.00, 25, 0, 0, 0, '2029-06-01', 'Shelf S5', 'Detects 6 days before missed period.', 0],
    ['Sure Check Pregnancy Test', 'Pregnancy Test', 'Sure Check', 'Test Kit', '1 Test', 'adult', 'Sexual Health',
     120.00, 75.00, 40, 0, 0, 0, '2029-06-01', 'Shelf S5', 'Midrange pregnancy test kit.', 0],
    ['Pregtest Pregnancy Strip', 'Pregnancy Test', 'Generic', 'Test Strip', '1 Test', 'adult', 'Sexual Health',
     45.00, 25.00, 80, 0, 0, 0, '2029-06-01', 'Shelf S5', 'Economy test strip.', 0],

    // --- Oral Contraceptives (Rx) ---
    ['Lady Pills 28s', 'Ethinylestradiol+Levonorgestrel', 'Lady', 'Tablet', '0.03mg/0.15mg', 'adult', 'Sexual Health',
     155.00, 95.00, 60, 28, 5.54, 0, '2028-01-01', 'Shelf S6', 'Combined oral contraceptive. 28 tablets.', 1],
    ['Trust Pills 21s', 'Ethinylestradiol+Levonorgestrel', 'Trust', 'Tablet', '0.03mg/0.15mg', 'adult', 'Sexual Health',
     120.00, 75.00, 50, 21, 5.71, 0, '2028-01-01', 'Shelf S6', 'Affordable OC. 21 tablets + 7 day break.', 1],
    ['Nordette 28s', 'Ethinylestradiol+Levonorgestrel', 'Nordette (Pfizer)', 'Tablet', '0.03mg/0.15mg', 'adult', 'Sexual Health',
     180.00, 115.00, 40, 28, 6.43, 0, '2028-01-01', 'Shelf S6', 'Combined OC. 21 active + 7 placebo tablets.', 1],
    ['Althea 21s', 'Ethinylestradiol+Cyproterone', 'Althea', 'Tablet', '0.035mg/2mg', 'adult', 'Sexual Health',
     650.00, 420.00, 20, 21, 30.95, 0, '2028-01-01', 'Shelf S6', 'OC with anti-androgen. Also for acne.', 1],
    ['Diane-35 21s', 'Ethinylestradiol+Cyproterone', 'Diane-35 (Bayer)', 'Tablet', '0.035mg/2mg', 'adult', 'Sexual Health',
     780.00, 520.00, 18, 21, 37.14, 0, '2028-01-01', 'Shelf S7', 'Anti-androgen OC for acne & hirsutism.', 1],
    ['Yaz 28s', 'Ethinylestradiol+Drospirenone', 'Yaz (Bayer)', 'Tablet', '0.02mg/3mg', 'adult', 'Sexual Health',
     920.00, 620.00, 16, 28, 32.86, 0, '2028-01-01', 'Shelf S7', 'Low-dose OC. 24 active + 4 placebo.', 1],
    ['Yasmin 21s', 'Ethinylestradiol+Drospirenone', 'Yasmin (Bayer)', 'Tablet', '0.03mg/3mg', 'adult', 'Sexual Health',
     980.00, 660.00, 14, 21, 46.67, 0, '2028-01-01', 'Shelf S7', 'OC with anti-mineralocorticoid activity.', 1],
    ['Marvelon 21s', 'Ethinylestradiol+Desogestrel', 'Marvelon (MSD)', 'Tablet', '0.03mg/0.15mg', 'adult', 'Sexual Health',
     850.00, 560.00, 16, 21, 40.48, 0, '2028-01-01', 'Shelf S7', 'Third-gen combined OC.', 1],

    // --- Emergency Contraception ---
    ['Nordiol (EC)', 'Levonorgestrel', 'Generic', 'Tablet', '1.5mg', 'adult', 'Sexual Health',
     350.00, 220.00, 25, 1, 350.00, 0, '2028-01-01', 'Shelf S7', 'Emergency contraceptive. Single dose within 72 hrs.', 1],
    ['Levonorgestrel 0.75mg x2', 'Levonorgestrel', 'Generic', 'Tablet', '0.75mg', 'adult', 'Sexual Health',
     320.00, 200.00, 22, 2, 160.00, 0, '2028-01-01', 'Shelf S7', 'Two-dose emergency contraceptive.', 1],

    // --- STI-Related Products ---
    ['Azithromycin 1g Single Dose', 'Azithromycin', 'Generic', 'Tablet', '1g', 'adult', 'Sexual Health',
     120.00, 72.00, 30, 1, 120.00, 0, '2027-10-01', 'Shelf S8', 'Single-dose treatment for chlamydia.', 1],
    ['Doxycycline 100mg (STI Course 14s)', 'Doxycycline', 'Generic', 'Capsule', '100mg', 'adult', 'Sexual Health',
     280.00, 175.00, 25, 14, 20.00, 0, '2027-09-01', 'Shelf S8', '14-day course for chlamydia/syphilis.', 1],
    ['Fluconazole 150mg (Yeast)', 'Fluconazole', 'Generic', 'Capsule', '150mg', 'adult', 'Sexual Health',
     45.00, 25.00, 60, 1, 45.00, 0, '2027-10-01', 'Shelf S8', 'Single-dose for vaginal yeast infection.', 1],
    ['Metronidazole 500mg (Trichomoniasis)', 'Metronidazole', 'Generic', 'Tablet', '500mg', 'adult', 'Sexual Health',
     65.00, 38.00, 40, 14, 4.64, 0, '2027-09-01', 'Shelf S8', '7-day course for trichomoniasis.', 1],
    ['Acyclovir 400mg (Herpes)', 'Acyclovir', 'Generic', 'Tablet', '400mg', 'adult', 'Sexual Health',
     180.00, 110.00, 30, 30, 6.50, 0, '2027-10-01', 'Shelf S8', 'Antiviral for genital herpes.', 1],
    ['Acyclovir 200mg', 'Acyclovir', 'Generic', 'Tablet', '200mg', 'adult', 'Sexual Health',
     95.00, 58.00, 40, 25, 4.00, 0, '2027-10-01', 'Shelf S8', 'Lower dose antiviral for herpes.', 1],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                       FEMININE CARE                         ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['pH Care Feminine Wash 150mL', 'Feminine Wash', 'pH Care', 'Wash', '150mL', 'adult', 'Feminine Care',
     120.00, 78.00, 50, 0, 0, 0, '2028-06-01', 'Shelf T1', 'pH-balanced feminine wash.', 0],
    ['Betadine Feminine Wash 150mL', 'Povidone-Iodine Feminine Wash', 'Betadine (Mundipharma)', 'Wash', '150mL', 'adult', 'Feminine Care',
     180.00, 115.00, 40, 0, 0, 0, '2028-06-01', 'Shelf T1', 'Antiseptic feminine wash.', 0],
    ['Lactacyd Feminine Wash 150mL', 'Lactic Acid Feminine Wash', 'Lactacyd', 'Wash', '150mL', 'adult', 'Feminine Care',
     165.00, 105.00, 45, 0, 0, 0, '2028-06-01', 'Shelf T1', 'Daily feminine hygiene wash.', 0],
    ['Whisper All Day Normal 8s', 'Sanitary Pad', 'Whisper (P&G)', 'Pad', 'Normal', 'adult', 'Feminine Care',
     65.00, 40.00, 80, 8, 8.13, 0, '2029-01-01', 'Shelf T2', 'Daytime normal flow pads.', 0],
    ['Whisper All Night Long 6s', 'Sanitary Pad', 'Whisper (P&G)', 'Pad', 'Overnight', 'adult', 'Feminine Care',
     85.00, 52.00, 60, 6, 14.17, 0, '2029-01-01', 'Shelf T2', 'Overnight heavy flow protection.', 0],
    ['Modess All Night 8s', 'Sanitary Pad', 'Modess (J&J)', 'Pad', 'Overnight', 'adult', 'Feminine Care',
     75.00, 48.00, 60, 8, 9.38, 0, '2029-01-01', 'Shelf T2', 'Overnight pads with wings.', 0],
    ['Kotex Overnight 8s', 'Sanitary Pad', 'Kotex (Kimberly-Clark)', 'Pad', 'Overnight', 'adult', 'Feminine Care',
     85.00, 52.00, 50, 8, 10.63, 0, '2029-01-01', 'Shelf T2', 'Ultra-thin overnight pads.', 0],
    ['Carefree Panty Liners 20s', 'Panty Liner', 'Carefree (J&J)', 'Liner', 'Regular', 'adult', 'Feminine Care',
     65.00, 40.00, 70, 20, 3.25, 0, '2029-01-01', 'Shelf T3', 'Daily freshness panty liners.', 0],
    ['Tampax Regular 8s', 'Tampon', 'Tampax (P&G)', 'Tampon', 'Regular', 'adult', 'Feminine Care',
     180.00, 115.00, 20, 8, 22.50, 0, '2029-01-01', 'Shelf T3', 'Regular absorbency tampons.', 0],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                        ORAL CARE                            ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Listerine Cool Mint 250mL', 'Antiseptic Mouthwash', 'Listerine (J&J)', 'Mouthwash', '250mL', 'adult', 'Oral Care',
     120.00, 78.00, 60, 0, 0, 0, '2028-12-01', 'Shelf U1', 'Kills 99.9% of germs.', 0],
    ['Listerine Total Care 500mL', 'Antiseptic Mouthwash', 'Listerine (J&J)', 'Mouthwash', '500mL', 'adult', 'Oral Care',
     250.00, 165.00, 40, 0, 0, 0, '2028-12-01', 'Shelf U1', '6-benefit mouthwash.', 0],
    ['Betadine Gargle 120mL', 'Povidone-Iodine', 'Betadine (Mundipharma)', 'Gargle', '1%', 'adult', 'Oral Care',
     185.00, 120.00, 35, 0, 0, 0, '2028-06-01', 'Shelf U1', 'Throat & mouth antiseptic gargle.', 0],
    ['Sensodyne Repair & Protect 100g', 'Stannous Fluoride Toothpaste', 'Sensodyne (GSK)', 'Toothpaste', '100g', 'adult', 'Oral Care',
     220.00, 145.00, 40, 0, 0, 0, '2028-12-01', 'Shelf U2', 'For sensitive teeth.', 0],
    ['Orajel Gel 9.5g', 'Benzocaine', 'Orajel', 'Gel', '20%', 'adult', 'Oral Care',
     320.00, 210.00, 20, 0, 0, 0, '2028-06-01', 'Shelf U2', 'Instant toothache pain relief gel.', 0],
    ['Daktarin Oral Gel 20g', 'Miconazole', 'Daktarin (J&J)', 'Gel', '20mg/g', 'all', 'Oral Care',
     350.00, 225.00, 15, 0, 0, 0, '2027-12-01', 'Shelf U2', 'Antifungal for oral thrush.', 1],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                      PERSONAL CARE                          ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Face Mask Surgical 50s', 'Disposable Face Mask', 'Generic', 'Supplies', '3-Ply', 'all', 'Personal Care',
     120.00, 65.00, 100, 50, 2.50, 0, '2029-01-01', 'Shelf V1', '3-ply surgical face masks.', 0],
    ['N95 Mask (3M) 1pc', 'Respirator Mask', '3M', 'Supplies', 'N95', 'adult', 'Personal Care',
     85.00, 52.00, 50, 0, 0, 0, '2029-01-01', 'Shelf V1', 'N95 respiratory protection.', 0],
    ['Digital Thermometer', 'Thermometer', 'Generic', 'Device', 'Digital', 'all', 'Personal Care',
     150.00, 85.00, 30, 0, 0, 0, '2029-06-01', 'Shelf V1', 'Digital oral/axillary thermometer.', 0],
    ['Mercury-Free Thermometer', 'Thermometer', 'Generic', 'Device', 'Galinstan', 'all', 'Personal Care',
     120.00, 72.00, 20, 0, 0, 0, '2029-06-01', 'Shelf V1', 'Mercury-free glass thermometer.', 0],
    ['Blood Pressure Monitor (Omron)', 'BP Monitor', 'Omron', 'Device', 'Automatic', 'adult', 'Personal Care',
     2500.00, 1650.00, 8, 0, 0, 0, '2029-06-01', 'Shelf V2', 'Automatic upper-arm BP monitor.', 0],
    ['Pulse Oximeter', 'Pulse Oximeter', 'Generic', 'Device', 'Fingertip', 'adult', 'Personal Care',
     650.00, 420.00, 15, 0, 0, 0, '2029-06-01', 'Shelf V2', 'Measures SpO2 and pulse rate.', 0],
    ['Nebulizer Machine (Omron)', 'Nebulizer', 'Omron', 'Device', 'Compressor', 'all', 'Personal Care',
     3500.00, 2300.00, 5, 0, 0, 0, '2029-06-01', 'Shelf V2', 'Compressor nebulizer for respiratory meds.', 0],
    ['Hot Water Bag', 'Hot Water Bottle', 'Generic', 'Supplies', '2L', 'all', 'Personal Care',
     180.00, 105.00, 15, 0, 0, 0, '2029-06-01', 'Shelf V3', 'Rubber hot water bottle for pain relief.', 0],

    // ╔══════════════════════════════════════════════════════════════╗
    // ║                    BABY & PEDIATRIC                         ║
    // ╚══════════════════════════════════════════════════════════════╝

    ['Zinc Drops 10mg/mL (RiteMed)', 'Zinc Sulfate', 'RiteMed', 'Drops', '10mg/mL', 'pediatric', 'Baby & Pediatric',
     65.00, 40.00, 50, 0, 0, 0, '2027-10-01', 'Shelf W1', 'WHO-recommended zinc for diarrhea.', 0],
    ['ORS Powder (Oresol)', 'Oral Rehydration Salts', 'Oresol', 'Powder', 'Standard', 'all', 'Baby & Pediatric',
     8.00, 4.00, 500, 0, 0, 0, '2028-06-01', 'Shelf W1', 'Rehydration salts. Mix with 1L water.', 0],
    ['Ceelin Plus Syrup 120mL', 'Ascorbic Acid + Zinc', 'Ceelin (Unilab)', 'Syrup', 'Multi', 'pediatric', 'Baby & Pediatric',
     185.00, 120.00, 40, 0, 0, 0, '2027-12-01', 'Shelf W1', 'Vitamin C + Zinc for kids\' immunity.', 0],
    ['Cherifer PGM 10-22 Syrup', 'Chlorella Growth Factor+Taurine+Zinc', 'Cherifer (Unilab)', 'Syrup', 'Multi', 'pediatric', 'Baby & Pediatric',
     280.00, 185.00, 30, 0, 0, 0, '2028-01-01', 'Shelf W1', 'Growth supplement for children.', 0],
    ['Bonnisan Drops 30mL', 'Herbal (Gripe Water)', 'Bonnisan (Himalaya)', 'Drops', 'Multi', 'pediatric', 'Baby & Pediatric',
     120.00, 78.00, 25, 0, 0, 0, '2028-06-01', 'Shelf W2', 'For infant colic, gas, and indigestion.', 0],
    ['Bepanthen Nappy Rash Cream 30g', 'Dexpanthenol', 'Bepanthen (Bayer)', 'Cream', '5%', 'pediatric', 'Baby & Pediatric',
     280.00, 185.00, 25, 0, 0, 0, '2028-06-01', 'Shelf W2', 'Diaper rash treatment & prevention.', 0],
    ['Calpol Infant Drops 15mL', 'Paracetamol', 'Calpol (GSK)', 'Drops', '100mg/mL', 'pediatric', 'Baby & Pediatric',
     75.00, 48.00, 35, 0, 0, 0, '2027-08-01', 'Shelf W2', 'Fever drops for infants 0-2 years.', 0],
    ['Pedialyte Oral Rehydration 500mL', 'Oral Electrolyte Solution', 'Pedialyte (Abbott)', 'Solution', 'Standard', 'pediatric', 'Baby & Pediatric',
     85.00, 52.00, 40, 0, 0, 0, '2028-03-01', 'Shelf W2', 'Ready-to-drink rehydration for kids.', 0],
];

// --- 4. Insert products ---
$insertStmt = $conn->prepare("
    INSERT INTO products (
        name, generic_name, brand_name, dosage_form, strength, age_group,
        category, category_id, selling_price, cost_price, stock_quantity,
        pieces_per_box, price_per_piece, sell_by_piece,
        expiry_date, location, description, is_active, requires_prescription
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$count = 0;
$errors = 0;
$isActive = 1;

foreach ($products as $p) {
    [$name, $generic, $brand, $form, $str, $age, $catName,
     $sellPrice, $costPrice, $stockQty, $perBox, $pricePiece, $byPiece,
     $expiry, $loc, $desc, $rx] = $p;

    $catId = $catIds[$catName] ?? null;

    $insertStmt->bind_param(
        "sssssssiddiidisssii",
        $name, $generic, $brand, $form, $str, $age,
        $catName, $catId, $sellPrice, $costPrice, $stockQty,
        $perBox, $pricePiece, $byPiece,
        $expiry, $loc, $desc, $isActive, $rx
    );

    if ($insertStmt->execute()) {
        $count++;
    } else {
        $errors++;
        echo "❌ Failed: $name — " . $insertStmt->error . "\n";
    }
}

echo "\n✅ Inserted $count products";
if ($errors > 0) echo " ($errors errors)";
echo "\n\n";

// Summary by category
$catSummary = $conn->query("SELECT category, COUNT(*) as cnt FROM products WHERE is_active=1 GROUP BY category ORDER BY category");
echo "=== Catalog Summary ===\n";
$total = 0;
while ($row = $catSummary->fetch_assoc()) {
    printf("  %-35s %d products\n", $row['category'], $row['cnt']);
    $total += $row['cnt'];
}
echo "  " . str_repeat('-', 48) . "\n";
echo sprintf("  %-35s %d products\n", 'TOTAL', $total);

$rxCount = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE requires_prescription=1 AND is_active=1")->fetch_assoc()['cnt'];
echo "\n  Prescription Required (Rx): $rxCount products\n";
echo "</pre>";
