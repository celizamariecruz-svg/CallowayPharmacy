<?php
include 'db_connection.php';

// Set default timezone to Philippines
date_default_timezone_set('Asia/Manila');

$medicines = [
    // Antibiotics
    ['Amoxicillin 500mg', 'Antibiotics', 500, '2025-12-31', 15.00, 'Shelf A1'],
    ['Cefalexin 500mg', 'Antibiotics', 300, '2025-11-30', 20.00, 'Shelf A1'],
    ['Azithromycin 500mg', 'Antibiotics', 200, '2025-10-31', 45.00, 'Shelf A2'],
    ['Ciprofloxacin 500mg', 'Antibiotics', 250, '2025-12-15', 25.00, 'Shelf A2'],
    
    // Pain Relievers
    ['Paracetamol 500mg', 'Pain Relief', 1000, '2025-12-31', 5.00, 'Shelf B1'],
    ['Ibuprofen 400mg', 'Pain Relief', 800, '2025-11-30', 8.00, 'Shelf B1'],
    ['Mefenamic Acid 500mg', 'Pain Relief', 600, '2025-10-31', 6.00, 'Shelf B2'],
    ['Naproxen 500mg', 'Pain Relief', 400, '2025-12-15', 12.00, 'Shelf B2'],
    
    // Maintenance Medicines
    ['Metformin 500mg', 'Maintenance', 700, '2025-12-31', 15.00, 'Shelf C1'],
    ['Losartan 50mg', 'Maintenance', 600, '2025-11-30', 20.00, 'Shelf C1'],
    ['Amlodipine 5mg', 'Maintenance', 500, '2025-10-31', 18.00, 'Shelf C2'],
    ['Metoprolol 50mg', 'Maintenance', 400, '2025-12-15', 16.00, 'Shelf C2'],
    
    // Vitamins and Supplements
    ['Vitamin C 500mg', 'Vitamins', 1500, '2025-12-31', 8.00, 'Shelf D1'],
    ['Vitamin B-Complex', 'Vitamins', 1000, '2025-11-30', 12.00, 'Shelf D1'],
    ['Calcium + Vitamin D3', 'Vitamins', 800, '2025-10-31', 15.00, 'Shelf D2'],
    ['Ferrous Sulfate + Folic Acid', 'Vitamins', 600, '2025-12-15', 10.00, 'Shelf D2'],
    
    // Gastrointestinal Medicines
    ['Omeprazole 20mg', 'Gastrointestinal', 400, '2025-12-31', 18.00, 'Shelf E1'],
    ['Loperamide 2mg', 'Gastrointestinal', 800, '2025-11-30', 8.00, 'Shelf E1'],
    ['Bismuth Subsalicylate', 'Gastrointestinal', 300, '2025-10-31', 12.00, 'Shelf E2'],
    ['Simethicone 80mg', 'Gastrointestinal', 400, '2025-12-15', 10.00, 'Shelf E2'],
    
    // Allergy Medications
    ['Cetirizine 10mg', 'Allergy', 1000, '2025-12-31', 8.00, 'Shelf F1'],
    ['Loratadine 10mg', 'Allergy', 800, '2025-11-30', 10.00, 'Shelf F1'],
    ['Diphenhydramine 50mg', 'Allergy', 600, '2025-10-31', 7.00, 'Shelf F2'],
    ['Fexofenadine 180mg', 'Allergy', 400, '2025-12-15', 15.00, 'Shelf F2'],
    
    // Respiratory Medicines
    ['Salbutamol Inhaler', 'Respiratory', 200, '2025-12-31', 250.00, 'Shelf G1'],
    ['Carbocisteine 500mg', 'Respiratory', 400, '2025-11-30', 15.00, 'Shelf G1'],
    ['Ambroxol 30mg', 'Respiratory', 500, '2025-10-31', 12.00, 'Shelf G2'],
    ['Montelukast 10mg', 'Respiratory', 300, '2025-12-15', 35.00, 'Shelf G2'],
    
    // Topical Medications
    ['Betamethasone Cream', 'Topical', 200, '2025-12-31', 18.00, 'Shelf H1'],
    ['Clotrimazole Cream', 'Topical', 300, '2025-11-30', 12.00, 'Shelf H1'],
    ['Mupirocin Ointment', 'Topical', 150, '2025-10-31', 25.00, 'Shelf H2'],
    ['Calamine Lotion', 'Topical', 200, '2025-12-15', 15.00, 'Shelf H2'],
    
    // Eye/Ear Medications
    ['Artificial Tears', 'Eye/Ear Care', 400, '2025-12-31', 20.00, 'Shelf I1'],
    ['Ofloxacin Eye Drops', 'Eye/Ear Care', 200, '2025-11-30', 35.00, 'Shelf I1'],
    ['Carboglycerin Ear Drops', 'Eye/Ear Care', 150, '2025-10-31', 25.00, 'Shelf I2'],
    ['Polymyxin Eye Ointment', 'Eye/Ear Care', 100, '2025-12-15', 30.00, 'Shelf I2'],
    
    // First Aid
    ['Povidone Iodine', 'First Aid', 300, '2025-12-31', 15.00, 'Shelf J1'],
    ['Hydrogen Peroxide 3%', 'First Aid', 400, '2025-11-30', 12.00, 'Shelf J1'],
    ['Elastic Bandage', 'First Aid', 200, '2025-10-31', 25.00, 'Shelf J2'],
    ['Gauze Pads Sterile', 'First Aid', 500, '2025-12-15', 8.00, 'Shelf J2'],
    
    // Supplements
    ['Multivitamins + Minerals', 'Supplements', 800, '2025-12-31', 20.00, 'Shelf K1'],
    ['Fish Oil 1000mg', 'Supplements', 600, '2025-11-30', 25.00, 'Shelf K1'],
    ['Glucosamine 500mg', 'Supplements', 400, '2025-10-31', 30.00, 'Shelf K2'],
    ['Collagen Supplements', 'Supplements', 300, '2025-12-15', 40.00, 'Shelf K2'],
    
    // Women's Health
    ['Folic Acid 5mg', 'Women\'s Health', 500, '2025-12-31', 10.00, 'Shelf L1'],
    ['Iron + Vitamin C', 'Women\'s Health', 400, '2025-11-30', 15.00, 'Shelf L1'],
    ['Calcium 600mg', 'Women\'s Health', 400, '2025-10-31', 18.00, 'Shelf L2'],
    ['Evening Primrose Oil', 'Women\'s Health', 200, '2025-12-15', 25.00, 'Shelf L2'],
    
    // Children's Medicines
    ['Paracetamol Syrup', 'Children\'s Medicine', 300, '2025-12-31', 25.00, 'Shelf M1'],
    ['Multivitamin Syrup', 'Children\'s Medicine', 250, '2025-11-30', 30.00, 'Shelf M1'],
    ['Zinc Supplement', 'Children\'s Medicine', 400, '2025-10-31', 15.00, 'Shelf M2'],
    ['Probiotic Powder', 'Children\'s Medicine', 200, '2025-12-15', 35.00, 'Shelf M2']
];

