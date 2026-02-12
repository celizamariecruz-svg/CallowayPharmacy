<?php
include 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicineId = $_POST['medicine'];
    $quantity = $_POST['quantity'];
    $customer = $_POST['customer'];

    // Validate input
    if (empty($medicineId) || empty($quantity) || empty($customer)) {
        die('All fields are required.');
    }

    // Check stock availability
    $stockQuery = "SELECT stock FROM medicines WHERE id = ?";
    $stmt = $conn->prepare($stockQuery);
    $stmt->bind_param('i', $medicineId);
    $stmt->execute();
    $result = $stmt->get_result();
    $medicine = $result->fetch_assoc();

    if ($medicine['stock'] < $quantity) {
        die('Insufficient stock.');
    }

    // Process transaction
    $conn->begin_transaction();
    try {
        // Deduct stock
        $updateStockQuery = "UPDATE medicines SET stock = stock - ? WHERE id = ?";
        $stmt = $conn->prepare($updateStockQuery);
        $stmt->bind_param('ii', $quantity, $medicineId);
        $stmt->execute();

        // Record transaction
        $transactionQuery = "INSERT INTO transactions (medicine_id, quantity, customer_name, transaction_date) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($transactionQuery);
        $stmt->bind_param('iis', $medicineId, $quantity, $customer);
        $stmt->execute();

        $conn->commit();
        echo 'Transaction processed successfully.';
    } catch (Exception $e) {
        $conn->rollback();
        die('Transaction failed: ' . $e->getMessage());
    }
}
?>
