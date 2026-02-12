<?php
$servername = "localhost";
$username = "root";
$password = "";

// Create connection without database
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Drop database if exists
$conn->query("DROP DATABASE IF EXISTS calloway_pharmacy");

// Create database
if ($conn->query("CREATE DATABASE calloway_pharmacy") === TRUE) {
    echo "Database created successfully\n";
} else {
    echo "Error creating database: " . $conn->error . "\n";
}

// Select the database
$conn->select_db("calloway_pharmacy");

// Create tables
$tables = [
    "CREATE TABLE products (
        product_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        price DECIMAL(10, 2) NOT NULL,
        stock_quantity INT NOT NULL DEFAULT 0,
        location VARCHAR(255) NULL,
        is_active TINYINT(1) DEFAULT 1,
        image_url VARCHAR(255) NULL,
        barcode VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE stock_movements (
        movement_id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        movement_type ENUM('IN','OUT','ADJUSTMENT') NOT NULL,
        quantity INT NOT NULL,
        reference_type VARCHAR(50) NULL,
        reference_id INT NULL,
        previous_stock INT NOT NULL,
        new_stock INT NOT NULL,
        notes TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
        INDEX idx_product (product_id),
        INDEX idx_movement_type (movement_type),
        INDEX idx_reference (reference_type, reference_id),
        INDEX idx_created (created_at)
    )",

    "CREATE TABLE employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        role VARCHAR(255) NOT NULL,
        shift_start TIME,
        shift_end TIME,
        on_leave TINYINT(1) DEFAULT 0
    )",

    "CREATE TABLE sales (
        sale_id INT AUTO_INCREMENT PRIMARY KEY,
        sale_reference VARCHAR(255) NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'Cash',
        paid_amount DECIMAL(10,2) DEFAULT 0.00,
        change_amount DECIMAL(10,2) DEFAULT 0.00,
        cashier VARCHAR(255) DEFAULT 'POS',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE sale_items (
        item_id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        quantity INT NOT NULL,
        line_total DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT
    )"
];

foreach ($tables as $table) {
    if ($conn->query($table) === TRUE) {
        echo "Table created successfully\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
}

// Insert sample data
$inserts = [
    "INSERT INTO products (name, category, price, stock_quantity) VALUES
    ('Paracetamol', 'Pain Relief', 2.50, 500),
    ('Ibuprofen', 'Pain Relief', 3.00, 300),
    ('Cough Syrup', 'Cold & Flu', 5.00, 200),
    ('Vitamin C', 'Supplements', 10.00, 400),
    ('Antibiotic Cream', 'First Aid', 7.50, 75)",

    "INSERT INTO employees (name, role, shift_start, shift_end) VALUES
    ('John Doe', 'Pharmacist', '09:00:00', '17:00:00'),
    ('Jane Smith', 'Assistant', '10:00:00', '18:00:00')"
];

foreach ($inserts as $insert) {
    if ($conn->query($insert) === TRUE) {
        echo "Data inserted successfully\n";
    } else {
        echo "Error inserting data: " . $conn->error . "\n";
    }
}

$conn->close();
echo "Database setup completed!\n";
?>