try {
    // Start transaction
    $conn->begin_transaction();

    // Prepare the SQL statement for products table
    $stmt = $conn->prepare("INSERT INTO products (name, category, stock_quantity, expiry_date, price, location) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($medicines as $medicine) {
        $stmt->bind_param('ssisss', 
            $medicine[0], // name
            $medicine[1], // category
            $medicine[2], // stock_quantity
            $medicine[3], // expiry_date
            $medicine[4], // price
            $medicine[5]  // location
        );
        
        if ($stmt->execute()) {
            $product_id = $conn->insert_id;
            echo "Added medicine: " . $medicine[0] . "\n";
            
            // Add to medicine inventory first (required by foreign key constraint)
            $inventory_stmt = $conn->prepare("INSERT INTO medicine_inventory (medicine_id, name, stock_quantity) VALUES (?, ?, ?)");
            $inventory_stmt->bind_param('isi', $product_id, $medicine[0], $medicine[2]);
            
            if (!$inventory_stmt->execute()) {
                throw new Exception("Error adding to inventory: " . $inventory_stmt->error);
            }
            
            // Now add to expiry monitoring
            $expiry_stmt = $conn->prepare("INSERT INTO expiry_monitoring (medicine_id, name, expiry_date, quantity) VALUES (?, ?, ?, ?)");
            $expiry_stmt->bind_param('issi', $product_id, $medicine[0], $medicine[3], $medicine[2]);
            
            if (!$expiry_stmt->execute()) {
                throw new Exception("Error adding to expiry monitoring: " . $expiry_stmt->error);
            }
        } else {
            throw new Exception("Error adding medicine: " . $medicine[0] . " - " . $stmt->error);
        }
    }

    // Commit transaction
    $conn->commit();
    echo "\nAll medicines have been added successfully!\n";

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    $stmt->close();
    $conn->close();
}
?> 
