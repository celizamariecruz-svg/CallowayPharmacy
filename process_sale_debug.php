<?php
/**
 * DEBUG VERSION OF PROCESS SALE
 * This version logs everything and returns detailed error info
 */

// Log to file
$logFile = __DIR__ . '/process_sale_debug.log';
file_put_contents($logFile, "=== REQUEST START: " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);

// Set headers
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

file_put_contents($logFile, "1. Headers set\n", FILE_APPEND);

try {
    // Clean output
    while (ob_get_level()) ob_end_clean();
    ob_start();
    
    file_put_contents($logFile, "2. Output buffer started\n", FILE_APPEND);
    
    // Require database
    require_once 'db_connection.php';
    file_put_contents($logFile, "3. Database connected\n", FILE_APPEND);
    
    // Require Auth
    require_once 'Auth.php';
    file_put_contents($logFile, "4. Auth class loaded\n", FILE_APPEND);
    
    // Check authentication
    $auth = new Auth($conn);
    file_put_contents($logFile, "5. Auth object created\n", FILE_APPEND);
    
    if (!$auth->isLoggedIn()) {
        file_put_contents($logFile, "ERROR: Not logged in\n", FILE_APPEND);
        if (ob_get_level()) ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login first']);
        exit;
    }
    
    file_put_contents($logFile, "6. User is logged in\n", FILE_APPEND);
    
    // Get current user
    $currentUser = $auth->getCurrentUser();
    file_put_contents($logFile, "7. Current user: " . $currentUser['username'] . "\n", FILE_APPEND);
    
    // Get JSON input
    $input = file_get_contents('php://input');
    file_put_contents($logFile, "8. Input received: " . strlen($input) . " bytes\n", FILE_APPEND);
    file_put_contents($logFile, "   Data: " . $input . "\n", FILE_APPEND);
    
    $data = json_decode($input, true);
    
    if (!$data) {
        file_put_contents($logFile, "ERROR: Invalid JSON\n", FILE_APPEND);
        if (ob_get_level()) ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    file_put_contents($logFile, "9. JSON decoded successfully\n", FILE_APPEND);
    
    // Validate items
    if (!isset($data['items']) || empty($data['items'])) {
        file_put_contents($logFile, "ERROR: No items in cart\n", FILE_APPEND);
        if (ob_get_level()) ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'No items in cart']);
        exit;
    }
    
    file_put_contents($logFile, "10. Found " . count($data['items']) . " items\n", FILE_APPEND);
    
    // Get payment details
    $paymentMethod = $data['payment_method'] ?? 'cash';
    $amountPaid = floatval($data['amount_paid'] ?? 0);
    
    file_put_contents($logFile, "11. Payment: $paymentMethod, Amount: $amountPaid\n", FILE_APPEND);
    
    // Calculate total
    $total = 0;
    foreach ($data['items'] as $item) {
        $total += floatval($item['price']) * intval($item['quantity']);
    }
    
    file_put_contents($logFile, "12. Total calculated: $total\n", FILE_APPEND);
    
    // Start transaction
    $conn->begin_transaction();
    file_put_contents($logFile, "13. Transaction started\n", FILE_APPEND);
    
    // Generate sale reference
    $saleRef = 'SALE-' . date('YmdHis') . '-' . rand(1000, 9999);
    file_put_contents($logFile, "14. Sale reference: $saleRef\n", FILE_APPEND);
    
    // Insert sale
    $cashier = $currentUser['full_name'] ?? $currentUser['username'];
    $change = $amountPaid - $total;
    
    $stmt = $conn->prepare("INSERT INTO sales (sale_reference, total, payment_method, paid_amount, change_amount, cashier) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdsdds", $saleRef, $total, $paymentMethod, $amountPaid, $change, $cashier);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert sale: " . $stmt->error);
    }
    
    $saleId = $conn->insert_id;
    file_put_contents($logFile, "15. Sale inserted: ID=$saleId\n", FILE_APPEND);
    
    // Insert sale items
    $itemStmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($data['items'] as $item) {
        $productId = intval($item['product_id']);
        $quantity = intval($item['quantity']);
        $price = floatval($item['price']);
        $lineTotal = $price * $quantity;
        
        // Get product name
        $prodStmt = $conn->prepare("SELECT name, stock_quantity FROM products WHERE product_id = ?");
        $prodStmt->bind_param("i", $productId);
        $prodStmt->execute();
        $result = $prodStmt->get_result();
        $product = $result->fetch_assoc();
        
        if (!$product) {
            throw new Exception("Product $productId not found");
        }
        
        $itemStmt->bind_param("iisdid", $saleId, $productId, $product['name'], $price, $quantity, $lineTotal);
        
        if (!$itemStmt->execute()) {
            throw new Exception("Failed to insert sale item: " . $itemStmt->error);
        }
        
        // Update stock
        $newStock = $product['stock_quantity'] - $quantity;
        $updateStmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
        $updateStmt->bind_param("ii", $newStock, $productId);
        $updateStmt->execute();
        
        file_put_contents($logFile, "16. Item added: ProdID=$productId, Qty=$quantity, Stock: {$product['stock_quantity']} -> $newStock\n", FILE_APPEND);
    }
    
    // Commit
    $conn->commit();
    file_put_contents($logFile, "17. Transaction committed\n", FILE_APPEND);
    
    // Success response
    $response = [
        'success' => true,
        'message' => 'Transaction successful',
        'sale_id' => $saleId,
        'sale_reference' => $saleRef,
        'total_amount' => $total,
        'amount_paid' => $amountPaid,
        'change' => $change
    ];
    
    file_put_contents($logFile, "18. SUCCESS - Sending response\n", FILE_APPEND);
    file_put_contents($logFile, json_encode($response) . "\n", FILE_APPEND);
    
    if (ob_get_level()) ob_end_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    file_put_contents($logFile, "ERROR EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($logFile, "Stack: " . $e->getTraceAsString() . "\n", FILE_APPEND);
    
    if (isset($conn)) {
        $conn->rollback();
    }
    
    if (ob_get_level()) ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}

file_put_contents($logFile, "=== REQUEST END ===\n\n", FILE_APPEND);

if (isset($conn)) {
    $conn->close();
}
exit;
?>
