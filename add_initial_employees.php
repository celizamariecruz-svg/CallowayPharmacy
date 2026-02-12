<?php
include 'db_connection.php';

$employees = [
    ['Dr. Maria Santos', 'pharmacist', '08:00', '17:00'],
    ['Juan dela Cruz', 'pharmacist', '16:00', '01:00'],
    ['Ana Reyes', 'cashier', '08:00', '17:00'],
    ['Mark Tan', 'cashier', '16:00', '01:00'],
    ['Rose Garcia', 'staff', '08:00', '17:00'],
    ['Pedro Mendoza', 'staff', '16:00', '01:00']
];

$stmt = $conn->prepare("INSERT INTO employees (name, role, shift_start, shift_end) VALUES (?, ?, ?, ?)");

foreach ($employees as $employee) {
    $stmt->bind_param('ssss', $employee[0], $employee[1], $employee[2], $employee[3]);
    if ($stmt->execute()) {
        echo "Added employee: " . $employee[0] . "\n";
    } else {
        echo "Error adding employee: " . $employee[0] . " - " . $stmt->error . "\n";
    }
}

$stmt->close();
$conn->close();
?> 
