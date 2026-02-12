<?php
$servername = "localhost";
$username = "root";
$password = "";

// Create connection without specifying database
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS calloway_pharmacy";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully\n";
} else {
    echo "Error creating database: " . $conn->error . "\n";
}

// Select the database
$conn->select_db("calloway_pharmacy");

// Read and execute the schema file
$schema = file_get_contents('database_schema.sql');
$statements = array_filter(array_map('trim', explode(';', $schema)));

foreach ($statements as $statement) {
    if (!empty($statement) && !preg_match('/^--/', $statement)) {
        if ($conn->query($statement) === TRUE) {
            echo "Executed: " . substr($statement, 0, 50) . "...\n";
        } else {
            echo "Error executing statement: " . $conn->error . "\n";
            echo "Statement: " . $statement . "\n";
        }
    }
}

$conn->close();
echo "Database setup completed!\n";
?>
